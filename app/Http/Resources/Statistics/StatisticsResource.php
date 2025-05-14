<?php

namespace App\Http\Resources\Statistics;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StatisticsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'puskesmas_id' => $this->resource['puskesmas_id'],
            'puskesmas_name' => $this->resource['puskesmas_name'],
            'ranking' => $this->resource['ranking'],
        ];

        // Add HT data if available
        if (isset($this->resource['ht'])) {
            $data['ht'] = [
                'target' => $this->resource['ht']['target'],
                'total_patients' => $this->resource['ht']['total_patients'],
                'achievement_percentage' => $this->resource['ht']['achievement_percentage'],
                'standard_patients' => $this->resource['ht']['standard_patients'],
                'non_standard_patients' => $this->resource['ht']['non_standard_patients'],
                'male_patients' => $this->resource['ht']['male_patients'],
                'female_patients' => $this->resource['ht']['female_patients'],
            ];

            // Add monthly data if present
            if (isset($this->resource['ht']['monthly_data'])) {
                $data['ht']['monthly_data'] = $this->resource['ht']['monthly_data'];
            }
        }

        // Add DM data if available
        if (isset($this->resource['dm'])) {
            $data['dm'] = [
                'target' => $this->resource['dm']['target'],
                'total_patients' => $this->resource['dm']['total_patients'],
                'achievement_percentage' => $this->resource['dm']['achievement_percentage'],
                'standard_patients' => $this->resource['dm']['standard_patients'],
                'non_standard_patients' => $this->resource['dm']['non_standard_patients'],
                'male_patients' => $this->resource['dm']['male_patients'],
                'female_patients' => $this->resource['dm']['female_patients'],
            ];

            // Add monthly data if present
            if (isset($this->resource['dm']['monthly_data'])) {
                $data['dm']['monthly_data'] = $this->resource['dm']['monthly_data'];
            }
        }

        // Add combined achievement if available
        if (isset($this->resource['combined_achievement'])) {
            $data['combined_achievement'] = $this->resource['combined_achievement'];
        }

        return $data;
    }
}