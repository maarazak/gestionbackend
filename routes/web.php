<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Route CSRF pour Sanctum avec les bons middlewares
Route::middleware(['web'])->group(function () {
    Route::get('/sanctum/csrf-cookie', function () {
        return response()->json([
            'csrf_token' => csrf_token(),
            'message' => 'CSRF token initialized'
        ]);
    });
});
