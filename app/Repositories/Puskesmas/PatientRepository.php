<?php

namespace App\Repositories\Eloquent;

use App\Models\Patient;
use App\Repositories\Contracts\PatientRepositoryInterface;

class PatientRepository extends BaseRepository implements PatientRepositoryInterface
{
    public function __construct(Patient $model)
    {
        parent::__construct($model);
    }

    public function findByPuskesmas(int $puskesmasId, array $columns = ['*'])
    {
        return $this->model->where('puskesmas_id', $puskesmasId)->get($columns);
    }

    public function findByDiseaseType(int $puskesmasId, string $diseaseType, array $columns = ['*'])
    {
        $query = $this->model->where('puskesmas_id', $puskesmasId);
        
        if ($diseaseType === 'ht') {
            $query->whereNotNull('ht_years')
                  ->where('ht_years', '<>', '[]')
                  ->where('ht_years', '<>', 'null');
        } elseif ($diseaseType === 'dm') {
            $query->whereNotNull('dm_years')
                  ->where('dm_years', '<>', '[]')
                  ->where('dm_years', '<>', 'null');
        } elseif ($diseaseType === 'both') {
            $query->whereNotNull('ht_years')
                  ->where('ht_years', '<>', '[]')
                  ->where('ht_years', '<>', 'null')
                  ->whereNotNull('dm_years')
                  ->where('dm_years', '<>', '[]')
                  ->where('dm_years', '<>', 'null');
        }
        
        return $query->get($columns);
    }

    public function findByDiseaseAndYear(int $puskesmasId, string $diseaseType, int $year, array $columns = ['*'])
    {
        // Get base results for the puskesmas
        $results = $this->model->where('puskesmas_id', $puskesmasId)->get($columns);
        
        // Filter results for disease type and year
        return $results->filter(function ($patient) use ($year, $diseaseType) {
            // Safely get the year arrays
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
    }

    public function search(int $puskesmasId, string $term, array $columns = ['*'])
    {
        return $this->model->where('puskesmas_id', $puskesmasId)
            ->where(function ($query) use ($term) {
                $query->where('name', 'like', "%{$term}%")
                      ->orWhere('nik', 'like', "%{$term}%")
                      ->orWhere('bpjs_number', 'like', "%{$term}%")
                      ->orWhere('medical_record_number', 'like', "%{$term}%");
            })
            ->get($columns);
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