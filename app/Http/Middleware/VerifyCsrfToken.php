<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        // Sanctum csrf-cookie endpoint must be excluded
        'sanctum/csrf-cookie',
        // Public API endpoints that don't require CSRF
        'api/login',
        'api/refresh'
    ];
}