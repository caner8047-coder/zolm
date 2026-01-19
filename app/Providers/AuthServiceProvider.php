<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [];

    public function boot(): void
    {
        // Admin gate
        Gate::define('admin', function (User $user) {
            return $user->isAdmin();
        });

        // Production access gate
        Gate::define('accessProduction', function (User $user) {
            return $user->canAccessProduction();
        });

        // Operation access gate
        Gate::define('accessOperation', function (User $user) {
            return $user->canAccessOperation();
        });

        // Reports access gate
        Gate::define('accessReports', function (User $user) {
            return $user->canAccessReports();
        });
    }
}
