<?php

namespace App\Observers;

use App\Models\Patient;
use App\Events\PatientCreated;
use Illuminate\Support\Facades\Log;

class PatientObserver
{
    /**
     * Handle the Patient "created" event.
     */
    public function created(Patient $patient): void
    {
        try {
            // Fire patient created event
            event(new PatientCreated($patient));
            
            // Log patient creation
            Log::info('Patient created', [
                'patient_id' => $patient->id,
                'puskesmas_id' => $patient->puskesmas_id,
                'name' => $patient->name
            ]);
        } catch (\Exception $e) {
            Log::error('Error in PatientObserver@created: ' . $e->getMessage(), [
                'patient_id' => $patient->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle the Patient "updated" event.
     */
    public function updated(Patient $patient): void
    {
        try {
            // Log patient update
            Log::info('Patient updated', [
                'patient_id' => $patient->id,
                'puskesmas_id' => $patient->puskesmas_id,
                'name' => $patient->name
            ]);
        } catch (\Exception $e) {
            Log::error('Error in PatientObserver@updated: ' . $e->getMessage(), [
                'patient_id' => $patient->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle the Patient "deleted" event.
     */
    public function deleted(Patient $patient): void
    {
        try {
            // Log patient deletion
            Log::info('Patient deleted', [
                'patient_id' => $patient->id,
                'puskesmas_id' => $patient->puskesmas_id,
                'name' => $patient->name
            ]);
        } catch (\Exception $e) {
            Log::error('Error in PatientObserver@deleted: ' . $e->getMessage(), [
                'patient_id' => $patient->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle the Patient "forceDeleted" event.
     */
    public function forceDeleted(Patient $patient): void
    {
        try {
            // Log patient permanent deletion
            Log::info('Patient permanently deleted', [
                'patient_id' => $patient->id,
                'puskesmas_id' => $patient->puskesmas_id,
                'name' => $patient->name
            ]);
        } catch (\Exception $e) {
            Log::error('Error in PatientObserver@forceDeleted: ' . $e->getMessage(), [
                'patient_id' => $patient->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}