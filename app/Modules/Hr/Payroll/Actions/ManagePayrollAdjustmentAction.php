<?php

namespace App\Modules\Hr\Payroll\Actions;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Payroll\Models\HrPayrollAdjustment;
use App\Modules\Hr\Payroll\Models\HrPayrollPeriod;
use App\Modules\Hr\Personnel\Models\HrEmployee;

class ManagePayrollAdjustmentAction
{
    public function __construct(private HrAuditService $audit) {}

    public function propose(HrPayrollPeriod $period, HrEmployee $employee, array $data): HrPayrollAdjustment
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.payroll.calculate'), 403);
        $tenant = app(TenantContext::class)->getId();
        abort_unless($period->legal_entity_id === $tenant && $employee->legal_entity_id === $tenant, 404);
        abort_unless($period->status === 'prepared' && $period->records()->where('employee_id', $employee->id)->exists(), 422, 'Yalnız hazırlanmış dönemdeki çalışan için düzeltme eklenebilir.');
        $validated = validator($data, [
            'code' => ['required', 'string', 'max:60'], 'name' => ['required', 'string', 'max:160'],
            'type' => ['required', 'in:'.implode(',', HrPayrollAdjustment::TYPES)], 'amount_cents' => ['required', 'integer', 'min:1'],
            'social_security_exempt' => ['sometimes', 'boolean'], 'income_tax_exempt' => ['sometimes', 'boolean'],
            'pre_tax_deduction' => ['sometimes', 'boolean'], 'reason' => ['required', 'string', 'max:1000'],
        ])->validate();
        if ($validated['type'] !== 'earning' && (($validated['social_security_exempt'] ?? false) || ($validated['income_tax_exempt'] ?? false))) {
            abort(422, 'SGK ve gelir vergisi istisnası yalnız kazanç kalemlerinde kullanılabilir.');
        }
        if ($validated['type'] !== 'deduction' && ($validated['pre_tax_deduction'] ?? false)) {
            abort(422, 'Vergi öncesi seçimi yalnız kesinti kalemlerinde kullanılabilir.');
        }
        $adjustment = HrPayrollAdjustment::create([
            'legal_entity_id' => $tenant, 'payroll_period_id' => $period->id, 'employee_id' => $employee->id,
            'code' => strtoupper(trim($validated['code'])), 'name' => trim($validated['name']), 'type' => $validated['type'],
            'amount_encrypted' => (string) $validated['amount_cents'], 'social_security_exempt' => $validated['social_security_exempt'] ?? false,
            'income_tax_exempt' => $validated['income_tax_exempt'] ?? false, 'pre_tax_deduction' => $validated['pre_tax_deduction'] ?? false,
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
