<?php

namespace App\Http\Middleware\DataSanitization;

use Closure;
use Illuminate\Foundation\Http\Middleware\TransformsRequest;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ConvertEmptyStringsToNull extends TransformsRequest
{
    /**
     * Transform the given value.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    protected function transform($key, $value)
    {
        // Skip transformation for files and password fields
        if (is_string($value) && $value === '' && !in_array($key, ['password', 'password_confirmation']) && !is_file($value)) {
            return null;
        }

        return $value;
    }
}