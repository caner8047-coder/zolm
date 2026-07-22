<?php

namespace App\Modules\Hr\Core\Livewire;

use App\Modules\Hr\Core\Services\HrStatutoryComplianceAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use Livewire\Component;

class HrComplianceWidget extends Component
{
    public array $auditResults = [];

    public function mount(HrStatutoryComplianceAuditService $auditService): void
    {
        $tenantId = app(TenantContext::class)->getId();
        $this->auditResults = $auditService->runFullComplianceAudit($tenantId);
    }

    public function render()
    {
        return view('livewire.hr.core.hr-compliance-widget');
    }
}
