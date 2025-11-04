<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

use App\Http\Controllers\Api\CentroController;
use App\Http\Controllers\Api\ProfesionalController;
use App\Http\Controllers\Api\DirectorController;
use App\Http\Controllers\Api\TipoCentroController;


Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('social-users', \App\Http\Controllers\Api\SocialUserController::class);
});

Route::apiResource('profesionales', ProfesionalController::class);
Route::apiResource('centros', CentroController::class);
Route::apiResource('directores', DirectorController::class)->only(['index', 'store', 'show', 'destroy']); // Solo básicos
Route::post('directores/{director}/baja', [DirectorController::class, 'darDeBaja']);
Route::apiResource('tipos-centro', TipoCentroController::class);
Route::apiResource('prestaciones', PrestacionController::class);

