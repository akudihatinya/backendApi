<?php

namespace App\Http\Resources\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminDashboardResource extends JsonResource
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
            'month' => $this->resource['month'],
            'month_name' => $this->resource['month_name'],
            'total_puskesmas' => $this->resource['total_puskesmas'],
            'summary' => [
                'ht' => [
                    'target' => $this->resource['summary']['ht']['target'],
                    'total_patients' => $this->resource['summary']['ht']['total_patients'],
                    'achievement_percentage' => $this->resource['summary']['ht']['achievement_percentage'],
                    'standard_patients' => $this->resource['summary']['ht']['standard_patients'],
                ],
                'dm' => [
                    'target' => $this->resource['summary']['dm']['target'],
                    'total_patients' => $this->resource['summary']['dm']['total_patients'],
                    'achievement_percentage' => $this->resource['summary']['dm']['achievement_percentage'],
                    'standard_patients' => $this->resource['summary']['dm']['standard_patients'],
                ],
            ],
            'chart_data' => $this->resource['chart_data'],
            'rankings' => [
                'top_puskesmas' => $this->resource['rankings']['top_puskesmas'],
                'bottom_puskesmas' => $this->resource['rankings']['bottom_puskesmas'],
            ],
            'all_puskesmas' => $this->resource['all_puskesmas'],
            'print_data' => $this->resource['print_data'],
        ];
    }
}