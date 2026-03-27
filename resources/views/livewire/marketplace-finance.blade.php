@php
    $formatMoney = fn ($value) => '₺' . number_format((float) $value, 2, ',', '.');
    $formatCount = fn ($value) => number_format((float) $value, 0, ',', '.');
    $activeFilters = array_values(array_filter([
        $search !== '' ? 'Arama: ' . $search : null,
        $marketplaceFilter !== '' ? 'Pazaryeri: ' . $this->humanMarketplace($marketplaceFilter) : null,
        $storeFilter !== '' ? 'Mağaza seçili' : null,
        $legalEntityFilter !== '' ? 'Firma seçili' : null,
        $orderStatusFilter !== '' ? 'Sipariş durumu seçili' : null,
        $profitStateFilter !== '' ? 'Kâr durumu seçili' : null,
        $financialStateFilter !== '' ? 'Finans akışı seçili' : null,
        $deltaStateFilter !== '' ? 'Mutabakat seçili' : null,
        $eventTypeFilter !== '' ? 'Olay tipi seçili' : null,
        $legacyProjectionFilter !== '' ? 'Legacy köprü seçili' : null,
        $dateFrom !== '' ? 'Başlangıç: ' . $dateFrom : null,
        $dateTo !== '' ? 'Bitiş: ' . $dateTo : null,
    ]));
    $guidanceItems = collect($diagnosticsGuidance['items'] ?? []);
    $primaryGuidance = $guidanceItems->first();
    $secondaryGuidance = $guidanceItems->slice(1)->take(4)->values();
    $sortIcon = function (string $columnKey) use ($sortableColumns, $sortField, $sortDirection) {
        $dbColumn = $sortableColumns[$columnKey] ?? null;
        if (!$dbColumn) {
            return '';
        }

        return $sortField === $dbColumn
            ? ($sortDirection === 'asc' ? '▲' : '▼')
            : '⇅';
    };
@endphp

@once
    <style>
        .mp-finance-v2-table {
            table-layout: fixed;
            width: 100%;
        }

        .mp-finance-v2-table th,
        .mp-finance-v2-table td {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
    </style>
@endonce

<div class="w-full space-y-6 overflow-hidden" x-data="{ expanded: [], prioritiesOpen: false, advancedFilters: false }">

    @if($actionMessage !== '')
        <div class="rounded-xl border p-4 text-sm {{ $actionMessageTone === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : ($actionMessageTone === 'warning' ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-slate-200 bg-slate-50 text-slate-700') }}">
            {{ $actionMessage }}
        </div>
    @endif

    {{-- ═══════════════════════════════════════════════ --}}
    {{-- BLOK 1: HERO + KPI --}}
    {{-- ═══════════════════════════════════════════════ --}}
    <section class="rounded-2xl border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
        <div class="grid grid-cols-1 xl:grid-cols-12 gap-6 lg:gap-8">
            {{-- Sol kısım: Başlık ve Butonlar --}}
            <div class="xl:col-span-7 flex flex-col justify-between">
                <div>
                    <div class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.24em] text-slate-500 mb-4 lg:mb-5">
                        Finans Merkezi
                    </div>
                    <h1 class="text-2xl lg:text-3xl font-bold text-slate-900">Finans ve Mutabakat</h1>
                    <p class="mt-2 text-sm lg:text-base text-slate-500">
                        Sipariş snapshot'ları, hakediş akışı ve kesinti kalemlerini izleyin.
                    </p>
                </div>
                <div class="mt-8 flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                    <a href="{{ route('marketplace-accounting') }}" class="inline-flex min-h-[44px] items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 hover:border-slate-300">Legacy muhasebe</a>
                    <a href="{{ route('mp.integrations') }}" class="inline-flex min-h-[44px] items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 hover:border-slate-300">Entegrasyonlar</a>
                    <a href="{{ route('mp.orders') }}" class="inline-flex min-h-[44px] items-center justify-center rounded-xl bg-slate-900 px-4 py-3 sm:py-2 text-sm font-medium text-white transition hover:bg-slate-800">Siparişler</a>
                    <div x-data="{ open: false }" class="relative">
                        <button type="button" @click="open = !open" class="inline-flex min-h-[44px] items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 hover:border-slate-300">Raporlar</button>
                        <div x-show="open" x-transition @click.outside="open = false" class="absolute right-0 top-full z-30 mt-2 w-56 rounded-xl border border-slate-200 bg-white p-2 shadow-xl">
                            <button type="button" wire:click="exportSummaryCsv" @click="open = false" class="flex w-full rounded-lg px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-50">Finans özeti CSV</button>
                            <button type="button" wire:click="exportEventsCsv" @click="open = false" class="flex w-full rounded-lg px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-50">Finans hareketleri CSV</button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Sağ kısım: Metrikler --}}
            <div class="xl:col-span-5 grid grid-cols-2 gap-3 lg:gap-4">
                <div class="flex flex-col justify-center rounded-xl border border-slate-200 bg-slate-50 p-4 lg:p-5 min-w-0">
                    <p class="text-[10px] lg:text-xs uppercase tracking-[0.2em] font-medium text-slate-500 truncate">Net Alacak</p>
                    <p class="mt-2 text-2xl lg:text-3xl font-bold text-emerald-600 truncate">{{ $formatMoney($stats['total_receivable']) }}</p>
                    <p class="mt-1 text-[11px] font-medium text-slate-500 truncate">{{ $stats['finance_ready_orders'] }} siparişte finans hazır</p>
                </div>
                <div class="flex flex-col justify-center rounded-xl border border-slate-200 bg-slate-50 p-4 lg:p-5 min-w-0">
                    <p class="text-[10px] lg:text-xs uppercase tracking-[0.2em] font-medium text-slate-500 truncate">Toplam Kesinti</p>
                    <p class="mt-2 text-2xl lg:text-3xl font-bold text-amber-600 truncate">{{ $formatMoney($stats['total_deductions']) }}</p>
                    <p class="mt-1 text-[11px] font-medium text-slate-500 truncate">{{ $formatMoney($stats['total_commission']) }} komisyon</p>
                </div>
                <div class="flex flex-col justify-center rounded-xl border border-slate-200 bg-slate-50 p-4 lg:p-5 min-w-0">
                    <p class="text-[10px] lg:text-xs uppercase tracking-[0.2em] font-medium text-slate-500 truncate">Kesin Kâr</p>
                    <p class="mt-2 text-2xl lg:text-3xl font-bold {{ $stats['confirmed_profit_total'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }} truncate">{{ $formatMoney($stats['confirmed_profit_total']) }}</p>
                    <p class="mt-1 text-[11px] font-medium text-slate-500 truncate">{{ $stats['confirmed_orders'] }} sipariş kesinleşti</p>
                </div>
                <div class="flex flex-col justify-center rounded-xl border {{ $stats['material_variance_orders'] > 0 ? 'border-rose-200 bg-rose-50' : 'border-slate-200 bg-slate-50' }} p-4 lg:p-5 group transition duration-300 hover:shadow-sm hover:bg-white min-w-0">
                    <p class="text-[10px] lg:text-xs uppercase tracking-[0.2em] font-medium {{ $stats['material_variance_orders'] > 0 ? 'text-rose-800' : 'text-slate-500' }} truncate">Mutabakat Sağlığı</p>
                    <p class="mt-2 text-2xl lg:text-3xl font-bold {{ $stats['material_variance_orders'] > 0 ? 'text-rose-600' : ($stats['snapshot_missing_orders'] > 0 ? 'text-amber-600' : 'text-emerald-600') }} truncate">{{ $formatCount($stats['aligned_orders']) }} uyumlu</p>
                    <p class="mt-1 text-[11px] font-medium {{ $stats['material_variance_orders'] > 0 ? 'text-rose-700' : 'text-slate-500' }} truncate">{{ $stats['minor_variance_orders'] }} izlenecek · {{ $stats['snapshot_missing_orders'] }} eksik</p>
                </div>
            </div>

            <div class="xl:col-span-12">
                {{-- Hızlı durum badge'leri --}}
                <div class="flex flex-wrap gap-2 pt-4 xl:pt-6 border-t border-slate-200 mt-2">
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 font-medium text-slate-600 text-[11px] lg:text-xs uppercase tracking-[0.1em]">Tahmini kâr {{ $formatMoney($stats['estimated_profit_total']) }}</span>
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 font-medium {{ abs($stats['total_profit_delta']) > 0.01 ? 'text-amber-700' : 'text-emerald-700' }} text-[11px] lg:text-xs uppercase tracking-[0.1em]">Kâr farkı {{ $formatMoney($stats['total_profit_delta']) }}</span>
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 font-medium text-slate-600 text-[11px] lg:text-xs uppercase tracking-[0.1em]">Finans bekleyen {{ $formatCount($stats['finance_waiting_orders']) }}</span>
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 font-medium text-slate-600 text-[11px] lg:text-xs uppercase tracking-[0.1em]">Snapshot eksik {{ $formatCount($stats['snapshot_missing_orders']) }}</span>
                </div>
            </div>
        </div>
    </section>

    @if(!$hasConfiguredStores)
        <section class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-5 py-12 text-center shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">Henüz mağaza bağlantısı yok</h2>
            <p class="mt-2 text-sm text-slate-500">Önce entegrasyonları tanımlayın, ardından sipariş ve finans sync çalıştırın.</p>
        </section>
    @else
        <div class="space-y-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
                <div>
                    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-3 lg:gap-4">
                        <div>
                            <h2 class="text-lg font-bold text-slate-900">Mutabakat Listesi</h2>
                            <p class="mt-1 text-sm text-slate-500">
                                Hakediş, kesinti ve kâr snapshot’larını sipariş bazında filtreleyin.
                            </p>
                        </div>

                        <div class="flex flex-col items-start gap-2 lg:items-end">
                            <p class="text-sm text-slate-500 lg:text-right">
                                {{ count($activeFilters) > 0 ? implode(' · ', $activeFilters) : 'Henüz aktif filtre yok.' }}
                                · {{ $formatCount($rows->total()) }} sipariş
                            </p>

                            <div class="flex items-center gap-3">
                                <div class="text-xs text-slate-500">{{ count($visibleColumns) }} / {{ count($columnDefs) }} kolon gösteriliyor</div>
                                <div x-data="{ open: false }" class="relative">
                                    <button @click="open = !open"
                                            type="button"
                                            class="inline-flex min-h-[44px] items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                        Kolonlar
                                    </button>

                                    <div x-show="open"
                                         @click.outside="open = false"
                                         x-transition
                                         class="absolute right-0 top-full z-30 mt-2 w-60 rounded-2xl border border-slate-200 bg-white p-3 shadow-xl">
                                        <p class="text-xs font-medium uppercase tracking-[0.16em] text-slate-500">Görünür kolonlar</p>
                                        <div class="mt-3 space-y-1.5">
                                            @foreach($columnDefs as $colKey => $colLabel)
                                                <label class="flex items-center gap-2 rounded-lg px-2 py-2 text-sm text-slate-700 transition hover:bg-slate-50">
                                                    <input type="checkbox"
                                                           wire:click="toggleColumn('{{ $colKey }}')"
                                                           {{ in_array($colKey, $visibleColumns, true) ? 'checked' : '' }}
                                                           class="rounded border-slate-300 text-slate-900 shadow-sm focus:ring-indigo-200">
                                                    <span>{{ $colLabel }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Bugün önce bunlara bak</p>
                                <p class="mt-2 text-sm text-slate-500">
                                    En önemli konu önce görünür. Diğer öneriler sadece ihtiyaç olduğunda açılır.
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full border border-rose-200 bg-rose-50 px-3 py-1 text-xs font-medium text-rose-700">
                                    Kritik {{ $formatCount($diagnosticsGuidance['totals']['critical']) }}
                                </span>
                                <span class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-medium text-amber-700">
                                    Uyarı {{ $formatCount($diagnosticsGuidance['totals']['warning']) }}
                                </span>
                                <button type="button"
                                        wire:click="focusTopGuidance"
                                        class="inline-flex min-h-[44px] items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                    {{ $this->guidanceFocusLabel() }}
                                </button>
                                <button type="button"
                                        wire:click="syncTopGuidance"
                                        class="inline-flex min-h-[44px] items-center justify-center rounded-lg bg-slate-900 px-4 py-3 sm:py-2 text-sm font-medium text-white transition hover:bg-slate-800">
                                    {{ $this->guidanceSyncLabel() }}
                                </button>
                            </div>
                        </div>

                        <div class="mt-4 space-y-3">
                            @if($legacyProjectionGuidanceCard)
                                <div class="rounded-xl border border-slate-200 bg-white px-4 py-4">
                                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <p class="text-sm font-semibold text-slate-900">{{ $legacyProjectionGuidanceCard['store_name'] }}</p>
                                                <span class="text-xs text-slate-400">·</span>
                                                <p class="text-xs text-slate-500">{{ $this->humanMarketplace($legacyProjectionGuidanceCard['marketplace']) }}</p>
                                                @if($legacyProjectionGuidanceCard['legal_entity_name'])
                                                    <span class="text-xs text-slate-400">·</span>
                                                    <p class="text-xs text-slate-500">{{ $legacyProjectionGuidanceCard['legal_entity_name'] }}</p>
                                                @endif
                                            </div>
                                            <p class="mt-2 text-sm font-medium text-slate-900">{{ $legacyProjectionGuidanceCard['title'] }}</p>
                                            <p class="mt-2 text-sm text-slate-500">{{ $legacyProjectionGuidanceCard['description'] }}</p>
                                        </div>

                                        <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                                            <x-zolm.status-badge :tone="$legacyProjectionGuidanceCard['state'] === 'warning' ? 'warning' : ($legacyProjectionGuidanceCard['state'] === 'success' ? 'success' : 'default')">
                                                {{ $legacyProjectionGuidanceCard['pending_rows'] > 0 ? 'Backlog var' : 'Kesin etki var' }}
                                            </x-zolm.status-badge>
                                            <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-600">
                                                Bekleyen {{ $formatCount($legacyProjectionGuidanceCard['pending_rows']) }}
                                            </span>
                                            <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-600">
                                                Kesine dönen {{ $formatCount($legacyProjectionGuidanceCard['confirmed_orders']) }}
                                            </span>
                                            <button type="button"
                                                    wire:click="focusLegacyProjectionCard"
                                                    class="inline-flex min-h-[44px] items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                                Backlogu odakla
                                            </button>
                                            @if($legacyProjectionGuidanceCard['confirmed_orders'] > 0)
                                                <button type="button"
                                                        wire:click="focusLegacyConfirmedProjectionCard"
                                                        class="inline-flex min-h-[44px] items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                                    Kesin etkiyi göster
                                                </button>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="mt-3 flex flex-col gap-2 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between">
                                        <span>
                                            Son aktarım:
                                            <span class="font-medium text-slate-900">
                                                {{ $legacyProjectionGuidanceCard['last_projected_at'] ? \Illuminate\Support\Carbon::parse($legacyProjectionGuidanceCard['last_projected_at'])->format('d.m.Y H:i') : 'Henüz yok' }}
                                            </span>
                                        </span>
                                        <span>{{ $this->guidanceCategoryLabel('legacy_financial_projection') }}</span>
                                    </div>
                                </div>
                            @endif

                            @if($primaryGuidance)
                                <a href="{{ $this->guidanceRoute($primaryGuidance) }}"
                                   class="block rounded-xl border border-slate-200 bg-white px-4 py-4 transition hover:border-slate-300 hover:bg-slate-50">
                                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <p class="text-sm font-semibold text-slate-900">{{ $primaryGuidance['store_name'] ?: '-' }}</p>
                                                <span class="text-xs text-slate-400">·</span>
                                                <p class="text-xs text-slate-500">{{ $this->humanMarketplace($primaryGuidance['marketplace']) }}</p>
                                                <span class="text-xs text-slate-400">·</span>
                                                <p class="text-xs text-slate-500">{{ $this->guidanceCategoryLabel($primaryGuidance['category']) }}</p>
                                            </div>
                                            <p class="mt-2 text-sm font-medium text-slate-900">{{ $primaryGuidance['title'] }}</p>
                                            <p class="mt-2 text-sm text-slate-500">{{ $primaryGuidance['recommended_action'] }}</p>
                                        </div>

                                        <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                                            <x-zolm.status-badge :tone="$this->guidanceSeverityTone($primaryGuidance['severity'])">
                                                {{ $this->guidanceSeverityLabel($primaryGuidance['severity']) }}
                                            </x-zolm.status-badge>
                                            <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-600">
                                                {{ $formatCount($primaryGuidance['impact_count']) }} kayıt
                                            </span>
                                            <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-600">
                                                {{ $this->guidanceRouteLabel($primaryGuidance['route']) }}
                                            </span>
                                        </div>
                                    </div>
                                </a>
                            @endif

                            @if($secondaryGuidance->isNotEmpty())
                                <div class="flex justify-end">
                                    <button type="button"
                                            @click="prioritiesOpen = !prioritiesOpen"
                                            class="inline-flex min-h-[44px] items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                        <span x-text="prioritiesOpen ? 'Diğer öncelikleri gizle' : 'Diğer öncelikleri göster'"></span>
                                    </button>
                                </div>

                                <div x-show="prioritiesOpen" x-cloak x-transition class="grid grid-cols-1 gap-3">
                                    @foreach($secondaryGuidance as $item)
                                        <a href="{{ $this->guidanceRoute($item) }}"
                                           class="block rounded-xl border border-slate-200 bg-white px-4 py-3 transition hover:border-slate-300 hover:bg-slate-50">
                                            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                                <div class="min-w-0">
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <p class="text-sm font-semibold text-slate-900">{{ $item['store_name'] ?: '-' }}</p>
                                                        <span class="text-xs text-slate-400">·</span>
                                                        <p class="text-xs text-slate-500">{{ $this->humanMarketplace($item['marketplace']) }}</p>
                                                        <span class="text-xs text-slate-400">·</span>
                                                        <p class="text-xs text-slate-500">{{ $this->guidanceCategoryLabel($item['category']) }}</p>
                                                    </div>
                                                    <p class="mt-2 text-sm font-medium text-slate-800">{{ $item['title'] }}</p>
                                                    <p class="mt-2 text-sm text-slate-500">{{ $item['recommended_action'] }}</p>
                                                </div>

                                                <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                                                    <x-zolm.status-badge :tone="$this->guidanceSeverityTone($item['severity'])">
                                                        {{ $this->guidanceSeverityLabel($item['severity']) }}
                                                    </x-zolm.status-badge>
                                                    <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-600">
                                                        {{ $formatCount($item['impact_count']) }} kayıt
                                                    </span>
                                                </div>
                                            </div>
                                        </a>
                                    @endforeach
                                </div>
                            @endif

                            @if(!$legacyProjectionGuidanceCard && !$primaryGuidance)
                                <div class="rounded-xl border border-dashed border-slate-300 bg-white px-4 py-5 text-sm text-slate-500">
                                    Finans ekranı için kayıtlı bir öncelik bulunmuyor.
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Eski veri aktarım etkisi</p>
                                <p class="mt-2 text-sm text-slate-500">
                                    Eski `mp_orders` muhasebe satırlarının yeni finans akışına taşınma durumu ve bu taşımanın kesin kâra etkisi burada özetlenir.
                                </p>
                            </div>

                            <a href="{{ route('mp.orders', array_filter(['storeFilter' => $storeFilter !== '' ? $storeFilter : null], fn ($value) => $value !== null && $value !== '')) }}"
                               class="inline-flex min-h-[44px] items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                Aktarım ekranına git
                            </a>
                        </div>

                        <div class="mt-4 grid grid-cols-2 xl:grid-cols-4 gap-3">
                            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4">
                                <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Bekleyen eski satır</p>
                                <p class="mt-2 text-lg font-semibold text-slate-900">{{ $formatCount($legacyProjectionInsights['pending_rows'] ?? 0) }}</p>
                                <p class="mt-1 text-xs text-slate-500">Henüz yeni finans akışına taşınmamış muhasebe kayıtları.</p>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4">
                                <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Projeksiyonu tamamlanan</p>
                                <p class="mt-2 text-lg font-semibold text-slate-900">{{ $formatCount($legacyProjectionInsights['projected_rows'] ?? 0) }}</p>
                                <p class="mt-1 text-xs text-slate-500">Eski finans aktarımı ile yeni finans akışına taşınan satırlar.</p>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4">
                                <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Legacy event siparişi</p>
                                <p class="mt-2 text-lg font-semibold text-slate-900">{{ $formatCount($legacyProjectionInsights['legacy_event_orders'] ?? 0) }}</p>
                                <p class="mt-1 text-xs text-slate-500">Legacy kaynaklı finans eventi taşıyan sipariş sayısı.</p>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4">
                                <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Kesine dönen sipariş</p>
                                <p class="mt-2 text-lg font-semibold text-slate-900">{{ $formatCount($legacyProjectionInsights['confirmed_orders'] ?? 0) }}</p>
                                <p class="mt-1 text-xs text-slate-500">Kesin kâra yansıyan legacy finans etkisi.</p>
                            </div>
                        </div>

                        <div class="mt-3 flex flex-col gap-2 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between">
                            <span>
                                Son aktarım:
                                <span class="font-medium text-slate-900">
                                    {{ !empty($legacyProjectionInsights['last_projected_at']) ? \Illuminate\Support\Carbon::parse($legacyProjectionInsights['last_projected_at'])->format('d.m.Y H:i') : 'Yok' }}
                                </span>
                            </span>
                            <a href="{{ route('mp.orders', array_filter(['storeFilter' => $storeFilter !== '' ? $storeFilter : null], fn ($value) => $value !== null && $value !== '')) }}"
                               class="inline-flex items-center gap-2 font-medium text-slate-700 transition hover:text-slate-900">
                                Aktarım ekranına git
                            </a>
                        </div>
                    </div>

                    <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50/60 p-4">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-900">Filtreler</p>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{ count($activeFilters) > 0 ? implode(' · ', array_slice($activeFilters, 0, 4)) : 'Henüz aktif filtre yok.' }}
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-600">
                                    {{ count($activeFilters) }} aktif filtre
                                </span>
                                <button type="button"
                                        @click="advancedFilters = !advancedFilters"
                                        class="inline-flex min-h-[44px] items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                    <span x-text="advancedFilters ? 'Gelişmiş filtreleri gizle' : 'Gelişmiş filtreler'"></span>
                                </button>
                                <button wire:click="resetFilters"
                                        class="inline-flex min-h-[44px] items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-rose-600 transition hover:bg-rose-50">
                                    Filtreleri temizle
                                </button>
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3 lg:gap-4">
                            <div>
                                <label class="block text-xs sm:text-sm font-medium text-slate-500">Arama</label>
                                <input wire:model.live.debounce.300ms="search"
                                       type="text"
                                       placeholder="Sipariş no, müşteri, referans..."
                                       class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                            </div>

                            <div>
                                <label class="block text-xs sm:text-sm font-medium text-slate-500">Pazaryeri</label>
                                <select wire:model.live="marketplaceFilter"
                                        class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                    <option value="">Tümü</option>
                                    @foreach($marketplaceOptions as $marketplace)
                                        <option value="{{ $marketplace }}">{{ $this->humanMarketplace($marketplace) }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs sm:text-sm font-medium text-slate-500">Mağaza</label>
                                <select wire:model.live="storeFilter"
                                        class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                    <option value="">Tümü</option>
                                    @foreach($storeOptions as $store)
                                        <option value="{{ $store->id }}">{{ $store->store_name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs sm:text-sm font-medium text-slate-500">Mutabakat</label>
                                <select wire:model.live="deltaStateFilter"
                                        class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                    <option value="">Tümü</option>
                                    <option value="waiting">Finans bekliyor</option>
                                    <option value="snapshot_missing">Snapshot eksik</option>
                                    <option value="aligned">Uyumlu</option>
                                    <option value="minor">İzlenecek</option>
                                    <option value="material">Materyal fark</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs sm:text-sm font-medium text-slate-500">Başlangıç tarihi</label>
                                <input wire:model.live="dateFrom"
                                       type="date"
                                       class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                            </div>

                            <div>
                                <label class="block text-xs sm:text-sm font-medium text-slate-500">Bitiş tarihi</label>
                                <input wire:model.live="dateTo"
                                       type="date"
                                       class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                            </div>
                        </div>

                        <div x-show="advancedFilters" x-cloak x-transition class="mt-4 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3 lg:gap-4">
                            <div>
                                <label class="block text-xs sm:text-sm font-medium text-slate-500">Firma</label>
                                <select wire:model.live="legalEntityFilter"
                                        class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                    <option value="">Tüm firmalar</option>
                                    @foreach($legalEntities as $entity)
                                        <option value="{{ $entity->id }}">{{ $entity->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs sm:text-sm font-medium text-slate-500">Sipariş durumu</label>
                                <select wire:model.live="orderStatusFilter"
                                        class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                    <option value="">Tümü</option>
                                    <option value="new">Yeni</option>
                                    <option value="approved">Onaylandı</option>
                                    <option value="shipped">Kargoda</option>
                                    <option value="delivered">Teslim</option>
                                    <option value="cancelled">İptal</option>
                                    <option value="returned">İade</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs sm:text-sm font-medium text-slate-500">Kâr durumu</label>
                                <select wire:model.live="profitStateFilter"
                                        class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                    <option value="">Tümü</option>
                                    <option value="confirmed">Kesin</option>
                                    <option value="estimated">Tahmini</option>
                                    <option value="missing">Snapshot yok</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs sm:text-sm font-medium text-slate-500">Finans durumu</label>
                                <select wire:model.live="financialStateFilter"
                                        class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                    <option value="">Tümü</option>
                                    <option value="ready">Hazır</option>
                                    <option value="waiting">Bekliyor</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs sm:text-sm font-medium text-slate-500">Finans olayı</label>
                                <select wire:model.live="eventTypeFilter"
                                        class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                    <option value="">Tümü</option>
                                    @foreach($eventTypeOptions as $eventType)
                                        <option value="{{ $eventType }}">{{ $this->humanEventType($eventType) }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs sm:text-sm font-medium text-slate-500">Legacy köprü</label>
                                <select wire:model.live="legacyProjectionFilter"
                                        class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                    <option value="">Tümü</option>
                                    <option value="backlog">Backlog var</option>
                                    <option value="confirmed">Kesin etki</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="px-4 pb-4 lg:px-6 lg:pb-6">
                    <div class="space-y-3 md:hidden">
                        @forelse($rows as $row)
                            @php
                                $profitValue = (float) ($row->profit_value_metric ?? 0);
                                $receivable = (float) ($row->net_receivable_metric ?? 0);
                                $profitDelta = (float) ($row->profit_delta_metric ?? 0);
                            @endphp
                            <article class="rounded-xl border border-slate-200 bg-white shadow-sm">
                                <button type="button"
                                        class="w-full p-4 text-left"
                                        @click="expanded.includes({{ $row->id }}) ? expanded = expanded.filter(i => i !== {{ $row->id }}) : expanded.push({{ $row->id }})">
                                    <div class="flex items-start gap-3">
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2">
                                                <svg class="h-4 w-4 shrink-0 text-slate-400 transition"
                                                     :class="{ 'rotate-90 text-slate-900': expanded.includes({{ $row->id }}) }"
                                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                </svg>
                                                <p class="truncate text-sm font-semibold text-slate-900">{{ $row->order_number ?: 'Sipariş no yok' }}</p>
                                            </div>
                                            <p class="mt-1 text-xs text-slate-500">{{ $row->customer_name ?: '-' }} · {{ $row->store_name_alias ?: '-' }}</p>
                                            <div class="mt-3 flex flex-wrap gap-2">
                                                <x-zolm.status-badge :tone="$this->reconciliationStateTone($row->reconciliation_state_metric)">
                                                    {{ $this->reconciliationStateLabel($row->reconciliation_state_metric) }}
                                                </x-zolm.status-badge>
                                                <x-zolm.status-badge :tone="$this->profitStateTone($row->profit_state_metric)">
                                                    {{ $this->profitStateLabel($row->profit_state_metric) }}
                                                </x-zolm.status-badge>
                                            </div>
                                            <div class="mt-3 grid grid-cols-2 gap-2">
                                                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 px-3 py-2">
                                                    <p class="text-[10px] uppercase tracking-[0.16em] text-slate-500">Net alacak</p>
                                                    <p class="mt-1 text-sm font-medium text-slate-900">{{ $formatMoney($receivable) }}</p>
                                                </div>
                                                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 px-3 py-2">
                                                    <p class="text-[10px] uppercase tracking-[0.16em] text-slate-500">Kâr farkı</p>
                                                    <p class="mt-1 text-sm font-medium {{ $profitDelta >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                                                        {{ $formatMoney($profitDelta) }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="text-right">
                                            <p class="mt-3 text-sm font-semibold {{ $profitValue >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ $formatMoney($profitValue) }}</p>
                                            <p class="mt-1 text-xs text-slate-500">{{ $this->humanStatus($row->order_status) }} · {{ $formatCount($row->financial_event_count) }} olay</p>
                                        </div>
                                    </div>
                                </button>

                                <div x-show="expanded.includes({{ $row->id }})" x-cloak x-transition class="expanded-row border-t border-slate-200 bg-slate-50/60 p-4 w-full max-w-full overflow-hidden">
                                    @include('livewire.partials.marketplace-finance-detail', ['order' => $row])
                                </div>
                            </article>
                        @empty
                            <div class="rounded-[24px] border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center text-sm text-slate-500">
                                {{ $hasChannelOrders ? 'Seçilen filtrelere uygun finans kaydı bulunamadı.' : 'Henüz kanal siparişi yok. Önce sipariş sync çalıştırın.' }}
                            </div>
                        @endforelse
                    </div>

                    <div class="hidden md:block rounded-lg border border-slate-200">
                        @php
                            $columnMeta = [
                                'siparis' => ['label' => 'Sipariş', 'width' => '240px'],
                                'magaza' => ['label' => 'Mağaza', 'width' => '160px'],
                                'ciro' => ['label' => 'Ciro', 'width' => '120px'],
                                'alacak' => ['label' => 'Net Alacak', 'width' => '140px'],
                                'kesinti' => ['label' => 'Kesinti', 'width' => '140px'],
                                'kar' => ['label' => 'Kâr', 'width' => '130px'],
                                'varyans' => ['label' => 'Kâr Farkı', 'width' => '140px'],
                                'durum' => ['label' => 'Durum', 'width' => '120px'],
                                'mutabakat' => ['label' => 'Mutabakat', 'width' => '150px'],
                                'sync' => ['label' => 'Son Finans', 'width' => '150px'],
                            ];
                        @endphp

                        <div class="overflow-x-auto w-full" x-data="columnResize()">
                            <table class="mp-finance-v2-table min-w-[1200px] w-full divide-y divide-slate-200 table-fixed">
                                <thead class="bg-slate-50 text-slate-500">
                                    <tr>
                                        <th class="w-10 px-3 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em]"></th>
                                        @foreach($columnMeta as $columnKey => $meta)
                                            @if(in_array($columnKey, $visibleColumns, true))
                                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em]"
                                                    style="width: {{ $meta['width'] }}; min-width: {{ $meta['width'] }};"
                                                    @if(isset($sortableColumns[$columnKey])) wire:click="sortTable('{{ $columnKey }}')" @endif>
                                                    <div class="flex items-center justify-between gap-2">
                                                        <span>{{ $meta['label'] }}</span>
                                                        @if(isset($sortableColumns[$columnKey]))
                                                            <span class="text-[10px] {{ ($sortableColumns[$columnKey] ?? null) === $sortField ? 'text-slate-700' : 'text-slate-300' }}">{{ $sortIcon($columnKey) }}</span>
                                                        @endif
                                                    </div>
                                                </th>
                                            @endif
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 bg-white text-sm text-slate-700">
                                    @forelse($rows as $row)
                                        @php
                                            $profitValue = (float) ($row->profit_value_metric ?? 0);
                                            $profitDelta = (float) ($row->profit_delta_metric ?? 0);
                                            $deductionDelta = (float) ($row->deduction_delta_metric ?? 0);
                                        @endphp
                                        <tr class="transition hover:bg-slate-50/80">
                                            <td class="px-3 py-4 align-top">
                                                <button type="button"
                                                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50 hover:text-slate-900"
                                                        @click="expanded.includes({{ $row->id }}) ? expanded = expanded.filter(i => i !== {{ $row->id }}) : expanded.push({{ $row->id }})">
                                                    <svg class="h-4 w-4 transition" :class="{ 'rotate-90': expanded.includes({{ $row->id }}) }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                    </svg>
                                                </button>
                                            </td>

                                            @if(in_array('siparis', $visibleColumns, true))
                                                <td class="px-3 py-4 align-top">
                                                    <div class="font-semibold text-slate-900">{{ $row->order_number ?: '-' }}</div>
                                                    <div class="mt-1 text-xs text-slate-500">{{ $row->customer_name ?: '-' }}</div>
                                                    <div class="mt-1 text-[11px] text-slate-400">{{ $row->ordered_at?->format('d.m.Y H:i') ?: '-' }}</div>
                                                </td>
                                            @endif

                                            @if(in_array('magaza', $visibleColumns, true))
                                                <td class="px-3 py-4 align-top">
                                                    <div class="font-semibold text-slate-900">{{ $row->store_name_alias ?: '-' }}</div>
                                                    <div class="mt-1 text-xs text-slate-500">{{ $this->humanMarketplace($row->marketplace_alias) }}</div>
                                                    <div class="mt-1 text-[11px] text-slate-400">{{ $row->legal_entity_name_alias ?: '-' }}</div>
                                                </td>
                                            @endif

                                            @if(in_array('ciro', $visibleColumns, true))
                                                <td class="px-3 py-4 align-top text-right">
                                                    <div class="font-semibold text-slate-900">{{ $formatMoney($row->gross_revenue_metric) }}</div>
                                                    <div class="mt-1 text-xs text-slate-500">{{ $formatCount($row->total_quantity ?? 0) }} adet</div>
                                                </td>
                                            @endif

                                            @if(in_array('alacak', $visibleColumns, true))
                                                <td class="px-3 py-4 align-top text-right">
                                                    <div class="font-semibold text-slate-900">{{ $formatMoney($row->net_receivable_metric) }}</div>
                                                    <div class="mt-1 text-xs text-slate-500">{{ $row->financial_event_count > 0 ? 'Finans geldi' : 'Bekleniyor' }}</div>
                                                </td>
                                            @endif

                                            @if(in_array('kesinti', $visibleColumns, true))
                                                <td class="px-3 py-4 align-top text-right">
                                                    <div class="font-semibold text-amber-600">{{ $formatMoney($row->deduction_total_metric) }}</div>
                                                    <div class="mt-1 text-xs text-slate-500">
                                                        K: {{ $formatMoney($row->commission_total_metric) }} · Cargo: {{ $formatMoney($row->cargo_total_metric) }}
                                                    </div>
                                                </td>
                                            @endif

                                            @if(in_array('kar', $visibleColumns, true))
                                                <td class="px-3 py-4 align-top text-right">
                                                    <div class="font-semibold {{ $profitValue >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ $formatMoney($profitValue) }}</div>
                                                    <div class="mt-1 text-xs text-slate-500">%{{ number_format((float) ($row->margin_percent_metric ?? 0), 1, ',', '.') }}</div>
                                                </td>
                                            @endif

                                            @if(in_array('varyans', $visibleColumns, true))
                                                <td class="px-3 py-4 align-top text-right">
                                                    <div class="font-semibold {{ $profitDelta >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ $formatMoney($profitDelta) }}</div>
                                                    <div class="mt-1 text-xs {{ $deductionDelta <= 0 ? 'text-emerald-600' : 'text-amber-600' }}">
                                                        Kesinti: {{ $formatMoney($deductionDelta) }}
                                                    </div>
                                                </td>
                                            @endif

                                            @if(in_array('durum', $visibleColumns, true))
                                                <td class="px-3 py-4 align-top">
                                                    <div class="flex flex-col gap-2">
                                                        <x-zolm.status-badge :tone="$this->statusTone($row->order_status)">
                                                            {{ $this->humanStatus($row->order_status) }}
                                                        </x-zolm.status-badge>
                                                        <x-zolm.status-badge :tone="$this->profitStateTone($row->profit_state_metric)">
                                                            {{ $this->profitStateLabel($row->profit_state_metric) }}
                                                        </x-zolm.status-badge>
                                                    </div>
                                                </td>
                                            @endif

                                            @if(in_array('mutabakat', $visibleColumns, true))
                                                <td class="px-3 py-4 align-top">
                                                    <x-zolm.status-badge :tone="$this->reconciliationStateTone($row->reconciliation_state_metric)">
                                                        {{ $this->reconciliationStateLabel($row->reconciliation_state_metric) }}
                                                    </x-zolm.status-badge>
                                                    <div class="mt-2 text-xs text-slate-500">
                                                        {{ $formatCount($row->financial_event_count) }} olay
                                                    </div>
                                                </td>
                                            @endif

                                            @if(in_array('sync', $visibleColumns, true))
                                                <td class="px-3 py-4 align-top">
                                                    <div class="font-medium text-slate-900">{{ $row->last_financial_event_at ? \Illuminate\Support\Carbon::parse($row->last_financial_event_at)->format('d.m.Y H:i') : '-' }}</div>
                                                    <div class="mt-1 text-xs text-slate-500">Snapshot {{ $row->order_snapshot?->calculated_at?->format('d.m H:i') ?: '-' }}</div>
                                                </td>
                                            @endif
                                        </tr>

                                        <tr x-show="expanded.includes({{ $row->id }})" x-cloak x-transition class="expanded-row bg-slate-50/60">
                                            <td colspan="{{ count($visibleColumns) + 1 }}" class="p-0">
                                                <div class="px-4 py-4 lg:px-6">
                                                    @include('livewire.partials.marketplace-finance-detail', ['order' => $row])
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="{{ count($visibleColumns) + 1 }}" class="px-6 py-12 text-center text-sm text-slate-500">
                                                {{ $hasChannelOrders ? 'Seçilen filtrelere uygun finans kaydı bulunamadı.' : 'Henüz kanal siparişi yok. Önce sipariş sync çalıştırın.' }}
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <label class="text-xs sm:text-sm text-slate-500">Sayfa boyutu</label>
                            <select wire:model.live="perPage"
                                    class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                <option value="10">10</option>
                                <option value="20">20</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                        </div>

                        {{ $rows->links() }}
                    </div>
                </div>
            </section>

            {{-- FİNANS HAZIRLIĞI --}}
            <section x-data="{ readinessOpen: false }" class="rounded-2xl border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3 cursor-pointer" @click="readinessOpen = !readinessOpen">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">Finans Hazırlığı</h2>
                        <p class="mt-1 text-sm text-slate-500">Bekleyen {{ $formatCount($sidebarSummary['pending_financial_events']) }} · Materyal fark {{ $formatCount($stats['material_variance_orders']) }} · Snapshot eksik {{ $formatCount($stats['snapshot_missing_orders']) }}</p>
                    </div>
                    <svg class="h-5 w-5 shrink-0 text-slate-400 transition" :class="{ 'rotate-180': readinessOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 9l-7 7-7-7" /></svg>
                </div>
                <div x-cloak x-show="readinessOpen" x-transition class="mt-4 space-y-3">
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                        <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-3">
                            <p class="text-xs uppercase tracking-[0.12em] text-slate-500">Aktif mağaza</p>
                            <p class="mt-2 text-lg font-semibold text-slate-900">{{ $sidebarSummary['active_store_count'] }} / {{ $sidebarSummary['store_count'] }}</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-3">
                            <p class="text-xs uppercase tracking-[0.12em] text-slate-500">Bekleyen olay</p>
                            <p class="mt-2 text-lg font-semibold {{ $sidebarSummary['pending_financial_events'] > 0 ? 'text-amber-600' : 'text-emerald-600' }}">{{ $formatCount($sidebarSummary['pending_financial_events']) }}</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-3">
                            <p class="text-xs uppercase tracking-[0.12em] text-slate-500">Materyal fark</p>
                            <p class="mt-2 text-lg font-semibold {{ $stats['material_variance_orders'] > 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ $formatCount($stats['material_variance_orders']) }}</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-3">
                            <p class="text-xs uppercase tracking-[0.12em] text-slate-500">Snapshot eksik</p>
                            <p class="mt-2 text-lg font-semibold {{ $stats['snapshot_missing_orders'] > 0 ? 'text-sky-600' : 'text-slate-900' }}">{{ $formatCount($stats['snapshot_missing_orders']) }}</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-3">
                            <p class="text-xs uppercase tracking-[0.12em] text-slate-500">Kuyrukta sync</p>
                            <p class="mt-2 text-lg font-semibold text-slate-900">{{ $formatCount($sidebarSummary['processing_finance_syncs']) }}</p>
                            <p class="text-xs text-slate-500">{{ $formatCount($stats['waiting_reconciliation_orders']) }} bekliyor</p>
                        </div>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-3">
                        <p class="text-xs uppercase tracking-[0.12em] text-slate-500">Son finans sync</p>
                        <p class="mt-2 text-sm font-semibold text-slate-900">{{ $sidebarSummary['latest_finance_sync'] ? \Illuminate\Support\Carbon::parse($sidebarSummary['latest_finance_sync'])->format('d.m.Y H:i') : 'Henüz çalışmadı' }}</p>
                        <p class="mt-1 text-xs {{ $sidebarSummary['failed_finance_syncs'] > 0 ? 'text-rose-600' : 'text-slate-500' }}">Son 24 saatte {{ $sidebarSummary['failed_finance_syncs'] }} başarısız sync.</p>
                    </div>
                </div>
            </section>

            {{-- KESİNTİ KOMPOZİSYONU --}}
            <section x-data="{ deductionsOpen: false }" class="rounded-2xl border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3 cursor-pointer" @click="deductionsOpen = !deductionsOpen">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">Kesinti Kompozisyonu</h2>
                        <p class="mt-1 text-sm text-slate-500">Toplam {{ $formatMoney($stats['total_deductions']) }} · Fark {{ $formatMoney($stats['total_deduction_delta']) }}</p>
                    </div>
                    <svg class="h-5 w-5 shrink-0 text-slate-400 transition" :class="{ 'rotate-180': deductionsOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 9l-7 7-7-7" /></svg>
                </div>
                <div x-cloak x-show="deductionsOpen" x-transition class="mt-4 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                    <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-3">
                        <p class="text-xs uppercase tracking-[0.12em] text-slate-500">Komisyon</p>
                        <p class="mt-2 text-lg font-semibold text-rose-600">{{ $formatMoney($stats['total_commission']) }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-3">
                        <p class="text-xs uppercase tracking-[0.12em] text-slate-500">Kargo</p>
                        <p class="mt-2 text-lg font-semibold text-amber-600">{{ $formatMoney($stats['total_cargo']) }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-3">
                        <p class="text-xs uppercase tracking-[0.12em] text-slate-500">Hizmet bedeli</p>
                        <p class="mt-2 text-lg font-semibold text-slate-900">{{ $formatMoney($stats['total_service_fee']) }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-3">
                        <p class="text-xs uppercase tracking-[0.12em] text-slate-500">Stopaj</p>
                        <p class="mt-2 text-lg font-semibold text-indigo-600">{{ $formatMoney($stats['total_withholding']) }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-3">
                        <p class="text-xs uppercase tracking-[0.12em] text-slate-500">Kesinti farkı</p>
                        <p class="mt-2 text-lg font-semibold {{ $stats['total_deduction_delta'] <= 0 ? 'text-emerald-600' : 'text-amber-600' }}">{{ $formatMoney($stats['total_deduction_delta']) }}</p>
                    </div>
                </div>
            </section>

            {{-- MUTABAKAT SEGMENTLERİ --}}
            <section x-data="{ segmentsOpen: false }" class="rounded-2xl border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3 cursor-pointer" @click="segmentsOpen = !segmentsOpen">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">Mutabakat Segmentleri</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ $formatCount(count($marketplaceBreakdown)) }} kanal</p>
                    </div>
                    <svg class="h-5 w-5 shrink-0 text-slate-400 transition" :class="{ 'rotate-180': segmentsOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 9l-7 7-7-7" /></svg>
                </div>
                <div x-cloak x-show="segmentsOpen" x-transition class="mt-4 space-y-3">
                    @forelse($marketplaceBreakdown as $segment)
                        <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-slate-900">{{ $this->humanMarketplace($segment->marketplace_alias) }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $formatCount($segment->order_count) }} sipariş · {{ $formatCount($segment->waiting_orders) }} finans bekliyor · {{ $formatCount($segment->material_orders) }} materyal fark</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-semibold text-slate-900">{{ $formatMoney($segment->total_receivable) }}</p>
                                    <p class="mt-1 text-xs text-slate-500">Net alacak</p>
                                </div>
                            </div>
                            <div class="mt-2 grid grid-cols-2 lg:grid-cols-4 gap-2 text-center text-xs">
                                <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">
                                    <p class="text-slate-500">Ciro</p>
                                    <p class="mt-1 font-semibold text-slate-900">{{ $formatMoney($segment->total_revenue) }}</p>
                                </div>
                                <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">
                                    <p class="text-slate-500">Kesinti</p>
                                    <p class="mt-1 font-semibold text-amber-600">{{ $formatMoney($segment->total_deductions) }}</p>
                                </div>
                                <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">
                                    <p class="text-slate-500">Kesin kâr</p>
                                    <p class="mt-1 font-semibold {{ (float) $segment->confirmed_profit >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ $formatMoney($segment->confirmed_profit) }}</p>
                                </div>
                                <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">
                                    <p class="text-slate-500">Kâr farkı</p>
                                    <p class="mt-1 font-semibold {{ (float) $segment->total_profit_delta >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ $formatMoney($segment->total_profit_delta) }}</p>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-sm text-slate-500">Henüz kırılım üretecek finans kaydı yok.</div>
                    @endforelse
                </div>
            </section>
        </div>
    @endif

    {{-- TABLO STİLLERİ --}}
    <style>
        .mp-finance-v2-table {
            table-layout: fixed;
            width: 100%;
        }
        .mp-finance-v2-table th, .mp-finance-v2-table tbody > tr:not(.expanded-row) > td {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .mp-finance-v2-table tbody > tr.expanded-row > td {
            white-space: normal;
        }
        .col-resize-handle {
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            width: 5px;
            cursor: col-resize;
            user-select: none;
        }
        .col-resize-handle:hover,
        .col-resize-handle:active {
            background-color: #cbd5e1;
        }
        .sortable-th {
            cursor: pointer;
            user-select: none;
        }
        .sortable-th:hover {
            background-color: #f1f5f9;
        }
    </style>

    {{-- Column Resize Alpine.js Component --}}
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
                        const diff = ev.pageX - this.startX;
                        const newWidth = Math.max(40, this.startWidth + diff);
                        this.currentTh.style.width = newWidth + 'px';
                        this.currentTh.style.minWidth = newWidth + 'px';
                    };

                    const onMouseUp = () => {
                        this.resizing = false;
                        if (this.handle) this.handle.classList.remove('active');
                        this.currentTh = null;
                        document.removeEventListener('mousemove', onMouseMove);
                        document.removeEventListener('mouseup', onMouseUp);
                    };

                    document.addEventListener('mousemove', onMouseMove);
                    document.addEventListener('mouseup', onMouseUp);
                }
            }));
        });
    </script>
</div>
