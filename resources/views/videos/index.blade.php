@extends('layouts.app')

@section('title', 'My Video Library - SpeedVision AI')

@section('content')

    <!-- Header Section -->
    <header class="p-8 pb-4">
        <div class="flex justify-between items-end mb-8">
            <div>
                <h2 class="text-3xl font-bold text-slate-900 dark:text-white">Video Library</h2>
                <p class="text-slate-500 dark:text-slate-400 mt-1">Supervisión e historial de grabaciones del sistema de control de calidad.</p>
            </div>
            <a href="{{ route('videos.import') }}" class="bg-primary hover:bg-primary/90 text-white px-5 py-2.5 rounded-lg font-semibold text-sm flex items-center gap-2 transition-all shadow-lg shadow-primary/20">
                <span class="material-symbols-outlined text-xl">upload</span>
                Upload Video
            </a>
        </div>

        <!-- Filter Controls -->
        <div class="flex flex-wrap items-center gap-4 bg-white dark:bg-slate-800/40 p-3 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="relative flex-1 min-w-[240px]">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">search</span>
                <input id="search-input" onkeyup="filterVideos()" class="w-full bg-slate-50 dark:bg-slate-900 border-slate-200 dark:border-slate-700 rounded-lg pl-10 text-sm focus:ring-primary focus:border-primary py-2.5 transition-all text-slate-900 dark:text-white focus:outline-none" placeholder="Search videos..." type="text"/>
            </div>
            <div class="flex items-center gap-2">
                <div class="flex items-center gap-1.5 px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-sm font-medium cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                    <span class="material-symbols-outlined text-primary text-lg">filter_list</span>
                    <select id="status-filter" onchange="filterVideos()" class="bg-transparent border-none text-xs font-bold text-slate-700 dark:text-slate-300 focus:ring-0 cursor-pointer p-0 pr-6">
                        <option value="all" class="bg-slate-900 text-white">Status: All</option>
                        <option value="completed" class="bg-slate-900 text-white">Analyzed</option>
                        <option value="processing" class="bg-slate-900 text-white">Processing</option>
                        <option value="pending" class="bg-slate-900 text-white">In Queue</option>
                        <option value="failed" class="bg-slate-900 text-white">Failed</option>
                    </select>
                </div>
            </div>
        </div>
    </header>

    <!-- Video Grid -->
    <section class="p-8 pt-4">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6" id="video-grid">
            @if($videos->count() > 0)
                @foreach($videos as $video)
                    <div onclick="window.location='{{ route('videos.show', $video->id) }}'" 
                         class="video-card group bg-white dark:bg-slate-800/40 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden hover:shadow-xl hover:shadow-primary/5 transition-all flex flex-col cursor-pointer"
                         data-title="{{ $video->title }}"
                         data-status="{{ $video->status }}">
                        
                        <!-- Video Preview Container -->
                        <div class="relative aspect-video bg-slate-900 overflow-hidden">
                            <video class="absolute inset-0 w-full h-full object-cover opacity-50 transition-all duration-300 group-hover:scale-105" src="{{ asset($video->file_path) }}#t=0.5" preload="metadata" muted playsinline></video>
                            
                            @if($video->status === 'completed')
                                <div class="absolute inset-0 bg-black/20 group-hover:bg-black/10 transition-colors"></div>
                                <div class="absolute top-3 left-3 flex gap-2">
                                    <span class="px-2 py-1 bg-primary text-white text-[10px] font-bold rounded uppercase tracking-wider">Analyzed</span>
                                    <span class="px-2 py-1 bg-black/60 backdrop-blur-md text-white text-[10px] font-bold rounded uppercase tracking-wider">Tacos</span>
                                </div>
                                <button class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                    <div class="size-12 rounded-full bg-primary flex items-center justify-center shadow-lg transform translate-y-2 group-hover:translate-y-0 transition-transform">
                                        <span class="material-symbols-outlined text-white text-3xl fill-1">play_arrow</span>
                                    </div>
                                </button>
                            @elseif($video->status === 'processing')
                                <div class="absolute inset-0 bg-slate-900/60 flex flex-col items-center justify-center">
                                    <div class="size-8 border-2 border-primary/20 border-t-primary rounded-full animate-spin mb-3"></div>
                                    <span class="text-white text-[10px] font-bold uppercase tracking-widest animate-pulse">Processing ({{ $video->progress }}%)</span>
                                </div>
                                <div class="absolute bottom-0 left-0 h-1 bg-primary transition-all duration-500" style="width: {{ $video->progress }}%"></div>
                            @elseif($video->status === 'pending')
                                <div class="absolute inset-0 bg-slate-900/70 flex flex-col items-center justify-center">
                                    <span class="material-symbols-outlined text-slate-400 text-2xl mb-2 animate-bounce">hourglass_empty</span>
                                    <span class="text-slate-300 text-[10px] font-bold uppercase tracking-widest">In Queue</span>
                                </div>
                                <div class="absolute bottom-0 left-0 h-1 bg-slate-700 w-0"></div>
                            @else
                                <div class="absolute inset-0 bg-red-950/60 flex flex-col items-center justify-center">
                                    <span class="material-symbols-outlined text-red-500 text-2xl mb-2">error</span>
                                    <span class="text-red-400 text-[10px] font-bold uppercase tracking-widest">Analysis Failed</span>
                                </div>
                            @endif
                        </div>

                        <!-- Card Body -->
                        <div class="p-4 flex-1 flex flex-col justify-between">
                            <div class="space-y-1">
                                <div class="flex justify-between items-start mb-1">
                                    <h3 class="font-bold text-slate-900 dark:text-white group-hover:text-primary transition-colors truncate pr-2" title="{{ $video->title }}">
                                        {{ $video->title }}
                                    </h3>
                                    <button class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 shrink-0">
                                        <span class="material-symbols-outlined text-lg">more_horiz</span>
                                    </button>
                                </div>
                                <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                                    <span class="material-symbols-outlined text-xs">calendar_month</span>
                                    <span>{{ $video->created_at->format('M d, Y') }}</span>
                                    <span class="size-1 bg-slate-300 dark:bg-slate-600 rounded-full"></span>
                                    <span>{{ $video->created_at->format('h:i A') }}</span>
                                </div>
                            </div>

                            <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700/50 flex items-center justify-between">
                                @if($video->status === 'completed')
                                    @php $count = $video->result_data['detections_count'] ?? 0; @endphp
                                    <div class="flex items-center gap-1.5 {{ $count > 0 ? 'text-red-400' : 'text-emerald-400' }}">
                                        <span class="material-symbols-outlined text-sm font-bold">{{ $count > 0 ? 'warning' : 'check_circle' }}</span>
                                        <span class="text-xs font-bold italic">
                                            {{ $count }} {{ $count == 1 ? 'error' : 'errores' }}
                                        </span>
                                    </div>
                                @elseif($video->status === 'processing')
                                    <div class="flex items-center gap-1.5 text-amber-400 animate-pulse">
                                        <span class="material-symbols-outlined text-sm font-bold">sync</span>
                                        <span class="text-xs font-bold italic">Analizando...</span>
                                    </div>
                                @elseif($video->status === 'pending')
                                    <div class="flex items-center gap-1.5 text-slate-400">
                                        <span class="material-symbols-outlined text-sm font-bold">schedule</span>
                                        <span class="text-xs font-bold italic">En cola</span>
                                    </div>
                                @else
                                    <div class="flex items-center gap-1.5 text-red-500">
                                        <span class="material-symbols-outlined text-sm font-bold">error</span>
                                        <span class="text-xs font-bold italic">Error</span>
                                    </div>
                                @endif
                                <span class="material-symbols-outlined text-slate-300 dark:text-slate-600">chevron_right</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            @else
                <!-- Add New Video Placeholder Card -->
                <div onclick="window.location='{{ route('videos.import') }}'" 
                     class="border-2 border-dashed border-slate-200 dark:border-slate-800 rounded-xl flex flex-col items-center justify-center p-8 bg-white/50 dark:bg-slate-800/10 hover:bg-slate-50 dark:hover:bg-slate-800/20 hover:border-primary transition-all group cursor-pointer aspect-video md:aspect-auto">
                    <div class="size-12 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center mb-4 group-hover:bg-primary/10 group-hover:text-primary transition-all">
                        <span class="material-symbols-outlined text-2xl">add</span>
                    </div>
                    <p class="font-semibold text-sm">Add more videos</p>
                    <p class="text-xs text-slate-500 mt-1">Drag and drop files here</p>
                </div>
            @endif
        </div>

        <!-- No Results Message -->
        <div id="no-results" class="hidden flex flex-col items-center justify-center p-12 text-center">
            <span class="material-symbols-outlined text-slate-500 text-5xl mb-4">search_off</span>
            <h3 class="text-lg font-bold text-slate-300">No se encontraron videos</h3>
            <p class="text-sm text-slate-500 mt-1">Prueba con otra búsqueda o filtro de estado.</p>
        </div>
    </section>

    <!-- Footer -->
    <footer class="px-6 py-8 mt-auto border-t border-slate-200 dark:border-slate-800 text-center">
        <p class="text-xs text-slate-500">
            © 2026 SpeedVision AI. Control de calidad de tacos con aprendizaje automático.
        </p>
    </footer>
@endsection

@section('scripts')
<script>
    // Filtro interactivo de videos en el frontend
    function filterVideos() {
        const query = document.getElementById('search-input').value.toLowerCase();
        const statusFilter = document.getElementById('status-filter').value;
        const cards = document.querySelectorAll('.video-card');
        let visibleCount = 0;

        cards.forEach(card => {
            const title = card.getAttribute('data-title').toLowerCase();
            const status = card.getAttribute('data-status');
            
            const matchesSearch = title.includes(query);
            const matchesStatus = statusFilter === 'all' || status === statusFilter;

            if (matchesSearch && matchesStatus) {
                card.style.display = '';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        const noResults = document.getElementById('no-results');
        if (visibleCount === 0 && cards.length > 0) {
            noResults.classList.remove('hidden');
        } else {
            noResults.classList.add('hidden');
        }
    }

    // Efecto de reproducción en hover para los videos en miniatura
    document.querySelectorAll('.video-card').forEach(card => {
        const video = card.querySelector('video');
        if (video) {
            card.addEventListener('mouseenter', () => {
                video.play().catch(e => {});
            });
            card.addEventListener('mouseleave', () => {
                video.pause();
            });
        }
    });
</script>
@endsection
