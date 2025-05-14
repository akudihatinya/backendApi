<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsPuskesmas
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() || $request->user()->role !== 'puskesmas') {
            return response()->json([
                'message' => 'Akses ditolak. Anda bukan Puskesmas.',
            ], 403);
        }

        return $next($request);
    }
}