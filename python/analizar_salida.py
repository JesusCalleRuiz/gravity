"""
Motor de análisis de la app de producción — sustituye a analyze_tacos.py.

Regla de integración: este script NO reimplementa nada del análisis
biomecánico. Reutiliza tal cual el paquete `core/` del proyecto de TFM
(entrenador): pose, eventos, biomecánica, features y modelo. Cualquier
cambio en la lógica de análisis se hace ahí, no aquí.

`entrenador` y este proyecto comparten el mismo Python portable
(C:/APPS/python-3.11.1-embed-amd64), así que basta con añadir su ruta a
sys.path — no hace falta empaquetar ni reinstalar dependencias.
"""

from __future__ import annotations

import argparse
import json
import os
import sys
from pathlib import Path

# Suprimir logs pesados de TensorFlow/MediaPipe/OpenCV antes de importarlos
# (igual que hacía analyze_tacos.py).
os.environ.setdefault("TF_CPP_MIN_LOG_LEVEL", "3")
os.environ.setdefault("TF_ENABLE_ONEDNN_OPTS", "0")
os.environ.setdefault("GLOG_minloglevel", "3")
os.environ.setdefault("MEDIAPIPE_DISABLE_GPU", "1")

# En Windows, stdout usa por defecto la página de códigos de la consola
# (cp1252), no UTF-8, incluso redirigido a un pipe. Laravel decodifica la
# salida de este script con json_decode(), que exige UTF-8 válido — sin
# esto, cualquier tilde/ñ en las frases generadas rompe el JSON en PHP.
sys.stdout.reconfigure(encoding="utf-8")

RUTA_ENTRENADOR = Path(r"C:\var\www\html\entrenador")
sys.path.insert(0, str(RUTA_ENTRENADOR))

from core import config as core_config  # noqa: E402

# La caché de landmarks/eventos de core.cache se guarda por defecto dentro
# de entrenador/data/cache/. Los vídeos de producción no son parte del
# dataset de entrenamiento: se redirige la caché a una carpeta propia de
# esta app para no mezclar ambos, reasignando el atributo del módulo (mismo
# mecanismo que usan los tests de entrenador para aislar su caché).
RUTA_CACHE_GRAVITY = Path(__file__).resolve().parent.parent / "storage" / "app" / "analisis_cache"
RUTA_CACHE_GRAVITY.mkdir(parents=True, exist_ok=True)
core_config.CACHE_DIR = RUTA_CACHE_GRAVITY

# Copias normalizadas de orientación (ver normalizar_orientacion_video):
# fuera de public/uploads/videos a propósito, es un archivo de trabajo
# interno, no algo que deba quedar accesible por URL ni mezclado con los
# vídeos que sirve la app.
RUTA_TMP_GRAVITY = Path(__file__).resolve().parent.parent / "storage" / "app" / "tmp"
RUTA_TMP_GRAVITY.mkdir(parents=True, exist_ok=True)

from core import biomecanica, calidad, experimentos, extraccion, feedback, visualizacion  # noqa: E402
from core.eventos import segmentar_zancadas  # noqa: E402
from core.features import calcular_features_por_frame, remuestrear_ciclo  # noqa: E402
from core.interpretabilidad import importancia_por_fase, resumen_importancia_por_fase  # noqa: E402
from core.modelos import predecir_con_abstencion  # noqa: E402
from core.video_io import normalizar_orientacion_video  # noqa: E402

# ---------------------------------------------------------------------------
# Puerta de calidad — criterios propios de esta app, no cubiertos por
# core.calidad (que evalúa % de detección y nº de zancadas, no encuadre).
#
# Nota de alcance: la comprobación de "cámara no perpendicular a la calle"
# que pide la especificación del producto NO está implementada todavía.
# No hay ninguna variable ya validada en core/ para estimarlo con fiabilidad
# desde un único vídeo monocular, y prefiero dejarlo fuera explícitamente a
# inventar un heurístico sin validar (mismo criterio que llevó a rehacer
# analyze_tacos.py). Queda como limitación conocida, no como omisión.
# ---------------------------------------------------------------------------

UMBRAL_BBOX_ALTO_AMBAR = 0.35  # por debajo del 35% -> aviso, nunca rechazo (ver más abajo)


def evaluar_encuadre(landmarks, alto_frame: int) -> dict:
    """
    Proporción mediana de la altura del bounding box del atleta respecto al
    alto del frame, sobre los frames con detección.

    Es solo un AVISO, nunca motivo de rechazo por sí solo: se pensó como
    proxy de "atleta demasiado pequeño para que MediaPipe trackee bien", con
    la idea de que un bbox pequeño implica peor detección. Comprobado con un
    caso real (vídeo rechazado a 18.77%, por debajo del 20% que antes
    rechazaba): la detección fue del 97.5% de los frames y la visibilidad
    media de los landmarks del 86% — un tracking perfectamente bueno pese al
    encuadre abierto (típico al grabar la salida completa de una carrera,
    no un plano cercano). El proxy no se sostiene; el motivo de rechazo real
    ya lo cubre core.calidad.evaluar_calidad_clip con el % de frames sin
    detección, que sí mide la degradación directamente en vez de adivinarla.
    """
    detectados = landmarks[landmarks["deteccion"]]
    if detectados.empty or alto_frame <= 0:
        return {"proporcion_bbox_alto_mediana": None, "motivo": "sin ningún frame con detección"}

    alturas_bbox = detectados["bbox_y_max"] - detectados["bbox_y_min"]
    proporcion_mediana = float((alturas_bbox / alto_frame).median())
    return {"proporcion_bbox_alto_mediana": proporcion_mediana}


def evaluar_calidad_produccion(clip: dict, ancho: int, alto: int, fps: float) -> dict:
    """
    Envuelve core.calidad.evaluar_calidad_clip añadiendo el check de
    encuadre y el aviso (no rechazo, ver más abajo) de fps bajo. Devuelve
    también 'landmarks'/'resultado' para no reprocesar el vídeo dos veces.
    """
    resultado = extraccion.procesar_clip(clip)
    calidad_base = calidad.evaluar_calidad_clip(clip)

    encuadre = evaluar_encuadre(resultado["landmarks"], alto)
    motivos_rojo = list(calidad_base["motivos"]) if calidad_base["semaforo"] == "rojo" else []
    avisos = []

    proporcion = encuadre.get("proporcion_bbox_alto_mediana")
    if proporcion is not None and proporcion < UMBRAL_BBOX_ALTO_AMBAR:
        avisos.append(f"el atleta ocupa el {proporcion:.0%} del alto del encuadre — un encuadre más cercano mejora la precisión")

    # fps bajo: aviso, NO motivo de rechazo. El propio modelo se entrenó con
    # vídeo de YouTube a fps variable (ver limitaciones de entrenador), así
    # que rechazar por debajo de 120fps excluiría el caso de uso principal
    # sin ninguna base empírica de que el modelo rinda peor ahí.
    if fps > 0 and fps < 60:
        avisos.append(
            f"el vídeo está a {fps:.0f} fps — se recomienda grabar a 120 fps o más para que el contacto del pie no salga borroso"
        )

    semaforo = "rojo" if motivos_rojo else calidad_base["semaforo"]
    return {
        **calidad_base,
        "semaforo": semaforo,
        "motivos": motivos_rojo if motivos_rojo else calidad_base["motivos"],
        "avisos": avisos,
        "encuadre": encuadre,
        "_resultado_extraccion": resultado,
    }


def log_progress(progreso: int) -> None:
    print(f"PROGRESS:{progreso}", flush=True)


def construir_referencia_variable(nombre_variable: str, rangos_referencia) -> dict | None:
    if rangos_referencia is None or rangos_referencia.empty:
        return None
    fila = rangos_referencia[rangos_referencia["variable"] == nombre_variable]
    if fila.empty:
        return None
    media, desviacion = fila.iloc[0]["media"], fila.iloc[0]["desviacion"]
    import pandas as pd

    if pd.isna(media) or pd.isna(desviacion):
        return None
    return {"media": float(media), "desviacion": float(desviacion)}


def analizar_zancada(
    zancada: dict,
    tabla_variables_clip,
    rangos_referencia,
    tabla_features_clip,
    bundle: dict | None,
    catalogo_por_id: dict,
) -> dict:
    fila_zancada = tabla_variables_clip[
        (tabla_variables_clip["n_zancada"] == zancada["n_zancada"]) & (tabla_variables_clip["pie"] == zancada["pie"])
    ]
    variables_zancada = dict(zip(fila_zancada["variable"], fila_zancada["valor"]))

    variables_json = {}
    for nombre_variable, valor in variables_zancada.items():
        referencia = construir_referencia_variable(nombre_variable, rangos_referencia)
        metadatos = biomecanica.CATALOGO_VARIABLES.get(nombre_variable, {})
        situacion = (
            feedback.situar_en_rango(valor, referencia["media"], referencia["desviacion"]) if referencia else None
        )
        variables_json[nombre_variable] = {
            "valor": None if pd_is_nan(valor) else float(valor),
            "unidad": metadatos.get("unidad"),
            "nombre_legible": metadatos.get("nombre_legible", nombre_variable),
            "frase": feedback.generar_frase_variable(nombre_variable, valor, referencia),
            "situacion": situacion,
            "media_referencia": referencia["media"] if referencia else None,
            "desviacion_referencia": referencia["desviacion"] if referencia else None,
        }

    predicciones_json = []
    if bundle is not None:
        try:
            ciclo = remuestrear_ciclo(
                tabla_features_clip, zancada["frame_contacto"], zancada["frame_contacto_siguiente"], bundle["config"]["n_puntos_ciclo"]
            )
            entrada = experimentos.aplicar_escalador(bundle["escalador"], ciclo).reshape(1, *ciclo.shape)
            probabilidades = bundle["modelo"].predict(entrada, verbose=0)[0]
            predicciones = predecir_con_abstencion(
                probabilidades.reshape(1, -1), bundle["umbrales"], margen=bundle["config"].get("margen_abstencion", 0.1)
            )[0]
            etiqueta_texto = {1: "SÍ", 0: "no", -1: "no concluyente"}
            fiabilidad_por_clase = bundle["config"].get("fiabilidad_por_clase", {})

            # Interpretabilidad: qué tramo de fase (0-100% del ciclo) influyó
            # más en el veredicto de cada clase, por ablación/oclusión (ver
            # core.interpretabilidad). Es la respuesta a "por qué dice esto
            # el modelo", no solo "qué dice" — barato de calcular (10
            # inferencias extra sobre un modelo pequeño) y muy defendible
            # frente a un tribunal de TFM de IA.
            resumen_fase_por_clase = {}
            try:
                importancias = importancia_por_fase(bundle["modelo"], bundle["escalador"], ciclo, n_segmentos=10)
                for info_fase in resumen_importancia_por_fase(importancias, bundle["ids_etiquetas"]):
                    resumen_fase_por_clase[info_fase["id_etiqueta"]] = info_fase
            except Exception:
                # La interpretabilidad es un extra sobre la predicción ya
                # calculada: si falla por lo que sea, no debe tirar abajo el
                # análisis completo de la zancada.
                pass

            for id_etiqueta, prob, pred in zip(bundle["ids_etiquetas"], probabilidades, predicciones):
                info_error = catalogo_por_id.get(id_etiqueta, {})
                info_fiabilidad = fiabilidad_por_clase.get(id_etiqueta, {})
                info_fase = resumen_fase_por_clase.get(id_etiqueta)
                predicciones_json.append(
                    {
                        "id_etiqueta": id_etiqueta,
                        "nombre": info_error.get("nombre", id_etiqueta),
                        "descripcion": info_error.get("descripcion"),
                        "probabilidad": float(prob),
                        "prediccion": int(pred),
                        "texto": etiqueta_texto[int(pred)],
                        # De core.experimentos.exportar_bundle_red_b: AUC de
                        # esta clase sobre un atleta nunca entrenado. Con
                        # datasets tan pequeños una clase puede no tener
                        # señal real aunque el resto del sistema funcione, y
                        # eso hay que decírselo a quien lee el informe, no
                        # solo confiar en que el umbral ya lo filtra.
                        "fiable": info_fiabilidad.get("fiable"),
                        "auc_calibracion": info_fiabilidad.get("auc_calibracion"),
                        "fase_mas_influyente_inicio": info_fase["fase_mas_influyente_inicio"] if info_fase else None,
                        "fase_mas_influyente_fin": info_fase["fase_mas_influyente_fin"] if info_fase else None,
                        "perfil_importancia_fase": info_fase["perfil"] if info_fase else None,
                    }
                )
        except ValueError:
            # Zancada demasiado corta/atípica para remuestrear a un ciclo completo:
            # se omite la predicción de esta zancada en vez de fallar todo el análisis.
            pass

    return {
        "n_zancada": zancada["n_zancada"],
        "pie": zancada["pie"],
        "frame_contacto": zancada["frame_contacto"],
        "frame_despegue": zancada["frame_despegue"],
        "frame_contacto_siguiente": zancada["frame_contacto_siguiente"],
        "variables": variables_json,
        "predicciones": predicciones_json,
    }


def pd_is_nan(valor) -> bool:
    import math

    try:
        return math.isnan(float(valor))
    except (TypeError, ValueError):
        return False


def run_analysis(video_path: str) -> dict:
    if not os.path.exists(video_path):
        return {"status": "failed", "error_message": f"No se encuentra el vídeo en: {video_path}"}

    log_progress(5)

    # Normalizar orientación ANTES de leer un solo frame: cv2.VideoCapture
    # (lo que usa MediaPipe en todo core/) ignora la rotación/espejo que
    # algunos vídeos llevan en los metadatos del contenedor, aunque
    # cualquier reproductor SÍ la aplique. Comprobado en producción: un
    # vídeo con esa transformación pendiente hacía que el atleta apareciera
    # a un lado en pantalla pero MediaPipe registrara su cadera al otro
    # lado del encuadre, invirtiendo la lateralidad de todo el análisis —
    # y solo en ESE vídeo, no en otros grabados sin esa marca. `video_path`
    # (nombre/carpeta) se conserva para las rutas de salida; la lectura de
    # frames a partir de aquí usa la copia normalizada si hizo falta.
    ruta_normalizada = RUTA_TMP_GRAVITY / f"normalizado_{Path(video_path).stem}.mp4"
    video_path_lectura = str(normalizar_orientacion_video(video_path, ruta_normalizada))

    import cv2

    captura = cv2.VideoCapture(video_path_lectura)
    if not captura.isOpened():
        return {"status": "failed", "error_message": "No se ha podido abrir el vídeo."}
    ancho = int(captura.get(cv2.CAP_PROP_FRAME_WIDTH))
    alto = int(captura.get(cv2.CAP_PROP_FRAME_HEIGHT))
    fps = float(captura.get(cv2.CAP_PROP_FPS)) or 30.0
    captura.release()

    clip_id = "gravity_" + Path(video_path).stem
    clip = {
        "id": clip_id,
        "ruta_archivo": video_path_lectura,
        # Vídeo grabado expresamente para esta app, no metraje de YouTube a
        # velocidad reducida: factor 1 siempre, y se da por confirmado (no
        # hay paso de confirmación manual en producción).
        "factor_camara_lenta": 1,
        "factor_camara_lenta_confirmado": True,
    }

    log_progress(15)
    reporte_calidad = evaluar_calidad_produccion(clip, ancho, alto, fps)
    resultado_extraccion = reporte_calidad.pop("_resultado_extraccion")

    if reporte_calidad["semaforo"] == "rojo":
        return {
            "status": "rejected",
            "quality": {k: v for k, v in reporte_calidad.items() if k != "sugerencia_camara_lenta"},
        }

    log_progress(30)
    landmarks = resultado_extraccion["landmarks"]
    eventos = resultado_extraccion["eventos_heuristicos"]
    zancadas = segmentar_zancadas(eventos)

    if not zancadas:
        return {
            "status": "rejected",
            "quality": {
                "semaforo": "rojo",
                "motivos": ["no se ha detectado ninguna zancada completa"],
                "avisos": reporte_calidad.get("avisos", []),
            },
        }

    log_progress(45)
    tabla_variables_clip = biomecanica.calcular_tabla_biomecanica(
        clip_id, landmarks, zancadas, resultado_extraccion["fps_efectivo"], incluye_arranque=True
    )
    tabla_features_clip = calcular_features_por_frame(landmarks, fps=resultado_extraccion["fps_efectivo"])
    rangos_referencia = experimentos.cargar_rangos_referencia()
    catalogo = experimentos.cargar_catalogo_errores()
    catalogo_por_id = {e["id"]: e for e in catalogo}

    modelo_disponible = experimentos.bundle_disponible()
    bundle = experimentos.cargar_bundle_red_b() if modelo_disponible else None

    log_progress(60)
    zancadas_json = [
        analizar_zancada(z, tabla_variables_clip, rangos_referencia, tabla_features_clip, bundle, catalogo_por_id)
        for z in zancadas
    ]

    log_progress(80)
    nombre_video_anotado = f"anotado_{Path(video_path).stem}.mp4"
    ruta_video_anotado = Path(video_path).parent / nombre_video_anotado
    visualizacion.generar_video_anotado(
        video_path_lectura, landmarks, eventos, ruta_video_anotado, resultado_extraccion["fps_efectivo"],
        zancadas_analizadas=zancadas_json,
    )

    log_progress(100)
    # Solo cuenta errores CONFIRMADOS (prediccion==1): un "no concluyente"
    # (-1) no es un error detectado, y contarlo junto a los confirmados
    # sería el mismo problema de honestidad que ya se corrigió en el PDF
    # (core.feedback._figura_resumen_ejecutivo). Views/Controller de gravity
    # leen esta clave tal cual para las tarjetas de vídeo.
    detections_count = sum(
        1 for zancada in zancadas_json for p in zancada.get("predicciones", []) if p["prediccion"] == 1
    )

    return {
        "status": "completed",
        "quality": {k: v for k, v in reporte_calidad.items() if k != "sugerencia_camara_lenta"},
        "fps_efectivo": resultado_extraccion["fps_efectivo"],
        "n_zancadas": len(zancadas_json),
        "modelo_disponible": modelo_disponible,
        "atleta_calibracion": bundle["config"]["atleta_calibracion"] if bundle else None,
        "zancadas": zancadas_json,
        "processed_video_filename": nombre_video_anotado,
        "detections_count": detections_count,
    }


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Análisis biomecánico de la salida de tacos (motor de producción)")
    parser.add_argument("--video", type=str, required=True, help="Ruta al vídeo de entrada")
    args = parser.parse_args()

    try:
        resultado = run_analysis(args.video)
    except Exception as e:
        # Frontera del sistema: de aquí para arriba solo hay JSON leído por
        # VideoAnalysisService.php desde stdout. Un traceback crudo ahí
        # rompe el parseo de json_decode (o, peor, se le muestra tal cual
        # al usuario final). El traceback completo va a stderr para poder
        # depurarlo sin filtrarlo a la UI.
        import traceback

        traceback.print_exc(file=sys.stderr)
        resultado = {"status": "failed", "error_message": f"{type(e).__name__}: {e}"}

    print(json.dumps(resultado, ensure_ascii=False))
