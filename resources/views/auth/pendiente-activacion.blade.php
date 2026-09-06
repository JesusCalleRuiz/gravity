@extends('layouts.app')

@section('title', 'Cuenta pendiente de activación - SpeedVision AI')

@section('body-class', 'items-center justify-center bg-radial from-[#1e293b] to-background-dark')

@section('content')
<div class="w-full max-w-md p-6">
    <div class="bg-[#1a2530] border border-slate-200/10 rounded-2xl shadow-2xl overflow-hidden p-8 backdrop-blur-md bg-opacity-80 text-center">
        <div class="size-14 mx-auto mb-6 text-amber-400 bg-amber-400/10 rounded-full flex items-center justify-center">
            <span class="material-symbols-outlined !text-3xl">hourglass_top</span>
        </div>

        <h1 class="text-xl font-bold text-white mb-2">Cuenta pendiente de activación</h1>
        <p class="text-sm text-slate-400 leading-relaxed">
            Tu cuenta se ha creado correctamente, pero todavía no está activada.
            Un administrador tiene que habilitarla antes de que puedas usar la plataforma.
            Vuelve a intentarlo más tarde.
        </p>

        <form id="logout-form" action="{{ route('logout') }}" method="POST" class="mt-8">
            @csrf
            <button type="submit"
                    class="w-full py-3 bg-slate-800 hover:bg-slate-700 text-white font-bold rounded-xl text-sm transition-all flex items-center justify-center gap-2 border border-slate-700/50">
                <span class="material-symbols-outlined !text-sm">logout</span>
                <span>Cerrar sesión</span>
            </button>
        </form>
    </div>
</div>
@endsection
