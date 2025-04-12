<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class YearlyTargetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'puskesmas_id' => 'required|exists:puskesmas,id',
            'disease_type' => 'required|in:ht,dm',
            'year' => 'required|integer|min:2000|max:2100',
            'target_count' => 'required|integer|min:1',
        ];
    }
}
