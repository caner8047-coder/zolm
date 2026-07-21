<?php

namespace App\Modules\Hr\Core\Livewire;

use App\Models\HrHoliday;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Document\Services\DocumentDashboardMetricsService;
use App\Modules\Hr\Leave\Enums\LeaveRequestStatus;
use App\Modules\Hr\Leave\Models\HrLeaveBalance;
use App\Modules\Hr\Leave\Models\HrLeaveRequest;
use App\Modules\Hr\Leave\Services\LeaveDashboardMetricsService;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Livewire\Component;

class HrDashboard extends Component
{
    public function render()
    {
        $tenant = app(TenantContext::class)->get();
        $modules = config('hr.modules', []);

        $activeModules = collect($modules)->filter(fn($m) => $m['enabled'] ?? true);

        // Belge metrik kartları: gerçek veriye dayalı, tenant bazlı. Yalnızca
        // hr.documents.view izni olan kullanıcı için hesaplanır.
        $documentMetrics = [];
        $leaveMetrics = [];
        $employeeWorkspace = null;
        $user = auth()->user();
        if ($user && $user->hasHrPermission('hr.documents.view')) {
            $documentMetrics = app(DocumentDashboardMetricsService::class)->getMetrics();
        }
        if ($user && $user->hasHrPermission('hr.leaves.view')) {
            $leaveMetrics = app(LeaveDashboardMetricsService::class)->getMetrics();
        }
        if ($user) {
            $employee = HrEmployee::withoutGlobalScope('tenant')
                ->where('legal_entity_id', $tenant->id)
                ->where('user_id', $user->id)
                ->with('activeEmployment.position')
                ->first();

            if ($employee) {
                $employeeWorkspace = [
                    'employee' => $employee,
                    'balances' => HrLeaveBalance::withoutGlobalScope('tenant')->where('legal_entity_id', $tenant->id)->where('employee_id', $employee->id)->with('leaveType')->orderByDesc('remaining_amount')->limit(3)->get(),
                    'upcomingLeaves' => HrLeaveRequest::withoutGlobalScope('tenant')->where('legal_entity_id', $tenant->id)->where('employee_id', $employee->id)->where('status', LeaveRequestStatus::Approved->value)->whereDate('end_date', '>=', today())->with('leaveType')->orderBy('start_date')->limit(3)->get(),
                    'holidays' => HrHoliday::withoutGlobalScope('tenant')->where('legal_entity_id', $tenant->id)->whereDate('date', '>=', today())->orderBy('date')->limit(3)->get(),
                    'birthdays' => HrEmployee::withoutGlobalScope('tenant')->where('legal_entity_id', $tenant->id)->active()->whereNotNull('date_of_birth')->get()->sortBy(function (HrEmployee $person) {
                        $birthday = $person->date_of_birth->copy()->year(now()->year);
                        return $birthday->lt(today()) ? $birthday->addYear()->timestamp : $birthday->timestamp;
                    })->take(3)->values(),
                ];
            }
        }

        return view('livewire.hr.dashboard', [
            'tenant' => $tenant,
            'modules' => $activeModules,
            'documentMetrics' => $documentMetrics,
            'leaveMetrics' => $leaveMetrics,
            'employeeWorkspace' => $employeeWorkspace,
        ])->layout('layouts.app');
    }
}
