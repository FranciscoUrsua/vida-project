<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\OnboardingController;
use App\Http\Controllers\PaginaInicioController;
use App\Livewire\CambiarContrasena;
use App\Support\Superficie\DestinoInicial;
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

// Raíz protegida: la página de inicio del usuario (R4.7, único resolutor). Siempre hay
// destino; la antigua vista «Redirigiendo…» dejaba en bucle a quien no tenía superficie (EST-02).
Route::middleware(['web', 'auth'])
    ->get('/', fn () => redirect(DestinoInicial::para(auth()->user())))
    ->name('inicio');

// Preferencia de página de inicio (R4.6): solo la cambia el propio usuario
Route::middleware(['web', 'auth'])
    ->post('/preferencias/pagina-inicio', [PaginaInicioController::class, 'guardar'])
    ->name('preferencias.pagina-inicio');

// Cambio de la propia contraseña, desde el desplegable del usuario
Route::middleware(['web', 'auth'])
    ->get('/mi-cuenta/contrasena', CambiarContrasena::class)
    ->name('cuenta.contrasena');

// Pantalla de usuario sin rol — accesible con auth pero sin middleware tiene.rol
Route::middleware('auth')->get('/sin-rol', fn () => view('errors.sin-rol'))->name('sin-rol');
