<?php

use Illuminate\Http\Request;  // ✅ Clase instancia (para type-hints)
use Illuminate\Support\Facades\Auth;  // Facade para Auth
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
    // Logout del guard web (maneja sesiones y transient tokens)
    Auth::guard('web')->logout();

    // Limpia la sesión (esto revoca transient tokens vía cookies)
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    // Opcional: Si usas PersonalAccessTokens (no transient), revócalos
    // $request->user()?->tokens()->delete();  // Descomenta si aplica

    return redirect('/');  // O route('login') / tu welcome
})->middleware('auth:sanctum')->name('logout');
