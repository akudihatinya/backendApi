<?php

namespace App\Http\Requests\Puskesmas;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->isPuskesmas();
    }

    public function rules(): array
    {
        return [
            'nik' => [
                'nullable',
                'string',
                'size:16',
                Rule::unique('patients')->ignore($this->patient),
            ],
            'bpjs_number' => 'nullable|string|max:20',
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'gender' => 'nullable|in:male,female',
            'birth_date' => 'nullable|date',
            'age' => 'nullable|integer|min:0|max:150',
            'has_ht' => 'sometimes|boolean',
            'has_dm' => 'sometimes|boolean',
        ];
    }
}