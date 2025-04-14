<?php

use App\Http\Controllers\API\Admin\StatisticsController;
use App\Http\Controllers\API\Admin\UserController;
use App\Http\Controllers\API\Admin\YearlyTargetController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\Puskesmas\DashboardController;
use App\Http\Controllers\API\Puskesmas\DmExaminationController;
use App\Http\Controllers\API\Puskesmas\HtExaminationController;
use App\Http\Controllers\API\Puskesmas\PatientController;
use App\Http\Controllers\API\Puskesmas\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\IsAdmin;
use App\Http\Middleware\IsPuskesmas;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);

    // Profile
    Route::post('/profile', [ProfileController::class, 'update']);

    // Akun sendiri
    Route::get('/me', [UserController::class, 'me']);
    Route::put('/me', [UserController::class, 'updateMe']);

    // Admin routes
    Route::middleware(IsAdmin::class)->prefix('admin')->group(function () {
        // User management
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users/{user}', [UserController::class, 'show']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);

        // Yearly targets
        Route::get('/yearly-targets', [YearlyTargetController::class, 'index']);
        Route::post('/yearly-targets', [YearlyTargetController::class, 'store']);
        Route::get('/yearly-targets/{target}', [YearlyTargetController::class, 'show']);
        Route::put('/yearly-targets/{target}', [YearlyTargetController::class, 'update']);
        Route::delete('/yearly-targets/{target}', [YearlyTargetController::class, 'destroy']);

        // Statistics API Endpoints
        Route::get('/statistics', [StatisticsController::class, 'index']);
        Route::get('/statistics/ht', [StatisticsController::class, 'htStatistics']);
        Route::get('/statistics/dm', [StatisticsController::class, 'dmStatistics']);

        // Export endpoints
        Route::get('/statistics/export', [StatisticsController::class, 'exportStatistics']);
        Route::get('/statistics/export/ht', [StatisticsController::class, 'exportHtStatistics']);
        Route::get('/statistics/export/dm', [StatisticsController::class, 'exportDmStatistics']);
    });

    // Puskesmas routes
    Route::middleware(IsPuskesmas::class)->prefix('puskesmas')->group(function () {
        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index']);

        // Patients
        Route::get('/patients', [PatientController::class, 'index']);
        Route::post('/patients', [PatientController::class, 'store']);
        Route::get('/patients/{patient}', [PatientController::class, 'show']);
        Route::put('/patients/{patient}', [PatientController::class, 'update']);
        Route::delete('/patients/{patient}', [PatientController::class, 'destroy']);

        // New routes for managing examination years
        Route::post('/patients/{patient}/examination-year', [PatientController::class, 'addExaminationYear']);
        Route::put('/patients/{patient}/examination-year', [PatientController::class, 'removeExaminationYear']);

        // HT Examinations
        Route::get('/ht-examinations', [HtExaminationController::class, 'index']);
        Route::post('/ht-examinations', [HtExaminationController::class, 'store']);
        Route::get('/ht-examinations/{htExamination}', [HtExaminationController::class, 'show']);
        Route::put('/ht-examinations/{htExamination}', [HtExaminationController::class, 'update']);
        Route::delete('/ht-examinations/{htExamination}', [HtExaminationController::class, 'destroy']);

        // DM Examinations
        Route::get('/dm-examinations', [DmExaminationController::class, 'index']);
        Route::post('/dm-examinations', [DmExaminationController::class, 'store']);
        Route::get('/dm-examinations/{dmExamination}', [DmExaminationController::class, 'show']);
        Route::put('/dm-examinations/{dmExamination}', [DmExaminationController::class, 'update']);
        Route::delete('/dm-examinations/{dmExamination}', [DmExaminationController::class, 'destroy']);
    });
});
