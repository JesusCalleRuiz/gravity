<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloquea el acceso a la app mientras `users.active` no sea true, mostrando
 * la pantalla de "pendiente de activación" en su lugar. Se aplica DESPUÉS
 * de 'auth' (necesita saber quién es el usuario) y nunca a la ruta de
 * logout: una cuenta pendiente tiene que poder cerrar sesión igualmente.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && !$user->active) {
            return response()->view('auth.pendiente-activacion');
        }

        return $next($request);
    }
}
