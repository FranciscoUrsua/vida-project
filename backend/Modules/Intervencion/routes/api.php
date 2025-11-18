<?php

use Illuminate\Support\Facades\Route;
use Modules\Intervencion\Http\Controllers\IntervencionController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('intervencions', IntervencionController::class)->names('intervencion');
});
