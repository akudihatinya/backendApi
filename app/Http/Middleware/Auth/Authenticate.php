<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;
use Closure;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        return $request->expectsJson() ? null : 404;
    }

    /**
     * Handle an incoming request.
     */
    public function handle($request, Closure $next, ...$guards)
    {
        // Extract token from cookie
        $token = $request->cookie('access_token');
        
        if ($token) {
            // Set Authorization header for Sanctum
            $request->headers->set('Authorization', 'Bearer ' . $token);
        }

        return parent::handle($request, $next, ...$guards);
    }
}