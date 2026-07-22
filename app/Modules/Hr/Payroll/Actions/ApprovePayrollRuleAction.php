<?php

namespace App\Modules\Hr\Payroll\Actions;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Payroll\Models\HrPayrollRule;
use App\Modules\Hr\Payroll\Services\PayrollRuleConfiguration;
use App\Modules\Hr\Payroll\Services\PayrollSourceStalenessService;
use Illuminate\Support\Facades\DB;

class ApprovePayrollRuleAction
{
    public function __construct(private HrAuditService $audit, private PayrollRuleConfiguration $configuration, private PayrollSourceStalenessService $staleness) {}

    public function execute(HrPayrollRule $rule): HrPayrollRule
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.payroll.manage_rules'), 403);
        abort_unless($rule->legal_entity_id === app(TenantContext::class)->getId(), 404);
        abort_unless($rule->status === 'pending_approval', 422, 'Yalnız onay bekleyen kural sürümü onaylanabilir.');
        abort_if($rule->created_by === auth()->id(), 422, 'Kural sürümünü hazırlayan kişi onaylayamaz.');

        if ($rule->code === PayrollRuleConfiguration::CODE) {
            $this->configuration->validate($rule->configuration);
        }

        return DB::transaction(function () use ($rule) {
            HrPayrollRule::withoutGlobalScope('tenant')
                ->where('legal_entity_id', $rule->legal_entity_id)
                ->where('code', $rule->code)
                ->where('status', 'approved')
                ->lockForUpdate()
                ->update(['status' => 'superseded', 'is_active' => false]);

            $rule->update([
                'status' => 'approved',
                'is_active' => true,
                'configuration_hash' => $this->configuration->hash($rule->configuration),
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);
            $this->staleness->markForRule($rule);
            $this->audit->log('payroll_rule_version_approved', $rule, null, [
                'code' => $rule->code,
                'version' => $rule->version,
                'configuration_hash' => $rule->configuration_hash,
            ]);

            return $rule->fresh();
        });
    }
}
