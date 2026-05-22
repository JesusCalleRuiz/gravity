@extends('layouts.app')

@section('title', 'Análisis de Lote - SpeedVision AI')

@section('content')
    <main class="flex-1 flex flex-col px-6 py-8 lg:px-10 max-w-7xl w-full mx-auto">
        <!-- Back Navigation -->
        <div class="mb-6">
            <a href="{{ route('videos.index') }}" class="inline-flex items-center gap-2 text-sm text-slate-400 hover:text-white transition-colors">
                <span class="material-symbols-outlined !text-sm">arrow_back</span>
                <span>Volver al Dashboard</span>
            </a>
        </div>

        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
            <div>
                <h1 class="text-2xl font-extrabold text-white tracking-tight" id="show-title">{{ $video->title }}</h1>
                <p class="text-xs text-slate-400 mt-1">Registrado el {{ $video->created_at->format('d/m/Y H:i:s') }}</p>
            </div>
            <div id="status-badge-container">
                <!-- El estado se actualizará dinámicamente -->
                @if($video->status === 'completed')
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                        <span class="size-1.5 rounded-full bg-emerald-400"></span> Completado
                    </span>
                @elseif($video->status === 'processing')
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                        <span class="size-1.5 rounded-full bg-amber-400 animate-pulse"></span> Analizando...
                    </span>
                @elseif($video->status === 'pending')
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-slate-800 text-slate-400 border border-slate-700">
                        <span class="size-1.5 rounded-full bg-slate-500"></span> En Cola de Espera
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-red-500/10 text-red-400 border border-red-500/20">
                        <span class="size-1.5 rounded-full bg-red-400"></span> Fallo de análisis
                    </span>
                @endif
            </div>
        </div>

        <!-- 1. PANTALLA DE PROGRESO (Visible mientras se procesa) -->
        <div id="processing-view" class="{{ in_array($video->status, ['pending', 'processing']) ? '' : 'hidden' }} bg-[#1a2530] border border-slate-200/5 rounded-2xl p-8 md:p-12 text-center max-w-2xl mx-auto w-full my-8 shadow-2xl">
            <div class="relative size-24 mx-auto mb-6">
                <!-- Círculo de Carga Pulsante -->
                <div class="absolute inset-0 rounded-full border-4 border-primary/20"></div>
                <div class="absolute inset-0 rounded-full border-4 border-t-primary animate-spin"></div>
                <div class="absolute inset-0 flex items-center justify-center text-primary">
                    <span class="material-symbols-outlined !text-3xl animate-pulse">settings_suggest</span>
                </div>
            </div>

            <h3 class="text-xl font-bold text-white mb-2" id="progress-header">Procesando lote de videos</h3>
            <p class="text-slate-400 text-sm max-w-md mx-auto mb-8" id="progress-subheader">
                El modelo de aprendizaje automático está escaneando la salida de la rampa para clasificar y reportar errores de cinta en tiempo real.
            </p>

            <!-- Progress Bar -->
            <div class="max-w-md mx-auto mb-3">
                <div class="flex items-center justify-between mb-1.5">
                    <span class="text-xs font-semibold text-slate-400" id="progress-status-text">Analizando fotogramas...</span>
                    <span class="text-sm font-extrabold text-primary" id="progress-value-text">{{ $video->progress }}%</span>
                </div>
                <div class="w-full bg-[#111418] rounded-full h-3 overflow-hidden border border-slate-700/30">
                    <div id="analysis-progress-bar" class="bg-primary h-full rounded-full animate-progress-stripe transition-all duration-300" style="width: {{ $video->progress }}%"></div>
                </div>
            </div>

            <div class="inline-flex items-center gap-2 px-3 py-1 bg-slate-900/60 rounded-full border border-slate-800 text-[10px] text-slate-400">
                <span class="size-1.5 bg-sky-400 rounded-full animate-ping"></span>
                <span>Conexión de telemetría activa</span>
            </div>
        </div>

        <!-- 2. PANTALLA DE ERROR (Visible si falla) -->
        <div id="failed-view" class="{{ $video->status === 'failed' ? '' : 'hidden' }} bg-[#1a2530] border border-red-500/20 rounded-2xl p-8 text-center max-w-2xl mx-auto w-full my-8 shadow-2xl">
            <div class="size-16 bg-red-500/10 rounded-full flex items-center justify-center text-red-400 mx-auto mb-4">
                <span class="material-symbols-outlined !text-3xl">report_problem</span>
            </div>
            <h3 class="text-lg font-bold text-white mb-2">Error en el Análisis</h3>
            <p class="text-slate-400 text-sm max-w-md mx-auto mb-6" id="error-message-text">
                {{ $video->error_message ?? 'El script de análisis de Python ha devuelto un código de error inesperado o falló la decodificación del video.' }}
            </p>
            <a href="{{ route('videos.import') }}" class="inline-flex items-center justify-center gap-2 rounded-xl h-11 px-5 bg-slate-800 text-white text-xs font-bold hover:bg-slate-700 transition-colors">
                <span class="material-symbols-outlined !text-sm">replay</span>
                <span>Volver a intentar</span>
            </a>
        </div>

        <!-- 3. DETALLE DE ANÁLISIS FINALIZADO (Visible cuando se completa) -->
        <div id="completed-view" class="{{ $video->status === 'completed' ? '' : 'hidden' }} grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            
            <!-- Video Player (Col 7) -->
            <div class="lg:col-span-7 space-y-6">
                <div class="bg-[#1a2530] border border-slate-200/5 rounded-2xl overflow-hidden shadow-2xl">
                    <div class="relative aspect-video bg-black flex items-center justify-center">
                        <video id="taco-video" class="w-full h-full object-contain" controls preload="auto">
                            <!-- El source se cargará dinámicamente si se sube un video real -->
                            <source src="{{ asset($video->file_path) }}" type="video/mp4">
                            Tu navegador no soporta el tag de video.
                        </video>
                    </div>
                    <div class="p-5 border-t border-slate-200/10 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-slate-400">play_circle</span>
                            <span class="text-xs font-semibold text-slate-300">Reproductor interactivo inteligente</span>
                        </div>
                        <span class="text-xs text-slate-500">Pulsa en los errores de la línea de tiempo para saltar al fotograma.</span>
                    </div>
                </div>

                <!-- Resumen de Métricas de Calidad -->
                <div class="grid grid-cols-3 gap-4" id="metrics-grid">
                    @php
                        $metrics = $video->result_data ?? [];
                    @endphp
                    <div class="bg-[#1a2530] border border-slate-200/5 p-4 rounded-xl text-center shadow-lg">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Tacos Analizados</span>
                        <span class="text-xl font-extrabold text-white mt-1 block" id="metric-analyzed">{{ $metrics['tacos_analyzed'] ?? 0 }}</span>
                    </div>
                    <div class="bg-[#1a2530] border border-slate-200/5 p-4 rounded-xl text-center shadow-lg">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Tasa de Éxito</span>
                        <span class="text-xl font-extrabold text-emerald-400 mt-1 block" id="metric-accuracy">
                            {{ isset($metrics['tacos_analyzed']) && isset($metrics['detections_count']) && $metrics['tacos_analyzed'] > 0 
                               ? round((($metrics['tacos_analyzed'] - $metrics['detections_count']) / $metrics['tacos_analyzed']) * 100, 1) 
                               : 100 }}%
                        </span>
                    </div>
                    <div class="bg-[#1a2530] border border-slate-200/5 p-4 rounded-xl text-center shadow-lg">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Confianza IA</span>
                        <span class="text-xl font-extrabold text-primary mt-1 block" id="metric-confidence">{{ $metrics['accuracy'] ?? 0 }}%</span>
                    </div>
                </div>
            </div>

            <!-- Detections Timeline / Sidebar (Col 5) -->
            <div class="lg:col-span-5 bg-[#1a2530] border border-slate-200/5 rounded-2xl shadow-2xl p-6 space-y-6">
                <div>
                    <h3 class="text-lg font-bold text-white mb-1 flex items-center justify-between">
                        <span>Línea de Tiempo de Errores</span>
                        <span class="text-xs bg-red-500/10 text-red-400 border border-red-500/20 px-2 py-0.5 rounded-full font-extrabold" id="error-badge-count">
                            {{ $metrics['detections_count'] ?? 0 }}
                        </span>
                    </h3>
                    <p class="text-xs text-slate-400">Detecciones de fallos en la cinta transportadora por segundo.</p>
                </div>

                <!-- Timeline Scroll Container -->
                <div class="space-y-4 max-h-[420px] overflow-y-auto pr-2 custom-scrollbar" id="timeline-container">
                    @if(isset($metrics['detections']) && count($metrics['detections']) > 0)
                        @foreach($metrics['detections'] as $index => $detection)
                            <div onclick="seekTo({{ $detection['timestamp'] }})" 
                                 class="p-4 bg-slate-900/50 hover:bg-slate-900 rounded-xl border border-slate-800 hover:border-red-500/30 transition-all cursor-pointer flex items-start gap-3.5 group">
                                <div class="size-8 rounded-lg bg-red-500/10 flex items-center justify-center text-red-400 shrink-0 group-hover:scale-105 transition-transform mt-0.5">
                                    <span class="material-symbols-outlined !text-lg">warning</span>
                                </div>
                                <div class="space-y-1 flex-1 min-w-0">
                                    <div class="flex justify-between items-center gap-2">
                                        <span class="text-xs font-extrabold uppercase tracking-wide text-red-400">
                                            {{ str_replace('_', ' ', $detection['error_type']) }}
                                        </span>
                                        <span class="text-[10px] font-bold text-slate-400 bg-slate-800 px-2 py-0.5 rounded-full">
                                            {{ sprintf('%02d:%02d', floor($detection['timestamp'] / 60), $detection['timestamp'] % 60) }}s
                                        </span>
                                    </div>
                                    <p class="text-xs text-slate-300 font-medium leading-relaxed">{{ $detection['description'] }}</p>
                                    <div class="flex items-center gap-1.5 pt-1">
                                        <span class="text-[10px] text-slate-500">Confianza:</span>
                                        <span class="text-[10px] font-bold text-slate-400">{{ round($detection['confidence'] * 100) }}%</span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <!-- No Detections (Perfect quality!) -->
                        <div class="p-8 text-center bg-emerald-500/5 rounded-xl border border-emerald-500/10 text-emerald-400">
                            <span class="material-symbols-outlined !text-4xl mb-2">verified</span>
                            <h4 class="font-bold text-sm text-white mb-1">¡Salida Perfecta!</h4>
                            <p class="text-xs text-slate-400">No se detectó ningún error de envoltura, rotura u obstrucción en este lote.</p>
                        </div>
                    @endif
                </div>

                <!-- Export Report Button -->
                <button onclick="showToast('Informe exportado en PDF (Simulado)', 'success')" 
                        class="w-full flex items-center justify-center gap-2 rounded-xl h-12 bg-slate-800 hover:bg-slate-700 text-white text-xs font-bold transition-all border border-slate-700/50">
                    <span class="material-symbols-outlined !text-sm">download</span>
                    <span>Descargar Informe de Calidad</span>
                </button>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="px-6 py-8 mt-auto border-t border-slate-200 dark:border-slate-800 text-center">
        <p class="text-xs text-slate-500">
            © 2026 SpeedVision AI. Control de calidad de tacos con aprendizaje automático.
        </p>
    </footer>
@endsection

@section('scripts')
<script>
    // Variable global del video player
    const player = document.getElementById('taco-video');
    const isCurrentlyProcessing = {{ in_array($video->status, ['pending', 'processing']) ? 'true' : 'false' }};
    const videoId = {{ $video->id }};
    let pollingInterval = null;

    // Control del Reproductor
    function seekTo(seconds) {
        if (player) {
            player.currentTime = seconds;
            player.play();
            player.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            showToast(`Saltando al segundo ${seconds}s`, 'success');
        }
    }

    // Iniciar Polling si está en cola o procesando
    if (isCurrentlyProcessing) {
        startProgressPolling();
    }

    function startProgressPolling() {
        console.log("Iniciando telemetría de cola por Polling...");
        pollingInterval = setInterval(fetchProgress, 1000); // Consulta cada 1 segundo
    }

    function fetchProgress() {
        fetch(`/api/videos/${videoId}/progress`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    clearInterval(pollingInterval);
                    showToast('Error en telemetría.', 'error');
                    return;
                }

                console.log("Telemetry update:", data);

                // Actualizar barra de progreso
                const progressBar = document.getElementById('analysis-progress-bar');
                const progressValText = document.getElementById('progress-value-text');
                const progressStatusText = document.getElementById('progress-status-text');

                progressBar.style.width = data.progress + '%';
                progressValText.textContent = data.progress + '%';

                // Cambiar textos según el estado
                if (data.status === 'processing') {
                    progressStatusText.textContent = `Analizando fotogramas... (${data.progress}%)`;
                    document.getElementById('status-badge-container').innerHTML = `
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                            <span class="size-1.5 rounded-full bg-amber-400 animate-pulse"></span> Analizando...
                        </span>
                    `;
                }

                // Si ha finalizado
                if (data.status === 'completed') {
                    clearInterval(pollingInterval);
                    showToast('¡Análisis completado con éxito!', 'success');
                    
                    // Actualizar UI completa
                    updateUIAfterCompletion(data);
                }

                // Si ha fallado
                if (data.status === 'failed') {
                    clearInterval(pollingInterval);
                    showToast('El análisis ha fallado.', 'error');
                    
                    document.getElementById('processing-view').classList.add('hidden');
                    document.getElementById('failed-view').classList.remove('hidden');
                    document.getElementById('error-message-text').textContent = data.error_message || 'Fallo de script de Python.';
                    document.getElementById('status-badge-container').innerHTML = `
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-red-500/10 text-red-400 border border-red-500/20">
                            <span class="size-1.5 rounded-full bg-red-400"></span> Fallo de análisis
                        </span>
                    `;
                }
            })
            .catch(err => {
                console.error("Telemetry error:", err);
            });
    }

    // Actualizar dinámicamente toda la vista de análisis una vez finalizado el Job de cola
    function updateUIAfterCompletion(data) {
        // 1. Ocultar progreso
        document.getElementById('processing-view').classList.add('hidden');
        
        // 2. Cargar Badge de Completado
        document.getElementById('status-badge-container').innerHTML = `
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                <span class="size-1.5 rounded-full bg-emerald-400"></span> Completado
            </span>
        `;

        // 3. Rellenar Métricas
        const metrics = data.result_data || {};
        document.getElementById('metric-analyzed').textContent = metrics.tacos_analyzed || 0;
        document.getElementById('metric-confidence').textContent = (metrics.accuracy || 0) + '%';
        
        const errorCount = metrics.detections_count ?? 0;
        document.getElementById('error-badge-count').textContent = errorCount;
        
        const accuracyText = document.getElementById('metric-accuracy');
        if (metrics.tacos_analyzed > 0) {
            const accRate = Math.round(((metrics.tacos_analyzed - errorCount) / metrics.tacos_analyzed) * 100 * 10) / 10;
            accuracyText.textContent = accRate + '%';
        } else {
            accuracyText.textContent = '100%';
        }

        // 4. Cargar Línea de Tiempo de errores
        const timeline = document.getElementById('timeline-container');
        timeline.innerHTML = ''; // Limpiar

        if (metrics.detections && metrics.detections.length > 0) {
            metrics.detections.forEach(detection => {
                const min = Math.floor(detection.timestamp / 60);
                const sec = Math.floor(detection.timestamp % 60);
                const timeString = `${min.toString().padStart(2, '0')}:${sec.toString().padStart(2, '0')}`;
                
                const card = document.createElement('div');
                card.onclick = () => seekTo(detection.timestamp);
                card.className = "p-4 bg-slate-900/50 hover:bg-slate-900 rounded-xl border border-slate-800 hover:border-red-500/30 transition-all cursor-pointer flex items-start gap-3.5 group";
                card.innerHTML = `
                    <div class="size-8 rounded-lg bg-red-500/10 flex items-center justify-center text-red-400 shrink-0 group-hover:scale-105 transition-transform mt-0.5">
                        <span class="material-symbols-outlined !text-lg">warning</span>
                    </div>
                    <div class="space-y-1 flex-1 min-w-0">
                        <div class="flex justify-between items-center gap-2">
                            <span class="text-xs font-extrabold uppercase tracking-wide text-red-400">
                                ${detection.error_type.replace('_', ' ')}
                            </span>
                            <span class="text-[10px] font-bold text-slate-400 bg-slate-800 px-2 py-0.5 rounded-full">
                                ${timeString}s
                            </span>
                        </div>
                        <p class="text-xs text-slate-300 font-medium leading-relaxed">${detection.description}</p>
                        <div class="flex items-center gap-1.5 pt-1">
                            <span class="text-[10px] text-slate-500">Confianza:</span>
                            <span class="text-[10px] font-bold text-slate-400">${Math.round(detection.confidence * 100)}%</span>
                        </div>
                    </div>
                `;
                timeline.appendChild(card);
            });
        } else {
            timeline.innerHTML = `
                <div class="p-8 text-center bg-emerald-500/5 rounded-xl border border-emerald-500/10 text-emerald-400">
                    <span class="material-symbols-outlined !text-4xl mb-2">verified</span>
                    <h4 class="font-bold text-sm text-white mb-1">¡Salida Perfecta!</h4>
                    <p class="text-xs text-slate-400">No se detectó ningún error de envoltura, rotura u obstrucción en este lote.</p>
                </div>
            `;
        }

        // 5. Recargar Source de Video
        if (player) {
            player.load();
        }

        // 6. Mostrar el contenedor completado
        document.getElementById('completed-view').classList.remove('hidden');
    }

    /*
     * =========================================================================
     *  INTEGRACIÓN FUTURA DE WEBSOCKETS (Laravel Echo)
     * =========================================================================
     * Cuando configures Laravel Reverb o Pusher, puedes eliminar el Polling
     * y sustituirlo por el siguiente bloque de código.
     *
     * 1. Asegúrate de instalar Laravel Echo y Pusher JS:
     *    npm install --save-dev laravel-echo pusher-js
     *
     * 2. Descomenta el siguiente código en tu script:
     *
     * import Echo from 'laravel-echo';
     * window.Pusher = require('pusher-js');
     *
     * window.Echo = new Echo({
     *     broadcaster: 'reverb', // o 'pusher'
     *     key: import.meta.env.VITE_REVERB_APP_KEY,
     *     wsHost: import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
     *     wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
     *     wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
     *     forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
     *     enabledTransports: ['ws', 'wss'],
     * });
     *
     * // Suscribirse al canal público de difusión del video
     * window.Echo.channel(`video.${videoId}`)
     *     .listen('VideoProgressUpdated', (e) => {
     *         console.log("WebSocket Recibido:", e);
     *
     *         // Si detecta progreso, actualiza la barra
     *         const progressBar = document.getElementById('analysis-progress-bar');
     *         const progressValText = document.getElementById('progress-value-text');
     *         const progressStatusText = document.getElementById('progress-status-text');
     *
     *         progressBar.style.width = e.progress + '%';
     *         progressValText.textContent = e.progress + '%';
     *
     *         if (e.status === 'processing') {
     *             progressStatusText.textContent = `Analizando fotogramas... (${e.progress}%)`;
     *         }
     *
     *         if (e.status === 'completed') {
     *             showToast('¡Análisis completado mediante WebSockets!', 'success');
     *             updateUIAfterCompletion(e);
     *         }
     *
     *         if (e.status === 'failed') {
     *             showToast('Fallo recibido por WebSockets.', 'error');
     *             // Manejo del estado fallido...
     *         }
     *     });
     */
</script>
@endsection
