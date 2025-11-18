<?php

use Illuminate\Support\Facades\Route;
use Modules\Intervencion\Http\Controllers\IntervencionController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('intervencions', IntervencionController::class)->names('intervencion');
});
