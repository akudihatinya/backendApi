<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group.
|
*/

// Include domain-specific route files
Route::middleware('api')->group(function () {
    // Auth routes
    require __DIR__.'/api/auth.php';
    
    // Admin routes
    require __DIR__.'/api/admin.php';
    
    // Puskesmas routes
    require __DIR__.'/api/puskesmas.php';
    
    // Stats & Dashboard routes
    require __DIR__.'/api/statistics.php';
    
    // User profile route (accessible by both admin and puskesmas)
    Route::middleware('auth:sanctum')->group(function () {
        Route::prefix('users')->group(function () {
            Route::get('/me', [\App\Http\Controllers\API\Shared\UserController::class, 'me']);
            Route::put('/me', [\App\Http\Controllers\API\Shared\UserController::class, 'updateMe']);
        });
    });
});