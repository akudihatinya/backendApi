<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HtExaminationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'patient_name' => $this->patient->name,
            'puskesmas_id' => $this->puskesmas_id,
            'examination_date' => $this->examination_date->format('Y-m-d'),
            'systolic' => $this->systolic,
            'diastolic' => $this->diastolic,
            'year' => $this->year,
            'month' => $this->month,
            'is_archived' => $this->is_archived,
            'is_controlled' => $this->isControlled(),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}