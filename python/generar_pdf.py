"""
Generación del informe PDF de una sesión ya analizada.

No repite el análisis: usa el `result_data` que ya se guardó en Laravel
cuando `analizar_salida.py` terminó, y los landmarks ya cacheados de esa
misma ejecución (mismo mecanismo de core.cache, misma caché redirigida a
esta app). Solo añade sobre eso las páginas de portada, resumen ejecutivo,
glosario y limitaciones — la generación en sí vive en
core.feedback.generar_informe_pdf, reutilizada tal cual desde entrenador.
"""

from __future__ import annotations

import argparse
import json
import os
import sys
from pathlib import Path

os.environ.setdefault("TF_CPP_MIN_LOG_LEVEL", "3")
os.environ.setdefault("MEDIAPIPE_DISABLE_GPU", "1")

# Ver analizar_salida.py: stdout debe ser UTF-8 explícito en Windows para
# que Laravel pueda decodificar el JSON sin que las tildes lo rompan.
sys.stdout.reconfigure(encoding="utf-8")

RUTA_ENTRENADOR = Path(r"C:\var\www\html\entrenador")
sys.path.insert(0, str(RUTA_ENTRENADOR))

from core import config as core_config  # noqa: E402

RUTA_CACHE_GRAVITY = Path(__file__).resolve().parent.parent / "storage" / "app" / "analisis_cache"
core_config.CACHE_DIR = RUTA_CACHE_GRAVITY

from core import cache, experimentos  # noqa: E402
from core.feedback import generar_informe_pdf  # noqa: E402
from core.features import FEATURE_VERSION  # noqa: E402


def main() -> None:
    parser = argparse.ArgumentParser(description="Genera el informe PDF de una sesión ya analizada")
    parser.add_argument("--result-json", required=True, help="Ruta a un fichero con el result_data ya guardado")
    parser.add_argument("--output", required=True, help="Ruta donde escribir el PDF")
    parser.add_argument("--titulo", required=True, help="Título de la sesión")
    args = parser.parse_args()

    with open(args.result_json, encoding="utf-8") as f:
        result_data = json.load(f)

    ruta_video_original = result_data.get("original_file_path") or result_data.get("_ruta_video_original")
    if ruta_video_original and not os.path.exists(ruta_video_original):
        # Sesiones analizadas antes de que VideoAnalysisService.php guardara
        # la ruta absoluta guardaban aquí la ruta tal cual la usa Laravel
        # (relativa a public/, p. ej. "uploads/videos/x.mp4"): sin este
        # fallback, os.path.exists() la comprueba contra el directorio de
        # trabajo del proceso Python, nunca la encuentra, y el informe de
        # cualquier vídeo analizado antes de este fix queda inservible.
        RUTA_GRAVITY_PUBLIC = Path(__file__).resolve().parent.parent / "public"
        candidata = RUTA_GRAVITY_PUBLIC / ruta_video_original
        if candidata.exists():
            ruta_video_original = str(candidata)

    if not ruta_video_original or not os.path.exists(ruta_video_original):
        print(json.dumps({"status": "failed", "error_message": "No se encuentra el vídeo original de esta sesión."}))
        sys.exit(1)

    clip_id = "gravity_" + Path(ruta_video_original).stem
    landmarks = cache.cargar_landmarks(clip_id)

    modelo_disponible = result_data.get("modelo_disponible", False)
    version_modelo = "sin modelo entrenado todavía"
    if modelo_disponible and experimentos.bundle_disponible():
        bundle = experimentos.cargar_bundle_red_b()
        version_modelo = f"exportado el {bundle['config'].get('fecha_exportacion', 'fecha desconocida')}"

    from datetime import datetime

    generar_informe_pdf(
        Path(args.output),
        titulo_sesion=args.titulo,
        fecha_generacion=datetime.now().strftime("%d/%m/%Y %H:%M"),
        zancadas=result_data.get("zancadas", []),
        ruta_video=ruta_video_original,
        landmarks=landmarks,
        version_modelo=version_modelo,
        feature_version=FEATURE_VERSION,
    )
    print(json.dumps({"status": "completed", "output": args.output}))


if __name__ == "__main__":
    main()
