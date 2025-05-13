<?php

namespace App\Http\Requests\Puskesmas;

use Illuminate\Foundation\Http\FormRequest;

class PatientRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $patientId = $this->route('patient') ? $this->route('patient')->id : null;
        
        return [
            'nik' => 'nullable|string|size:16|unique:patients,nik,' . $patientId,
            'bpjs_number' => 'nullable|string|max:20',
            'medical_record_number' => 'nullable|string|max:50',
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'gender' => 'nullable|in:male,female',
            'birth_date' => 'nullable|date',
            'age' => 'nullable|integer|min:0|max:150',
            'ht_years' => 'nullable|array',
            'ht_years.*' => 'integer|digits:4',
            'dm_years' => 'nullable|array',
            'dm_years.*' => 'integer|digits:4',
        ];
    }
}