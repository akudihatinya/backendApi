<?php

namespace App\Services\Patient;

use App\DataTransferObjects\PatientData;
use App\Events\PatientCreated;
use App\Exceptions\PatientNotFoundException;
use App\Models\Patient;
use App\Repositories\Contracts\PatientRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PatientService
{
    /**
     * Create a new patient service instance.
     */
    public function __construct(
        protected PatientRepositoryInterface $patientRepository
    ) {}

    /**
     * Get all patients with filters and pagination
     */
    public function getAllPatients(
        int $puskesmasId, 
        array $filters = [], 
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = Patient::where('puskesmas_id', $puskesmasId);
        
        // Filter by disease type
        if (isset($filters['disease_type'])) {
            if ($filters['disease_type'] === 'ht') {
                $query->whereNotNull('ht_years')
                      ->where('ht_years', '<>', '[]')
                      ->where('ht_years', '<>', 'null');
            } elseif ($filters['disease_type'] === 'dm') {
                $query->whereNotNull('dm_years')
                      ->where('dm_years', '<>', '[]')
                      ->where('dm_years', '<>', 'null');
            } elseif ($filters['disease_type'] === 'both') {
                $query->whereNotNull('ht_years')
                      ->where('ht_years', '<>', '[]')
                      ->where('ht_years', '<>', 'null')
                      ->whereNotNull('dm_years')
                      ->where('dm_years', '<>', '[]')
                      ->where('dm_years', '<>', 'null');
            }
        }
        
        // Search
        if (isset($filters['search']) && !empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('nik', 'like', "%{$search}%")
                  ->orWhere('bpjs_number', 'like', "%{$search}%")
                  ->orWhere('medical_record_number', 'like', "%{$search}%");
            });
        }
        
        // Year filtering (this needs PHP filtering because of JSON column)
        if (isset($filters['year'])) {
            $year = $filters['year'];
            $diseaseType = $filters['disease_type'] ?? null;
            
            // Get all results first
            $results = $query->get();
            
            // Filter by year
            $filtered = $results->filter(function ($patient) use ($year, $diseaseType) {
                $htYears = $this->safeGetYears($patient->ht_years);
                $dmYears = $this->safeGetYears($patient->dm_years);
                
                if ($diseaseType === 'ht') {
                    return in_array($year, $htYears);
                } elseif ($diseaseType === 'dm') {
                    return in_array($year, $dmYears);
                } elseif ($diseaseType === 'both') {
                    return in_array($year, $htYears) && in_array($year, $dmYears);
                } else {
                    return in_array($year, $htYears) || in_array($year, $dmYears);
                }
            });
            
            // Create paginator
            $page = $filters['page'] ?? 1;
            $items = $filtered->forPage($page, $perPage);
            
            return new LengthAwarePaginator(
                $items,
                $filtered->count(),
                $perPage,
                $page,
                [
                    'path' => request()->url(),
                    'query' => request()->query(),
                ]
            );
        }
        
        // Return paginated results
        return $query->paginate($perPage);
    }

    /**
     * Create a new patient
     */
    public function createPatient(array $data): Patient
    {
        // Initialize empty arrays for years if not provided
        $data['ht_years'] = $data['ht_years'] ?? [];
        $data['dm_years'] = $data['dm_years'] ?? [];
        
        // Calculate age from birth date if not provided
        if (!isset($data['age']) && isset($data['birth_date'])) {
            $data['age'] = Carbon::parse($data['birth_date'])->age;
        }
        
        DB::beginTransaction();
        try {
            $patient = Patient::create($data);
            
            // Dispatch event
            event(new PatientCreated($patient));
            
            DB::commit();
            return $patient;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating patient: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update a patient
     */
    public function updatePatient(int $id, array $data): Patient
    {
        $patient = Patient::find($id);
        
        if (!$patient) {
            throw new PatientNotFoundException();
        }
        
        // Calculate age from birth date if provided
        if (isset($data['birth_date']) && !isset($data['age'])) {
            $data['age'] = Carbon::parse($data['birth_date'])->age;
        }
        
        DB::beginTransaction();
        try {
            $patient->update($data);
            DB::commit();
            return $patient->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating patient: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Delete a patient
     */
    public function deletePatient(int $id): bool
    {
        $patient = Patient::find($id);
        
        if (!$patient) {
            throw new PatientNotFoundException();
        }
        
        DB::beginTransaction();
        try {
            // Delete related examinations first
            // Note: This is redundant if foreign keys with cascade are set up correctly
            // but it's a safety measure
            $patient->htExaminations()->delete();
            $patient->dmExaminations()->delete();
            
            $patient->delete();
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting patient: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Add examination year to patient
     */
    public function addExaminationYear(Patient $patient, int $year, string $type): Patient
    {
        if ($type === 'ht') {
            $patient->addHtYear($year);
        } else {
            $patient->addDmYear($year);
        }
        
        $patient->save();
        return $patient;
    }

    /**
     * Remove examination year from patient
     */
    public function removeExaminationYear(Patient $patient, int $year, string $type): Patient
    {
        if ($type === 'ht') {
            $patient->removeHtYear($year);
        } else {
            $patient->removeDmYear($year);
        }
        
        $patient->save();
        return $patient;
    }

    /**
     * Safely get years array from various possible formats
     */
    private function safeGetYears($years)
    {
        // If it's null, return empty array
        if (is_null($years)) {
            return [];
        }
        
        // If it's already an array, return it
        if (is_array($years)) {
            return $years;
        }
        
        // If it's a string, try to decode it
        if (is_string($years)) {
            $decoded = json_decode($years, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }
        
        // Default fallback
        return [];
    }
}