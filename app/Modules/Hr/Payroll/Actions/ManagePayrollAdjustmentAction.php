<?php

namespace App\Modules\Hr\Payroll\Actions;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Payroll\Models\HrPayrollAdjustment;
use App\Modules\Hr\Payroll\Models\HrPayrollPeriod;
use App\Modules\Hr\Payroll\Services\PayrollItemCatalog;
use App\Modules\Hr\Personnel\Models\HrEmployee;

class ManagePayrollAdjustmentAction
{
    public function __construct(private HrAuditService $audit, private PayrollItemCatalog $catalog) {}

    public function propose(HrPayrollPeriod $period, HrEmployee $employee, array $data): HrPayrollAdjustment
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.payroll.calculate'), 403);
        $tenant = app(TenantContext::class)->getId();
        abort_unless($period->legal_entity_id === $tenant && $employee->legal_entity_id === $tenant, 404);
        abort_unless($period->status === 'prepared' && $period->records()->where('employee_id', $employee->id)->exists(), 422, 'Yalnız hazırlanmış dönemdeki çalışan için düzeltme eklenebilir.');
        $validated = validator($data, [
            'code' => ['required', 'string', 'max:60'],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:1000'],
        ])->validate();
        $item = $this->catalog->get($validated['code']);
        $adjustment = HrPayrollAdjustment::create([
            'legal_entity_id' => $tenant, 'payroll_period_id' => $period->id, 'employee_id' => $employee->id,
            'code' => $item['code'], 'name' => $item['name'], 'type' => $item['type'],
            'amount_encrypted' => (string) $validated['amount_cents'], 'social_security_exempt' => $item['social_security_exempt'],
            'income_tax_exempt' => $item['income_tax_exempt'], 'pre_tax_deduction' => $item['pre_tax_deduction'],
            'status' => 'pending_approval', 'reason' => trim($validated['reason']), 'created_by' => auth()->id(),
        ]);
        $this->audit->log('payroll_adjustment_proposed', $adjustment, null, ['code' => $adjustment->code, 'type' => $adjustment->type, 'amount' => '[MASKED]']);
        return $adjustment;
    }

    public function approve(HrPayrollAdjustment $adjustment): HrPayrollAdjustment
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.payroll.approve'), 403);
        abort_unless($adjustment->legal_entity_id === app(TenantContext::class)->getId(), 404);
        abort_unless($adjustment->status === 'pending_approval' && $adjustment->period?->status === 'prepared', 422, 'Düzeltme onaylanabilir durumda değil.');
        abort_if($adjustment->created_by === auth()->id(), 422, 'Düzeltmeyi hazırlayan kişi onaylayamaz.');
        $adjustment->update(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()]);
        $this->audit->log('payroll_adjustment_approved', $adjustment, null, ['code' => $adjustment->code, 'type' => $adjustment->type, 'amount' => '[MASKED]']);
        return $adjustment->fresh();
    }
}
