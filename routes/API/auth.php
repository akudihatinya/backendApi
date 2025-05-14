<?php

use App\Http\Controllers\API\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Authentication Routes
|--------------------------------------------------------------------------
*/

// CSRF cookie for SPA authentication
Route::get('/sanctum/csrf-cookie', function () {
    return response()->json(['message' => 'CSRF cookie set']);
})->middleware(['api']);

// Public routes (authentication endpoints)
Route::post('/login', [AuthController::class, 'login']);
Route::post('/refresh', [AuthController::class, 'refresh']);

// Auth routes (require authentication)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::controller(AuthController::class)->group(function () {
        Route::post('/logout', 'logout');
        Route::get('/user', 'user');
        Route::post('/change-password', 'changePassword');
    });
});

// Debug endpoint (remove in production)
Route::get('/debug/auth-status', [AuthController::class, 'checkAuthStatus']);