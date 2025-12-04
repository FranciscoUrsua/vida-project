<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('welcome');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        $kpis = ['casos_abiertos' => 12, 'citas_hoy' => 3, 'tareas_pendientes' => 5, 'beneficiarios' => 45]; // Placeholder data
        return view('dashboard', compact('kpis'));
    })->name('dashboard');
});

Route::post('/logout', function (Request $request) {
    // Logout del guard web (sesiones)
    Auth::guard('web')->logout();

    // Revoca el token actual de Sanctum (para SPA/API)
    $request->user()?->currentAccessToken()?->delete();

    // Limpia la sesión (estándar para web)
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/');  // O a tu ruta de login/welcome, e.g., route('login')
})->middleware('auth:sanctum')->name('logout');
