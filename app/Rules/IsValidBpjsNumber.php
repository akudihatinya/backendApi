<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class IsValidBpjsNumber implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Skip validation if value is empty
        if (empty($value)) {
            return;
        }
        
        // BPJS number must be 13 digits
        if (!preg_match('/^[0-9]{13}$/', $value)) {
            $fail('Format Nomor BPJS tidak valid. Nomor BPJS harus berupa 13 digit angka.');
            return;
        }
        
        // Additional validation could include a checksum verification
        // For simplicity, we'll just check the length and digit requirement
    }
}