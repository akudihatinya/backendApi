<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // If the user is not logged in
        if (!$request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        // Role: admin
        if (in_array('admin', $roles) && $request->user()->is_admin) {
            return $next($request);
        }

        // Role: dinas
        if (in_array('dinas', $roles) && $request->user()->is_dinas) {
            return $next($request);
        }

        // Role: puskesmas
        if (in_array('puskesmas', $roles) && !$request->user()->is_admin && !$request->user()->is_dinas) {
            return $next($request);
        }

        // No matching role
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized. You do not have the required permissions.'
        ], 403);
    }
}