<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'username' => [
                'sometimes',
                'string',
                Rule::unique('users')->ignore($this->user),
            ],
            'name' => 'sometimes|string|max:255',
            'password' => 'sometimes|string|min:8|nullable',
            'profile_picture' => 'sometimes|image|max:2048|nullable',
        ];
    }
}
