<?php

namespace App\Services\Patient;

use App\Repositories\Contracts\HtExaminationRepositoryInterface;
use App\Models\HtExamination;
use App\Models\Patient;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

class HtExaminationService
{
    protected $htExaminationRepository;

    public function __construct(HtExaminationRepositoryInterface $htExaminationRepository)
    {
        $this->htExaminationRepository = $htExaminationRepository;
    }

    /**
     * Get all examinations with filters and pagination
     * 
     * @param int $puskesmasId
     * @param array $filters
     * @param int $perPage
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getAllExaminations(int $puskesmasId, array $filters = [], int $perPage = 10)
    {
        $query = HtExamination::where('puskesmas_id', $puskesmasId)
            ->with('patient');
        
        // Apply filters
        if (isset($filters['year'])) {
            $query->where('year', $filters['year']);
        }
        
        if (isset($filters['month'])) {
            $query->where('month', $filters['month']);
        }
        
        if (isset($filters['is_archived'])) {
            $query->where('is_archived', $filters['is_archived']);
        }
        
        if (isset($filters['patient_id'])) {
            $query->where('patient_id', $filters['patient_id']);
        }
        
        return $query->orderBy('examination_date', 'desc')
            ->paginate($perPage);
    }

    /**
     * Create a new HT examination
     * 
     * @param array $data
     * @return \App\Models\HtExamination
     */
    public function createExamination(array $data)
    {
        $date = Carbon::parse($data['examination_date']);
        $data['year'] = $date->year;
        $data['month'] = $date->month;
        
        // Set archived status based on year
        $data['is_archived'] = $date->year < Carbon::now()->year;
        
        $examination = $this->htExaminationRepository->create($data);
        
        // Make sure the patient has the examination year recorded
        $patient = Patient::find($data['patient_id']);
        if (!$patient->hasHtInYear($data['year'])) {
            $patient->addHtYear($data['year']);
            $patient->save();
        }
        
        return $examination;
    }

    /**
     * Update an HT examination
     * 
     * @param int $id
     * @param array $data
     * @return \App\Models\HtExamination|bool
     */
    public function updateExamination(int $id, array $data)
    {
        $date = Carbon::parse($data['examination_date']);
        $data['year'] = $date->year;
        $data['month'] = $date->month;
        
        // Set archived status based on year
        $data['is_archived'] = $date->year < Carbon::now()->year;
        
        return $this->htExaminationRepository->update($id, $data);
    }

    /**
     * Delete an HT examination
     * 
     * @param int $id
     * @return bool
     */
    public function deleteExamination(int $id)
    {
        return $this->htExaminationRepository->delete($id);
    }

    /**
     * Check if an examination is controlled
     * 
     * @param int $systolic
     * @param int $diastolic
     * @return bool
     */
    public function isControlled(int $systolic, int $diastolic)
    {
        return $systolic >= 90 && $systolic <= 139 && 
               $diastolic >= 60 && $diastolic <= 89;
    }
}