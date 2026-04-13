<?php

use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\MeAuthorizationController;
use App\Http\Controllers\Api\MeController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);
Route::get('/me', MeController::class);
Route::get('/me/authorization', MeAuthorizationController::class);
