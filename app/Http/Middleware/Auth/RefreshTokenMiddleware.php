<?php

namespace App\Http\Middleware\Auth;

use Closure;
use Illuminate\Http\Request;
use App\Services\Auth\AuthService;
use Illuminate\Support\Facades\Log;

class RefreshTokenMiddleware
{
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Skip this middleware if there's no auth user or no refresh token in the request
        if (!$request->user() || !$request->has('refresh_token')) {
            return $next($request);
        }

        $response = $next($request);

        // Check if token is about to expire (less than 5 minutes left)
        $tokenExpiration = $request->user()->currentAccessToken()->expires_at;
        
        if ($tokenExpiration && now()->diffInMinutes($tokenExpiration) < 5) {
            Log::info('Token is about to expire, refreshing...');
            
            // Get the refresh token
            $refreshToken = $request->refresh_token;
            
            // Refresh the token
            $tokens = $this->authService->refreshToken($refreshToken);
            
            if ($tokens) {
                // Add the new tokens to the response
                $response->headers->set('X-New-Access-Token', $tokens['access_token']);
                $response->headers->set('X-New-Refresh-Token', $tokens['refresh_token']);
                $response->headers->set('X-Token-Expires-In', $tokens['expires_in']);
            }
        }

        return $response;
    }
}