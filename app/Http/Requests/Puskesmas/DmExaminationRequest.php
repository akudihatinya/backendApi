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
                    
                    if (!$patient->has_dm) {
                        $fail('Pasien tidak memiliki riwayat Diabetes Mellitus.');
                    }
                    
                    if ($patient->puskesmas_id !== auth()->user()->puskesmas->id) {
                        $fail('Pasien bukan milik Puskesmas ini.');
                    }
                },
            ],
            'examination_date' => 'required|date|before_or_equal:today',
            'examination_type' => 'required|in:hba1c,gdp,gd2jpp,gdsp',
            'result' => 'required|numeric|min:0|max:1000',
        ];
    }
}