<?php

namespace App\Modules\Hr\Document\Livewire;

use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Document\Models\HrDocumentType;
use Livewire\Component;
use Livewire\WithPagination;

class DocumentTypeList extends Component
{
    use WithPagination;

    public string $search = '';
    public ?string $categoryFilter = null;
    public ?string $statusFilter = null;

    public function render()
    {
        $tenantId = app(TenantContext::class)->getId();
        $query = HrDocumentType::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId);

        if ($this->search) {
            $query->where(fn($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('code', 'like', "%{$this->search}%"));
        }
        if ($this->categoryFilter) {
            $query->where('category', $this->categoryFilter);
        }
        if ($this->statusFilter !== null) {
            $query->where('is_active', $this->statusFilter === 'active');
        }

        return view('livewire.hr.document.document-type-list', [
            'types' => $query->ordered()->paginate(15),
        ])->layout('layouts.app');
    }

    public function toggleActive(int $id): void
    {
        $type = HrDocumentType::withoutGlobalScope('tenant')->where('legal_entity_id', app(TenantContext::class)->getId())->findOrFail($id);
        $type->update(['is_active' => !$type->is_active, 'updated_by' => auth()->id()]);
        session()->flash('success', $type->is_active ? 'Belge türü aktifleştirildi.' : 'Belge türü pasifleştirildi.');
    }
}
