<?php

use Illuminate\Support\Facades\Route;
use Modules\Centro\Http\Controllers\TipoCentroController;
use Modules\Centro\Http\Controllers\CentroController;
use Modules\Centro\Http\Controllers\DirectorController;
use Modules\Centro\Http\Controllers\CentroProfesionalController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Aquí van las rutas API del módulo Centro. Todas protegidas por Sanctum.
| Usa Route::apiResource para CRUD estándar.
|
*/

// Grupo para auth (ajusta middleware si usas guards custom)
Route::middleware('auth:sanctum')->group(function () {
    // Tipos de Centro: Solo admins sistema (usa FormRequests para granular)
    Route::apiResource('tipos-centros', TipoCentroController::class)
        ->parameters(['tipos-centros' => 'tipoCentro']); // Slug para route model binding

    // Centros: Admins de centros (con límites por tipo via FormRequests)
    Route::apiResource('centros', CentroController::class);

    // Directores: Asignaciones, con filtros históricos
    Route::apiResource('directores', DirectorController::class);

    // Asignaciones Centro-Profesional: Histórico via params
    Route::apiResource('centro-profesionales', CentroProfesionalController::class);
});

// Rutas adicionales para filtros históricos (e.g., GET /centros/{id}/historial?fecha=2023-01-01)
// Puedes agregar custom routes si hace falta, e.g.:
Route::middleware('auth:sanctum')->group(function () {
    Route::get('centros/{centro}/historial-profesionales', [CentroController::class, 'historialProfesionales'])
        ->name('centros.historial-profesionales'); // Método custom en controller
});
