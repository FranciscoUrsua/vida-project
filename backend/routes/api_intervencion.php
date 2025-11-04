<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Intervencion\Http\Controllers\HistoriaController;

Route::prefix('intervencion')->middleware('auth:sanctum')->group(function () {
    Route::apiResource('historias', HistoriaController::class);
    // Futuras: Route::apiResource('valoraciones', ValoracionController::class);
});
