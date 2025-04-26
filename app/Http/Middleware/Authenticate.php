<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        return $request->expectsJson() ? null : route('login');
    }

    public function authenticate($request, array $guards)
    {
        $token = $request->cookie('access_token');

        if (empty($token)) {
            $this->unauthenticated($request, $guards);
        }

        $request->headers->set('Authorization', 'Bearer ' . $token);

        parent::authenticate($request, $guards);
    }
}
