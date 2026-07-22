<?php

namespace App\Modules\Hr\Personnel\Livewire;

use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Document\Actions\UploadNewVersionAction;
use App\Modules\Hr\Document\Actions\VerifyDocumentAction;
use App\Modules\Hr\Document\Enums\DocumentCategory;
use App\Modules\Hr\Document\Enums\DocumentSensitivity;
use App\Modules\Hr\Document\Enums\DocumentStatus;
use App\Modules\Hr\Document\Enums\VerificationStatus;
use App\Modules\Hr\Document\Models\HrDocumentRequest;
use App\Modules\Hr\Document\Models\HrDocumentType;
use App\Modules\Hr\Document\Models\HrEmployeeDocument;
use App\Modules\Hr\Leave\Models\HrLeaveBalance;
use App\Modules\Hr\Leave\Models\HrLeaveRequest;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithFileUploads;

class EmployeeDetail extends Component
{
    use WithFileUploads;

    public HrEmployee $employee;
    public string $activeTab = 'overview';

    // Yeni sürüm yükleme
    public ?int $newVersionDocId = null;
    public $newVersionFile = null;

    // Ret gerekçesi
    public ?int $rejectDocId = null;
    public string $rejectReason = '';

    public function mount(HrEmployee|int|null $employee = null, ?int $id = null): void
    {
        $employeeId = $employee instanceof HrEmployee ? $employee->id : ($employee ?? $id);
        abort_unless($employeeId !== null, 404);

        $this->employee = HrEmployee::withoutGlobalScope('tenant')
            ->where('legal_entity_id', app(TenantContext::class)->getId())
            ->with([
                'activeEmployment.position',
                'activeEmployment.department',
                'activeEmployment.branch',
                'activeEmployment.manager',
                'employmentRecords.position',
                'employmentRecords.department',
            ])
            ->findOrFail($employeeId);
    }

    public function render()
    {
        $tenantId = app(TenantContext::class)->getId();
        $user = auth()->user();

        $canViewSensitive = $user && $user->hasHrPermission('hr.documents.view_sensitive');
        $canViewHealth = $user && $user->hasHrPermission('hr.documents.view_health');
        $canViewDocumentType = static function (?HrDocumentType $type) use ($canViewSensitive, $canViewHealth): bool {
            if ($type && $type->sensitivity === DocumentSensitivity::HighlySensitive && ! $canViewSensitive) {
                return false;
            }

            return ! ($type && $type->category === DocumentCategory::Health && ! $canViewHealth);
        };

        // Hassas/sağlık erişim filtresi: yetkisiz kullanıcı bu belgeleri göremez.
        $documents = HrEmployeeDocument::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenantId)
            ->where('employee_id', $this->employee->id)
            ->with('documentType')
            ->latest()
            ->get()
            ->filter(fn (HrEmployeeDocument $document): bool => $canViewDocumentType($document->documentType))
            ->values();

        $mandatoryCount = $documents->where('status', DocumentStatus::Requested)->count();
        $activeCount = $documents->where('status', DocumentStatus::Active)->count();
        $pendingVerification = $documents->where('verification_status', VerificationStatus::Pending)->count();
        $expiringSoon = $documents->filter(fn($d) => $d->days_until_expiry !== null && $d->days_until_expiry <= 30 && $d->days_until_expiry > 0)->count();
        $expired = $documents->where('status', DocumentStatus::Expired)->count();

        $pendingRequests = HrDocumentRequest::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenantId)
            ->where('employee_id', $this->employee->id)
            ->whereIn('status', ['pending', 'overdue'])
            ->with('documentType')
            ->latest()
            ->get()
            ->filter(fn (HrDocumentRequest $request): bool => $canViewDocumentType($request->documentType))
            ->values();

        $missingMandatoryTypes = $this->missingMandatoryTypes($tenantId, $documents)
            ->filter($canViewDocumentType)
            ->values();
        $leaveRequests = HrLeaveRequest::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('employee_id', $this->employee->id)->with('leaveType')->latest()->get();
        $leaveBalances = HrLeaveBalance::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('employee_id', $this->employee->id)->where('period_year', now()->year)->with('leaveType')->get();

        $fileChecklist = app(\App\Modules\Hr\Document\Services\HrPersonnelFileChecklistService::class)->analyzeEmployeeFile($tenantId, $this->employee->id);
        if (! $canViewHealth) {
            $visibleChecklist = collect($fileChecklist['checklist'])
                ->reject(fn (array $item): bool => ($item['type_key'] ?? null) === 'health_report')
                ->values();
            $presentCount = $visibleChecklist->where('is_present', true)->count();
            $totalRequired = $visibleChecklist->count();
            $completionRate = $totalRequired > 0 ? (int) round(($presentCount / $totalRequired) * 100) : 0;

            $fileChecklist = array_merge($fileChecklist, [
                'total_required' => $totalRequired,
                'present_count' => $presentCount,
                'missing_count' => $totalRequired - $presentCount,
                'completion_rate' => $completionRate,
                'is_complete' => $completionRate === 100,
                'checklist' => $visibleChecklist->all(),
            ]);
        }

        return view('livewire.hr.personnel.employee-detail', [
            'employee' => $this->employee,
            'documents' => $documents,
            'mandatoryCount' => $mandatoryCount,
            'activeCount' => $activeCount,
            'pendingVerification' => $pendingVerification,
            'expiringSoon' => $expiringSoon,
            'expiredCount' => $expired,
            'pendingRequests' => $pendingRequests,
            'missingMandatoryTypes' => $missingMandatoryTypes,
            'canViewSensitive' => $canViewSensitive,
            'canViewHealth' => $canViewHealth,
            'leaveRequests' => $leaveRequests,
            'leaveBalances' => $leaveBalances,
            'fileChecklist' => $fileChecklist,
        ])->layout('layouts.app');
    }

    public function startNewVersion(int $documentId): void
    {
        abort_unless(auth()->user() && auth()->user()->hasHrPermission('hr.documents.create'), 403);
        $this->newVersionDocId = $documentId;
        $this->newVersionFile = null;
    }

    public function uploadNewVersion(): void
    {
        abort_unless(auth()->user() && auth()->user()->hasHrPermission('hr.documents.create'), 403);
        $this->validate(['newVersionFile' => 'required|file|max:20480']);

        $doc = $this->resolveDocument($this->newVersionDocId);
        app(UploadNewVersionAction::class)->execute($doc, $this->newVersionFile, 'Profil sekmesinden yeni sürüm');

        $this->reset(['newVersionDocId', 'newVersionFile']);
        session()->flash('document_success', 'Yeni belge sürümü yüklendi.');
    }

    public function verifyDocument(int $documentId): void
    {
        $doc = $this->resolveDocument($documentId);
        Gate::authorize('verify', $doc);
        app(VerifyDocumentAction::class)->verify($doc);
        session()->flash('document_success', 'Belge doğrulandı.');
    }

    public function startReject(int $documentId): void
    {
        $doc = $this->resolveDocument($documentId);
        Gate::authorize('verify', $doc);
        $this->rejectDocId = $documentId;
        $this->rejectReason = '';
    }

    public function rejectDocument(): void
    {
        $this->validate(['rejectReason' => 'required|string|max:1000']);
        $doc = $this->resolveDocument($this->rejectDocId);
        Gate::authorize('verify', $doc);
        app(VerifyDocumentAction::class)->reject($doc, $this->rejectReason);
        $this->reset(['rejectDocId', 'rejectReason']);
        session()->flash('document_success', 'Belge reddedildi.');
    }

    public function archiveDocument(int $documentId): void
    {
        abort_unless(auth()->user() && auth()->user()->hasHrPermission('hr.documents.archive'), 403);
        $doc = $this->resolveDocument($documentId);
        $doc->update(['status' => DocumentStatus::Archived, 'updated_by' => auth()->id()]);
        session()->flash('document_success', 'Belge arşivlendi.');
    }

    private function resolveDocument(int $documentId): HrEmployeeDocument
    {
        $tenantId = app(TenantContext::class)->getId();

        return HrEmployeeDocument::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenantId)
            ->where('id', $documentId)
            ->where('employee_id', $this->employee->id)
            ->firstOrFail();
    }

    private function missingMandatoryTypes(int $tenantId, $documents)
    {
        $mandatoryTypes = HrDocumentType::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenantId)
            ->where('is_mandatory', true)
            ->where('is_active', true)
            ->get();

        $fulfilledTypeIds = $documents
            ->whereIn('status', [DocumentStatus::Active, DocumentStatus::Uploaded])
            ->pluck('document_type_id')
            ->unique();

        return $mandatoryTypes->whereNotIn('id', $fulfilledTypeIds)->values();
    }
}
