<?php

namespace App\Rules;

use App\Models\Patient;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Auth;

class BelongsToPuskesmas implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Skip validation for admin users (they can access all data)
        if (Auth::user()->isAdmin()) {
            return;
        }
        
        // Skip validation if value is empty
        if (empty($value)) {
            return;
        }
        
        // Get current user's puskesmas ID
        $userPuskesmasId = Auth::user()->puskesmas_id;
        if (!$userPuskesmasId) {
            $fail('User tidak terkait dengan puskesmas manapun.');
            return;
        }
        
        // Check if the patient belongs to the user's puskesmas
        $patient = Patient::find($value);
        if (!$patient) {
            $fail('Pasien tidak ditemukan.');
            return;
        }
        
        if ($patient->puskesmas_id !== $userPuskesmasId) {
            $fail('Pasien tidak terdaftar di puskesmas Anda.');
            return;
        }
    }
}