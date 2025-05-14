<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminOrPuskesmas
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
        if (Auth::check()) {
            $user = Auth::user();
            
            // Berikan akses jika user adalah admin atau memiliki role puskesmas
            if ($user->isAdmin() || $user->isPuskesmas()) {
                return $next($request);
            }
        }
        
        return response()->json(['message' => 'Unauthorized. Anda tidak memiliki akses.'], 403);
    }
}