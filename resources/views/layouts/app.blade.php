<!DOCTYPE html>
<html class="dark" lang="es">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>@yield('title', 'SpeedVision AI - Taco Error Analysis')</title>
    
    <!-- Scripts & Fonts -->
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#3b82f6",
                        "background-light": "#0f172a",
                        "background-dark": "#0b0f17",
                    },
                    fontFamily: {
                        "display": ["Lexend", "sans-serif"]
                    },
                    borderRadius: {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "0.75rem",
                        "full": "9999px"
                    },
                },
            },
        }
    </script>
    
    <style>
        body {
            font-family: 'Lexend', sans-serif;
            background-color: #0b0f17;
            color: #f8fafc;
        }
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #0f172a;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #1e293b;
            border-radius: 10px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #334155;
        }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }
        
        /* Animaciones Premium */
        @keyframes progress-stripe {
            0% { background-position: 1rem 0; }
            100% { background-position: 0 0; }
        }
        .animate-progress-stripe {
            background-image: linear-gradient(
                45deg,
                rgba(255, 255, 255, 0.15) 25%,
                transparent 25%,
                transparent 50%,
                rgba(255, 255, 255, 0.15) 50%,
                rgba(255, 255, 255, 0.15) 75%,
                transparent 75%,
                transparent
            );
            background-size: 1rem 1rem;
            animation: progress-stripe 1s linear infinite;
        }
        
        .bg-radial {
            background: radial-gradient(circle at center, var(--tw-gradient-from) 0%, var(--tw-gradient-to) 100%);
        }
        /* Estilos del sidebar colapsable */
        #sidebar {
            transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        #sidebar.collapsed {
            width: 5rem;
        }
        #sidebar.collapsed .sidebar-text {
            opacity: 0;
            max-width: 0;
            overflow: hidden;
            pointer-events: none;
            display: none;
        }
        #sidebar.collapsed .px-4 {
            padding-left: 0.75rem;
            padding-right: 0.75rem;
        }
        #sidebar.collapsed .p-6 {
            padding-left: 0.75rem;
            padding-right: 0.75rem;
            justify-content: center;
        }
        #sidebar.collapsed .p-4 {
            padding-left: 0.5rem;
            padding-right: 0.5rem;
        }
        
        /* Regla de inicialización para evitar destellos (FOUC) */
        .sidebar-init-collapsed #sidebar {
            width: 5rem !important;
        }
        .sidebar-init-collapsed #sidebar .sidebar-text {
            opacity: 0 !important;
            max-width: 0 !important;
            display: none !important;
        }
        .sidebar-init-collapsed #sidebar .px-4 {
            padding-left: 0.75rem !important;
            padding-right: 0.75rem !important;
        }
        .sidebar-init-collapsed #sidebar .p-6 {
            padding-left: 0.75rem !important;
            padding-right: 0.75rem !important;
            justify-content: center !important;
        }
        .sidebar-init-collapsed #sidebar .p-4 {
            padding-left: 0.5rem !important;
            padding-right: 0.5rem !important;
        }
        @yield('styles')
    </style>
    <script>
        // Inicialización rápida para evitar saltos visuales al recargar
        (function() {
            if (localStorage.getItem('sidebar-collapsed') === 'true') {
                document.documentElement.classList.add('sidebar-init-collapsed');
            }
        })();
    </script>
</head>
<body class="bg-[#0b0f17] text-slate-100 min-h-screen custom-scrollbar flex">
    @auth
        <!-- Side Navigation (Colapsable) -->
        <aside id="sidebar" class="w-64 border-r border-slate-200 dark:border-slate-800 flex flex-col h-screen sticky top-0 bg-[#0f172a] dark:bg-[#0c121e] shrink-0 z-30">
            <!-- Header -->
            <div class="p-6 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3 overflow-hidden">
                    <div class="bg-primary rounded-lg p-1.5 flex items-center justify-center text-white shrink-0">
                        <span class="material-symbols-outlined text-white text-2xl">bolt</span>
                    </div>
                    <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sidebar-text transition-all duration-300">SpeedVision</h1>
                </div>
                <!-- Toggle Button -->
                <button id="sidebar-toggle" onclick="toggleSidebar()" class="text-slate-400 hover:text-white transition-colors shrink-0">
                    <span id="toggle-icon" class="material-symbols-outlined">menu_open</span>
                </button>
            </div>
            
            <!-- Navigation Links -->
            <nav class="flex-1 px-4 space-y-1 mt-4 overflow-x-hidden">
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg {{ Request::routeIs('videos.index') ? 'text-primary bg-primary/10 dark:bg-primary/5 active-nav' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800' }} transition-all" href="{{ route('videos.index') }}" title="My Videos">
                    <span class="material-symbols-outlined {{ Request::routeIs('videos.index') ? 'text-primary' : '' }} shrink-0">video_library</span>
                    <span class="font-semibold text-sm sidebar-text transition-all duration-300 whitespace-nowrap">My Videos</span>
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg {{ Request::routeIs('videos.import') ? 'text-primary bg-primary/10 dark:bg-primary/5 active-nav' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800' }} transition-all" href="{{ route('videos.import') }}" title="Analizar Salida">
                    <span class="material-symbols-outlined {{ Request::routeIs('videos.import') ? 'text-primary' : '' }} shrink-0">upload</span>
                    <span class="font-medium text-sm sidebar-text transition-all duration-300 whitespace-nowrap">Analizar Salida</span>
                </a>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg {{ Request::routeIs('modelo-ia') ? 'text-primary bg-primary/10 dark:bg-primary/5 active-nav' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800' }} transition-all" href="{{ route('modelo-ia') }}" title="Cómo funciona la IA">
                    <span class="material-symbols-outlined {{ Request::routeIs('modelo-ia') ? 'text-primary' : '' }} shrink-0">neurology</span>
                    <span class="font-medium text-sm sidebar-text transition-all duration-300 whitespace-nowrap">Cómo funciona la IA</span>
                </a>
            </nav>

            <!-- User Section -->
            <div class="p-4 border-t border-slate-200 dark:border-slate-800 overflow-hidden">
                <div class="flex items-center gap-3 mb-6 p-2 rounded-xl bg-slate-100 dark:bg-slate-800/50">
                    <div class="size-10 rounded-full bg-slate-300 dark:bg-slate-700 bg-cover bg-center shrink-0" style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuBdFkf87WPHg7HguS-IdH6GWJpU_TidqNoktFjeZ38lR6i9xh-pOqLu2FSrwQzGVxa1s5M6mzWj01WdS2JVmo1kJVDNMLvPOOCh56v5e5N3zhmBX-CfHMcO1LR1XdFs55qJ6YbPPLaaSdyTevHB-6Hj34S9NH4DP_CX6ggu9dudPJaXqShDZuIsR80v0M--7RaNvHqIXHMjmxKG5FAcIZBHLowx-H5IsxfucftCUnC541IcK1nZrEvixE_G203w51TQYmffyZ4-s7_4')"></div>
                    <div class="overflow-hidden sidebar-text transition-all duration-300 flex-1">
                        <p class="text-sm font-semibold truncate text-slate-900 dark:text-white">{{ Auth::user()->name }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400 truncate">Elite Controller</p>
                    </div>
                </div>
                <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">
                    @csrf
                </form>
                <a class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors cursor-pointer" onclick="event.preventDefault(); document.getElementById('logout-form').submit();" title="Logout">
                    <span class="material-symbols-outlined shrink-0">logout</span>
                    <span class="font-medium text-sm sidebar-text transition-all duration-300 whitespace-nowrap">Logout</span>
                </a>
            </div>
        </aside>
    @endauth

    <div class="flex-1 flex flex-col min-h-screen overflow-y-auto bg-[#0b0f17]">
        @yield('content')
    </div>

    <!-- Sistema de Toasts Notificaciones -->
    <div id="toast-container" class="fixed bottom-5 right-5 z-50 flex flex-col gap-3"></div>

    <script>
        // Función global para mostrar Toasts profesionales
        function showToast(message, type = 'success') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            
            const bgClass = type === 'success' ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400' : 'bg-red-500/10 border-red-500/30 text-red-400';
            const icon = type === 'success' ? 'check_circle' : 'error';
            
            toast.className = `flex items-center gap-3 px-4 py-3 rounded-xl border ${bgClass} shadow-2xl backdrop-blur-md translate-y-5 opacity-0 transition-all duration-300 min-w-[280px] max-w-[400px]`;
            toast.innerHTML = `
                <span class="material-symbols-outlined">${icon}</span>
                <span class="text-xs font-semibold flex-1">${message}</span>
                <button class="text-slate-400 hover:text-white transition-colors" onclick="this.parentElement.remove()">
                    <span class="material-symbols-outlined !text-sm">close</span>
                </button>
            `;
            
            container.appendChild(toast);
            
            // Animación de entrada
            setTimeout(() => {
                toast.classList.remove('translate-y-5', 'opacity-0');
            }, 10);
            
            // Auto-eliminar
            setTimeout(() => {
                toast.classList.add('translate-y-5', 'opacity-0');
                setTimeout(() => toast.remove(), 300);
            }, 4500);
        }

        // Toggle interactivo del Sidebar
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const icon = document.getElementById('toggle-icon');
            
            // Quitar inicializador preventivo de destellos
            document.documentElement.classList.remove('sidebar-init-collapsed');
            
            const isCollapsed = sidebar.classList.toggle('collapsed');
            
            if (isCollapsed) {
                icon.textContent = 'menu';
                localStorage.setItem('sidebar-collapsed', 'true');
            } else {
                icon.textContent = 'menu_open';
                localStorage.setItem('sidebar-collapsed', 'false');
            }
        }

        // Sincronizar icono en la carga de la página
        document.addEventListener('DOMContentLoaded', () => {
            const sidebar = document.getElementById('sidebar');
            const icon = document.getElementById('toggle-icon');
            if (sidebar && icon) {
                const isCollapsed = localStorage.getItem('sidebar-collapsed') === 'true';
                if (isCollapsed) {
                    sidebar.classList.add('collapsed');
                    icon.textContent = 'menu';
                } else {
                    sidebar.classList.remove('collapsed');
                    icon.textContent = 'menu_open';
                }
            }
        });

        // Leer mensajes de sesión de Laravel y mostrarlos como Toast
        @if(session('success'))
            showToast("{{ session('success') }}", 'success');
        @endif
        @if(session('error'))
            showToast("{{ session('error') }}", 'error');
        @endif
    </script>
    @yield('scripts')
</body>
</html>
