<?php

namespace App\Repositories\Eloquent;

use App\Models\DmExamination;
use App\Repositories\Contracts\DmExaminationRepositoryInterface;
use Carbon\Carbon;

class DmExaminationRepository extends BaseRepository implements DmExaminationRepositoryInterface
{
    public function __construct(DmExamination $model)
    {
        parent::__construct($model);
    }

    public function findByPuskesmas(int $puskesmasId, array $columns = ['*'])
    {
        return $this->model->where('puskesmas_id', $puskesmasId)
            ->with('patient')
            ->orderBy('examination_date', 'desc')
            ->get($columns);
    }

    public function findByPatient(int $patientId, array $columns = ['*'])
    {
        return $this->model->where('patient_id', $patientId)
            ->orderBy('examination_date', 'desc')
            ->get($columns);
    }

    public function findByDate(int $puskesmasId, $date, array $columns = ['*'])
    {
        return $this->model->where('puskesmas_id', $puskesmasId)
            ->whereDate('examination_date', $date)
            ->with('patient')
            ->orderBy('examination_date', 'desc')
            ->get($columns);
    }

    public function findByPeriod(int $puskesmasId, $startDate, $endDate, array $columns = ['*'])
    {
        return $this->model->where('puskesmas_id', $puskesmasId)
            ->whereBetween('examination_date', [$startDate, $endDate])
            ->with('patient')
            ->orderBy('examination_date', 'desc')
            ->get($columns);
    }

    public function findByYearMonth(int $puskesmasId, int $year, int $month = null, array $columns = ['*'])
    {
        $query = $this->model->where('puskesmas_id', $puskesmasId)
            ->where('year', $year)
            ->with('patient');

        if ($month) {
            $query->where('month', $month);
        }

        return $query->orderBy('examination_date', 'desc')->get($columns);
    }

    public function findByArchiveStatus(int $puskesmasId, bool $isArchived, array $columns = ['*'])
    {
        return $this->model->where('puskesmas_id', $puskesmasId)
            ->where('is_archived', $isArchived)
            ->with('patient')
            ->orderBy('examination_date', 'desc')
            ->get($columns);
    }

    public function findByPatientAndDate(int $patientId, $date, array $columns = ['*'])
    {
        return $this->model->where('patient_id', $patientId)
            ->whereDate('examination_date', $date)
            ->orderBy('examination_type', 'asc')
            ->get($columns);
    }

    public function findByExaminationType(int $puskesmasId, string $examinationType, array $columns = ['*'])
    {
        return $this->model->where('puskesmas_id', $puskesmasId)
            ->where('examination_type', $examinationType)
            ->with('patient')
            ->orderBy('examination_date', 'desc')
            ->get($columns);
    }
}