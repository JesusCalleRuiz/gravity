@extends('layouts.app')

@section('title', 'Cómo funciona la IA - SpeedVision AI')

@section('content')
    <main class="flex-1 px-4 md:px-10 py-10">
        <div class="max-w-[1100px] mx-auto">
            <div class="mb-8">
                <h1 class="tracking-light text-3xl md:text-4xl font-extrabold leading-tight mb-3 text-white">Cómo funciona la IA</h1>
                <p class="text-slate-400 text-sm max-w-3xl">
                    Gravity no detecta errores técnicos con reglas escritas a mano: usa una red neuronal entrenada y validada
                    con el protocolo estándar en biomecánica (LOSO — Leave-One-Subject-Out), comparada contra modelos
                    clásicos y calibrada para poder decir "no lo sé" cuando no hay evidencia suficiente. Esta página resume
                    esa evidencia.
                </p>
            </div>

            @if (!$datos)
                <div class="p-6 rounded-2xl border border-amber-500/20 bg-amber-500/10 text-amber-300 text-sm">
                    Todavía no se ha generado el análisis del modelo. Ejecuta
                    <code class="px-1.5 py-0.5 rounded bg-slate-900/50 text-xs">python/generar_metodologia_ia.py</code>
                    desde el proyecto entrenador.
                </div>
            @else
                <!-- Ficha técnica -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-10">
                    <div class="p-4 rounded-xl bg-[#1a2530] border border-slate-700/50">
                        <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Atletas en el dataset</p>
                        <p class="text-2xl font-black text-white mt-1">{{ $datos['n_atletas'] }}</p>
                    </div>
                    <div class="p-4 rounded-xl bg-[#1a2530] border border-slate-700/50">
                        <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Zancadas analizadas</p>
                        <p class="text-2xl font-black text-white mt-1">{{ $datos['n_zancadas_red_b'] }}</p>
                    </div>
                    <div class="p-4 rounded-xl bg-[#1a2530] border border-slate-700/50">
                        <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Puntos por ciclo</p>
                        <p class="text-2xl font-black text-white mt-1">{{ $datos['n_puntos_ciclo'] }}</p>
                    </div>
                    <div class="p-4 rounded-xl bg-[#1a2530] border border-slate-700/50">
                        <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Errores detectables</p>
                        <p class="text-2xl font-black text-white mt-1">{{ count($datos['catalogo_errores']) }}</p>
                    </div>
                </div>

                <!-- Arquitectura -->
                <section class="mb-10">
                    <h2 class="text-lg font-bold text-white mb-2">Arquitectura y protocolo</h2>
                    <div class="p-5 rounded-xl bg-[#1a2530] border border-slate-700/50 text-sm text-slate-300 leading-relaxed space-y-2">
                        <p><span class="font-bold text-white">Red B</span> — Conv1D (patrones locales de la forma angular) + BiLSTM bidireccional (dependencias a lo largo del ciclo) + GlobalAveragePooling1D, cabeza multi-etiqueta (sigmoide por clase: un atleta puede cometer varios errores a la vez).</p>
                        <p><span class="font-bold text-white">Validación LOSO</span> — un atleta entero queda fuera para test en cada fold, nunca un split aleatorio: con solo {{ $datos['n_atletas'] }} atletas, mezclar zancadas del mismo atleta en train y test infla las métricas de forma artificial.</p>
                        <p><span class="font-bold text-white">Calibración con abstención</span> — el umbral de decisión de cada clase se ajusta por F1 sobre un atleta de validación nunca visto en entrenamiento; una probabilidad cercana al umbral se reporta como "no concluyente" en vez de forzar un veredicto sin evidencia.</p>
                        <p><span class="font-bold text-white">Preentrenamiento autosupervisado (Red C)</span> — con tan pocos datos etiquetados, el codificador puede preentrenarse reconstruyendo tramos enmascarados de la secuencia sobre clips sin etiquetar, antes del ajuste fino supervisado.</p>
                    </div>
                </section>

                <!-- Comparativa -->
                <section class="mb-10">
                    <h2 class="text-lg font-bold text-white mb-2">Comparativa frente a modelos clásicos (LOSO)</h2>
                    <p class="text-xs text-slate-400 mb-3">Media ± desviación entre folds. F1 macro/micro sobre las {{ $datos['n_atletas'] }} particiones LOSO.</p>
                    <div class="overflow-x-auto rounded-xl border border-slate-700/50">
                        <table class="w-full text-sm text-left">
                            <thead class="bg-slate-900/50 text-slate-400 text-xs uppercase tracking-wider">
                                <tr>
                                    <th class="px-4 py-3">Modelo</th>
                                    <th class="px-4 py-3 text-right">F1 macro</th>
                                    <th class="px-4 py-3 text-right">F1 micro</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800">
                                @foreach ($datos['comparativa'] as $fila)
                                    <tr class="{{ str_contains($fila['modelo'], 'Red B') ? 'bg-primary/5' : '' }}">
                                        <td class="px-4 py-3 font-semibold text-white">{{ $fila['modelo'] }}</td>
                                        <td class="px-4 py-3 text-right text-slate-300 font-mono text-xs">{{ number_format($fila['f1_macro_media'], 3) }} ± {{ number_format($fila['f1_macro_desviacion'], 3) }}</td>
                                        <td class="px-4 py-3 text-right text-slate-300 font-mono text-xs">{{ number_format($fila['f1_micro_media'], 3) }} ± {{ number_format($fila['f1_micro_desviacion'], 3) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- Calibración -->
                <section class="mb-10">
                    <h2 class="text-lg font-bold text-white mb-2">Curva de calibración</h2>
                    <p class="text-xs text-slate-400 mb-3">
                        Probabilidades out-of-fold (nunca vistas en entrenamiento) frente a la frecuencia real de positivos.
                        Sobre la diagonal = calibración perfecta: cuando el modelo dice "70% de probabilidad", ese error
                        aparece de verdad en ~70% de esos casos.
                    </p>
                    <div class="p-4 rounded-xl bg-white inline-block">
                        <img src="{{ asset('images/ia/calibracion.png') }}?v={{ $datos['generado_el'] }}" alt="Curva de calibración" class="max-w-full rounded-lg">
                    </div>
                </section>

                <!-- Espacio latente -->
                @if (file_exists(public_path('images/ia/espacio_latente.png')))
                    <section class="mb-10">
                        <h2 class="text-lg font-bold text-white mb-2">Espacio latente aprendido</h2>
                        <p class="text-xs text-slate-400 mb-3">
                            Proyección PCA (2D) de la representación interna que el codificador aprende para cada zancada,
                            antes de la capa de clasificación — el modelo realmente desplegado, no una copia de laboratorio.
                            Si las zancadas con y sin un error tienden a separarse, es evidencia de que la red capturó
                            estructura real del movimiento, no solo ruido.
                        </p>
                        <div class="p-4 rounded-xl bg-white inline-block">
                            <img src="{{ asset('images/ia/espacio_latente.png') }}?v={{ $datos['generado_el'] }}" alt="Espacio latente del codificador" class="max-w-full rounded-lg">
                        </div>
                    </section>
                @endif

                <p class="text-[11px] text-slate-500">Generado el {{ \Carbon\Carbon::parse($datos['generado_el'])->format('d/m/Y H:i') }} UTC · versión del modelo: {{ $datos['version_modelo'] ?? 'desconocida' }}</p>
            @endif
        </div>
    </main>
@endsection
