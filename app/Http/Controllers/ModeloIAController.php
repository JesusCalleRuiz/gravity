<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;

class ModeloIAController extends Controller
{
    /**
     * Página de transparencia del modelo: comparativa LOSO frente a
     * baselines, curva de calibración y espacio latente aprendido. Estos
     * análisis son a nivel de dataset/modelo (no cambian de un vídeo a
     * otro), así que se generan UNA VEZ desde entrenador (ver
     * python/generar_metodologia_ia.py) y aquí solo se leen y se muestran
     * — nada se recalcula en cada visita.
     */
    public function index()
    {
        $rutaDatos = storage_path('app/ia_metodologia.json');

        $datos = File::exists($rutaDatos)
            ? json_decode(File::get($rutaDatos), true)
            : null;

        return view('modelo-ia.index', ['datos' => $datos]);
    }
}
