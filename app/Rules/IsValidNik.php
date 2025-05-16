<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class IsValidNik implements ValidationRule
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
        
        // NIK must be exactly 16 digits
        if (!preg_match('/^[0-9]{16}$/', $value)) {
            $fail('Format NIK tidak valid. NIK harus berupa 16 digit angka.');
            return;
        }
        
        // Extract date components
        $dateCode = substr($value, 6, 2); // Day component
        $monthCode = substr($value, 8, 2); // Month component
        
        // If gender is female, add 40 to date
        $date = (int)$dateCode;
        if ($date > 40) {
            $date -= 40;
        }
        
        // Validate date (1-31)
        if ($date < 1 || $date > 31) {
            $fail('Tanggal lahir pada NIK tidak valid.');
            return;
        }
        
        // Validate month (1-12)
        $month = (int)$monthCode;
        if ($month < 1 || $month > 12) {
            $fail('Bulan lahir pada NIK tidak valid.');
            return;
        }
        
        // Further validation could check province and district codes
        // For simplicity, we'll stop here as those checks would require lookup tables
    }
}