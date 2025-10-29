<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\UserController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/switch-tenant', [AuthController::class, 'switchTenant']);
    
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users/invite', [UserController::class, 'invite']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);
    
    Route::apiResource('projects', ProjectController::class);
    
    Route::apiResource('tasks', TaskController::class);
    
    Route::get('/tenants', [TenantController::class, 'index']);
    Route::post('/tenants', [TenantController::class, 'store']);
    Route::get('/tenants/{tenant}', [TenantController::class, 'show']);
    Route::put('/tenants/{tenant}', [TenantController::class, 'update']);
    Route::delete('/tenants/{tenant}', [TenantController::class, 'destroy']);
    Route::post('/tenants/{tenant}/users', [TenantController::class, 'addUser']);
    Route::get('/tenants/{tenant}/users', [TenantController::class, 'users']);
    Route::get('/tenants/{tenant}/projects', [TenantController::class, 'projects']);
    Route::get('/tenants/{tenant}/tasks', [TenantController::class, 'tasks']);
});
