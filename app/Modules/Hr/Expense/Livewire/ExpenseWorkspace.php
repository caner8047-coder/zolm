<?php

namespace App\Modules\Hr\Expense\Livewire;

use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Expense\Actions\CancelExpenseAction;
use App\Modules\Hr\Expense\Actions\CreateExpenseAction;
use App\Modules\Hr\Expense\Actions\DecideExpenseAction;
use App\Modules\Hr\Expense\Actions\MarkExpensePaidAction;
use App\Modules\Hr\Expense\Models\HrExpense;
use App\Modules\Hr\Expense\Models\HrExpenseCategory;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class ExpenseWorkspace extends Component
{
    use WithFileUploads, WithPagination;

    public bool $selfService = false;
    public ?int $employeeId = null;
    public ?int $categoryId = null;
    public string $expenseDate = '';
    public string $currency = 'TRY';
    public string $netAmount = '';
    public string $vatRate = '20';
    public string $merchantName = '';
    public string $documentNumber = '';
    public string $description = '';
    public string $projectReference = '';
    public string $orderReference = '';
    public string $customerReference = '';
    public string $sourceKey = '';
    public $receipt;
    public string $search = '';
    public string $statusFilter = '';
    public string $categoryFilter = '';
    public string $sortField = 'expense_date';
    public string $sortDirection = 'desc';
    public array $visibleColumns = ['employee', 'date', 'category', 'merchant', 'references', 'amount', 'status', 'actions'];
    public ?int $decidingId = null;
    public string $decisionNote = '';
    public ?int $payingId = null;
    public string $paymentReference = '';

    private const SORTABLE_COLUMNS = ['expense_date', 'gross_amount', 'status', 'created_at'];
    public const COLUMN_LABELS = ['employee' => 'Çalışan', 'date' => 'Tarih', 'category' => 'Kategori', 'merchant' => 'Satıcı / belge', 'references' => 'Referanslar', 'amount' => 'Tutar', 'status' => 'Durum', 'actions' => 'İşlem'];

    public function mount(bool $selfService = false): void
    {
        $this->selfService = $selfService; $this->expenseDate = now()->toDateString(); $this->sourceKey = (string) Str::uuid();
        if ($selfService) $this->employeeId = $this->ownEmployee()->id;
    }

    public function updatedCategoryId(): void
    {
        if ($this->categoryId) $this->vatRate = (string) $this->category((int) $this->categoryId)->default_vat_rate;
    }

    public function create(CreateExpenseAction $action): void
    {
        $this->validate(['employeeId' => 'required|integer', 'categoryId' => 'required|integer', 'expenseDate' => 'required|date|before_or_equal:today', 'currency' => 'required|in:TRY,EUR,USD,GBP', 'netAmount' => 'required|numeric|min:0.01', 'vatRate' => 'required|numeric|min:0|max:100', 'merchantName' => 'nullable|string|max:160', 'documentNumber' => 'nullable|string|max:120', 'description' => 'required|string|max:2000', 'projectReference' => 'nullable|string|max:120', 'orderReference' => 'nullable|string|max:120', 'customerReference' => 'nullable|string|max:120', 'receipt' => 'nullable|file|max:20480|mimes:pdf,jpg,jpeg,png,webp']);
        $employee = $this->employee((int) $this->employeeId); $category = $this->category((int) $this->categoryId);
        $action->execute($employee, $category, ['expense_date' => $this->expenseDate, 'currency' => $this->currency, 'net_amount' => $this->netAmount, 'vat_rate' => $this->vatRate, 'merchant_name' => $this->merchantName, 'document_number' => $this->documentNumber, 'description' => $this->description, 'project_reference' => $this->projectReference, 'order_reference' => $this->orderReference, 'customer_reference' => $this->customerReference], $this->receipt, $this->sourceKey);
        $this->reset(['categoryId', 'netAmount', 'merchantName', 'documentNumber', 'description', 'projectReference', 'orderReference', 'customerReference', 'receipt']);
        $this->vatRate = '20'; $this->sourceKey = (string) Str::uuid(); if ($this->selfService) $this->employeeId = $this->ownEmployee()->id;
        session()->flash('success', 'Masraf talebi oluşturuldu.');
    }

    public function startDecision(int $id): void { $this->expense($id); $this->decidingId = $id; $this->decisionNote = ''; }
    public function approve(DecideExpenseAction $action): void { $action->approve($this->expense($this->decidingId), $this->decisionNote ?: null); $this->reset(['decidingId', 'decisionNote']); session()->flash('success', 'Onay adımı tamamlandı.'); }
    public function reject(DecideExpenseAction $action): void { $this->validate(['decisionNote' => 'required|string|max:1000']); $action->reject($this->expense($this->decidingId), $this->decisionNote); $this->reset(['decidingId', 'decisionNote']); session()->flash('success', 'Masraf reddedildi.'); }
    public function startPayment(int $id): void { $this->expense($id); $this->payingId = $id; $this->paymentReference = ''; }
    public function markPaid(MarkExpensePaidAction $action): void { $this->validate(['paymentReference' => 'required|string|max:160']); $action->execute($this->expense($this->payingId), $this->paymentReference); $this->reset(['payingId', 'paymentReference']); session()->flash('success', 'Masraf ödendi olarak işaretlendi.'); }
    public function cancel(int $id, CancelExpenseAction $action): void { $action->execute($this->expense($id)); session()->flash('success', 'Masraf iptal edildi.'); }
    public function sortTable(string $column): void { abort_unless(in_array($column, self::SORTABLE_COLUMNS, true), 422); if ($this->sortField === $column) $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc'; else { $this->sortField = $column; $this->sortDirection = 'asc'; } }
    public function toggleColumn(string $column): void { abort_unless(array_key_exists($column, self::COLUMN_LABELS), 422); if (in_array($column, ['employee', 'actions'], true)) return; $this->visibleColumns = in_array($column, $this->visibleColumns, true) ? array_values(array_diff($this->visibleColumns, [$column])) : array_values(array_intersect(array_keys(self::COLUMN_LABELS), [...$this->visibleColumns, $column])); }
    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedStatusFilter(): void { $this->resetPage(); }
    public function updatedCategoryFilter(): void { $this->resetPage(); }

    public function render()
    {
        $tenantId = app(TenantContext::class)->getId();
        $query = HrExpense::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->with(['employee', 'category', 'receipt']);
        if ($this->selfService) $query->where('employee_id', $this->ownEmployee()->id);
        elseif ($this->search !== '') $query->where(fn ($q) => $q->whereHas('employee', fn ($employee) => $employee->search($this->search))->orWhere('merchant_name', 'like', "%{$this->search}%")->orWhere('document_number', 'like', "%{$this->search}%"));
        if ($this->statusFilter !== '') $query->where('status', $this->statusFilter);
        if ($this->categoryFilter !== '') $query->where('expense_category_id', $this->categoryFilter);
        $summary = (clone $query)->selectRaw('COUNT(*) count, COALESCE(SUM(gross_amount),0) total, SUM(status IN (\'pending_manager\',\'pending_hr\')) pending_count, SUM(status = \'approved\') approved_count')->first();
        return view('livewire.hr.expense.expense-workspace', ['expenses' => $query->orderBy($this->sortField, $this->sortDirection)->paginate(20), 'summary' => $summary, 'employees' => HrEmployee::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->active()->orderBy('first_name')->get(), 'categories' => HrExpenseCategory::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('is_active', true)->orderBy('name')->get(), 'columnLabels' => self::COLUMN_LABELS])->layout('layouts.app');
    }

    private function ownEmployee(): HrEmployee { return HrEmployee::withoutGlobalScope('tenant')->where('legal_entity_id', app(TenantContext::class)->getId())->where('user_id', auth()->id())->firstOrFail(); }
    private function employee(int $id): HrEmployee { return HrEmployee::withoutGlobalScope('tenant')->where('legal_entity_id', app(TenantContext::class)->getId())->findOrFail($id); }
    private function category(int $id): HrExpenseCategory { return HrExpenseCategory::withoutGlobalScope('tenant')->where('legal_entity_id', app(TenantContext::class)->getId())->findOrFail($id); }
    private function expense(?int $id): HrExpense { abort_unless($id, 404); return HrExpense::withoutGlobalScope('tenant')->where('legal_entity_id', app(TenantContext::class)->getId())->findOrFail($id); }
}
