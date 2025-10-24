<?php

use App\Http\Middleware\TenantScope;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\ProjectController;

// Auth routes (publiques)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Routes protégées
Route::middleware(['auth:sanctum', TenantScope::class])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    
    Route::apiResource('projects', ProjectController::class);
    Route::apiResource('tasks', TaskController::class);
});

// Routes admin pour gestion des tenants
Route::middleware(['auth:sanctum'])->group(function () {
    Route::apiResource('tenants', TenantController::class);
    Route::post('/tenants/{tenant}/users', [TenantController::class, 'addUser']);
    Route::get('/tenants/{tenant}/users', [TenantController::class, 'users']);
    Route::get('/tenants/{tenant}/projects', [TenantController::class, 'projects']);
    Route::get('/tenants/{tenant}/tasks', [TenantController::class, 'tasks']);
});
