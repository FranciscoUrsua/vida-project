<?php

use Illuminate\Support\Facades\Route;
use app\Modules\\Intervencion\Http\Controllers\IntervencionController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('intervencions', IntervencionController::class)->names('intervencion');
});
