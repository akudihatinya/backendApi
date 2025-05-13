<?php

use App\Http\Controllers\API\Maintenance\SystemController;
use App\Http\Controllers\API\Maintenance\CacheController;
use App\Http\Controllers\API\Maintenance\LogsController;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\IsAdmin;

// Maintenance routes (admin only)
Route::middleware(['auth:sanctum', IsAdmin::class])->prefix('maintenance')->group(function () {
    // System status
    Route::get('/system/status', [SystemController::class, 'status']);
    Route::get('/system/info', [SystemController::class, 'info']);
    
    // Cache management
    Route::get('/cache/status', [CacheController::class, 'status']);
    Route::post('/cache/rebuild', [CacheController::class, 'rebuild']);
    Route::post('/cache/clear', [CacheController::class, 'clear']);
    
    // Log management
    Route::get('/logs', [LogsController::class, 'index']);
    Route::get('/logs/{date}', [LogsController::class, 'show']);
    
    // Authentication debug (only in non-production)
    Route::get('/auth-status', [SystemController::class, 'authStatus']);
});