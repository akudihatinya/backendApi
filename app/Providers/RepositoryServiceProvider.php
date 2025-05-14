<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register repositories
        $this->app->bind(
            \App\Repositories\Contracts\RepositoryInterface::class,
            \App\Repositories\Eloquent\BaseRepository::class
        );

        $this->app->bind(
            \App\Repositories\Contracts\UserRepositoryInterface::class,
            \App\Repositories\Eloquent\UserRepository::class
        );

        $this->app->bind(
            \App\Repositories\Contracts\PatientRepositoryInterface::class,
            \App\Repositories\Eloquent\PatientRepository::class
        );

        $this->app->bind(
            \App\Repositories\Contracts\HtExaminationRepositoryInterface::class,
            \App\Repositories\Eloquent\HtExaminationRepository::class
        );

        $this->app->bind(
            \App\Repositories\Contracts\DmExaminationRepositoryInterface::class,
            \App\Repositories\Eloquent\DmExaminationRepository::class
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}