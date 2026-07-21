<?php

namespace App\Modules\Hr\Core;

use App\Models\HrFile;
use App\Modules\Hr\Core\Policies\HrFilePolicy;
use App\Modules\Hr\Core\Services\ConfigBasedMalwareScanner;
use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\HrCalendarService;
use App\Modules\Hr\Core\Services\HrFileService;
use App\Modules\Hr\Core\Services\MalwareScanner;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Document\Events\EmployeeDocumentExpired;
use App\Modules\Hr\Document\Events\EmployeeDocumentRejected;
use App\Modules\Hr\Document\Events\EmployeeDocumentRequestFulfilled;
use App\Modules\Hr\Document\Events\EmployeeDocumentRequested;
use App\Modules\Hr\Document\Events\EmployeeDocumentUploaded;
use App\Modules\Hr\Document\Events\EmployeeDocumentVerified;
use App\Modules\Hr\Document\Listeners\FulfillDocumentRequest;
use App\Modules\Hr\Document\Listeners\InvalidateDocumentMetricsCache;
use App\Modules\Hr\Document\Listeners\LogDocumentEvent;
use App\Modules\Hr\Document\Listeners\SendDocumentNotification;
use App\Modules\Hr\Document\Models\HrDocumentType;
use App\Modules\Hr\Document\Models\HrEmployeeDocument;
use App\Modules\Hr\Document\Policies\HrDocumentTypePolicy;
use App\Modules\Hr\Document\Policies\HrEmployeeDocumentPolicy;
use App\Modules\Hr\Leave\Models\HrLeaveRequest;
use App\Modules\Hr\Leave\Models\HrLeaveType;
use App\Modules\Hr\Leave\Policies\HrLeaveRequestPolicy;
use App\Modules\Hr\Leave\Policies\HrLeaveTypePolicy;
use App\Modules\Hr\Leave\Events\LeaveRequested;
use App\Modules\Hr\Leave\Events\LeaveApproved;
use App\Modules\Hr\Leave\Events\LeaveRejected;
use App\Modules\Hr\Leave\Events\LeaveCancelled;
use App\Modules\Hr\Leave\Listeners\LogLeaveEvent;
use App\Modules\Hr\Organization\Models\HrDepartment;
use App\Modules\Hr\Organization\Models\HrDepartmentPolicy;
use App\Modules\Hr\Organization\Models\HrSgkWorkplace;
use App\Modules\Hr\Organization\Models\HrSgkWorkplacePolicy;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use App\Modules\Hr\Personnel\Policies\HrEmployeePolicy;
use Illuminate\Support\Facades\Event;
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
        $this->app->singleton(MalwareScanner::class, ConfigBasedMalwareScanner::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/Routes/hr.php');
        $this->loadViewsFrom(__DIR__ . '/Resources/views', 'hr');
        $this->mergeConfigFrom(base_path('config/hr.php'), 'hr');

        $this->publishes([
            base_path('config/hr.php') => config_path('hr.php'),
        ], 'hr-config');

        // Policies
        Gate::policy(HrFile::class, HrFilePolicy::class);
        Gate::policy(HrEmployee::class, HrEmployeePolicy::class);
        Gate::policy(HrDepartment::class, HrDepartmentPolicy::class);
        Gate::policy(HrSgkWorkplace::class, HrSgkWorkplacePolicy::class);
        Gate::policy(HrDocumentType::class, HrDocumentTypePolicy::class);
        Gate::policy(HrEmployeeDocument::class, HrEmployeeDocumentPolicy::class);
        Gate::policy(HrLeaveType::class, HrLeaveTypePolicy::class);
        Gate::policy(HrLeaveRequest::class, HrLeaveRequestPolicy::class);

        // Events & Listeners
        Event::listen(EmployeeDocumentUploaded::class, [LogDocumentEvent::class, 'handle']);
        Event::listen(EmployeeDocumentUploaded::class, [FulfillDocumentRequest::class, 'handle']);
        Event::listen(EmployeeDocumentUploaded::class, [InvalidateDocumentMetricsCache::class, 'handle']);
        Event::listen(EmployeeDocumentUploaded::class, [SendDocumentNotification::class, 'handle']);

        Event::listen(EmployeeDocumentVerified::class, [LogDocumentEvent::class, 'handle']);
        Event::listen(EmployeeDocumentVerified::class, [InvalidateDocumentMetricsCache::class, 'handle']);
        Event::listen(EmployeeDocumentVerified::class, [SendDocumentNotification::class, 'handle']);

        Event::listen(EmployeeDocumentRejected::class, [LogDocumentEvent::class, 'handle']);
        Event::listen(EmployeeDocumentRejected::class, [SendDocumentNotification::class, 'handle']);

        Event::listen(EmployeeDocumentExpired::class, [LogDocumentEvent::class, 'handle']);
        Event::listen(EmployeeDocumentExpired::class, [InvalidateDocumentMetricsCache::class, 'handle']);

        Event::listen(EmployeeDocumentRequested::class, [LogDocumentEvent::class, 'handle']);
        Event::listen(EmployeeDocumentRequested::class, [SendDocumentNotification::class, 'handle']);

        Event::listen(EmployeeDocumentRequestFulfilled::class, [LogDocumentEvent::class, 'handle']);
        Event::listen(EmployeeDocumentRequestFulfilled::class, [InvalidateDocumentMetricsCache::class, 'handle']);
        Event::listen(EmployeeDocumentRequestFulfilled::class, [SendDocumentNotification::class, 'handle']);

        Event::listen(LeaveRequested::class, [LogLeaveEvent::class, 'handle']);
        Event::listen(LeaveApproved::class, [LogLeaveEvent::class, 'handle']);
        Event::listen(LeaveRejected::class, [LogLeaveEvent::class, 'handle']);
        Event::listen(LeaveCancelled::class, [LogLeaveEvent::class, 'handle']);
    }
}
