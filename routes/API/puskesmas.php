<?php

use App\Http\Controllers\API\Puskesmas\DmExaminationController;
use App\Http\Controllers\API\Puskesmas\HtExaminationController;
use App\Http\Controllers\API\Puskesmas\PatientController;
use App\Http\Controllers\API\Puskesmas\ProfileController;
use App\Http\Middleware\IsPuskesmas;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Puskesmas Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', IsPuskesmas::class])->prefix('puskesmas')->group(function () {
    // Profile management
    Route::post('/profile', [ProfileController::class, 'update']);

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