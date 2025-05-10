<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\Shared\StatisticsController;
use App\Http\Controllers\API\Shared\DashboardController;
use App\Http\Controllers\API\Shared\UserController;
use App\Http\Controllers\API\Admin\YearlyTargetController;
use App\Http\Controllers\API\Puskesmas\DmExaminationController;
use App\Http\Controllers\API\Puskesmas\HtExaminationController;
use App\Http\Controllers\API\Puskesmas\PatientController;
use App\Http\Controllers\API\Puskesmas\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\IsAdmin;
use App\Http\Middleware\IsPuskesmas;
use App\Http\Middleware\AdminOrPuskesmas;

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
// Route untuk cek otentikasi tanpa middleware auth
Route::get('/auth/check', [AuthController::class, 'check']);

// Public routes (authentication endpoints)
Route::post('/login', [AuthController::class, 'login']);
// Protected routes (require authentication)
Route::middleware(['auth:sanctum'])->group(function () {
    // Auth routes
    Route::controller(AuthController::class)->group(function () {
        Route::post('/logout', 'logout');
        Route::get('/user', 'user');
        Route::post('/change-password', 'changePassword');
    });

    // Profile management
    Route::post('/profile', [ProfileController::class, 'update']);

    // User management for own account
    Route::prefix('users')->group(function () {
        Route::get('/me', [UserController::class, 'me']);
        Route::put('/me', [UserController::class, 'updateMe']);
    });

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
        Route::get('/ht/{year}/{month}/export', function ($year, $month) {
            return app(StatisticsController::class)->exportStatistics(
                request()->merge(['year' => $year, 'month' => $month, 'type' => 'ht'])
            );
        })->where('year', '[0-9]{4}')
            ->where('month', '[0-9]{1,2}');

        // DM specific monthly export
        Route::get('/dm/{year}/{month}/export', function ($year, $month) {
            return app(StatisticsController::class)->exportStatistics(
                request()->merge(['year' => $year, 'month' => $month, 'type' => 'dm'])
            );
        })->where('year', '[0-9]{4}')
            ->where('month', '[0-9]{1,2}');

        // Monitoring Reports
        Route::get('/monitoring', [StatisticsController::class, 'exportMonitoringReport']);

        Route::get('/monitoring/ht', function (Request $request) {
            return app(StatisticsController::class)->exportMonitoringReport(
                $request->merge(['type' => 'ht'])
            );
        });

        Route::get('/monitoring/dm', function (Request $request) {
            return app(StatisticsController::class)->exportMonitoringReport(
                $request->merge(['type' => 'dm'])
            );
        });

        // Monthly monitoring export shortcuts
        Route::get('/monitoring/{year}/{month}', function ($year, $month, Request $request) {
            return app(StatisticsController::class)->exportMonitoringReport(
                $request->merge(['year' => $year, 'month' => $month])
            );
        })->where('year', '[0-9]{4}')
            ->where('month', '[0-9]{1,2}');

        Route::get('/monitoring/ht/{year}/{month}', function ($year, $month, Request $request) {
            return app(StatisticsController::class)->exportMonitoringReport(
                $request->merge(['year' => $year, 'month' => $month, 'type' => 'ht'])
            );
        })->where('year', '[0-9]{4}')
            ->where('month', '[0-9]{1,2}');

        Route::get('/monitoring/dm/{year}/{month}', function ($year, $month, Request $request) {
            return app(StatisticsController::class)->exportMonitoringReport(
                $request->merge(['year' => $year, 'month' => $month, 'type' => 'dm'])
            );
        })->where('year', '[0-9]{4}')
            ->where('month', '[0-9]{1,2}');
    });

    // Admin-only routes
    Route::middleware(IsAdmin::class)->prefix('admin')->group(function () {
        // User management
        Route::apiResource('users', UserController::class);
        Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword']);

        // Yearly targets
        Route::apiResource('yearly-targets', YearlyTargetController::class);
    });

    // Puskesmas-only routes
    Route::middleware(IsPuskesmas::class)->prefix('puskesmas')->group(function () {
        // Patients management
        Route::apiResource('patients', PatientController::class);

        // Examination years
        Route::post('/patients/{patient}/examination-year', [PatientController::class, 'addExaminationYear']);
        Route::put('/patients/{patient}/examination-year', [PatientController::class, 'removeExaminationYear']);

        // HT Examinations
        Route::apiResource('ht-examinations', HtExaminationController::class);

        // DM Examinations
        Route::apiResource('dm-examinations', DmExaminationController::class);
    });
});
