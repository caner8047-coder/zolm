<?php

namespace App\Livewire\Cargo;

use App\Models\CargoReport;
use App\Models\CargoReportItem;
use App\Models\Compensation;
use App\Models\User;
use App\Services\MpSettingsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Tazmin Dashboard Bileşeni
 * 
 * Tab 4: Tazmin
 * - Kargo harcamaları grafik paneli
 * - Son tespit edilen desi hataları
 * - Son oluşturulan tazminler
 * - Tazmin CRUD
 */
class CompensationDashboard extends Component
{
    use WithPagination;
    use \Livewire\WithFileUploads;

    public array $errorVisibleColumns = ['date', 'customer', 'product', 'wrong_desi', 'correct_desi', 'difference', 'actions'];
    public array $compensationVisibleColumns = ['date', 'customer', 'reason', 'claimed', 'approved', 'status', 'documents', 'actions'];
    public string $errorSortField = 'created_at';
    public string $errorSortDirection = 'desc';
    public string $compensationSortField = 'created_at';
    public string $compensationSortDirection = 'desc';

    public static array $errorColumnDefs = [
        'date' => 'Tarih',
        'customer' => 'Müşteri',
        'product' => 'Ürün',
        'wrong_desi' => 'Yanlış Desi',
        'correct_desi' => 'Doğru Desi',
        'difference' => 'Fark',
        'actions' => 'İşlem',
    ];
    public static array $errorSortableColumns = [
        'date' => 'tarih',
        'customer' => 'musteri_adi',
        'product' => 'urun_adi',
        'wrong_desi' => 'gercek_desi',
        'correct_desi' => 'beklenen_desi',
        'difference' => 'tutar_fark',
    ];
    public static array $compensationColumnDefs = [
        'date' => 'Tarih',
        'customer' => 'Müşteri',
        'reason' => 'Sebep',
        'claimed' => 'Talep',
        'approved' => 'Onaylanan',
        'status' => 'Durum',
        'documents' => 'Belgeler',
        'actions' => 'İşlem',
    ];
    public static array $compensationSortableColumns = [
        'date' => 'tarih',
        'customer' => 'musteri_adi',
        'reason' => 'sebep',
        'claimed' => 'talep_tutari',
        'approved' => 'onaylanan_tutar',
        'status' => 'durum',
    ];

    // Görünüm modu: dashboard, all_errors, all_compensations
    public string $viewMode = 'dashboard';

    // Arama
    public string $search = '';

    // Ortak filtreler
    public ?string $filterStartDate = null;
    public ?string $filterEndDate = null;
    public string $filterCargoCompany = '';
    public string $filterMarketplace = '';
    public string $filterStore = '';
    public string $filterRecordType = 'all'; // all, siparis, iade, parca
    public string $filterStatus = '';
    public string $filterReason = '';
    public string $filterPriority = '';



    // Yeni tazmin modalı
    public bool $showCreateModal = false;
    public array $newCompensation = [
        'tarih' => '',
        'musteri_adi' => '',
        'takip_kodu' => '',
        'urun_adi' => '',
        'cargo_company' => 'Sürat Kargo',
        'sebep' => 'desi_fazla',
        'aciklama' => '',
        'talep_tutari' => 0,
        'priority' => 'normal',
        'responsible_user_id' => null,
        'next_action_at' => null,
        'carrier_case_no' => '',
        'internal_note' => '',
    ];
    
    // Görsel yükleme
    public $attachments = [];

    // Detay modalı
    public bool $showDetailModal = false;
    public ?int $viewingCompensationId = null;

    // Durum güncelleme
    public bool $showStatusModal = false;
    public ?int $updatingCompensationId = null;
    public string $newStatus = '';
    public float $onaylananTutar = 0;
    public float $collectedAmount = 0;
    public ?string $paymentDate = null;
    public ?string $nextActionAt = null;
    public ?int $responsibleUserId = null;
    public string $priority = 'normal';
    public string $carrierCaseNo = '';
    public string $internalNote = '';
    public string $resolutionNote = '';
    
    // Dilekçe Düzenleme
    public bool $showPetitionModal = false;
    public string $editingPetitionText = '';

    // Ekleri Görüntüleme
    public bool $showAttachmentsModal = false;
    public array $viewingAttachments = [];

    // Görüntüleme/Düzenleme/Silme
    public ?int $editingCompensationId = null;

    // Mesaj
    public string $message = '';
    public string $messageType = 'info';

    public function mount()
    {
        $this->newCompensation['tarih'] = now()->format('Y-m-d');
        $this->newCompensation['responsible_user_id'] = auth()->id();
        $this->filterStartDate = now()->subDays(30)->format('Y-m-d');
        $this->filterEndDate = now()->format('Y-m-d');
        $settings = app(MpSettingsService::class);
        $this->errorVisibleColumns = $this->normalizeVisibleColumns(
            $settings->getArray('cargo_reports.compensation.errors.visible_columns', $this->errorVisibleColumns),
            array_keys(static::$errorColumnDefs),
            ['date', 'customer', 'product', 'wrong_desi', 'correct_desi', 'difference', 'actions']
        );
        $this->compensationVisibleColumns = $this->normalizeVisibleColumns(
            $settings->getArray('cargo_reports.compensation.claims.visible_columns', $this->compensationVisibleColumns),
            array_keys(static::$compensationColumnDefs),
            ['date', 'customer', 'reason', 'claimed', 'approved', 'status', 'documents', 'actions']
        );

        $requestedCargoItemId = request()->integer('cargoItem');

        if ($requestedCargoItemId > 0) {
            $this->openCreateModal($requestedCargoItemId);
        }
    }

    /**
     * Arama değiştiğinde
     */
    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedFilterStartDate()
    {
        $this->resetPage();
    }

    public function updatedFilterEndDate()
    {
        $this->resetPage();
    }

    public function updatedFilterCargoCompany()
    {
        $this->resetPage();
    }

    public function updatedFilterMarketplace()
    {
        $this->resetPage();
    }

    public function updatedFilterStore()
    {
        $this->resetPage();
    }

    public function updatedFilterRecordType()
    {
        $this->resetPage();
    }

    public function updatedFilterStatus()
    {
        $this->resetPage();
    }

    public function updatedFilterReason()
    {
        $this->resetPage();
    }

    public function updatedFilterPriority()
    {
        $this->resetPage();
    }

    /**
     * Tüm hataları göster
     */
    #[On('cargo-comp-show-all-errors')]
    public function showAllErrors()
    {
        $this->viewMode = 'all_errors';
        $this->resetPage();
    }

    /**
     * Tüm tazminleri göster
     */
    #[On('cargo-comp-show-all-compensations')]
    public function showAllCompensations()
    {
        $this->viewMode = 'all_compensations';
        $this->resetPage();
    }

    /**
     * Dashboard'a dön
     */
    #[On('cargo-comp-back-to-dashboard')]
    public function backToDashboard()
    {
        $this->viewMode = 'dashboard';
        $this->search = '';
        $this->resetPage();
    }

    #[Computed]
    public function cargoCompanies()
    {
        return CargoReport::query()
            ->whereNotNull('cargo_company')
            ->distinct()
            ->orderBy('cargo_company')
            ->pluck('cargo_company');
    }

    #[Computed]
    public function marketplaces()
    {
        return CargoReportItem::query()
            ->whereNotNull('pazaryeri')
            ->where('pazaryeri', '!=', '')
            ->distinct()
            ->orderBy('pazaryeri')
            ->pluck('pazaryeri');
    }

    #[Computed]
    public function stores()
    {
        return CargoReportItem::query()
            ->whereNotNull('magaza')
            ->where('magaza', '!=', '')
            ->distinct()
            ->orderBy('magaza')
            ->pluck('magaza');
    }

    #[Computed]
    public function assignableUsers()
    {
        return User::query()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    protected function filteredErrorsQuery(): Builder
    {
        $query = CargoReportItem::query()
            ->with(['cargoReport', 'compensation'])
            ->where('has_error', true)
            ->whereIn('error_type', [
                'desi_fazla',
                'tutar_fazla',
                'desi_eksik',
                'tutar_eksik',
                'referans_eksik',
                'parca_eksik',
                'parca_fazla',
                'eslesmedi',
            ]);

        if ($this->filterStartDate) {
            $query->where(function (Builder $subQuery) {
                $subQuery
                    ->whereDate('tarih', '>=', $this->filterStartDate)
                    ->orWhere(function (Builder $innerQuery) {
                        $innerQuery->whereNull('tarih')->whereDate('created_at', '>=', $this->filterStartDate);
                    });
            });
        }

        if ($this->filterEndDate) {
            $query->where(function (Builder $subQuery) {
                $subQuery
                    ->whereDate('tarih', '<=', $this->filterEndDate)
                    ->orWhere(function (Builder $innerQuery) {
                        $innerQuery->whereNull('tarih')->whereDate('created_at', '<=', $this->filterEndDate);
                    });
            });
        }

        if ($this->filterCargoCompany !== '') {
            $query->whereHas('cargoReport', fn (Builder $reportQuery) => $reportQuery->where('cargo_company', $this->filterCargoCompany));
        }

        if ($this->filterMarketplace !== '') {
            $query->where('pazaryeri', $this->filterMarketplace);
        }

        if ($this->filterStore !== '') {
            $query->where('magaza', $this->filterStore);
        }

        if ($this->filterRecordType === 'siparis') {
            $query->where('is_iade', false)->where('is_parca_gonderi', false);
        } elseif ($this->filterRecordType === 'iade') {
            $query->where('is_iade', true);
        } elseif ($this->filterRecordType === 'parca') {
            $query->where('is_parca_gonderi', true);
        }

        if ($this->search !== '') {
            $query->where(function (Builder $subQuery) {
                $subQuery
                    ->where('musteri_adi', 'like', '%' . $this->search . '%')
                    ->orWhere('takip_kodu', 'like', '%' . $this->search . '%')
                    ->orWhere('urun_adi', 'like', '%' . $this->search . '%')
                    ->orWhere('stok_kodu', 'like', '%' . $this->search . '%');
            });
        }

        return $query;
    }

    protected function filteredCompensationsQuery(): Builder
    {
        $query = Compensation::query()
            ->with(['cargoReportItem.cargoReport', 'responsibleUser']);

        if ($this->filterStartDate) {
            $query->where(function (Builder $subQuery) {
                $subQuery
                    ->whereDate('talep_tarihi', '>=', $this->filterStartDate)
                    ->orWhere(function (Builder $innerQuery) {
                        $innerQuery->whereNull('talep_tarihi')->whereDate('created_at', '>=', $this->filterStartDate);
                    });
            });
        }

        if ($this->filterEndDate) {
            $query->where(function (Builder $subQuery) {
                $subQuery
                    ->whereDate('talep_tarihi', '<=', $this->filterEndDate)
                    ->orWhere(function (Builder $innerQuery) {
                        $innerQuery->whereNull('talep_tarihi')->whereDate('created_at', '<=', $this->filterEndDate);
                    });
            });
        }

        if ($this->filterCargoCompany !== '') {
            $query->where('cargo_company', $this->filterCargoCompany);
        }

        if ($this->filterStatus !== '') {
            $query->where('durum', $this->filterStatus);
        }

        if ($this->filterReason !== '') {
            $query->where('sebep', $this->filterReason);
        }

        if ($this->filterPriority !== '' && $this->supportsLifecycleFields()) {
            $query->where('priority', $this->filterPriority);
        }

        if ($this->filterMarketplace !== '') {
            $query->whereHas('cargoReportItem', fn (Builder $itemQuery) => $itemQuery->where('pazaryeri', $this->filterMarketplace));
        }

        if ($this->filterStore !== '') {
            $query->whereHas('cargoReportItem', fn (Builder $itemQuery) => $itemQuery->where('magaza', $this->filterStore));
        }

        if ($this->filterRecordType === 'siparis') {
            $query->whereHas('cargoReportItem', fn (Builder $itemQuery) => $itemQuery->where('is_iade', false)->where('is_parca_gonderi', false));
        } elseif ($this->filterRecordType === 'iade') {
            $query->whereHas('cargoReportItem', fn (Builder $itemQuery) => $itemQuery->where('is_iade', true));
        } elseif ($this->filterRecordType === 'parca') {
            $query->whereHas('cargoReportItem', fn (Builder $itemQuery) => $itemQuery->where('is_parca_gonderi', true));
        }

        if ($this->search !== '') {
            $query->where(function (Builder $subQuery) {
                $subQuery
                    ->where('musteri_adi', 'like', '%' . $this->search . '%')
                    ->orWhere('takip_kodu', 'like', '%' . $this->search . '%')
                    ->orWhere('kargo_referans_no', 'like', '%' . $this->search . '%');

                if ($this->supportsLifecycleFields()) {
                    $subQuery->orWhere('carrier_case_no', 'like', '%' . $this->search . '%');
                }
            });
        }

        return $query;
    }

    /**
     * Son 10 aksiyon kaydı
     */
    #[Computed]
    public function recentErrors()
    {
        return $this->filteredErrorsQuery()
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
    }

    /**
     * Son 10 tazmin
     */
    #[Computed]
    public function recentCompensations()
    {
        return $this->filteredCompensationsQuery()
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();
    }

    /**
     * Tüm hatalar (paginated & search)
     */
    #[Computed]
    public function allErrors()
    {
        return $this->filteredErrorsQuery()
            ->orderBy($this->errorSortField, $this->errorSortDirection)
            ->orderByDesc('created_at')
            ->paginate(20);
    }

    /**
     * Tüm tazminler (paginated & search)
     */
    #[Computed]
    public function allCompensations()
    {
        return $this->filteredCompensationsQuery()
            ->orderBy($this->compensationSortField, $this->compensationSortDirection)
            ->orderByDesc('created_at')
            ->paginate(20);
    }
    
    /**
     * Tazmin oluştur
     */
    public function createCompensation()
    {
        $this->validate([
            'newCompensation.tarih' => 'required|date',
            'newCompensation.musteri_adi' => 'required|string|max:255',
            'newCompensation.sebep' => 'required',
            'newCompensation.talep_tutari' => 'required|numeric|min:0',
            'newCompensation.priority' => 'required|in:low,normal,high,critical',
            'newCompensation.responsible_user_id' => 'nullable|exists:users,id',
            'newCompensation.next_action_at' => 'nullable|date',
            'attachments.*' => 'nullable|image|max:5120', // 5MB max
        ]);

        try {
            // Dosyaları yükle
            $attachmentPaths = [];
            if (!empty($this->attachments)) {
                foreach ($this->attachments as $file) {
                    $path = $file->store('compensations', 'public');
                    $attachmentPaths[] = $path;
                }
            }

            $payload = [
                'user_id' => auth()->id(),
                'tarih' => $this->newCompensation['tarih'],
                'musteri_adi' => $this->newCompensation['musteri_adi'],
                'takip_kodu' => $this->newCompensation['takip_kodu'],
                'urun_adi' => $this->newCompensation['urun_adi'],
                'stok_kodu' => $this->newCompensation['stok_kodu'] ?? null,
                'cargo_company' => $this->newCompensation['cargo_company'],
                'sebep' => $this->newCompensation['sebep'],
                'aciklama' => $this->newCompensation['aciklama'],
                'talep_tutari' => $this->newCompensation['talep_tutari'],
                'durum' => 'beklemede',
                'talep_tarihi' => $this->newCompensation['tarih'],
            ];

            if (!$this->editingCompensationId) {
                $payload['cargo_report_item_id'] = $this->newCompensation['cargo_report_item_id'] ?? null;
            }

            if ($this->supportsLifecycleFields()) {
                $payload = array_merge($payload, [
                    'internal_note' => $this->newCompensation['internal_note'],
                    'priority' => $this->newCompensation['priority'] ?: 'normal',
                    'responsible_user_id' => $this->newCompensation['responsible_user_id'] ?: auth()->id(),
                    'next_action_at' => $this->newCompensation['next_action_at'] ?: null,
                    'carrier_case_no' => $this->newCompensation['carrier_case_no'] ?: null,
                    'last_action_at' => now(),
                ]);
            }

            if ($this->editingCompensationId) {
                $compensation = Compensation::findOrFail($this->editingCompensationId);
                
                // Mevcut ekleri koru ve yenilerini ekle
                if (!empty($attachmentPaths)) {
                    $existingAttachments = $compensation->attachments ?? [];
                    $payload['attachments'] = array_merge($existingAttachments, $attachmentPaths);
                }

                $compensation->update($payload);
                $this->showMessage('Tazmin talebi başarıyla güncellendi.', 'success');
            } else {
                if ($this->supportsLifecycleFields()) {
                    $payload['first_action_at'] = now();
                }
                
                if (!empty($attachmentPaths)) {
                    $payload['attachments'] = $attachmentPaths;
                }

                Compensation::create($payload);
                $this->showMessage('Tazmin talebi ve görseller kaydedildi.', 'success');
            }

            $this->showCreateModal = false;
            $this->resetNewCompensation();

        } catch (\Exception $e) {
            $this->showMessage('Hata: ' . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Yeni tazmin formunu sıfırla
     */
    protected function resetNewCompensation()
    {
        $this->editingCompensationId = null;
        $this->newCompensation = [
            'tarih' => now()->format('Y-m-d'),
            'musteri_adi' => '',
            'takip_kodu' => '',
            'urun_adi' => '',
            'stok_kodu' => '',
            'cargo_company' => 'Sürat Kargo',
            'sebep' => 'desi_fazla',
            'aciklama' => '',
            'talep_tutari' => 0,
            'priority' => 'normal',
            'responsible_user_id' => auth()->id(),
            'next_action_at' => null,
            'carrier_case_no' => '',
            'internal_note' => '',
            'cargo_report_item_id' => null,
        ];
        $this->attachments = [];
    }

    /**
     * Tazmin istatistikleri
     */
    #[Computed]
    public function stats()
    {
        $query = $this->filteredCompensationsQuery();
        $totalClaimed = (clone $query)->sum('talep_tutari');
        $totalApproved = (clone $query)->sum('onaylanan_tutar');
        $collected = $this->supportsLifecycleFields()
            ? (clone $query)->sum('collected_amount')
            : 0;

        return [
            'total' => (clone $query)->count(),
            'pending' => (clone $query)->pending()->count(),
            'completed' => (clone $query)->completed()->count(),
            'total_claimed' => $totalClaimed,
            'total_approved' => $totalApproved,
            'collected' => $collected,
            'success_rate' => $this->calculateSuccessRate($query),
        ];
    }

    /**
     * Tazmin istatistikleri detaylı dağılımı (Grafik Paneli için)
     */
    #[Computed]
    public function statsBreakdown()
    {
        $comps = $this->filteredCompensationsQuery()->get();

        $statuses = [
            'beklemede' => $comps->whereIn('durum', ['beklemede', 'talep_edildi', 'inceleniyor', 'ek_belge_isteniyor'])->count(),
            'onaylandi' => $comps->whereIn('durum', ['onaylandi', 'odeme_bekleniyor', 'odendi'])->count(),
            'kismi_onay' => $comps->where('durum', 'kismi_onay')->count(),
            'reddedildi' => $comps->whereIn('durum', ['reddedildi', 'kapandi'])->count(),
        ];

        $reasons = [
            'kayip_urun' => $comps->whereIn('sebep', ['kayip_urun', 'iade_kayip'])->count(),
            'hasarli_urun' => $comps->where('sebep', 'hasarli_urun')->count(),
            'desi_fazla' => $comps->where('sebep', 'desi_fazla')->count(),
            'tutar_fazla' => $comps->where('sebep', 'tutar_fazla')->count(),
        ];
        
        $reasons['diger'] = $comps->count() - array_sum($reasons);
        if ($reasons['diger'] < 0 || $comps->count() === 0) $reasons['diger'] = 0;

        return [
            'total' => $comps->count(),
            'statuses' => $statuses,
            'reasons' => $reasons
        ];
    }

    #[Computed]
    public function financeSnapshot(): array
    {
        $query = $this->filteredCompensationsQuery();
        $claimed = (float) (clone $query)->sum('talep_tutari');
        $approved = (float) (clone $query)->sum('onaylanan_tutar');
        $collected = $this->supportsLifecycleFields()
            ? (float) (clone $query)->sum('collected_amount')
            : 0.0;
        $openRisk = max(0, $claimed - $collected);
        $collectible = max(0, $approved - $collected);

        $writeOff = (float) (clone $query)
            ->whereIn('durum', ['reddedildi', 'kapandi'])
            ->get()
            ->sum(fn (Compensation $compensation) => max(0, (float) $compensation->talep_tutari - (float) $compensation->onaylanan_tutar));

        $approvedComps = (clone $query)
            ->whereNotNull('talep_tarihi')
            ->whereNotNull('sonuc_tarihi')
            ->whereIn('durum', ['onaylandi', 'kismi_onay', 'odeme_bekleniyor', 'odendi'])
            ->get();

        $avgApprovalDays = round($approvedComps->avg(function (Compensation $compensation) {
            return $compensation->talep_tarihi?->diffInDays($compensation->sonuc_tarihi) ?? 0;
        }) ?? 0, 1);

        $avgPaymentDays = 0.0;
        if ($this->supportsLifecycleFields()) {
            $paidComps = (clone $query)
                ->whereNotNull('talep_tarihi')
                ->whereNotNull('payment_date')
                ->where('durum', 'odendi')
                ->get();

            $avgPaymentDays = round($paidComps->avg(function (Compensation $compensation) {
                return $compensation->talep_tarihi?->diffInDays($compensation->payment_date) ?? 0;
            }) ?? 0, 1);
        }

        return [
            'claimed' => $claimed,
            'approved' => $approved,
            'collected' => $collected,
            'open_risk' => $openRisk,
            'collectible' => $collectible,
            'write_off' => $writeOff,
            'avg_approval_days' => $avgApprovalDays,
            'avg_payment_days' => $avgPaymentDays,
        ];
    }

    #[Computed]
    public function agingBuckets(): array
    {
        $pendingCompensations = $this->filteredCompensationsQuery()
            ->pending()
            ->get();

        $buckets = [
            '0_7' => ['label' => '0-7 gün', 'count' => 0, 'amount' => 0],
            '8_15' => ['label' => '8-15 gün', 'count' => 0, 'amount' => 0],
            '16_30' => ['label' => '16-30 gün', 'count' => 0, 'amount' => 0],
            '30_plus' => ['label' => '30+ gün', 'count' => 0, 'amount' => 0],
        ];

        foreach ($pendingCompensations as $compensation) {
            $days = $compensation->aging_days;
            $key = $days <= 7 ? '0_7' : ($days <= 15 ? '8_15' : ($days <= 30 ? '16_30' : '30_plus'));
            $buckets[$key]['count']++;
            $buckets[$key]['amount'] += (float) $compensation->open_risk;
        }

        return $buckets;
    }

    #[Computed]
    public function financeWaterfall(): array
    {
        $finance = $this->financeSnapshot;

        return [
            ['label' => 'Tespit edilen talep', 'amount' => $finance['claimed']],
            ['label' => 'Onaylanan', 'amount' => $finance['approved']],
            ['label' => 'Tahsil edilen', 'amount' => $finance['collected']],
            ['label' => 'Tahsilat bekleyen', 'amount' => $finance['collectible']],
            ['label' => 'Kayıp yazılan', 'amount' => $finance['write_off']],
        ];
    }

    #[Computed]
    public function carrierPerformance()
    {
        return $this->filteredCompensationsQuery()
            ->select('cargo_company')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(talep_tutari) as claimed')
            ->selectRaw('SUM(onaylanan_tutar) as approved')
            ->selectRaw($this->supportsLifecycleFields() ? 'SUM(collected_amount) as collected' : '0 as collected')
            ->groupBy('cargo_company')
            ->orderByDesc('claimed')
            ->get()
            ->map(function ($row) {
                $claimed = (float) $row->claimed;
                $approved = (float) $row->approved;
                $collected = (float) $row->collected;

                return [
                    'cargo_company' => $row->cargo_company ?: 'Belirtilmedi',
                    'total' => (int) $row->total,
                    'claimed' => $claimed,
                    'approved' => $approved,
                    'collected' => $collected,
                    'approval_rate' => $claimed > 0 ? round(($approved / $claimed) * 100, 1) : 0,
                    'collection_rate' => $approved > 0 ? round(($collected / $approved) * 100, 1) : 0,
                ];
            });
    }

    /**
     * Başarı oranı hesapla
     */
    protected function calculateSuccessRate(?Builder $baseQuery = null): float
    {
        $query = $baseQuery ? clone $baseQuery : $this->filteredCompensationsQuery();
        $completed = (clone $query)->completed()->count();
        if ($completed === 0) return 0;

        $successful = (clone $query)->whereIn('durum', ['onaylandi', 'kismi_onay', 'odeme_bekleniyor', 'odendi'])->count();
        return round(($successful / $completed) * 100, 1);
    }



    /**
     * Tazmin oluştur modalını aç
     */
    #[On('cargo-comp-open-create')]
    public function openCreateModal(?int $errorItemId = null)
    {
        $this->resetNewCompensation();

        // Hata itemından oluşturuluyorsa bilgileri doldur
        if ($errorItemId) {
            $item = CargoReportItem::with('cargoReport')->find($errorItemId);
            if ($item) {
                $this->newCompensation = [
                    'tarih' => $item->tarih?->format('Y-m-d') ?? now()->format('Y-m-d'),
                    'musteri_adi' => $item->musteri_adi,
                    'takip_kodu' => $item->takip_kodu,
                    'urun_adi' => $item->urun_adi,
                    'stok_kodu' => $item->stok_kodu,
                    'cargo_company' => $item->cargoReport?->cargo_company ?? 'Sürat Kargo',
                    'sebep' => $this->mapErrorTypeToSebep($item->error_type),
                    'aciklama' => '',
                    'talep_tutari' => abs($item->tutar_fark),
                    'priority' => abs((float) $item->tutar_fark) >= 500 ? 'critical' : (abs((float) $item->tutar_fark) >= 150 ? 'high' : 'normal'),
                    'responsible_user_id' => auth()->id(),
                    'next_action_at' => now()->addDays(3)->format('Y-m-d'),
                    'carrier_case_no' => '',
                    'internal_note' => '',
                    'cargo_report_item_id' => $item->id,
                ];
            }
        }

        $this->showCreateModal = true;
    }

    /**
     * Tazmin düzenle modalını aç
     */
    public function editCompensation(int $id)
    {
        $compensation = Compensation::findOrFail($id);
        
        $this->resetNewCompensation();
        $this->editingCompensationId = $compensation->id;
        
        $this->newCompensation = [
            'tarih' => $compensation->tarih?->format('Y-m-d') ?? now()->format('Y-m-d'),
            'musteri_adi' => $compensation->musteri_adi,
            'takip_kodu' => $compensation->takip_kodu ?? '',
            'urun_adi' => $compensation->urun_adi ?? '',
            'stok_kodu' => $compensation->stok_kodu ?? '',
            'cargo_company' => $compensation->cargo_company,
            'sebep' => $compensation->sebep,
            'aciklama' => $compensation->aciklama ?? '',
            'talep_tutari' => $compensation->talep_tutari,
            'cargo_report_item_id' => $compensation->cargo_report_item_id,
        ];
        
        if ($this->supportsLifecycleFields()) {
            $this->newCompensation['priority'] = $compensation->priority ?: 'normal';
            $this->newCompensation['responsible_user_id'] = $compensation->responsible_user_id;
            $this->newCompensation['next_action_at'] = $compensation->next_action_at?->format('Y-m-d');
            $this->newCompensation['carrier_case_no'] = $compensation->carrier_case_no ?? '';
            $this->newCompensation['internal_note'] = $compensation->internal_note ?? '';
        }

        $this->showCreateModal = true;
    }

    /**
     * Tazmin talebini sil
     */
    public function deleteCompensation(int $id)
    {
        try {
            $compensation = Compensation::findOrFail($id);
            
            // Ekleri sil (storage'dan)
            if (!empty($compensation->attachments)) {
                foreach ($compensation->attachments as $attachment) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($attachment);
                }
            }
            
            $compensation->delete();
            $this->showMessage('Tazmin talebi başarıyla silindi.', 'success');
            $this->resetPage();
        } catch (\Exception $e) {
            $this->showMessage('Tazmin talebi silinirken bir hata oluştu: ' . $e->getMessage(), 'error');
        }
    }

    /**
     * Hata tipini tazmin sebebine çevir
     */
    protected function mapErrorTypeToSebep(string $errorType): string
    {
        return match($errorType) {
            'desi_fazla' => 'desi_fazla',
            'tutar_fazla' => 'tutar_fazla',
            'parca_eksik' => 'kayip_urun',
            'parca_fazla' => 'diger',
            'referans_eksik' => 'diger',
            'eslesmedi' => 'yanlis_teslim',
            default => 'diger',
        };
    }

    /**
     * Durum güncelleme modalını aç
     */
    #[On('cargo-comp-open-status')]
    public function openStatusModal(int $id)
    {
        $compensation = Compensation::find($id);
        if (!$compensation) return;

        $this->updatingCompensationId = $id;
        $this->newStatus = $compensation->durum;
        $this->onaylananTutar = (float) ($compensation->onaylanan_tutar ?? 0);

        if ($this->supportsLifecycleFields()) {
            $this->collectedAmount = (float) ($compensation->collected_amount ?? 0);
            $this->paymentDate = $compensation->payment_date?->format('Y-m-d');
            $this->nextActionAt = $compensation->next_action_at?->format('Y-m-d');
            $this->responsibleUserId = $compensation->responsible_user_id;
            $this->priority = $compensation->priority ?: 'normal';
            $this->carrierCaseNo = $compensation->carrier_case_no ?? '';
            $this->internalNote = $compensation->internal_note ?? '';
            $this->resolutionNote = $compensation->resolution_note ?? '';
        } else {
            $this->collectedAmount = 0.0;
            $this->paymentDate = null;
            $this->nextActionAt = null;
            $this->responsibleUserId = null;
            $this->priority = 'normal';
            $this->carrierCaseNo = '';
            $this->internalNote = '';
            $this->resolutionNote = '';
        }
        $this->showStatusModal = true;
    }

    /**
     * Durumu güncelle
     */
    public function updateStatus()
    {
        if (!$this->updatingCompensationId) return;

        try {
            $compensation = Compensation::find($this->updatingCompensationId);
            if (!$compensation) return;

            $resolvedStatuses = ['onaylandi', 'kismi_onay', 'reddedildi', 'odeme_bekleniyor', 'odendi', 'kapandi'];
            $requiresPaymentDate = in_array($this->newStatus, ['odeme_bekleniyor', 'odendi'], true);

            $this->validate([
                'newStatus' => 'required|in:' . implode(',', array_keys(Compensation::DURUMLAR)),
                'onaylananTutar' => 'required|numeric|min:0',
                'collectedAmount' => 'nullable|numeric|min:0',
                'paymentDate' => $requiresPaymentDate ? 'required|date' : 'nullable|date',
                'nextActionAt' => 'nullable|date',
                'responsibleUserId' => 'nullable|exists:users,id',
                'priority' => 'required|in:low,normal,high,critical',
            ]);

            $payload = [
                'durum' => $this->newStatus,
                'onaylanan_tutar' => $this->onaylananTutar,
                'sonuc_tarihi' => in_array($this->newStatus, $resolvedStatuses, true)
                    ? now()->toDateString()
                    : null,
            ];

            if ($this->supportsLifecycleFields()) {
                $payload = array_merge($payload, [
                    'collected_amount' => $this->collectedAmount,
                    'payment_date' => $this->paymentDate ?: null,
                    'next_action_at' => $this->nextActionAt ?: null,
                    'responsible_user_id' => $this->responsibleUserId,
                    'priority' => $this->priority,
                    'carrier_case_no' => $this->carrierCaseNo ?: null,
                    'internal_note' => $this->internalNote ?: null,
                    'resolution_note' => $this->resolutionNote ?: null,
                    'last_action_at' => now(),
                ]);
            }

            $compensation->update($payload);

            $this->showStatusModal = false;
            $this->updatingCompensationId = null;
            $this->showMessage('Durum güncellendi.', 'success');

        } catch (\Exception $e) {
            $this->showMessage('Hata: ' . $e->getMessage(), 'error');
        }
    }
    /**
     * AI ile dilekçe oluştur
     */
    public function generateAiPetition(int $id)
    {
        try {
            $compensation = Compensation::find($id);
            if (!$compensation) return;

            $aiService = new \App\Services\AIService();
            $dilekce = $aiService->generatePetitionText($compensation);

            // Veritabanına kaydet
            $compensation->update(['dilekce_icerigi' => $dilekce]);

            // Modalı güncelle
            $this->editingPetitionText = $dilekce;
            $this->showMessage('AI tarafından dilekçe taslağı oluşturuldu.', 'success');

        } catch (\Exception $e) {
            $this->showMessage('AI Hatası: ' . $e->getMessage(), 'error');
        }
    }

    /**
     * Dilekçe düzenleme modalını aç
     */
    #[On('cargo-comp-open-petition')]
    public function openPetitionModal(int $id)
    {
        $compensation = Compensation::find($id);
        if (!$compensation) return;

        $this->updatingCompensationId = $id;
        $this->editingPetitionText = $compensation->dilekce_icerigi ?? "Dilekçe içeriği henüz oluşturulmadı. 'AI ile Oluştur' butonuna basarak taslak oluşturabilirsiniz.";
        $this->showPetitionModal = true;
    }

    /**
     * Dilekçe içeriğini kaydet
     */
    public function savePetitionText()
    {
        if (!$this->updatingCompensationId) return;

        $compensation = Compensation::find($this->updatingCompensationId);
        if ($compensation) {
            $compensation->update(['dilekce_icerigi' => $this->editingPetitionText]);
            $this->showMessage('Dilekçe içeriği güncellendi.', 'success');
            $this->showPetitionModal = false;
        }
    }

    /**
     * Tazmin detayını görüntüle
     */
    public function viewCompensation(int $id)
    {
        $this->viewingCompensationId = $id;
        $this->showDetailModal = true;
    }

    /**
     * Görüntülenen tazmin
     */
    #[Computed]
    public function viewingCompensation()
    {
        if (!$this->viewingCompensationId) return null;
        return Compensation::find($this->viewingCompensationId);
    }

    /**
     * Ekleri Görüntüle
     */
    #[On('cargo-comp-view-attachments')]
    public function viewAttachments(int $id)
    {
        $comp = Compensation::find($id);
        if ($comp && !empty($comp->attachments)) {
            $this->viewingAttachments = $comp->attachments;
            $this->showAttachmentsModal = true;
        } else {
            $this->showMessage('Bu talebe ait görüntülecek ek/görsel bulunamadı.', 'warning');
        }
    }

    /**
     * Mesaj göster
     */
    protected function showMessage(string $message, string $type = 'info')
    {
        $this->message = $message;
        $this->messageType = $type;
    }

    public function sortErrors(string $columnKey): void
    {
        $field = static::$errorSortableColumns[$columnKey] ?? null;
        if (!$field) {
            return;
        }

        if ($this->errorSortField === $field) {
            $this->errorSortDirection = $this->errorSortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->errorSortField = $field;
            $this->errorSortDirection = 'desc';
        }

        $this->resetPage();
    }

    public function sortCompensations(string $columnKey): void
    {
        $field = static::$compensationSortableColumns[$columnKey] ?? null;
        if (!$field) {
            return;
        }

        if ($this->compensationSortField === $field) {
            $this->compensationSortDirection = $this->compensationSortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->compensationSortField = $field;
            $this->compensationSortDirection = 'desc';
        }

        $this->resetPage();
    }

    public function toggleErrorColumn(string $column): void
    {
        if (!array_key_exists($column, static::$errorColumnDefs)) {
            return;
        }

        if (in_array($column, $this->errorVisibleColumns, true)) {
            if (count($this->errorVisibleColumns) === 1) {
                return;
            }

            $this->errorVisibleColumns = array_values(array_diff($this->errorVisibleColumns, [$column]));
        } else {
            $this->errorVisibleColumns[] = $column;
            $this->errorVisibleColumns = $this->normalizeVisibleColumns(
                $this->errorVisibleColumns,
                array_keys(static::$errorColumnDefs),
                ['date', 'customer', 'product', 'wrong_desi', 'correct_desi', 'difference', 'actions']
            );
        }

        app(MpSettingsService::class)->set('cargo_reports.compensation.errors.visible_columns', $this->errorVisibleColumns);
    }

    public function toggleCompensationColumn(string $column): void
    {
        if (!array_key_exists($column, static::$compensationColumnDefs)) {
            return;
        }

        if (in_array($column, $this->compensationVisibleColumns, true)) {
            if (count($this->compensationVisibleColumns) === 1) {
                return;
            }

            $this->compensationVisibleColumns = array_values(array_diff($this->compensationVisibleColumns, [$column]));
        } else {
            $this->compensationVisibleColumns[] = $column;
            $this->compensationVisibleColumns = $this->normalizeVisibleColumns(
                $this->compensationVisibleColumns,
                array_keys(static::$compensationColumnDefs),
                ['date', 'customer', 'reason', 'claimed', 'approved', 'status', 'documents', 'actions']
            );
        }

        app(MpSettingsService::class)->set('cargo_reports.compensation.claims.visible_columns', $this->compensationVisibleColumns);
    }

    public function render()
    {
        return view('livewire.cargo.compensation-dashboard');
    }

    protected function normalizeVisibleColumns(array $columns, array $allowed, array $defaults): array
    {
        $normalized = array_values(array_intersect($allowed, $columns));

        return $normalized !== [] ? $normalized : $defaults;
    }

    protected function supportsLifecycleFields(): bool
    {
        static $supportsLifecycleFields;

        if ($supportsLifecycleFields === null) {
            $supportsLifecycleFields = Schema::hasColumns('compensations', [
                'responsible_user_id',
                'priority',
                'carrier_case_no',
                'collected_amount',
                'payment_date',
                'first_action_at',
                'last_action_at',
                'next_action_at',
                'internal_note',
                'resolution_note',
            ]);
        }

        return $supportsLifecycleFields;
    }
}
