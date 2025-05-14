<?php

namespace App\Repositories\Contracts;

interface PatientRepositoryInterface extends RepositoryInterface
{
    public function findByPuskesmas(int $puskesmasId, array $columns = ['*']);
    public function findByDiseaseType(int $puskesmasId, string $diseaseType, array $columns = ['*']);
    public function findByDiseaseAndYear(int $puskesmasId, string $diseaseType, int $year, array $columns = ['*']);
    public function search(int $puskesmasId, string $term, array $columns = ['*']);
}