@extends('layouts.app')

@section('title', 'Importar Video - SpeedVision AI')

@section('content')
    <main class="flex-1 flex flex-col items-center px-4 py-12">
        <!-- Header Section -->
        <div class="max-w-[800px] w-full text-center mb-8">
            <h1 class="tracking-light text-3xl md:text-4xl font-extrabold leading-tight mb-3 text-white">Importar Video de Producción</h1>
            <p class="text-slate-400 text-sm max-w-xl mx-auto">
                Sube una toma de la cinta transportadora de salida de tacos para realizar una detección automática de fallos y control de calidad con nuestro modelo.
            </p>
        </div>

        <!-- Upload Form -->
        <div class="max-w-[800px] w-full bg-[#1a2530] rounded-2xl shadow-2xl overflow-hidden border border-slate-200/10">
            <div class="p-8 lg:p-12">
                <form id="upload-form" action="{{ route('videos.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    <!-- Title of the Analysis -->
                    <div class="mb-6 space-y-2">
                        <label for="title" class="text-xs font-bold text-slate-300 uppercase tracking-wider">Nombre del Análisis / Lote</label>
                        <input type="text" name="title" id="title" required
                               class="w-full bg-[#111418] border border-slate-700/50 rounded-xl py-3 px-4 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all"
                               placeholder="Ej. Lote_Tacos_A10_Mañana" value="Lote_{{ date('dMy_H:i') }}">
                    </div>

                    <!-- Drag and Drop Area -->
                    <div id="drop-zone" class="flex flex-col items-center gap-6 rounded-2xl border-2 border-dashed border-slate-700 bg-slate-900/30 px-6 py-14 hover:border-primary/50 hover:bg-slate-900/50 transition-all cursor-pointer group relative">
                        <input type="file" name="video" id="video-input" accept="video/mp4,video/quicktime,video/x-matroska" class="hidden">
                        
                        <div class="size-16 rounded-full bg-primary/10 flex items-center justify-center text-primary group-hover:scale-110 transition-transform">
                            <span class="material-symbols-outlined !text-4xl">video_file</span>
                        </div>
                        
                        <div class="flex max-w-[480px] flex-col items-center gap-2 text-center" id="drop-zone-text">
                            <p class="text-lg font-bold leading-tight tracking-[-0.015em] text-white">Arrastra y suelta tu archivo de video aquí</p>
                            <p class="text-slate-400 text-xs">o haz clic para explorar los archivos de tu equipo</p>
                        </div>

                        <!-- Selected File Info -->
                        <div class="hidden flex-col items-center text-center gap-1.5" id="file-info">
                            <span class="text-sm font-bold text-white" id="file-name">filename.mp4</span>
                            <span class="text-xs text-slate-400" id="file-size">0.0 MB</span>
                            <button type="button" id="remove-file-btn" class="mt-2 text-xs text-red-400 hover:text-red-300 font-bold hover:underline flex items-center gap-1">
                                <span class="material-symbols-outlined !text-xs">delete</span> Quitar archivo
                            </button>
                        </div>
                    </div>

                    <!-- Submit Section -->
                    <div class="mt-6 flex justify-end">
                        <button type="submit" id="submit-btn" disabled
                                class="w-full sm:w-auto flex min-w-[160px] cursor-not-allowed items-center justify-center rounded-xl h-12 px-6 bg-slate-700 text-slate-400 text-sm font-bold leading-normal tracking-[0.015em] transition-all">
                            <span class="material-symbols-outlined mr-2">analytics</span> Iniciar Análisis de Calidad
                        </button>
                    </div>
                </form>

                <!-- Active Upload (Axios Progress) -->
                <div id="upload-progress-card" class="hidden mt-8 p-5 bg-[#111418] rounded-xl border border-slate-700/50">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-sm text-primary">movie</span>
                            <span class="text-xs font-semibold text-white truncate max-w-[200px]" id="progress-file-name">video.mp4</span>
                        </div>
                        <span class="text-xs font-bold text-primary" id="progress-percent">0%</span>
                    </div>
                    <div class="w-full bg-[#1a2530] rounded-full h-2 overflow-hidden border border-slate-700/30">
                        <div id="progress-bar" class="bg-primary h-full rounded-full animate-progress-stripe transition-all duration-100" style="width: 0%"></div>
                    </div>
                    <div class="flex items-center justify-between mt-3">
                        <span class="text-[10px] text-slate-400">Subiendo video al servidor...</span>
                        <span class="text-[10px] font-semibold text-slate-300" id="progress-status">Subiendo...</span>
                    </div>
                </div>

                <!-- Guidelines -->
                <div class="mt-8 flex flex-col md:flex-row gap-6 items-start justify-between border-t border-slate-700/50 pt-8">
                    <div class="flex gap-3">
                        <span class="material-symbols-outlined text-primary">info</span>
                        <div class="space-y-1">
                            <p class="text-sm font-semibold text-white">Recomendación para Precisión del Modelo</p>
                            <p class="text-xs text-slate-400">Graba la salida desde un ángulo cenital o lateral directo a la rampa de caída a la altura de la cinta para un óptimo rendimiento de la IA.</p>
                        </div>
                    </div>
                    <div class="text-right whitespace-nowrap">
                        <p class="text-xs text-slate-400">Formatos: MP4, MOV, MKV</p>
                        <p class="text-xs text-slate-400">Tamaño máx: 500MB</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Uploads / Drafts -->
        <div class="max-w-[800px] w-full mt-12">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-extrabold text-white">Análisis Recientes</h3>
                <a href="{{ route('videos.index') }}" class="text-sm font-bold text-primary hover:underline">Ver todos en Dashboard</a>
            </div>
            
            @if(isset($recentVideos) && $recentVideos->count() > 0)
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @foreach($recentVideos as $recent)
                        <div onclick="window.location='{{ route('videos.show', $recent->id) }}'" 
                             class="flex gap-4 p-4 bg-[#1a2530] rounded-xl border border-slate-200/5 hover:border-primary/30 transition-all cursor-pointer hover:scale-[1.01]">
                            
                            <!-- Miniatura generada/placeholder con ícono de video -->
                            <div class="w-20 h-20 rounded-lg bg-slate-900 shrink-0 border border-slate-800 flex items-center justify-center text-slate-600">
                                <span class="material-symbols-outlined !text-3xl">video_file</span>
                            </div>
                            
                            <div class="flex flex-col justify-between py-1 overflow-hidden flex-1">
                                <div class="space-y-0.5">
                                    <h4 class="font-bold text-sm truncate text-white">{{ $recent->title }}</h4>
                                    <p class="text-[11px] text-slate-400">{{ $recent->created_at->diffForHumans() }}</p>
                                </div>
                                <div class="flex items-center gap-1.5 mt-2">
                                    @if($recent->status === 'completed')
                                        <span class="size-2 rounded-full bg-emerald-500"></span>
                                        <span class="text-[10px] font-bold uppercase tracking-tight text-emerald-500">
                                            Analizado ({{ $recent->result_data['detections_count'] ?? 0 }} errores)
                                        </span>
                                    @elseif($recent->status === 'processing')
                                        <span class="size-2 rounded-full bg-amber-500 animate-pulse"></span>
                                        <span class="text-[10px] font-bold uppercase tracking-tight text-amber-500">
                                            Procesando ({{ $recent->progress }}%)
                                        </span>
                                    @elseif($recent->status === 'pending')
                                        <span class="size-2 rounded-full bg-slate-500"></span>
                                        <span class="text-[10px] font-bold uppercase tracking-tight text-slate-400">
                                            En Cola...
                                        </span>
                                    @else
                                        <span class="size-2 rounded-full bg-red-500"></span>
                                        <span class="text-[10px] font-bold uppercase tracking-tight text-red-500">
                                            Fallo
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="p-8 bg-[#1a2530] rounded-xl border border-slate-700/50 text-center">
                    <span class="material-symbols-outlined !text-4xl text-slate-600 mb-2">video_library</span>
                    <p class="text-slate-400 text-sm">No has subido ningún video aún.</p>
                </div>
            @endif
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
<!-- Cargar Axios para la subida con barra de progreso -->
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script>
    const dropZone = document.getElementById('drop-zone');
    const videoInput = document.getElementById('video-input');
    const submitBtn = document.getElementById('submit-btn');
    const fileInfo = document.getElementById('file-info');
    const dropZoneText = document.getElementById('drop-zone-text');
    const fileNameElement = document.getElementById('file-name');
    const fileSizeElement = document.getElementById('file-size');
    const removeFileBtn = document.getElementById('remove-file-btn');
    const uploadForm = document.getElementById('upload-form');
    
    const progressCard = document.getElementById('upload-progress-card');
    const progressFileName = document.getElementById('progress-file-name');
    const progressPercent = document.getElementById('progress-percent');
    const progressBar = document.getElementById('progress-bar');
    const progressStatus = document.getElementById('progress-status');

    // Trigger input click al pulsar drop-zone
    dropZone.addEventListener('click', (e) => {
        // Prevenir loop infinito al pulsar botones hijos
        if (e.target !== removeFileBtn && !removeFileBtn.contains(e.target)) {
            videoInput.click();
        }
    });

    // Cambiar estilos al arrastrar
    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, (e) => {
            e.preventDefault();
            dropZone.classList.add('border-primary', 'bg-slate-900/60');
        }, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, (e) => {
            e.preventDefault();
            dropZone.classList.remove('border-primary', 'bg-slate-900/60');
        }, false);
    });

    // Detectar soltar archivo
    dropZone.addEventListener('drop', (e) => {
        const dt = e.dataTransfer;
        const files = dt.files;
        if (files.length) {
            videoInput.files = files;
            handleFileSelected(files[0]);
        }
    });

    // Detectar seleccion de archivo por click
    videoInput.addEventListener('change', () => {
        if (videoInput.files.length) {
            handleFileSelected(videoInput.files[0]);
        }
    });

    // Manejar el archivo seleccionado
    function handleFileSelected(file) {
        // Validar que sea un video
        const allowedTypes = ['video/mp4', 'video/quicktime', 'video/x-matroska'];
        if (!allowedTypes.includes(file.type) && !file.name.endsWith('.mp4') && !file.name.endsWith('.mov') && !file.name.endsWith('.mkv')) {
            showToast('Formato de archivo no soportado. Debe ser MP4, MOV o MKV.', 'error');
            resetFile();
            return;
        }

        // Validar tamaño (500MB)
        const maxSize = 500 * 1024 * 1024;
        if (file.size > maxSize) {
            showToast('El video excede el límite de 500MB.', 'error');
            resetFile();
            return;
        }

        // Mostrar detalles del archivo
        fileNameElement.textContent = file.name;
        fileSizeElement.textContent = (file.size / (1024 * 1024)).toFixed(2) + ' MB';
        
        dropZoneText.classList.add('hidden');
        dropZone.querySelector('.size-16').classList.add('hidden');
        fileInfo.classList.remove('hidden');
        
        // Habilitar boton submit
        submitBtn.removeAttribute('disabled');
        submitBtn.className = "w-full sm:w-auto flex min-w-[160px] cursor-pointer items-center justify-center rounded-xl h-12 px-6 bg-primary text-white text-sm font-bold leading-normal tracking-[0.015em] hover:bg-primary/95 transition-all shadow-lg shadow-primary/20 hover:scale-[1.01] active:scale-[0.99]";
    }

    // Quitar archivo
    removeFileBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        resetFile();
    });

    function resetFile() {
        videoInput.value = '';
        dropZoneText.classList.remove('hidden');
        dropZone.querySelector('.size-16').classList.remove('hidden');
        fileInfo.classList.add('hidden');
        
        // Deshabilitar submit
        submitBtn.setAttribute('disabled', 'true');
        submitBtn.className = "w-full sm:w-auto flex min-w-[160px] cursor-not-allowed items-center justify-center rounded-xl h-12 px-6 bg-slate-700 text-slate-400 text-sm font-bold leading-normal tracking-[0.015em] transition-all";
    }

    // Controlar el submit del formulario por Axios para el progreso
    uploadForm.addEventListener('submit', (e) => {
        e.preventDefault();
        
        const file = videoInput.files[0];
        if (!file) return;

        const title = document.getElementById('title').value;
        const formData = new FormData();
        formData.append('title', title);
        formData.append('video', file);
        formData.append('_token', '{{ csrf_token() }}');

        // Desactivar UI
        submitBtn.setAttribute('disabled', 'true');
        submitBtn.classList.add('opacity-50');
        dropZone.style.pointerEvents = 'none';

        // Mostrar progreso
        progressFileName.textContent = file.name;
        progressCard.classList.remove('hidden');
        
        axios.post("{{ route('videos.store') }}", formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            },
            onUploadProgress: (progressEvent) => {
                const percentCompleted = Math.round((progressEvent.loaded * 100) / progressEvent.total);
                progressBar.style.width = percentCompleted + '%';
                progressPercent.textContent = percentCompleted + '%';
                
                if (percentCompleted === 100) {
                    progressStatus.textContent = 'Procesando en el servidor...';
                    showToast('Carga completa. Iniciando cola de análisis...', 'success');
                }
            }
        })
        .then(response => {
            // Laravel redireccionará tras el controller exitoso. Redirigimos manualmente al destino
            // El backend devuelve una redirección, Axios la sigue, o podemos leer la respuesta final
            if (response.request.responseURL) {
                window.location.href = response.request.responseURL;
            } else {
                window.location.href = "{{ route('videos.index') }}";
            }
        })
        .catch(error => {
            console.error(error);
            showToast(mensajeErrorSubida(error), 'error');
            submitBtn.removeAttribute('disabled');
            submitBtn.classList.remove('opacity-50');
            dropZone.style.pointerEvents = 'auto';
            progressCard.classList.add('hidden');
        });

        function mensajeErrorSubida(error) {
            const status = error.response?.status;
            // Un vídeo entre post_max_size y upload_max_filesize (ver php.ini)
            // hace que PHP vacíe $_POST antes de que Laravel valide nada: el
            // servidor responde como si faltaran los campos (422) o corta la
            // conexión (sin response) en vez de decir "el archivo es demasiado
            // grande" — el mensaje genérico anterior no daba ninguna pista.
            if (status === 413) {
                return 'El vídeo es demasiado grande para el servidor. Prueba con un archivo más pequeño.';
            }
            if (!error.response) {
                return 'Se ha perdido la conexión durante la subida (puede deberse a que el vídeo es demasiado grande). Inténtalo de nuevo con un archivo más pequeño.';
            }
            const errores = error.response.data?.errors;
            if (errores) {
                return Object.values(errores).flat().join(' ');
            }
            if (error.response.data?.message) {
                return error.response.data.message;
            }
            return 'Error al subir el video. Inténtalo de nuevo.';
        }
    });
</script>
@endsection
