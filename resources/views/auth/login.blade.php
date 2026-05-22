@extends('layouts.app')

@section('title', 'Iniciar Sesión - SpeedVision AI')

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
        <h2 class="text-xl font-bold mb-6 text-white text-center">Accede a tu Cuenta</h2>

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

        <form action="{{ route('login') }}" method="POST" class="space-y-5">
            @csrf

            <!-- Email -->
            <div class="space-y-1.5">
                <label for="email" class="text-xs font-semibold text-slate-300 uppercase tracking-wider">Correo Electrónico</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-500">
                        <span class="material-symbols-outlined">mail</span>
                    </span>
                    <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus
                           class="w-full bg-[#111418] border border-slate-700/50 rounded-xl py-3 pl-10 pr-4 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all"
                           placeholder="tu@correo.com">
                </div>
            </div>

            <!-- Password -->
            <div class="space-y-1.5">
                <div class="flex justify-between items-center">
                    <label for="password" class="text-xs font-semibold text-slate-300 uppercase tracking-wider">Contraseña</label>
                    <a href="#" class="text-xs text-primary hover:underline">¿La olvidaste?</a>
                </div>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-500">
                        <span class="material-symbols-outlined">lock</span>
                    </span>
                    <input type="password" name="password" id="password" required
                           class="w-full bg-[#111418] border border-slate-700/50 rounded-xl py-3 pl-10 pr-4 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all"
                           placeholder="••••••••">
                </div>
            </div>

            <!-- Remember Me -->
            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="remember" class="rounded bg-[#111418] border-slate-700 text-primary focus:ring-primary/20">
                    <span class="text-xs text-slate-400">Recordarme</span>
                </label>
            </div>

            <!-- Submit Button -->
            <button type="submit"
                    class="w-full py-3 bg-primary hover:bg-primary/95 text-white font-bold rounded-xl text-sm transition-all hover:scale-[1.01] active:scale-[0.99] shadow-lg shadow-primary/20 flex items-center justify-center gap-2">
                <span>Ingresar</span>
                <span class="material-symbols-outlined !text-sm">arrow_forward</span>
            </button>
        </form>

        <div class="mt-8 pt-6 border-t border-slate-200/10 text-center">
            <p class="text-xs text-slate-400">
                ¿No tienes una cuenta?
                <a href="{{ route('register') }}" class="text-primary font-bold hover:underline">Regístrate gratis</a>
            </p>
        </div>
    </div>
</div>
@endsection
