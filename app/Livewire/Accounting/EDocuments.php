<?php

namespace App\Livewire\Accounting;

use App\Models\EDocument;
use App\Models\LegalEntity;
use App\Models\Party;
use App\Models\SalesOrder;
use App\Services\Accounting\EDocumentService;
use Livewire\Component;
use Livewire\WithPagination;

class EDocuments extends Component
{
    use WithPagination;

    // Arama ve Filtreler
    public string $search = '';
    public string $filterStatus = '';
    public string $filterDocumentType = '';
    public string $filterDateFrom = '';
    public string $filterDateTo = '';
    public ?int $filterPartyId = null;
    public ?int $filterLegalEntityId = null;

    // Sıralama ve Kolon Standartları
    public string $sortColumn = 'id';
    public string $sortDirection = 'desc';
    public array $visibleColumns = ['id', 'issue_date', 'invoice_number', 'document_type', 'buyer', 'total_amount', 'status', 'action'];

    protected static array $sortableColumns = ['id', 'invoice_number', 'issue_date', 'document_type', 'total_amount', 'status'];

    // Yeni Taslak Belge Formu
    public bool $showCreateForm = false;
    public ?int $selectedSalesOrderId = null;
    public string $documentType = 'e_archive'; // e_invoice, e_archive
    public string $buyerTaxNumber = '';
    public string $buyerTaxOffice = '';
    public string $buyerEmail = '';
    public string $buyerPhone = '';
    public string $buyerAddress = '';

    // İptal Modalı
    public bool $showCancelModal = false;
    public ?int $cancelDocId = null;
    public string $cancelReason = '';

    // Olay Günlüğü Modalı
    public bool $showEventsModal = false;
    public ?int $viewDocId = null;

    // Mesajlar
    public string $message = '';
    public string $messageType = 'success'; // success, error

    protected $queryString = [
        'search'             => ['except' => ''],
        'filterStatus'       => ['except' => ''],
        'filterDocumentType' => ['except' => ''],
        'filterDateFrom'     => ['except' => ''],
        'filterDateTo'       => ['except' => ''],
        'filterPartyId'      => ['except' => null],
        'filterLegalEntityId'=> ['except' => null],
    ];

    public function updatedSelectedSalesOrderId($value): void
    {
        if ($value) {
            $userId = auth()->id();
            $order = SalesOrder::where('user_id', $userId)->with('party')->find($value);
            if ($order && $order->party) {
                // Alıcı bilgilerini doldur
                $this->buyerTaxOffice = $order->party->tax_office ?? '';
                $this->buyerEmail     = $order->party->email ?? '';
                $this->buyerPhone     = $order->party->phone ?? '';
                $this->buyerAddress   = $order->party->address ?? '';

                // VKN/TCKN bul
                $identity = $order->party->identities()
                    ->whereIn('identity_kind', ['vkn', 'tckn'])
                    ->first();
                $this->buyerTaxNumber = $identity ? $identity->identity_value : '';
            }
        } else {
            $this->resetBuyerFields();
        }
    }

    private function resetBuyerFields(): void
    {
        $this->buyerTaxNumber = '';
        $this->buyerTaxOffice = '';
        $this->buyerEmail     = '';
        $this->buyerPhone     = '';
        $this->buyerAddress   = '';
    }

    public function createEDocument(): void
    {
        $userId = auth()->id();
        $this->validate([
            'selectedSalesOrderId' => 'required|integer',
            'documentType'         => 'required|in:e_invoice,e_archive',
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
            $service->createDraft($order, $this->documentType, [
                'buyer_tax_number' => $this->buyerTaxNumber ?: null,
                'buyer_tax_office' => $this->buyerTaxOffice ?: null,
                'buyer_email'      => $this->buyerEmail ?: null,
                'buyer_phone'      => $this->buyerPhone ?: null,
                'buyer_address'    => $this->buyerAddress ?: null,
            ], $userId);

            $this->message = 'e-Belge taslağı başarıyla oluşturuldu.';
            $this->messageType = 'success';
            $this->selectedSalesOrderId = null;
            $this->showCreateForm = false;
            $this->resetBuyerFields();
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
            $service->sendToProvider($doc, $userId);

            $this->message = 'e-Belge GİB entegratörüne başarıyla gönderildi ve onaylandı.';
            $this->messageType = 'success';
        } catch (\Exception $e) {
            $this->message = 'Gönderim sırasında hata: ' . $e->getMessage();
            $this->messageType = 'error';
        }
    }

    public function openCancelModal(int $docId): void
    {
        $userId = auth()->id();
        // Belgenin kullanıcıya ait olduğundan emin ol
        EDocument::where('user_id', $userId)->findOrFail($docId);

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
            $service->cancelDocument($doc, $this->cancelReason, $userId);

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
        $userId = auth()->id();
        // Guard check
        EDocument::where('user_id', $userId)->findOrFail($docId);

        $this->viewDocId = $docId;
        $this->showEventsModal = true;
    }

    // -------------------------------------------------------------------------
    // Computed Properties
    // -------------------------------------------------------------------------

    public function getAvailableSalesOrdersProperty()
    {
        return SalesOrder::where('user_id', auth()->id())
            ->where('status', 'approved')
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
        $userId = auth()->id();
        $query = EDocument::where('user_id', $userId)
            ->with(['salesOrder.party', 'party', 'events']);

        // Arama (Tenant sızıntısını engellemek için nested inside where)
        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('invoice_number', 'like', "%{$this->search}%")
                  ->orWhere('uuid', 'like', "%{$this->search}%")
                  ->orWhere('buyer_name', 'like', "%{$this->search}%")
                  ->orWhere('buyer_tax_number', 'like', "%{$this->search}%")
                  ->orWhereHas('salesOrder', function ($sq) {
                      $sq->where('document_number', 'like', "%{$this->search}%");
                  });
            });
        }

        // Filtreler
        if ($this->filterStatus !== '') {
            $query->where('status', $this->filterStatus);
        }

        if ($this->filterDocumentType !== '') {
            $query->where('document_type', $this->filterDocumentType);
        }

        if ($this->filterDateFrom !== '') {
            $query->whereDate('issue_date', '>=', $this->filterDateFrom);
        }

        if ($this->filterDateTo !== '') {
            $query->whereDate('issue_date', '<=', $this->filterDateTo);
        }

        if ($this->filterPartyId) {
            $query->where('party_id', $this->filterPartyId);
        }

        if ($this->filterLegalEntityId) {
            $query->where('legal_entity_id', $this->filterLegalEntityId);
        }

        // Sıralama Hardening
        $dir = in_array(strtolower($this->sortDirection), ['asc', 'desc'], true) ? strtolower($this->sortDirection) : 'desc';
        if (in_array($this->sortColumn, self::$sortableColumns, true)) {
            $query->orderBy($this->sortColumn, $dir);
        } else {
            $query->orderBy('id', 'desc');
        }

        return $query->paginate(15);
    }

    public function getKpisProperty(): array
    {
        $userId = auth()->id();
        $drafts = EDocument::where('user_id', $userId)->where('status', 'draft')->count();
        $accepted = EDocument::where('user_id', $userId)->where('status', 'accepted')->count();
        $cancelled = EDocument::where('user_id', $userId)->where('status', 'cancelled')->count();

        $acceptedTotal = (float) EDocument::where('user_id', $userId)
            ->where('status', 'accepted')
            ->sum('total_amount');

        return [
            'drafts'        => $drafts,
            'accepted'      => $accepted,
            'cancelled'     => $cancelled,
            'acceptedTotal' => $acceptedTotal,
        ];
    }

    public function getViewDocProperty()
    {
        if ($this->viewDocId) {
            return EDocument::where('user_id', auth()->id())->with('events.actor')->find($this->viewDocId);
        }
        return null;
    }

    public function getColumnDefsProperty(): array
    {
        return [
            'id'            => 'No',
            'issue_date'    => 'Tarih',
            'invoice_number'=> 'Belge No / UUID',
            'document_type' => 'Belge Türü',
            'buyer'         => 'Alıcı',
            'total_amount'  => 'Toplam Tutar',
            'status'        => 'Durum',
            'action'        => 'İşlemler',
        ];
    }

    public function toggleColumn(string $column): void
    {
        if (in_array($column, $this->visibleColumns, true)) {
            $this->visibleColumns = array_values(array_diff($this->visibleColumns, [$column]));
        } else {
            $this->visibleColumns[] = $column;
        }
    }

    public function sortTable(string $column): void
    {
        if (!in_array($column, self::$sortableColumns, true)) {
            return;
        }

        if ($this->sortColumn === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortColumn    = $column;
            $this->sortDirection = 'asc';
        }
    }

    public function getPartiesProperty()
    {
        return Party::where('user_id', auth()->id())->orderBy('display_name')->get();
    }

    public function getLegalEntitiesProperty()
    {
        return LegalEntity::where('user_id', auth()->id())->active()->orderBy('name')->get();
    }

    public function render()
    {
        return view('livewire.accounting.e-documents')
            ->layout('layouts.app');
    }
}
