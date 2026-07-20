<?php

namespace App\Modules\Hr\Core\Livewire;

use App\Modules\Hr\Core\Services\TenantContext;
use Livewire\Component;

class HrDashboard extends Component
{
    public function render()
    {
        $tenant = app(TenantContext::class)->get();
        $modules = config('hr.modules', []);

        $activeModules = collect($modules)->filter(fn($m) => $m['enabled'] ?? true);

        return view('livewire.hr.dashboard', [
            'tenant' => $tenant,
            'modules' => $activeModules,
        ])->layout('layouts.app');
    }
}
