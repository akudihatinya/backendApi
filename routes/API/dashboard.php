<?php

use App\Http\Controllers\API\Dashboard\AdminDashboardController;
use App\Http\Controllers\API\Dashboard\PuskesmasDashboardController;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\IsAdmin;
use App\Http\Middleware\IsPuskesmas;

// Dashboard routes
Route::middleware(['auth:sanctum'])->group(function () {
    // Admin dashboard
    Route::middleware([IsAdmin::class])->group(function () {
        Route::get('/admin/dashboard', [AdminDashboardController::class, 'index']);
    });

    // Puskesmas dashboard
    Route::middleware([IsPuskesmas::class])->group(function () {
        Route::get('/puskesmas/dashboard', [PuskesmasDashboardController::class, 'index']);
    });
    
    // User profile/account
    Route::prefix('users')->group(function () {
        Route::get('/me', [UserProfileController::class, 'me']);
        Route::put('/me', [UserProfileController::class, 'updateMe']);
    });
});