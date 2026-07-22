<?php

namespace App\Modules\Hr\Payroll\Actions;

use App\Modules\Hr\Compensation\Models\HrSalaryRecord;
use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Payroll\Models\HrPayrollPeriod;
use App\Modules\Hr\Payroll\Models\HrPayrollAdjustment;
use App\Modules\Hr\Payroll\Models\HrPayrollEmployeeProfile;
use App\Modules\Hr\Payroll\Models\HrPayrollRule;
use App\Modules\Hr\Payroll\Models\HrPayrollTaxLedger;
use App\Modules\Hr\Payroll\Models\HrPayrollTaxOpening;
use App\Modules\Hr\Payroll\Services\PayrollCalculationEngine;
use App\Modules\Hr\Payroll\Services\PayrollRuleConfiguration;
use App\Modules\Hr\Payroll\Services\PayrollVarianceAnalysisService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CalculatePayrollPeriodAction
{
    public function __construct(
        private HrAuditService $audit,
        private PayrollRuleConfiguration $configuration,
        private PayrollCalculationEngine $engine,
        private PayrollVarianceAnalysisService $varianceAnalysis,
    ) {}

    public function execute(HrPayrollPeriod $period): HrPayrollPeriod
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.payroll.calculate'), 403);
        $tenant = app(TenantContext::class)->getId();
        abort_unless($period->legal_entity_id === $tenant, 404);
        abort_unless($period->status === 'prepared' && $period->records()->exists(), 422, 'Yalnız hazırlanmış bordro paketi hesaplanabilir.');
        abort_if($period->source_status === 'stale', 422, 'Bordro kaynağı değişti. Hesaplamadan önce hazırlık paketini yenileyin.');

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
        $profiles = [];
        $adjustments = HrPayrollAdjustment::withoutGlobalScope('tenant')->where('legal_entity_id', $tenant)->where('payroll_period_id', $period->id)->get()->groupBy('employee_id');
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

            $profile = $this->resolveEmployeeProfile($period, $record->employee_id);
            if (! $profile && ($rules['require_employee_payroll_profile'] ?? false)) {
                $findings[] = ['code' => 'payroll_profile_missing', 'severity' => 'blocking', 'employee_id' => $record->employee_id, 'message' => 'Çalışanın dönem için onaylı bordro profili yok.'];
                continue;
            }
            $profiles[$record->employee_id] = $profile;

            $opening = $this->resolveOpeningTaxBase($period, $record->employee_id);
            if ($opening === null) {
                $findings[] = ['code' => 'opening_tax_base_missing', 'severity' => 'blocking', 'employee_id' => $record->employee_id, 'message' => 'Yıl ortası başlangıcı için devreden gelir vergisi matrahı yok.'];
                continue;
            }
            $openings[$record->employee_id] = $opening;
            if (($adjustments[$record->employee_id] ?? collect())->contains(fn ($adjustment) => $adjustment->status !== 'approved')) {
                $findings[] = ['code' => 'adjustment_pending_approval', 'severity' => 'blocking', 'employee_id' => $record->employee_id, 'message' => 'Çalışanın onay bekleyen bordro düzeltmesi var.'];
            }
        }

        if ($findings !== []) {
            $period->update(['preflight_status' => 'failed', 'preflight_findings' => $findings]);
            $this->audit->log('payroll_calculation_preflight_failed', $period, null, ['finding_codes' => array_column($findings, 'code')]);
            abort(422, 'Bordro hesap ön kontrolleri başarısız. Bulguları giderip yeniden deneyin.');
        }

        $period = DB::transaction(function () use ($period, $rule, $rules, $salaries, $openings, $profiles, $adjustments, $tenant) {
            $period = HrPayrollPeriod::withoutGlobalScope('tenant')->where('legal_entity_id', $tenant)->lockForUpdate()->findOrFail($period->id);
            abort_unless($period->status === 'prepared', 422, 'Bordro paketi başka bir işlem tarafından değiştirilmiş.');
            $period->load(['records', 'timesheetPeriod']);
            $recordHashes = [];
            foreach ($period->records as $record) {
                $salary = $salaries[$record->employee_id];
                $usesClassifiedOvertime = (int) data_get($record->source_snapshot, 'classification_version', 1) >= 2;
                $input = [
                    'scheduled_minutes' => $record->scheduled_minutes,
                    'missing_minutes' => $record->missing_minutes,
                    'approved_overtime_minutes' => $record->approved_overtime_minutes,
                    'approved_regular_overtime_minutes' => $usesClassifiedOvertime
                        ? $record->approved_regular_overtime_minutes
                        : $record->approved_overtime_minutes,
                    'approved_holiday_work_minutes' => $usesClassifiedOvertime ? $record->approved_holiday_work_minutes : 0,
                    'approved_weekly_rest_work_minutes' => $usesClassifiedOvertime ? $record->approved_weekly_rest_work_minutes : 0,
                ];
                $recordAdjustments = ($adjustments[$record->employee_id] ?? collect())->map(fn ($adjustment) => [
                    'id' => $adjustment->id, 'code' => $adjustment->code, 'type' => $adjustment->type,
                    'amount_cents' => $adjustment->amountCents(), 'social_security_exempt' => $adjustment->social_security_exempt,
                    'income_tax_exempt' => $adjustment->income_tax_exempt, 'pre_tax_deduction' => $adjustment->pre_tax_deduction,
                ])->values()->all();
                $trace = $this->engine->calculate(
                    (int) round($salary->grossSalary() * 100),
                    $openings[$record->employee_id],
                    $input,
                    $rules,
                    $recordAdjustments
                );
                $trace['adjustments'] = $recordAdjustments;
                $trace['currency'] = $salary->currency;
                $trace['salary_version'] = $salary->version;
                $trace['payroll_profile_version'] = $profiles[$record->employee_id]?->version;
                $trace['rule_version'] = $rule->version;
                $trace['algorithm_version'] = 2;
                $hash = $this->configuration->hash($trace);

                $record->update([
                    'salary_record_id' => $salary->id,
                    'payroll_profile_id' => $profiles[$record->employee_id]?->id,
                    'rule_snapshot' => ['id' => $rule->id, 'code' => $rule->code, 'version' => $rule->version, 'configuration_hash' => $rule->configuration_hash],
                    'payroll_profile_snapshot' => $this->profileSnapshot($profiles[$record->employee_id]),
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
                'output_preflight_status' => 'pending',
                'output_preflight_findings' => null,
                'output_preflight_hash' => null,
                'output_preflight_at' => null,
                'output_preflight_by' => null,
            ]);
            return $period->fresh(['records', 'timesheetPeriod']);
        });

        $period = $this->varianceAnalysis->analyze($period);
        $this->audit->log('payroll_period_calculated', $period, null, [
            'calculation_hash' => $period->calculation_hash,
            'record_count' => $period->records->count(),
            'variance_status' => $period->variance_status,
        ]);

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

    private function resolveEmployeeProfile(HrPayrollPeriod $period, int $employeeId): ?HrPayrollEmployeeProfile
    {
        return HrPayrollEmployeeProfile::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $period->legal_entity_id)
            ->where('employee_id', $employeeId)
            ->whereIn('status', ['approved', 'superseded'])
            ->whereDate('effective_from', '<=', $period->timesheetPeriod->ends_on)
            ->where(fn ($query) => $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', $period->timesheetPeriod->starts_on))
            ->orderByDesc('effective_from')
            ->orderByDesc('version')
            ->first();
    }

    private function profileSnapshot(?HrPayrollEmployeeProfile $profile): ?array
    {
        if (! $profile) {
            return null;
        }

        return [
            'id' => $profile->id,
            'version' => $profile->version,
            'payment_method' => $profile->payment_method,
            'iban_hash' => $profile->iban_hash,
            'iban_last_four' => $profile->iban_last_four,
            'social_security_status' => $profile->social_security_status,
            'insurance_branch_code' => $profile->insurance_branch_code,
            'incentive_law_code' => $profile->incentive_law_code,
            'missing_day_default_code' => $profile->missing_day_default_code,
            'disability_degree' => $profile->disability_degree,
            'is_retired' => $profile->is_retired,
            'is_rd_employee' => $profile->is_rd_employee,
            'is_technopark_employee' => $profile->is_technopark_employee,
        ];
    }

    private function decimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
