@extends('layouts.app')

@section('title', 'Análisis de Lote - SpeedVision AI')

@section('content')
    @php
        $metrics = $video->result_data ?? [];
    @endphp
    <main class="flex-1 flex flex-col px-6 py-8 lg:px-10 max-w-7xl w-full mx-auto">
        <!-- Back Navigation -->
        <div class="mb-6">
            <a href="{{ route('videos.index') }}" class="inline-flex items-center gap-2 text-sm text-slate-400 hover:text-white transition-colors">
                <span class="material-symbols-outlined !text-sm">arrow_back</span>
                <span>Volver al Dashboard</span>
            </a>
        </div>

        <!-- Header -->
        <div class="flex flex-wrap justify-between items-end gap-4 mb-8">
            <div class="flex flex-col gap-1">
                <h1 class="text-3xl font-black text-white tracking-tight" id="show-title">{{ $video->title }}</h1>
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-slate-400 mt-1">
                    <span class="flex items-center gap-1"><span class="material-symbols-outlined !text-sm text-primary">person</span> {{ Auth::user()->name }}</span>
                    <span class="size-1 bg-slate-700 rounded-full"></span>
                    <span class="flex items-center gap-1"><span class="material-symbols-outlined !text-sm">calendar_today</span> {{ $video->created_at->format('M d, Y') }}</span>
                    <span class="size-1 bg-slate-700 rounded-full"></span>
                    <span class="flex items-center gap-1 font-semibold text-primary" id="status-badge-container">
                        @if($video->status === 'completed')
                            <span class="flex items-center gap-1"><span class="material-symbols-outlined !text-sm text-emerald-400">verified</span> Análisis Biomecánico Listo</span>
                        @elseif($video->status === 'processing')
                            <span class="flex items-center gap-1 animate-pulse text-amber-400"><span class="material-symbols-outlined !text-sm text-amber-400">sync</span> Procesando Cinemática...</span>
                        @elseif($video->status === 'pending')
                            <span class="flex items-center gap-1 text-slate-400"><span class="material-symbols-outlined !text-sm">hourglass_empty</span> En Cola de Análisis</span>
                        @else
                            <span class="flex items-center gap-1 text-red-400"><span class="material-symbols-outlined !text-sm">report_problem</span> Fallo en Análisis</span>
                        @endif
                    </span>
                </div>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('videos.report.pdf', $video->id) }}" id="download-report-btn" class="{{ $video->status === 'completed' ? '' : 'hidden' }} flex items-center gap-2 rounded-lg h-10 px-4 bg-slate-800 hover:bg-slate-700 text-sm font-bold text-white transition-colors border border-slate-700/50 shadow-sm">
                    <span class="material-symbols-outlined text-lg text-primary">download</span> Descargar Informe PDF
                </a>
                <a href="{{ $video->status === 'completed' ? asset($video->file_path) : '#' }}" download id="download-video-btn" class="{{ $video->status === 'completed' ? '' : 'hidden' }} flex items-center gap-2 rounded-lg h-10 px-4 bg-slate-800 hover:bg-slate-700 text-sm font-bold text-white transition-colors border border-slate-700/50 shadow-sm">
                    <span class="material-symbols-outlined text-lg text-primary">video_file</span> Descargar Vídeo Analizado
                </a>
            </div>
        </div>

        <!-- 1. PANTALLA DE PROGRESO (Visible mientras se procesa) -->
        <div id="processing-view" class="{{ in_array($video->status, ['pending', 'processing']) ? '' : 'hidden' }} bg-[#1a2530] border border-slate-200/5 rounded-2xl p-8 md:p-12 text-center max-w-2xl mx-auto w-full my-8 shadow-2xl">
            <div class="relative size-24 mx-auto mb-6">
                <div class="absolute inset-0 rounded-full border-4 border-primary/20"></div>
                <div class="absolute inset-0 rounded-full border-4 border-t-primary animate-spin"></div>
                <div class="absolute inset-0 flex items-center justify-center text-primary">
                    <span class="material-symbols-outlined !text-3xl animate-pulse">settings_suggest</span>
                </div>
            </div>

            <h3 class="text-xl font-bold text-white mb-2" id="progress-header">Procesando cinemática de carrera</h3>
            <p class="text-slate-400 text-sm max-w-md mx-auto mb-8" id="progress-subheader">
                Se está reconstruyendo el esqueleto biomecánico de la salida y calculando los ángulos de contacto,
                despegue y extensión de cada zancada.
            </p>

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

        <!-- 2. PANTALLA DE ERROR / RECHAZO (Visible si falla o si la puerta de calidad rechaza el vídeo) -->
        <div id="failed-view" class="{{ $video->status === 'failed' ? '' : 'hidden' }} bg-[#1a2530] border border-red-500/20 rounded-2xl p-8 text-center max-w-2xl mx-auto w-full my-8 shadow-2xl">
            <div class="size-16 bg-red-500/10 rounded-full flex items-center justify-center text-red-400 mx-auto mb-4">
                <span class="material-symbols-outlined !text-3xl">report_problem</span>
            </div>
            <h3 class="text-lg font-bold text-white mb-2" id="failed-title">
                {{ isset($metrics['quality']) ? 'Vídeo rechazado por la puerta de calidad' : 'Error en el análisis biomecánico' }}
            </h3>
            <div class="text-slate-400 text-sm max-w-md mx-auto mb-2 text-left" id="error-message-text">
                @if(isset($metrics['quality']['motivos']) && count($metrics['quality']['motivos']) > 0)
                    <ul class="list-disc list-inside space-y-1">
                        @foreach($metrics['quality']['motivos'] as $motivo)
                            <li>{{ $motivo }}</li>
                        @endforeach
                    </ul>
                @else
                    <p>{{ $video->error_message ?? 'El análisis no ha podido completarse.' }}</p>
                @endif
            </div>
            <a href="{{ route('videos.import') }}" class="inline-flex items-center justify-center gap-2 rounded-xl h-11 px-5 bg-slate-800 text-white text-xs font-bold hover:bg-slate-700 transition-colors mt-4">
                <span class="material-symbols-outlined !text-sm">replay</span>
                <span>Volver a intentar subida</span>
            </a>
        </div>

        <!-- 3. DETALLE DE ANÁLISIS FINALIZADO (Visible cuando se completa) -->
        <div id="completed-view" class="{{ $video->status === 'completed' ? '' : 'hidden' }} grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

            <!-- Video Player + Navegación por zancadas + Evidencia (Col 8) -->
            <div class="lg:col-span-8 space-y-6">
                <div class="relative bg-black rounded-2xl overflow-hidden shadow-2xl border border-slate-800 group">
                    <div class="aspect-video relative bg-slate-950 flex items-center justify-center overflow-hidden">
                        <video id="taco-video" class="w-full h-full object-contain" preload="auto">
                            <source src="{{ $video->status === 'completed' ? asset($video->file_path) : '' }}" type="video/mp4">
                            Tu navegador no soporta el tag de video.
                        </video>
                        <div class="absolute top-4 left-4 flex flex-col gap-2 pointer-events-none">
                            <span class="px-3 py-1 bg-black/60 backdrop-blur-md rounded-full text-[10px] font-mono font-bold text-white flex items-center gap-2 border border-white/10 shadow-lg">
                                <span class="size-2 bg-red-500 rounded-full animate-ping"></span> TRACKING ATLETA
                            </span>
                        </div>
                    </div>

                    <!-- Custom Professional Video Controls -->
                    <div class="bg-slate-900/95 backdrop-blur-md p-4 border-t border-slate-800/80">
                        <div class="relative h-6 flex items-center mb-3 px-2 group/track select-none">
                            <input type="range" id="timeline-slider" min="0" max="100" step="0.1" value="0"
                                   class="absolute inset-0 w-full h-1.5 bg-slate-700 rounded-full appearance-none cursor-pointer outline-none accent-primary focus:ring-0 z-10 opacity-30 group-hover/track:opacity-75 transition-opacity"/>
                            <div id="timeline-progress" class="absolute left-2 h-1.5 bg-primary rounded-full pointer-events-none" style="width: 0%"></div>
                            <!-- Marcas de contacto de cada zancada, una por cada frame_contacto real -->
                            <div id="timeline-hotspots-container" class="absolute inset-0 pointer-events-none"></div>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-4">
                            <div class="flex items-center gap-3.5 text-white">
                                <button onclick="skipTime(-0.1)" class="text-slate-400 hover:text-white transition-colors flex items-center" title="Frame Atrás">
                                    <span class="material-symbols-outlined !text-2xl">skip_previous</span>
                                </button>
                                <button id="play-pause-btn" onclick="togglePlay()" class="text-primary hover:text-primary/80 transition-colors flex items-center" title="Reproducir / Pausa">
                                    <span class="material-symbols-outlined !text-4xl fill-1" id="play-icon">play_circle</span>
                                </button>
                                <button onclick="skipTime(0.1)" class="text-slate-400 hover:text-white transition-colors flex items-center" title="Frame Adelante">
                                    <span class="material-symbols-outlined !text-2xl">skip_next</span>
                                </button>
                                <span class="text-xs font-mono ml-2 text-slate-300" id="video-time">0.00s / 0.00s</span>
                            </div>

                            <div class="flex items-center gap-6 text-slate-400 text-xs">
                                <div class="flex items-center gap-1.5">
                                    <span class="font-bold text-slate-500">VELOCIDAD</span>
                                    <select id="speed-selector" onchange="changeSpeed(this.value)" class="bg-slate-800 border-slate-700 text-xs font-bold text-white rounded-lg focus:ring-primary focus:border-primary py-1 px-2.5 cursor-pointer">
                                        <option value="0.25">0.25x (Lento)</option>
                                        <option value="0.5" selected>0.50x (Estudio)</option>
                                        <option value="1.0">1.00x (Real)</option>
                                    </select>
                                </div>
                                <div class="flex items-center gap-3">
                                    <button onclick="toggleMute()" class="hover:text-white transition-colors flex items-center" id="volume-btn">
                                        <span class="material-symbols-outlined !text-xl">volume_up</span>
                                    </button>
                                    <button onclick="toggleFullscreen()" class="hover:text-white transition-colors flex items-center">
                                        <span class="material-symbols-outlined !text-xl">fullscreen</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Navegación por zancadas -->
                <div class="bg-[#1a2530] border border-slate-200/5 rounded-2xl p-6 shadow-2xl">
                    <h3 class="text-lg font-bold text-white mb-4 flex items-center gap-2.5">
                        <span class="material-symbols-outlined text-primary text-xl">directions_walk</span>
                        <span>Zancadas detectadas</span>
                    </h3>
                    <div class="flex flex-wrap gap-2" id="zancadas-nav"></div>
                    <p class="text-[11px] text-slate-500 mt-3">Pulsa una zancada para saltar al instante del contacto en el vídeo y ver su evidencia debajo.</p>
                </div>

                <!-- Evidencia biomecánica de la zancada seleccionada -->
                <div class="bg-[#1a2530] border border-slate-200/5 rounded-2xl p-6 shadow-2xl">
                    <h3 class="text-lg font-bold text-white mb-4 flex items-center gap-2.5">
                        <span class="material-symbols-outlined text-primary text-xl">analytics</span>
                        <span>Evidencia biomecánica</span>
                    </h3>
                    <div id="zancada-evidencia" class="text-sm text-slate-400">Selecciona una zancada arriba.</div>
                </div>
            </div>

            <!-- Right Sidebar: Resumen real del análisis (Col 4) -->
            <div class="lg:col-span-4 space-y-6">
                <div class="bg-[#1a2530] border border-slate-200/5 rounded-2xl overflow-hidden shadow-2xl">
                    <div class="p-4 border-b border-slate-800/80 flex justify-between items-center bg-slate-900/30">
                        <h3 class="font-bold text-sm text-white tracking-tight flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary text-lg">assessment</span>
                            <span>Resumen del análisis</span>
                        </h3>
                    </div>
                    <div class="p-5 space-y-4" id="resumen-analisis"></div>
                </div>

                <!-- Longitud de zancada real, medida por ciclo (no estimada) -->
                <div class="bg-[#1a2530] border border-slate-200/5 rounded-2xl p-5 shadow-2xl">
                    <h3 class="font-bold text-sm text-white tracking-tight flex items-center gap-2 mb-6">
                        <span class="material-symbols-outlined text-primary text-lg">stacked_line_chart</span>
                        <span>Longitud de zancada</span>
                    </h3>
                    <div class="h-32 w-full flex items-end gap-2 px-1 relative" id="longitud-zancada-chart"></div>
                    <p class="text-[10px] text-slate-500 mt-3">Desplazamiento de cadera por ciclo, en fracción de longitud de pierna. Una barra por zancada detectada.</p>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="px-6 py-8 mt-auto border-t border-slate-200 dark:border-slate-800 text-center">
        <p class="text-xs text-slate-500">
            Análisis biomecánico de salidas de tacos con inteligencia artificial.
        </p>
    </footer>
@endsection

@section('scripts')
<script>
    // ------------------------------------------------------------------
    // Estado inicial (renderizado del lado servidor) y utilidades del
    // reproductor de vídeo. El renderizado de resultados (completado o
    // rechazado) usa SIEMPRE las mismas funciones tanto si la página
    // carga ya terminada como si termina mientras el usuario espera
    // (polling) — una sola implementación, no dos copias divergentes.
    // ------------------------------------------------------------------
    const player = document.getElementById('taco-video');
    const videoId = {{ $video->id }};
    const isCurrentlyProcessing = {{ in_array($video->status, ['pending', 'processing']) ? 'true' : 'false' }};
    let pollingInterval = null;

    const initialData = {
        status: @json($video->status),
        result_data: @json($video->result_data),
        error_message: @json($video->error_message),
        file_path: @json($video->file_path),
    };

    function togglePlay() {
        if (!player) return;
        const playIcon = document.getElementById('play-icon');
        if (player.paused || player.ended) {
            player.play();
            if (playIcon) playIcon.textContent = 'pause_circle';
        } else {
            player.pause();
            if (playIcon) playIcon.textContent = 'play_circle';
        }
    }

    function seekTo(seconds) {
        if (player) {
            player.currentTime = seconds;
            player.play();
        }
    }

    function skipTime(delta) {
        if (!player) return;
        player.currentTime = Math.max(0, Math.min(player.duration || 0, player.currentTime + delta));
        updateTimeline();
    }

    function changeSpeed(speed) {
        if (!player) return;
        player.playbackRate = parseFloat(speed);
    }

    function toggleMute() {
        if (!player) return;
        const volIcon = document.getElementById('volume-btn').querySelector('.material-symbols-outlined');
        player.muted = !player.muted;
        if (volIcon) volIcon.textContent = player.muted ? 'volume_off' : 'volume_up';
    }

    function toggleFullscreen() {
        if (!player) return;
        if (player.requestFullscreen) player.requestFullscreen();
    }

    function updateTimeline() {
        if (player && player.duration) {
            const progressPercent = (player.currentTime / player.duration) * 100;
            document.getElementById('timeline-slider').value = progressPercent;
            document.getElementById('timeline-progress').style.width = progressPercent + '%';
            document.getElementById('video-time').textContent = player.currentTime.toFixed(2) + 's / ' + player.duration.toFixed(2) + 's';
        }
    }

    if (player) {
        player.addEventListener('play', () => {
            const playIcon = document.getElementById('play-icon');
            if (playIcon) playIcon.textContent = 'pause_circle';
            const speedSelector = document.getElementById('speed-selector');
            if (speedSelector) player.playbackRate = parseFloat(speedSelector.value);
        });
        player.addEventListener('pause', () => {
            const playIcon = document.getElementById('play-icon');
            if (playIcon) playIcon.textContent = 'play_circle';
        });
        player.addEventListener('timeupdate', updateTimeline);
        player.addEventListener('loadedmetadata', () => {
            document.getElementById('video-time').textContent = '0.00s / ' + player.duration.toFixed(2) + 's';
            renderHotspots((initialData.result_data && initialData.result_data.zancadas) || []);
        });
        const timelineSlider = document.getElementById('timeline-slider');
        if (timelineSlider) {
            timelineSlider.addEventListener('input', () => {
                if (player.duration) player.currentTime = (parseFloat(timelineSlider.value) / 100) * player.duration;
            });
        }
    }

    // ------------------------------------------------------------------
    // Renderizado de resultados a partir del contrato real de
    // analizar_salida.py (entrenador/core): nada de esto se inventa aquí,
    // solo se muestra lo que el análisis calculó.
    // ------------------------------------------------------------------
    function fpsEfectivo(resultData) {
        return (resultData && resultData.fps_efectivo) || 30;
    }

    function situacionInfo(situacion) {
        if (situacion === 'dentro') return { label: 'dentro del rango de referencia', cls: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-400' };
        if (situacion === 'por_encima') return { label: 'por encima del rango de referencia', cls: 'border-amber-500/30 bg-amber-500/10 text-amber-400' };
        if (situacion === 'por_debajo') return { label: 'por debajo del rango de referencia', cls: 'border-amber-500/30 bg-amber-500/10 text-amber-400' };
        return { label: 'sin rango de referencia todavía', cls: 'border-slate-600/40 bg-slate-700/20 text-slate-400' };
    }

    function renderHotspots(zancadas) {
        const cont = document.getElementById('timeline-hotspots-container');
        if (!cont || !player || !player.duration || !zancadas.length) return;
        const fps = fpsEfectivo(initialData.result_data);
        cont.innerHTML = '';
        zancadas.forEach(z => {
            const t = z.frame_contacto / fps;
            const pct = Math.min(100, Math.max(0, (t / player.duration) * 100));
            const mark = document.createElement('div');
            mark.className = 'absolute top-0 bottom-0 w-0.5 bg-primary/70';
            mark.style.left = pct + '%';
            mark.title = `Zancada ${z.n_zancada} (${z.pie}) — contacto a los ${t.toFixed(2)}s`;
            cont.appendChild(mark);
        });
    }

    function renderZancadasNav(zancadas) {
        const nav = document.getElementById('zancadas-nav');
        if (!nav) return;
        nav.innerHTML = '';
        zancadas.forEach((z, i) => {
            const btn = document.createElement('button');
            btn.dataset.index = i;
            btn.className = claseBotonZancada(i === 0);
            btn.textContent = `Zancada ${z.n_zancada} (${z.pie === 'izq' ? 'izq.' : 'der.'})`;
            btn.onclick = () => seleccionarZancada(zancadas, i);
            nav.appendChild(btn);
        });
        if (zancadas.length) seleccionarZancada(zancadas, 0);
    }

    function claseBotonZancada(activa) {
        return 'px-3 py-1.5 rounded-lg text-xs font-bold border transition-colors ' +
            (activa ? 'bg-primary text-white border-primary' : 'bg-slate-800 text-slate-300 border-slate-700 hover:border-primary/50');
    }

    function seleccionarZancada(zancadas, indice) {
        document.querySelectorAll('#zancadas-nav button').forEach((btn, i) => {
            btn.className = claseBotonZancada(i === indice);
        });
        const zancada = zancadas[indice];
        if (player) {
            seekTo(zancada.frame_contacto / fpsEfectivo(initialData.result_data));
        }
        renderEvidenciaZancada(zancada);
    }

    function renderEvidenciaZancada(zancada) {
        const cont = document.getElementById('zancada-evidencia');
        if (!cont || !zancada) return;

        let html = '';
        const modeloDisponible = initialData.result_data && initialData.result_data.modelo_disponible;

        if (zancada.predicciones && zancada.predicciones.length > 0) {
            html += '<div class="space-y-3 mb-5">';
            zancada.predicciones.forEach(pred => {
                const esSi = pred.prediccion === 1;
                const esNoConcluyente = pred.prediccion === -1;
                const cls = esSi ? 'bg-red-500/10 border-red-500/20 text-red-400'
                    : (esNoConcluyente ? 'bg-slate-700/30 border-slate-600/40 text-slate-300' : 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400');
                const icon = esSi ? 'report' : (esNoConcluyente ? 'help' : 'check_circle');
                html += `
                    <div class="p-4 rounded-xl border ${cls} flex items-start gap-3">
                        <span class="material-symbols-outlined text-lg shrink-0">${icon}</span>
                        <div class="flex-1 min-w-0">
                            <div class="flex justify-between items-center gap-2 flex-wrap">
                                <span class="text-xs font-black uppercase tracking-wide">${pred.nombre}</span>
                                <span class="text-[10px] font-bold text-slate-300 bg-slate-950 px-2 py-0.5 rounded-full border border-white/5">${pred.texto} · confianza ${Math.round(pred.probabilidad * 100)}%</span>
                            </div>
                            ${pred.descripcion ? `<p class="text-xs text-slate-300 font-medium leading-relaxed mt-1.5">${pred.descripcion}</p>` : ''}
                        </div>
                    </div>`;
            });
            html += '</div>';
        } else if (!modeloDisponible) {
            html += `
                <div class="p-4 rounded-xl border border-slate-700/40 bg-slate-800/40 text-slate-300 text-xs mb-5">
                    El modelo de clasificación de errores todavía no está entrenado. A continuación tienes el análisis
                    biomecánico detallado de esta zancada — es la misma evidencia que usará el modelo en cuanto esté disponible.
                </div>`;
        }

        const variables = zancada.variables || {};
        const nombresVariables = Object.keys(variables);
        if (nombresVariables.length > 0) {
            html += '<div class="grid grid-cols-1 md:grid-cols-2 gap-3">';
            nombresVariables.forEach(nombreVar => {
                const v = variables[nombreVar];
                const info = situacionInfo(v.situacion);
                const valorTexto = (v.valor === null || v.valor === undefined) ? '—' : v.valor.toFixed(2);
                html += `
                    <div class="p-3 rounded-lg border ${info.cls}">
                        <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">${v.nombre_legible}</p>
                        <p class="text-sm font-black text-white mt-0.5">${valorTexto} <span class="text-[10px] font-medium text-slate-400">${v.unidad || ''}</span></p>
                        <p class="text-[10px] mt-1 font-semibold">${info.label}</p>
                    </div>`;
            });
            html += '</div>';
        }

        cont.innerHTML = html || '<p class="text-sm text-slate-500">Sin variables calculadas para esta zancada.</p>';
    }

    function renderResumen(resultData) {
        const cont = document.getElementById('resumen-analisis');
        if (!cont) return;
        const modelo = resultData.modelo_disponible;
        let html = `
            <div class="p-4 rounded-xl bg-slate-900/40 border border-slate-800 flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Zancadas analizadas</p>
                    <p class="text-2xl font-black text-white mt-1">${resultData.n_zancadas ?? 0}</p>
                </div>
                <span class="material-symbols-outlined text-slate-500">directions_walk</span>
            </div>
            <div class="p-4 rounded-xl bg-slate-900/40 border border-slate-800 flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Clasificación de errores</p>
                    <p class="text-sm font-bold ${modelo ? 'text-emerald-400' : 'text-amber-400'} mt-1">${modelo ? 'Modelo activo' : 'Todavía no entrenado'}</p>
                </div>
                <span class="material-symbols-outlined ${modelo ? 'text-emerald-400' : 'text-amber-400'}">${modelo ? 'verified' : 'hourglass_empty'}</span>
            </div>`;
        if (resultData.atleta_calibracion) {
            html += `<p class="text-[10px] text-slate-500">Umbrales calibrados dejando fuera a "${resultData.atleta_calibracion}".</p>`;
        }
        const avisos = (resultData.quality && resultData.quality.avisos) || [];
        if (avisos.length) {
            html += `<div class="p-3 rounded-lg bg-amber-500/10 border border-amber-500/20 text-amber-300 text-[10px] space-y-1">
                ${avisos.map(a => `<p>⚠ ${a}</p>`).join('')}
            </div>`;
        }
        cont.innerHTML = html;
    }

    function renderLongitudZancada(zancadas) {
        const cont = document.getElementById('longitud-zancada-chart');
        if (!cont) return;
        cont.innerHTML = '';
        const valores = zancadas.map(z => {
            const v = z.variables && z.variables['longitud_zancada'];
            return (v && v.valor !== null && v.valor !== undefined) ? v.valor : 0;
        });
        const max = Math.max(...valores, 0.01);
        valores.forEach((v, i) => {
            const bar = document.createElement('div');
            bar.className = 'w-full bg-primary/50 hover:bg-primary/70 transition-colors rounded-t';
            bar.style.height = Math.max(4, (v / max) * 100) + '%';
            bar.title = `Zancada ${zancadas[i].n_zancada}: ${v.toFixed(2)}`;
            cont.appendChild(bar);
        });
    }

    function renderResultadoCompleto(resultData, filePath) {
        document.getElementById('processing-view')?.classList.add('hidden');
        document.getElementById('failed-view')?.classList.add('hidden');
        document.getElementById('completed-view')?.classList.remove('hidden');

        if (player && filePath) {
            const src = filePath.startsWith('/') ? filePath : '/' + filePath;
            const sourceEl = player.querySelector('source');
            if (sourceEl) sourceEl.src = src;
            player.load();
        }
        const downloadVideoBtn = document.getElementById('download-video-btn');
        if (downloadVideoBtn && filePath) {
            downloadVideoBtn.href = filePath.startsWith('/') ? filePath : '/' + filePath;
            downloadVideoBtn.classList.remove('hidden');
        }
        document.getElementById('download-report-btn')?.classList.remove('hidden');

        renderResumen(resultData);
        renderLongitudZancada(resultData.zancadas || []);
        renderZancadasNav(resultData.zancadas || []);
    }

    function mostrarRechazoOFallo(errorMessage, resultData) {
        document.getElementById('processing-view')?.classList.add('hidden');
        document.getElementById('completed-view')?.classList.add('hidden');
        document.getElementById('failed-view')?.classList.remove('hidden');

        const motivos = (resultData && resultData.quality && resultData.quality.motivos) || [];
        const titulo = document.getElementById('failed-title');
        const cont = document.getElementById('error-message-text');
        if (titulo) titulo.textContent = motivos.length ? 'Vídeo rechazado por la puerta de calidad' : 'Error en el análisis biomecánico';
        if (cont) {
            if (motivos.length) {
                cont.innerHTML = '<ul class="list-disc list-inside space-y-1">' + motivos.map(m => `<li>${m}</li>`).join('') + '</ul>';
            } else {
                cont.innerHTML = `<p>${errorMessage || 'El análisis no ha podido completarse.'}</p>`;
            }
        }
    }

    // ------------------------------------------------------------------
    // Polling de progreso mientras el análisis está en cola/procesando
    // ------------------------------------------------------------------
    function startProgressPolling() {
        pollingInterval = setInterval(fetchProgress, 1000);
    }

    function fetchProgress() {
        fetch(`/api/videos/${videoId}/progress`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    clearInterval(pollingInterval);
                    return;
                }

                const progressBar = document.getElementById('analysis-progress-bar');
                const progressValText = document.getElementById('progress-value-text');
                const progressStatusText = document.getElementById('progress-status-text');
                if (progressBar) progressBar.style.width = data.progress + '%';
                if (progressValText) progressValText.textContent = data.progress + '%';

                if (data.status === 'processing') {
                    if (progressStatusText) progressStatusText.textContent = `Analizando fotogramas... (${data.progress}%)`;
                    document.getElementById('status-badge-container').innerHTML = `
                        <span class="flex items-center gap-1 animate-pulse text-amber-400">
                            <span class="material-symbols-outlined !text-sm text-amber-400">sync</span> Procesando Cinemática...
                        </span>`;
                }

                if (data.status === 'completed') {
                    clearInterval(pollingInterval);
                    initialData.result_data = data.result_data;
                    initialData.file_path = data.file_path;
                    document.getElementById('status-badge-container').innerHTML = `
                        <span class="flex items-center gap-1"><span class="material-symbols-outlined !text-sm text-emerald-400">verified</span> Análisis Biomecánico Listo</span>`;
                    renderResultadoCompleto(data.result_data, data.file_path);
                }

                if (data.status === 'failed') {
                    clearInterval(pollingInterval);
                    initialData.result_data = data.result_data;
                    document.getElementById('status-badge-container').innerHTML = `
                        <span class="flex items-center gap-1 text-red-400"><span class="material-symbols-outlined !text-sm text-red-400">report_problem</span> Fallo en Análisis</span>`;
                    mostrarRechazoOFallo(data.error_message, data.result_data);
                }
            })
            .catch(err => console.error("Telemetry error:", err));
    }

    // ------------------------------------------------------------------
    // Inicialización
    // ------------------------------------------------------------------
    if (initialData.status === 'completed') {
        renderResultadoCompleto(initialData.result_data, initialData.file_path);
    } else if (initialData.status === 'failed') {
        mostrarRechazoOFallo(initialData.error_message, initialData.result_data);
    }
    if (isCurrentlyProcessing) {
        startProgressPolling();
    }
</script>
@endsection
