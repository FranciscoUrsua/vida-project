<?php

use Illuminate\Support\Facades\Route;
use app\Modules\\Test\Http\Controllers\TestController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('tests', TestController::class)->names('test');
});
