<?php

namespace App\Repositories\Puskesmas;

use App\Models\DmExamination;
use App\Repositories\Contracts\DmExaminationRepositoryInterface;
use App\Repositories\Eloquent\BaseRepository;
use Carbon\Carbon;

class DmExaminationRepository extends BaseRepository implements DmExaminationRepositoryInterface
{
    public function __construct(DmExamination $model)
    {
        parent::__construct($model);
    }

    /**
     * Find DM examinations by puskesmas ID
     */
    public function findByPuskesmas(int $puskesmasId, array $columns = ['*'])
    {
        return $this->model->where('puskesmas_id', $puskesmasId)->get($columns);
    }

    /**
     * Find DM examinations by patient ID
     */
    public function findByPatient(int $patientId, array $columns = ['*'])
    {
        return $this->model->where('patient_id', $patientId)->get($columns);
    }

    /**
     * Find DM examinations by date
     */
    public function findByDate(int $puskesmasId, $date, array $columns = ['*'])
    {
        return $this->model->where('puskesmas_id', $puskesmasId)
            ->where('examination_date', $date)
            ->get($columns);
    }

    /**
     * Find DM examinations by date range
     */
    public function findByPeriod(int $puskesmasId, $startDate, $endDate, array $columns = ['*'])
    {
        return $this->model->where('puskesmas_id', $puskesmasId)
            ->whereBetween('examination_date', [$startDate, $endDate])
            ->get($columns);
    }

    /**
     * Find DM examinations by year and month
     */
    public function findByYearMonth(int $puskesmasId, int $year, int $month = null, array $columns = ['*'])
    {
        $query = $this->model->where('puskesmas_id', $puskesmasId)
            ->where('year', $year);
            
        if ($month) {
            $query->where('month', $month);
        }
        
        return $query->get($columns);
    }

    /**
     * Find DM examinations by archive status
     */
    public function findByArchiveStatus(int $puskesmasId, bool $isArchived, array $columns = ['*'])
    {
        return $this->model->where('puskesmas_id', $puskesmasId)
            ->where('is_archived', $isArchived)
            ->get($columns);
    }

    /**
     * Find DM examinations by patient ID and date
     */
    public function findByPatientAndDate(int $patientId, $date, array $columns = ['*'])
    {
        return $this->model->where('patient_id', $patientId)
            ->where('examination_date', $date)
            ->get($columns);
    }

    /**
     * Find DM examinations by examination type
     */
    public function findByExaminationType(int $puskesmasId, string $examinationType, array $columns = ['*'])
    {
        return $this->model->where('puskesmas_id', $puskesmasId)
            ->where('examination_type', $examinationType)
            ->get($columns);
    }
    
    /**
     * Get all examinations with pagination and filtering
     */
    public function getAllWithFilters(int $puskesmasId, array $filters = [], int $perPage = 15)
    {
        $query = $this->model->with('patient')
            ->where('puskesmas_id', $puskesmasId);
            
        // Apply year filter
        if (isset($filters['year'])) {
            $query->where('year', $filters['year']);
        }
        
        // Apply month filter
        if (isset($filters['month'])) {
            $query->where('month', $filters['month']);
        }
        
        // Apply archived filter
        if (isset($filters['is_archived'])) {
            $query->where('is_archived', filter_var($filters['is_archived'], FILTER_VALIDATE_BOOLEAN));
        }
        
        // Apply patient filter
        if (isset($filters['patient_id'])) {
            $query->where('patient_id', $filters['patient_id']);
        }
        
        return $query->orderBy('examination_date', 'desc')->paginate($perPage);
    }
    
    /**
     * Get examinations grouped by date for a patient
     */
    public function getByPatientGroupedByDate(int $patientId, int $year = null, int $month = null)
    {
        $query = $this->model->where('patient_id', $patientId);
        
        if ($year) {
            $query->where('year', $year);
        }
        
        if ($month) {
            $query->where('month', $month);
        }
        
        $examinations = $query->orderBy('examination_date', 'desc')->get();
        
        // Group by date
        $result = [];
        foreach ($examinations as $examination) {
            $date = $examination->examination_date->format('Y-m-d');
            
            if (!isset($result[$date])) {
                $result[$date] = [
                    'date' => $date,
                    'examinations' => []
                ];
            }
            
            $result[$date]['examinations'][] = [
                'id' => $examination->id,
                'type' => $examination->examination_type,
                'result' => $examination->result,
                'is_controlled' => $examination->isControlled(),
            ];
        }
        
        return array_values($result);
    }
}