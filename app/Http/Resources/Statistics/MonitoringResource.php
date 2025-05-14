<?php

namespace App\Http\Resources\Statistics;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MonitoringResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Handle collection of patient monitoring data
        if (isset($this->resource['patients'])) {
            return [
                'puskesmas_id' => $this->resource['puskesmas_id'],
                'puskesmas_name' => $this->resource['puskesmas_name'],
                'year' => $this->resource['year'],
                'month' => $this->resource['month'],
                'month_name' => $this->resource['month_name'],
                'days_in_month' => $this->resource['days_in_month'],
                'disease_type' => $this->resource['disease_type'],
                'patients' => $this->resource['patients'],
                'total_patients' => count($this->resource['patients']),
                'visit_summary' => $this->resource['visit_summary'] ?? [],
            ];
        }

        // Handle individual patient monitoring data
        return [
            'patient_id' => $this->resource['patient_id'],
            'patient_name' => $this->resource['patient_name'],
            'medical_record_number' => $this->resource['medical_record_number'] ?? null,
            'gender' => $this->resource['gender'],
            'age' => $this->resource['age'],
            'attendance' => $this->resource['attendance'],
            'visit_count' => $this->resource['visit_count'],
        ];
    }
}