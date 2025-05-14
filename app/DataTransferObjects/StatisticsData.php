<?php

namespace App\DataTransferObjects;

class StatisticsData
{
    public function __construct(
        public readonly int $year,
        public readonly ?int $month,
        public readonly string $disease_type,
        public readonly ?int $puskesmas_id,
        public readonly int $target_count,
        public readonly int $total_patients,
        public readonly int $standard_patients,
        public readonly int $non_standard_patients,
        public readonly int $male_patients,
        public readonly int $female_patients,
        public readonly float $achievement_percentage,
        public readonly float $standard_percentage,
        public readonly array $monthly_data = []
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            year: $data['year'],
            month: $data['month'] ?? null,
            disease_type: $data['disease_type'],
            puskesmas_id: $data['puskesmas_id'] ?? null,
            target_count: $data['target_count'] ?? 0,
            total_patients: $data['total_patients'] ?? 0,
            standard_patients: $data['standard_patients'] ?? 0,
            non_standard_patients: $data['non_standard_patients'] ?? 0,
            male_patients: $data['male_patients'] ?? 0,
            female_patients: $data['female_patients'] ?? 0,
            achievement_percentage: $data['achievement_percentage'] ?? 0,
            standard_percentage: $data['standard_percentage'] ?? 0,
            monthly_data: $data['monthly_data'] ?? []
        );
    }

    public function toArray(): array
    {
        return [
            'year' => $this->year,
            'month' => $this->month,
            'disease_type' => $this->disease_type,
            'puskesmas_id' => $this->puskesmas_id,
            'target_count' => $this->target_count,
            'total_patients' => $this->total_patients,
            'standard_patients' => $this->standard_patients,
            'non_standard_patients' => $this->non_standard_patients,
            'male_patients' => $this->male_patients,
            'female_patients' => $this->female_patients,
            'achievement_percentage' => $this->achievement_percentage,
            'standard_percentage' => $this->standard_percentage,
            'monthly_data' => $this->monthly_data,
        ];
    }
    
    /**
     * Create a monthly statistics data object
     */
    public static function createMonthlyStatistics(
        int $puskesmas_id,
        string $disease_type,
        int $year,
        int $month,
        int $male_count,
        int $female_count,
        int $total_count,
        int $standard_count,
        int $non_standard_count,
        int $target_count = 0
    ): self {
        $achievement_percentage = $target_count > 0
            ? round(($standard_count / $target_count) * 100, 2)
            : 0;
            
        $standard_percentage = $total_count > 0
            ? round(($standard_count / $total_count) * 100, 2)
            : 0;
            
        return new self(
            year: $year,
            month: $month,
            disease_type: $disease_type,
            puskesmas_id: $puskesmas_id,
            target_count: $target_count,
            total_patients: $total_count,
            standard_patients: $standard_count,
            non_standard_patients: $non_standard_count,
            male_patients: $male_count,
            female_patients: $female_count,
            achievement_percentage: $achievement_percentage,
            standard_percentage: $standard_percentage,
            monthly_data: []
        );
    }
}