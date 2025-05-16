<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Http\Kernel;
use App\Http\Middleware\ApiProtection\ApiRateLimiter;
use App\Http\Middleware\ApiProtection\ApiResponseFormat;
use App\Http\Middleware\ApiProtection\JsonRequestMiddleware;
use App\Http\Middleware\Auth\RefreshTokenMiddleware;
use App\Http\Middleware\Authorization\AdminOrPuskesmas;
use App\Http\Middleware\Authorization\CheckUserRole;
use App\Http\Middleware\Authorization\IsAdmin;
use App\Http\Middleware\Authorization\IsPuskesmas;
use App\Http\Middleware\DataSanitization\ConvertEmptyStringsToNull;

class MiddlewareServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $kernel = $this->app->make(Kernel::class);
        
        // Register global middleware
        $kernel->pushMiddleware(ConvertEmptyStringsToNull::class);
        
        // Register route middleware
        $router = $this->app['router'];
        
        $router->aliasMiddleware('api.rate_limit', ApiRateLimiter::class);
        $router->aliasMiddleware('api.json_format', ApiResponseFormat::class);
        $router->aliasMiddleware('api.json_request', JsonRequestMiddleware::class);
        $router->aliasMiddleware('refresh_token', RefreshTokenMiddleware::class);
        $router->aliasMiddleware('is_admin', IsAdmin::class);
        $router->aliasMiddleware('is_puskesmas', IsPuskesmas::class);
        $router->aliasMiddleware('admin_or_puskesmas', AdminOrPuskesmas::class);
        $router->aliasMiddleware('check_user_role', CheckUserRole::class);
    }
}