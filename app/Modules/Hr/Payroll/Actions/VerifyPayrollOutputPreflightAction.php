<?php

namespace App\Modules\Hr\Payroll\Actions;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Payroll\Models\HrPayrollPeriod;
use App\Modules\Hr\Payroll\Models\HrPayrollRule;
use App\Modules\Hr\Payroll\Services\PayrollRuleConfiguration;

class VerifyPayrollOutputPreflightAction
{
    public function __construct(private HrAuditService $audit, private PayrollRuleConfiguration $configuration) {}

    public function execute(HrPayrollPeriod $period): HrPayrollPeriod
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.payroll.export'), 403);
        abort_unless(auth()->user()?->hasHrPermission('hr.salary.view'), 403);
        abort_unless($period->legal_entity_id === app(TenantContext::class)->getId(), 404);
        $period->load(['records.salaryRecord', 'records.employee', 'timesheetPeriod']);
        $findings = [];

        if ($period->status !== 'approved' || $period->preflight_status !== 'passed') {
            $findings[] = $this->finding('period_not_approved', 'Bordro dönemi hesaplanmış ve ikinci kullanıcı tarafından onaylanmış olmalı.');
        }
        if (! $period->approved_by || $period->approved_by === $period->calculated_by) {
            $findings[] = $this->finding('maker_checker_invalid', 'Hesaplayan ve onaylayan kullanıcı ayrımı doğrulanamadı.');
        }

        $sourceHashes = [];
        $calculationHashes = [];
        $currencies = [];
        foreach ($period->records->sortBy('id') as $record) {
            $sourceHash = $this->configuration->hash($record->source_snapshot);
            if (! hash_equals((string) $record->source_hash, $sourceHash)) {
                $findings[] = $this->finding('source_hash_mismatch', 'Puantaj kaynak izi değişmiş.', $record->employee_id);
            }
            $sourceHashes[] = $record->source_snapshot;

            $trace = $record->calculation_trace;
            $calculationHash = is_array($trace) ? $this->configuration->hash($trace) : '';
            if ($record->status !== 'approved' || ! $calculationHash || ! hash_equals((string) $record->calculation_hash, $calculationHash)) {
                $findings[] = $this->finding('calculation_hash_mismatch', 'Çalışan hesap izi doğrulanamadı.', $record->employee_id);
            }
            $calculationHashes[] = $record->calculation_hash;
            $currencies[] = $trace['currency'] ?? null;

            foreach ($trace['adjustments'] ?? [] as $adjustmentSnapshot) {
                $adjustment = ! empty($adjustmentSnapshot['id']) ? \App\Modules\Hr\Payroll\Models\HrPayrollAdjustment::withoutGlobalScope('tenant')->where('legal_entity_id', $period->legal_entity_id)->find($adjustmentSnapshot['id']) : null;
                $current = $adjustment ? ['id' => $adjustment->id, 'code' => $adjustment->code, 'type' => $adjustment->type, 'amount_cents' => $adjustment->amountCents(), 'social_security_exempt' => $adjustment->social_security_exempt, 'income_tax_exempt' => $adjustment->income_tax_exempt, 'pre_tax_deduction' => $adjustment->pre_tax_deduction] : null;
                if (! $adjustment || $adjustment->status !== 'approved' || $current !== $adjustmentSnapshot) {
                    $findings[] = $this->finding('adjustment_snapshot_invalid', 'Bordro ek kalemi onayı veya hesap anındaki değeri doğrulanamadı.', $record->employee_id);
                }
            }

            $salary = $record->salaryRecord;
            if (! $salary || ! in_array($salary->status, ['approved', 'superseded'], true) || $salary->effective_from->gt($period->timesheetPeriod->ends_on)) {
                $findings[] = $this->finding('salary_snapshot_invalid', 'Onaylı ücret sürümü dönemle eşleşmiyor.', $record->employee_id);
            }

            $ruleSnapshot = $record->rule_snapshot;
            $rule = ! empty($ruleSnapshot['id']) ? HrPayrollRule::withoutGlobalScope('tenant')->where('legal_entity_id', $period->legal_entity_id)->find($ruleSnapshot['id']) : null;
            if (! $rule || ! in_array($rule->status, ['approved', 'superseded'], true)
                || ! hash_equals((string) ($ruleSnapshot['configuration_hash'] ?? ''), $this->configuration->hash($rule->configuration))
                || $rule->effective_from->gt($period->timesheetPeriod->ends_on)
                || ($rule->effective_until && $rule->effective_until->lt($period->timesheetPeriod->starts_on))) {
                $findings[] = $this->finding('rule_snapshot_invalid', 'Onaylı mevzuat kural sürümü dönemle eşleşmiyor.', $record->employee_id);
            }

            $ledgerValid = \App\Modules\Hr\Payroll\Models\HrPayrollTaxLedger::withoutGlobalScope('tenant')
                ->where('legal_entity_id', $period->legal_entity_id)
                ->where('payroll_record_id', $record->id)->where('calculation_hash', $record->calculation_hash)->exists();
            if (! $ledgerValid) {
                $findings[] = $this->finding('tax_ledger_missing', 'Kümülatif vergi matrahı hareketi bulunamadı.', $record->employee_id);
            }
        }

        $periodSourceHash = $this->configuration->hash($sourceHashes);
        if (! hash_equals((string) $period->source_hash, $periodSourceHash)) {
            $findings[] = $this->finding('period_source_hash_mismatch', 'Dönem kaynak paketi bütünlüğü doğrulanamadı.');
        }
        if (! hash_equals((string) $period->calculation_hash, hash('sha256', implode('|', $calculationHashes)))) {
            $findings[] = $this->finding('period_calculation_hash_mismatch', 'Dönem hesap paketi bütünlüğü doğrulanamadı.');
        }
        if (count(array_unique(array_filter($currencies))) !== 1) {
            $findings[] = $this->finding('currency_mismatch', 'Kontrol çıktısı tek bir para birimi içermeli.');
        }

        if ($findings !== []) {
            $period->update(['output_preflight_status' => 'failed', 'output_preflight_findings' => $findings, 'output_preflight_hash' => null, 'output_preflight_at' => now(), 'output_preflight_by' => auth()->id()]);
            $this->audit->log('payroll_output_preflight_failed', $period, null, ['finding_codes' => array_column($findings, 'code')]);
            abort(422, 'Bordro çıktı ön kontrolleri başarısız.');
        }

        $preflightHash = hash('sha256', implode('|', [$period->id, $period->source_hash, $period->calculation_hash, $period->approved_by, $period->approved_at?->toIso8601String()]));
        $period->update(['output_preflight_status' => 'passed', 'output_preflight_findings' => [], 'output_preflight_hash' => $preflightHash, 'output_preflight_at' => now(), 'output_preflight_by' => auth()->id()]);
        $this->audit->log('payroll_output_preflight_passed', $period, null, ['preflight_hash' => $preflightHash]);
        return $period->fresh(['records.employee', 'records.salaryRecord', 'timesheetPeriod']);
    }

    private function finding(string $code, string $message, ?int $employeeId = null): array
    {
        return array_filter(['code' => $code, 'severity' => 'blocking', 'employee_id' => $employeeId, 'message' => $message], fn ($value) => $value !== null);
    }
}
