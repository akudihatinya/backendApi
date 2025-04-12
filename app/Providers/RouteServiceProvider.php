<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Jalur ke "home" aplikasi kamu (digunakan untuk redirect setelah login).
     */
    public const HOME = '/home';

    /**
     * Daftarkan semua route aplikasi.
     */
    public function boot(): void
    {
        $this->routes(function () {
            // API Routes
            Route::prefix('api')
                ->middleware('api')
                ->group(base_path('routes/api.php'));
        });
    }
}
