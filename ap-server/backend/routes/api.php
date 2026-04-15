<?php

use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\MeAuthorizationController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\ChecklistDeleteController;
use App\Http\Controllers\Api\ChecklistIndexController;
use App\Http\Controllers\Api\ChecklistShowController;
use App\Http\Controllers\Api\ChecklistStoreController;
use App\Http\Controllers\Api\ChecklistUpdateController;
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
use App\Http\Controllers\Api\UserIndexController;
use App\Http\Controllers\Api\UserShowController;
use App\Http\Controllers\Api\UserAssignmentStoreController;
use App\Http\Controllers\Api\UserAssignmentDeleteController;
use App\Http\Controllers\Api\UserAssignmentItemDeleteController;
use App\Http\Controllers\Api\RoleIndexController;
use App\Http\Controllers\Api\ScopeIndexController;
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
Route::get('/checklists', ChecklistIndexController::class)
    ->middleware('required_permissions:object.read')
    ->name('api.checklists.index');
Route::get('/users', UserIndexController::class)
    ->middleware('required_permissions:user.manage')
    ->name('api.users.index');
Route::get('/roles', RoleIndexController::class)
    ->middleware('required_permissions:user.manage')
    ->name('api.roles.index');
Route::get('/scopes', ScopeIndexController::class)
    ->middleware('required_permissions:user.manage')
    ->name('api.scopes.index');
Route::get('/users/{keycloakSub}', UserShowController::class)
    ->middleware('required_permissions:user.manage')
    ->name('api.users.show');
Route::post('/users/{keycloakSub}/assignments', UserAssignmentStoreController::class)
    ->middleware('required_permissions:user.manage')
    ->name('api.users.assignments.store');
Route::delete('/users/{keycloakSub}/assignments', UserAssignmentDeleteController::class)
    ->middleware('required_permissions:user.manage')
    ->name('api.users.assignments.destroy');
Route::delete('/users/{keycloakSub}/assignments/{assignmentId}', UserAssignmentItemDeleteController::class)
    ->middleware('required_permissions:user.manage')
    ->name('api.users.assignments.item.destroy');
Route::post('/playbooks', PlaybookStoreController::class)
    ->middleware('required_permissions:object.create')
    ->name('api.playbooks.store');
Route::post('/policies', PolicyStoreController::class)
    ->middleware('required_permissions:object.create')
    ->name('api.policies.store');
Route::post('/checklists', ChecklistStoreController::class)
    ->middleware('required_permissions:object.create')
    ->name('api.checklists.store');
Route::get('/playbooks/{playbookId}', PlaybookShowController::class)
    ->middleware('required_permissions:object.read')
    ->name('api.playbooks.show');
Route::get('/policies/{policyId}', PolicyShowController::class)
    ->middleware('required_permissions:object.read')
    ->name('api.policies.show');
Route::get('/checklists/{checklistId}', ChecklistShowController::class)
    ->middleware('required_permissions:object.read')
    ->name('api.checklists.show');
Route::patch('/playbooks/{playbookId}', PlaybookUpdateController::class)
    ->middleware('required_permissions:object.update')
    ->name('api.playbooks.update');
Route::patch('/policies/{policyId}', PolicyUpdateController::class)
    ->middleware('required_permissions:object.update')
    ->name('api.policies.update');
Route::patch('/checklists/{checklistId}', ChecklistUpdateController::class)
    ->middleware('required_permissions:object.update')
    ->name('api.checklists.update');
Route::delete('/playbooks/{playbookId}', PlaybookDeleteController::class)
    ->middleware('required_permissions:object.delete')
    ->name('api.playbooks.destroy');
Route::delete('/policies/{policyId}', PolicyDeleteController::class)
    ->middleware('required_permissions:object.delete')
    ->name('api.policies.destroy');
Route::delete('/checklists/{checklistId}', ChecklistDeleteController::class)
    ->middleware('required_permissions:object.delete')
    ->name('api.checklists.destroy');
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
