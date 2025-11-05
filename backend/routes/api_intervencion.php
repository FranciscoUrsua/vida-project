<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Intervencion\Http\Controllers\HistoriaController;
use App\Modules\Intervencion\Http\Controllers\ValoracionController;
use App\Modules\Intervencion\Http\Controllers\FichaController;

Route::prefix('intervencion')->middleware('auth:sanctum')->group(function () {
    Route::apiResource('historias', HistoriaController::class);
    Route::apiResource('valoraciones', ValoracionController::class);
    Route::apiResource('fichas', FichaController::class);
    
    Route::get('historias/{historia}/valoraciones', 
               [ValoracionController::class, 'index'])->name('valoraciones.by-historia');
    Route::get('valoraciones/{valoracion}/fichas', 
               [FichaController::class, 'index'])->name('fichas.by-valoracion');
    Route::apiResource('valoraciones/{valoracion}/fichas', 
                       FichaController::class)->shallow(); // Shallow para no anidar historia

});
