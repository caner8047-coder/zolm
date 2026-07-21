<?php

namespace App\Modules\Hr\Payroll\Actions;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Payroll\Models\HrPayrollTaxLedger;
use App\Modules\Hr\Payroll\Models\HrPayrollTaxOpening;
use App\Modules\Hr\Personnel\Models\HrEmployee;

class SetPayrollTaxOpeningAction
{
    public function __construct(private HrAuditService $audit) {}

    public function execute(HrEmployee $employee, int $taxYear, int $openingTaxBaseCents, string $sourceReference): HrPayrollTaxOpening
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.payroll.manage_rules'), 403);
        $tenant = app(TenantContext::class)->getId();
        abort_unless($employee->legal_entity_id === $tenant, 404);
        abort_if($taxYear < 2000 || $taxYear > 2200 || $openingTaxBaseCents < 0 || blank($sourceReference), 422, 'Devreden matrah bilgisi geçersiz.');
        abort_if(HrPayrollTaxLedger::withoutGlobalScope('tenant')->where('legal_entity_id', $tenant)->where('employee_id', $employee->id)->where('tax_year', $taxYear)->exists(), 422, 'Hesap hareketi oluşmuş yılın devreden matrahı değiştirilemez.');

        $opening = HrPayrollTaxOpening::updateOrCreate(
            ['legal_entity_id' => $tenant, 'employee_id' => $employee->id, 'tax_year' => $taxYear],
            ['opening_tax_base_encrypted' => (string) $openingTaxBaseCents, 'source_reference' => trim($sourceReference), 'created_by' => auth()->id()]
        );
        $this->audit->log('payroll_tax_opening_recorded', $opening, null, ['tax_year' => $taxYear, 'opening_tax_base' => '[MASKED]', 'source_reference' => trim($sourceReference)]);
        return $opening;
    }
}
