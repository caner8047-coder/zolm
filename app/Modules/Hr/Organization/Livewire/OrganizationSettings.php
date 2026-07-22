<?php

namespace App\Modules\Hr\Organization\Livewire;

use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Organization\Models\HrBranch;
use App\Modules\Hr\Organization\Models\HrDepartment;
use App\Modules\Hr\Organization\Models\HrPosition;
use App\Modules\Hr\Organization\Models\HrTeam;
use App\Modules\Hr\Organization\Models\HrUnit;
use Livewire\Component;

class OrganizationSettings extends Component
{
    public string $activeTab = 'sgk-workplaces';

    public function render()
    {
        $tenant = app(TenantContext::class)->get();

        return view('livewire.hr.organization.settings', [
            'tenant' => $tenant,
            'metrics' => [
                'branches' => HrBranch::withoutGlobalScope('tenant')->where('legal_entity_id', $tenant->id)->where('is_active', true)->count(),
                'departments' => HrDepartment::withoutGlobalScope('tenant')->where('legal_entity_id', $tenant->id)->where('is_active', true)->count(),
                'units' => HrUnit::withoutGlobalScope('tenant')
                    ->whereHas('department', fn ($query) => $query->where('legal_entity_id', $tenant->id))
                    ->where('is_active', true)
                    ->count(),
                'teams' => HrTeam::withoutGlobalScope('tenant')
                    ->whereHas('unit.department', fn ($query) => $query->where('legal_entity_id', $tenant->id))
                    ->where('is_active', true)
                    ->count(),
                'positions' => HrPosition::withoutGlobalScope('tenant')->where('legal_entity_id', $tenant->id)->where('is_active', true)->count(),
            ],
        ])->layout('layouts.app');
    }
}
