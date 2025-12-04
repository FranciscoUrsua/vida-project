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

Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/');  // O tu pantalla de login
})->middleware('auth:sanctum')->name('logout');
