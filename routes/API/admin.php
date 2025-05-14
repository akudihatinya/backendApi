<?php

use App\Http\Controllers\API\Admin\YearlyTargetController;
use App\Http\Controllers\API\Shared\UserController;
use App\Http\Middleware\IsAdmin;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Admin Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', IsAdmin::class])->prefix('admin')->group(function () {
    // User management
    Route::apiResource('users', UserController::class);
    Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword']);

    // Yearly targets
    Route::apiResource('yearly-targets', YearlyTargetController::class);
});