<?php
// app/Http/Middleware/EnsureSessionContinuity.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class EnsureSessionContinuity
{
    public function handle(Request $request, Closure $next)
    {
        // Log session ID untuk debugging
        Log::info('Request Path: ' . $request->path());
        Log::info('Current Session ID: ' . session()->getId());
        Log::info('Is Authenticated: ' . (Auth::check() ? 'Yes' : 'No'));

        // Jika user tidak terautentikasi tapi punya cookie session
        if (!Auth::check() && $request->hasCookie('laravel_session')) {
            Log::info('Session cookie exists but user is not authenticated');
        }

        $response = $next($request);

        // Pastikan session ID TIDAK berubah setelah request
        Log::info('Session ID after request: ' . session()->getId());

        // Periksa semua cookie yang akan di-set
        if (method_exists($response, 'headers') && $response->headers) {
            $allHeaders = $response->headers->all();
            if (isset($allHeaders['set-cookie'])) {
                if (is_array($allHeaders['set-cookie'])) {
                    foreach ($allHeaders['set-cookie'] as $cookie) {
                        if (is_string($cookie) && strpos($cookie, 'laravel_session') !== false) {
                            Log::info('Setting new session cookie: ' . $cookie);
                        }
                    }
                } else if (
                    is_string($allHeaders['set-cookie']) &&
                    strpos($allHeaders['set-cookie'], 'laravel_session') !== false
                ) {
                    Log::info('Setting new session cookie: ' . $allHeaders['set-cookie']);
                }
            }
        }

        return $response;
    }
}
