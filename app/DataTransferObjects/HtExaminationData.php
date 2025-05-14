<?php

namespace App\DataTransferObjects;

use Carbon\Carbon;

class HtExaminationData
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $patient_id,
        public readonly int $puskesmas_id,
        public readonly string $examination_date,
        public readonly int $systolic,
        public readonly int $diastolic, 
        public readonly ?int $year = null,
        public readonly ?int $month = null,
        public readonly bool $is_archived = false
    ) {
        // If year and month are not provided, derive them from examination_date
        if ($this->year === null || $this->month === null) {
            $date = Carbon::parse($this->examination_date);
            $this->year = $date->year;
            $this->month = $date->month;
        }

        // Set archived status based on year (if older than current year)
        if ($this->is_archived === false) {
            $this->is_archived = $this->year < Carbon::now()->year;
        }
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? null,
            patient_id: $data['patient_id'],
            puskesmas_id: $data['puskesmas_id'],
            examination_date: $data['examination_date'],
            systolic: $data['systolic'],
            diastolic: $data['diastolic'],
            year: $data['year'] ?? null,
            month: $data['month'] ?? null,
            is_archived: $data['is_archived'] ?? false
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'puskesmas_id' => $this->puskesmas_id,
            'examination_date' => $this->examination_date,
            'systolic' => $this->systolic,
            'diastolic' => $this->diastolic,
            'year' => $this->year,
            'month' => $this->month,
            'is_archived' => $this->is_archived,
        ];
    }

    public function isControlled(): bool
    {
        return $this->systolic >= 90 && $this->systolic <= 139 && 
               $this->diastolic >= 60 && $this->diastolic <= 89;
    }
}