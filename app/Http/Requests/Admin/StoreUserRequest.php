<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::user()->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'username' => 'required|string|max:255|unique:users,username',
            'password' => 'required|string|min:8',
            'name' => 'required|string|max:255',
            'role' => 'required|in:puskesmas', // Hanya bisa membuat user puskesmas
            'profile_picture' => 'nullable|image|max:2048',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'username.required' => 'Username wajib diisi',
            'username.unique' => 'Username sudah digunakan',
            'password.required' => 'Password wajib diisi',
            'password.min' => 'Password minimal 8 karakter',
            'name.required' => 'Nama wajib diisi',
            'role.required' => 'Role wajib diisi',
            'role.in' => 'Role harus puskesmas',
            'profile_picture.image' => 'File harus berupa gambar',
        ];
    }
    
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Jika profile_picture adalah string dan bukan file
        if ($this->has('profile_picture') && 
            is_string($this->profile_picture) && 
            !$this->hasFile('profile_picture')) {
            
            // Jika kosong, set null
            if (empty(trim($this->profile_picture))) {
                $this->merge([
                    'profile_picture' => null
                ]);
            }
        }
    }
}