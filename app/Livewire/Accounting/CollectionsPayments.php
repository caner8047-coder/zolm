<?php

namespace App\Livewire\Accounting;

use App\Models\Account;
use App\Models\Collection;
use App\Models\Party;
use App\Models\Payable;
use App\Models\Payment;
use App\Models\Receivable;
use App\Services\Accounting\CollectionPaymentService;
use Livewire\Component;
use Livewire\WithPagination;

class CollectionsPayments extends Component
{
    use WithPagination;

    // ─── Tab ───────────────────────────────────────────────────────────────
    public string $tab = 'collections'; // collections | payments

    // ─── Filtreler ─────────────────────────────────────────────────────────
    public string $search = '';
    public string $filterStatus = '';
    public string $filterDateFrom = '';
    public string $filterDateTo = '';

    // ─── Tahsilat Formu ────────────────────────────────────────────────────
    public bool $showCollectionForm = false;
    public ?int $colPartyId = null;
    public ?int $colAccountId = null;
    public float $colAmount = 0.0;
    public string $colDate = '';
    public string $colMethod = 'bank';
    public string $colDescription = '';
    public string $colReference = '';

    // ─── Ödeme Formu ───────────────────────────────────────────────────────
    public bool $showPaymentForm = false;
    public ?int $payPartyId = null;
    public ?int $payAccountId = null;
    public float $payAmount = 0.0;
    public string $payDate = '';
    public string $payMethod = 'bank';
    public string $payDescription = '';
    public string $payReference = '';

    // ─── Dağıtım Formu ─────────────────────────────────────────────────────
    public bool $showAllocateForm = false;
    public string $allocateType = ''; // collection | payment
    public ?int $allocateId = null;
    public float $allocateBalance = 0.0;
    public array $allocateLines = [];  // [{receivable_id|payable_id, amount, ref, remaining}]

    // ─── İptal ─────────────────────────────────────────────────────────────
    public bool $showVoidConfirm = false;
    public string $voidType = ''; // collection | payment
    public ?int $voidId = null;
    public string $voidReason = '';

    // ─── Mesajlaşma ────────────────────────────────────────────────────────
    public string $message = '';
    public string $messageType = 'success';

    protected $queryString = [
        'tab'            => ['except' => 'collections'],
        'search'         => ['except' => ''],
        'filterStatus'   => ['except' => ''],
        'filterDateFrom' => ['except' => ''],
        'filterDateTo'   => ['except' => ''],
    ];

    public function mount(): void
    {
        $this->colDate = now()->toDateString();
        $this->payDate = now()->toDateString();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // COMPUTED PROPERTIES
    // ─────────────────────────────────────────────────────────────────────────

    public function getCollectionsProperty()
    {
        $userId = auth()->id();

        return Collection::where('user_id', $userId)
            ->with(['party', 'account'])
            ->when($this->search, function ($q) {
                $q->where(function ($inner) {
                    $inner->whereHas('party', fn($p) => $p->where('display_name', 'like', '%' . $this->search . '%'))
                          ->orWhere('description', 'like', '%' . $this->search . '%')
                          ->orWhere('reference_number', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->filterStatus, fn($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterDateFrom, fn($q) => $q->where('collection_date', '>=', $this->filterDateFrom))
            ->when($this->filterDateTo, fn($q) => $q->where('collection_date', '<=', $this->filterDateTo))
            ->orderByDesc('collection_date')
            ->orderByDesc('id')
            ->paginate(15);
    }

    public function getPaymentsProperty()
    {
        $userId = auth()->id();

        return Payment::where('user_id', $userId)
            ->with(['party', 'account'])
            ->when($this->search, function ($q) {
                $q->where(function ($inner) {
                    $inner->whereHas('party', fn($p) => $p->where('display_name', 'like', '%' . $this->search . '%'))
                          ->orWhere('description', 'like', '%' . $this->search . '%')
                          ->orWhere('reference_number', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->filterStatus, fn($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterDateFrom, fn($q) => $q->where('payment_date', '>=', $this->filterDateFrom))
            ->when($this->filterDateTo, fn($q) => $q->where('payment_date', '<=', $this->filterDateTo))
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->paginate(15);
    }

    public function getPartiesProperty()
    {
        return Party::where('user_id', auth()->id())
            ->where('status', 'active')
            ->orderBy('display_name')
            ->get();
    }

    public function getCustomerPartiesProperty()
    {
        return Party::where('user_id', auth()->id())
            ->where('status', 'active')
            ->whereHas('roles', fn($q) => $q->where('role', 'customer'))
            ->orderBy('display_name')
            ->get();
    }

    public function getSupplierPartiesProperty()
    {
        return Party::where('user_id', auth()->id())
            ->where('status', 'active')
            ->whereHas('roles', fn($q) => $q->where('role', 'supplier'))
            ->orderBy('display_name')
            ->get();
    }

    public function getCashBankAccountsProperty()
    {
        return app(CollectionPaymentService::class)->getCashBankAccounts(auth()->id());
    }

    public function getKpisProperty(): array
    {
        $userId = auth()->id();

        $totalCollected = Collection::where('user_id', $userId)
            ->where('status', 'posted')
            ->sum('amount');

        $totalPaid = Payment::where('user_id', $userId)
            ->where('status', 'posted')
            ->sum('amount');

        $openReceivables = Receivable::where('user_id', $userId)
            ->whereIn('status', ['open', 'partially_paid'])
            ->selectRaw('SUM(amount - paid_amount) as total')
            ->value('total') ?? 0;

        $openPayables = Payable::where('user_id', $userId)
            ->whereIn('status', ['open', 'partially_paid'])
            ->selectRaw('SUM(amount - paid_amount) as total')
            ->value('total') ?? 0;

        return [
            'total_collected'  => (float) $totalCollected,
            'total_paid'       => (float) $totalPaid,
            'open_receivables' => (float) $openReceivables,
            'open_payables'    => (float) $openPayables,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // TAHSİLAT İŞLEMLERİ
    // ─────────────────────────────────────────────────────────────────────────

    public function openCollectionForm(): void
    {
        $this->resetCollectionForm();
        $this->showCollectionForm = true;
    }

    public function saveCollection(): void
    {
        $this->validate([
            'colPartyId'   => 'required|integer',
            'colAccountId' => 'required|integer',
            'colAmount'    => 'required|numeric|min:0.01',
            'colDate'      => 'required|date',
            'colMethod'    => 'required|string',
        ], [
            'colPartyId.required'   => 'Lütfen bir cari seçin.',
            'colAccountId.required' => 'Lütfen bir kasa/banka hesabı seçin.',
            'colAmount.min'         => 'Tutar sıfırdan büyük olmalıdır.',
            'colDate.required'      => 'Tahsilat tarihi zorunludur.',
        ]);

        try {
            $service = app(CollectionPaymentService::class);
            $service->recordCollection([
                'user_id'        => auth()->id(),
                'party_id'       => $this->colPartyId,
                'account_id'     => $this->colAccountId,
                'amount'         => $this->colAmount,
                'collection_date' => $this->colDate,
                'payment_method' => $this->colMethod,
                'description'    => $this->colDescription ?: null,
                'reference_number' => $this->colReference ?: null,
            ]);

            $this->showCollectionForm = false;
            $this->resetCollectionForm();
            $this->message = 'Tahsilat başarıyla kaydedildi.';
            $this->messageType = 'success';
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->message = $e->getMessage();
            $this->messageType = 'error';
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ÖDEME İŞLEMLERİ
    // ─────────────────────────────────────────────────────────────────────────

    public function openPaymentForm(): void
    {
        $this->resetPaymentForm();
        $this->showPaymentForm = true;
    }

    public function savePayment(): void
    {
        $this->validate([
            'payPartyId'   => 'required|integer',
            'payAccountId' => 'required|integer',
            'payAmount'    => 'required|numeric|min:0.01',
            'payDate'      => 'required|date',
            'payMethod'    => 'required|string',
        ], [
            'payPartyId.required'   => 'Lütfen bir cari seçin.',
            'payAccountId.required' => 'Lütfen bir kasa/banka hesabı seçin.',
            'payAmount.min'         => 'Tutar sıfırdan büyük olmalıdır.',
            'payDate.required'      => 'Ödeme tarihi zorunludur.',
        ]);

        try {
            $service = app(CollectionPaymentService::class);
            $service->recordPayment([
                'user_id'        => auth()->id(),
                'party_id'       => $this->payPartyId,
                'account_id'     => $this->payAccountId,
                'amount'         => $this->payAmount,
                'payment_date'   => $this->payDate,
                'payment_method' => $this->payMethod,
                'description'    => $this->payDescription ?: null,
                'reference_number' => $this->payReference ?: null,
            ]);

            $this->showPaymentForm = false;
            $this->resetPaymentForm();
            $this->message = 'Ödeme başarıyla kaydedildi.';
            $this->messageType = 'success';
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->message = $e->getMessage();
            $this->messageType = 'error';
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DAĞITIM (ALLOCATION)
    // ─────────────────────────────────────────────────────────────────────────

    public function openAllocateForm(string $type, int $id): void
    {
        $this->allocateType = $type;
        $this->allocateId = $id;
        $this->allocateLines = [];

        if ($type === 'collection') {
            $collection = Collection::where('user_id', auth()->id())->findOrFail($id);
            $alreadyAllocated = (float) $collection->allocations()->sum('amount');
            $this->allocateBalance = (float) $collection->amount - $alreadyAllocated;

            $openRecs = app(CollectionPaymentService::class)
                ->getOpenReceivables(auth()->id(), $collection->party_id);

            foreach ($openRecs as $rec) {
                $this->allocateLines[] = [
                    'receivable_id' => $rec->id,
                    'ref'           => $rec->document_number ?? 'REC-' . $rec->id,
                    'remaining'     => $rec->remainingAmount(),
                    'amount'        => 0.0,
                ];
            }
        } else {
            $payment = Payment::where('user_id', auth()->id())->findOrFail($id);
            $alreadyAllocated = (float) $payment->allocations()->sum('amount');
            $this->allocateBalance = (float) $payment->amount - $alreadyAllocated;

            $openPays = app(CollectionPaymentService::class)
                ->getOpenPayables(auth()->id(), $payment->party_id);

            foreach ($openPays as $pay) {
                $this->allocateLines[] = [
                    'payable_id' => $pay->id,
                    'ref'        => $pay->document_number ?? 'PAY-' . $pay->id,
                    'remaining'  => $pay->remainingAmount(),
                    'amount'     => 0.0,
                ];
            }
        }

        $this->showAllocateForm = true;
    }

    public function saveAllocation(): void
    {
        try {
            $service = app(CollectionPaymentService::class);

            if ($this->allocateType === 'collection') {
                $collection = Collection::where('user_id', auth()->id())->findOrFail($this->allocateId);
                $allocations = array_map(fn($l) => [
                    'receivable_id' => $l['receivable_id'],
                    'amount'        => (float) $l['amount'],
                ], array_filter($this->allocateLines, fn($l) => (float) $l['amount'] > 0));

                $service->allocateCollection($collection, array_values($allocations));
                $this->message = 'Tahsilat faturalara dağıtıldı.';
            } else {
                $payment = Payment::where('user_id', auth()->id())->findOrFail($this->allocateId);
                $allocations = array_map(fn($l) => [
                    'payable_id' => $l['payable_id'],
                    'amount'     => (float) $l['amount'],
                ], array_filter($this->allocateLines, fn($l) => (float) $l['amount'] > 0));

                $service->allocatePayment($payment, array_values($allocations));
                $this->message = 'Ödeme faturalara dağıtıldı.';
            }

            $this->messageType = 'success';
            $this->showAllocateForm = false;
            $this->allocateLines = [];
        } catch (\Throwable $e) {
            $this->message = $e->getMessage();
            $this->messageType = 'error';
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // İPTAL (VOID)
    // ─────────────────────────────────────────────────────────────────────────

    public function openVoidConfirm(string $type, int $id): void
    {
        $this->voidType = $type;
        $this->voidId = $id;
        $this->voidReason = '';
        $this->showVoidConfirm = true;
    }

    public function confirmVoid(): void
    {
        try {
            $service = app(CollectionPaymentService::class);

            if ($this->voidType === 'collection') {
                $collection = Collection::where('user_id', auth()->id())->findOrFail($this->voidId);
                $service->voidCollection($collection, $this->voidReason ?: null);
                $this->message = 'Tahsilat iptal edildi.';
            } else {
                $payment = Payment::where('user_id', auth()->id())->findOrFail($this->voidId);
                $service->voidPayment($payment, $this->voidReason ?: null);
                $this->message = 'Ödeme iptal edildi.';
            }

            $this->messageType = 'success';
            $this->showVoidConfirm = false;
        } catch (\Throwable $e) {
            $this->message = $e->getMessage();
            $this->messageType = 'error';
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // YARDIMCI
    // ─────────────────────────────────────────────────────────────────────────

    public function switchTab(string $tab): void
    {
        $this->tab = $tab;
        $this->resetPage();
        $this->search = '';
        $this->filterStatus = '';
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    protected function resetCollectionForm(): void
    {
        $this->colPartyId = null;
        $this->colAccountId = null;
        $this->colAmount = 0.0;
        $this->colDate = now()->toDateString();
        $this->colMethod = 'bank';
        $this->colDescription = '';
        $this->colReference = '';
    }

    protected function resetPaymentForm(): void
    {
        $this->payPartyId = null;
        $this->payAccountId = null;
        $this->payAmount = 0.0;
        $this->payDate = now()->toDateString();
        $this->payMethod = 'bank';
        $this->payDescription = '';
        $this->payReference = '';
    }

    public function render()
    {
        return view('livewire.accounting.collections-payments')
            ->layout('layouts.app');
    }
}
