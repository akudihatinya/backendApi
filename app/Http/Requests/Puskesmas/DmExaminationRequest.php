<?php

namespace App\Http\Requests\Puskesmas;

use Illuminate\Foundation\Http\FormRequest;

class DmExaminationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->isPuskesmas();
    }

    public function rules(): array
    {
        return [
            'patient_id' => [
                'required',
                'exists:patients,id',
                function ($attribute, $value, $fail) {
                    $patient = \App\Models\Patient::find($value);
                    
                    if (!$patient) {
                        $fail('Pasien tidak ditemukan.');
                        return;
                    }
                    
                    if ($patient->puskesmas_id !== auth()->user()->puskesmas->id) {
                        $fail('Pasien bukan milik Puskesmas ini.');
                    }
                },
            ],
            'examination_date' => 'required|date|before_or_equal:today',
            'examinations' => 'required|array',
            'examinations.hba1c' => 'nullable|numeric|min:0|max:1000',
            'examinations.gdp' => 'nullable|numeric|min:0|max:1000',
            'examinations.gd2jpp' => 'nullable|numeric|min:0|max:1000',
            'examinations.gdsp' => 'nullable|numeric|min:0|max:1000',
        ];
    }
}