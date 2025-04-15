<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DmExaminationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'patient_name' => $this->patient->name,
            'puskesmas_id' => $this->puskesmas_id,
            'examination_date' => $this->examination_date->format('Y-m-d'),

            'gula_darah_puasa' => $this->whenLoaded('gdp', fn() => $this->gdp ?? null),
            'gula_darah_dua_jam' => $this->whenLoaded('gd2jpp', fn() => $this->gd2jpp ?? null),
            'gula_darah_sewaktu' => $this->whenLoaded('gdsp', fn() => $this->gdsp ?? null),
            'hbA1c' => $this->whenLoaded('hbA1c', fn() => $this->hbA1c ?? null),


            'year' => $this->year,
            'month' => $this->month,
            'is_archived' => $this->is_archived,
            'is_controlled' => $this->isControlled(),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
