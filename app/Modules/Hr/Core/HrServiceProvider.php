<?php

namespace App\Modules\Hr\Core;

use App\Models\HrFile;
use App\Modules\Hr\Core\Policies\HrFilePolicy;
use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\HrCalendarService;
use App\Modules\Hr\Core\Services\HrFileService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Organization\Models\HrDepartment;
use App\Modules\Hr\Organization\Models\HrDepartmentPolicy;
use App\Modules\Hr\Organization\Models\HrSgkWorkplace;
use App\Modules\Hr\Organization\Models\HrSgkWorkplacePolicy;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use App\Modules\Hr\Personnel\Policies\HrEmployeePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class HrServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
        $this->app->singleton(HrAuditService::class);
        $this->app->singleton(HrFileService::class);
        $this->app->singleton(HrCalendarService::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/Routes/hr.php');
        $this->loadViewsFrom(__DIR__ . '/Resources/views', 'hr');
        $this->mergeConfigFrom(base_path('config/hr.php'), 'hr');

        $this->publishes([
            base_path('config/hr.php') => config_path('hr.php'),
        ], 'hr-config');

        Gate::policy(HrFile::class, HrFilePolicy::class);
        Gate::policy(HrEmployee::class, HrEmployeePolicy::class);
        Gate::policy(HrDepartment::class, HrDepartmentPolicy::class);
        Gate::policy(HrSgkWorkplace::class, HrSgkWorkplacePolicy::class);
    }
}
