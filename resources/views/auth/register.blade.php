@extends('layouts.app')

@section('title', 'Crear Cuenta - SpeedVision AI')

@section('body-class', 'items-center justify-center bg-radial from-[#1e293b] to-background-dark')

@section('content')
<div class="w-full max-w-md p-6">
    <!-- Logo & Header -->
    <div class="flex flex-col items-center mb-8">
        <div class="size-12 text-primary mb-4 p-2 bg-primary/10 rounded-xl">
            <svg fill="none" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
                <path d="M4 4H17.3334V17.3334H30.6666V30.6666H44V44H4V4Z" fill="currentColor"></path>
            </svg>
        </div>
        <h1 class="text-3xl font-extrabold tracking-tight text-white">SpeedVision AI</h1>
        <p class="text-slate-400 text-sm mt-2">Detección de errores en salida de tacos</p>
    </div>

    <!-- Card -->
    <div class="bg-[#1a2530] border border-slate-200/10 dark:border-[#283039] rounded-2xl shadow-2xl overflow-hidden p-8 backdrop-blur-md bg-opacity-80">
        <h2 class="text-xl font-bold mb-6 text-white text-center">Únete a la Plataforma</h2>

        @if ($errors->any())
            <div class="mb-4 p-3 bg-red-500/10 border border-red-500/20 text-red-400 rounded-lg text-xs space-y-1">
                @foreach ($errors->all() as $error)
                    <p class="flex items-center gap-1">
                        <span class="material-symbols-outlined !text-sm">error</span>
                        {{ $error }}
                    </p>
                @endforeach
            </div>
        @endif

        <form action="{{ route('register') }}" method="POST" class="space-y-4">
            @csrf

            <!-- Name -->
            <div class="space-y-1.5">
                <label for="name" class="text-xs font-semibold text-slate-300 uppercase tracking-wider">Nombre Completo</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-500">
                        <span class="material-symbols-outlined">person</span>
                    </span>
                    <input type="text" name="name" id="name" value="{{ old('name') }}" required autofocus
                           class="w-full bg-[#111418] border border-slate-700/50 rounded-xl py-3 pl-10 pr-4 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all"
                           placeholder="Juan Pérez">
                </div>
            </div>

            <!-- Email -->
            <div class="space-y-1.5">
                <label for="email" class="text-xs font-semibold text-slate-300 uppercase tracking-wider">Correo Electrónico</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-500">
                        <span class="material-symbols-outlined">mail</span>
                    </span>
                    <input type="email" name="email" id="email" value="{{ old('email') }}" required
                           class="w-full bg-[#111418] border border-slate-700/50 rounded-xl py-3 pl-10 pr-4 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all"
                           placeholder="tu@correo.com">
                </div>
            </div>

            <!-- Password -->
            <div class="space-y-1.5">
                <label for="password" class="text-xs font-semibold text-slate-300 uppercase tracking-wider">Contraseña</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-500">
                        <span class="material-symbols-outlined">lock</span>
                    </span>
                    <input type="password" name="password" id="password" required
                           class="w-full bg-[#111418] border border-slate-700/50 rounded-xl py-3 pl-10 pr-4 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all"
                           placeholder="Mínimo 8 caracteres">
                </div>
            </div>

            <!-- Password Confirmation -->
            <div class="space-y-1.5">
                <label for="password_confirmation" class="text-xs font-semibold text-slate-300 uppercase tracking-wider">Confirmar Contraseña</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-500">
                        <span class="material-symbols-outlined">lock_reset</span>
                    </span>
                    <input type="password" name="password_confirmation" id="password_confirmation" required
                           class="w-full bg-[#111418] border border-slate-700/50 rounded-xl py-3 pl-10 pr-4 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all"
                           placeholder="Repite tu contraseña">
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit"
                    class="w-full mt-2 py-3 bg-primary hover:bg-primary/95 text-white font-bold rounded-xl text-sm transition-all hover:scale-[1.01] active:scale-[0.99] shadow-lg shadow-primary/20 flex items-center justify-center gap-2">
                <span>Registrarme</span>
                <span class="material-symbols-outlined !text-sm">person_add</span>
            </button>
        </form>

        <div class="mt-6 pt-6 border-t border-slate-200/10 text-center">
            <p class="text-xs text-slate-400">
                ¿Ya tienes una cuenta?
                <a href="{{ route('login') }}" class="text-primary font-bold hover:underline">Inicia sesión aquí</a>
            </p>
        </div>
    </div>
</div>
@endsection
