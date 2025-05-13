<?php

use App\Http\Controllers\API\Admin\UserManagementController;
use App\Http\Controllers\API\Admin\YearlyTargetController;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\IsAdmin;

// Admin routes - semua route di sini memerlukan IsAdmin middleware
Route::middleware(['auth:sanctum', IsAdmin::class])->prefix('admin')->group(function () {
    // User Management 
    Route::apiResource('users', UserManagementController::class);
    Route::post('/users/{user}/reset-password', [UserManagementController::class, 'resetPassword']);

    // Yearly Targets
    Route::apiResource('yearly-targets', YearlyTargetController::class);
});