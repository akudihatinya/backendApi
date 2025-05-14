<?php

namespace App\Services\Patient;

use App\Repositories\Contracts\PatientRepositoryInterface;
use App\Models\Patient;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class PatientService
{
    protected $patientRepository;

    public function __construct(PatientRepositoryInterface $patientRepository)
    {
        $this->patientRepository = $patientRepository;
    }

    /**
     * Get all patients with filtering and pagination
     * 
     * @param int $puskesmasId
     * @param array $filters
     * @param int $perPage
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getAllPatients(int $puskesmasId, array $filters = [], int $perPage = 15)
    {
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
        
        // Search by name, NIK, or BPJS
        if (isset($filters['search']) && !empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('nik', 'like', "%{$search}%")
                  ->orWhere('bpjs_number', 'like', "%{$search}%")
                  ->orWhere('medical_record_number', 'like', "%{$search}%");
            });
        }
        
        // Handle year filtering (which needs PHP filtering)
        if (isset($filters['year'])) {
            $year = $filters['year'];
            $diseaseType = $filters['disease_type'] ?? null;
            
            // Get all results first
            $results = $query->get();
            
            // Filter results for the year
            $filteredResults = $results->filter(function ($patient) use ($year, $diseaseType) {
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
            
            // Create a custom paginator
            $page = isset($filters['page']) ? (int)$filters['page'] : 1;
            $items = $filteredResults->forPage($page, $perPage);
            
            return new LengthAwarePaginator(
                $items,
                $filteredResults->count(),
                $perPage,
                $page,
                ['path' => request()->url(), 'query' => request()->query()]
            );
        }
        
        // Standard pagination if no year filtering
        return $query->paginate($perPage);
    }

    /**
     * Create a new patient
     * 
     * @param array $data
     * @return \App\Models\Patient
     */
    public function createPatient(array $data)
    {
        // Initialize empty arrays for years if not provided
        $data['ht_years'] = $data['ht_years'] ?? [];
        $data['dm_years'] = $data['dm_years'] ?? [];
        
        return $this->patientRepository->create($data);
    }

    /**
     * Update a patient
     * 
     * @param int $id
     * @param array $data
     * @return \App\Models\Patient|bool
     */
    public function updatePatient(int $id, array $data)
    {
        return $this->patientRepository->update($id, $data);
    }

    /**
     * Delete a patient
     * 
     * @param int $id
     * @return bool
     */
    public function deletePatient(int $id)
    {
        return $this->patientRepository->delete($id);
    }

    /**
     * Add examination year to patient
     * 
     * @param Patient $patient
     * @param int $year
     * @param string $type
     * @return Patient
     */
    public function addExaminationYear(Patient $patient, int $year, string $type)
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
     * 
     * @param Patient $patient
     * @param int $year
     * @param string $type
     * @return Patient
     */
    public function removeExaminationYear(Patient $patient, int $year, string $type)
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