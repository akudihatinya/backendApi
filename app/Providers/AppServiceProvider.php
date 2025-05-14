<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\HtExamination;
use App\Models\DmExamination;
use App\Observers\HtExaminationObserver;
use App\Observers\DmExaminationObserver;
use App\Services\Cache\StatisticsCacheService;
use App\Services\Auth\AuthService;
use App\Services\Patient\PatientService;
use App\Services\Patient\HtExaminationService;
use App\Services\Patient\DmExaminationService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register services
        $this->app->singleton(StatisticsCacheService::class, function ($app) {
            return new StatisticsCacheService();
        });

        $this->app->singleton(AuthService::class, function ($app) {
            return new AuthService();
        });

        $this->app->singleton(PatientService::class, function ($app) {
            return new PatientService(
                $app->make(\App\Repositories\Contracts\PatientRepositoryInterface::class)
            );
        });

        $this->app->singleton(HtExaminationService::class, function ($app) {
            return new HtExaminationService(
                $app->make(\App\Repositories\Contracts\HtExaminationRepositoryInterface::class)
            );
        });

        $this->app->singleton(DmExaminationService::class, function ($app) {
            return new DmExaminationService(
                $app->make(\App\Repositories\Contracts\DmExaminationRepositoryInterface::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register observers
        HtExamination::observe(HtExaminationObserver::class);
        DmExamination::observe(DmExaminationObserver::class);
    }
}