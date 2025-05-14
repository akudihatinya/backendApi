<?php

namespace App\Http\Middleware\ApiProtection;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class JsonRequestMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Check if the request is expecting a JSON response
        if ($request->expectsJson() || $request->isJson() || $request->wantsJson()) {
            return $next($request);
        }

        // For non-GET requests, require Accept: application/json header
        if (!$request->isMethod('GET')) {
            return response()->json([
                'success' => false,
                'error' => 'JSON request expected. Add Accept: application/json header.',
            ], Response::HTTP_NOT_ACCEPTABLE);
        }

        // For GET requests, add application/json to the Accept header
        $request->headers->set('Accept', 'application/json');
        
        return $next($request);
    }
}