<?php

namespace App\Repositories\Contracts;

interface DmExaminationRepositoryInterface extends RepositoryInterface
{
    /**
     * Find DM examinations by puskesmas ID
     * 
     * @param int $puskesmasId
     * @param array $columns
     * @return mixed
     */
    public function findByPuskesmas(int $puskesmasId, array $columns = ['*']);

    /**
     * Find DM examinations by patient ID
     * 
     * @param int $patientId
     * @param array $columns
     * @return mixed
     */
    public function findByPatient(int $patientId, array $columns = ['*']);

    /**
     * Find DM examinations by date
     * 
     * @param int $puskesmasId
     * @param mixed $date
     * @param array $columns
     * @return mixed
     */
    public function findByDate(int $puskesmasId, $date, array $columns = ['*']);

    /**
     * Find DM examinations by date range
     * 
     * @param int $puskesmasId
     * @param mixed $startDate
     * @param mixed $endDate
     * @param array $columns
     * @return mixed
     */
    public function findByPeriod(int $puskesmasId, $startDate, $endDate, array $columns = ['*']);

    /**
     * Find DM examinations by year and month
     * 
     * @param int $puskesmasId
     * @param int $year
     * @param int|null $month
     * @param array $columns
     * @return mixed
     */
    public function findByYearMonth(int $puskesmasId, int $year, int $month = null, array $columns = ['*']);

    /**
     * Find DM examinations by archive status
     * 
     * @param int $puskesmasId
     * @param bool $isArchived
     * @param array $columns
     * @return mixed
     */
    public function findByArchiveStatus(int $puskesmasId, bool $isArchived, array $columns = ['*']);

    /**
     * Find DM examinations by patient ID and date
     * 
     * @param int $patientId
     * @param mixed $date
     * @param array $columns
     * @return mixed
     */
    public function findByPatientAndDate(int $patientId, $date, array $columns = ['*']);

    /**
     * Find DM examinations by examination type
     * 
     * @param int $puskesmasId
     * @param string $examinationType
     * @param array $columns
     * @return mixed
     */
    public function findByExaminationType(int $puskesmasId, string $examinationType, array $columns = ['*']);
}