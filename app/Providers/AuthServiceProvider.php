<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\HtExamination;
use App\Models\DmExamination;
use App\Models\Patient;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Define gates for admin and puskesmas roles
        Gate::define('admin', function ($user) {
            return $user->role === 'admin';
        });

        Gate::define('puskesmas', function ($user) {
            return $user->role === 'puskesmas';
        });

        // Define gates for managing data
        Gate::define('manage-patients', function ($user, Patient $patient = null) {
            if (!$user->isPuskesmas()) {
                return false;
            }

            if (!$patient) {
                return true;
            }

            return $patient->puskesmas_id === $user->puskesmas->id;
        });

        Gate::define('manage-ht-examinations', function ($user, HtExamination $examination = null) {
            if (!$user->isPuskesmas()) {
                return false;
            }

            if (!$examination) {
                return true;
            }

            return $examination->puskesmas_id === $user->puskesmas->id;
        });

        Gate::define('manage-dm-examinations', function ($user, DmExamination $examination = null) {
            if (!$user->isPuskesmas()) {
                return false;
            }

            if (!$examination) {
                return true;
            }

            return $examination->puskesmas_id === $user->puskesmas->id;
        });
    }
}