<?php

namespace App\Modules\Hr\Core\Livewire;

use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Document\Services\DocumentDashboardMetricsService;
use App\Modules\Hr\Leave\Services\LeaveDashboardMetricsService;
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
        $user = auth()->user();
        if ($user && $user->hasHrPermission('hr.documents.view')) {
            $documentMetrics = app(DocumentDashboardMetricsService::class)->getMetrics();
        }
        if ($user && $user->hasHrPermission('hr.leaves.view')) {
            $leaveMetrics = app(LeaveDashboardMetricsService::class)->getMetrics();
        }

        return view('livewire.hr.dashboard', [
            'tenant' => $tenant,
            'modules' => $activeModules,
            'documentMetrics' => $documentMetrics,
            'leaveMetrics' => $leaveMetrics,
        ])->layout('layouts.app');
    }
}
