<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\OnboardingController;
use Illuminate\Support\Facades\Route;

// Autenticación
Route::get('/login', [LoginController::class, 'mostrar'])->name('login')->middleware('guest');
Route::post('/login', [LoginController::class, 'autenticar'])->name('login.post')->middleware('guest');
Route::post('/logout', [LoginController::class, 'cerrarSesion'])->name('logout')->middleware('auth');

// Onboarding (primer acceso)
Route::get('/bienvenida', [OnboardingController::class, 'mostrar'])
    ->name('onboarding')
    ->middleware(['auth', 'primer.acceso']);
Route::post('/bienvenida', [OnboardingController::class, 'completar'])
    ->name('onboarding.completar')
    ->middleware(['auth', 'primer.acceso']);

// Raíz protegida — redirige según rol del usuario autenticado
Route::middleware(['web', 'auth'])->get('/', function () {
    $usuario = auth()->user();

    if ($usuario->hasAnyRole(['adm_sistema', 'supervision', 'adm_usuarios'])) {
        return redirect('/admin');
    }

    if ($usuario->hasRole('intervencion')) {
        return redirect()->route('intervencion.agenda.index');
    }

    if ($usuario->roles()->count() === 0) {
        return redirect()->route('sin-rol');
    }

    return view('inicio');
})->name('inicio');

// Pantalla de usuario sin rol — accesible con auth pero sin middleware tiene.rol
Route::middleware('auth')->get('/sin-rol', fn () => view('errors.sin-rol'))->name('sin-rol');
