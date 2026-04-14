<?php

use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\MeAuthorizationController;
use App\Http\Controllers\Api\MeController;
use Illuminate\Support\Facades\Route;

// Public and introspection endpoints stay permission-free.
Route::get('/health', HealthController::class)->name('api.health');
Route::get('/me', MeController::class)->name('api.me');
Route::get('/me/authorization', MeAuthorizationController::class)->name('api.me.authorization');

// Business APIs should declare required permissions explicitly.
// Example:
// Route::get('/objects', ObjectIndexController::class)
//     ->middleware('required_permissions:object.read');
