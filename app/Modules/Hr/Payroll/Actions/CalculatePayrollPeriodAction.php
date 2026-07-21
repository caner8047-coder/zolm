<?php

namespace App\Modules\Hr\Payroll\Actions;

use App\Modules\Hr\Compensation\Models\HrSalaryRecord;
use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Payroll\Models\HrPayrollPeriod;
use App\Modules\Hr\Payroll\Models\HrPayrollRule;
use App\Modules\Hr\Payroll\Models\HrPayrollTaxLedger;
use App\Modules\Hr\Payroll\Models\HrPayrollTaxOpening;
use App\Modules\Hr\Payroll\Services\PayrollCalculationEngine;
use App\Modules\Hr\Payroll\Services\PayrollRuleConfiguration;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CalculatePayrollPeriodAction
{
    public function __construct(
        private HrAuditService $audit,
        private PayrollRuleConfiguration $configuration,
        private PayrollCalculationEngine $engine,
    ) {}

    public function execute(HrPayrollPeriod $period): HrPayrollPeriod
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.payroll.calculate'), 403);
        $tenant = app(TenantContext::class)->getId();
        abort_unless($period->legal_entity_id === $tenant, 404);
        abort_unless($period->status === 'prepared' && $period->records()->exists(), 422, 'Yalnız hazırlanmış bordro paketi hesaplanabilir.');

        $period->loadMissing(['timesheetPeriod', 'records']);
        $rule = $this->resolveRule($period);
        $findings = [];
        if (! $rule) {
            $findings[] = ['code' => 'statutory_rule_missing', 'severity' => 'blocking', 'message' => 'Dönem için onaylı STATUTORY_PAYROLL kural paketi bulunamadı.'];
        }

        $rules = null;
        if ($rule) {
            try {
                $rules = $this->configuration->validate($rule->configuration);
            } catch (ValidationException $exception) {
                $findings[] = ['code' => 'statutory_rule_invalid', 'severity' => 'blocking', 'message' => $exception->getMessage()];
            }
        }

        $salaries = [];
        $openings = [];
        foreach ($period->records as $record) {
            $salary = HrSalaryRecord::withoutGlobalScope('tenant')
                ->where('legal_entity_id', $tenant)
                ->where('employee_id', $record->employee_id)
                ->whereIn('status', ['approved', 'superseded'])
                ->whereDate('effective_from', '<=', $period->timesheetPeriod->ends_on)
                ->orderByDesc('effective_from')->orderByDesc('version')->first();
            if (! $salary) {
                $findings[] = ['code' => 'approved_salary_missing', 'severity' => 'blocking', 'employee_id' => $record->employee_id, 'message' => 'Çalışanın dönem için onaylı ücret kaydı yok.'];
                continue;
            }
            $salaries[$record->employee_id] = $salary;

            $opening = $this->resolveOpeningTaxBase($period, $record->employee_id);
            if ($opening === null) {
                $findings[] = ['code' => 'opening_tax_base_missing', 'severity' => 'blocking', 'employee_id' => $record->employee_id, 'message' => 'Yıl ortası başlangıcı için devreden gelir vergisi matrahı yok.'];
                continue;
            }
            $openings[$record->employee_id] = $opening;
        }

        if ($findings !== []) {
            $period->update(['preflight_status' => 'failed', 'preflight_findings' => $findings]);
            $this->audit->log('payroll_calculation_preflight_failed', $period, null, ['finding_codes' => array_column($findings, 'code')]);
            abort(422, 'Bordro hesap ön kontrolleri başarısız. Bulguları giderip yeniden deneyin.');
        }

        $period = DB::transaction(function () use ($period, $rule, $rules, $salaries, $openings, $tenant) {
            $period = HrPayrollPeriod::withoutGlobalScope('tenant')->where('legal_entity_id', $tenant)->lockForUpdate()->findOrFail($period->id);
            abort_unless($period->status === 'prepared', 422, 'Bordro paketi başka bir işlem tarafından değiştirilmiş.');
            $period->load(['records', 'timesheetPeriod']);
            $recordHashes = [];
            foreach ($period->records as $record) {
                $salary = $salaries[$record->employee_id];
                $input = [
                    'scheduled_minutes' => $record->scheduled_minutes,
                    'missing_minutes' => $record->missing_minutes,
                    'approved_overtime_minutes' => $record->approved_overtime_minutes,
                ];
                $trace = $this->engine->calculate(
                    (int) round($salary->grossSalary() * 100),
                    $openings[$record->employee_id],
                    $input,
                    $rules
                );
                $trace['currency'] = $salary->currency;
                $trace['salary_version'] = $salary->version;
                $trace['rule_version'] = $rule->version;
                $trace['algorithm_version'] = 1;
                $hash = hash('sha256', json_encode($trace, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

                $record->update([
                    'salary_record_id' => $salary->id,
                    'rule_snapshot' => ['id' => $rule->id, 'code' => $rule->code, 'version' => $rule->version, 'configuration_hash' => $rule->configuration_hash],
                    'calculation_trace' => $trace,
                    'gross_pay_encrypted' => $this->decimal($trace['gross_pay_cents']),
                    'employee_deductions_encrypted' => $this->decimal($trace['employee_deductions_cents']),
                    'employer_contributions_encrypted' => $this->decimal($trace['employer_contributions_cents']),
                    'income_tax_encrypted' => $this->decimal($trace['income_tax_cents']),
                    'stamp_tax_encrypted' => $this->decimal($trace['stamp_tax_cents']),
                    'net_pay_encrypted' => $this->decimal($trace['net_pay_cents']),
                    'calculation_hash' => $hash,
                    'calculated_at' => now(),
                    'status' => 'calculated',
                ]);

                HrPayrollTaxLedger::updateOrCreate(
                    ['payroll_period_id' => $period->id, 'employee_id' => $record->employee_id],
                    [
                        'legal_entity_id' => $tenant,
                        'payroll_record_id' => $record->id,
                        'tax_year' => $period->timesheetPeriod->ends_on->year,
                        'opening_tax_base_encrypted' => (string) $trace['opening_tax_base_cents'],
                        'period_tax_base_encrypted' => (string) $trace['period_tax_base_cents'],
                        'closing_tax_base_encrypted' => (string) $trace['closing_tax_base_cents'],
                        'calculation_hash' => $hash,
                    ]
                );
                $recordHashes[] = $hash;
            }

            $calculationHash = hash('sha256', implode('|', $recordHashes));
            $period->update([
                'status' => 'calculated',
                'calculated_at' => now(),
                'calculated_by' => auth()->id(),
                'calculation_hash' => $calculationHash,
                'preflight_status' => 'passed',
                'preflight_findings' => [],
            ]);
            return $period->fresh(['records', 'timesheetPeriod']);
        });

        $this->audit->log('payroll_period_calculated', $period, null, ['calculation_hash' => $period->calculation_hash, 'record_count' => $period->records->count()]);
        return $period;
    }

    private function resolveRule(HrPayrollPeriod $period): ?HrPayrollRule
    {
        $snapshot = collect(data_get($period->records->first(), 'source_snapshot.rule_versions', []))->firstWhere('code', PayrollRuleConfiguration::CODE);
        if (! $snapshot || empty($snapshot['id'])) {
            return null;
        }
        $rule = HrPayrollRule::withoutGlobalScope('tenant')->where('legal_entity_id', $period->legal_entity_id)->whereKey($snapshot['id'])->whereIn('status', ['approved', 'superseded'])->first();
        if (! $rule || ! hash_equals((string) $snapshot['configuration_hash'], (string) $rule->configuration_hash)) {
            return null;
        }
        return $rule;
    }

    private function resolveOpeningTaxBase(HrPayrollPeriod $period, int $employeeId): ?int
    {
        $taxYear = $period->timesheetPeriod->ends_on->year;
        $previous = HrPayrollTaxLedger::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $period->legal_entity_id)
            ->where('employee_id', $employeeId)
            ->where('tax_year', $taxYear)
            ->whereHas('period.timesheetPeriod', fn ($query) => $query->whereDate('ends_on', '<', $period->timesheetPeriod->starts_on))
            ->latest('id')->first();
        if ($previous) {
            return $previous->closingTaxBaseCents();
        }
        $opening = HrPayrollTaxOpening::withoutGlobalScope('tenant')->where('legal_entity_id', $period->legal_entity_id)->where('employee_id', $employeeId)->where('tax_year', $taxYear)->first();
        if ($opening) {
            return $opening->openingTaxBaseCents();
        }
        return $period->timesheetPeriod->starts_on->month === 1 ? 0 : null;
    }

    private function decimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
