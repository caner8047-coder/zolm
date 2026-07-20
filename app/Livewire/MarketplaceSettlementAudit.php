<?php

namespace App\Livewire;

use App\Models\ChannelOrder;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\MpAuditLog;
use App\Services\ExcelService;
use App\Services\Marketplace\MarketplaceSettlementAuditQueryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class MarketplaceSettlementAudit extends Component
{
    public static array $allColumnDefs = [
        'order' => 'Sipariş',
        'store' => 'Mağaza',
        'risk' => 'Risk',
        'commission' => 'Komisyon',
        'cargo' => 'Kargo',
        'desi' => 'Desi',
        'recovery' => 'Potansiyel İade',
        'action' => 'Aksiyon',
    ];

    public static array $sortableColumns = [
        'order' => 'order_number',
        'store' => 'store_name',
        'risk' => 'severity_score',
        'commission' => 'commission_delta',
        'cargo' => 'cargo_delta',
        'desi' => 'desi_delta',
        'recovery' => 'potential_recovery',
    ];

    public string $search = '';
    public string $marketplaceFilter = '';
    public string $storeFilter = '';
    public string $legalEntityFilter = '';
    public string $riskTypeFilter = '';
    public string $dateFrom = '';
    public string $dateTo = '';
    public string $sortField = 'severity_score';
    public string $sortDirection = 'desc';
    public array $visibleColumns = ['order', 'store', 'risk', 'commission', 'cargo', 'desi', 'recovery', 'action'];

    // ─── İtiraz Aksiyon Merkezi ─────────────────────────────────
    public bool $showDisputeModal = false;
    public ?int $disputeAuditLogId = null;
    public string $disputeNote = '';
    public array $selectedDisputeIds = [];
    public bool $showBulkDisputeModal = false;
    public string $bulkDisputeNote = '';
    // ────────────────────────────────────────────────────────────

    protected $queryString = [
        'search' => ['except' => ''],
        'marketplaceFilter' => ['except' => ''],
        'storeFilter' => ['except' => ''],
        'legalEntityFilter' => ['except' => ''],
        'riskTypeFilter' => ['except' => '', 'as' => 'risk'],
        'dateFrom' => ['except' => ''],
        'dateTo' => ['except' => ''],
        'sortField' => ['except' => 'severity_score'],
        'sortDirection' => ['except' => 'desc'],
    ];

    public function mount(): void
    {
        if ($this->dateFrom === '' && $this->dateTo === '') {
            [$this->dateFrom, $this->dateTo] = $this->defaultDateRange();
        }

        if (! in_array($this->sortField, self::$sortableColumns, true)) {
            $this->sortField = 'severity_score';
        }

        $this->visibleColumns = array_values(array_intersect(
            $this->visibleColumns,
            array_keys(self::$allColumnDefs)
        ));
    }

    #[Computed]
    public function marketplaceOptions()
    {
        return MarketplaceStore::query()
            ->where('user_id', $this->userId())
            ->select('marketplace')
            ->distinct()
            ->orderBy('marketplace')
            ->pluck('marketplace');
    }

    #[Computed]
    public function storeOptions()
    {
        return MarketplaceStore::query()
            ->where('user_id', $this->userId())
            ->when(
                $this->marketplaceFilter !== '',
                fn (Builder $query) => $query->where('marketplace', $this->marketplaceFilter)
            )
            ->orderBy('store_name')
            ->get(['id', 'store_name', 'marketplace']);
    }

    #[Computed]
    public function legalEntities()
    {
        return LegalEntity::query()
            ->where('user_id', $this->userId())
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    #[Computed]
    public function audit(): array
    {
        $audit = $this->auditService()->audit($this->userId(), $this->filters());
        $queue = collect($audit['queue']);
        $field = $this->sortField;

        $audit['queue'] = ($this->sortDirection === 'asc'
            ? $queue->sortBy(fn (array $row) => $row[$field] ?? null, SORT_NATURAL | SORT_FLAG_CASE)
            : $queue->sortByDesc(fn (array $row) => $row[$field] ?? null, SORT_NATURAL | SORT_FLAG_CASE))
            ->values()
            ->all();

        return $audit;
    }

    public function updatedMarketplaceFilter(): void
    {
        $this->storeFilter = '';
        unset($this->storeOptions, $this->audit);
    }

    public function updated($property): void
    {
        unset($this->audit);
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->marketplaceFilter = '';
        $this->storeFilter = '';
        $this->legalEntityFilter = '';
        $this->riskTypeFilter = '';
        [$this->dateFrom, $this->dateTo] = $this->defaultDateRange();
        unset($this->audit);
    }

    public function focusRisk(string $risk): void
    {
        if (! array_key_exists($risk, $this->riskOptions())) {
            return;
        }

        $this->riskTypeFilter = $this->riskTypeFilter === $risk ? '' : $risk;
        unset($this->audit);
    }

    public function sortTable(string $column): void
    {
        if (! isset(self::$sortableColumns[$column])) {
            return;
        }

        $field = self::$sortableColumns[$column];
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'desc';
        }

        unset($this->audit);
    }

    public function toggleColumn(string $column): void
    {
        if (! array_key_exists($column, self::$allColumnDefs)) {
            return;
        }

        if (in_array($column, $this->visibleColumns, true)) {
            if (count($this->visibleColumns) > 2) {
                $this->visibleColumns = array_values(array_diff($this->visibleColumns, [$column]));
            }
        } else {
            $this->visibleColumns[] = $column;
            $this->visibleColumns = array_values(array_unique($this->visibleColumns));
        }
    }

    // ─── İtiraz Aksiyon Merkezi Metodları ───────────────────────

    /**
     * Tekil itiraz modalını aç
     */
    public function openDisputeModal(int $auditLogId): void
    {
        $log = MpAuditLog::find($auditLogId);
        if (! $log || ! $log->canBeDisputed()) {
            session()->flash('warning', 'Bu kayıt itiraz için uygun değil.');
            return;
        }

        $this->disputeAuditLogId = $auditLogId;
        $this->disputeNote       = '';
        $this->showDisputeModal  = true;
    }

    public function closeDisputeModal(): void
    {
        $this->showDisputeModal  = false;
        $this->disputeAuditLogId = null;
        $this->disputeNote       = '';
    }

    /**
     * Tekil itirazı kaydet
     */
    public function submitDispute(): void
    {
        if (! $this->disputeAuditLogId) {
            return;
        }

        $log = MpAuditLog::find($this->disputeAuditLogId);
        if (! $log || ! $log->canBeDisputed()) {
            session()->flash('warning', 'Bu kayıt itiraz için uygun değil.');
            $this->closeDisputeModal();
            return;
        }

        $log->markAsDisputed($this->disputeNote);
        $this->closeDisputeModal();
        session()->flash('success', 'İtiraz kaydedildi. Durum: Beklemede.');
    }

    /**
     * Vade takvimi — KAYIP_ODEME kayıtlarını gecikme süresine göre segmentler
     * Trendyol'un 14 günlük ödeme döngüsünü baz alır (teslimattan itibaren)
     */
    #[Computed]
    public function paymentVadeSegments(): array
    {
        $now = now();

        // description alanından gün sayısını regex ile çıkarıyoruz
        $logs = MpAuditLog::query()
            ->where('rule_code', 'KAYIP_ODEME')
            ->whereNull('dispute_status')
            ->get(['id', 'description', 'difference', 'title']);

        $segments = [
            '14_gunluk'   => ['label' => '0–14 gün (henüz vadesi gelmemiş)', 'count' => 0, 'total' => 0.0, 'color' => 'slate'],
            '30_gunluk'   => ['label' => '15–30 gün (takip gerekli)',          'count' => 0, 'total' => 0.0, 'color' => 'amber'],
            '90_gunluk'   => ['label' => '31–90 gün (kritik gecikme)',          'count' => 0, 'total' => 0.0, 'color' => 'orange'],
            '90_ustu'     => ['label' => '90+ gün (itiraz öncelikli)',          'count' => 0, 'total' => 0.0, 'color' => 'red'],
        ];

        foreach ($logs as $log) {
            // Description'dan "86.264122917245 gün" gibi değeri çek
            preg_match('/(\d+(?:\.\d+)?)\s*gün/u', $log->description ?? '', $m);
            $days = isset($m[1]) ? (int) $m[1] : 0;
            $diff = (float) $log->difference;

            if ($days <= 14) {
                $segments['14_gunluk']['count']++;
                $segments['14_gunluk']['total'] += $diff;
            } elseif ($days <= 30) {
                $segments['30_gunluk']['count']++;
                $segments['30_gunluk']['total'] += $diff;
            } elseif ($days <= 90) {
                $segments['90_gunluk']['count']++;
                $segments['90_gunluk']['total'] += $diff;
            } else {
                $segments['90_ustu']['count']++;
                $segments['90_ustu']['total'] += $diff;
            }
        }

        // Tutarları yuvarla
        foreach ($segments as &$seg) {
            $seg['total'] = round($seg['total'], 2);
        }
        unset($seg);

        return $segments;
    }

    /**
     * İtiraz durumu özeti
     */
    #[Computed]
    public function disputeSummary(): array
    {
        $base = MpAuditLog::query()
            ->whereIn('rule_code', ['KAYIP_ODEME', 'KARGO_MALIYET_ASIMI', 'HAKEDIS_FARK']);

        return [
            'total'    => (clone $base)->count(),
            'disputed' => (clone $base)->whereNotNull('dispute_status')->count(),
            'pending'  => (clone $base)->where('dispute_status', MpAuditLog::DISPUTE_PENDING)->count(),
            'accepted' => (clone $base)->where('dispute_status', MpAuditLog::DISPUTE_ACCEPTED)->count(),
            'open'     => (clone $base)->whereNull('dispute_status')->count(),
        ];
    }

    /**
     * İtiraz paketi Excel'e audit loglarını da ekle
     */
    public function exportDisputeExcel(): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $logs = MpAuditLog::query()
            ->whereIn('rule_code', ['KAYIP_ODEME', 'KARGO_MALIYET_ASIMI', 'HAKEDIS_FARK'])
            ->whereNull('dispute_status')
            ->orderByDesc('difference')
            ->get(['id', 'rule_code', 'title', 'description', 'expected_value', 'actual_value', 'difference', 'created_at']);

        $fileName = 'itiraz-paketi-' . now()->format('Ymd-His') . '.xlsx';
        $path     = storage_path('app/temp/' . $fileName);

        if (! is_dir(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        app(ExcelService::class)->exportToXlsx([
            [
                'name' => 'Itiraz Edilecekler',
                'data' => $logs->map(fn ($log) => [
                    'Kural'            => $log->rule_code,
                    'Başlık'           => $log->title,
                    'Açıklama'         => $log->description,
                    'Beklenen (TL)'    => (float) $log->expected_value,
                    'Gerçekleşen (TL)' => (float) $log->actual_value,
                    'Fark (TL)'        => (float) $log->difference,
                    'Tarih'            => $log->created_at,
                ])->values()->all(),
            ],
        ], $path);

        return response()->download($path, $fileName)->deleteFileAfterSend(true);
    }

    // ────────────────────────────────────────────────────────────

    public function exportAppealPackage()
    {
        $audit = $this->audit;
        $fileName = 'hakedis-desi-kesinti-itiraz-paketi-' . now()->format('Ymd-His') . '.xlsx';
        $path = storage_path('app/temp/' . $fileName);
        $summary = $audit['summary'];
        $trend = $audit['service_fee_trend'];
        $tolerances = $audit['tolerances'];

        app(ExcelService::class)->exportToXlsx([
            [
                'name' => 'Kontrol Ozeti',
                'data' => [
                    ['Metrik' => 'İncelenecek sipariş', 'Değer' => $summary['review_order_count']],
                    ['Metrik' => 'Kritik sipariş', 'Değer' => $summary['critical_order_count']],
                    ['Metrik' => 'Potansiyel iade', 'Değer' => $summary['potential_recovery']],
                    ['Metrik' => 'Ödeme bekleyen', 'Değer' => $summary['waiting_settlement_count']],
                    ['Metrik' => 'Komisyon farkı', 'Değer' => $summary['commission_difference_count']],
                    ['Metrik' => 'Kargo tutar farkı', 'Değer' => $summary['cargo_difference_count']],
                    ['Metrik' => 'Desi farkı', 'Değer' => $summary['desi_difference_count']],
                    ['Metrik' => 'Sevkiyat kaydı eksik', 'Değer' => $summary['missing_shipment_count']],
                    ['Metrik' => 'Eşleşmeyen kargo faturası', 'Değer' => $summary['orphan_invoice_count']],
                    ['Metrik' => 'Hizmet bedeli oranı', 'Değer' => $trend['current_rate']],
                    ['Metrik' => 'Önceki hizmet bedeli oranı', 'Değer' => $trend['previous_rate']],
                ],
            ],
            [
                'name' => 'Risk Dagilimi',
                'data' => collect($audit['risk_breakdown'])->map(fn (array $risk) => [
                    'Risk' => $risk['label'],
                    'Kayıt Sayısı' => $risk['count'],
                    'Tutar' => $risk['amount'],
                ])->all(),
            ],
            [
                'name' => 'Itiraz Detayi',
                'data' => $this->auditService()->appealExportRows($this->userId(), $this->filters()),
            ],
            [
                'name' => 'Toleranslar',
                'data' => [
                    ['Kontrol' => 'Komisyon farkı', 'Limit' => $tolerances['commission'], 'Birim' => 'TL'],
                    ['Kontrol' => 'Kargo tutar farkı', 'Limit' => $tolerances['cargo'], 'Birim' => 'TL'],
                    ['Kontrol' => 'Desi farkı', 'Limit' => $tolerances['desi'], 'Birim' => 'Desi'],
                    ['Kontrol' => 'Ödeme farkı', 'Limit' => $tolerances['settlement'], 'Birim' => 'TL'],
                    ['Kontrol' => 'Hizmet bedeli artışı', 'Limit' => $tolerances['service_fee_rate'], 'Birim' => 'Puan'],
                ],
            ],
        ], $path);

        return response()->download($path, $fileName)->deleteFileAfterSend(true);
    }

    public function financeUrl(array $row): string
    {
        return route('mp.finance', [
            'search' => $row['order_number'] ?? '',
            'sortField' => 'reconciliation_delta_abs_metric',
            'sortDirection' => 'desc',
        ]);
    }

    public function cargoUrl(): string
    {
        return route('cargo-reports', ['activeTab' => 'shipments']);
    }

    public function render()
    {
        return view('livewire.marketplace-settlement-audit', [
            'audit'                => $this->audit,
            'marketplaceOptions'   => $this->marketplaceOptions,
            'storeOptions'         => $this->storeOptions,
            'legalEntities'        => $this->legalEntities,
            'riskOptions'          => $this->riskOptions(),
            'columnDefs'           => self::$allColumnDefs,
            'sortableColumns'      => self::$sortableColumns,
            'paymentVadeSegments'  => $this->paymentVadeSegments,
            'disputeSummary'       => $this->disputeSummary,
        ])->layout('layouts.app', ['title' => 'Eksik Ödeme Takibi']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function filters(): array
    {
        return [
            'search' => $this->search,
            'marketplace' => $this->marketplaceFilter,
            'store_id' => $this->storeFilter,
            'legal_entity_id' => $this->legalEntityFilter,
            'risk_type' => $this->riskTypeFilter,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function riskOptions(): array
    {
        return [
            'waiting_settlement' => 'Ödeme bekleyen',
            'settlement_difference' => 'Ödeme farkı',
            'commission_difference' => 'Komisyon farkı',
            'cargo_amount_difference' => 'Kargo tutar farkı',
            'desi_difference' => 'Desi farkı',
            'missing_shipment' => 'Sevkiyat kaydı eksik',
            'penalty_other_invoice' => 'Ceza / diğer fatura',
            'return_cargo' => 'İade kargo',
            // Ported from V1 AuditEngine
            'stopaj_difference' => 'Stopaj tutarsızlığı',
            'commission_refund_missing' => 'Komisyon iade eksikliği',
            'transaction_discrepancy' => 'Cari hesap uyumsuzluğu',
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function defaultDateRange(): array
    {
        $latestOrderedAt = ChannelOrder::query()
            ->whereHas('store', fn (Builder $query) => $query->where('user_id', $this->userId()))
            ->max('ordered_at');

        $dateTo = $latestOrderedAt
            ? Carbon::parse($latestOrderedAt)->toDateString()
            : now()->toDateString();

        return [
            Carbon::parse($dateTo)->subDays(30)->toDateString(),
            $dateTo,
        ];
    }

    protected function auditService(): MarketplaceSettlementAuditQueryService
    {
        return app(MarketplaceSettlementAuditQueryService::class);
    }

    protected function userId(): int
    {
        return Auth::id() ?? 1;
    }
}
