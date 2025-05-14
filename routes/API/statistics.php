<?php

use App\Http\Controllers\API\Shared\DashboardController;
use App\Http\Controllers\API\Shared\StatisticsController;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\IsAdmin;
use App\Http\Middleware\IsPuskesmas;
use App\Http\Middleware\AdminOrPuskesmas;

/*
|--------------------------------------------------------------------------
| API Dashboard & Statistics Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum'])->group(function () {
    // Dashboard API for Dinas (admin)
    Route::middleware(IsAdmin::class)->prefix('admin')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'dinasIndex']);
    });

    // Dashboard API for Puskesmas
    Route::middleware(IsPuskesmas::class)->prefix('puskesmas')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'puskesmasIndex']);
    });

    // Statistics routes with combined middleware
    Route::middleware(AdminOrPuskesmas::class)->prefix('statistics')->group(function () {
        // Statistics for Dashboard
        Route::get('/dashboard-statistics', [StatisticsController::class, 'dashboardStatistics']);

        // Reports (Monthly and Yearly)
        Route::get('/', [StatisticsController::class, 'index']);
        Route::get('/ht', [StatisticsController::class, 'htStatistics']);
        Route::get('/dm', [StatisticsController::class, 'dmStatistics']);

        // Export endpoints
        Route::get('/export', [StatisticsController::class, 'exportStatistics']);
        Route::get('/export/ht', [StatisticsController::class, 'exportHtStatistics']);
        Route::get('/export/dm', [StatisticsController::class, 'exportDmStatistics']);

        // Monthly statistics export shortcuts
        Route::get('/{year}/{month}/export', [StatisticsController::class, 'exportStatistics'])
            ->where('year', '[0-9]{4}')
            ->where('month', '[0-9]{1,2}');

        // HT specific monthly export
        Route::get('/ht/{year}/{month}/export', function ($year, $month, \Illuminate\Http\Request $request) {
            return app(StatisticsController::class)->exportStatistics(
                $request->merge(['year' => $year, 'month' => $month, 'type' => 'ht'])
            );
        })->where('year', '[0-9]{4}')
            ->where('month', '[0-9]{1,2}');

        // DM specific monthly export
        Route::get('/dm/{year}/{month}/export', function ($year, $month, \Illuminate\Http\Request $request) {
            return app(StatisticsController::class)->exportStatistics(
                $request->merge(['year' => $year, 'month' => $month, 'type' => 'dm'])
            );
        })->where('year', '[0-9]{4}')
            ->where('month', '[0-9]{1,2}');

        // Monitoring Reports
        Route::get('/monitoring', [StatisticsController::class, 'exportMonitoringReport']);
        Route::get('/monitoring/ht', [StatisticsController::class, 'exportMonitoringReportHt']);
        Route::get('/monitoring/dm', [StatisticsController::class, 'exportMonitoringReportDm']);

        // Monthly monitoring export shortcuts
        Route::get('/monitoring/{year}/{month}', [StatisticsController::class, 'exportMonitoringReportByMonth'])
            ->where('year', '[0-9]{4}')
            ->where('month', '[0-9]{1,2}');
    });
});