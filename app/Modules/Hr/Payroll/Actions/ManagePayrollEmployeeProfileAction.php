<?php

namespace App\Modules\Hr\Payroll\Actions;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Payroll\Models\HrPayrollEmployeeProfile;
use App\Modules\Hr\Payroll\Services\PayrollSourceStalenessService;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManagePayrollEmployeeProfileAction
{
    public function __construct(private HrAuditService $audit, private PayrollSourceStalenessService $staleness) {}

    public function propose(HrEmployee $employee, array $data): HrPayrollEmployeeProfile
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.payroll.manage_profiles'), 403);
        $tenantId = app(TenantContext::class)->getId();
        abort_unless($employee->legal_entity_id === $tenantId, 404);

        $validated = validator($data, [
            'effective_from' => ['required', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'payroll_group_code' => ['nullable', 'string', 'max:60'],
            'payment_method' => ['required', 'in:bank,cash'],
            'iban' => ['nullable', 'string', 'max:40'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_account_holder' => ['nullable', 'string', 'max:160'],
            'social_security_status' => ['required', 'in:standard,retired,apprentice,intern,foreign'],
            'insurance_branch_code' => ['nullable', 'string', 'max:20'],
            'incentive_law_code' => ['nullable', 'string', 'max:30'],
            'missing_day_default_code' => ['nullable', 'string', 'max:20'],
            'disability_degree' => ['nullable', 'integer', 'between:1,3'],
            'is_retired' => ['sometimes', 'boolean'],
            'is_rd_employee' => ['sometimes', 'boolean'],
            'is_technopark_employee' => ['sometimes', 'boolean'],
            'change_reason' => ['required', 'string', 'max:1000'],
        ])->validate();

        $iban = $this->normalizeIban($validated['iban'] ?? null);
        if ($validated['payment_method'] === 'bank' && ! $iban) {
            throw ValidationException::withMessages(['iban' => 'Banka ödemesi için IBAN zorunludur.']);
        }
        if ($iban && ! $this->isValidTurkishIban($iban)) {
            throw ValidationException::withMessages(['iban' => 'Geçerli bir Türkiye IBAN numarası girin.']);
        }

        $ibanHash = $iban ? hash('sha256', $iban.config('app.key')) : null;
        if ($ibanHash && HrPayrollEmployeeProfile::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenantId)
            ->where('iban_hash', $ibanHash)
            ->where('employee_id', '!=', $employee->id)
            ->whereIn('status', ['pending_approval', 'approved'])
            ->exists()) {
            throw ValidationException::withMessages(['iban' => 'Bu IBAN başka bir çalışanın aktif bordro profilinde kullanılıyor.']);
        }

        $version = (int) HrPayrollEmployeeProfile::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenantId)
            ->where('employee_id', $employee->id)
            ->max('version') + 1;
        $profile = HrPayrollEmployeeProfile::create([
            'legal_entity_id' => $tenantId,
            'employee_id' => $employee->id,
            'version' => $version,
            'effective_from' => $validated['effective_from'],
            'effective_until' => $validated['effective_until'] ?? null,
            'payroll_group_code' => $validated['payroll_group_code'] ?? null,
            'payment_method' => $validated['payment_method'],
            'iban_encrypted' => $iban,
            'iban_hash' => $ibanHash,
            'iban_last_four' => $iban ? substr($iban, -4) : null,
            'bank_name' => $validated['bank_name'] ?? null,
            'bank_account_holder' => $validated['bank_account_holder'] ?? null,
            'social_security_status' => $validated['social_security_status'],
            'insurance_branch_code' => $validated['insurance_branch_code'] ?? null,
            'incentive_law_code' => $validated['incentive_law_code'] ?? null,
            'missing_day_default_code' => $validated['missing_day_default_code'] ?? null,
            'disability_degree' => $validated['disability_degree'] ?? null,
            'is_retired' => (bool) ($validated['is_retired'] ?? false),
            'is_rd_employee' => (bool) ($validated['is_rd_employee'] ?? false),
            'is_technopark_employee' => (bool) ($validated['is_technopark_employee'] ?? false),
            'status' => 'pending_approval',
            'change_reason' => trim($validated['change_reason']),
            'created_by' => auth()->id(),
        ]);

        $this->audit->log('payroll_employee_profile_proposed', $profile, null, [
            'employee_id' => $employee->id,
            'version' => $version,
            'iban' => '[MASKED]',
        ]);

        return $profile;
    }

    public function approve(HrPayrollEmployeeProfile $profile): HrPayrollEmployeeProfile
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.payroll.manage_profiles'), 403);
        abort_unless($profile->legal_entity_id === app(TenantContext::class)->getId(), 404);
        abort_unless($profile->status === 'pending_approval', 422, 'Yalnız onay bekleyen bordro profili onaylanabilir.');
        abort_if($profile->created_by === auth()->id(), 422, 'Bordro profilini hazırlayan kişi onaylayamaz.');

        return DB::transaction(function () use ($profile) {
            HrPayrollEmployeeProfile::withoutGlobalScope('tenant')
                ->where('legal_entity_id', $profile->legal_entity_id)
                ->where('employee_id', $profile->employee_id)
                ->where('status', 'approved')
                ->whereDate('effective_from', '<=', $profile->effective_from)
                ->lockForUpdate()
                ->update(['status' => 'superseded']);
            $profile->update([
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);
            $this->staleness->markForProfile($profile);
            $this->audit->log('payroll_employee_profile_approved', $profile, null, [
                'employee_id' => $profile->employee_id,
                'version' => $profile->version,
            ]);

            return $profile->fresh();
        });
    }

    private function normalizeIban(?string $iban): ?string
    {
        $normalized = strtoupper((string) preg_replace('/\s+/u', '', trim((string) $iban)));

        return $normalized !== '' ? $normalized : null;
    }

    private function isValidTurkishIban(string $iban): bool
    {
        if (! preg_match('/^TR\d{24}$/', $iban)) {
            return false;
        }

        $rearranged = substr($iban, 4).substr($iban, 0, 4);
        $numeric = '';
        foreach (str_split($rearranged) as $character) {
            $numeric .= ctype_alpha($character) ? (string) (ord($character) - 55) : $character;
        }
        $remainder = 0;
        foreach (str_split($numeric) as $digit) {
            $remainder = ($remainder * 10 + (int) $digit) % 97;
        }

        return $remainder === 1;
    }
}
