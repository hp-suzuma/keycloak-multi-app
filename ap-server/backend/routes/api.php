<?php

use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\MeAuthorizationController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\ObjectIndexController;
use App\Http\Controllers\Api\ObjectStoreController;
use App\Http\Controllers\Api\ObjectShowController;
use App\Http\Controllers\Api\ObjectUpdateController;
use App\Http\Controllers\Api\ObjectDeleteController;
use App\Http\Controllers\Api\PlaybookIndexController;
use App\Http\Controllers\Api\PlaybookStoreController;
use App\Http\Controllers\Api\PlaybookShowController;
use App\Http\Controllers\Api\PlaybookUpdateController;
use App\Http\Controllers\Api\PlaybookDeleteController;
use App\Http\Controllers\Api\PolicyIndexController;
use App\Http\Controllers\Api\PolicyStoreController;
use App\Http\Controllers\Api\PolicyShowController;
use App\Http\Controllers\Api\PolicyUpdateController;
use App\Http\Controllers\Api\PolicyDeleteController;
use Illuminate\Support\Facades\Route;

// Public and introspection endpoints stay permission-free.
Route::get('/health', HealthController::class)->name('api.health');
Route::get('/me', MeController::class)->name('api.me');
Route::get('/me/authorization', MeAuthorizationController::class)->name('api.me.authorization');

// Business APIs should declare required permissions explicitly.
Route::get('/objects', ObjectIndexController::class)
    ->middleware('required_permissions:object.read')
    ->name('api.objects.index');
Route::get('/playbooks', PlaybookIndexController::class)
    ->middleware('required_permissions:object.read')
    ->name('api.playbooks.index');
Route::get('/policies', PolicyIndexController::class)
    ->middleware('required_permissions:object.read')
    ->name('api.policies.index');
Route::post('/playbooks', PlaybookStoreController::class)
    ->middleware('required_permissions:object.create')
    ->name('api.playbooks.store');
Route::post('/policies', PolicyStoreController::class)
    ->middleware('required_permissions:object.create')
    ->name('api.policies.store');
Route::get('/playbooks/{playbookId}', PlaybookShowController::class)
    ->middleware('required_permissions:object.read')
    ->name('api.playbooks.show');
Route::get('/policies/{policyId}', PolicyShowController::class)
    ->middleware('required_permissions:object.read')
    ->name('api.policies.show');
Route::patch('/playbooks/{playbookId}', PlaybookUpdateController::class)
    ->middleware('required_permissions:object.update')
    ->name('api.playbooks.update');
Route::patch('/policies/{policyId}', PolicyUpdateController::class)
    ->middleware('required_permissions:object.update')
    ->name('api.policies.update');
Route::delete('/playbooks/{playbookId}', PlaybookDeleteController::class)
    ->middleware('required_permissions:object.delete')
    ->name('api.playbooks.destroy');
Route::delete('/policies/{policyId}', PolicyDeleteController::class)
    ->middleware('required_permissions:object.delete')
    ->name('api.policies.destroy');
Route::post('/objects', ObjectStoreController::class)
    ->middleware('required_permissions:object.create')
    ->name('api.objects.store');
Route::get('/objects/{objectId}', ObjectShowController::class)
    ->middleware('required_permissions:object.read')
    ->name('api.objects.show');
Route::patch('/objects/{objectId}', ObjectUpdateController::class)
    ->middleware('required_permissions:object.update')
    ->name('api.objects.update');
Route::delete('/objects/{objectId}', ObjectDeleteController::class)
    ->middleware('required_permissions:object.delete')
    ->name('api.objects.destroy');
