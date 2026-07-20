<?php

namespace App\Modules\Hr\Organization\Livewire;

use App\Modules\Hr\Core\Services\TenantContext;
use Livewire\Component;

class OrganizationSettings extends Component
{
    public string $activeTab = 'sgk-workplaces';

    public function render()
    {
        $tenant = app(TenantContext::class)->get();

        return view('livewire.hr.organization.settings', [
            'tenant' => $tenant,
        ])->layout('layouts.app');
    }
}
