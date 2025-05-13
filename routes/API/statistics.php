<?php

use App\Http\Controllers\API\Statistics\StatisticsController;
use App\Http\Controllers\API\Statistics\ExportController;
use App\Http\Controllers\API\Statistics\MonitoringController;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\AdminOrPuskesmas;

// Statistics routes
Route::middleware(['auth:sanctum', AdminOrPuskesmas::class])->prefix('statistics')->group(function () {
    // Dashboard Statistics
    Route::get('/dashboard-statistics', [StatisticsController::class, 'dashboardStatistics']);

    // Standard Reports
    Route::get('/', [StatisticsController::class, 'index']);
    Route::get('/ht', [StatisticsController::class, 'htStatistics']);
    Route::get('/dm', [StatisticsController::class, 'dmStatistics']);

    // Export Endpoints - separate controller for exports
    Route::controller(ExportController::class)->prefix('export')->group(function () {
        Route::get('/', 'exportStatistics');
        Route::get('/ht', 'exportHtStatistics');
        Route::get('/dm', 'exportDmStatistics');
        Route::get('/{year}/{month}', 'exportMonthlyStatistics')
            ->where('year', '[0-9]{4}')
            ->where('month', '[0-9]{1,2}');
        Route::get('/ht/{year}/{month}', 'exportMonthlyHtStatistics')
            ->where('year', '[0-9]{4}')
            ->where('month', '[0-9]{1,2}');
        Route::get('/dm/{year}/{month}', 'exportMonthlyDmStatistics')
            ->where('year', '[0-9]{4}')
            ->where('month', '[0-9]{1,2}');
    });

    // Monitoring Reports - dedicated controller
    Route::controller(MonitoringController::class)->prefix('monitoring')->group(function () {
        Route::get('/', 'index');
        Route::get('/ht', 'htMonitoring');
        Route::get('/dm', 'dmMonitoring');
        Route::get('/{year}/{month}', 'monthlyMonitoring')
            ->where('year', '[0-9]{4}')
            ->where('month', '[0-9]{1,2}');
        Route::get('/ht/{year}/{month}', 'monthlyHtMonitoring')
            ->where('year', '[0-9]{4}')
            ->where('month', '[0-9]{1,2}');
        Route::get('/dm/{year}/{month}', 'monthlyDmMonitoring')
            ->where('year', '[0-9]{4}')
            ->where('month', '[0-9]{1,2}');
    });
});