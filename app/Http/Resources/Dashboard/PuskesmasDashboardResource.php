<?php

namespace App\Http\Resources\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PuskesmasDashboardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'year' => $this->resource['year'],
            'puskesmas' => $this->resource['puskesmas'],
            'current_month' => $this->resource['current_month'],
            'ht' => [
                'target' => $this->resource['ht']['target'],
                'total_patients' => $this->resource['ht']['total_patients'],
                'achievement_percentage' => $this->resource['ht']['achievement_percentage'],
                'standard_patients' => $this->resource['ht']['standard_patients'],
                'registered_patients' => $this->resource['ht']['registered_patients'],
                'current_month_exams' => $this->resource['ht']['current_month_exams'],
            ],
            'dm' => [
                'target' => $this->resource['dm']['target'],
                'total_patients' => $this->resource['dm']['total_patients'],
                'achievement_percentage' => $this->resource['dm']['achievement_percentage'],
                'standard_patients' => $this->resource['dm']['standard_patients'],
                'registered_patients' => $this->resource['dm']['registered_patients'],
                'current_month_exams' => $this->resource['dm']['current_month_exams'],
            ],
            'chart_data' => $this->resource['chart_data'],
            'print_data' => $this->resource['print_data'],
        ];
    }
}