<?php

namespace App\Livewire\Returns;

use App\Jobs\AnalyzeReturnIntakeItemJob;
use App\Models\ReturnIntakeDecision;
use App\Models\ReturnIntakeItem;
use App\Services\Marketplace\MarketplaceClaimActionService;
use App\Services\Returns\ReturnAutoDecisionPolicyService;
use App\Services\Returns\ReturnDailyReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class ReturnIntelligenceCenter extends Component
{
    use WithPagination;

    public bool $embedded = false;
    public string $searchQuery = '';
    public string $statusFilter = 'all';
    public string $conditionFilter = 'all';
    public string $decisionFilter = 'all';
    public ?int $selectedItemId = null;
    public string $rejectReason = '';
    public string $decisionNote = '';
    public string $message = '';
    public string $messageType = 'info';
    public string $dateFilter = 'today';

    // -- ZOLM Tablo Standardı --
    public string $sortField = 'arrived_at';
    public string $sortDirection = 'desc';

    /**
     * @var array<string, bool>
     */
    public array $visibleColumns = [
        'date' => true,
        'type' => true,
        'reference' => true,
        'marketplace' => true,
        'status' => true,
        'condition' => true,
        'decision' => true,
        'confidence' => false,
        'operator' => false,
    ];

    /**
     * @var array<string, string>
     */
    public static array $sortableColumns = [
        'arrived_at' => 'Tarih',
        'intake_status' => 'Durum',
        'condition_status' => 'Durum (Hasar)',
        'decision_status' => 'Karar',
        'matching_confidence' => 'Güven',
    ];

    public function mount(bool $embedded = false): void
    {
        $this->embedded = $embedded;
        abort_unless(auth()->user()?->canAccessReturnsReview(), 403);

        $requestedItemId = request()->integer('item');

        if ($requestedItemId > 0) {
            $this->selectedItemId = $requestedItemId;
        }
    }

    public function updatedSearchQuery(): void
    {
        $this->resetPage($this->itemsPageName());
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage($this->itemsPageName());
    }

    public function updatedDateFilter(): void
    {
        $this->resetPage($this->itemsPageName());
    }

    public function updatedConditionFilter(): void
    {
        $this->resetPage($this->itemsPageName());
    }

    public function updatedDecisionFilter(): void
    {
        $this->resetPage($this->itemsPageName());
    }

    public function selectItem(int $itemId): void
    {
        $this->selectedItemId = $itemId;
        $this->rejectReason = '';
        $this->decisionNote = '';

        $this->dispatch('return-item-selected', itemId: $itemId);
    }

    public function reanalyzeSelectedItem(): void
    {
        $item = $this->selectedItem;

        if (!$item) {
            return;
        }

        $item->update([
            'intake_status' => 'queued',
            'analysis_started_at' => null,
            'analysis_completed_at' => null,
            'last_error' => null,
        ]);

        try {
            AnalyzeReturnIntakeItemJob::dispatchSync($item->id);
            $this->message = 'Kayıt yeniden analiz edildi.';
            $this->messageType = 'success';
        } catch (\Throwable $e) {
            $this->message = 'Analiz hatası: ' . $e->getMessage();
            $this->messageType = 'error';
        }
    }

    public function toggleColumn(string $column): void
    {
        if (isset($this->visibleColumns[$column])) {
            $this->visibleColumns[$column] = !$this->visibleColumns[$column];
        }
    }

    public function sortTable(string $field): void
    {
        if (!array_key_exists($field, self::$sortableColumns)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'desc';
        }

        $this->resetPage($this->itemsPageName());
    }

    public function markRestocked(): void
    {
        $this->recordInternalDecision('restocked', 'restock');
    }

    public function markScrapped(): void
    {
        $this->recordInternalDecision('scrapped', 'scrap');
    }

    public function markNeedsReview(): void
    {
        $this->recordInternalDecision('needs_review', 'manual_review');
    }

    public function runAutoPolicies(ReturnAutoDecisionPolicyService $policyService): void
    {
        $summary = $policyService->run(
            dryRun: false,
            limit: (int) config('returns.auto_policy_limit', 25),
        );

        $parts = [];

        if ($summary['processed'] > 0) {
            $parts[] = $summary['processed'] . ' kayıt işlendi';
        }

        if ($summary['blocked'] > 0) {
            $parts[] = $summary['blocked'] . ' kayıt marketplace flag kapalı olduğu için bekletildi';
        }

        if ($summary['errors'] > 0) {
            $parts[] = $summary['errors'] . ' kayıt hata verdi';
        }

        $this->showMessage(($parts !== [] ? implode(', ', $parts) : 'Uygun otomatik politika kaydı bulunamadı.') . '.', $summary['errors'] > 0 ? 'error' : 'success');
    }

    public function approveClaim(MarketplaceClaimActionService $claimActionService): void
    {
        $item = $this->selectedItem;

        if (!$item || !$item->claim) {
            $this->showMessage('Bağlı pazaryeri iadesi bulunamadı.', 'error');
            return;
        }

        try {
            $result = $claimActionService->approveClaim($item->claim);

            $this->createDecision($item, 'approved', 'manual', 'marketplace_approve', $this->decisionNote ?: ($result['message'] ?? null), [
                'marketplace_result' => $result,
            ], true);

            $item->update([
                'decision_status' => 'approved',
                'intake_status' => 'decisioned',
            ]);

            $this->showMessage($result['message'] ?? 'İade onaylandı.', 'success');
        } catch (\Throwable $exception) {
            $this->showMessage('Onay hatası: ' . $exception->getMessage(), 'error');
        }
    }

    public function rejectClaim(MarketplaceClaimActionService $claimActionService): void
    {
        $this->validate([
            'rejectReason' => ['required', 'string', 'min:3', 'max:1000'],
        ], [], [
            'rejectReason' => 'red nedeni',
        ]);

        $item = $this->selectedItem;

        if (!$item || !$item->claim) {
            $this->showMessage('Bağlı pazaryeri iadesi bulunamadı.', 'error');
            return;
        }

        try {
            $result = $claimActionService->rejectClaim($item->claim, $this->rejectReason);

            $this->createDecision($item, 'rejected', 'manual', 'marketplace_reject', $this->rejectReason, [
                'marketplace_result' => $result,
            ], true);

            $item->update([
                'decision_status' => 'rejected',
                'intake_status' => 'decisioned',
            ]);

            $this->showMessage($result['message'] ?? 'İade reddedildi.', 'success');
            $this->rejectReason = '';
        } catch (\Throwable $exception) {
            $this->showMessage('Red hatası: ' . $exception->getMessage(), 'error');
        }
    }

    #[Computed]
    public function items(): LengthAwarePaginator
    {
        $query = $this->buildQuery();

        $items = $query->paginate(20, ['*'], $this->itemsPageName());

        if (!$this->selectedItemId && $items->count() > 0) {
            $this->selectedItemId = (int) $items->first()->id;
        }

        return $items;
    }

    protected function buildQuery()
    {
        return ReturnIntakeItem::query()
            ->with([
                'batch.user',
                'store',
                'claim.store',
                'claim.items',
                'order.store',
                'order.items',
                'package',
                'media',
                'latestAnalysis',
                'latestDecision.user',
            ])
            ->when($this->dateFilter === 'today', fn ($builder) => $builder->whereDate('arrived_at', today()))
            ->when($this->dateFilter === 'yesterday', fn ($builder) => $builder->whereDate('arrived_at', today()->subDay()))
            ->when($this->dateFilter === 'last7days', fn ($builder) => $builder->where('arrived_at', '>=', today()->subDays(7)))
            ->when($this->statusFilter !== 'all', fn ($builder) => $builder->where('intake_status', $this->statusFilter))
            ->when($this->conditionFilter !== 'all', fn ($builder) => $builder->where('condition_status', $this->conditionFilter))
            ->when($this->decisionFilter !== 'all', fn ($builder) => $builder->where('decision_status', $this->decisionFilter))
            ->when($this->searchQuery !== '', function ($builder) {
                $search = '%' . $this->searchQuery . '%';

                $builder->where(function ($query) use ($search) {
                    $query->where('manual_reference', 'like', $search)
                        ->orWhere('warehouse_note', 'like', $search)
                        ->orWhere('operator_barcode', 'like', $search)
                        ->orWhere('detected_tracking_number', 'like', $search)
                        ->orWhere('detected_order_number', 'like', $search)
                        ->orWhere('detected_barcode', 'like', $search)
                        ->orWhere('detected_customer_name', 'like', $search)
                        ->orWhereHas('claim', function ($claimQuery) use ($search) {
                            $claimQuery->where('order_number', 'like', $search)
                                ->orWhere('external_claim_id', 'like', $search)
                                ->orWhere('customer_name', 'like', $search)
                                ->orWhere('cargo_tracking_number', 'like', $search);
                        })
                        ->orWhereHas('order', function ($orderQuery) use ($search) {
                            $orderQuery->where('order_number', 'like', $search)
                                ->orWhere('customer_name', 'like', $search);
                        });
                });
            })
            ->orderBy($this->sortField, $this->sortDirection)
            ->orderByDesc('id');
    }

    protected function itemsPageName(): string
    {
        return 'returnItemsPage';
    }

    public function exportExcel(\App\Services\ExcelService $excelService)
    {
        $query = $this->buildQuery();
        $records = $query->get();

        if ($records->isEmpty()) {
            $this->showMessage('Dışa aktarılacak kayıt bulunamadı.', 'error');
            return;
        }

        $data = $records->map(function(ReturnIntakeItem $item) {
            $contents = [];
            if ($item->claim && $item->claim->items) {
                foreach ($item->claim->items as $cItem) {
                    $qty = $cItem->quantity ?? 1;
                    $contents[] = ($cItem->product_name ?? 'Bilinmeyen Ürün') . " ({$qty} Adet)";
                }
            } elseif ($item->order && $item->order->items) {
                foreach ($item->order->items as $oItem) {
                    $qty = $oItem->quantity ?? 1;
                    $contents[] = ($oItem->product_name ?? 'Bilinmeyen Ürün') . " ({$qty} Adet)";
                }
            }
            $productDetails = implode(" | ", $contents);

            return [
                'Tarih' => $item->arrived_at?->format('d.m.Y H:i') ?? '',
                'Tür' => $item->intake_type === 'damaged' ? 'Hasarlı' : 'Hasarsız',
                'Analiz Durumu' => $item->statusLabel(),
                'Karar Durumu' => $item->decisionLabel(),
                'Sipariş No' => $item->detected_order_number ?? $item->order?->order_number ?? $item->claim?->order_number ?? '',
                'Takip No' => $item->detected_tracking_number ?? $item->claim?->cargo_tracking_number ?? '',
                'Müşteri' => $item->detected_customer_name ?? $item->order?->customer_name ?? $item->claim?->customer_name ?? '',
                'Sipariş İçeriği' => $productDetails,
                'Kargo Firması' => $item->cargo_provider ?? '',
                'Barkod' => $item->detected_barcode ?? '',
                'AI Güven Skoru' => $item->matching_confidence ? '% ' . $item->matching_confidence : '',
                'AI Tespit Hasarı' => $item->conditionLabel(),
            ];
        });

        $path = storage_path('app/public/iadeler_rapor_' . now()->format('Ymd_His') . '.xlsx');

        $excelService->exportToXlsx([
            ['name' => 'İadeler', 'data' => $data->toArray()]
        ], $path);

        return response()->download($path);
    }

    #[Computed]
    public function selectedItem(): ?ReturnIntakeItem
    {
        if (!$this->selectedItemId) {
            return null;
        }

        return ReturnIntakeItem::query()
            ->with([
                'batch.user',
                'store',
                'claim.store',
                'claim.items',
                'order.store',
                'order.items',
                'package',
                'media',
                'analyses',
                'latestAnalysis',
                'decisions.user',
                'latestDecision.user',
            ])
            ->find($this->selectedItemId);
    }

    #[Computed]
    public function kpis(): array
    {
        $base = ReturnIntakeItem::query();

        return [
            'queued' => (clone $base)->whereIn('intake_status', ['queued', 'analyzing'])->count(),
            'ready' => (clone $base)->where('intake_status', 'ready_for_decision')->count(),
            'review' => (clone $base)->whereIn('intake_status', ['needs_review', 'failed'])->count(),
            'decisioned' => (clone $base)->where('decision_status', '!=', 'pending')->count(),
        ];
    }

    #[Computed]
    public function dailyReport(): array
    {
        return app(ReturnDailyReportService::class)->build(today());
    }

    #[Computed]
    public function autoPolicyPreview(): array
    {
        return app(ReturnAutoDecisionPolicyService::class)->preview(limit: 8);
    }


    protected function recordInternalDecision(string $decisionStatus, string $reasonCode): void
    {
        $item = $this->selectedItem;

        if (!$item) {
            return;
        }

        $this->createDecision($item, $decisionStatus, 'manual', $reasonCode, $this->decisionNote);

        $item->update([
            'decision_status' => $decisionStatus,
            'intake_status' => $decisionStatus === 'needs_review' ? 'needs_review' : 'decisioned',
        ]);

        $this->showMessage('Karar kaydedildi.', 'success');
        $this->decisionNote = '';
    }

    /**
     * @param  array<string, mixed>  $rawPayload
     */
    protected function createDecision(
        ReturnIntakeItem $item,
        string $decision,
        string $mode,
        ?string $reasonCode,
        ?string $note = null,
        array $rawPayload = [],
        bool $marketplacePushed = false,
    ): ReturnIntakeDecision {
        return $item->decisions()->create([
            'user_id' => auth()->id(),
            'decision' => $decision,
            'decision_mode' => $mode,
            'reason_code' => $reasonCode,
            'note' => $note ?: null,
            'marketplace_pushed_at' => $marketplacePushed ? now() : null,
            'raw_payload' => $rawPayload ?: null,
        ]);
    }

    protected function showMessage(string $message, string $type): void
    {
        $this->message = $message;
        $this->messageType = $type;
    }

    public function render(): View
    {
        $view = view('livewire.returns.return-intelligence-center', [
            'items' => $this->items,
            'selectedItem' => $this->selectedItem,
            'kpis' => $this->kpis,
            'dailyReport' => $this->dailyReport,
            'autoPolicyPreview' => $this->autoPolicyPreview,
            'sortableColumns' => self::$sortableColumns,
        ]);

        if ($this->embedded) {
            return $view;
        }

        return $view->layout('layouts.app', [
            'title' => 'Akıllı İade Merkezi',
        ]);
    }
}
