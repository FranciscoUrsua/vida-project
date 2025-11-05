<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Intervencion\Http\Controllers\HistoriaController;
use App\Modules\Intervencion\Http\Controllers\ValoracionController;

Route::prefix('intervencion')->middleware('auth:sanctum')->group(function () {
    Route::apiResource('historias', HistoriaController::class);
    Route::apiResource('valoraciones', ValoracionController::class);
    Route::get('historias/{historia}/valoraciones', 
               [ValoracionController::class, 'index'])->name('valoraciones.by-historia');
});
