<?php

namespace App\Modules\Hr\Core\Livewire;

use App\Models\HrHoliday;
use App\Modules\Hr\Attendance\Models\HrAttendanceAnomaly;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Leave\Services\LeaveDashboardMetricsService;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use App\Modules\Hr\Recruitment\Models\HrJobPosting;
use Livewire\Component;

class HrDashboard extends Component
{
    public function render()
    {
        $tenant = app(TenantContext::class)->get();
        $modules = config('hr.modules', []);

        $activeModules = collect($modules)->filter(fn($m) => $m['enabled'] ?? true);

        $leaveMetrics = [];
        $recentEmployees = collect();
        $user = auth()->user();

        if ($user && $user->hasHrPermission('hr.leaves.view')) {
            $leaveMetrics = app(LeaveDashboardMetricsService::class)->getMetrics();
        }

        if ($user) {
            if ($user->hasHrPermission('hr.employees.view')) {
                $recentEmployees = HrEmployee::withoutGlobalScope('tenant')
                    ->where('legal_entity_id', $tenant->id)
                    ->with('activeEmployment.position')
                    ->latest('id')
                    ->limit(5)
                    ->get();
            }
        }

        $overviewMetrics = [
            'active_employees' => HrEmployee::withoutGlobalScope('tenant')
                ->where('legal_entity_id', $tenant->id)
                ->active()
                ->count(),
            'pending_leave' => (int) ($leaveMetrics['pending_approval'] ?? 0),
            'attendance_risks' => $user?->hasHrPermission('hr.attendance.view')
                ? HrAttendanceAnomaly::withoutGlobalScope('tenant')
                    ->where('legal_entity_id', $tenant->id)
                    ->where('status', 'open')
                    ->count()
                : null,
            'open_positions' => $user?->hasHrPermission('hr.recruitment.view')
                ? HrJobPosting::withoutGlobalScope('tenant')
                    ->where('legal_entity_id', $tenant->id)
                    ->where('status', 'published')
                    ->sum('headcount')
                : null,
        ];

        $upcomingHolidays = HrHoliday::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenant->id)
            ->whereDate('date', '>=', today())
            ->orderBy('date')
            ->limit(4)
            ->get();

        return view('livewire.hr.dashboard', [
            'tenant' => $tenant,
            'modules' => $activeModules,
            'overviewMetrics' => $overviewMetrics,
            'recentEmployees' => $recentEmployees,
            'upcomingHolidays' => $upcomingHolidays,
        ])->layout('layouts.app');
    }
}
