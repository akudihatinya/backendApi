<?php

namespace App\Repositories\Contracts;

interface PatientRepositoryInterface extends RepositoryInterface
{
    /**
     * Find patients by puskesmas ID
     * 
     * @param int $puskesmasId
     * @param array $columns
     * @return mixed
     */
    public function findByPuskesmas(int $puskesmasId, array $columns = ['*']);

    /**
     * Find patients by disease type
     * 
     * @param int $puskesmasId
     * @param string $diseaseType 'ht', 'dm', or 'both'
     * @param array $columns
     * @return mixed
     */
    public function findByDiseaseType(int $puskesmasId, string $diseaseType, array $columns = ['*']);

    /**
     * Find patients by disease type and year
     * 
     * @param int $puskesmasId
     * @param string $diseaseType 'ht', 'dm', or 'both'
     * @param int $year
     * @param array $columns
     * @return mixed
     */
    public function findByDiseaseAndYear(int $puskesmasId, string $diseaseType, int $year, array $columns = ['*']);

    /**
     * Search patients by name, NIK, or BPJS number
     * 
     * @param int $puskesmasId
     * @param string $term
     * @param array $columns
     * @return mixed
     */
    public function search(int $puskesmasId, string $term, array $columns = ['*']);
}