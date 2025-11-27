<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

use Modules\Centro\Http\Controllers\CentroController;
use App\Http\Controllers\Api\ProfesionalController;
use Modules\Centro\Http\Controllers\DirectorController;
use Modules\Centro\Http\Controllers\TipoCentroController;
use App\Http\Controllers\Api\PrestacionController; 

Route::middleware('auth:sanctum')->group(function () {
//    Route::apiResource('audits', AuditController::class)->only(['index', 'show']);
});

Route::apiResource('profesionales', ProfesionalController::class);
Route::apiResource('centros', CentroController::class);
Route::apiResource('directores', DirectorController::class)->only(['index', 'store', 'show', 'destroy']); // Solo básicos
Route::post('directores/{director}/baja', [DirectorController::class, 'darDeBaja']);
Route::apiResource('tipos-centro', TipoCentroController::class);
Route::apiResource('prestaciones', PrestacionController::class);

