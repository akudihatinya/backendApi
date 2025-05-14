<?php

namespace App\Repositories\Contracts;

interface HtExaminationRepositoryInterface extends RepositoryInterface
{
    public function findByPuskesmas(int $puskesmasId, array $columns = ['*']);
    public function findByPatient(int $patientId, array $columns = ['*']);
    public function findByDate(int $puskesmasId, $date, array $columns = ['*']);
    public function findByPeriod(int $puskesmasId, $startDate, $endDate, array $columns = ['*']);
    public function findByYearMonth(int $puskesmasId, int $year, int $month = null, array $columns = ['*']);
    public function findByArchiveStatus(int $puskesmasId, bool $isArchived, array $columns = ['*']);
}