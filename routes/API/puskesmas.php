<?php

use App\Http\Controllers\API\Puskesmas\DmExaminationController;
use App\Http\Controllers\API\Puskesmas\HtExaminationController;
use App\Http\Controllers\API\Puskesmas\PatientController;
use App\Http\Controllers\API\Puskesmas\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\IsPuskesmas;

// Puskesmas routes
Route::middleware(['auth:sanctum', IsPuskesmas::class])->prefix('puskesmas')->group(function () {
    // Profile Management
    Route::post('/profile', [ProfileController::class, 'update']);
    
    // Patients Management
    Route::apiResource('patients', PatientController::class);
    Route::post('/patients/{patient}/examination-year', [PatientController::class, 'addExaminationYear']);
    Route::put('/patients/{patient}/examination-year', [PatientController::class, 'removeExaminationYear']);

    // HT Examinations
    Route::apiResource('ht-examinations', HtExaminationController::class);

    // DM Examinations
    Route::apiResource('dm-examinations', DmExaminationController::class);
});