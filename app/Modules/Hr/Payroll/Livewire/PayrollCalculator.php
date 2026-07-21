<?php

namespace App\Modules\Hr\Payroll\Livewire;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Payroll\Models\HrPayrollRule;
use App\Modules\Hr\Payroll\Services\NetToGrossPayrollSolver;
use App\Modules\Hr\Payroll\Services\PayrollCalculationEngine;
use App\Modules\Hr\Payroll\Services\PayrollRuleConfiguration;
use Livewire\Component;

class PayrollCalculator extends Component
{
    public string $mode = 'gross_to_net';
    public string $amount = '';
    public string $openingTaxBase = '0';
    public int $scheduledMinutes = 13500;
    public int $missingMinutes = 0;
    public int $approvedOvertimeMinutes = 0;
    public ?array $result = null;

    public function calculate(PayrollCalculationEngine $engine, NetToGrossPayrollSolver $solver, PayrollRuleConfiguration $configuration, HrAuditService $audit): void
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.payroll.calculate') && auth()->user()?->hasHrPermission('hr.salary.view'), 403);
        $this->validate(['mode' => 'required|in:gross_to_net,net_to_gross', 'amount' => 'required|numeric|min:0.01', 'openingTaxBase' => 'required|numeric|min:0', 'scheduledMinutes' => 'required|integer|min:1', 'missingMinutes' => 'required|integer|min:0', 'approvedOvertimeMinutes' => 'required|integer|min:0']);
        $tenant = app(TenantContext::class)->getId();
        $rule = HrPayrollRule::withoutGlobalScope('tenant')->where('legal_entity_id', $tenant)->where('code', PayrollRuleConfiguration::CODE)->whereIn('status', ['approved', 'superseded'])->whereDate('effective_from', '<=', today())->where(fn ($query) => $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', today()))->orderByDesc('effective_from')->orderByDesc('version')->first();
        abort_unless($rule, 422, 'Bugün için onaylı STATUTORY_PAYROLL kural paketi yok.');
        $rules = $configuration->validate($rule->configuration);
        $input = ['scheduled_minutes' => $this->scheduledMinutes, 'missing_minutes' => $this->missingMinutes, 'approved_overtime_minutes' => $this->approvedOvertimeMinutes];
        $amountCents = (int) round((float) $this->amount * 100);
        $openingCents = (int) round((float) $this->openingTaxBase * 100);
        if ($this->mode === 'net_to_gross') {
            $solution = $solver->solve($amountCents, $openingCents, $input, $rules);
            $this->result = $solution['calculation'] + ['monthly_gross_cents' => $solution['monthly_gross_cents'], 'difference_cents' => $solution['difference_cents']];
        } else {
            $this->result = $engine->calculate($amountCents, $openingCents, $input, $rules) + ['monthly_gross_cents' => $amountCents, 'difference_cents' => 0];
        }
        $audit->logEvent('payroll_calculation_simulated', 'Brüt-net simülasyonu çalıştırıldı', ['legal_entity_id' => $tenant, 'mode' => $this->mode, 'rule_id' => $rule->id, 'rule_version' => $rule->version]);
    }

    public function render()
    {
        return view('livewire.hr.payroll.payroll-calculator')->layout('layouts.app');
    }
}
