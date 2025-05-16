<?php

namespace App\Services\Patient;

use App\Events\PatientCreated;
use App\Models\Patient;
use App\Repositories\Contracts\PatientRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PatientService
{
    protected $patientRepository;

    public function __construct(PatientRepositoryInterface $patientRepository)
    {
        $this->patientRepository = $patientRepository;
    }

    /**
     * Get all patients with filtering
     */
    public function getAllPatients(int $puskesmasId, array $filters = [], int $perPage = 15)
    {
        return $this->patientRepository->getAllWithFilters($puskesmasId, $filters, $perPage);
    }

    /**
     * Create a new patient
     */
    public function createPatient(array $data): Patient
    {
        try {
            DB::beginTransaction();
            
            // Ensure puskesmas_id is set
            if (!isset($data['puskesmas_id'])) {
                $data['puskesmas_id'] = Auth::user()->puskesmas_id;
            }
            
            // Initialize years arrays if not provided
            if (!isset($data['ht_years'])) {
                $data['ht_years'] = [];
            }
            
            if (!isset($data['dm_years'])) {
                $data['dm_years'] = [];
            }
            
            // Create patient
            $patient = $this->patientRepository->create($data);
            
            // Fire patient created event
            event(new PatientCreated($patient));
            
            DB::commit();
            
            return $patient;
            
        } catch (\Exception $e) {
            DB::rollback();
            
            Log::error("Error creating patient: " . $e->getMessage(), [
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    /**
     * Update an existing patient
     */
    public function updatePatient(int $id, array $data): Patient
    {
        try {
            DB::beginTransaction();
            
            // Get the patient
            $patient = Patient::findOrFail($id);
            
            // Update patient
            $this->patientRepository->update($id, $data);
            
            // Refresh patient data
            $patient = Patient::findOrFail($id);
            
            DB::commit();
            
            return $patient;
            
        } catch (\Exception $e) {
            DB::rollback();
            
            Log::error("Error updating patient: " . $e->getMessage(), [
                'id' => $id,
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    /**
     * Delete a patient
     */
    public function deletePatient(int $id): bool
    {
        try {
            DB::beginTransaction();
            
            // Get the patient
            $patient = Patient::findOrFail($id);
            
            // Delete patient
            $result = $this->patientRepository->delete($id);
            
            DB::commit();
            
            return $result;
            
        } catch (\Exception $e) {
            DB::rollback();
            
            Log::error("Error deleting patient: " . $e->getMessage(), [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    /**
     * Add examination year to patient
     */
    public function addExaminationYear(Patient $patient, int $year, string $examinationType): Patient
    {
        try {
            // Update the appropriate years array
            if ($examinationType === 'ht') {
                $patient->addHtYear($year);
            } else if ($examinationType === 'dm') {
                $patient->addDmYear($year);
            }
            
            // Save patient
            $patient->save();
            
            return $patient;
            
        } catch (\Exception $e) {
            Log::error("Error adding examination year: " . $e->getMessage(), [
                'patient_id' => $patient->id,
                'year' => $year,
                'examination_type' => $examinationType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    /**
     * Remove examination year from patient
     */
    public function removeExaminationYear(Patient $patient, int $year, string $examinationType): Patient
    {
        try {
            // Update the appropriate years array
            if ($examinationType === 'ht') {
                $patient->removeHtYear($year);
            } else if ($examinationType === 'dm') {
                $patient->removeDmYear($year);
            }
            
            // Save patient
            $patient->save();
            
            return $patient;
            
        } catch (\Exception $e) {
            Log::error("Error removing examination year: " . $e->getMessage(), [
                'patient_id' => $patient->id,
                'year' => $year,
                'examination_type' => $examinationType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }
}