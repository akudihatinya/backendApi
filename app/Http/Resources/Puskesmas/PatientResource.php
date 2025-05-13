<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PatientResource extends JsonResource
{
    public function toArray($request)
    {
        // Get the year parameter from the request if available
        $year = $request->year;
        
        // Safely get year arrays
        $htYears = $this->safeGetYears($this->ht_years);
        $dmYears = $this->safeGetYears($this->dm_years);
        
        $data = [
            'id' => $this->id,
            'puskesmas_id' => $this->puskesmas_id,
            'nik' => $this->nik,
            'bpjs_number' => $this->bpjs_number,
            'medical_record_number' => $this->medical_record_number,
            'name' => $this->name,
            'address' => $this->address,
            'gender' => $this->gender,
            'birth_date' => $this->birth_date ? $this->birth_date->format('Y-m-d') : null,
            'age' => $this->age,
            'ht_years' => $htYears,
            'dm_years' => $dmYears,
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
        ];
        
        // If a specific year is requested, calculate has_ht and has_dm for that year
        if ($year) {
            $data['has_ht'] = in_array($year, $htYears);
            $data['has_dm'] = in_array($year, $dmYears);
        } else {
            // Otherwise use the dynamic accessors
            $data['has_ht'] = !empty($htYears);
            $data['has_dm'] = !empty($dmYears);
        }
        
        return $data;
    }
    
    /**
     * Safely get years array from various possible formats
     */
    private function safeGetYears($years)
    {
        // If it's null, return empty array
        if (is_null($years)) {
            return [];
        }
        
        // If it's already an array, return it
        if (is_array($years)) {
            return $years;
        }
        
        // If it's a string, try to decode it
        if (is_string($years)) {
            $decoded = json_decode($years, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }
        
        // Default fallback
        return [];
    }
}