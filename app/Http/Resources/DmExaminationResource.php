<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DmExaminationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Get all examination types for the patient on the same date
        $examinationResults = [
            'hba1c' => null,
            'gdp' => null,
            'gd2jpp' => null,
            'gdsp' => null,
        ];
        
        // Set the current examination result
        $examinationResults[$this->examination_type] = $this->result;
        
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'patient_name' => $this->patient->name,
            'puskesmas_id' => $this->puskesmas_id,
            'examination_date' => $this->examination_date->format('Y-m-d'),
            'examination_results' => $examinationResults,
            'year' => $this->year,
            'month' => $this->month,
            'is_controlled' => $this->isControlled(),
            'is_archived' => $this->is_archived,
        ];
    }
}