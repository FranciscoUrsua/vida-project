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

Route::post('/logout', function (Request $request) {  // Ahora $request es instancia
    Auth::guard('web')->logout();

    $request->user()?->currentAccessToken()?->delete();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/');  // O route('login')
})->middleware('auth:sanctum')->name('logout');
