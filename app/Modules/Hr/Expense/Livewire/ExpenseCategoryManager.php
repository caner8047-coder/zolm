<?php

namespace App\Modules\Hr\Expense\Livewire;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Expense\Models\HrExpenseCategory;
use Livewire\Component;

class ExpenseCategoryManager extends Component
{
    public ?int $editingId = null;
    public string $code = '';
    public string $name = '';
    public bool $requiresReceipt = true;
    public string $defaultVatRate = '20';
    public string $approvalLimit = '';

    public function save(HrAuditService $audit): void
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.expenses.approve'), 403);
        $this->validate(['code' => 'required|string|max:60', 'name' => 'required|string|max:160', 'defaultVatRate' => 'required|numeric|min:0|max:100', 'approvalLimit' => 'nullable|numeric|min:0']);
        $tenantId = app(TenantContext::class)->getId();
        $code = strtoupper(trim($this->code));
        abort_if(HrExpenseCategory::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('code', $code)->when($this->editingId, fn ($q) => $q->whereKeyNot($this->editingId))->exists(), 422, 'Bu kategori kodu zaten kullanılıyor.');
        $category = $this->editingId
            ? HrExpenseCategory::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->findOrFail($this->editingId)
            : new HrExpenseCategory(['legal_entity_id' => $tenantId, 'created_by' => auth()->id()]);
        $old = $category->exists ? $category->toArray() : null;
        $category->fill(['code' => $code, 'name' => trim($this->name), 'requires_receipt' => $this->requiresReceipt, 'default_vat_rate' => $this->defaultVatRate, 'approval_limit' => $this->approvalLimit !== '' ? $this->approvalLimit : null, 'updated_by' => auth()->id()])->save();
        $audit->log($old ? 'expense_category_updated' : 'expense_category_created', $category, $old, $category->toArray());
        $this->resetForm();
        session()->flash('success', 'Masraf kategorisi kaydedildi.');
    }

    public function edit(int $id): void
    {
        $category = $this->category($id);
        $this->editingId = $category->id; $this->code = $category->code; $this->name = $category->name; $this->requiresReceipt = $category->requires_receipt; $this->defaultVatRate = (string) $category->default_vat_rate; $this->approvalLimit = (string) ($category->approval_limit ?? '');
    }

    public function toggle(int $id, HrAuditService $audit): void
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.expenses.approve'), 403);
        $category = $this->category($id); $old = $category->is_active; $category->update(['is_active' => ! $old, 'updated_by' => auth()->id()]);
        $audit->log('expense_category_status_changed', $category, ['is_active' => $old], ['is_active' => ! $old]);
    }

    public function resetForm(): void { $this->reset(['editingId', 'code', 'name', 'approvalLimit']); $this->requiresReceipt = true; $this->defaultVatRate = '20'; }
    public function render() { return view('livewire.hr.expense.expense-category-manager', ['categories' => HrExpenseCategory::orderBy('name')->get()])->layout('layouts.app'); }
    private function category(int $id): HrExpenseCategory { return HrExpenseCategory::withoutGlobalScope('tenant')->where('legal_entity_id', app(TenantContext::class)->getId())->findOrFail($id); }
}
