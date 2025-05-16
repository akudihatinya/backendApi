<?php

namespace App\Repositories\Puskesmas;

use App\Models\Patient;
use App\Repositories\Contracts\PatientRepositoryInterface;
use App\Repositories\Eloquent\BaseRepository;

class PatientRepository extends BaseRepository implements PatientRepositoryInterface
{
    public function __construct(Patient $model)
    {
        parent::__construct($model);
    }

    /**
     * Find patients by puskesmas ID
     */
    public function findByPuskesmas(int $puskesmasId, array $columns = ['*'])
    {
        return $this->model->where('puskesmas_id', $puskesmasId)->get($columns);
    }

    /**
     * Find patients by disease type
     */
    public function findByDiseaseType(int $puskesmasId, string $diseaseType, array $columns = ['*'])
    {
        $query = $this->model->where('puskesmas_id', $puskesmasId);
        
        switch ($diseaseType) {
            case 'ht':
                $query->where(function ($q) {
                    $q->whereNotNull('ht_years')
                      ->whereRaw("JSON_LENGTH(ht_years) > 0");
                });
                break;
            case 'dm':
                $query->where(function ($q) {
                    $q->whereNotNull('dm_years')
                      ->whereRaw("JSON_LENGTH(dm_years) > 0");
                });
                break;
            case 'both':
                $query->where(function ($q) {
                    $q->where(function ($q) {
                            $q->whereNotNull('ht_years')
                              ->whereRaw("JSON_LENGTH(ht_years) > 0");
                        })
                        ->orWhere(function ($q) {
                            $q->whereNotNull('dm_years')
                              ->whereRaw("JSON_LENGTH(dm_years) > 0");
                        });
                });
                break;
        }
        
        return $query->get($columns);
    }

    /**
     * Find patients by disease type and year
     */
    public function findByDiseaseAndYear(int $puskesmasId, string $diseaseType, int $year, array $columns = ['*'])
    {
        $query = $this->model->where('puskesmas_id', $puskesmasId);
        
        switch ($diseaseType) {
            case 'ht':
                $query->whereJsonContains('ht_years', $year);
                break;
            case 'dm':
                $query->whereJsonContains('dm_years', $year);
                break;
            case 'both':
                $query->where(function ($q) use ($year) {
                    $q->whereJsonContains('ht_years', $year)
                      ->orWhereJsonContains('dm_years', $year);
                });
                break;
        }
        
        return $query->get($columns);
    }

    /**
     * Search patients by name, NIK, or BPJS number
     */
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
     * Get all patients with pagination and filtering
     */
    public function getAllWithFilters(int $puskesmasId, array $filters = [], int $perPage = 15)
    {
        $query = $this->model->where('puskesmas_id', $puskesmasId);
        
        // Apply disease type filter
        if (isset($filters['disease_type'])) {
            $year = $filters['year'] ?? date('Y');
            
            switch ($filters['disease_type']) {
                case 'ht':
                    $query->whereJsonContains('ht_years', (int)$year);
                    break;
                case 'dm':
                    $query->whereJsonContains('dm_years', (int)$year);
                    break;
                case 'both':
                    $query->where(function($q) use ($year) {
                        $q->whereJsonContains('ht_years', (int)$year)
                          ->orWhereJsonContains('dm_years', (int)$year);
                    });
                    break;
            }
        }
        
        // Apply search filter
        if (isset($filters['search']) && !empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('nik', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('bpjs_number', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('medical_record_number', 'like', '%' . $filters['search'] . '%');
            });
        }
        
        return $query->orderBy('name')->paginate($perPage);
    }
    
    /**
     * Count patients by gender and disease type
     */
    public function countByGenderAndDisease(int $puskesmasId, int $year)
    {
        $htMale = $this->model->where('puskesmas_id', $puskesmasId)
            ->whereJsonContains('ht_years', $year)
            ->where('gender', 'male')
            ->count();
            
        $htFemale = $this->model->where('puskesmas_id', $puskesmasId)
            ->whereJsonContains('ht_years', $year)
            ->where('gender', 'female')
            ->count();
            
        $dmMale = $this->model->where('puskesmas_id', $puskesmasId)
            ->whereJsonContains('dm_years', $year)
            ->where('gender', 'male')
            ->count();
            
        $dmFemale = $this->model->where('puskesmas_id', $puskesmasId)
            ->whereJsonContains('dm_years', $year)
            ->where('gender', 'female')
            ->count();
            
        return [
            'ht' => [
                'male' => $htMale,
                'female' => $htFemale,
                'total' => $htMale + $htFemale
            ],
            'dm' => [
                'male' => $dmMale,
                'female' => $dmFemale,
                'total' => $dmMale + $dmFemale
            ]
        ];
    }
}