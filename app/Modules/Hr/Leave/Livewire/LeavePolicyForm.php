<?php

namespace App\Modules\Hr\Leave\Livewire;

use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Leave\Models\HrLeavePolicy;
use App\Modules\Hr\Leave\Models\HrLeaveType;
use App\Modules\Hr\Organization\Models\HrBranch;
use App\Modules\Hr\Organization\Models\HrDepartment;
use App\Modules\Hr\Organization\Models\HrPosition;
use Livewire\Component;

class LeavePolicyForm extends Component
{
    public ?int $policyId = null;
    public ?int $leave_type_id = null;
    public string $scope = 'company';
    public ?int $branch_id = null;
    public ?int $department_id = null;
    public ?int $position_id = null;
    public ?string $employment_type = null;
    public string $annual_entitlement = '0';
    public string $max_carryover = '0';
    public bool $allows_negative_balance = false;
    public bool $requires_hr_approval = false;
    public string $effective_from = '';
    public ?string $effective_until = null;
    public bool $is_active = true;

    public function mount(?int $id = null): void
    {
        $this->effective_from = now()->toDateString();
        if (!$id) return;
        $policy = HrLeavePolicy::withoutGlobalScope('tenant')->where('legal_entity_id', app(TenantContext::class)->getId())->findOrFail($id);
        $this->policyId = $policy->id;
        foreach (['leave_type_id', 'scope', 'branch_id', 'department_id', 'position_id', 'employment_type', 'annual_entitlement', 'max_carryover', 'allows_negative_balance', 'requires_hr_approval', 'effective_from', 'effective_until', 'is_active'] as $field) {
            $value = $policy->{$field};
            $this->{$field} = $value instanceof \Carbon\CarbonInterface ? $value->toDateString() : $value;
        }
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.leaves.manage_policy'), 403);
        $this->validate([
            'leave_type_id' => 'required|integer', 'scope' => 'required|in:company,branch,department,position,employment_type',
            'annual_entitlement' => 'required|numeric|min:0', 'max_carryover' => 'required|numeric|min:0', 'effective_from' => 'required|date', 'effective_until' => 'nullable|date|after_or_equal:effective_from',
            'branch_id' => 'nullable|integer', 'department_id' => 'nullable|integer', 'position_id' => 'nullable|integer', 'employment_type' => 'nullable|in:full_time,part_time,contract,intern,temporary',
        ]);
        $tenantId = app(TenantContext::class)->getId();
        $this->assertScopeTarget($tenantId);
        $data = $this->only(['leave_type_id', 'scope', 'branch_id', 'department_id', 'position_id', 'employment_type', 'annual_entitlement', 'max_carryover', 'allows_negative_balance', 'requires_hr_approval', 'effective_from', 'effective_until', 'is_active']) + ['legal_entity_id' => $tenantId, 'updated_by' => auth()->id()];
        $this->assertNoOverlap($data);

        if ($this->policyId) {
            HrLeavePolicy::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('id', $this->policyId)->update($data);
            session()->flash('success', 'İzin politikası güncellendi.');
        } else {
            HrLeavePolicy::create($data + ['created_by' => auth()->id()]);
            session()->flash('success', 'İzin politikası oluşturuldu.');
        }
        $this->redirect(route('hr.settings.leave-policies'));
    }

    private function assertScopeTarget(int $tenantId): void
    {
        abort_unless(HrLeaveType::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('is_active', true)->whereKey($this->leave_type_id)->exists(), 422, 'İzin türü geçersiz.');
        $targets = ['branch' => [HrBranch::class, $this->branch_id], 'department' => [HrDepartment::class, $this->department_id], 'position' => [HrPosition::class, $this->position_id]];
        if (isset($targets[$this->scope])) {
            [$model, $id] = $targets[$this->scope];
            abort_unless($id && $model::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->whereKey($id)->exists(), 422, 'Politika kapsamı başka bir tüzel kişiliğe ait veya geçersiz.');
        }
        if ($this->scope === 'employment_type') abort_unless($this->employment_type, 422, 'İstihdam tipi seçilmelidir.');
    }

    private function assertNoOverlap(array $data): void
    {
        if (!$data['is_active']) return;
        $query = HrLeavePolicy::withoutGlobalScope('tenant')->where('legal_entity_id', $data['legal_entity_id'])->where('leave_type_id', $data['leave_type_id'])->where('scope', $data['scope'])->where('is_active', true)
            ->whereDate('effective_from', '<=', $data['effective_until'] ?? '9999-12-31')->where(fn ($q) => $q->whereNull('effective_until')->orWhereDate('effective_until', '>=', $data['effective_from']));
        foreach (['branch_id', 'department_id', 'position_id', 'employment_type'] as $field) $data[$field] === null ? $query->whereNull($field) : $query->where($field, $data[$field]);
        if ($this->policyId) $query->where('id', '!=', $this->policyId);
        abort_if($query->exists(), 422, 'Bu kapsam ve tarih aralığında çakışan aktif politika bulunuyor.');
    }

    public function render()
    {
        $tenantId = app(TenantContext::class)->getId();
        return view('livewire.hr.leave.leave-policy-form', ['isEdit' => $this->policyId !== null, 'leaveTypes' => HrLeaveType::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('is_active', true)->orderBy('name')->get(), 'branches' => HrBranch::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('is_active', true)->orderBy('name')->get(), 'departments' => HrDepartment::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('is_active', true)->orderBy('name')->get(), 'positions' => HrPosition::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('is_active', true)->orderBy('title')->get()])->layout('layouts.app');
    }
}
