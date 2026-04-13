<?php

use App\Http\Controllers\BackendServerController;
use App\Http\Controllers\GlobalAuthController;
use App\Http\Controllers\TenantAuthController;
use App\Http\Middleware\ProtectManagementApi;
use Illuminate\Support\Facades\Route;

$role = env('APP_ROLE');

Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'app_role' => $role,
]));

if ($role === 'backend') {
    Route::middleware(ProtectManagementApi::class)->group(function (): void {
        Route::get('/internal/route-assignments', [BackendServerController::class, 'index']);
        Route::post('/internal/route-assignments', [BackendServerController::class, 'store']);
        Route::put('/internal/route-assignments/{sub}', [BackendServerController::class, 'update']);
        Route::delete('/internal/route-assignments/{sub}', [BackendServerController::class, 'destroy']);
    });

    Route::get('/internal/users/{sub}/server', [BackendServerController::class, 'show']);
}

if ($role === 'global-bff') {
    Route::get('/login', [GlobalAuthController::class, 'login']);
    Route::get('/auth/callback', [GlobalAuthController::class, 'callback']);
    Route::get('/logout', [GlobalAuthController::class, 'logout']);
}

if (in_array($role, ['bff-a', 'bff-b'], true)) {
    Route::get('/auth/silent-login', [TenantAuthController::class, 'silentLogin']);
    Route::get('/auth/callback', [TenantAuthController::class, 'callback']);
    Route::get('/logout', [TenantAuthController::class, 'logout']);
    Route::get('/', [TenantAuthController::class, 'home']);
}
