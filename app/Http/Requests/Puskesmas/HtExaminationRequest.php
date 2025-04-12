<?php

namespace App\Http\Requests\Puskesmas;

use Illuminate\Foundation\Http\FormRequest;

class HtExaminationRequest extends FormRequest
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
                    
                    if (!$patient->has_ht) {
                        $fail('Pasien tidak memiliki riwayat Hipertensi.');
                    }
                    
                    if ($patient->puskesmas_id !== auth()->user()->puskesmas->id) {
                        $fail('Pasien bukan milik Puskesmas ini.');
                    }
                },
            ],
            'examination_date' => 'required|date|before_or_equal:today',
            'systolic' => 'required|integer|min:60|max:300',
            'diastolic' => 'required|integer|min:40|max:200',
        ];
    }
}