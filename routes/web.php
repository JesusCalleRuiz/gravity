<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\VideoController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ModeloIAController;

// Rutas de Invitado (Auth)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

// Logout: solo necesita 'auth', nunca 'active' — una cuenta pendiente de
// activación tiene que poder cerrar sesión igualmente.
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

// Rutas Protegidas (Dashboard & Videos): requieren cuenta activada.
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/', [VideoController::class, 'index'])->name('videos.index');
    Route::get('/import', [VideoController::class, 'import'])->name('videos.import');
    Route::post('/upload', [VideoController::class, 'store'])->name('videos.store');
    Route::get('/videos/{id}', [VideoController::class, 'show'])->name('videos.show');
    Route::get('/videos/{id}/report', [VideoController::class, 'report'])->name('videos.report');
    Route::get('/videos/{id}/report/pdf', [VideoController::class, 'reportPdf'])->name('videos.report.pdf');

    Route::get('/modelo-ia', [ModeloIAController::class, 'index'])->name('modelo-ia');

    // Endpoint para el Polling de progreso desde el frontend
    Route::get('/api/videos/{id}/progress', [VideoController::class, 'progressApi'])->name('videos.progress');
});
