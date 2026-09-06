"""
Genera, UNA VEZ, los análisis de "transparencia del modelo" que muestra
gravity en /modelo-ia: comparativa LOSO (baselines vs Red B vs Red B con
preentrenamiento Red C), curva de calibración (reliability diagram,
out-of-fold) y espacio latente aprendido (PCA sobre los embeddings del
codificador realmente desplegado en artifacts/).

Estos análisis son del MODELO/DATASET, no de un vídeo concreto: no se
recalculan en cada visita a la página, se generan aparte con este script
cuando el modelo se reentrena, y gravity solo lee sus resultados ya
guardados (ver app/Http/Controllers/ModeloIAController.php).

Uso: python generar_metodologia_ia.py
"""

from __future__ import annotations

import json
import os
import sys
from datetime import datetime, timezone
from pathlib import Path

os.environ.setdefault("TF_CPP_MIN_LOG_LEVEL", "3")
os.environ.setdefault("MEDIAPIPE_DISABLE_GPU", "1")

RUTA_ENTRENADOR = Path(os.environ.get("RUTA_ENTRENADOR", r"C:\var\www\html\entrenador"))
sys.path.insert(0, str(RUTA_ENTRENADOR))

import numpy as np  # noqa: E402
import tensorflow as tf  # noqa: E402

from core import config as core_config, experimentos, manifest  # noqa: E402
from core.dataset import construir_dataset_red_b, construir_dataset_resumen_zancada  # noqa: E402

RUTA_PUBLIC_IMAGENES = Path(__file__).resolve().parent.parent / "public" / "images" / "ia"
RUTA_JSON_SALIDA = Path(__file__).resolve().parent.parent / "storage" / "app" / "ia_metodologia.json"


def main() -> None:
    RUTA_PUBLIC_IMAGENES.mkdir(parents=True, exist_ok=True)
    RUTA_JSON_SALIDA.parent.mkdir(parents=True, exist_ok=True)

    m = manifest.cargar_manifest()
    clips = manifest.listar_clips(m)
    catalogo = manifest.listar_catalogo_errores(m)
    ids_etiquetas = [e["id"] for e in catalogo]
    nombres_por_id = {e["id"]: e["nombre"] for e in catalogo}

    print("Construyendo datasets...", flush=True)
    d_resumen = construir_dataset_resumen_zancada(clips, ids_etiquetas)
    d_red_b = construir_dataset_red_b(clips, ids_etiquetas)
    n_atletas = len(np.unique(d_red_b["grupos"]))
    print(f"Red B: X={d_red_b['X'].shape}, atletas={n_atletas}", flush=True)

    # 1. Comparativa LOSO: baselines vs Red B vs Red B+preentrenamiento.
    print("Comparativa LOSO (baselines vs Red B vs Red B+preentrenamiento)...", flush=True)
    tabla_comparativa = experimentos.comparar_modelos_loso(
        d_resumen["X"], d_resumen["y"], d_resumen["grupos"],
        d_red_b["X"], d_red_b["y"], d_red_b["grupos"],
    )
    print(tabla_comparativa, flush=True)
    experimentos.exportar_tabla(tabla_comparativa, "comparativa_modelos_loso")

    # 2. Curva de calibración: probabilidades OUT-OF-FOLD de la Red B (no las
    # del modelo final ya entrenado con todo, para no mezclar train/eval).
    print("Curva de calibración (LOSO, out-of-fold)...", flush=True)
    y_test_acumulado, y_prob_acumulado = [], []
    experimentos.evaluar_red_b_loso(
        d_red_b["X"], d_red_b["y"], d_red_b["grupos"],
        callback_probabilidad=lambda idx, y_t, y_p: (y_test_acumulado.append(y_t), y_prob_acumulado.append(y_p)),
    )
    y_test_oof = np.concatenate(y_test_acumulado)
    y_prob_oof = np.concatenate(y_prob_acumulado)
    tabla_calibracion = experimentos.curva_calibracion(y_test_oof, y_prob_oof, ids_etiquetas)
    figura_calibracion = experimentos.figura_curva_calibracion(tabla_calibracion, nombres_por_id)
    experimentos.exportar_figura(figura_calibracion, "curva_calibracion")
    figura_calibracion.savefig(RUTA_PUBLIC_IMAGENES / "calibracion.png", dpi=150, bbox_inches="tight")

    # 3. Espacio latente: embeddings del codificador REALMENTE desplegado
    # (artifacts/red_b.keras), no uno reentrenado aparte, para que la figura
    # describa el modelo que de verdad está en producción.
    print("Espacio latente del codificador desplegado...", flush=True)
    if experimentos.bundle_disponible():
        bundle = experimentos.cargar_bundle_red_b()
        modelo_embeddings = tf.keras.Model(bundle["modelo"].input, bundle["modelo"].get_layer("red_b_pool").output)
        n_features = d_red_b["X"].shape[2]
        X_esc = experimentos.aplicar_escalador(bundle["escalador"], d_red_b["X"].reshape(-1, n_features)).reshape(d_red_b["X"].shape)
        embeddings = modelo_embeddings.predict(X_esc, verbose=0)
        coordenadas_pca = experimentos.reducir_dimensionalidad(embeddings, metodo="pca")
        figura_latente = experimentos.figura_espacio_latente(coordenadas_pca, d_red_b["y"], ids_etiquetas, nombres_por_id, metodo="PCA")
        experimentos.exportar_figura(figura_latente, "espacio_latente_pca")
        figura_latente.savefig(RUTA_PUBLIC_IMAGENES / "espacio_latente.png", dpi=150, bbox_inches="tight")
        version_modelo = bundle["config"].get("fecha_exportacion", "desconocida")
    else:
        print("Sin bundle exportado: se omite el espacio latente.", flush=True)
        version_modelo = None

    # 4. Ficha de metodología para la página.
    datos_pagina = {
        "generado_el": datetime.now(timezone.utc).isoformat(),
        "version_modelo": version_modelo,
        "n_atletas": int(n_atletas),
        "n_zancadas_red_b": int(d_red_b["X"].shape[0]),
        "n_puntos_ciclo": int(d_red_b["X"].shape[1]),
        "n_features": int(d_red_b["X"].shape[2]),
        "comparativa": tabla_comparativa.to_dict(orient="records"),
        "catalogo_errores": [{"id": e["id"], "nombre": e["nombre"]} for e in catalogo],
    }
    with open(RUTA_JSON_SALIDA, "w", encoding="utf-8") as f:
        json.dump(datos_pagina, f, ensure_ascii=False, indent=2)

    print("Listo. JSON:", RUTA_JSON_SALIDA, flush=True)
    print("Imágenes en:", RUTA_PUBLIC_IMAGENES, flush=True)


if __name__ == "__main__":
    main()
