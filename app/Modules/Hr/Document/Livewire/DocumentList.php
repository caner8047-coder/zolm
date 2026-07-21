<?php

namespace App\Modules\Hr\Document\Livewire;

use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Document\Models\HrEmployeeDocument;
use Livewire\Component;
use Livewire\WithPagination;

class DocumentList extends Component
{
    use WithPagination;

    public string $search = '';
    public ?string $statusFilter = null;
    public ?string $categoryFilter = null;

    public function mount(): void
    {
        // Dashboard kartlarından filtreli linklerle (?status=...&category=...) gelindiğinde uygulanır.
        $this->statusFilter = request()->query('status');
        $this->categoryFilter = request()->query('category');
    }

    public function render()
    {
        $tenantId = app(TenantContext::class)->getId();
        $query = HrEmployeeDocument::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenantId)
            ->with('employee', 'documentType');

        if ($this->search) {
            $query->whereHas('employee', fn($q) => $q->where('first_name', 'like', "%{$this->search}%")->orWhere('last_name', 'like', "%{$this->search}%")->orWhere('employee_number', 'like', "%{$this->search}%"));
        }
        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }
        if ($this->categoryFilter) {
            $query->whereHas('documentType', fn($q) => $q->where('category', $this->categoryFilter));
        }

        return view('livewire.hr.document.document-list', [
            'documents' => $query->latest()->paginate(15),
        ])->layout('layouts.app');
    }
}
