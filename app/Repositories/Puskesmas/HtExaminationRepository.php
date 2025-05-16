<?php

namespace App\Repositories\Puskesmas;

use App\Models\HtExamination;
use App\Repositories\Contracts\HtExaminationRepositoryInterface;
use App\Repositories\Eloquent\BaseRepository;
use Carbon\Carbon;

class HtExaminationRepository extends BaseRepository implements HtExaminationRepositoryInterface
{
    public function __construct(HtExamination $model)
    {
        parent::__construct($model);
    }

    /**
     * Find HT examinations by puskesmas ID
     */
    public function findByPuskesmas(int $puskesmasId, array $columns = ['*'])
    {
        return $this->model->where('puskesmas_id', $puskesmasId)->get($columns);
    }

    /**
     * Find HT examinations by patient ID
     */
    public function findByPatient(int $patientId, array $columns = ['*'])
    {
        return $this->model->where('patient_id', $patientId)->get($columns);
    }

    /**
     * Find HT examinations by date
     */
    public function findByDate(int $puskesmasId, $date, array $columns = ['*'])
    {
        return $this->model->where('puskesmas_id', $puskesmasId)
            ->where('examination_date', $date)
            ->get($columns);
    }

    /**
     * Find HT examinations by date range
     */
    public function findByPeriod(int $puskesmasId, $startDate, $endDate, array $columns = ['*'])
    {
        return $this->model->where('puskesmas_id', $puskesmasId)
            ->whereBetween('examination_date', [$startDate, $endDate])
            ->get($columns);
    }

    /**
     * Find HT examinations by year and month
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
     * Find HT examinations by archive status
     */
    public function findByArchiveStatus(int $puskesmasId, bool $isArchived, array $columns = ['*'])
    {
        return $this->model->where('puskesmas_id', $puskesmasId)
            ->where('is_archived', $isArchived)
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
     * Get controlled examinations for specified period
     */
    public function getControlledExaminations(int $puskesmasId, int $year, int $month = null)
    {
        $query = $this->model->where('puskesmas_id', $puskesmasId)
            ->where('year', $year)
            ->where(function ($q) {
                $q->where(function ($q) {
                    $q->whereBetween('systolic', [90, 139])
                      ->whereBetween('diastolic', [60, 89]);
                });
            });
            
        if ($month) {
            $query->where('month', $month);
        }
        
        return $query->get();
    }
    
    /**
     * Get uncontrolled examinations for specified period
     */
    public function getUncontrolledExaminations(int $puskesmasId, int $year, int $month = null)
    {
        $query = $this->model->where('puskesmas_id', $puskesmasId)
            ->where('year', $year)
            ->where(function ($q) {
                $q->whereNotBetween('systolic', [90, 139])
                  ->orWhereNotBetween('diastolic', [60, 89]);
            });
            
        if ($month) {
            $query->where('month', $month);
        }
        
        return $query->get();
    }
    
    /**
     * Get examinations grouped by month for a year
     */
    public function getExaminationsByMonth(int $puskesmasId, int $year)
    {
        $query = $this->model->where('puskesmas_id', $puskesmasId)
            ->where('year', $year)
            ->selectRaw('month, COUNT(*) as count, 
                         SUM(CASE WHEN systolic BETWEEN 90 AND 139 AND diastolic BETWEEN 60 AND 89 THEN 1 ELSE 0 END) as controlled_count')
            ->groupBy('month')
            ->orderBy('month');
            
        return $query->get();
    }
}