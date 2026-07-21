<?php

namespace App\Modules\Hr\Document\Livewire;

use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Document\Models\HrDocumentType;
use Livewire\Component;

class DocumentTypeForm extends Component
{
    public ?int $typeId = null;
    public string $name = '';
    public string $code = '';
    public string $category = 'other';
    public string $sensitivity = 'standard';
    public ?string $description = null;
    public bool $requires_expiry_date = false;
    public bool $requires_issue_date = false;
    public bool $requires_document_number = false;
    public bool $is_mandatory = false;
    public bool $employee_can_upload = true;
    public bool $is_active = true;
    public int $sort_order = 0;

    public function mount(?int $id = null): void
    {
        if ($id) {
            $this->typeId = $id;
            $type = HrDocumentType::withoutGlobalScope('tenant')->where('legal_entity_id', app(TenantContext::class)->getId())->findOrFail($id);
            foreach (['name', 'code', 'category', 'sensitivity', 'description', 'requires_expiry_date', 'requires_issue_date', 'requires_document_number', 'is_mandatory', 'employee_can_upload', 'is_active', 'sort_order'] as $field) {
                $this->{$field} = $type->{$field};
            }
        }
    }

    public function render()
    {
        return view('livewire.hr.document.document-type-form', ['isEdit' => $this->typeId !== null])->layout('layouts.app');
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:100',
            'category' => 'required|in:identity,contract,education,residence,criminal_record,health,certificate,kvkk,occupational_safety,payroll,termination,other',
            'sensitivity' => 'required|in:standard,confidential,highly_sensitive',
        ]);

        $tenantId = app(TenantContext::class)->getId();
        $query = HrDocumentType::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('code', $this->code);
        if ($this->typeId) { $query->where('id', '!=', $this->typeId); }
        if ($query->exists()) { session()->flash('error', 'Bu kod zaten kullanılıyor.'); return; }

        $data = collect($this->only(['name', 'code', 'category', 'sensitivity', 'description', 'requires_expiry_date', 'requires_issue_date', 'requires_document_number', 'is_mandatory', 'employee_can_upload', 'is_active', 'sort_order']))
            ->put('legal_entity_id', $tenantId)
            ->put('updated_by', auth()->id())
            ->toArray();

        if ($this->typeId) {
            HrDocumentType::withoutGlobalScope('tenant')->where('id', $this->typeId)->update($data);
            session()->flash('success', 'Belge türü güncellendi.');
        } else {
            $data['created_by'] = auth()->id();
            HrDocumentType::create($data);
            session()->flash('success', 'Belge türü oluşturuldu.');
        }

        $this->redirect(route('hr.settings.document-types'));
    }
}
