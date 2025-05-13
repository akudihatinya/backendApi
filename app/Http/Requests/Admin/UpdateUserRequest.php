<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Izinkan jika user adalah admin ATAU user sedang update dirinya sendiri
        $user = $this->user(); // yang login
        $updating = $this->route('user'); // user yang sedang diedit di route (bisa null di /me)

        return $user && (
            $user->isAdmin() ||
            !$updating || $user->id === optional($updating)->id
        );
    }

    public function rules(): array
    {
        // Ambil ID user yang sedang diedit (atau user login jika route /me)
        $editingUser = $this->route('user') ?? $this->user();

        return [
            'username' => [
                'sometimes',
                'string',
                Rule::unique('users')->ignore($editingUser?->id),
            ],
            'name' => 'sometimes|string|max:255',
            'password' => 'sometimes|string|min:8|nullable|confirmed',
            'profile_picture' => 'sometimes|image|max:2048|nullable',
        ];
    }
}