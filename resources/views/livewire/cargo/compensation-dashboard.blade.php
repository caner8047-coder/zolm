@php
    $stats = $this->stats;
    $recentErrors = $this->recentErrors;
    $recentCompensations = $this->recentCompensations;
    $cargoCompanies = $this->cargoCompanies;
    $marketplaces = $this->marketplaces;
    $stores = $this->stores;
    $assignableUsers = $this->assignableUsers;
    $errorColumnDefs = \App\Livewire\Cargo\CompensationDashboard::$errorColumnDefs;
    $errorSortableColumns = \App\Livewire\Cargo\CompensationDashboard::$errorSortableColumns;
    $compensationColumnDefs = \App\Livewire\Cargo\CompensationDashboard::$compensationColumnDefs;
    $compensationSortableColumns = \App\Livewire\Cargo\CompensationDashboard::$compensationSortableColumns;
    $crmLinks = app(\App\Services\Crm\CrmSourceLinkService::class);
    $crmSnapshots = app(\App\Services\Crm\CrmCustomerSnapshotService::class);

    $messageClasses = match ($messageType) {
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
        'error' => 'border-rose-200 bg-rose-50 text-rose-800',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-800',
        default => 'border-sky-200 bg-sky-50 text-sky-800',
    };

    $formatCount = fn ($value) => number_format((float) $value, 0, ',', '.');
    $formatMoney = function ($value) {
        $val = (float) $value;
        return '₺' . (floor($val) == $val ? number_format($val, 0, ',', '.') : number_format($val, 2, ',', '.'));
    };
    $formatSignedMoney = function ($value) {
        $val = (float) $value;
        $formatted = (floor(abs($val)) == abs($val) ? number_format(abs($val), 0, ',', '.') : number_format(abs($val), 2, ',', '.'));
        return (($val > 0) ? '+₺' : (($val < 0) ? '-₺' : '₺')) . $formatted;
    };
    $attachmentUrl = fn ($path) => \Illuminate\Support\Facades\Storage::disk('public')->url($path);

    $toneFromColor = fn ($color) => match ($color) {
        'green' => 'success',
        'yellow', 'orange' => 'warning',
        'red' => 'danger',
        'blue', 'purple' => 'info',
        default => 'default',
    };

    $errorSortIcon = function (string $columnKey) use ($errorSortableColumns, $errorSortField, $errorSortDirection) {
        $dbColumn = $errorSortableColumns[$columnKey] ?? null;
        if (!$dbColumn) {
            return '';
        }

        return $errorSortField === $dbColumn
            ? ($errorSortDirection === 'asc' ? '▲' : '▼')
            : '⇅';
    };
    $compensationSortIcon = function (string $columnKey) use ($compensationSortableColumns, $compensationSortField, $compensationSortDirection) {
        $dbColumn = $compensationSortableColumns[$columnKey] ?? null;
        if (!$dbColumn) {
            return '';
        }

        return $compensationSortField === $dbColumn
            ? ($compensationSortDirection === 'asc' ? '▲' : '▼')
            : '⇅';
    };
    $statusOptions = \App\Models\Compensation::DURUMLAR;
    $reasonOptions = \App\Models\Compensation::SEBEPLER;
    $priorityOptions = \App\Models\Compensation::PRIORITIES;
    $recordTypeLabels = [
        'all' => 'Tümü',
        'siparis' => 'Sipariş',
        'iade' => 'İade / değişim',
        'parca' => 'Parça gönderisi',
    ];
    $activeFilters = array_values(array_filter([
        $filterCargoCompany !== '' ? 'Firma: ' . $filterCargoCompany : null,
        $filterMarketplace !== '' ? 'Pazaryeri: ' . $filterMarketplace : null,
        $filterStore !== '' ? 'Mağaza: ' . $filterStore : null,
        ($filterRecordType ?? 'all') !== 'all' ? 'Kayıt: ' . ($recordTypeLabels[$filterRecordType] ?? $filterRecordType) : null,
        $filterStatus !== '' ? 'Durum: ' . ($statusOptions[$filterStatus]['label'] ?? $filterStatus) : null,
        $filterReason !== '' ? 'Sebep: ' . ($reasonOptions[$filterReason]['label'] ?? $filterReason) : null,
        $filterPriority !== '' ? 'Öncelik: ' . ($priorityOptions[$filterPriority]['label'] ?? $filterPriority) : null,
    ]));
    $listActiveFilters = array_values(array_filter([
        $filterCargoCompany !== '' ? 'Firma: ' . $filterCargoCompany : null,
        ($filterRecordType ?? 'all') !== 'all' ? 'Kayıt: ' . ($recordTypeLabels[$filterRecordType] ?? $filterRecordType) : null,
        $filterMarketplace !== '' ? 'Pazaryeri: ' . $filterMarketplace : null,
        $filterStore !== '' ? 'Mağaza: ' . $filterStore : null,
        $viewMode === 'all_compensations' && $filterStatus !== '' ? 'Durum: ' . ($statusOptions[$filterStatus]['label'] ?? $filterStatus) : null,
        $viewMode === 'all_compensations' && $filterReason !== '' ? 'Sebep: ' . ($reasonOptions[$filterReason]['label'] ?? $filterReason) : null,
        $viewMode === 'all_compensations' && $filterPriority !== '' ? 'Öncelik: ' . ($priorityOptions[$filterPriority]['label'] ?? $filterPriority) : null,
    ]));
    $showDashboardAdvancedFilters = ($filterRecordType ?? 'all') !== 'all'
        || $filterMarketplace !== ''
        || $filterStore !== ''
        || $filterReason !== ''
        || $filterPriority !== '';
    $showListAdvancedFilters = ($filterRecordType ?? 'all') !== 'all'
        || $filterMarketplace !== ''
        || $filterStore !== ''
        || ($viewMode === 'all_compensations' && ($filterReason !== '' || $filterPriority !== ''));
@endphp

<div class="w-full space-y-6 overflow-hidden">
    @once
        <style>
            .col-resize-handle { position: absolute; right: 0; top: 0; bottom: 0; width: 4px; cursor: col-resize; background: transparent; z-index: 10; transition: background 0.15s; }
            .col-resize-handle:hover, .col-resize-handle.active { background: #6366f1; }
            .sortable-th { cursor: pointer; user-select: none; position: relative; }
            .sortable-th:hover { background: #f8fafc; }
            #compensationErrorsTable .text-xs,
            #compensationClaimsTable .text-xs { font-size: 10px !important; }
            #compensationErrorsTable .text-sm,
            #compensationClaimsTable .text-sm { font-size: 12px !important; }
            #compensationErrorsTable .text-\[10px\],
            #compensationClaimsTable .text-\[10px\] { font-size: 8px !important; }
            #compensationErrorsTable,
            #compensationClaimsTable { table-layout: fixed; width: 100%; }
            #compensationErrorsTable th,
            #compensationErrorsTable td,
            #compensationClaimsTable th,
            #compensationClaimsTable td { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        </style>
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('columnResize', () => ({
                    resizing: false,
                    startX: 0,
                    startWidth: 0,
                    currentTh: null,
                    handle: null,
                    startResize(e, th) {
                        this.resizing = true;
                        this.startX = e.pageX;
                        this.currentTh = th;
                        this.startWidth = th.offsetWidth;
                        this.handle = e.target;
                        this.handle.classList.add('active');

                        const onMouseMove = (ev) => {
                            if (!this.resizing) return;
                            const newWidth = Math.max(40, this.startWidth + (ev.pageX - this.startX));
                            this.currentTh.style.width = newWidth + 'px';
                            this.currentTh.style.minWidth = newWidth + 'px';
                        };

                        const onMouseUp = () => {
                            this.resizing = false;
                            if (this.handle) this.handle.classList.remove('active');
                            document.removeEventListener('mousemove', onMouseMove);
                            document.removeEventListener('mouseup', onMouseUp);
                        };

                        document.addEventListener('mousemove', onMouseMove);
                        document.addEventListener('mouseup', onMouseUp);
                    }
                }));
            });
        </script>
    @endonce

    @if($message)
        <div class="rounded-2xl border p-4 text-sm shadow-sm {{ $messageClasses }}">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 lg:gap-4">
                <p>{{ $message }}</p>
                <button type="button" wire:click="$set('message', '')" class="w-full sm:w-auto rounded-lg border border-current/20 bg-white/70 px-4 py-3 sm:py-2 text-sm font-medium transition hover:bg-white">
                    Kapat
                </button>
            </div>
        </div>
    @endif

    @if($viewMode === 'dashboard')
        <div class="flex flex-col xl:flex-row xl:items-start xl:justify-between gap-4 lg:gap-6">
            <div class="max-w-3xl">
                <h2 class="text-xl lg:text-2xl font-bold text-slate-900">Kargo tazmin merkezi</h2>
                <p class="mt-1 text-sm lg:text-base text-slate-700">
                    Açık dosyaları, talep tutarını ve tahsilat sonucunu tek ekranda izleyin.
                </p>
            </div>

            <div class="flex w-full xl:w-auto flex-col sm:flex-row gap-3">
                <x-zolm.primary-button color="indigo" compact wire:click="openCreateModal">Yeni Talep</x-zolm.primary-button>
                <button wire:click="showAllErrors" class="min-h-[44px] w-full sm:w-auto rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                    Tüm Hatalar
                </button>
                <button wire:click="showAllCompensations" class="min-h-[44px] w-full sm:w-auto rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                    Tüm Talepler
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4">
            <x-zolm.stat-card variant="orders" label="Açık dosya" :value="$formatCount($stats['pending'])" tone="warning" description="Yanıt bekleyen veya işlemde olan talepler">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </x-slot:icon>
            </x-zolm.stat-card>
            <x-zolm.stat-card variant="orders" label="Sonuçlanan" :value="$formatCount($stats['completed'])" tone="success" description="Onaylanan, ödenen veya kapanan dosyalar">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </x-slot:icon>
            </x-zolm.stat-card>
            <x-zolm.stat-card variant="orders" label="Onaylanan" :value="$formatMoney($stats['total_approved'])" tone="success" description="Geri kazanılan toplam tutar">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
                    </svg>
                </x-slot:icon>
            </x-zolm.stat-card>
            <x-zolm.stat-card variant="orders" label="Tahsil edilen" :value="$formatMoney($stats['collected'])" tone="info" description="Fiilen hesabınıza geçen tutar">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a5 5 0 00-10 0v2m-2 0h14l-1 10H6L5 9z" />
                    </svg>
                </x-slot:icon>
            </x-zolm.stat-card>
        </div>

        <x-zolm.section-card variant="orders" padding="p-4 lg:p-5">
            <div class="space-y-4">
                <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-3 lg:gap-4">
                    <div>
                        <h3 class="text-base lg:text-lg font-semibold text-slate-900">Tazmin filtreleri</h3>
                        <p class="mt-1 text-sm text-slate-500">
                            Dosyaları firma, durum, sebep ve kayıt tipine göre daraltın.
                        </p>
                    </div>
                    <p class="text-sm text-slate-500">
                        {{ count($activeFilters) > 0 ? implode(' · ', $activeFilters) : 'Son 30 günün tüm talepleri ve hataları gösteriliyor.' }}
                        · {{ $filterStartDate ? \Carbon\Carbon::parse($filterStartDate)->format('d.m.Y') : '-' }} - {{ $filterEndDate ? \Carbon\Carbon::parse($filterEndDate)->format('d.m.Y') : '-' }}
                    </p>
                </div>

                <div x-data="{ showAdvanced: @js($showDashboardAdvancedFilters) }" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 lg:gap-4">
                        <div>
                            <label class="block text-xs sm:text-sm font-medium text-slate-500">Başlangıç</label>
                            <input type="date" wire:model.live="filterStartDate" class="mt-1 min-h-[44px] w-full rounded-md border border-slate-300 px-3 py-2 text-base sm:text-sm text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                        </div>
                        <div>
                            <label class="block text-xs sm:text-sm font-medium text-slate-500">Bitiş</label>
                            <input type="date" wire:model.live="filterEndDate" class="mt-1 min-h-[44px] w-full rounded-md border border-slate-300 px-3 py-2 text-base sm:text-sm text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                        </div>
                        <div>
                            <label class="block text-xs sm:text-sm font-medium text-slate-500">Kargo firması</label>
                            <select wire:model.live="filterCargoCompany" class="mt-1 min-h-[44px] w-full rounded-md border border-slate-300 px-3 py-2 text-base sm:text-sm text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                <option value="">Tümü</option>
                                @foreach($cargoCompanies as $company)
                                    <option value="{{ $company }}">{{ $company }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs sm:text-sm font-medium text-slate-500">Durum</label>
                            <select wire:model.live="filterStatus" class="mt-1 min-h-[44px] w-full rounded-md border border-slate-300 px-3 py-2 text-base sm:text-sm text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                <option value="">Tümü</option>
                                @foreach($statusOptions as $statusKey => $statusInfo)
                                    <option value="{{ $statusKey }}">{{ $statusInfo['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <button type="button" @click="showAdvanced = !showAdvanced" class="min-h-[44px] w-full sm:w-auto rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                        <span x-text="showAdvanced ? 'Gelişmiş filtreleri gizle' : 'Gelişmiş filtreler'"></span>
                    </button>

                    <div x-show="showAdvanced" x-cloak x-transition.opacity.duration.150ms class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3 lg:gap-4">
                            <div>
                                <label class="block text-xs sm:text-sm font-medium text-slate-500">Kayıt tipi</label>
                                <select wire:model.live="filterRecordType" class="mt-1 min-h-[44px] w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-base sm:text-sm text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                    <option value="all">Tümü</option>
                                    <option value="siparis">Sipariş</option>
                                    <option value="iade">İade / değişim</option>
                                    <option value="parca">Parça gönderisi</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs sm:text-sm font-medium text-slate-500">Pazaryeri</label>
                                <select wire:model.live="filterMarketplace" class="mt-1 min-h-[44px] w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-base sm:text-sm text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                    <option value="">Tümü</option>
                                    @foreach($marketplaces as $marketplace)
                                        <option value="{{ $marketplace }}">{{ $marketplace }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs sm:text-sm font-medium text-slate-500">Mağaza</label>
                                <select wire:model.live="filterStore" class="mt-1 min-h-[44px] w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-base sm:text-sm text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                    <option value="">Tümü</option>
                                    @foreach($stores as $store)
                                        <option value="{{ $store }}">{{ $store }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs sm:text-sm font-medium text-slate-500">Sebep</label>
                                <select wire:model.live="filterReason" class="mt-1 min-h-[44px] w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-base sm:text-sm text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                    <option value="">Tümü</option>
                                    @foreach($reasonOptions as $reasonKey => $reasonInfo)
                                        <option value="{{ $reasonKey }}">{{ $reasonInfo['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs sm:text-sm font-medium text-slate-500">Öncelik</label>
                                <select wire:model.live="filterPriority" class="mt-1 min-h-[44px] w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-base sm:text-sm text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                    <option value="">Tümü</option>
                                    @foreach($priorityOptions as $priorityKey => $priorityInfo)
                                        <option value="{{ $priorityKey }}">{{ $priorityInfo['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </x-zolm.section-card>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 lg:gap-8">
            <x-zolm.section-card variant="orders" eyebrow="Son Hatalar" title="İncelenecek aksiyon kayıtları" description="Talep, düzeltme veya manuel inceleme gerektiren son kayıtlar." padding="p-4 lg:p-6">
                <div class="space-y-3">
                    @forelse($recentErrors as $error)
                        @php
                            $isClaimable = in_array($error->error_type, \App\Models\CargoReportItem::CLAIMABLE_ERROR_TYPES, true);
                        @endphp
                        <div class="rounded-3xl border border-slate-200 bg-white p-4">
                            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 lg:gap-4">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                        <span>{{ $error->tarih?->format('d.m.Y') }}</span>
                                        <x-zolm.status-badge :tone="$error->tutar_fark > 0 ? 'danger' : 'success'">
                                            Fark {{ $formatSignedMoney($error->tutar_fark) }}
                                        </x-zolm.status-badge>
                                    </div>
                                    <p class="mt-2 truncate text-sm font-semibold text-slate-900">{{ $error->musteri_adi }}</p>
                                    <p class="mt-1 truncate text-sm text-slate-500">{{ $error->urun_adi ?: 'Ürün bilgisi yok' }}</p>

                                    <div class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-2 lg:gap-3">
                                        <div class="rounded-[8px] border border-slate-200 bg-slate-50 px-3 py-3 text-sm">
                                            <p class="text-slate-500">Yanlış desi</p>
                                            <p class="mt-1 font-semibold text-rose-600">{{ number_format((float) $error->gercek_desi, 0, ',', '.') }}</p>
                                        </div>
                                        <div class="rounded-[8px] border border-slate-200 bg-slate-50 px-3 py-3 text-sm">
                                            <p class="text-slate-500">Doğru desi</p>
                                            <p class="mt-1 font-semibold text-emerald-600">{{ number_format((float) $error->beklenen_desi, 0, ',', '.') }}</p>
                                        </div>
                                        <div class="rounded-[8px] border border-slate-200 bg-slate-50 px-3 py-3 text-sm">
                                            <p class="text-slate-500">Talep potansiyeli</p>
                                            <p class="mt-1 font-semibold {{ $error->tutar_fark > 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ $formatSignedMoney($error->tutar_fark) }}</p>
                                        </div>
                                    </div>
                                </div>

                                @if($isClaimable)
                                    <button wire:click="openCreateModal({{ $error->id }})" class="w-full sm:w-auto rounded-lg border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                        Talep Aç
                                    </button>
                                @else
                                    <button type="button" disabled class="w-full sm:w-auto rounded-lg border border-slate-200 bg-slate-100 px-4 py-3 sm:py-2 text-sm font-medium text-slate-400">
                                        Düzeltme Gerekli
                                    </button>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center text-sm text-slate-500">
                            Seçili filtrelerde talebe veya aksiyona uygun hata kaydı bulunmuyor.
                        </div>
                    @endforelse
                </div>

                @if($recentErrors->isNotEmpty())
                    <div class="mt-4">
                        <button wire:click="showAllErrors" class="w-full sm:w-auto rounded-lg border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                            Tüm hata kayıtlarını aç
                        </button>
                    </div>
                @endif
            </x-zolm.section-card>

            <x-zolm.section-card variant="orders" eyebrow="Son Talepler" title="Açılan tazmin dosyaları" description="En son oluşturulan talepler ve işlem durumları." padding="p-4 lg:p-6">
                <div class="space-y-3">
                    @forelse($recentCompensations as $comp)
                        @php
                            $sebepInfo = $comp->sebep_info;
                            $durumInfo = $comp->durum_info;
                        @endphp
                        <div class="rounded-3xl border border-slate-200 bg-white p-4">
                            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 lg:gap-4">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                        <span>{{ $comp->tarih->format('d.m.Y') }}</span>
                                        <x-zolm.status-badge :tone="$toneFromColor($durumInfo['color'])">{{ $durumInfo['label'] }}</x-zolm.status-badge>
                                        <x-zolm.status-badge :tone="$toneFromColor($sebepInfo['color'])">{{ $sebepInfo['label'] }}</x-zolm.status-badge>
                                    </div>
                                    <p class="mt-2 truncate text-sm font-semibold text-slate-900">{{ $comp->musteri_adi }}</p>
                                    <p class="mt-1 text-sm text-slate-500">{{ $comp->cargo_company ?: 'Kargo firması yok' }}{{ $comp->takip_kodu ? ' · ' . $comp->takip_kodu : '' }}</p>

                                    <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2 lg:gap-3">
                                        <div class="rounded-[8px] border border-slate-200 bg-slate-50 px-3 py-3 text-sm">
                                            <p class="text-slate-500">Talep edilen</p>
                                            <p class="mt-1 font-semibold text-slate-900">{{ $formatMoney($comp->talep_tutari) }}</p>
                                        </div>
                                        <div class="rounded-[8px] border border-slate-200 bg-slate-50 px-3 py-3 text-sm">
                                            <p class="text-slate-500">Onaylanan</p>
                                            <p class="mt-1 font-semibold text-emerald-600">{{ $formatMoney($comp->onaylanan_tutar) }}</p>
                                        </div>
                                        <div class="rounded-[8px] border border-slate-200 bg-slate-50 px-3 py-3 text-sm">
                                            <p class="text-slate-500">Sorumlu</p>
                                            <p class="mt-1 font-semibold text-slate-900">{{ $comp->responsibleUser?->name ?? 'Atanmadı' }}</p>
                                        </div>
                                        <div class="rounded-[8px] border border-slate-200 bg-slate-50 px-3 py-3 text-sm">
                                            <p class="text-slate-500">Sonraki aksiyon</p>
                                            <p class="mt-1 font-semibold text-slate-900">{{ $comp->next_action_at?->format('d.m.Y') ?? 'Planlanmadı' }}</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex w-full sm:w-auto flex-col gap-2">
                                    <button wire:click="openStatusModal({{ $comp->id }})" class="w-full rounded-lg border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                        Durum Güncelle
                                    </button>
                                    <button wire:click="openPetitionModal({{ $comp->id }})" class="w-full rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 sm:py-2 text-sm font-medium text-sky-700 transition hover:bg-sky-100">
                                        Dilekçe Düzenle
                                    </button>
                                    @if(!empty($comp->attachments))
                                        <button wire:click="viewAttachments({{ $comp->id }})" class="w-full rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 sm:py-2 text-sm font-medium text-emerald-700 transition hover:bg-emerald-100">
                                            Ekleri Gör
                                        </button>
                                    @endif
                                    <button wire:click="editCompensation({{ $comp->id }})" class="w-full rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 sm:py-2 text-sm font-medium text-amber-700 transition hover:bg-amber-100">
                                        Talebi Düzenle
                                    </button>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center text-sm text-slate-500">
                            Henüz açılmış tazmin talebi yok.
                        </div>
                    @endforelse
                </div>

                @if($recentCompensations->isNotEmpty())
                    <div class="mt-4">
                        <button wire:click="showAllCompensations" class="w-full sm:w-auto rounded-lg border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                            Tüm talepleri aç
                        </button>
                    </div>
                @endif
            </x-zolm.section-card>
        </div>
    @else
        <x-zolm.section-card variant="orders" padding="p-4 lg:p-6">
            <div class="flex flex-col xl:flex-row xl:items-end xl:justify-between gap-4 lg:gap-6">
                <div class="max-w-3xl">
                    <div class="flex items-center gap-3">
                        <button wire:click="backToDashboard" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50 hover:text-slate-900">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                        </button>
                        <div>
                            <x-zolm.eyebrow variant="classic">Liste Görünümü</x-zolm.eyebrow>
                            <h2 class="mt-3 text-xl lg:text-2xl font-bold text-slate-900">
                                {{ $viewMode === 'all_errors' ? 'Tüm aksiyon kayıtları' : 'Tüm tazmin talepleri' }}
                            </h2>
                            <p class="mt-2 text-sm lg:text-base text-slate-500">
                                {{ $viewMode === 'all_errors' ? 'Tespit edilen fark, eksik ve operasyon kayıtlarını arayın; uygun olanları talebe dönüştürün.' : 'Açılan talepleri, belgeleri ve sonuç tutarlarını aynı listede yönetin.' }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="w-full xl:max-w-md">
                    <label class="text-xs sm:text-sm font-medium text-slate-700">Arama</label>
                    <div class="relative mt-1">
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="search"
                            placeholder="Müşteri, takip kodu veya ürün ara..."
                            class="w-full rounded-2xl border border-slate-200 bg-white pl-11 pr-4 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200"
                        >
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 21l-4.35-4.35m1.85-5.15a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
        </x-zolm.section-card>

        <x-zolm.section-card
            variant="orders"
            eyebrow="Liste Filtreleri"
            title="Detay segmentasyonu"
            description="Arama, durum ve kanal filtrelerini listede birlikte kullanın."
            headerPadding="px-4 pt-4 pb-2 lg:px-6 lg:pt-6 lg:pb-2"
            bodyPadding="px-4 pb-4 lg:px-6 lg:pb-6"
        >
            <div x-data="{ showAdvanced: @js($showListAdvancedFilters) }" class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 lg:gap-4">
                    <div>
                        <label class="text-xs sm:text-sm font-medium text-slate-700">Başlangıç</label>
                        <input type="date" wire:model.live="filterStartDate" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                    </div>
                    <div>
                        <label class="text-xs sm:text-sm font-medium text-slate-700">Bitiş</label>
                        <input type="date" wire:model.live="filterEndDate" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                    </div>
                    <div>
                        <label class="text-xs sm:text-sm font-medium text-slate-700">Kargo firması</label>
                        <select wire:model.live="filterCargoCompany" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                            <option value="">Tümü</option>
                            @foreach($cargoCompanies as $company)
                                <option value="{{ $company }}">{{ $company }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if($viewMode === 'all_compensations')
                        <div>
                            <label class="text-xs sm:text-sm font-medium text-slate-700">Durum</label>
                            <select wire:model.live="filterStatus" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                <option value="">Tümü</option>
                                @foreach(\App\Models\Compensation::DURUMLAR as $statusKey => $statusInfo)
                                    <option value="{{ $statusKey }}">{{ $statusInfo['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <button type="button" @click="showAdvanced = !showAdvanced" class="w-full sm:w-auto rounded-lg border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                        <span x-text="showAdvanced ? 'Gelişmiş filtreleri gizle' : 'Gelişmiş filtreler'"></span>
                    </button>
                    <p class="text-sm text-slate-500">
                        {{ count($listActiveFilters) > 0 ? implode(' · ', $listActiveFilters) : 'Temel liste filtreleri açık.' }}
                    </p>
                </div>

                <div x-show="showAdvanced" x-cloak x-transition.opacity.duration.150ms class="rounded-[8px] border border-dashed border-slate-200 bg-slate-50/80 p-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3 lg:gap-4">
                        <div>
                            <label class="text-xs sm:text-sm font-medium text-slate-700">Kayıt tipi</label>
                            <select wire:model.live="filterRecordType" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                <option value="all">Tümü</option>
                                <option value="siparis">Sipariş</option>
                                <option value="iade">İade / değişim</option>
                                <option value="parca">Parça gönderisi</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs sm:text-sm font-medium text-slate-700">Pazaryeri</label>
                            <select wire:model.live="filterMarketplace" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                <option value="">Tümü</option>
                                @foreach($marketplaces as $marketplace)
                                    <option value="{{ $marketplace }}">{{ $marketplace }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-xs sm:text-sm font-medium text-slate-700">Mağaza</label>
                            <select wire:model.live="filterStore" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                <option value="">Tümü</option>
                                @foreach($stores as $store)
                                    <option value="{{ $store }}">{{ $store }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if($viewMode === 'all_compensations')
                            <div>
                                <label class="text-xs sm:text-sm font-medium text-slate-700">Sebep</label>
                                <select wire:model.live="filterReason" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                    <option value="">Tümü</option>
                                    @foreach(\App\Models\Compensation::SEBEPLER as $reasonKey => $reasonInfo)
                                        <option value="{{ $reasonKey }}">{{ $reasonInfo['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="text-xs sm:text-sm font-medium text-slate-700">Öncelik</label>
                                <select wire:model.live="filterPriority" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                    <option value="">Tümü</option>
                                    @foreach(\App\Models\Compensation::PRIORITIES as $priorityKey => $priorityInfo)
                                        <option value="{{ $priorityKey }}">{{ $priorityInfo['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </x-zolm.section-card>

        @if($viewMode === 'all_errors')
            <x-zolm.section-card
                variant="orders"
                eyebrow="Hata Listesi"
                title="Fark tespit edilen kayıtlar"
                description="Müşteri, ürün ve fark tutarına göre tüm kayıtlar."
                headerPadding="px-4 pt-4 pb-2 lg:px-6 lg:pt-6 lg:pb-2"
                bodyPadding="px-4 pb-4 lg:px-6 lg:pb-6"
            >
                @php
                    $errorColumnMeta = [
                        'date' => ['width' => '88px', 'align' => 'text-left'],
                        'customer' => ['width' => '168px', 'align' => 'text-left'],
                        'product' => ['width' => '208px', 'align' => 'text-left'],
                        'wrong_desi' => ['width' => '80px', 'align' => 'text-center'],
                        'correct_desi' => ['width' => '80px', 'align' => 'text-center'],
                        'difference' => ['width' => '96px', 'align' => 'text-right'],
                        'actions' => ['width' => '88px', 'align' => 'text-right'],
                    ];
                @endphp

                <div class="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 lg:gap-4">
                    <div class="text-xs text-slate-500">{{ count($errorVisibleColumns) }} / {{ count($errorColumnDefs) }} kolon gösteriliyor</div>
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" type="button" class="w-full sm:w-auto rounded-lg border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                            Kolonlar
                        </button>
                        <div x-show="open" @click.outside="open = false" x-transition class="absolute right-0 top-full z-30 mt-2 w-60 rounded-[10px] border border-slate-200 bg-white p-3 shadow-xl">
                            <p class="text-xs font-medium uppercase tracking-[0.16em] text-slate-500">Hata Kolonları</p>
                            <div class="mt-3 space-y-1.5">
                                @foreach($errorColumnDefs as $colKey => $colLabel)
                                    <label class="flex items-center gap-2 rounded-lg px-2 py-2 text-sm text-slate-700 transition hover:bg-slate-50">
                                        <input type="checkbox" wire:click="toggleErrorColumn('{{ $colKey }}')" {{ in_array($colKey, $errorVisibleColumns, true) ? 'checked' : '' }} class="rounded border-slate-300 text-slate-900 shadow-sm focus:ring-slate-200">
                                        <span>{{ $colLabel }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white overflow-hidden">
                    <div class="hidden md:block overflow-x-auto">
                        <table class="w-full" x-data="columnResize()" id="compensationErrorsTable">
                            <thead class="bg-slate-50 text-slate-500">
                                <tr>
                                    @foreach($errorColumnDefs as $colKey => $colLabel)
                                        @if(in_array($colKey, $errorVisibleColumns, true))
                                            @php
                                                $meta = $errorColumnMeta[$colKey];
                                                $align = $meta['align'];
                                                $isSortable = isset($errorSortableColumns[$colKey]);
                                            @endphp
                                            <th class="px-2 py-2 {{ $align }} text-xs font-medium uppercase tracking-[0.14em] {{ $isSortable ? 'sortable-th' : '' }}" style="position: relative; width: {{ $meta['width'] }}; min-width: 40px;" @if($isSortable) wire:click="sortErrors('{{ $colKey }}')" @endif>
                                                <div class="flex items-center gap-1 {{ $align === 'text-right' ? 'justify-end' : ($align === 'text-center' ? 'justify-center' : '') }}">
                                                    <span>{{ $colLabel }}</span>
                                                    @if($isSortable)
                                                        <span class="text-[10px] {{ ($errorSortableColumns[$colKey] ?? null) === $errorSortField ? 'text-slate-700' : 'text-slate-300' }}">{{ $errorSortIcon($colKey) }}</span>
                                                    @endif
                                                </div>
                                                <div class="col-resize-handle" @mousedown.stop.prevent="startResize($event, $el.parentElement)"></div>
                                            </th>
                                        @endif
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                @forelse($this->allErrors as $error)
                                    @php
                                        $isClaimable = in_array($error->error_type, \App\Models\CargoReportItem::CLAIMABLE_ERROR_TYPES, true);
                                        $errorCrmSnapshot = $crmSnapshots->forSubject(auth()->user(), 'cargo', $error);
                                    @endphp
                                    <tr class="bg-white hover:bg-slate-50 transition">
                                        @foreach($errorColumnDefs as $colKey => $colLabel)
                                            @if(in_array($colKey, $errorVisibleColumns, true))
                                                @switch($colKey)
                                                    @case('date')
                                                        <td class="px-2 py-2 text-sm text-slate-900">{{ $error->tarih?->format('d.m.Y') }}</td>
                                                        @break
                                                    @case('customer')
                                                        <td class="px-2 py-2 align-top">
                                                            <p class="truncate text-sm font-semibold text-slate-900" title="{{ $error->musteri_adi }}">{{ $error->musteri_adi }}</p>
                                                            <p class="mt-1 truncate text-xs text-slate-500">{{ $error->takip_kodu ?: 'Takip kodu yok' }}</p>
                                                            <x-zolm.crm-snapshot :snapshot="$errorCrmSnapshot" variant="table" class="mt-2" />
                                                        </td>
                                                        @break
                                                    @case('product')
                                                        <td class="px-2 py-2 text-sm text-slate-500">
                                                            <p class="truncate" title="{{ $error->urun_adi }}">{{ $error->urun_adi ?: 'Ürün bilgisi yok' }}</p>
                                                        </td>
                                                        @break
                                                    @case('wrong_desi')
                                                        <td class="px-1 py-2 text-center text-sm font-semibold text-rose-600">{{ number_format((float) $error->gercek_desi, 0, ',', '.') }}</td>
                                                        @break
                                                    @case('correct_desi')
                                                        <td class="px-1 py-2 text-center text-sm font-semibold text-emerald-600">{{ number_format((float) $error->beklenen_desi, 0, ',', '.') }}</td>
                                                        @break
                                                    @case('difference')
                                                        <td class="px-1 py-2 text-right text-sm font-semibold {{ $error->tutar_fark > 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ $formatSignedMoney($error->tutar_fark) }}</td>
                                                        @break
                                                    @case('actions')
                                                        <td class="px-2 py-2 text-right">
                                                            <div class="flex justify-end gap-1.5">
                                                                <a href="{{ $crmLinks->urlFor('cargo', $error) }}"
                                                                   class="inline-flex h-8 items-center justify-center rounded-lg border border-slate-200 bg-white px-2.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50">
                                                                    CRM
                                                                </a>
                                                                @if($isClaimable)
                                                                    <button wire:click="openCreateModal({{ $error->id }})" class="inline-flex h-8 items-center justify-center rounded-lg border border-slate-200 bg-white px-2.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50">
                                                                        Talep Aç
                                                                    </button>
                                                                @else
                                                                    <span class="inline-flex h-8 items-center justify-center rounded-lg border border-slate-200 bg-slate-100 px-2.5 text-xs font-medium text-slate-400">
                                                                        Manuel İnceleme
                                                                    </span>
                                                                @endif
                                                            </div>
                                                        </td>
                                                        @break
                                                @endswitch
                                            @endif
                                        @endforeach
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ max(count($errorVisibleColumns), 1) }}" class="px-6 py-12 text-center text-sm text-slate-500">Aramanıza uygun hata kaydı bulunamadı.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="md:hidden space-y-3 p-4">
                        @forelse($this->allErrors as $error)
                            @php $isClaimable = in_array($error->error_type, \App\Models\CargoReportItem::CLAIMABLE_ERROR_TYPES, true); @endphp
                            <div class="rounded-xl border border-slate-200 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2 text-xs text-slate-500">
                                            @if(in_array('date', $errorVisibleColumns, true))
                                                <span>{{ $error->tarih?->format('d.m.Y') }}</span>
                                            @endif
                                            @if(in_array('difference', $errorVisibleColumns, true))
                                                <x-zolm.status-badge :tone="$error->tutar_fark > 0 ? 'danger' : 'success'">{{ $formatSignedMoney($error->tutar_fark) }}</x-zolm.status-badge>
                                            @endif
                                        </div>
                                        @if(in_array('customer', $errorVisibleColumns, true))
                                            <p class="mt-2 truncate text-sm font-semibold text-slate-900">{{ $error->musteri_adi }}</p>
                                        @endif
                                        @if(in_array('product', $errorVisibleColumns, true))
                                            <p class="mt-1 truncate text-sm text-slate-500">{{ $error->urun_adi ?: 'Ürün bilgisi yok' }}</p>
                                        @endif
                                    </div>
                                </div>

                                <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    @if(in_array('wrong_desi', $errorVisibleColumns, true))
                                        <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                                            <span class="text-slate-500">Yanlış desi</span>
                                            <span class="font-semibold text-rose-600">{{ number_format((float) $error->gercek_desi, 0, ',', '.') }}</span>
                                        </div>
                                    @endif
                                    @if(in_array('correct_desi', $errorVisibleColumns, true))
                                        <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                                            <span class="text-slate-500">Doğru desi</span>
                                            <span class="font-semibold text-emerald-600">{{ number_format((float) $error->beklenen_desi, 0, ',', '.') }}</span>
                                        </div>
                                    @endif
                                </div>

                                @if(in_array('actions', $errorVisibleColumns, true))
                                    <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                        <a href="{{ $crmLinks->urlFor('cargo', $error) }}"
                                           class="inline-flex w-full items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                            CRM 360 Aç
                                        </a>
                                        @if($isClaimable)
                                            <button wire:click="openCreateModal({{ $error->id }})" class="w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                                Tazmin Talebi Oluştur
                                            </button>
                                        @else
                                            <button type="button" disabled class="w-full rounded-lg border border-slate-200 bg-slate-100 px-4 py-3 text-sm font-medium text-slate-400">
                                                Referans / Operasyon İncele
                                            </button>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-8 text-center text-sm text-slate-500">Aramanıza uygun hata kaydı bulunamadı.</div>
                        @endforelse
                    </div>

                    @if($this->allErrors->hasPages())
                        <div class="border-t border-slate-200 px-4 py-3">
                            {{ $this->allErrors->links() }}
                        </div>
                    @endif
                </div>
            </x-zolm.section-card>
        @endif

        @if($viewMode === 'all_compensations')
            <x-zolm.section-card
                variant="orders"
                eyebrow="Talep Listesi"
                title="Kayıtlı tazmin talepleri"
                description="Durum, belge ve sonuç tutarlarıyla tüm dosyalar."
                headerPadding="px-4 pt-4 pb-2 lg:px-6 lg:pt-6 lg:pb-2"
                bodyPadding="px-4 pb-4 lg:px-6 lg:pb-6"
            >
                @php
                    $compensationColumnMeta = [
                        'date' => ['width' => '88px', 'align' => 'text-left'],
                        'customer' => ['width' => '168px', 'align' => 'text-left'],
                        'reason' => ['width' => '100px', 'align' => 'text-left'],
                        'claimed' => ['width' => '92px', 'align' => 'text-right'],
                        'approved' => ['width' => '92px', 'align' => 'text-right'],
                        'status' => ['width' => '94px', 'align' => 'text-center'],
                        'documents' => ['width' => '152px', 'align' => 'text-left'],
                        'actions' => ['width' => '118px', 'align' => 'text-right'],
                    ];
                @endphp

                <div class="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 lg:gap-4">
                    <div class="text-xs text-slate-500">{{ count($compensationVisibleColumns) }} / {{ count($compensationColumnDefs) }} kolon gösteriliyor</div>
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" type="button" class="w-full sm:w-auto rounded-lg border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                            Kolonlar
                        </button>
                        <div x-show="open" @click.outside="open = false" x-transition class="absolute right-0 top-full z-30 mt-2 w-60 rounded-[10px] border border-slate-200 bg-white p-3 shadow-xl">
                            <p class="text-xs font-medium uppercase tracking-[0.16em] text-slate-500">Talep Kolonları</p>
                            <div class="mt-3 space-y-1.5">
                                @foreach($compensationColumnDefs as $colKey => $colLabel)
                                    <label class="flex items-center gap-2 rounded-lg px-2 py-2 text-sm text-slate-700 transition hover:bg-slate-50">
                                        <input type="checkbox" wire:click="toggleCompensationColumn('{{ $colKey }}')" {{ in_array($colKey, $compensationVisibleColumns, true) ? 'checked' : '' }} class="rounded border-slate-300 text-slate-900 shadow-sm focus:ring-slate-200">
                                        <span>{{ $colLabel }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white">
                    <div class="hidden md:block overflow-x-visible">
                        <table class="w-full" x-data="columnResize()" id="compensationClaimsTable">
                            <thead class="bg-slate-50 text-slate-500">
                                <tr>
                                    @foreach($compensationColumnDefs as $colKey => $colLabel)
                                        @if(in_array($colKey, $compensationVisibleColumns, true))
                                            @php
                                                $meta = $compensationColumnMeta[$colKey];
                                                $align = $meta['align'];
                                                $isSortable = isset($compensationSortableColumns[$colKey]);
                                            @endphp
                                            <th class="px-2 py-2 {{ $align }} text-xs font-medium uppercase tracking-[0.14em] {{ $isSortable ? 'sortable-th' : '' }}" style="position: relative; width: {{ $meta['width'] }}; min-width: 40px;" @if($isSortable) wire:click="sortCompensations('{{ $colKey }}')" @endif>
                                                <div class="flex items-center gap-1 {{ $align === 'text-right' ? 'justify-end' : ($align === 'text-center' ? 'justify-center' : '') }}">
                                                    <span>{{ $colLabel }}</span>
                                                    @if($isSortable)
                                                        <span class="text-[10px] {{ ($compensationSortableColumns[$colKey] ?? null) === $compensationSortField ? 'text-slate-700' : 'text-slate-300' }}">{{ $compensationSortIcon($colKey) }}</span>
                                                    @endif
                                                </div>
                                                <div class="col-resize-handle" @mousedown.stop.prevent="startResize($event, $el.parentElement)"></div>
                                            </th>
                                        @endif
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                @forelse($this->allCompensations as $comp)
                                    @php
                                        $sebepInfo = $comp->sebep_info;
                                        $durumInfo = $comp->durum_info;
                                    @endphp
                                    <tr wire:key="comp-row-{{ $comp->id }}" class="bg-white hover:bg-slate-50 transition border-b border-slate-100">
                                        @foreach($compensationColumnDefs as $colKey => $colLabel)
                                            @if(in_array($colKey, $compensationVisibleColumns, true))
                                                @switch($colKey)
                                                    @case('date')
                                                        <td class="px-2 py-2 text-sm text-slate-900">{{ $comp->tarih->format('d.m.Y') }}</td>
                                                        @break
                                                    @case('customer')
                                                        <td class="px-2 py-2 align-top">
                                                            <p class="truncate text-sm font-semibold text-slate-900" title="{{ $comp->musteri_adi }}">{{ $comp->musteri_adi }}</p>
                                                            <p class="mt-1 truncate text-xs text-slate-500">{{ $comp->cargo_company ?: 'Kargo firması yok' }}{{ $comp->takip_kodu ? ' · ' . $comp->takip_kodu : '' }}</p>
                                                            <p class="mt-1 truncate text-xs text-slate-400">Sorumlu: {{ $comp->responsibleUser?->name ?? 'Atanmadı' }} · {{ $comp->next_action_at?->format('d.m.Y') ?? 'Aksiyon yok' }}</p>
                                                        </td>
                                                        @break
                                                    @case('reason')
                                                        <td class="px-2 py-2">
                                                            <x-zolm.status-badge :tone="$toneFromColor($sebepInfo['color'])">{{ $sebepInfo['label'] }}</x-zolm.status-badge>
                                                        </td>
                                                        @break
                                                    @case('claimed')
                                                        <td class="px-1 py-2 text-right text-sm text-slate-900">{{ $formatMoney($comp->talep_tutari) }}</td>
                                                        @break
                                                    @case('approved')
                                                        <td class="px-1 py-2 text-right text-sm font-semibold text-emerald-600">{{ $formatMoney($comp->onaylanan_tutar) }}</td>
                                                        @break
                                                    @case('status')
                                                        <td class="px-2 py-2 text-center">
                                                            <x-zolm.status-badge :tone="$toneFromColor($durumInfo['color'])">{{ $durumInfo['label'] }}</x-zolm.status-badge>
                                                        </td>
                                                        @break
                                                    @case('documents')
                                                        <td class="px-2 py-2">
                                                            <div class="flex flex-wrap gap-2">
                                                                <a href="{{ route('compensation.petition', $comp->id) }}" class="inline-flex h-8 items-center justify-center rounded-lg border border-slate-200 bg-white px-2.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50">
                                                                    Dilekçe
                                                                </a>
                                                                <a href="{{ route('compensation.form', $comp->id) }}" class="inline-flex h-8 items-center justify-center rounded-lg border border-slate-200 bg-white px-2.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50">
                                                                    Form
                                                                </a>
                                                                <a href="{{ route('compensation.download-all', $comp->id) }}" class="inline-flex h-8 items-center justify-center rounded-lg border border-sky-200 bg-sky-50 px-2.5 text-xs font-medium text-sky-700 transition hover:bg-sky-100">
                                                                    ZIP
                                                                </a>
                                                                @if(!empty($comp->attachments))
                                                                    <button wire:click="viewAttachments({{ $comp->id }})" class="inline-flex h-8 items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 text-xs font-medium text-emerald-700 transition hover:bg-emerald-100">
                                                                        Ekler
                                                                    </button>
                                                                @endif
                                                        </td>
                                                        @break
                                                    @case('actions')
                                                        <td class="px-2 py-2 text-right">
                                                            <div
                                                                x-data="{
                                                                    open: false,
                                                                    top: 0,
                                                                    left: 0,
                                                                    toggle(e) {
                                                                        var r = e.currentTarget.getBoundingClientRect();
                                                                        this.top = r.bottom + 4;
                                                                        this.left = r.right - 192;
                                                                        this.open = !this.open;
                                                                    }
                                                                }"
                                                                @click.outside="open = false"
                                                                class="inline-flex justify-end"
                                                            >
                                                                <button
                                                                    @click="toggle($event)"
                                                                    type="button"
                                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 hover:bg-slate-50 hover:text-slate-700"
                                                                >
                                                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/></svg>
                                                                </button>

                                                                <div
                                                                    x-show="open"
                                                                    style="display:none;"
                                                                    :style="'position:fixed;top:'+top+'px;left:'+left+'px;z-index:9999;width:192px;'"
                                                                    class="rounded-[10px] border border-slate-200 bg-white p-1.5 shadow-xl"
                                                                >
                                                                    <button wire:click="openStatusModal({{ $comp->id }})" @click="open=false" class="w-full flex items-center gap-2.5 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 transition text-left">
                                                                        <svg class="h-4 w-4 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                                        Durum Güncelle
                                                                    </button>
                                                                    <button wire:click="openPetitionModal({{ $comp->id }})" @click="open=false" class="w-full flex items-center gap-2.5 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 transition text-left">
                                                                        <svg class="h-4 w-4 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                                        Dilekçe Düzenle
                                                                    </button>
                                                                    <button wire:click="editCompensation({{ $comp->id }})" @click="open=false" class="w-full flex items-center gap-2.5 rounded-xl px-3 py-2.5 text-sm font-medium text-amber-700 hover:bg-amber-50 transition text-left">
                                                                        <svg class="h-4 w-4 text-amber-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                                        Talebi Düzenle
                                                                    </button>
                                                                    <div class="my-1 border-t border-slate-100"></div>
                                                                    <button wire:click="deleteCompensation({{ $comp->id }})" wire:confirm="Bu tazmin talebini silmek istediğinize emin misiniz? Bu işlem geri alınamaz." @click="open=false" class="w-full flex items-center gap-2.5 rounded-xl px-3 py-2.5 text-sm font-medium text-rose-700 hover:bg-rose-50 transition text-left">
                                                                        <svg class="h-4 w-4 text-rose-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                                        Sil
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        @break
                                                @endswitch
                                            @endif
                                        @endforeach
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ max(count($compensationVisibleColumns), 1) }}" class="px-6 py-12 text-center text-sm text-slate-500">Aramanıza uygun tazmin talebi bulunamadı.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="md:hidden space-y-3 p-4">
                        @forelse($this->allCompensations as $comp)
                            @php
                                $sebepInfo = $comp->sebep_info;
                                $durumInfo = $comp->durum_info;
                            @endphp
                            <div wire:key="comp-card-{{ $comp->id }}" class="rounded-xl border border-slate-200 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                            @if(in_array('date', $compensationVisibleColumns, true))
                                                <span>{{ $comp->tarih->format('d.m.Y') }}</span>
                                            @endif
                                            @if(in_array('status', $compensationVisibleColumns, true))
                                                <x-zolm.status-badge :tone="$toneFromColor($durumInfo['color'])">{{ $durumInfo['label'] }}</x-zolm.status-badge>
                                            @endif
                                        </div>
                                        @if(in_array('customer', $compensationVisibleColumns, true))
                                            <p class="mt-2 truncate text-sm font-semibold text-slate-900">{{ $comp->musteri_adi }}</p>
                                        @endif
                                        @if(in_array('reason', $compensationVisibleColumns, true))
                                            <p class="mt-1 text-sm text-slate-500">{{ $sebepInfo['label'] }}</p>
                                        @endif
                                        <p class="mt-1 text-xs text-slate-400">Sorumlu: {{ $comp->responsibleUser?->name ?? 'Atanmadı' }} · {{ $comp->next_action_at?->format('d.m.Y') ?? 'Aksiyon yok' }}</p>
                                    </div>
                                </div>

                                <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    @if(in_array('claimed', $compensationVisibleColumns, true))
                                        <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                                            <span class="text-slate-500">Talep edilen</span>
                                            <span class="font-semibold text-slate-900">{{ $formatMoney($comp->talep_tutari) }}</span>
                                        </div>
                                    @endif
                                    @if(in_array('approved', $compensationVisibleColumns, true))
                                        <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                                            <span class="text-slate-500">Onaylanan</span>
                                            <span class="font-semibold text-emerald-600">{{ $formatMoney($comp->onaylanan_tutar) }}</span>
                                        </div>
                                    @endif
                                </div>

                                @if(in_array('actions', $compensationVisibleColumns, true))
                                    <div class="mt-4 border-t border-slate-100 pt-3">
                                        <div class="grid grid-cols-2 gap-2">
                                            <button wire:click="openStatusModal({{ $comp->id }})" class="flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">
                                                <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                Durum
                                            </button>
                                            <button wire:click="openPetitionModal({{ $comp->id }})" class="flex items-center justify-center gap-2 rounded-xl border border-sky-200 bg-sky-50 px-3 py-2.5 text-sm font-medium text-sky-700 shadow-sm transition hover:bg-sky-100">
                                                <svg class="h-4 w-4 text-sky-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                                Dilekçe
                                            </button>
                                            <button wire:click="editCompensation({{ $comp->id }})" class="flex items-center justify-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2.5 text-sm font-medium text-amber-700 shadow-sm transition hover:bg-amber-100">
                                                <svg class="h-4 w-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                                Düzenle
                                            </button>
                                            <button wire:click="deleteCompensation({{ $comp->id }})" wire:confirm="Bu tazmin talebini silmek istediğinize emin misiniz? Bu işlem geri alınamaz." class="flex items-center justify-center gap-2 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2.5 text-sm font-medium text-rose-700 shadow-sm transition hover:bg-rose-100">
                                                <svg class="h-4 w-4 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                Sil
                                            </button>
                                        </div>
                                    </div>
                                @endif

                                @if(in_array('documents', $compensationVisibleColumns, true))
                                    <div class="mt-3 flex flex-col sm:flex-row gap-2">
                                        <a href="{{ route('compensation.petition', $comp->id) }}" class="w-full sm:w-auto rounded-lg border border-slate-200 bg-white px-4 py-3 text-center text-sm font-medium text-slate-700 transition hover:bg-slate-50">Dilekçe İndir</a>
                                        <a href="{{ route('compensation.form', $comp->id) }}" class="w-full sm:w-auto rounded-lg border border-slate-200 bg-white px-4 py-3 text-center text-sm font-medium text-slate-700 transition hover:bg-slate-50">Form İndir</a>
                                        <a href="{{ route('compensation.download-all', $comp->id) }}" class="w-full sm:w-auto rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-center text-sm font-medium text-sky-700 transition hover:bg-sky-100">ZIP İndir</a>
                                        @if(!empty($comp->attachments))
                                            <button wire:click="viewAttachments({{ $comp->id }})" class="w-full sm:w-auto rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 transition hover:bg-emerald-100">Ekler</button>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-8 text-center text-sm text-slate-500">Aramanıza uygun tazmin talebi bulunamadı.</div>
                        @endforelse
                    </div>

                    @if($this->allCompensations->hasPages())
                        <div class="border-t border-slate-200 px-4 py-3">
                            {{ $this->allCompensations->links() }}
                        </div>
                    @endif
                </div>
            </x-zolm.section-card>
        @endif
    @endif

    @if($showCreateModal)
        @php
            $newReasonInfo = \App\Models\Compensation::SEBEPLER[$newCompensation['sebep'] ?? 'diger'] ?? \App\Models\Compensation::SEBEPLER['diger'];
        @endphp
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
            <div class="flex min-h-screen items-end justify-center p-0 sm:items-center sm:p-4">
                <button type="button" class="fixed inset-0 bg-slate-900/40" wire:click="$set('showCreateModal', false)"></button>

                <div class="relative flex w-full max-h-[100dvh] min-h-0 flex-col overflow-hidden rounded-t-[28px] border border-slate-200 bg-white shadow-xl sm:max-h-[90vh] sm:max-w-3xl sm:rounded-[28px]">
                    <form wire:submit="createCompensation" class="flex min-h-0 flex-1 flex-col">
                        <div class="shrink-0 border-b border-slate-200 px-4 py-4 lg:px-6">
                            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                                <div>
                                    <x-zolm.eyebrow variant="classic">{{ $editingCompensationId ? 'Talebi Düzenle' : 'Yeni Talep' }}</x-zolm.eyebrow>
                                    <h3 class="mt-3 text-xl font-bold text-slate-900">{{ $editingCompensationId ? 'Tazmin talebini güncelle' : 'Tazmin talebi oluştur' }}</h3>
                                    <p class="mt-2 text-sm text-slate-500">Müşteri, sebep, tutar ve kanıt görsellerini tek formdan kaydedin.</p>
                                </div>
                                <button type="button" wire:click="$set('showCreateModal', false)" class="self-end sm:self-start rounded-lg border border-slate-200 bg-white p-2 text-slate-400 transition hover:text-slate-700">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>
                            </div>
                        </div>

                        <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 py-4 lg:px-6">
                            <div class="space-y-4">
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 lg:gap-4">
                                    <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                                        <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Kargo</p>
                                        <p class="mt-2 text-lg font-semibold text-slate-900">{{ $newCompensation['cargo_company'] ?: 'Belirtilmedi' }}</p>
                                    </div>
                                    <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                                        <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Sebep</p>
                                        <p class="mt-2 text-lg font-semibold text-slate-900">{{ $newReasonInfo['label'] }}</p>
                                    </div>
                                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                                    <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Talep Tutarı</p>
                                    <p class="mt-2 text-lg font-semibold text-slate-900">{{ $formatMoney($newCompensation['talep_tutari'] ?? 0) }}</p>
                                </div>
                            </div>

                                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                                    <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Talep Bilgileri</p>
                                    <p class="mt-2 text-sm text-slate-500">Talebin tarih, müşteri ve finansal bilgilerini tek blokta tamamlayın.</p>

                                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4 lg:gap-6">
                                        <div>
                                            <label class="text-xs sm:text-sm font-medium text-slate-700">Tarih</label>
                                            <input type="date" wire:model="newCompensation.tarih" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                            @error('newCompensation.tarih') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                                        </div>
                                        <div>
                                            <label class="text-xs sm:text-sm font-medium text-slate-700">Kargo firması</label>
                                            <select wire:model="newCompensation.cargo_company" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                                <option value="Sürat Kargo">Sürat Kargo</option>
                                                <option value="Yurtiçi Kargo">Yurtiçi Kargo</option>
                                                <option value="Aras Kargo">Aras Kargo</option>
                                                <option value="PTT Kargo">PTT Kargo</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4 lg:gap-6">
                                        <div>
                                            <label class="text-xs sm:text-sm font-medium text-slate-700">Müşteri adı</label>
                                            <input type="text" wire:model="newCompensation.musteri_adi" placeholder="Müşteri adını girin" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                            @error('newCompensation.musteri_adi') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                                        </div>
                                        <div>
                                            <label class="text-xs sm:text-sm font-medium text-slate-700">Talep tutarı (₺)</label>
                                            <input type="number" step="0.01" wire:model="newCompensation.talep_tutari" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                            @error('newCompensation.talep_tutari') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                                        </div>
                                    </div>

                                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-4 lg:gap-6">
                                        <div>
                                            <label class="text-xs sm:text-sm font-medium text-slate-700">Öncelik</label>
                                            <select wire:model="newCompensation.priority" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                                @foreach(\App\Models\Compensation::PRIORITIES as $priorityKey => $priorityInfo)
                                                    <option value="{{ $priorityKey }}">{{ $priorityInfo['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="text-xs sm:text-sm font-medium text-slate-700">Sorumlu</label>
                                            <select wire:model="newCompensation.responsible_user_id" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                                <option value="">Seçilmedi</option>
                                                @foreach($assignableUsers as $user)
                                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="text-xs sm:text-sm font-medium text-slate-700">Sonraki aksiyon</label>
                                            <input type="date" wire:model="newCompensation.next_action_at" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                        </div>
                                    </div>
                                </div>

                                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                                    <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Sevkiyat ve Açıklama</p>
                                    <p class="mt-2 text-sm text-slate-500">Takip, sebep ve ürün bilgisini mümkün olduğunca doldurun; bu bilgi dilekçe ve raporlarda kullanılır.</p>

                                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4 lg:gap-6">
                                        <div>
                                            <label class="text-xs sm:text-sm font-medium text-slate-700">Takip kodu</label>
                                            <input type="text" wire:model="newCompensation.takip_kodu" placeholder="Opsiyonel" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                        </div>
                                        <div>
                                            <label class="text-xs sm:text-sm font-medium text-slate-700">Sebep</label>
                                            <select wire:model="newCompensation.sebep" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                                @foreach(\App\Models\Compensation::SEBEPLER as $key => $info)
                                                    <option value="{{ $key }}">{{ $info['label'] }}</option>
                                                @endforeach
                                            </select>
                                            @error('newCompensation.sebep') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                                        </div>
                                    </div>

                                    <div class="mt-4">
                                        <label class="text-xs sm:text-sm font-medium text-slate-700">Ürün adı</label>
                                        <input type="text" wire:model="newCompensation.urun_adi" placeholder="Opsiyonel ürün bilgisi" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                    </div>

                                    <div class="mt-4">
                                        <label class="text-xs sm:text-sm font-medium text-slate-700">Açıklama</label>
                                        <textarea wire:model="newCompensation.aciklama" rows="4" placeholder="Talebin kısa açıklamasını girin" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200"></textarea>
                                    </div>

                                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4 lg:gap-6">
                                        <div>
                                            <label class="text-xs sm:text-sm font-medium text-slate-700">Kargo vaka / referans no</label>
                                            <input type="text" wire:model="newCompensation.carrier_case_no" placeholder="Opsiyonel taşıyıcı referansı" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                        </div>
                                        <div>
                                            <label class="text-xs sm:text-sm font-medium text-slate-700">İç not</label>
                                            <input type="text" wire:model="newCompensation.internal_note" placeholder="Operasyon / finans notu" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                        </div>
                                    </div>
                                </div>

                                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                                        <div>
                                            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Kanıt Görselleri</p>
                                            <p class="mt-2 text-sm text-slate-500">PNG, JPG veya GIF formatında en fazla 5 MB boyutunda dosyalar yükleyin.</p>
                                        </div>
                                        @if($attachments)
                                            <x-zolm.status-badge tone="info">{{ count($attachments) }} dosya seçildi</x-zolm.status-badge>
                                        @endif
                                    </div>

                                    <label class="mt-4 block cursor-pointer rounded-3xl border border-dashed border-slate-300 bg-white px-4 py-5 transition hover:border-slate-400">
                                        <input wire:model="attachments" type="file" class="hidden" multiple accept="image/*">
                                        <div class="flex flex-col sm:flex-row sm:items-center gap-3 lg:gap-4">
                                            <div class="flex h-12 w-12 items-center justify-center rounded-[8px] bg-slate-900 text-white">
                                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-6l-4-4m0 0L8 10m4-4v12" />
                                                </svg>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <p class="text-sm font-medium text-slate-900">Görselleri seçin</p>
                                                <p class="mt-1 text-xs sm:text-sm text-slate-500">Kanıt görselleri tek seferde toplu yüklenebilir.</p>
                                            </div>
                                        </div>
                                    </label>

                                    @error('attachments.*') <p class="mt-3 text-sm text-rose-600">{{ $message }}</p> @enderror

                                    @if($attachments)
                                        <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-3 lg:gap-4">
                                            @foreach($attachments as $file)
                                                <img src="{{ $file->temporaryUrl() }}" alt="Önizleme" class="h-24 w-full rounded-2xl border border-slate-200 object-cover">
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="shrink-0 border-t border-slate-200 bg-white px-4 py-4 lg:px-6">
                            <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-3">
                                <button type="button" wire:click="$set('showCreateModal', false)" class="w-full sm:w-auto rounded-lg border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                    İptal
                                </button>
                                <x-zolm.primary-button color="indigo" type="submit" compact wire:loading.attr="disabled" wire:target="createCompensation,attachments">
                                    <span wire:loading.remove wire:target="createCompensation">{{ $editingCompensationId ? 'Değişiklikleri Kaydet' : 'Talebi Oluştur' }}</span>
                                    <span wire:loading wire:target="createCompensation">Kaydediliyor...</span>
                                </x-zolm.primary-button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if($showPetitionModal)
        @php
            $petitionComp = $updatingCompensationId ? \App\Models\Compensation::find($updatingCompensationId) : null;
            $petitionStatusInfo = $petitionComp?->durum_info;
            $petitionReasonInfo = $petitionComp?->sebep_info;
        @endphp
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
            <div class="flex min-h-screen items-end justify-center p-0 sm:items-center sm:p-4">
                <button type="button" class="fixed inset-0 bg-slate-900/40" wire:click="$set('showPetitionModal', false)"></button>

                <div class="relative flex w-full max-h-[100dvh] min-h-0 flex-col overflow-hidden rounded-t-[28px] border border-slate-200 bg-white shadow-xl sm:max-h-[90vh] sm:max-w-4xl sm:rounded-[28px]">
                    <div class="shrink-0 border-b border-slate-200 px-4 py-4 lg:px-6">
                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                            <div>
                                <x-zolm.eyebrow variant="classic">Dilekçe Metni</x-zolm.eyebrow>
                                <h3 class="mt-3 text-xl font-bold text-slate-900">Dilekçe içeriğini düzenle</h3>
                                <p class="mt-2 text-sm text-slate-500">Gövde metni bu alandan güncellenir. Standart resmi başlık ve imza alanları PDF oluşturulurken eklenir.</p>
                            </div>
                            <button type="button" wire:click="$set('showPetitionModal', false)" class="self-end sm:self-start rounded-lg border border-slate-200 bg-white p-2 text-slate-400 transition hover:text-slate-700">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 py-4 lg:px-6">
                        @if($petitionComp)
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 lg:gap-4">
                                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                                    <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Müşteri</p>
                                    <p class="mt-2 text-lg font-semibold text-slate-900">{{ $petitionComp->musteri_adi }}</p>
                                </div>
                                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                                    <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Durum</p>
                                    <div class="mt-2">
                                        <x-zolm.status-badge :tone="$toneFromColor($petitionStatusInfo['color'] ?? 'gray')">{{ $petitionStatusInfo['label'] ?? 'Beklemede' }}</x-zolm.status-badge>
                                    </div>
                                    <p class="mt-2 text-sm text-slate-500">{{ $petitionReasonInfo['label'] ?? 'Genel Talep' }}</p>
                                </div>
                                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                                    <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Talep / Onay</p>
                                    <p class="mt-2 text-lg font-semibold text-slate-900">{{ $formatMoney($petitionComp->talep_tutari) }}</p>
                                    <p class="mt-2 text-sm text-slate-500">Onaylanan: {{ $formatMoney($petitionComp->onaylanan_tutar) }}</p>
                                </div>
                            </div>
                        @endif

                        <div class="rounded-3xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-800 {{ $petitionComp ? 'mt-4' : '' }}">
                            AI ile taslak oluşturabilir, ardından metni manuel olarak revize edebilirsiniz.
                        </div>

                        <div class="mt-4">
                            <label class="text-xs sm:text-sm font-medium text-slate-700">Dilekçe metni</label>
                            <textarea wire:model="editingPetitionText" rows="16" placeholder="Dilekçe metni..." class="mt-1 w-full rounded-3xl border border-slate-200 bg-white px-4 py-4 text-base sm:text-sm text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200"></textarea>
                        </div>
                    </div>

                    <div class="shrink-0 border-t border-slate-200 bg-white px-4 py-4 lg:px-6">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <button type="button" wire:click="generateAiPetition({{ $updatingCompensationId }})" wire:loading.attr="disabled" class="w-full sm:w-auto rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 sm:py-2 text-sm font-medium text-sky-700 transition hover:bg-sky-100 disabled:opacity-60">
                                <span wire:loading.remove wire:target="generateAiPetition">AI ile Taslak Oluştur</span>
                                <span wire:loading wire:target="generateAiPetition">Hazırlanıyor...</span>
                            </button>
                            <div class="flex w-full sm:w-auto flex-col-reverse sm:flex-row gap-3">
                                <button type="button" wire:click="$set('showPetitionModal', false)" class="w-full sm:w-auto rounded-lg border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                    İptal
                                </button>
                                <x-zolm.primary-button color="indigo" compact wire:click="savePetitionText">Kaydet</x-zolm.primary-button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($showStatusModal)
        @php
            $statusComp = $updatingCompensationId ? \App\Models\Compensation::find($updatingCompensationId) : null;
            $statusInfo = $statusComp?->durum_info;
        @endphp
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
            <div class="flex min-h-screen items-end justify-center p-0 sm:items-center sm:p-4">
                <button type="button" class="fixed inset-0 bg-slate-900/40" wire:click="$set('showStatusModal', false)"></button>

                <div class="relative flex w-full max-h-[100dvh] min-h-0 flex-col overflow-hidden rounded-t-[28px] border border-slate-200 bg-white shadow-xl sm:max-h-[90vh] sm:max-w-xl sm:rounded-[28px]">
                    <div class="shrink-0 border-b border-slate-200 px-4 py-4 lg:px-6">
                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                            <div>
                                <x-zolm.eyebrow variant="classic">Durum Güncelle</x-zolm.eyebrow>
                                <h3 class="mt-3 text-xl font-bold text-slate-900">Talep sonucunu kaydet</h3>
                                <p class="mt-2 text-sm text-slate-500">Son durum ve onaylanan tutar aynı anda güncellenir.</p>
                            </div>
                            <button type="button" wire:click="$set('showStatusModal', false)" class="self-end sm:self-start rounded-lg border border-slate-200 bg-white p-2 text-slate-400 transition hover:text-slate-700">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 py-4 lg:px-6">
                        <div class="space-y-4">
                        @if($statusComp)
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 lg:gap-4">
                                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                                    <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Mevcut Durum</p>
                                    <div class="mt-2">
                                        <x-zolm.status-badge :tone="$toneFromColor($statusInfo['color'] ?? 'gray')">{{ $statusInfo['label'] ?? 'Beklemede' }}</x-zolm.status-badge>
                                    </div>
                                </div>
                                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                                    <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Talep Edilen</p>
                                    <p class="mt-2 text-lg font-semibold text-slate-900">{{ $formatMoney($statusComp->talep_tutari) }}</p>
                                </div>
                                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                                    <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Kalan Fark</p>
                                    <p class="mt-2 text-lg font-semibold text-slate-900">{{ $formatMoney(($statusComp->talep_tutari ?? 0) - $onaylananTutar) }}</p>
                                </div>
                            </div>
                        @endif

                        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 lg:gap-6">
                                <div>
                                    <label class="text-xs sm:text-sm font-medium text-slate-700">Durum</label>
                                    <select wire:model="newStatus" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                        @foreach(\App\Models\Compensation::DURUMLAR as $key => $info)
                                            <option value="{{ $key }}">{{ $info['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs sm:text-sm font-medium text-slate-700">Onaylanan tutar (₺)</label>
                                    <input type="number" step="0.01" wire:model="onaylananTutar" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                </div>
                            </div>

                            <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4 lg:gap-6">
                                <div>
                                    <label class="text-xs sm:text-sm font-medium text-slate-700">Tahsil edilen tutar (₺)</label>
                                    <input type="number" step="0.01" wire:model="collectedAmount" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                </div>
                                <div>
                                    <label class="text-xs sm:text-sm font-medium text-slate-700">Ödeme tarihi</label>
                                    <input type="date" wire:model="paymentDate" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                </div>
                                <div>
                                    <label class="text-xs sm:text-sm font-medium text-slate-700">Sonraki aksiyon</label>
                                    <input type="date" wire:model="nextActionAt" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                </div>
                                <div>
                                    <label class="text-xs sm:text-sm font-medium text-slate-700">Sorumlu</label>
                                    <select wire:model="responsibleUserId" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                        <option value="">Seçilmedi</option>
                                        @foreach($assignableUsers as $user)
                                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs sm:text-sm font-medium text-slate-700">Öncelik</label>
                                    <select wire:model="priority" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                        @foreach(\App\Models\Compensation::PRIORITIES as $priorityKey => $priorityInfo)
                                            <option value="{{ $priorityKey }}">{{ $priorityInfo['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs sm:text-sm font-medium text-slate-700">Taşıyıcı vaka no</label>
                                    <input type="text" wire:model="carrierCaseNo" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                </div>
                            </div>

                            <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4 lg:gap-6">
                                <div>
                                    <label class="text-xs sm:text-sm font-medium text-slate-700">İç not</label>
                                    <textarea wire:model="internalNote" rows="4" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200"></textarea>
                                </div>
                                <div>
                                    <label class="text-xs sm:text-sm font-medium text-slate-700">Sonuç notu</label>
                                    <textarea wire:model="resolutionNote" rows="4" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200"></textarea>
                                </div>
                            </div>

                            <div class="mt-4 rounded-[8px] border border-sky-200 bg-sky-50 p-4 text-sm text-sky-800">
                                Sonuç tarihi sonuçlanan statülerde, ödeme tarihi ise ödeme bekleniyor / ödendi akışlarında kullanılır.
                            </div>
                        </div>
                        </div>
                    </div>

                    <div class="shrink-0 border-t border-slate-200 bg-white px-4 py-4 lg:px-6">
                        <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-3">
                            <button type="button" wire:click="$set('showStatusModal', false)" class="w-full sm:w-auto rounded-lg border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                İptal
                            </button>
                            <x-zolm.primary-button color="indigo" compact wire:click="updateStatus">Güncelle</x-zolm.primary-button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($showAttachmentsModal)
        <div class="fixed inset-0 z-[60] overflow-y-auto" aria-modal="true">
            <div class="flex min-h-screen items-end justify-center p-0 sm:items-center sm:p-4">
                <button type="button" class="fixed inset-0 bg-slate-900/60" wire:click="$set('showAttachmentsModal', false)"></button>

                <div class="relative flex w-full max-h-[100dvh] min-h-0 flex-col overflow-hidden rounded-t-[28px] border border-slate-200 bg-white shadow-xl sm:max-h-[90vh] sm:max-w-5xl sm:rounded-[28px]">
                    <div class="shrink-0 border-b border-slate-200 px-4 py-4 lg:px-6">
                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                            <div>
                                <x-zolm.eyebrow variant="classic">Kanıt Görselleri</x-zolm.eyebrow>
                                <h3 class="mt-3 text-xl font-bold text-slate-900">Yüklenen ekler</h3>
                                <p class="mt-2 text-sm text-slate-500">Talebe bağlı tüm kanıt görsellerini tek ekranda görüntüleyin.</p>
                            </div>
                            <div class="flex items-center gap-2 self-end sm:self-start">
                                <x-zolm.status-badge tone="info">{{ count($viewingAttachments) }} görsel</x-zolm.status-badge>
                                <button type="button" wire:click="$set('showAttachmentsModal', false)" class="rounded-lg border border-slate-200 bg-white p-2 text-slate-400 transition hover:text-slate-700">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 py-4 lg:px-6">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 lg:gap-6">
                            @foreach($viewingAttachments as $index => $path)
                                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                        <p class="text-sm font-semibold text-slate-900">Görsel {{ $index + 1 }}</p>
                                        <a href="{{ $attachmentUrl($path) }}" target="_blank" class="w-full sm:w-auto rounded-lg border border-slate-200 bg-white px-4 py-3 sm:py-2 text-center text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                            Yeni Sekmede Aç
                                        </a>
                                    </div>
                                    <img src="{{ $attachmentUrl($path) }}" alt="Kanıt {{ $index + 1 }}" class="mt-4 w-full rounded-3xl border border-slate-200 bg-white object-contain max-h-[60vh]">
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Dropdown menüleri için teleport hedefi (Livewire root içinde ama overflow dışında kalmalı) --}}
    <div id="cargo-compensation-dropdown-teleport"></div>
</div>
