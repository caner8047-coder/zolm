<?php

namespace App\Modules\Hr\Advance\Actions;

use App\Modules\Hr\Advance\Enums\AdvanceStatus;
use App\Modules\Hr\Advance\Enums\AdvanceTransactionType;
use App\Modules\Hr\Advance\Models\HrAdvance;
use App\Modules\Hr\Advance\Models\HrAdvanceTransaction;
use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\HrIntegrationOutboxService;
use App\Modules\Hr\Core\Services\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecordAdvanceRepaymentAction
{
    public function __construct(private HrAuditService $audit, private HrIntegrationOutboxService $outbox) {}

    public function execute(HrAdvance $advance, float $amount, string $reference, AdvanceTransactionType $type = AdvanceTransactionType::Repayment, ?string $sourceKey = null): HrAdvance
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.advances.approve'), 403);
        abort_unless($advance->legal_entity_id === app(TenantContext::class)->getId(), 404);
        abort_unless(in_array($type, [AdvanceTransactionType::Repayment, AdvanceTransactionType::PayrollDeduction], true), 422);
        $amount = round($amount, 2);
        abort_if($amount <= 0 || blank($reference), 422);
        $sourceKey ??= (string) Str::uuid();
        abort_unless(Str::isUuid($sourceKey), 422);

        return DB::transaction(function () use ($advance, $amount, $reference, $type, $sourceKey) {
            $row = HrAdvance::withoutGlobalScope('tenant')->whereKey($advance->id)->lockForUpdate()->firstOrFail();
            $payload = ['advance_id' => $row->id, 'type' => $type->value, 'amount' => number_format($amount, 2, '.', ''), 'reference' => trim($reference)];
            $hash = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE));
            $existing = HrAdvanceTransaction::withoutGlobalScope('tenant')->where('legal_entity_id', $row->legal_entity_id)->where('source_key', $sourceKey)->first();
            if ($existing) {
                abort_unless(hash_equals($existing->payload_hash, $hash), 409, 'Kaynak anahtarı farklı tahsilat içeriğiyle kullanılamaz.');
                return $row->fresh();
            }
            abort_unless($row->status === AdvanceStatus::Paid, 422, 'Yalnızca ödenmiş avansa tahsilat girilebilir.');
            $repaid = (float) HrAdvanceTransaction::withoutGlobalScope('tenant')->where('advance_id', $row->id)->whereIn('type', ['repayment', 'payroll_deduction'])->sum('amount');
            abort_if($repaid + $amount > (float) $row->requested_amount + 0.001, 422, 'Tahsilat kalan avans tutarını aşamaz.');
            $transaction = HrAdvanceTransaction::create(['legal_entity_id' => $row->legal_entity_id, 'advance_id' => $row->id, 'type' => $type, 'amount' => $amount, 'transaction_date' => today(), 'reference' => trim($reference), 'source_key' => $sourceKey, 'payload_hash' => $hash, 'created_by' => auth()->id(), 'created_at' => now()]);
            if ($type === AdvanceTransactionType::PayrollDeduction) {
                $this->outbox->enqueue('payroll', 'advance_payroll_deduction', $transaction, 'hr-advance-deduction-'.$transaction->id, ['advance_id' => $row->id, 'transaction_id' => $transaction->id, 'employee_id' => $row->employee_id, 'amount' => number_format($amount, 2, '.', ''), 'currency' => $row->currency, 'reference' => trim($reference)]);
            }
            if (abs(($repaid + $amount) - (float) $row->requested_amount) < 0.001) $row->update(['status' => AdvanceStatus::Settled]);
            $this->audit->log('advance_repayment_recorded', $row, null, ['amount' => $amount, 'type' => $type->value]);
            return $row->fresh();
        });
    }
}
