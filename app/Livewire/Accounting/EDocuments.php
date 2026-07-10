<?php

namespace App\Livewire\Accounting;

use App\Models\EDocument;
use App\Models\SalesOrder;
use App\Services\Accounting\EDocumentService;
use Livewire\Component;
use Livewire\WithPagination;

class EDocuments extends Component
{
    use WithPagination;

    // Filters
    public string $search = '';
    public string $filterStatus = '';

    // Creation Form
    public bool $showCreateForm = false;
    public ?int $selectedSalesOrderId = null;
    public string $documentType = 'e_archive'; // e_invoice, e_archive

    // Cancellation Form
    public bool $showCancelModal = false;
    public ?int $cancelDocId = null;
    public string $cancelReason = '';

    // Events Viewer
    public bool $showEventsModal = false;
    public ?int $viewDocId = null;

    // Messaging
    public string $message = '';
    public string $messageType = 'success';

    protected $queryString = [
        'search' => ['except' => ''],
        'filterStatus' => ['except' => ''],
    ];

    public function createEDocument(): void
    {
        $userId = auth()->id();
        $this->validate([
            'selectedSalesOrderId' => 'required|integer',
            'documentType' => 'required|in:e_invoice,e_archive',
        ], [
            'selectedSalesOrderId.required' => 'Faturaya dönüştürülecek sipariş seçimi zorunludur.',
        ]);

        $order = SalesOrder::where('user_id', $userId)->find($this->selectedSalesOrderId);
        if (!$order) {
            $this->message = 'Seçilen sipariş bulunamadı.';
            $this->messageType = 'error';
            return;
        }

        try {
            $service = app(EDocumentService::class);
            $service->createDraft($order, $this->documentType);

            $this->message = 'e-Belge taslağı başarıyla oluşturuldu.';
            $this->messageType = 'success';
            $this->selectedSalesOrderId = null;
            $this->showCreateForm = false;
        } catch (\Exception $e) {
            $this->message = 'Belge oluşturulurken hata: ' . $e->getMessage();
            $this->messageType = 'error';
        }
    }

    public function sendToGib(int $docId): void
    {
        $userId = auth()->id();
        $doc = EDocument::where('user_id', $userId)->findOrFail($docId);

        try {
            $service = app(EDocumentService::class);
            $service->sendToProvider($doc);

            $this->message = 'e-Belge GİB entegratörüne başarıyla gönderildi ve onaylandı.';
            $this->messageType = 'success';
        } catch (\Exception $e) {
            $this->message = 'Gönderim sırasında hata: ' . $e->getMessage();
            $this->messageType = 'error';
        }
    }

    public function openCancelModal(int $docId): void
    {
        $this->cancelDocId = $docId;
        $this->cancelReason = '';
        $this->showCancelModal = true;
    }

    public function cancelDocument(): void
    {
        $userId = auth()->id();
        if (!$this->cancelDocId) {
            return;
        }

        $doc = EDocument::where('user_id', $userId)->findOrFail($this->cancelDocId);

        try {
            $service = app(EDocumentService::class);
            $service->cancelDocument($doc, $this->cancelReason);

            $this->message = 'e-Belge başarıyla iptal edildi.';
            $this->messageType = 'success';
            $this->showCancelModal = false;
            $this->cancelDocId = null;
        } catch (\Exception $e) {
            $this->message = 'İptal sırasında hata: ' . $e->getMessage();
            $this->messageType = 'error';
        }
    }

    public function openEventsModal(int $docId): void
    {
        $this->viewDocId = $docId;
        $this->showEventsModal = true;
    }

    public function getAvailableSalesOrdersProperty()
    {
        return SalesOrder::where('user_id', auth()->id())
            ->where('status', 'approved')
            ->whereDoesntHave('receivable') // Wait, or check if it doesn't have an e-document yet
            ->whereNotExists(function ($query) {
                $query->select('*')
                    ->from('e_documents')
                    ->whereColumn('e_documents.sales_order_id', 'sales_orders.id');
            })
            ->orderByDesc('id')
            ->get();
    }

    public function getDocumentsProperty()
    {
        $query = EDocument::where('user_id', auth()->id())
            ->with(['salesOrder.party', 'events']);

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('invoice_number', 'like', "%{$this->search}%")
                  ->orWhere('uuid', 'like', "%{$this->search}%")
                  ->orWhereHas('salesOrder', function ($sq) {
                      $sq->where('document_number', 'like', "%{$this->search}%");
                  });
            });
        }

        if ($this->filterStatus !== '') {
            $query->where('status', $this->filterStatus);
        }

        return $query->orderByDesc('id')
            ->paginate(15);
    }

    public function getViewDocProperty()
    {
        if ($this->viewDocId) {
            return EDocument::where('user_id', auth()->id())->with('events')->find($this->viewDocId);
        }
        return null;
    }

    public function render()
    {
        return view('livewire.accounting.e-documents')
            ->layout('layouts.app');
    }
}
