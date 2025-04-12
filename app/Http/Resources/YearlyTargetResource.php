<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class YearlyTargetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'puskesmas_id' => $this->puskesmas_id,
            'puskesmas_name' => $this->puskesmas->name,
            'disease_type' => $this->disease_type,
            'year' => $this->year,
            'target_count' => $this->target_count,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
