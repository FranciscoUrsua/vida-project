<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('social-users', \App\Http\Controllers\Api\SocialUserController::class);
});
