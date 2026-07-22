@php
    $stats = $this->stats;
    $products = $this->products;
    $marketplaceOptions = $this->marketplaceOptions;
    $legalEntities = $this->legalEntities;
    $sidebarSummary = $this->sidebarSummary;
    $columnDefs = \App\Livewire\MpProductsManager::$allColumnDefs;
    $sortableColumns = \App\Livewire\MpProductsManager::$sortableColumns;

    $formatCount = fn ($value) => number_format((float) $value, 0, ',', '.');
    $formatMoney = fn ($value) => '₺' . number_format((float) $value, 2, ',', '.');
    $formatSignedMoney = fn ($value) => ((float) $value > 0 ? '+' : ((float) $value < 0 ? '-' : '')) . $formatMoney(abs((float) $value));
    $formatPercent = fn ($value) => $value !== null ? '%' . number_format((float) $value, 1, ',', '.') : '—';
    $formatMultiplier = fn ($value) => $value !== null ? '%' . number_format((((float) $value) - 1) * 100, 1, ',', '.') : '—';
    $marketplaceDomain = fn (?string $marketplace) => match (strtolower((string) $marketplace)) {
        'trendyol' => 'trendyol.com',
        'hepsiburada' => 'hepsiburada.com',
        'n11' => 'n11.com',
        'pazarama' => 'pazarama.com',
        'ciceksepeti' => 'ciceksepeti.com',
        'koctas' => 'koctas.com.tr',
        'amazon' => 'amazon.com.tr',
        'woocommerce' => 'woocommerce.com',
        'shopify' => 'shopify.com',
        default => null,
    };
    $marketplaceFavicon = fn (?string $marketplace) => ($marketplaceDomain($marketplace))
        ? 'https://www.google.com/s2/favicons?sz=32&domain=' . $marketplaceDomain($marketplace)
        : null;
    $marketplaceInitial = fn (?string $marketplace) => mb_substr($this->humanMarketplace($marketplace), 0, 1);
    $sortIcon = function (string $columnKey) use ($sortableColumns, $sortField, $sortDirection) {
        $dbColumn = $sortableColumns[$columnKey] ?? null;
        if (!$dbColumn) {
            return '';
        }

        return $sortField === $dbColumn
            ? ($sortDirection === 'asc' ? '▲' : '▼')
            : '⇅';
    };

    $selectedLegalEntity = $legalEntities->firstWhere('id', (int) $legalEntityFilter);
    $activeFilters = array_values(array_filter([
        $search !== '' ? 'Arama: ' . $search : null,
        $filterStatus !== 'all'
            ? 'Durum: ' . match ($filterStatus) {
                'active' => 'Satışta',
                'out_of_stock' => 'Tükendi',
                'pending' => 'Onay bekliyor',
                'suspended' => 'Beklemede',
                default => $filterStatus,
            }
            : null,
        $filterCategory !== 'all' ? 'Kategori: ' . $filterCategory : null,
        $filterBrand !== 'all' ? 'Marka: ' . $filterBrand : null,
        $marketplaceFilter !== 'all' ? 'Kanal: ' . $this->humanMarketplace($marketplaceFilter) : null,
        $listingStatusFilter !== 'all'
            ? 'Listeleme: ' . match ($listingStatusFilter) {
                'active' => 'Yayında',
                'passive' => 'Pasif',
                'draft' => 'Taslak',
                default => $listingStatusFilter,
            }
            : null,
        $listingCoverageFilter !== 'all'
            ? 'Kapsam: ' . match ($listingCoverageFilter) {
                'listed' => 'Listelenen',
                'unlisted' => 'Kanalı olmayan',
                'multi_channel' => 'Çok kanallı',
                'issues' => 'Sorunlu',
                default => $listingCoverageFilter,
            }
            : null,
        $selectedLegalEntity ? 'Firma: ' . $selectedLegalEntity->name : null,
        $filterStockLevel !== 'all'
            ? 'Stok: ' . match ($filterStockLevel) {
                'in_stock' => 'Stokta',
                'critical' => 'Kritik',
                'out_of_stock' => 'Tükendi',
                default => $filterStockLevel,
            }
            : null,
        $filterCostDefined !== 'all'
            ? 'Maliyet: ' . match ($filterCostDefined) {
                'yes' => 'Maliyet tanımlı',
                'no' => 'Maliyet eksik',
                default => $filterCostDefined,
            }
            : null,
        $recipeLinkFilter !== 'all'
            ? 'Reçete: ' . match ($recipeLinkFilter) {
                'linked' => 'Bağlı',
                'unlinked' => 'Bağlı olmayan',
                default => $recipeLinkFilter,
            }
            : null,
        $setContentFilter !== 'all'
            ? 'Set içeriği: ' . match ($setContentFilter) {
                'defined' => 'Girilenler',
                'missing' => 'Boş set kartları',
                default => $setContentFilter,
            }
            : null,
        $filterProfitComparison !== 'all' && filled($filterProfitMargin)
            ? 'Kârlılık: %' . number_format((float) $filterProfitMargin, 1, ',', '.') . ' ' . match ($filterProfitComparison) {
                'above' => 'üstü',
                'below' => 'altı',
                default => '',
            }
            : null,
        filled($filterSalePriceMin) || filled($filterSalePriceMax)
            ? 'Satış: ' . (filled($filterSalePriceMin) ? $formatMoney($filterSalePriceMin) : 'min yok') . ' - ' . (filled($filterSalePriceMax) ? $formatMoney($filterSalePriceMax) : 'max yok')
            : null,
        filled($filterCostMin) || filled($filterCostMax)
            ? 'Maliyet: ' . (filled($filterCostMin) ? $formatMoney($filterCostMin) : 'min yok') . ' - ' . (filled($filterCostMax) ? $formatMoney($filterCostMax) : 'max yok')
            : null,
        filled($filterStockMin) || filled($filterStockMax)
            ? 'Stok: ' . (filled($filterStockMin) ? $formatCount($filterStockMin) : 'min yok') . ' - ' . (filled($filterStockMax) ? $formatCount($filterStockMax) : 'max yok')
            : null,
        filled($filterDesiMin) || filled($filterDesiMax)
            ? 'Desi: ' . (filled($filterDesiMin) ? number_format((float) $filterDesiMin, 1, ',', '.') : 'min yok') . ' - ' . (filled($filterDesiMax) ? number_format((float) $filterDesiMax, 1, ',', '.') : 'max yok')
            : null,
        filled($filterReturnRateMin) || filled($filterReturnRateMax)
            ? 'İade: ' . (filled($filterReturnRateMin) ? '%' . number_format((float) $filterReturnRateMin, 1, ',', '.') : 'min yok') . ' - ' . (filled($filterReturnRateMax) ? '%' . number_format((float) $filterReturnRateMax, 1, ',', '.') : 'max yok')
            : null,
    ]));
    $advancedFiltersActive = $marketplaceFilter !== 'all'
        || $filterBrand !== 'all'
        || $listingStatusFilter !== 'all'
        || $legalEntityFilter !== 'all'
        || $filterStockLevel !== 'all'
        || $filterCostDefined !== 'all'
        || $recipeLinkFilter !== 'all'
        || $setContentFilter !== 'all'
        || ($filterProfitComparison !== 'all' && filled($filterProfitMargin))
        || filled($filterSalePriceMin)
        || filled($filterSalePriceMax)
        || filled($filterCostMin)
        || filled($filterCostMax)
        || filled($filterStockMin)
        || filled($filterStockMax)
        || filled($filterDesiMin)
        || filled($filterDesiMax)
        || filled($filterReturnRateMin)
        || filled($filterReturnRateMax);
    $showResetFilters = count($activeFilters) > 0 || $sortField !== 'product_name' || $sortDirection !== 'asc';
    $guidanceItems = collect($diagnosticsGuidance['items'] ?? []);
    $primaryGuidance = $guidanceItems->first();
    $secondaryGuidance = $guidanceItems->slice(1)->take(4)->values();
    $latestCatalogSyncAt = !empty($sidebarSummary['latest_catalog_sync'])
        ? \Illuminate\Support\Carbon::parse($sidebarSummary['latest_catalog_sync'])
        : null;
    $listedCoverage = (int) round(($stats['total_products'] ?? 0) > 0
        ? (($stats['listed_products'] ?? 0) / max(1, (int) $stats['total_products'])) * 100
        : 0);
@endphp

<div class="products-page-shell -mt-1 lg:-mt-4 w-full space-y-5 overflow-hidden lg:space-y-6"
     x-data="{
        expanded: [],
        advancedFilters: {{ $advancedFiltersActive ? 'true' : 'false' }},
        workspaceCollapsed: false,
        searchDraft: @js($search),
        searchNavigationTimer: null,
        init() {
            try {
                this.workspaceCollapsed = JSON.parse(window.localStorage.getItem('products-workspace-collapsed') ?? 'false');
            } catch (error) {
                this.workspaceCollapsed = false;
            }

            this.$watch('searchDraft', (value) => {
                window.clearTimeout(this.searchNavigationTimer);

                this.searchNavigationTimer = window.setTimeout(() => {
                    this.applySearchNavigation(value);
                }, 350);
            });
        },
        toggleWorkspace() {
            this.workspaceCollapsed = !this.workspaceCollapsed;

            try {
                window.localStorage.setItem('products-workspace-collapsed', JSON.stringify(this.workspaceCollapsed));
            } catch (error) {}
        },
        applySearchNavigation(value) {
            const nextValue = (value ?? '').trim();
            const currentValue = @js($search);

            if (nextValue === currentValue) {
                return;
            }

            const url = new URL(window.location.href);

            if (nextValue === '') {
                url.searchParams.delete('search');
            } else {
                url.searchParams.set('search', nextValue);
            }

            url.searchParams.delete('page');

            window.location.assign(url.toString());
        },
        flushSearchNavigation() {
            window.clearTimeout(this.searchNavigationTimer);
            this.applySearchNavigation(this.searchDraft);
        }
     }">
    <x-zolm.risk-guidance :guidance="$this->riskGuidance" context-label="Ürünler" />

    @once
        <style>
            .products-page-shell {
                --products-border: #e2e8f0;
            }

            .products-hero-card {
                background:
                    radial-gradient(circle at top left, rgba(59, 130, 246, 0.10), transparent 28%),
                    radial-gradient(circle at top right, rgba(15, 23, 42, 0.06), transparent 30%),
                    linear-gradient(180deg, rgba(248, 250, 252, 0.94), rgba(255, 255, 255, 1));
            }

            .products-hero-lead {
                background:
                    radial-gradient(circle at top right, rgba(96, 165, 250, 0.10), transparent 24%),
                    linear-gradient(180deg, rgba(255, 255, 255, 1), rgba(248, 250, 252, 0.94));
            }

            .products-control-surface {
                background: rgba(248, 250, 252, 0.68);
            }

            .products-tool-rail {
                background:
                    radial-gradient(circle at top right, rgba(96, 165, 250, 0.10), transparent 28%),
                    linear-gradient(180deg, rgba(248, 250, 252, 0.96), rgba(255, 255, 255, 1));
            }

            .products-stat-card {
                background: linear-gradient(180deg, #ffffff, #f8fafc);
            }

            .products-ledger-shell {
                background: #ffffff;
            }

            .mp-products-v2-table {
                table-layout: fixed;
                width: var(--products-table-width, 100%);
                min-width: var(--products-table-width, 100%);
            }

            .mp-products-v2-table th {
                white-space: nowrap;
            }

            .mp-products-v2-table tbody > tr:not(.expanded-row) > td {
                background: #ffffff;
                border-bottom: 1px solid #f1f5f9;
                transition: background 0.15s ease;
            }

            .mp-products-v2-table tbody > tr:not(.expanded-row):hover > td {
                background: #f8fafc;
            }

            .mp-products-v2-table tbody > tr:not(.expanded-row) > td {
                overflow: hidden;
            }

            .mp-products-v2-table tbody > tr:not(.expanded-row) > td.cell-actions {
                overflow: visible;
            }

            .mp-products-v2-table tbody > tr:not(.expanded-row) > td.cell-product {
                overflow: visible;
                position: relative;
            }

            .mp-products-v2-table .cell-actions-header,
            .mp-products-v2-table .cell-actions {
                position: sticky;
                right: 0;
                z-index: 20;
                box-shadow: -10px 0 14px rgba(255, 255, 255, 0.9);
            }

            .mp-products-v2-table .cell-actions-header {
                background: #f8fafc;
                z-index: 30;
            }

            .mp-products-v2-table .cell-actions.is-action-menu-open {
                z-index: 80;
            }

            .mp-products-v2-table .action-menu-panel {
                z-index: 90;
            }

            .mp-ledger-stack {
                display: grid;
                gap: 0.375rem;
                min-width: 0;
            }

            .mp-ledger-pair {
                display: grid;
                grid-template-columns: minmax(0, 3.25rem) minmax(0, 1fr);
                align-items: center;
                column-gap: 0.5rem;
                min-width: 0;
            }

            .mp-ledger-label {
                min-width: 0;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .mp-ledger-control {
                min-width: 0;
                width: 100%;
            }

            .mp-products-v2-table .product-name-clamp {
                display: -webkit-box;
                -webkit-box-orient: vertical;
                -webkit-line-clamp: 2;
                overflow: hidden;
                line-height: 1.25rem;
            }

            .mp-products-v2-table tbody > tr.expanded-row > td {
                white-space: normal;
            }

            .col-resize-handle {
                position: absolute;
                top: 0;
                right: 0;
                bottom: 0;
                width: 4px;
                cursor: col-resize;
                z-index: 10;
                background: transparent;
                transition: background 0.15s;
            }

            .col-resize-handle:hover,
            .col-resize-handle.active {
                background: #0f172a;
            }

            .products-collapse-panel {
                transition: max-height 0.32s ease, opacity 0.24s ease, transform 0.24s ease, margin 0.24s ease;
                will-change: max-height, opacity, transform;
            }

            .products-collapse-divider-line {
                height: 1px;
                background: linear-gradient(90deg, rgba(226, 232, 240, 0.15), rgba(203, 213, 225, 0.95), rgba(226, 232, 240, 0.15));
            }

            .products-collapse-button {
                box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08);
            }
        </style>
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('columnResize', () => ({
                    resizing: false,
                    startX: 0,
                    startWidth: 0,
                    currentTh: null,
                    handle: null,
                    startResize(event, th) {
                        this.resizing = true;
                        this.startX = event.pageX;
                        this.startWidth = th.offsetWidth;
                        this.currentTh = th;
                        this.handle = event.target;
                        this.handle.classList.add('active');

                        const onMouseMove = (moveEvent) => {
                            if (!this.resizing) {
                                return;
                            }

                            const newWidth = Math.max(90, this.startWidth + (moveEvent.pageX - this.startX));
                            this.currentTh.style.width = newWidth + 'px';
                            this.currentTh.style.minWidth = newWidth + 'px';
                        };

                        const onMouseUp = () => {
                            this.resizing = false;
                            if (this.handle) {
                                this.handle.classList.remove('active');
                            }

                            document.removeEventListener('mousemove', onMouseMove);
                            document.removeEventListener('mouseup', onMouseUp);
                        };

                        document.addEventListener('mousemove', onMouseMove);
                        document.addEventListener('mouseup', onMouseUp);
                    },
                }));
            });
        </script>
    @endonce

    @if($currentStatusRefreshRunIds !== [])
        <div wire:poll.4s="pollCurrentStatusRefresh" class="hidden" aria-hidden="true"></div>
    @endif

    {{-- Kısa işlem bildirimi --}}
    @if(session()->has('success') || session()->has('warning'))
        @php
            $toastTone = session()->has('success') ? 'success' : 'warning';
            $toastMessage = session('success') ?: session('warning');
        @endphp
        <div x-data="{ show: true }"
             x-init="window.setTimeout(() => show = false, 4200)"
             x-show="show"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-2 scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0 scale-100"
             x-transition:leave-end="opacity-0 translate-y-2 scale-95"
             class="fixed right-4 top-24 z-50 w-[calc(100vw-2rem)] max-w-sm rounded-[10px] border bg-white p-4 shadow-lg sm:right-6 {{ $toastTone === 'success' ? 'border-emerald-200' : 'border-amber-200' }}"
             role="status">
            <div class="flex items-start gap-3">
                <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-[8px] {{ $toastTone === 'success' ? 'bg-emerald-50 text-emerald-600' : 'bg-amber-50 text-amber-600' }}">
                    @if($toastTone === 'success')
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    @else
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                        </svg>
                    @endif
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-slate-900">{{ $toastTone === 'success' ? 'İşlem tamam' : 'Kontrol gerekli' }}</p>
                    <p class="mt-1 text-sm leading-5 text-slate-600">{{ $toastMessage }}</p>
                </div>
                <button type="button"
                        @click="show = false"
                        class="rounded-[6px] p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                        aria-label="Bildirimi kapat">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="mt-3 h-1 overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full {{ $toastTone === 'success' ? 'bg-emerald-500' : 'bg-amber-500' }}"
                     x-data
                     x-init="$el.animate([{ width: '100%' }, { width: '0%' }], { duration: 4200, easing: 'linear', fill: 'forwards' })"></div>
            </div>
        </div>
    @endif

    {{-- ═══════════════════════════════════════════════ --}}
    {{-- BLOK 1: HERO + KPI --}}
    {{-- ═══════════════════════════════════════════════ --}}
    <div class="products-collapse-panel space-y-4 overflow-hidden"
         x-bind:style="workspaceCollapsed ? 'max-height: 0px; opacity: 0; transform: translateY(-10px); margin-bottom: 0; pointer-events: none;' : 'max-height: 2600px; opacity: 1; transform: translateY(0); pointer-events: auto;'">
        <section class="products-hero-card rounded-[10px] border border-slate-200/90 p-4 shadow-sm lg:p-5">
            <div class="grid grid-cols-1 gap-3 xl:grid-cols-[minmax(420px,1.08fr)_minmax(0,0.92fr)]">
                <div class="products-hero-lead rounded-[10px] border border-slate-200 p-4 shadow-sm lg:p-6">
                    <div class="grid gap-3 xl:grid-cols-[minmax(0,1fr)_220px]">
                        <div class="min-w-0">
                            <div class="inline-flex items-center rounded-[6px] border border-slate-200 bg-slate-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">
                                Ürün Çalışma Alanı
                            </div>
                            <h1 class="mt-4 text-3xl font-bold leading-none tracking-tight text-slate-950 lg:mt-5 lg:text-[40px]">Ürün Yönetimi</h1>
                            <p class="mt-3 max-w-xl text-sm leading-6 text-slate-500">
                                Ana ürün kartlarını maliyet otoritesi olarak koruyun; kanal listelemelerini, stok ve fiyat akışlarını tek çalışma alanında yönetin.
                            </p>

                            <div class="mt-5 flex flex-col gap-3 sm:flex-row">
                                <button wire:click="openImportModal"
                                        wire:loading.attr="disabled"
                                        wire:target="openImportModal"
                                        class="inline-flex min-h-[48px] items-center justify-center gap-2 rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                    <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                    </svg>
                                    Excel İçe Aktar
                                </button>
                                <button wire:click="openCostUpdateModal"
                                        wire:loading.attr="disabled"
                                        wire:target="openCostUpdateModal"
                                        class="inline-flex min-h-[48px] items-center justify-center gap-2 rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                    <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m-4-4l4 4 4-4M5 4h14a2 2 0 012 2v3M3 15v3a2 2 0 002 2h14" />
                                    </svg>
                                    Maliyet Güncelle
                                </button>
                                {{-- COGS Sihirbazı — maliyet eksik ürünleri kategori bazlı hızlı atama --}}
                                @php $cogsMissingCount = $stats['missing_cost_products'] ?? 0; @endphp
                                <button wire:click="openCogsWizard"
                                        wire:loading.attr="disabled"
                                        wire:target="openCogsWizard"
                                        class="relative inline-flex min-h-[48px] items-center justify-center gap-2 rounded-[6px] border {{ $cogsMissingCount > 0 ? 'border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50' }} px-4 py-3 text-sm font-medium transition">
                                    <svg class="h-4 w-4 {{ $cogsMissingCount > 0 ? 'text-amber-500' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                    </svg>
                                    COGS Sihirbazı
                                    @if($cogsMissingCount > 0)
                                        <span class="absolute -right-1.5 -top-1.5 flex h-5 w-5 items-center justify-center rounded-full bg-amber-500 text-[10px] font-bold text-white">{{ $cogsMissingCount > 99 ? '99+' : $cogsMissingCount }}</span>
                                    @endif
                                </button>
                                {{-- Akıllı Eşleştirme — eşleşmeyen sipariş stok kodları için fuzzy match --}}
                                <button wire:click="openMatchWizard"
                                        wire:loading.attr="disabled"
                                        wire:target="openMatchWizard"
                                        class="relative inline-flex min-h-[48px] items-center justify-center gap-2 rounded-[6px] border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm font-medium text-indigo-700 transition hover:bg-indigo-100">
                                    <svg class="h-4 w-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                    </svg>
                                    <span wire:loading.remove wire:target="openMatchWizard">Akıllı Eşleştir</span>
                                    <span wire:loading wire:target="openMatchWizard">Yükleniyor...</span>
                                </button>
                                <button wire:click="exportExcel"
                                        wire:loading.attr="disabled"
                                        wire:target="exportExcel"
                                        class="inline-flex min-h-[48px] items-center justify-center gap-2 rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                    <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    Dışa Aktar
                                </button>
                                <button wire:click="refreshReturnRates"
                                        wire:loading.attr="disabled"
                                        wire:target="refreshReturnRates"
                                        class="inline-flex min-h-[48px] items-center justify-center gap-2 rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                    <svg wire:loading.remove wire:target="refreshReturnRates" class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v6h6M20 20v-6h-6M20 8A8 8 0 006.6 4.7M4 16a8 8 0 0013.4 3.3" />
                                    </svg>
                                    <svg wire:loading wire:target="refreshReturnRates" class="h-4 w-4 animate-spin text-slate-400" viewBox="0 0 24 24" fill="none">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                    </svg>
                                    İade Oranı
                                </button>
                                <button wire:click="openCreateModal"
                                        wire:loading.attr="disabled"
                                        wire:target="openCreateModal"
                                        class="inline-flex min-h-[48px] items-center justify-center gap-2 rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                    </svg>
                                    Yeni Ürün
                                </button>
                            </div>

                            <div class="mt-4 grid grid-cols-1 gap-2 text-xs sm:grid-cols-3 lg:mt-5">
                                <div class="rounded-[8px] border border-slate-200 bg-slate-50/80 px-3 py-3">
                                    <p class="text-slate-500">Ana görünüm</p>
                                    <p class="mt-1 font-semibold text-slate-900">Maliyet + stok otoritesi</p>
                                </div>
                                <div class="rounded-[8px] border border-slate-200 bg-slate-50/80 px-3 py-3">
                                    <p class="text-slate-500">Kontrol yüzeyi</p>
                                    <p class="mt-1 font-semibold text-slate-900">Filtre + araçlar birleşik</p>
                                </div>
                                <div class="rounded-[8px] border border-slate-200 bg-slate-50/80 px-3 py-3">
                                    <p class="text-slate-500">Kanal sağlığı</p>
                                    <p class="mt-1 font-semibold text-slate-900">Listeleme ve gönderim izleme</p>
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-3">
                            <div class="rounded-[10px] border border-slate-200 bg-white px-4 py-4">
                                <div class="flex items-center gap-1.5">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Listeleme kapsamı</p>
                                    <x-zolm.help-tip title="Listeleme kapsamı" summary="Ana ürünlerin ne kadarının en az bir kanalda listelendiğini yüzde olarak gösterir." source="Ana ürün sayısı ve aktif listeleme kayıtları." refresh="Yeni listeleme açıldığında, kapandığında veya senkron geldiğinde." impact="Kanal yayılımını ve büyüme boşluğunu hızlıca gösterir." />
                                </div>
                                <p class="mt-3 text-3xl font-bold tracking-tight text-slate-950">%{{ $listedCoverage }}</p>
                                <div class="mt-3 h-2 rounded-full bg-slate-100">
                                    <div class="h-2 rounded-full bg-slate-900 transition-all" style="width: {{ min(100, max(0, $listedCoverage)) }}%"></div>
                                </div>
                            </div>
                            <div class="rounded-[10px] border border-slate-200 bg-white px-4 py-4">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Yayında</p>
                                <p class="mt-2 text-2xl font-bold tracking-tight text-slate-950">{{ $formatCount($stats['listed_products']) }}</p>
                                <p class="mt-2 text-xs text-emerald-600">{{ $formatCount($stats['active_listings']) }} aktif listeleme</p>
                            </div>
                            <div class="rounded-[10px] border border-slate-200 bg-white px-4 py-4">
                                <div class="flex items-center gap-1.5">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Eşleşme riski</p>
                                    <x-zolm.help-tip title="Eşleşme riski" summary="Ana ürünle kanal listelemesi arasında net ilişki kurulamayan kayıtları gösterir." source="Eşleşme sorun kayıtları ve bağsız listeleme sayısı." refresh="Sorun çözüldüğünde veya yeni listeleme geldiğinde." impact="Fiyat, kâr ve stok akışındaki güvenilirliği etkiler." />
                                </div>
                                <p class="mt-2 text-2xl font-bold tracking-tight text-slate-950">{{ $formatCount($stats['pending_match_issues']) }}</p>
                                <p class="mt-2 text-xs text-amber-600">{{ $formatCount($sidebarSummary['orphan_listings'] ?? 0) }} bağsız listeleme</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-2">
                    <div class="products-stat-card rounded-[10px] border border-slate-200 p-5 shadow-sm lg:col-span-2">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="inline-flex items-center rounded-[6px] border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">
                                    Çalışma Alanı Özeti
                                </div>
                                <p class="mt-4 text-3xl font-bold tracking-tight text-slate-950 lg:text-[36px]">{{ $formatCount($stats['total_products']) }}</p>
                                <p class="mt-2 text-sm font-medium text-slate-700">toplam ana ürün</p>
                            </div>
                            <span class="rounded-[6px] bg-emerald-50 px-2.5 py-1 text-[10px] font-semibold text-emerald-600">Canlı</span>
                        </div>
                        <div class="mt-4 grid gap-2 sm:grid-cols-2">
                            <div class="rounded-[8px] border border-slate-200 bg-slate-50/80 px-4 py-3">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">Toplam listeleme</p>
                                <p class="mt-2 text-lg font-semibold tracking-tight text-slate-950">{{ $formatCount($stats['total_listings']) }}</p>
                            </div>
                            <div class="rounded-[8px] border border-slate-200 bg-slate-50/80 px-4 py-3">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">Stok Değeri</p>
                                <p class="mt-2 text-lg font-semibold tracking-tight text-slate-950">{{ $formatMoney($stats['stock_value']) }}</p>
                            </div>
                        </div>
                        <div class="mt-4 flex flex-wrap items-center gap-2 text-xs">
                            <span class="rounded-[6px] border border-slate-200 bg-white px-2.5 py-1 text-slate-600">{{ $formatCount($stats['multi_channel_products']) }} çok kanallı</span>
                            <span class="rounded-[6px] border border-slate-200 bg-white px-2.5 py-1 text-slate-600">Son katalog senkronu {{ $latestCatalogSyncAt?->format('d.m H:i') ?: 'yok' }}</span>
                        </div>
                    </div>

                    <div class="products-stat-card rounded-[10px] border border-slate-200 p-4 shadow-sm">
                        <div class="flex items-center gap-1.5">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Gönderim Hazır</p>
                            <x-zolm.help-tip title="Gönderim hazır" summary="Fiyat ve stok değişimini dış kanala göndermeye uygun profil ve mağaza hazırlığını gösterir." source="Senkron profil ayarları, mağaza yetkileri ve gönderim hazırlık kontrolleri." refresh="Profil güncellendiğinde veya mağaza hazırlığı değiştiğinde." impact="Hangi mağazalarda otomatik gönderim yapılabileceğini belirler." />
                        </div>
                        <p class="mt-3 text-2xl font-bold tracking-tight text-slate-950">{{ $formatCount($sidebarSummary['price_push_ready'] ?? 0) }}/{{ $formatCount($sidebarSummary['stock_push_ready'] ?? 0) }}</p>
                        <p class="mt-2 text-xs text-slate-500">Fiyat / stok profili hazır mağaza</p>
                    </div>
                    <div class="products-stat-card rounded-[10px] border border-slate-200 p-4 shadow-sm">
                        <div class="flex items-center gap-1.5">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Gönderim Hatası</p>
                            <x-zolm.help-tip title="Gönderim hatası" summary="Son dönemde başarısız katalog, fiyat veya stok gönderimlerini özetler." source="Katalog senkronu ve gönderim hata kayıtları." refresh="Her yeni gönderim sonucu işlendiğinde." impact="Kanalda güncel olmayan fiyat veya stok riskini işaret eder." />
                        </div>
                        <p class="mt-3 text-2xl font-bold tracking-tight text-slate-950">{{ $formatCount($sidebarSummary['failed_catalog_syncs'] ?? 0) }}</p>
                        <p class="mt-2 text-xs text-rose-600">Son 24 saatte başarısız katalog senkronu</p>
                    </div>
                </div>
            </div>
        </section>

        @include('livewire.partials.mp-guidance-banner', [
            'diagnosticsGuidance' => $diagnosticsGuidance,
            'guidanceItems' => $diagnosticsGuidance['items'] ?? [],
            'primaryGuidance' => $primaryGuidance,
            'secondaryGuidance' => $secondaryGuidance,
            'accordionStyle' => true,
            'defaultOpen' => false,
            'headerContextLabel' => 'Ürünler',
        ])
    </div>

    <div class="pb-1">
        <div class="flex items-center gap-3">
            <div class="products-collapse-divider-line flex-1"></div>
            <button type="button"
                    @click="toggleWorkspace"
                    class="products-collapse-button inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-500 transition hover:border-slate-300 hover:text-slate-900">
                <svg class="h-4 w-4 transition duration-200"
                     :class="{ 'rotate-180': workspaceCollapsed }"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div class="products-collapse-divider-line flex-1"></div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════ --}}
    {{-- BLOK 2: TABLO BAŞLANGICI --}}
    {{-- ═══════════════════════════════════════════════ --}}
    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">

        <div class="products-control-surface mb-5 rounded-[10px] border border-slate-200 p-3 lg:p-4">
            <div class="grid gap-3 xl:grid-cols-[minmax(0,1fr)_304px]">
                <div class="rounded-[10px] border border-slate-200 bg-white/90 p-3 sm:p-4">
                    <div class="flex flex-col gap-4 border-b border-slate-200/80 pb-3 xl:flex-row xl:items-start xl:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex items-center rounded-[6px] border border-slate-200 bg-slate-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">
                                    Komut Çubuğu
                                </span>
                                <span class="inline-flex items-center rounded-[6px] bg-slate-900 px-2.5 py-1 text-[11px] font-semibold text-white">
                                    {{ $formatCount($products->total()) }} ürün
                                </span>
                                @if(count($activeFilters) > 0)
                                    <span class="inline-flex items-center rounded-[6px] border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-[11px] font-semibold text-indigo-600">
                                        {{ count($activeFilters) }} aktif filtre
                                    </span>
                                @endif
                            </div>
                            <p class="mt-3 text-sm font-semibold text-slate-900">Ana ürün ve kanal akışı tek çalışma panelinde</p>
                        </div>
                        <div class="grid grid-cols-3 gap-1.5 xl:w-[288px] xl:flex-none">
                            <div class="rounded-[6px] border border-slate-200 bg-slate-50/80 px-2 py-1.5">
                                <p class="text-[9px] font-semibold uppercase tracking-[0.16em] text-slate-400">Yayında</p>
                                <p class="mt-0.5 text-[11px] font-semibold text-emerald-600">{{ $formatCount($stats['listed_products']) }}</p>
                            </div>
                            <div class="rounded-[6px] border border-slate-200 bg-slate-50/80 px-2 py-1.5">
                                <p class="text-[9px] font-semibold uppercase tracking-[0.16em] text-slate-400">Sorun</p>
                                <p class="mt-0.5 text-[11px] font-semibold text-amber-600">{{ $formatCount($stats['pending_match_issues']) }}</p>
                            </div>
                            <div class="rounded-[6px] border border-slate-200 bg-slate-50/80 px-2 py-1.5">
                                <p class="text-[9px] font-semibold uppercase tracking-[0.16em] text-slate-400">Gönderim</p>
                                <p class="mt-0.5 text-[11px] font-semibold text-rose-600">{{ $formatCount($stats['failed_pushes']) }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-3 lg:grid-cols-[minmax(0,1fr)_220px_220px]">
                        <div class="relative">
                            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <input x-model="searchDraft"
                                   @keydown.enter.prevent="flushSearchNavigation()"
                                   @search="flushSearchNavigation()"
                                   type="search"
                                   placeholder="Ürün adı, barkod, stok kodu arayın..."
                                   class="w-full rounded-[6px] border border-slate-200 bg-white py-3 pl-10 pr-4 text-sm font-medium text-slate-900 shadow-sm transition focus:border-slate-900 focus:bg-white focus:outline-none">
                        </div>
                        <select wire:model.live="filterStatus"
                                class="w-full rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                            <option value="all">Tüm Durumlar</option>
                            <option value="active">Satışta</option>
                            <option value="out_of_stock">Tükendi</option>
                            <option value="pending">Onay bekliyor</option>
                            <option value="suspended">Beklemede</option>
                        </select>
                        <select wire:model.live="filterCategory"
                                class="w-full rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                            <option value="all">Tüm Kategoriler</option>
                            @foreach($this->categories as $category)
                                <option value="{{ $category }}">{{ $category }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-[repeat(3,minmax(0,1fr))_auto]">
                        <select wire:model.live="listingCoverageFilter"
                                class="w-full rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                            <option value="all">Kanal Kapsamı</option>
                            <option value="listed">Listelenen</option>
                            <option value="unlisted">Kanalsız</option>
                            <option value="multi_channel">Çok kanallı</option>
                            <option value="issues">Sorunlu</option>
                        </select>
                        <select wire:model.live="marketplaceFilter"
                                class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                            <option value="all">Tüm Pazaryerleri</option>
                            @foreach($marketplaceOptions as $marketplace)
                                <option value="{{ $marketplace }}">{{ $this->humanMarketplace($marketplace) }}</option>
                            @endforeach
                        </select>
                        <select wire:model.live="filterBrand"
                                class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                            <option value="all">Tüm Markalar</option>
                            @foreach($this->brands as $brand)
                                <option value="{{ $brand }}">{{ $brand }}</option>
                            @endforeach
                        </select>
                        <button type="button"
                                @click="advancedFilters = !advancedFilters"
                                class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-white xl:w-auto sm:py-2">
                            <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                            </svg>
                            Gelişmiş
                        </button>
                    </div>

                    <div x-show="advancedFilters" x-cloak x-transition class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-8">
                        <select wire:model.live="listingStatusFilter"
                                class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                            <option value="all">Listeleme Durumu</option>
                            <option value="active">Yayında</option>
                            <option value="passive">Pasif</option>
                            <option value="draft">Taslak</option>
                        </select>
                        <select wire:model.live="legalEntityFilter"
                                class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                            <option value="all">Tüm Firmalar</option>
                            @foreach($legalEntities as $entity)
                                <option value="{{ $entity->id }}">{{ $entity->name }}</option>
                            @endforeach
                        </select>
                        <select wire:model.live="filterStockLevel"
                                class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                            <option value="all">Stok Seviyesi</option>
                            <option value="in_stock">Stokta</option>
                            <option value="critical">Kritik</option>
                            <option value="out_of_stock">Tükendi</option>
                        </select>
                        <select wire:model.live="filterCostDefined"
                                class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                            <option value="all">Maliyet Durumu</option>
                            <option value="yes">Maliyet tanımlı</option>
                            <option value="no">Maliyet eksik</option>
                        </select>
                        <select wire:model.live="recipeLinkFilter"
                                class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                            <option value="all">Reçete Bağı</option>
                            <option value="linked">Reçeteye bağlı</option>
                            <option value="unlinked">Reçeteye bağlı olmayan</option>
                        </select>
                        <select wire:model.live="setContentFilter"
                                class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                            <option value="all">Set İçeriği</option>
                            <option value="defined">Set içeriği girilenler</option>
                            <option value="missing">Set kartı boş olanlar</option>
                        </select>
                        <div class="grid grid-cols-[minmax(0,1fr)_88px] gap-2">
                            <select wire:model.live="filterProfitComparison"
                                    class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                                <option value="all">Kârlılık</option>
                                <option value="below">Altında</option>
                                <option value="above">Üstünde</option>
                            </select>
                            <input type="number"
                                   step="0.1"
                                   wire:model.live.debounce.500ms="filterProfitMargin"
                                   placeholder="%"
                                   class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                        </div>
                        <div class="flex items-center">
                            @if($showResetFilters)
                                <button wire:click="resetFilters"
                                        type="button"
                                        title="Filtreleri sıfırla"
                                        wire:loading.attr="disabled"
                                        wire:target="resetFilters"
                                        class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-600 transition hover:bg-rose-100 disabled:cursor-not-allowed disabled:opacity-60">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                    Sıfırla
                                </button>
                            @endif
                        </div>
                    </div>

                    <div x-show="advancedFilters" x-cloak x-transition class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
                        <div class="grid grid-cols-2 gap-2">
                            <input type="number" step="0.01" wire:model.live.debounce.500ms="filterSalePriceMin" placeholder="Satış min" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                            <input type="number" step="0.01" wire:model.live.debounce.500ms="filterSalePriceMax" placeholder="Satış max" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="number" step="0.01" wire:model.live.debounce.500ms="filterCostMin" placeholder="Maliyet min" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                            <input type="number" step="0.01" wire:model.live.debounce.500ms="filterCostMax" placeholder="Maliyet max" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="number" step="1" wire:model.live.debounce.500ms="filterStockMin" placeholder="Stok min" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                            <input type="number" step="1" wire:model.live.debounce.500ms="filterStockMax" placeholder="Stok max" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="number" step="0.1" wire:model.live.debounce.500ms="filterDesiMin" placeholder="Desi min" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                            <input type="number" step="0.1" wire:model.live.debounce.500ms="filterDesiMax" placeholder="Desi max" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="number" step="0.1" wire:model.live.debounce.500ms="filterReturnRateMin" placeholder="İade min %" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                            <input type="number" step="0.1" wire:model.live.debounce.500ms="filterReturnRateMax" placeholder="İade max %" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                        </div>
                    </div>

                    <div class="mt-3 space-y-3 xl:hidden">
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 px-4 py-3">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">Aktif filtreler</p>
                            <div class="mt-3 flex flex-wrap gap-2 text-[11px]">
                                @if(count($activeFilters) > 0)
                                    @foreach($activeFilters as $filter)
                                        <span class="rounded-[6px] border border-slate-200 bg-white px-2.5 py-1 text-slate-600">{{ $filter }}</span>
                                    @endforeach
                                @else
                                    <span class="rounded-[6px] border border-slate-200 bg-white px-2.5 py-1 text-slate-500">Aktif filtre yok</span>
                                @endif
                            </div>
                        </div>

                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 px-4 py-3">
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">Çalışma alanı özeti</p>
                                <span class="rounded-[6px] border border-slate-200 bg-white px-2 py-0.5 text-[10px] font-medium text-slate-600">
                                    {{ count($visibleColumns) }}/{{ count($columnDefs) }}
                                </span>
                            </div>
                            <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 text-xs">
                                <div class="rounded-[6px] border border-slate-200 bg-white px-3 py-2">
                                    <p class="text-slate-500">Bağlı mağaza</p>
                                    <p class="mt-1 font-medium text-slate-900">{{ $formatCount($sidebarSummary['store_count'] ?? 0) }}</p>
                                </div>
                                <div class="rounded-[6px] border border-slate-200 bg-white px-3 py-2">
                                    <p class="text-slate-500">Aktif mağaza</p>
                                    <p class="mt-1 font-medium text-slate-900">{{ $formatCount($sidebarSummary['active_store_count'] ?? 0) }}</p>
                                </div>
                                <div class="rounded-[6px] border border-slate-200 bg-white px-3 py-2">
                                    <p class="text-slate-500">Bağsız listeleme</p>
                                    <p class="mt-1 font-medium {{ (int) ($sidebarSummary['orphan_listings'] ?? 0) > 0 ? 'text-amber-600' : 'text-slate-900' }}">{{ $formatCount($sidebarSummary['orphan_listings'] ?? 0) }}</p>
                                </div>
                                <div class="rounded-[6px] border border-slate-200 bg-white px-3 py-2">
                                    <p class="text-slate-500">Son senkron</p>
                                    <p class="mt-1 font-medium text-slate-900">{{ $latestCatalogSyncAt?->format('d.m H:i') ?: 'Henüz yok' }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            @if(count($selectedProducts) > 0)
                                <div x-data="{ bulkOpen: false }" class="relative">
                                    <button @click="bulkOpen = !bulkOpen"
                                            type="button"
                                            wire:loading.attr="disabled"
                                            wire:target="bulkUpdateStatus,bulkRefreshCurrentStatus,bulkSetProfitCommissionOverride,bulkAdjustSalePrices,bulkSetTargetProfitMargin,bulkSetPackagingCost,bulkSetLogisticsInfo,bulkSetStockQuantity,bulkSetCriticalStockThreshold,bulkClearCriticalStockThreshold,bulkDelete"
                                            class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] border border-indigo-300 bg-indigo-50 px-4 py-3 text-sm font-medium text-indigo-700 transition hover:bg-indigo-100 disabled:cursor-not-allowed disabled:opacity-60">
                                        Toplu İşlem
                                        <span class="rounded-[6px] bg-indigo-100 px-1.5 py-0.5 text-[10px] font-medium text-indigo-700">{{ count($selectedProducts) }}</span>
                                    </button>
                                    <div x-show="bulkOpen"
                                         @click.outside="bulkOpen = false"
                                         x-transition
                                         class="absolute left-0 right-0 top-full z-30 mt-2 max-h-[80vh] overflow-y-auto rounded-[8px] border border-slate-200 bg-white py-1 shadow-xl sm:left-auto sm:w-72">
                                        <button wire:click="bulkUpdateStatus('active')"
                                                wire:loading.attr="disabled"
                                                wire:loading.class="cursor-wait opacity-60"
                                                wire:target="bulkUpdateStatus"
                                                @click="bulkOpen = false"
                                                class="flex w-full items-center gap-2 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed">
                                            Satışa al
                                        </button>
                                        <button wire:click="bulkUpdateStatus('suspended')"
                                                wire:loading.attr="disabled"
                                                wire:loading.class="cursor-wait opacity-60"
                                                wire:target="bulkUpdateStatus"
                                                @click="bulkOpen = false"
                                                class="flex w-full items-center gap-2 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed">
                                            Beklet
                                        </button>
                                        <div class="my-1 border-t border-slate-100"></div>
                                        <button wire:click="bulkRefreshCurrentStatus"
                                                wire:loading.attr="disabled"
                                                wire:loading.class="cursor-wait opacity-60"
                                                wire:target="bulkRefreshCurrentStatus"
                                                @click="bulkOpen = false"
                                                title="Seçili ürünlerin bağlı pazaryerlerinden güncel fiyat, stok ve kanal bilgisini al"
                                                class="flex w-full items-center gap-2 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed">
                                            <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 4v6h6M20 20v-6h-6M5.5 15A7 7 0 0018 18.5M18.5 9A7 7 0 006 5.5" />
                                            </svg>
                                            Güncel durum al
                                        </button>
                                        <div class="my-1 border-t border-slate-100"></div>
                                        <button wire:click="bulkSetProfitCommissionOverride(true)"
                                                wire:loading.attr="disabled"
                                                wire:loading.class="cursor-wait opacity-60"
                                                wire:target="bulkSetProfitCommissionOverride"
                                                @click="bulkOpen = false"
                                                class="flex w-full items-center gap-2 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed">
                                            Manuel komisyonu aç
                                        </button>
                                        <button wire:click="bulkSetProfitCommissionOverride(false)"
                                                wire:loading.attr="disabled"
                                                wire:loading.class="cursor-wait opacity-60"
                                                wire:target="bulkSetProfitCommissionOverride"
                                                @click="bulkOpen = false"
                                                class="flex w-full items-center gap-2 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed">
                                            Manuel komisyonu kapat
                                        </button>
                                        <div class="my-1 border-t border-slate-100"></div>
                                        <div class="px-3 py-2">
                                            <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">Fiyat güncelle</p>
                                            <select wire:model.defer="bulkPriceTarget"
                                                    class="mt-2 min-h-[38px] w-full rounded-[6px] border border-slate-200 bg-white px-3 text-base text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                                                <option value="all">Tümü (ana + kanal)</option>
                                                @foreach($marketplaceOptions as $marketplace)
                                                    <option value="marketplace:{{ $marketplace }}">{{ $this->humanMarketplace($marketplace) }}</option>
                                                @endforeach
                                            </select>
                                            <div class="mt-2 flex gap-2">
                                                <select wire:model.defer="bulkPriceDirection"
                                                        class="min-h-[38px] w-[88px] shrink-0 rounded-[6px] border border-slate-200 bg-white px-2 text-base text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                                                    <option value="increase">Artır</option>
                                                    <option value="decrease">Düşür</option>
                                                </select>
                                                <input type="number"
                                                       min="0"
                                                       step="0.01"
                                                       wire:model.defer="bulkPricePercent"
                                                       placeholder="%"
                                                       class="min-h-[38px] min-w-0 w-full rounded-[6px] border border-slate-200 bg-white px-3 text-base text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                                                <button type="button"
                                                        wire:click="bulkAdjustSalePrices"
                                                        wire:loading.attr="disabled"
                                                        wire:target="bulkAdjustSalePrices"
                                                        title="Seçili ürünlerin satış fiyatını yüzde olarak güncelle"
                                                        class="inline-flex min-h-[38px] shrink-0 items-center justify-center rounded-[6px] bg-slate-900 px-3 text-xs font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60">
                                                    Uygula
                                                </button>
                                            </div>
                                            @error('bulkPricePercent')
                                                <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>
                                            @enderror
                                            @error('bulkPriceTarget')
                                                <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>
                                            @enderror
                                            @error('bulkPriceDirection')
                                                <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>
                                            @enderror
                                        </div>
                                        <div class="my-1 border-t border-slate-100"></div>
                                        <div class="px-3 py-2">
                                            <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">Kârlılık hedefle</p>
                                            <select wire:model.defer="bulkProfitTarget"
                                                    class="mt-2 min-h-[38px] w-full rounded-[6px] border border-slate-200 bg-white px-3 text-base text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                                                <option value="all">Tümü (ana + kanal)</option>
                                                @foreach($marketplaceOptions as $marketplace)
                                                    <option value="marketplace:{{ $marketplace }}">{{ $this->humanMarketplace($marketplace) }}</option>
                                                @endforeach
                                            </select>
                                            <div class="mt-2 flex gap-2">
                                                <input type="number"
                                                       min="-99"
                                                       max="1000"
                                                       step="0.1"
                                                       wire:model.defer="bulkProfitTargetMargin"
                                                       placeholder="%"
                                                       class="min-h-[38px] min-w-0 w-full rounded-[6px] border border-slate-200 bg-white px-3 text-base text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                                                <button type="button"
                                                        wire:click="bulkSetTargetProfitMargin"
                                                        wire:loading.attr="disabled"
                                                        wire:target="bulkSetTargetProfitMargin"
                                                        title="Seçili ürünlerin fiyatını hedef kârlılık yüzdesine göre güncelle"
                                                        class="inline-flex min-h-[38px] shrink-0 items-center justify-center rounded-[6px] bg-slate-900 px-3 text-xs font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60">
                                                    Sabitle
                                                </button>
                                            </div>
                                            @error('bulkProfitTargetMargin')
                                                <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>
                                            @enderror
                                            @error('bulkProfitTarget')
                                                <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>
                                            @enderror
                                        </div>
                                        <div class="my-1 border-t border-slate-100"></div>
                                        <div class="px-3 py-2">
                                            <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">Ambalaj fiyatı gir</p>
                                            <div class="mt-2 flex gap-2">
                                                <input type="number"
                                                       min="0"
                                                       step="0.01"
                                                       wire:model.defer="bulkPackagingCost"
                                                       placeholder="₺"
                                                       class="min-h-[38px] min-w-0 w-full rounded-[6px] border border-slate-200 bg-white px-3 text-base text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                                                <button type="button"
                                                        wire:click="bulkSetPackagingCost"
                                                        wire:loading.attr="disabled"
                                                        wire:target="bulkSetPackagingCost"
                                                        title="Seçili ürünlerin ambalaj fiyatını güncelle"
                                                        class="inline-flex min-h-[38px] shrink-0 items-center justify-center rounded-[6px] bg-slate-900 px-3 text-xs font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60">
                                                    Güncelle
                                                </button>
                                            </div>
                                            @error('bulkPackagingCost')
                                                <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>
                                            @enderror
                                        </div>
                                        <div class="my-1 border-t border-slate-100"></div>
                                        <div class="px-3 py-2">
                                            <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">Lojistik bilgisi gir</p>
                                            <div class="mt-2 grid grid-cols-3 gap-2">
                                                <input type="number"
                                                       min="0"
                                                       step="0.01"
                                                       wire:model.defer="bulkCargoCost"
                                                       placeholder="₺"
                                                       class="min-h-[44px] min-w-0 rounded-[6px] border border-slate-200 bg-white px-2 text-base text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                                                <input type="number"
                                                       min="0"
                                                       step="0.01"
                                                       wire:model.defer="bulkDesi"
                                                       placeholder="Desi"
                                                       class="min-h-[44px] min-w-0 rounded-[6px] border border-slate-200 bg-white px-2 text-base text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                                                <input type="number"
                                                       min="1"
                                                       step="1"
                                                       wire:model.defer="bulkPieces"
                                                       placeholder="Parça"
                                                       class="min-h-[44px] min-w-0 rounded-[6px] border border-slate-200 bg-white px-2 text-base text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                                            </div>
                                            <button type="button"
                                                    wire:click="bulkSetLogisticsInfo"
                                                    wire:loading.attr="disabled"
                                                    wire:target="bulkSetLogisticsInfo"
                                                    title="Seçili ürünlerin lojistik tutar, desi ve parça bilgisini güncelle"
                                                    class="mt-2 inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] bg-slate-900 px-3 text-xs font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60">
                                                Tutar / desi / parça güncelle
                                            </button>
                                            @error('bulkCargoCost')
                                                <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>
                                            @enderror
                                            @error('bulkDesi')
                                                <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>
                                            @enderror
                                            @error('bulkPieces')
                                                <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>
                                            @enderror
                                        </div>
                                        <div class="my-1 border-t border-slate-100"></div>
                                        <div class="px-3 py-2">
                                            <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">Stok güncelle</p>
                                            <select wire:model.defer="bulkStockTarget"
                                                    class="mt-2 min-h-[38px] w-full rounded-[6px] border border-slate-200 bg-white px-3 text-base text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                                                <option value="all">Tümü (ana + kanal)</option>
                                                @foreach($marketplaceOptions as $marketplace)
                                                    <option value="marketplace:{{ $marketplace }}">{{ $this->humanMarketplace($marketplace) }}</option>
                                                @endforeach
                                            </select>
                                            <div class="mt-2 flex gap-2">
                                                <input type="number"
                                                       min="0"
                                                       step="1"
                                                       wire:model.defer="bulkStockQuantity"
                                                       placeholder="Adet"
                                                       class="min-h-[38px] w-full rounded-[6px] border border-slate-200 bg-white px-3 text-base text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                                                <button type="button"
                                                        wire:click="bulkSetStockQuantity"
                                                        wire:loading.attr="disabled"
                                                        wire:target="bulkSetStockQuantity"
                                                        title="Seçili ürünlerin stok adedini güncelle"
                                                        class="inline-flex min-h-[38px] shrink-0 items-center justify-center rounded-[6px] bg-slate-900 px-3 text-xs font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60">
                                                    Güncelle
                                                </button>
                                            </div>
                                            @error('bulkStockQuantity')
                                                <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>
                                            @enderror
                                            @error('bulkStockTarget')
                                                <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>
                                            @enderror
                                        </div>
                                        <div class="my-1 border-t border-slate-100"></div>
                                        <div class="px-3 py-2">
                                            <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">Kritik stok</p>
                                            <div class="mt-2 flex gap-2">
                                                <input type="number"
                                                       min="0"
                                                       step="1"
                                                       wire:model.defer="bulkCriticalStockThreshold"
                                                       placeholder="Eşik"
                                                       class="min-h-[38px] w-full rounded-[6px] border border-slate-200 bg-white px-3 text-base text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                                                <button type="button"
                                                        wire:click="bulkSetCriticalStockThreshold"
                                                        wire:loading.attr="disabled"
                                                        wire:target="bulkSetCriticalStockThreshold"
                                                        title="Seçili ürünlere kritik stok eşiğini uygula"
                                                        class="inline-flex min-h-[38px] shrink-0 items-center justify-center rounded-[6px] bg-slate-900 px-3 text-xs font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60">
                                                    Uygula
                                                </button>
                                            </div>
                                            @error('bulkCriticalStockThreshold')
                                                <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>
                                            @enderror
                                            <button type="button"
                                            wire:click="bulkClearCriticalStockThreshold"
                                            wire:loading.attr="disabled"
                                            wire:target="bulkClearCriticalStockThreshold"
                                            class="mt-2 text-xs font-medium text-slate-500 transition hover:text-slate-900 disabled:cursor-not-allowed disabled:opacity-60">
                                                Eşiği kaldır
                                            </button>
                                        </div>
                                        <div class="my-1 border-t border-slate-100"></div>
                                        <button wire:click="bulkDelete"
                                                wire:loading.attr="disabled"
                                                wire:loading.class="cursor-wait opacity-60"
                                                wire:target="bulkDelete"
                                                wire:confirm="Seçili ürünleri silmek istediğinize emin misiniz?"
                                                @click="bulkOpen = false"
                                                class="flex w-full items-center gap-2 px-3 py-2 text-sm text-rose-600 transition hover:bg-rose-50 disabled:cursor-not-allowed">
                                            Sil
                                        </button>
                                    </div>
                                </div>
                            @endif

                            @if($showResetFilters)
                                <button wire:click="resetFilters"
                                        type="button"
                                        wire:loading.attr="disabled"
                                        wire:target="resetFilters"
                                        class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-white">
                                    Filtreleri temizle
                                </button>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="products-tool-rail hidden xl:block rounded-[10px] border border-slate-200 p-4 text-slate-900 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Çalışma Alanı Araçları</p>
                            <p class="mt-2 text-lg font-semibold text-slate-900">Görünüm ve kanal sağlığı</p>
                        </div>
                        <span class="rounded-[6px] border border-slate-200 bg-white px-2.5 py-1 text-[10px] font-semibold text-slate-500">
                            {{ count($visibleColumns) }}/{{ count($columnDefs) }}
                        </span>
                    </div>

                    <div class="mt-4 space-y-3">
                        <div class="rounded-[8px] border border-slate-200 bg-white/90 px-4 py-3">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">Aktif filtreler</p>
                            <div class="mt-3 flex flex-wrap gap-2 text-[11px]">
                                @if(count($activeFilters) > 0)
                                    @foreach($activeFilters as $filter)
                                        <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2.5 py-1 text-slate-600">{{ $filter }}</span>
                                    @endforeach
                                @else
                                    <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2.5 py-1 text-slate-500">Aktif filtre yok</span>
                                @endif
                            </div>
                        </div>

                        <div class="rounded-[8px] border border-slate-200 bg-white/90 px-4 py-3 text-sm text-slate-500">
                            <div class="flex items-start justify-between gap-3">
                                <span>Bağlı mağaza</span>
                                <span class="font-medium text-slate-900">{{ $formatCount($sidebarSummary['store_count'] ?? 0) }}</span>
                            </div>
                            <div class="mt-2 flex items-start justify-between gap-3">
                                <span>Aktif mağaza</span>
                                <span class="font-medium text-slate-900">{{ $formatCount($sidebarSummary['active_store_count'] ?? 0) }}</span>
                            </div>
                            <div class="mt-2 flex items-start justify-between gap-3">
                                <span>Gönderim hazır</span>
                                <span class="font-medium text-slate-900">{{ $formatCount(($sidebarSummary['price_push_ready'] ?? 0) + ($sidebarSummary['stock_push_ready'] ?? 0)) }}</span>
                            </div>
                            <div class="mt-2 flex items-start justify-between gap-3">
                                <span>Bağsız listeleme</span>
                                <span class="font-medium {{ (int) ($sidebarSummary['orphan_listings'] ?? 0) > 0 ? 'text-amber-600' : 'text-slate-900' }}">{{ $formatCount($sidebarSummary['orphan_listings'] ?? 0) }}</span>
                            </div>
                            <div class="mt-2 flex items-start justify-between gap-3">
                                <span>Son katalog senkronu</span>
                                <span class="font-medium text-slate-900">{{ $latestCatalogSyncAt?->format('d.m H:i') ?: 'Henüz yok' }}</span>
                            </div>
                        </div>

                        @if($showResetFilters)
                            <button wire:click="resetFilters"
                                    type="button"
                                    wire:loading.attr="disabled"
                                    wire:target="resetFilters"
                                    class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-white">
                                Filtreleri Temizle
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>

    {{-- ═══════════════════════════════════════════════ --}}
    {{-- TABLO --}}
    {{-- ═══════════════════════════════════════════════ --}}
    <div class="mb-3 rounded-[8px] border border-slate-200 bg-white/90 px-4 py-3 md:hidden">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Ürün Kayıt Defteri</p>
                <p class="mt-1 text-sm font-semibold text-slate-900">Canlı ürün tablosu</p>
            </div>
            <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2.5 py-1 text-[10px] font-medium text-slate-600">
                {{ count($visibleColumns) }}/{{ count($columnDefs) }}
            </span>
        </div>
        <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-slate-500">
            <span>{{ $formatCount($products->total()) }} ürün</span>
            <span wire:loading.delay.short
                  wire:target="search,filterStatus,filterCategory,listingCoverageFilter,marketplaceFilter,filterBrand,listingStatusFilter,legalEntityFilter,filterStockLevel,filterCostDefined,recipeLinkFilter,setContentFilter,perPage,resetFilters,sortTable,refreshCommissionRates"
                  class="inline-flex items-center gap-1.5 text-xs text-indigo-600">
                <svg class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="9" class="opacity-25" stroke="currentColor" stroke-width="3"></circle>
                    <path d="M21 12a9 9 0 0 0-9-9" class="opacity-75" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path>
                </svg>
                Liste güncelleniyor
            </span>
        </div>
        <button type="button"
                wire:click="refreshCommissionRates"
                wire:loading.attr="disabled"
                wire:target="refreshCommissionRates"
                class="mt-3 inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60">
            <svg wire:loading.remove wire:target="refreshCommissionRates" class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v6h6M20 20v-6h-6M5.64 16.36A8 8 0 0018.36 18M18.36 7.64A8 8 0 005.64 6" />
            </svg>
            <svg wire:loading wire:target="refreshCommissionRates" class="h-4 w-4 animate-spin text-slate-400" viewBox="0 0 24 24" fill="none">
                <circle cx="12" cy="12" r="9" class="opacity-25" stroke="currentColor" stroke-width="3"></circle>
                <path d="M21 12a9 9 0 0 0-9-9" class="opacity-75" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path>
            </svg>
            <span wire:loading.remove wire:target="refreshCommissionRates">Komisyonları yenile</span>
            <span wire:loading wire:target="refreshCommissionRates">Yenileniyor...</span>
        </button>
    </div>

    {{-- Mobil kart görünümü --}}
    <div class="space-y-3 md:hidden">
        @forelse($products as $product)
            @php
                $listingCount = (int) ($product->listing_count_metric ?? 0);
                $channelStock = (int) ($product->channel_stock_total_metric ?? 0);
                $channelListings = ($product->channelListings ?? collect())->values();
                $activeListingCount = (int) ($product->active_listing_count_metric ?? 0);
                $pendingIssueCount = (int) ($product->pending_issue_count_metric ?? 0);
                $commissionScenarios = $this->productCommissionScenarios($product);
                $selectedProfitScenario = $this->selectedProductCommissionScenario($product, $commissionScenarios);
                $profitValue = (float) ($selectedProfitScenario['profit'] ?? $product->profit_metric ?? 0);
                $profitMargin = $selectedProfitScenario['profit_margin'] ?? null;
                $displaySalePrice = (float) ($selectedProfitScenario['sale_price'] ?? $product->sale_price ?? 0);
                $selectedReceivable = (float) ($selectedProfitScenario['receivable'] ?? 0);
                $selectedCommissionAmount = (float) ($selectedProfitScenario['commission_amount'] ?? 0);
                $selectedCostPlusPackaging = (float) ($selectedProfitScenario['cost_plus_packaging'] ?? (((float) ($product->cogs ?? 0)) + ((float) ($product->packaging_cost ?? 0))));
                $selectedCargoCost = (float) ($selectedProfitScenario['cargo_cost'] ?? $product->cargo_cost ?? 0);
                $selectedExtraCostFixed = (float) ($selectedProfitScenario['extra_cost_fixed'] ?? $product->extra_cost_fixed ?? 0);
                $selectedExtraCostPercentageAmount = (float) ($selectedProfitScenario['extra_cost_percentage_amount'] ?? ($displaySalePrice * (((float) ($product->extra_cost_percentage ?? 0)) / 100)));
                $selectedExtraCostTotal = $selectedExtraCostFixed + $selectedExtraCostPercentageAmount;
                $selectedScenarioLabel = (string) ($selectedProfitScenario['selection_label'] ?? $selectedProfitScenario['label'] ?? $this->productProfitDefaultMarketplaceLabel());
                $hasDistinctStockCode = filled($product->stock_code) && $product->stock_code !== $product->barcode;
                $desiValue = (float) ($product->desi ?? 0);
                $desiLabel = floor($desiValue) === $desiValue
                    ? number_format($desiValue, 0, ',', '.')
                    : number_format($desiValue, 1, ',', '.');
                $shippingSummary = ((int) ($product->pieces ?? 1)) . 'P ' . $desiLabel . 'D';
                $signedProfitLabel = $formatSignedMoney($profitValue);
                $channelCommissionRate = (float) ($selectedProfitScenario['commission_rate'] ?? $product->channel_commission_rate_metric ?? $product->commission_rate ?? 0);
                $channelCommissionLabel = $channelCommissionRate > 0
                    ? '%' . number_format($channelCommissionRate, 1, ',', '.') . ' kom.'
                    : 'kom. kanal bazlı';
                $deliverySummary = $this->productDeliverySummary($product);
                $activeRecipeCount = (int) ($product->active_recipe_count_metric ?? 0);
                $activeRecipeId = (int) ($product->active_recipe_id_metric ?? 0);
                $isSetProduct = (bool) ($product->is_set_product ?? false) || (bool) $product->productSet;
                $setComponentCount = $product->productSet?->items?->count() ?? 0;
            @endphp

            <article class="overflow-visible rounded-[8px] border border-slate-200 bg-white shadow-sm">
                <div class="p-4">
                    <div class="flex items-start gap-3">
                        <input type="checkbox"
                               value="{{ $product->id }}"
                               wire:model.live="selectedProducts"
                               @click.stop
                               class="mt-1 rounded border-slate-300 text-slate-900 shadow-sm focus:ring-slate-200">

                        <button type="button"
                                class="min-w-0 flex-1 text-left"
                                @click="expanded.includes({{ $product->id }}) ? expanded = expanded.filter(i => i !== {{ $product->id }}) : expanded.push({{ $product->id }})">
                            <div class="flex items-start gap-3">
                                @if($product->main_image)
                                    <img src="{{ $product->main_image }}" alt="" class="h-14 w-14 rounded-[8px] border border-slate-200 object-cover">
                                @else
                                    <div class="flex h-14 w-14 items-center justify-center rounded-[8px] border border-slate-200 bg-slate-50 text-slate-400">
                                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                @endif

                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start gap-2">
                                        <div class="flex min-w-0 flex-1 items-start gap-1.5">
                                            <p class="product-name-clamp min-w-0 text-sm font-semibold text-slate-900">{{ $product->product_name ?: 'İsimsiz ürün' }}</p>
                                            @if($activeRecipeCount > 0)
                                                <x-zolm.recipe-verified-badge
                                                    class="mt-0.5"
                                                    :count="$activeRecipeCount"
                                                    :recipe-id="$activeRecipeId ?: null"
                                                    :stock-code="$product->stock_code" />
                                            @endif
                                            @if($isSetProduct)
                                                @include('livewire.partials.mp-products-set-badge', [
                                                    'product' => $product,
                                                    'badgeLabel' => 'Set · ' . $setComponentCount . ' bileşen',
                                                    'popoverFocusable' => false,
                                                ])
                                            @endif
                                        </div>
                                        <svg class="mt-0.5 h-4 w-4 shrink-0 text-slate-400 transition"
                                             :class="{ 'rotate-90 text-slate-900': expanded.includes({{ $product->id }}) }"
                                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </div>
                                    <p class="mt-1 text-xs text-slate-500">
                                        {{ $product->barcode ?: '-' }}@if($hasDistinctStockCode) · {{ $product->stock_code }}@endif
                                    </p>
                                    <div class="mt-1 flex min-w-0 flex-wrap items-center gap-1.5 text-[11px] text-slate-400">
                                        @if($product->brand)
                                            <span>{{ $product->brand }}</span>
                                        @endif
                                        @if($product->category_name)
                                            <span>· {{ $product->category_name }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 grid grid-cols-2 gap-2">
                                <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-3 py-2">
                                    <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">Satış</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ $formatMoney($displaySalePrice) }}</p>
                                    <p class="mt-0.5 text-[11px] text-slate-500">{{ $channelCommissionLabel }}</p>
                                </div>
                                <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-3 py-2">
                                    <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">Kârlılık</p>
                                    <p class="mt-1 text-sm font-semibold {{ $profitValue >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ $formatMultiplier($profitMargin) }}</p>
                                    <p class="mt-0.5 text-[11px] font-semibold {{ $profitValue >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ $signedProfitLabel }}</p>
                                </div>
                                <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-3 py-2">
                                    <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">Kanal</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ $listingCount }} mağaza</p>
                                    <p class="mt-0.5 truncate text-[11px] {{ $pendingIssueCount > 0 ? 'text-amber-600' : 'text-emerald-600' }}">{{ $activeListingCount }}/{{ $listingCount }} yayında{{ $pendingIssueCount > 0 ? ' · sorun var' : '' }} · {{ $deliverySummary['label'] }}</p>
                                </div>
                                <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-3 py-2">
                                    <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">Stok</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ $formatCount($product->stock_quantity) }}</p>
                                    <p class="mt-0.5 text-[11px] text-slate-500">Kanal {{ $formatCount($channelStock) }}</p>
                                </div>
                                <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-3 py-2">
                                    <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">Maliyet</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ $formatMoney($selectedCostPlusPackaging) }}</p>
                                    <p class="mt-0.5 text-[11px] text-slate-500">Amb. {{ $formatMoney($product->packaging_cost ?? 0) }}</p>
                                </div>
                                <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-3 py-2">
                                    <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">Lojistik</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ $shippingSummary }}</p>
                                    <p class="mt-0.5 text-[11px] {{ $selectedCargoCost > 0 ? 'text-amber-600' : 'text-slate-500' }}">{{ $selectedCargoCost > 0 ? $formatMoney($selectedCargoCost) : 'Kargo yok' }}</p>
                                </div>
                                <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-3 py-2">
                                    <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">Ek Gider</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ $formatMoney($selectedExtraCostTotal) }}</p>
                                    <p class="mt-0.5 text-[11px] text-slate-500">Sabit {{ $formatMoney($selectedExtraCostFixed) }}</p>
                                </div>
                                <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-3 py-2">
                                    <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">İade</p>
                                    <p class="mt-1 text-sm font-semibold {{ (float) ($product->return_rate ?? 0) >= 15 ? 'text-rose-600' : 'text-slate-900' }}">{{ $formatPercent($product->return_rate) }}</p>
                                    <p class="mt-0.5 truncate text-[11px] text-slate-500" title="{{ $deliverySummary['title'] }}">{{ $deliverySummary['detail'] }}</p>
                                </div>
                            </div>

                            <div class="mt-3 flex items-center justify-between gap-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <x-zolm.status-badge size="sm" :tone="$product->status === 'active' ? 'success' : ($product->status === 'out_of_stock' ? 'danger' : 'warning')">
                                        {{ $product->status_label }}
                                    </x-zolm.status-badge>
                                    <span class="rounded-[6px] bg-indigo-50 px-2 py-0.5 text-[10px] font-semibold text-indigo-700">%{{ number_format((float) ($product->vat_rate ?? 0), 0, ',', '.') }} KDV</span>
                                </div>
                                <span class="text-[11px] font-medium text-slate-500">Hak. {{ $formatMoney($selectedReceivable) }}</span>
                            </div>
                        </button>
                    </div>
                </div>

                <div x-show="expanded.includes({{ $product->id }})" x-cloak x-transition class="border-t border-slate-200 bg-slate-50/60 p-4">
                    @include('livewire.partials.mp-product-v2-detail', ['product' => $product])
                </div>
            </article>
        @empty
            <div class="rounded-[8px] border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center text-sm text-slate-500">
                @if($showResetFilters)
                    <p>Bu filtrelerle eşleşen ürün bulunamadı.</p>
                    <button type="button"
                            wire:click="resetFilters"
                            class="mt-4 inline-flex min-h-[44px] items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                        Filtreleri temizle
                    </button>
                @else
                    Kayıtlı ana ürün bulunamadı. Excel içe aktarma veya manuel ürün ekleme ile başlayabilirsiniz.
                @endif
            </div>
        @endforelse
    </div>

    {{-- Desktop tablo --}}
    <div class="products-ledger-shell hidden md:block rounded-[10px] border border-slate-200 p-3 shadow-sm">
        @php
            $columnMeta = [
                'urun' => ['label' => 'Ürün', 'width' => '380px'],
                'kanal' => ['label' => 'Kanal', 'width' => '124px'],
                'fiyat' => ['label' => 'Satış', 'width' => '160px'],
                'cogs' => ['label' => 'Maliyet', 'width' => '160px'],
                'kargo' => ['label' => 'Lojistik', 'width' => '146px'],
                'ek_gider' => ['label' => 'Ek Gider', 'width' => '154px'],
                'stok' => ['label' => 'Stok', 'width' => '84px'],
                'kritik_stok' => ['label' => 'Kritik', 'width' => '92px'],
                'kdv' => ['label' => 'KDV', 'width' => '78px'],
                'maliyet_kdv' => ['label' => 'Maliyet KDV', 'width' => '112px'],
                'desi' => ['label' => 'Desi', 'width' => '86px'],
                'iade' => ['label' => 'İade', 'width' => '96px'],
                'teslimat' => ['label' => 'Termin', 'width' => '88px'],
                'roi' => ['label' => 'Kâr', 'width' => '112px'],
                'durum' => ['label' => 'Durum', 'width' => '92px'],
                'islem' => ['label' => 'İşlem', 'width' => '64px'],
            ];
            $columnHelp = [
                'cogs' => [
                    'title' => 'Maliyet',
                    'summary' => 'Ürünün birim maliyetini gösterir; kâr hesabının ana maliyet ayağıdır.',
                    'source' => 'Ana ürün kartındaki maliyet alanı.',
                    'refresh' => 'Ürün düzenleme veya Excel içe aktarım sonrası.',
                    'impact' => 'Kâr oranı ve gerçek kâr hesaplarının doğruluğunu belirler.',
                ],
                'kdv' => [
                    'title' => 'KDV',
                    'summary' => 'Ürünün satışa uygulanan KDV oranını gösterir.',
                    'source' => 'Ana ürün verisi ve kanal satış kuralı.',
                    'refresh' => 'Ürün kartı güncellendiğinde.',
                    'impact' => 'Net gelir ve vergi etkisi hesaplarında kullanılır.',
                ],
                'ek_gider' => [
                    'title' => 'Ek Gider',
                    'summary' => 'Reklam, fire veya kanal dışı operasyon giderlerini sabit ve yüzdesel olarak gösterir.',
                    'source' => 'Ana ürün kartındaki ek gider alanları.',
                    'refresh' => 'Satır içi düzenleme, Excel veya ürün formu sonrası.',
                    'impact' => 'Kâr hesabını çoklu pazaryerlerinde daha gerçekçi yapar.',
                ],
                'iade' => [
                    'title' => 'İade Oranı',
                    'summary' => 'Ürünün sipariş geçmişinden hesaplanan veya manuel girilen iade oranıdır.',
                    'source' => 'Kanal siparişleri, iade statüleri veya manuel ürün kartı.',
                    'refresh' => 'İade oranı hesapla aksiyonu veya manuel güncelleme sonrası.',
                    'impact' => 'Riskli ürünleri ve fiyat/maliyet baskısını erkenden görünür kılar.',
                ],
                'teslimat' => [
                    'title' => 'Termin',
                    'summary' => 'Pazaryeri ürün senkronundan gelen mağaza bazlı sevkiyat süresini gösterir.',
                    'source' => 'Kanal listing katalog verisi; yoksa ana ürün teslimat alanı.',
                    'refresh' => 'Ürün senkronu çalıştığında veya manuel ürün kartı güncellendiğinde.',
                    'impact' => 'Aynı ürünün mağazalar arasındaki teslimat vaadini karşılaştırmayı sağlar.',
                ],
                'roi' => [
                    'title' => 'Kârlılık',
                    'summary' => 'Hakedişten kargo ve giderler düştükten sonra kalan net kârın ürün maliyetine oranını verir.',
                    'source' => 'Satış fiyatı, maliyet, komisyon, kargo ve KDV verileri.',
                    'refresh' => 'Ürün veya finans parametresi değiştiğinde yeniden hesaplanır.',
                    'impact' => 'Hızlı fiyatlama ve kârlılık kararlarını yönlendirir.',
                ],
            ];
            $tableWidthPx = 36;
            foreach ($columnMeta as $columnKey => $meta) {
                if (in_array($columnKey, $visibleColumns, true)) {
                    $tableWidthPx += (int) str_replace('px', '', $meta['width'] ?? '120');
                }
            }
            $tableWidthPx += 1;
        @endphp

        <div class="mb-3 flex flex-col gap-3 rounded-[8px] border border-slate-200 bg-white/90 px-4 py-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Ürün Kayıt Defteri</p>
                <p class="mt-1 text-sm font-semibold text-slate-900">Canlı ürün tablosu</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2.5 py-1 text-slate-600">{{ $formatCount($products->total()) }} ürün</span>
                <button type="button"
                        wire:click="refreshCommissionRates"
                        wire:loading.attr="disabled"
                        wire:target="refreshCommissionRates"
                        class="inline-flex min-h-[36px] items-center justify-center gap-1.5 rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                        title="Tüm uygun mağazalarda ürün senkronu başlatıp komisyon oranlarını tazeler">
                    <svg wire:loading.remove wire:target="refreshCommissionRates" class="h-3.5 w-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v6h6M20 20v-6h-6M5.64 16.36A8 8 0 0018.36 18M18.36 7.64A8 8 0 005.64 6" />
                    </svg>
                    <svg wire:loading wire:target="refreshCommissionRates" class="h-3.5 w-3.5 animate-spin text-slate-400" viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="12" r="9" class="opacity-25" stroke="currentColor" stroke-width="3"></circle>
                        <path d="M21 12a9 9 0 0 0-9-9" class="opacity-75" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path>
                    </svg>
                    <span wire:loading.remove wire:target="refreshCommissionRates">Komisyonları yenile</span>
                    <span wire:loading wire:target="refreshCommissionRates">Yenileniyor...</span>
                </button>
                <div class="hidden md:block">
                    @include('livewire.partials.mp-column-toggle', ['columnDefs' => $columnDefs, 'visibleColumns' => $visibleColumns])
                </div>

                @if(count($selectedProducts) > 0)
                    <div x-data="{ bulkOpen: false }" class="relative w-full sm:w-auto">
                        <button @click="bulkOpen = !bulkOpen"
                                type="button"
                                wire:loading.attr="disabled"
                                wire:target="bulkUpdateStatus,bulkRefreshCurrentStatus,bulkSetProfitCommissionOverride,bulkAdjustSalePrices,bulkSetTargetProfitMargin,bulkSetPackagingCost,bulkSetLogisticsInfo,bulkSetStockQuantity,bulkSetCriticalStockThreshold,bulkClearCriticalStockThreshold,bulkDelete"
                                class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] border border-indigo-300 bg-indigo-50 px-4 py-3 text-sm font-medium text-indigo-700 transition hover:bg-indigo-100 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto sm:py-2">
                            Toplu İşlem
                            <span class="rounded-[6px] bg-indigo-100 px-1.5 py-0.5 text-[10px] font-medium text-indigo-700">{{ count($selectedProducts) }}</span>
                        </button>
                        <div x-show="bulkOpen"
                             @click.outside="bulkOpen = false"
                             x-transition
                             class="absolute right-0 top-full z-30 mt-2 max-h-[80vh] w-72 overflow-y-auto rounded-[8px] border border-slate-200 bg-white py-1 shadow-xl">
                            <button wire:click="bulkUpdateStatus('active')"
                                    wire:loading.attr="disabled"
                                    wire:loading.class="cursor-wait opacity-60"
                                    wire:target="bulkUpdateStatus"
                                    @click="bulkOpen = false"
                                    class="flex w-full items-center gap-2 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed">
                                Satışa al
                            </button>
                            <button wire:click="bulkUpdateStatus('suspended')"
                                    wire:loading.attr="disabled"
                                    wire:loading.class="cursor-wait opacity-60"
                                    wire:target="bulkUpdateStatus"
                                    @click="bulkOpen = false"
                                    class="flex w-full items-center gap-2 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed">
                                Beklet
                            </button>
                            <div class="my-1 border-t border-slate-100"></div>
                            <button wire:click="bulkRefreshCurrentStatus"
                                    wire:loading.attr="disabled"
                                    wire:loading.class="cursor-wait opacity-60"
                                    wire:target="bulkRefreshCurrentStatus"
                                    @click="bulkOpen = false"
                                    title="Seçili ürünlerin bağlı pazaryerlerinden güncel fiyat, stok ve kanal bilgisini al"
                                    class="flex w-full items-center gap-2 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed">
                                <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 4v6h6M20 20v-6h-6M5.5 15A7 7 0 0018 18.5M18.5 9A7 7 0 006 5.5" />
                                </svg>
                                Güncel durum al
                            </button>
                            <div class="my-1 border-t border-slate-100"></div>
                            <button wire:click="bulkSetProfitCommissionOverride(true)"
                                    wire:loading.attr="disabled"
                                    wire:loading.class="cursor-wait opacity-60"
                                    wire:target="bulkSetProfitCommissionOverride"
                                    @click="bulkOpen = false"
                                    class="flex w-full items-center gap-2 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed">
                                Manuel komisyonu aç
                            </button>
                            <button wire:click="bulkSetProfitCommissionOverride(false)"
                                    wire:loading.attr="disabled"
                                    wire:loading.class="cursor-wait opacity-60"
                                    wire:target="bulkSetProfitCommissionOverride"
                                    @click="bulkOpen = false"
                                    class="flex w-full items-center gap-2 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed">
                                Manuel komisyonu kapat
                            </button>
                            <div class="my-1 border-t border-slate-100"></div>
                            <div class="px-3 py-2">
                                <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">Fiyat güncelle</p>
                                <select wire:model.defer="bulkPriceTarget"
                                        class="mt-2 min-h-[38px] w-full rounded-[6px] border border-slate-200 bg-white px-3 text-base text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                                    <option value="all">Tümü (ana + kanal)</option>
                                    @foreach($marketplaceOptions as $marketplace)
                                        <option value="marketplace:{{ $marketplace }}">{{ $this->humanMarketplace($marketplace) }}</option>
                                    @endforeach
                                </select>
                                <div class="mt-2 flex gap-2">
                                    <select wire:model.defer="bulkPriceDirection"
                                            class="min-h-[38px] w-[88px] shrink-0 rounded-[6px] border border-slate-200 bg-white px-2 text-base text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                                        <option value="increase">Artır</option>
                                        <option value="decrease">Düşür</option>
                                    </select>
                                    <input type="number"
                                           min="0"
                                           step="0.01"
                                           wire:model.defer="bulkPricePercent"
                                           placeholder="%"
                                           class="min-h-[38px] min-w-0 w-full rounded-[6px] border border-slate-200 bg-white px-3 text-base text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                                    <button type="button"
                                            wire:click="bulkAdjustSalePrices"
                                            wire:loading.attr="disabled"
                                            wire:target="bulkAdjustSalePrices"
                                            title="Seçili ürünlerin satış fiyatını yüzde olarak güncelle"
                                            class="inline-flex min-h-[38px] shrink-0 items-center justify-center rounded-[6px] bg-slate-900 px-3 text-xs font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60">
                                        Uygula
                                    </button>
                                </div>
                                @error('bulkPricePercent')
                                    <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>
                                @enderror
                                @error('bulkPriceTarget')
                                    <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>
                                @enderror
                                @error('bulkPriceDirection')
                                    <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="my-1 border-t border-slate-100"></div>
                            <div class="px-3 py-2">
                                <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">Kârlılık hedefle</p>
                                <select wire:model.defer="bulkProfitTarget"
                                        class="mt-2 min-h-[38px] w-full rounded-[6px] border border-slate-200 bg-white px-3 text-base text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                                    <option value="all">Tümü (ana + kanal)</option>
                                    @foreach($marketplaceOptions as $marketplace)
                                        <option value="marketplace:{{ $marketplace }}">{{ $this->humanMarketplace($marketplace) }}</option>
                                    @endforeach
                                </select>
                                <div class="mt-2 flex gap-2">
                                    <input type="number"
                                           min="-99"
                                           max="1000"
                                           step="0.1"
                                           wire:model.defer="bulkProfitTargetMargin"
                                           placeholder="%"
                                           class="min-h-[38px] min-w-0 w-full rounded-[6px] border border-slate-200 bg-white px-3 text-base text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                                    <button type="button"
                                            wire:click="bulkSetTargetProfitMargin"
                                            wire:loading.attr="disabled"
                                            wire:target="bulkSetTargetProfitMargin"
                                            title="Seçili ürünlerin fiyatını hedef kârlılık yüzdesine göre güncelle"
                                            class="inline-flex min-h-[38px] shrink-0 items-center justify-center rounded-[6px] bg-slate-900 px-3 text-xs font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60">
                                        Sabitle
                                    </button>
                                </div>
                                @error('bulkProfitTargetMargin')
                                    <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>
                                @enderror
                                @error('bulkProfitTarget')
                                    <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="my-1 border-t border-slate-100"></div>
                            <div class="px-3 py-2">
                                <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">Ambalaj fiyatı gir</p>
                                <div class="mt-2 flex gap-2">
                                    <input type="number"
                                           min="0"
                                           step="0.01"
                                           wire:model.defer="bulkPackagingCost"
                                           placeholder="₺"
                                           class="min-h-[38px] min-w-0 w-full rounded-[6px] border border-slate-200 bg-white px-3 text-base text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                                    <button type="button"
                                            wire:click="bulkSetPackagingCost"
                                            wire:loading.attr="disabled"
                                            wire:target="bulkSetPackagingCost"
                                            title="Seçili ürünlerin ambalaj fiyatını güncelle"
                                            class="inline-flex min-h-[38px] shrink-0 items-center justify-center rounded-[6px] bg-slate-900 px-3 text-xs font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60">
                                        Güncelle
                                    </button>
                                </div>
                                @error('bulkPackagingCost')
                                    <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="my-1 border-t border-slate-100"></div>
                            <div class="px-3 py-2">
                                <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">Lojistik bilgisi gir</p>
                                <div class="mt-2 grid grid-cols-3 gap-2">
                                    <input type="number"
                                           min="0"
                                           step="0.01"
                                           wire:model.defer="bulkCargoCost"
                                           placeholder="₺"
                                           class="min-h-[44px] min-w-0 rounded-[6px] border border-slate-200 bg-white px-2 text-base text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                                    <input type="number"
                                           min="0"
                                           step="0.01"
                                           wire:model.defer="bulkDesi"
                                           placeholder="Desi"
                                           class="min-h-[44px] min-w-0 rounded-[6px] border border-slate-200 bg-white px-2 text-base text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                                    <input type="number"
                                           min="1"
                                           step="1"
                                           wire:model.defer="bulkPieces"
                                           placeholder="Parça"
                                           class="min-h-[44px] min-w-0 rounded-[6px] border border-slate-200 bg-white px-2 text-base text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                                </div>
                                <button type="button"
                                        wire:click="bulkSetLogisticsInfo"
                                        wire:loading.attr="disabled"
                                        wire:target="bulkSetLogisticsInfo"
                                        title="Seçili ürünlerin lojistik tutar, desi ve parça bilgisini güncelle"
                                        class="mt-2 inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] bg-slate-900 px-3 text-xs font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60">
                                    Tutar / desi / parça güncelle
                                </button>
                                @error('bulkCargoCost')
                                    <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>
                                @enderror
                                @error('bulkDesi')
                                    <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>
                                @enderror
                                @error('bulkPieces')
                                    <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="my-1 border-t border-slate-100"></div>
                            <div class="px-3 py-2">
                                <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">Stok güncelle</p>
                                <select wire:model.defer="bulkStockTarget"
                                        class="mt-2 min-h-[38px] w-full rounded-[6px] border border-slate-200 bg-white px-3 text-base text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                                    <option value="all">Tümü (ana + kanal)</option>
                                    @foreach($marketplaceOptions as $marketplace)
                                        <option value="marketplace:{{ $marketplace }}">{{ $this->humanMarketplace($marketplace) }}</option>
                                    @endforeach
                                </select>
                                <div class="mt-2 flex gap-2">
                                    <input type="number"
                                           min="0"
                                           step="1"
                                           wire:model.defer="bulkStockQuantity"
                                           placeholder="Adet"
                                           class="min-h-[38px] w-full rounded-[6px] border border-slate-200 bg-white px-3 text-base text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                                    <button type="button"
                                            wire:click="bulkSetStockQuantity"
                                            wire:loading.attr="disabled"
                                            wire:target="bulkSetStockQuantity"
                                            title="Seçili ürünlerin stok adedini güncelle"
                                            class="inline-flex min-h-[38px] shrink-0 items-center justify-center rounded-[6px] bg-slate-900 px-3 text-xs font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60">
                                        Güncelle
                                    </button>
                                </div>
                                @error('bulkStockQuantity')
                                    <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>
                                @enderror
                                @error('bulkStockTarget')
                                    <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="my-1 border-t border-slate-100"></div>
                            <div class="px-3 py-2">
                                <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">Kritik stok</p>
                                <div class="mt-2 flex gap-2">
                                    <input type="number"
                                           min="0"
                                           step="1"
                                           wire:model.defer="bulkCriticalStockThreshold"
                                           placeholder="Eşik"
                                           class="min-h-[38px] w-full rounded-[6px] border border-slate-200 bg-white px-3 text-base text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                                    <button type="button"
                                            wire:click="bulkSetCriticalStockThreshold"
                                            wire:loading.attr="disabled"
                                            wire:target="bulkSetCriticalStockThreshold"
                                            title="Seçili ürünlere kritik stok eşiğini uygula"
                                            class="inline-flex min-h-[38px] shrink-0 items-center justify-center rounded-[6px] bg-slate-900 px-3 text-xs font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60">
                                        Uygula
                                    </button>
                                </div>
                                @error('bulkCriticalStockThreshold')
                                    <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>
                                @enderror
                                <button type="button"
                                        wire:click="bulkClearCriticalStockThreshold"
                                        wire:loading.attr="disabled"
                                        wire:target="bulkClearCriticalStockThreshold"
                                        class="mt-2 text-xs font-medium text-slate-500 transition hover:text-slate-900 disabled:cursor-not-allowed disabled:opacity-60">
                                    Eşiği kaldır
                                </button>
                            </div>
                            <div class="my-1 border-t border-slate-100"></div>
                            <button wire:click="bulkDelete"
                                    wire:loading.attr="disabled"
                                    wire:loading.class="cursor-wait opacity-60"
                                    wire:target="bulkDelete"
                                    wire:confirm="Seçili ürünleri silmek istediğinize emin misiniz?"
                                    @click="bulkOpen = false"
                                    class="flex w-full items-center gap-2 px-3 py-2 text-sm text-rose-600 transition hover:bg-rose-50 disabled:cursor-not-allowed">
                                Sil
                            </button>
                        </div>
                    </div>
                @endif

            </div>
        </div>

        <div class="w-full overflow-x-auto rounded-[8px] border border-slate-200 bg-white" x-data="columnResize()">
            <table class="mp-products-v2-table table-fixed" style="--products-table-width: {{ $tableWidthPx }}px;">
                <thead class="bg-slate-50/90 text-slate-500">
                    <tr>
                        <th class="w-9 px-1.5 py-3 text-center text-[11px] font-semibold uppercase tracking-[0.14em]">
                            <input type="checkbox" wire:model.live="selectAll" class="rounded border-slate-300 text-slate-900 shadow-sm focus:ring-slate-200">
                        </th>
                        @foreach($columnMeta as $columnKey => $meta)
                            @if(in_array($columnKey, $visibleColumns, true))
                                <th class="relative px-2 py-3 text-left text-[11px] font-semibold uppercase tracking-[0.14em] {{ $columnKey === 'islem' ? 'cell-actions-header text-center' : '' }}"
                                    @if(isset($meta['width']))
                                        style="width: {{ $meta['width'] }}; min-width: {{ $meta['width'] }};"
                                    @endif
                                    @if(isset($sortableColumns[$columnKey])) wire:click="sortTable('{{ $columnKey }}')" @endif>
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="flex items-center gap-1.5 min-w-0">
                                            <span>{{ $meta['label'] }}</span>
                                            @if(isset($columnHelp[$columnKey]))
                                                <x-zolm.help-tip
                                                    :title="$columnHelp[$columnKey]['title']"
                                                    :summary="$columnHelp[$columnKey]['summary']"
                                                    :source="$columnHelp[$columnKey]['source']"
                                                    :refresh="$columnHelp[$columnKey]['refresh']"
                                                    :impact="$columnHelp[$columnKey]['impact']"
                                                />
                                            @endif
                                        </div>
                                        @if(isset($sortableColumns[$columnKey]))
                                            <span class="text-[10px] {{ ($sortableColumns[$columnKey] ?? null) === $sortField ? 'text-slate-700' : 'text-slate-300' }}">{{ $sortIcon($columnKey) }}</span>
                                        @endif
                                    </div>
                                    <span class="col-resize-handle" @mousedown.prevent="startResize($event, $el.parentElement)" @click.stop></span>
                                </th>
                            @endif
                        @endforeach
                    </tr>
                </thead>
                <tbody class="bg-white text-[13px] text-slate-700">
                    @forelse($products as $product)
                        @php
                            $listingCount = (int) ($product->listing_count_metric ?? 0);
                            $channelListings = ($product->channelListings ?? collect())->values();
                            $commissionScenarios = $this->productCommissionScenarios($product);
                            $selectedProfitScenario = $this->selectedProductCommissionScenario($product, $commissionScenarios);
                            $profitValue = (float) ($selectedProfitScenario['profit'] ?? $product->profit_metric ?? 0);
                            $profitMargin = $selectedProfitScenario['profit_margin'] ?? null;
                            $displaySalePrice = (float) ($selectedProfitScenario['sale_price'] ?? $product->sale_price ?? 0);
                            $selectedReceivable = (float) ($selectedProfitScenario['receivable'] ?? 0);
                            $selectedCommissionAmount = (float) ($selectedProfitScenario['commission_amount'] ?? 0);
                            $selectedCostPlusPackaging = (float) ($selectedProfitScenario['cost_plus_packaging'] ?? (((float) ($product->cogs ?? 0)) + ((float) ($product->packaging_cost ?? 0))));
                            $selectedCargoCost = (float) ($selectedProfitScenario['cargo_cost'] ?? $product->cargo_cost ?? 0);
                            $selectedExtraCostFixed = (float) ($selectedProfitScenario['extra_cost_fixed'] ?? $product->extra_cost_fixed ?? 0);
                            $selectedExtraCostPercentageAmount = (float) ($selectedProfitScenario['extra_cost_percentage_amount'] ?? ($displaySalePrice * (((float) ($product->extra_cost_percentage ?? 0)) / 100)));
                            $selectedExtraCostTotal = $selectedExtraCostFixed + $selectedExtraCostPercentageAmount;
                            $selectedScenarioLabel = (string) ($selectedProfitScenario['selection_label'] ?? $selectedProfitScenario['label'] ?? $this->productProfitDefaultMarketplaceLabel());
                            $hasDistinctStockCode = filled($product->stock_code) && $product->stock_code !== $product->barcode;
                            $desiValue = (float) ($product->desi ?? 0);
                            $desiLabel = floor($desiValue) === $desiValue
                                ? number_format($desiValue, 0, ',', '.')
                                : number_format($desiValue, 1, ',', '.');
                            $shippingSummary = ((int) ($product->pieces ?? 1)) . 'P ' . $desiLabel . 'D';
                            $signedProfitLabel = $formatSignedMoney($profitValue);
                            $channelCommissionRate = (float) ($selectedProfitScenario['commission_rate'] ?? $product->channel_commission_rate_metric ?? $product->commission_rate ?? 0);
                            $channelCommissionLabel = $channelCommissionRate > 0
                                ? '%' . number_format($channelCommissionRate, 1, ',', '.') . ' kom.'
                                : 'kom. kanal bazlı';
                            $deliverySummary = $this->productDeliverySummary($product);
                            $activeRecipeCount = (int) ($product->active_recipe_count_metric ?? 0);
                            $activeRecipeId = (int) ($product->active_recipe_id_metric ?? 0);
                            $isSetProduct = (bool) ($product->is_set_product ?? false) || (bool) $product->productSet;
                            $setComponentCount = $product->productSet?->items?->count() ?? 0;
                        @endphp

                        <tr wire:key="mp-product-row-{{ $product->id }}">
                            <td class="w-9 px-1.5 py-3 align-top text-center">
                                <input type="checkbox" value="{{ $product->id }}" wire:model.live="selectedProducts" class="rounded border-slate-300 text-slate-900 shadow-sm focus:ring-slate-200">
                            </td>

                            @if(in_array('urun', $visibleColumns, true))
                                <td class="cell-product pl-2 pr-3 py-3 align-top">
                                    <div class="flex min-w-0 items-start gap-2.5">
                                        <button type="button"
                                                class="group relative mt-0.5 inline-flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-[8px] border border-slate-200 bg-white transition hover:border-slate-300"
                                                @click="expanded.includes({{ $product->id }}) ? expanded = expanded.filter(i => i !== {{ $product->id }}) : expanded.push({{ $product->id }})">
                                            @if($product->main_image)
                                                <img src="{{ $product->main_image }}" alt="" class="h-full w-full object-cover">
                                            @else
                                                <div class="flex h-full w-full items-center justify-center bg-slate-50 text-slate-400">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                    </svg>
                                                </div>
                                            @endif

                                            <span class="absolute bottom-0.5 right-0.5 inline-flex h-4 w-4 items-center justify-center rounded-full border border-white bg-slate-900/85 text-white shadow-sm">
                                                <svg class="h-2.5 w-2.5 transition" :class="{ 'rotate-90': expanded.includes({{ $product->id }}) }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                </svg>
                                            </span>
                                        </button>

                                        <div class="min-w-0 flex-1">
                                            <div class="flex min-w-0 items-start gap-1.5">
                                                <div class="product-name-clamp min-w-0 text-[13px] font-semibold leading-5 text-slate-900">{{ $product->product_name ?: 'İsimsiz ürün' }}</div>
                                                @if($activeRecipeCount > 0)
                                                    <x-zolm.recipe-verified-badge
                                                        class="mt-0.5"
                                                        :count="$activeRecipeCount"
                                                        :recipe-id="$activeRecipeId ?: null"
                                                        :stock-code="$product->stock_code" />
                                                @endif
                                                @if($isSetProduct)
                                                    @include('livewire.partials.mp-products-set-badge', [
                                                        'product' => $product,
                                                        'badgeLabel' => 'Set · ' . $setComponentCount,
                                                        'popoverFocusable' => true,
                                                    ])
                                                @endif
                                            </div>
                                            <div class="mt-1 text-[11px] leading-4 text-slate-500">
                                                <span>{{ $product->barcode ?: '-' }}</span>
                                                @if($hasDistinctStockCode)
                                                    <span> · {{ $product->stock_code }}</span>
                                                @endif
                                            </div>
                                            <div class="mt-1 flex min-w-0 items-center gap-1.5 overflow-hidden text-[11px] leading-4 text-slate-400">
                                                @if($product->brand)
                                                    <span class="truncate">{{ $product->brand }}</span>
                                                @endif
                                                @if($product->category_name)
                                                    <span class="truncate">· {{ $product->category_name }}</span>
                                                @endif
                                            </div>
                                            @if(!in_array('kanal', $visibleColumns, true))
                                                <div class="mt-1 text-[10px] text-slate-400">
                                                    {{ $listingCount }} mağaza · {{ (int) ($product->active_listing_count_metric ?? 0) }} yayında
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            @endif

                            @if(in_array('kanal', $visibleColumns, true))
                                <td class="px-2 py-3 align-top">
                                    @php
                                        $activeListingCount = (int) ($product->active_listing_count_metric ?? 0);
                                        $pendingIssueCount = (int) ($product->pending_issue_count_metric ?? 0);
                                    @endphp

                                    @if($listingCount > 0)
                                        <div class="group relative inline-flex max-w-full"
                                             x-data="{
                                                open: false,
                                                hideTimer: null,
                                                style: 'left: 0px; top: 0px; width: 240px;',
                                                show() {
                                                    window.clearTimeout(this.hideTimer);
                                                    this.open = true;
                                                    this.$nextTick(() => this.position());
                                                },
                                                hideSoon() {
                                                    window.clearTimeout(this.hideTimer);
                                                    this.hideTimer = window.setTimeout(() => this.open = false, 140);
                                                },
                                                position() {
                                                    const trigger = this.$refs.trigger;
                                                    const panel = this.$refs.panel;

                                                    if (!trigger || !panel) {
                                                        return;
                                                    }

                                                    const rect = trigger.getBoundingClientRect();
                                                    const width = 240;
                                                    const panelHeight = panel.offsetHeight || 180;
                                                    const viewportPadding = 8;
                                                    const left = Math.min(
                                                        Math.max(viewportPadding, rect.left),
                                                        window.innerWidth - width - viewportPadding
                                                    );
                                                    const belowTop = rect.bottom + 6;
                                                    const aboveTop = rect.top - panelHeight - 6;
                                                    const hasRoomBelow = belowTop + panelHeight < window.innerHeight - viewportPadding;
                                                    const top = hasRoomBelow ? belowTop : Math.max(viewportPadding, aboveTop);

                                                    this.style = `left: ${left}px; top: ${top}px; width: ${width}px;`;
                                                }
                                             }"
                                             @mouseenter="show()"
                                             @mouseleave="hideSoon()"
                                             @focusin="show()"
                                             @focusout="hideSoon()"
                                             @scroll.window="open && position()"
                                             @resize.window="open && position()">
                                            <button type="button"
                                                    x-ref="trigger"
                                                    class="inline-flex max-w-full items-center rounded-[6px] px-1 py-0.5 text-left transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-slate-200">
                                                <span class="min-w-0 leading-tight">
                                                    <span class="flex items-center gap-1.5 truncate text-[12px] font-semibold text-slate-900">
                                                        <span>{{ $listingCount }} mağaza</span>
                                                        @if($pendingIssueCount > 0)
                                                            <span class="rounded-[6px] bg-amber-50 px-1.5 py-0.5 text-[9px] font-semibold text-amber-700">{{ $pendingIssueCount }}</span>
                                                        @endif
                                                    </span>
                                                    <span class="mt-0.5 flex items-center gap-1 text-[10px] {{ $pendingIssueCount > 0 ? 'text-amber-600' : 'text-emerald-600' }}">
                                                        <span class="h-[5px] w-[5px] rounded-full {{ $pendingIssueCount > 0 ? 'bg-amber-500' : 'bg-emerald-500' }}"></span>
                                                        {{ $activeListingCount }}/{{ $listingCount }} yayında
                                                    </span>
                                                </span>
                                            </button>

                                            <template x-teleport="body">
                                                <div x-show="open"
                                                     x-cloak
                                                     x-ref="panel"
                                                     x-transition.opacity.duration.120ms
                                                     :style="style"
                                                     @mouseenter="window.clearTimeout(hideTimer)"
                                                     @mouseleave="hideSoon()"
                                                     class="fixed z-[9999] rounded-[8px] border border-slate-200 bg-white p-1.5 text-left shadow-xl">
                                                    <div class="mb-1 flex items-center justify-between px-1">
                                                        <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">Bağlı mağazalar</p>
                                                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600">{{ $activeListingCount }}/{{ $listingCount }}</span>
                                                    </div>

                                                    <div class="space-y-1">
                                                        @foreach($channelListings as $listing)
                                                            @php
                                                                $store = $listing->store;
                                                                $faviconUrl = $marketplaceFavicon($store?->marketplace);
                                                                $listingIssueCount = ($listing->matchIssues ?? collect())->where('match_status', 'pending')->count();
                                                                $listingPublicUrl = $this->marketplacePublicProductUrl($listing);
                                                                $listingScenario = $this->listingCommissionScenario($product, $listing);
                                                                $listingCommissionRate = (float) ($listingScenario['commission_rate'] ?? 0);
                                                                $listingDeliveryLabel = $this->listingDeliveryTermLabel($listing);
                                                                $rowClasses = 'flex min-w-0 items-center gap-2 rounded-[6px] px-1.5 py-1 transition';
                                                                $rowClasses .= $listingPublicUrl ? ' hover:bg-slate-50' : ' opacity-75';
                                                            @endphp
                                                            <a @if($listingPublicUrl) href="{{ $listingPublicUrl }}" target="_blank" rel="noopener noreferrer" @endif
                                                               class="{{ $rowClasses }}"
                                                               title="{{ $listingPublicUrl ? 'Pazaryerinde aç' : 'Bu mağaza için ürün linki yok' }}">
                                                                <span class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full border border-slate-200 bg-slate-50 text-[9px] font-semibold text-slate-600">
                                                                    @if($faviconUrl)
                                                                        <img src="{{ $faviconUrl }}" alt="" class="h-3.5 w-3.5" loading="lazy">
                                                                    @else
                                                                        {{ $marketplaceInitial($store?->marketplace) }}
                                                                    @endif
                                                                </span>
                                                                <div class="min-w-0 flex-1">
                                                                    <p class="truncate text-[11px] font-semibold text-slate-900">{{ $store?->store_name ?: 'Mağaza adı yok' }}</p>
                                                                    <p class="truncate text-[10px] text-slate-500">{{ $this->humanMarketplace($store?->marketplace) }} · {{ $listingDeliveryLabel }}</p>
                                                                </div>
                                                                <div class="flex shrink-0 items-center gap-1 text-right">
                                                                    @if($listingCommissionRate > 0 || ($listingScenario['marketplace_key'] ?? null) === 'woocommerce')
                                                                        <span class="rounded-full bg-slate-50 px-1.5 py-0.5 text-[9px] font-semibold text-slate-500">%{{ number_format($listingCommissionRate, 1, ',', '.') }}</span>
                                                                    @endif
                                                                    <span class="inline-flex rounded-full px-1.5 py-0.5 text-[9px] font-semibold {{ $this->listingStatusTone($listing->listing_status) === 'success' ? 'bg-emerald-50 text-emerald-700' : ($this->listingStatusTone($listing->listing_status) === 'danger' ? 'bg-rose-50 text-rose-700' : 'bg-slate-100 text-slate-600') }}">
                                                                        {{ $this->listingStatusLabel($listing->listing_status) }}
                                                                    </span>
                                                                    @if($listingPublicUrl)
                                                                        <span class="text-[10px] text-slate-400">↗</span>
                                                                    @endif
                                                                    @if($listingIssueCount > 0)
                                                                        <span class="text-[9px] font-medium text-amber-600">{{ $listingIssueCount }}</span>
                                                                    @endif
                                                                </div>
                                                            </a>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    @else
                                        <span class="inline-flex text-xs font-medium leading-tight text-slate-400">Mağaza yok</span>
                                    @endif
                                </td>
                            @endif

                            @if(in_array('fiyat', $visibleColumns, true))
                                <td class="px-2 py-3 align-top">
                                    <div class="mp-ledger-stack">
                                        <div class="mp-ledger-pair">
                                            <span class="mp-ledger-label text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">Ana</span>
                                            <input type="number"
                                                   step="0.01"
                                                   value="{{ (float) ($product->sale_price ?? 0) }}"
                                                   wire:change="updateInlineField({{ $product->id }}, 'sale_price', $event.target.value)"
                                                   class="mp-ledger-control h-7 rounded-[6px] border border-slate-200 bg-white px-2 text-right text-[12px] font-semibold text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                        </div>
                                        <div class="inline-flex w-full justify-end"
                                         x-data="{
                                            open: false,
                                            hideTimer: null,
                                            style: 'left: 0px; top: 0px; width: 340px;',
                                            show() {
                                                window.clearTimeout(this.hideTimer);
                                                this.open = true;
                                                this.$nextTick(() => this.position());
                                            },
                                            hideSoon() {
                                                window.clearTimeout(this.hideTimer);
                                                this.hideTimer = window.setTimeout(() => this.open = false, 140);
                                            },
                                            position() {
                                                const trigger = this.$refs.trigger;
                                                const panel = this.$refs.panel;

                                                if (!trigger || !panel) {
                                                    return;
                                                }

                                                const rect = trigger.getBoundingClientRect();
                                                const width = 340;
                                                const panelHeight = panel.offsetHeight || 260;
                                                const viewportPadding = 8;
                                                const left = Math.min(
                                                    Math.max(viewportPadding, rect.right - width),
                                                    window.innerWidth - width - viewportPadding
                                                );
                                                const belowTop = rect.bottom + 6;
                                                const aboveTop = rect.top - panelHeight - 6;
                                                const hasRoomBelow = belowTop + panelHeight < window.innerHeight - viewportPadding;
                                                const top = hasRoomBelow ? belowTop : Math.max(viewportPadding, aboveTop);

                                                this.style = `left: ${left}px; top: ${top}px; width: ${width}px;`;
                                            }
                                         }"
                                         @mouseenter="show()"
                                         @mouseleave="hideSoon()"
                                         @focusin="show()"
                                         @focusout="hideSoon()"
                                         @scroll.window="open && position()"
                                         @resize.window="open && position()">
                                            <button type="button"
                                                    x-ref="trigger"
                                                    class="inline-flex w-full items-center justify-between gap-2 rounded-[6px] border border-slate-200 bg-slate-50/70 px-2 py-1.5 text-left transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-slate-200">
                                                <span class="text-[11px] font-semibold text-slate-600">{{ $channelCommissionLabel }}</span>
                                                <span class="text-[10px] text-slate-500">Hak. {{ $formatMoney($selectedReceivable) }}</span>
                                            </button>

                                        <template x-teleport="body">
                                            <div x-show="open"
                                                 x-cloak
                                                 x-ref="panel"
                                                 x-transition.opacity.duration.120ms
                                                 :style="style"
                                                 @mouseenter="window.clearTimeout(hideTimer)"
                                                 @mouseleave="hideSoon()"
                                                 class="fixed z-[9999] rounded-[8px] border border-slate-200 bg-white p-3 text-left shadow-xl">
                                                <div class="flex items-start justify-between gap-3">
                                                    <div>
                                                        <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">Komisyon ve Hakediş</p>
                                                        <p class="mt-1 text-xs font-semibold text-slate-900">{{ $product->product_name ?: 'İsimsiz ürün' }}</p>
                                                    </div>
                                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600">{{ $selectedScenarioLabel }}</span>
                                                </div>

                                                <div class="mt-3 max-h-80 space-y-2 overflow-y-auto pr-1">
                                                    @foreach($commissionScenarios as $scenario)
                                                        @php
                                                            $scenarioSelected = ($scenario['key'] ?? null) === ($selectedProfitScenario['selection_key'] ?? $selectedProfitScenario['key'] ?? null)
                                                                || (($selectedProfitScenario['key'] ?? '') === 'provider:' . ($scenario['marketplace_key'] ?? '') && ($scenario['marketplace_key'] ?? '') !== '')
                                                                || (($selectedProfitScenario['key'] ?? '') === 'average');
                                                            $scenarioProfit = (float) ($scenario['profit'] ?? 0);
                                                            $scenarioMargin = $scenario['profit_margin'] ?? null;
                                                        @endphp
                                                        <div class="rounded-[8px] border p-2 {{ $scenarioSelected ? 'border-slate-900 bg-slate-50' : 'border-slate-200 bg-white' }}">
                                                            <div class="flex items-start justify-between gap-2">
                                                                <div class="min-w-0">
                                                                    <p class="truncate text-xs font-semibold text-slate-900">{{ $scenario['store_name'] ?? '-' }}</p>
                                                                    <p class="mt-0.5 text-[10px] text-slate-500">{{ $scenario['marketplace_label'] ?? '-' }} · {{ $scenario['commission_source'] ?? '-' }}</p>
                                                                </div>
                                                                <span class="shrink-0 rounded-full bg-white px-2 py-0.5 text-[10px] font-semibold text-slate-700">%{{ number_format((float) ($scenario['commission_rate'] ?? 0), 1, ',', '.') }}</span>
                                                            </div>
                                                            <div class="mt-2 grid grid-cols-3 gap-2 text-[10px]">
                                                                <div>
                                                                    <p class="text-slate-400">Kesinti</p>
                                                                    <p class="font-semibold text-slate-700">{{ $formatMoney($scenario['commission_amount'] ?? 0) }}</p>
                                                                </div>
                                                                <div>
                                                                    <p class="text-slate-400">Hakediş</p>
                                                                    <p class="font-semibold text-slate-700">{{ $formatMoney($scenario['receivable'] ?? 0) }}</p>
                                                                </div>
                                                                <div>
                                                                    <p class="text-slate-400">Kâr</p>
                                                                    <p class="font-semibold {{ $scenarioProfit >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ $formatMoney($scenarioProfit) }}</p>
                                                                    <p class="text-[10px] text-slate-500">{{ $formatMultiplier($scenarioMargin) }}</p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                    </div>
                                </td>
                            @endif

                            @if(in_array('cogs', $visibleColumns, true))
                                <td class="px-2 py-3 align-top">
                                    <div class="mp-ledger-stack">
                                        <div class="mp-ledger-pair">
                                            <span class="mp-ledger-label text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">Ürün</span>
                                            <input type="number"
                                                   step="0.01"
                                                   value="{{ (float) ($product->cogs ?? 0) }}"
                                                   wire:change="updateInlineField({{ $product->id }}, 'cogs', $event.target.value)"
                                                   class="mp-ledger-control h-7 rounded-[6px] border border-slate-200 bg-white px-2 text-right text-[12px] font-semibold {{ (float) ($product->cogs ?? 0) > 0 ? 'text-slate-900' : 'text-amber-700' }} focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                        </div>
                                        <div class="mp-ledger-pair text-[11px]">
                                            <span class="mp-ledger-label text-slate-500">Ambalaj</span>
                                            <input type="number"
                                                   step="0.01"
                                                   value="{{ (float) ($product->packaging_cost ?? 0) }}"
                                                   wire:change="updateInlineField({{ $product->id }}, 'packaging_cost', $event.target.value)"
                                                   class="mp-ledger-control h-7 rounded-[6px] border border-slate-200 bg-white px-2 text-right text-[11px] font-medium text-slate-700 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                        </div>
                                        <div class="mp-ledger-pair text-[11px]">
                                            <span class="mp-ledger-label text-slate-400">Toplam</span>
                                            <span class="mp-ledger-control truncate text-right font-medium text-slate-700">{{ $formatMoney($selectedCostPlusPackaging) }}</span>
                                        </div>
                                    </div>
                                </td>
                            @endif

                            @if(in_array('kargo', $visibleColumns, true))
                                <td class="px-2 py-3 align-top">
                                    <div class="mp-ledger-stack">
                                        <div class="mp-ledger-pair">
                                            <span class="mp-ledger-label text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">Tutar</span>
                                            <input type="number"
                                                   step="0.01"
                                                   value="{{ (float) ($product->cargo_cost ?? 0) }}"
                                                   wire:change="updateInlineField({{ $product->id }}, 'cargo_cost', $event.target.value)"
                                                   class="mp-ledger-control h-7 rounded-[6px] border border-slate-200 bg-white px-2 text-right text-[11px] font-medium text-slate-700 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                        </div>
                                        <div class="mp-ledger-pair">
                                            <span class="mp-ledger-label text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">Desi</span>
                                            <input type="number"
                                                   step="0.1"
                                                   value="{{ (float) ($product->desi ?? 0) }}"
                                                   wire:change="updateInlineField({{ $product->id }}, 'desi', $event.target.value)"
                                                   class="mp-ledger-control h-7 rounded-[6px] border border-slate-200 bg-white px-2 text-right text-[11px] font-medium text-slate-700 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                        </div>
                                        <div class="mp-ledger-pair">
                                            <span class="mp-ledger-label text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">Parça</span>
                                            <input type="number"
                                                   min="1"
                                                   step="1"
                                                   value="{{ (int) ($product->pieces ?? 1) }}"
                                                   wire:change="updateInlineField({{ $product->id }}, 'pieces', $event.target.value)"
                                                   class="mp-ledger-control h-7 rounded-[6px] border border-slate-200 bg-white px-2 text-right text-[11px] font-medium text-slate-700 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                        </div>
                                    </div>
                                </td>
                            @endif

                            @if(in_array('ek_gider', $visibleColumns, true))
                                <td class="px-2 py-3 align-top">
                                    <div class="mp-ledger-stack">
                                        <div class="mp-ledger-pair">
                                            <span class="mp-ledger-label text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">Sabit</span>
                                            <input type="number"
                                                   step="0.01"
                                                   value="{{ (float) ($product->extra_cost_fixed ?? 0) }}"
                                                   wire:change="updateInlineField({{ $product->id }}, 'extra_cost_fixed', $event.target.value)"
                                                   class="mp-ledger-control h-7 rounded-[6px] border border-slate-200 bg-white px-2 text-right text-[11px] font-medium text-slate-700 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                        </div>
                                        <div class="mp-ledger-pair">
                                            <span class="mp-ledger-label text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">%</span>
                                            <input type="number"
                                                   step="0.1"
                                                   value="{{ (float) ($product->extra_cost_percentage ?? 0) }}"
                                                   wire:change="updateInlineField({{ $product->id }}, 'extra_cost_percentage', $event.target.value)"
                                                   class="mp-ledger-control h-7 rounded-[6px] border border-slate-200 bg-white px-2 text-right text-[11px] font-medium text-slate-700 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                        </div>
                                    </div>
                                </td>
                            @endif

                            @if(in_array('stok', $visibleColumns, true))
                                <td class="px-2 py-3 align-top">
                                    <div class="flex min-w-0 flex-col items-end gap-1">
                                        <div class="inline-flex max-w-full"
                                                 x-data="{
                                                    open: false,
                                                    hideTimer: null,
                                                    style: 'left: 0px; top: 0px; min-width: 104px;',
                                                    show() {
                                                        window.clearTimeout(this.hideTimer);
                                                        this.open = true;
                                                        this.$nextTick(() => this.position());
                                                    },
                                                    hideSoon() {
                                                        window.clearTimeout(this.hideTimer);
                                                        this.hideTimer = window.setTimeout(() => this.open = false, 120);
                                                    },
                                                    position() {
                                                        const trigger = this.$refs.trigger;
                                                        const panel = this.$refs.panel;

                                                        if (!trigger || !panel) {
                                                            return;
                                                        }

                                                        const rect = trigger.getBoundingClientRect();
                                                        const width = Math.max(104, panel.offsetWidth || 104);
                                                        const panelHeight = panel.offsetHeight || 120;
                                                        const viewportPadding = 8;
                                                        const left = Math.min(
                                                            Math.max(viewportPadding, rect.right - width),
                                                            window.innerWidth - width - viewportPadding
                                                        );
                                                        const belowTop = rect.bottom + 6;
                                                        const aboveTop = rect.top - panelHeight - 6;
                                                        const hasRoomBelow = belowTop + panelHeight < window.innerHeight - viewportPadding;
                                                        const top = hasRoomBelow ? belowTop : Math.max(viewportPadding, aboveTop);

                                                        this.style = `left: ${left}px; top: ${top}px; min-width: 104px;`;
                                                    }
                                                 }"
                                                 @mouseenter="show()"
                                                 @mouseleave="hideSoon()"
                                                 @focusin="show()"
                                                 @focusout="hideSoon()"
                                                 @scroll.window="open && position()"
                                                 @resize.window="open && position()">
                                                <button type="button"
                                                        x-ref="trigger"
                                                        title="Ana stok: {{ $formatCount($product->stock_quantity) }}"
                                                        aria-label="Ana stok {{ $formatCount($product->stock_quantity) }}"
                                                        class="inline-flex max-w-full min-w-[2.5rem] items-center justify-center rounded-[6px] px-2 py-0.5 text-[10px] font-semibold transition hover:ring-2 hover:ring-slate-200 focus:outline-none focus:ring-2 focus:ring-slate-200 {{ $product->stock_level_color }}">
                                                    {{ $formatCount($product->stock_quantity) }}
                                                </button>

                                                <template x-teleport="body">
                                                    <div x-show="open"
                                                         x-cloak
                                                         x-ref="panel"
                                                         x-transition.opacity.duration.120ms
                                                         :style="style"
                                                         @mouseenter="window.clearTimeout(hideTimer)"
                                                         @mouseleave="hideSoon()"
                                                         class="fixed z-[9999] rounded-[8px] border border-slate-200 bg-white p-1.5 text-left shadow-lg">
                                                        <div class="max-h-44 space-y-1 overflow-y-auto">
                                                            @forelse($channelListings as $listing)
                                                                @php
                                                                    $store = $listing->store;
                                                                    $faviconUrl = $marketplaceFavicon($store?->marketplace);
                                                                @endphp
                                                                <div class="flex min-w-[92px] items-center justify-between gap-3 rounded-[6px] px-1.5 py-1"
                                                                     title="{{ $store?->store_name ?: $this->humanMarketplace($store?->marketplace) }}">
                                                                    <span class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full border border-slate-200 bg-slate-50 text-[9px] font-semibold text-slate-600">
                                                                        @if($faviconUrl)
                                                                            <img src="{{ $faviconUrl }}" alt="" class="h-3.5 w-3.5" loading="lazy">
                                                                        @else
                                                                            {{ $marketplaceInitial($store?->marketplace) }}
                                                                        @endif
                                                                    </span>
                                                                    <span class="text-[11px] font-semibold text-slate-900">{{ $formatCount($listing->stock_quantity ?? 0) }}</span>
                                                                </div>
                                                            @empty
                                                                <div class="px-2 py-1.5 text-[11px] font-medium text-slate-400">Kanal yok</div>
                                                            @endforelse
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>
                                        <span class="inline-flex max-w-full items-center justify-end gap-1 rounded-[6px] bg-slate-50 px-1.5 py-0.5 text-[10px] font-medium text-slate-500"
                                              title="Kanal stok toplamı: {{ $formatCount($product->channel_stock_total_metric ?? 0) }}"
                                              aria-label="Kanal stok toplamı {{ $formatCount($product->channel_stock_total_metric ?? 0) }}">
                                            <span class="text-slate-300">Σ</span>
                                            <span class="truncate">{{ $formatCount($product->channel_stock_total_metric ?? 0) }}</span>
                                        </span>
                                    </div>
                                </td>
                            @endif

                            @if(in_array('kritik_stok', $visibleColumns, true))
                                <td class="px-2 py-3 align-top text-right">
                                    @if($product->critical_stock_threshold !== null)
                                        <span class="inline-flex rounded-[6px] bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700">
                                            {{ $formatCount($product->critical_stock_threshold) }}
                                        </span>
                                    @else
                                        <span class="text-[11px] font-medium text-slate-400">Eşik yok</span>
                                    @endif
                                    @if($product->critical_stock_threshold !== null && (int) $product->stock_quantity <= (int) $product->critical_stock_threshold && (int) $product->stock_quantity > 0)
                                        <div class="mt-1 text-[10px] text-amber-600">Uyarıda</div>
                                    @endif
                                </td>
                            @endif

                            @if(in_array('kdv', $visibleColumns, true))
                                <td class="px-2 py-3 align-top text-center">
                                    <span class="inline-flex rounded-[6px] bg-indigo-50 px-2 py-0.5 text-[10px] font-semibold text-indigo-700">
                                        %{{ number_format((float) ($product->vat_rate ?? 0), 0, ',', '.') }}
                                    </span>
                                </td>
                            @endif

                            @if(in_array('maliyet_kdv', $visibleColumns, true))
                                <td class="px-2 py-3 align-top">
                                    <input type="number"
                                           step="0.1"
                                           value="{{ $product->cost_vat_rate !== null ? (float) $product->cost_vat_rate : '' }}"
                                           wire:change="updateInlineField({{ $product->id }}, 'cost_vat_rate', $event.target.value)"
                                           placeholder="%"
                                           class="h-8 w-full rounded-[6px] border border-slate-200 bg-white px-2 text-right text-[12px] font-semibold text-slate-800 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                </td>
                            @endif

                            @if(in_array('desi', $visibleColumns, true))
                                <td class="px-2 py-3 align-top">
                                    <input type="number"
                                           step="0.1"
                                           value="{{ (float) ($product->desi ?? 0) }}"
                                           wire:change="updateInlineField({{ $product->id }}, 'desi', $event.target.value)"
                                           class="h-8 w-full rounded-[6px] border border-slate-200 bg-white px-2 text-right text-[12px] font-semibold text-slate-800 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                </td>
                            @endif

                            @if(in_array('iade', $visibleColumns, true))
                                <td class="px-2 py-3 align-top">
                                    <div class="space-y-1">
                                        <input type="number"
                                               step="0.1"
                                               value="{{ $product->return_rate !== null ? (float) $product->return_rate : '' }}"
                                               wire:change="updateInlineField({{ $product->id }}, 'return_rate', $event.target.value)"
                                               placeholder="%"
                                               class="h-8 w-full rounded-[6px] border border-slate-200 bg-white px-2 text-right text-[12px] font-semibold {{ (float) ($product->return_rate ?? 0) >= 15 ? 'text-rose-600' : 'text-slate-800' }} focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                        <p class="truncate text-right text-[10px] text-slate-400">{{ $product->return_rate_source ?: 'manuel' }}</p>
                                    </div>
                                </td>
                            @endif

                            @if(in_array('teslimat', $visibleColumns, true))
                                <td class="px-1.5 py-3 align-top">
                                    @if($deliverySummary['has_channel_terms'])
                                        <div class="inline-flex h-8 max-w-full items-center justify-center rounded-[6px] border border-slate-200 bg-slate-50/70 px-2" title="{{ $deliverySummary['title'] }}">
                                            <span class="truncate text-[12px] font-semibold text-slate-900">{{ $deliverySummary['short_label'] ?? $deliverySummary['label'] }}</span>
                                        </div>
                                    @else
                                        <input type="text"
                                               value="{{ $product->fast_delivery_type ?? '' }}"
                                               wire:change="updateInlineField({{ $product->id }}, 'fast_delivery_type', $event.target.value)"
                                               placeholder="Std"
                                               title="{{ $deliverySummary['title'] }}"
                                               class="h-8 w-16 rounded-[6px] border border-slate-200 bg-white px-1.5 text-center text-[12px] font-medium text-slate-800 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                    @endif
                                </td>
                            @endif

                            @if(in_array('roi', $visibleColumns, true))
                                <td class="px-2 py-3 align-top text-right">
                                    <div class="inline-flex justify-end"
                                         x-data="{
                                            open: false,
                                            hideTimer: null,
                                            style: 'left: 0px; top: 0px; width: 360px;',
                                            show() {
                                                window.clearTimeout(this.hideTimer);
                                                this.open = true;
                                                this.$nextTick(() => this.position());
                                            },
                                            hideSoon() {
                                                window.clearTimeout(this.hideTimer);
                                                this.hideTimer = window.setTimeout(() => this.open = false, 140);
                                            },
                                            position() {
                                                const trigger = this.$refs.trigger;
                                                const panel = this.$refs.panel;

                                                if (!trigger || !panel) {
                                                    return;
                                                }

                                                const rect = trigger.getBoundingClientRect();
                                                const width = 360;
                                                const panelHeight = panel.offsetHeight || 280;
                                                const viewportPadding = 8;
                                                const left = Math.min(
                                                    Math.max(viewportPadding, rect.right - width),
                                                    window.innerWidth - width - viewportPadding
                                                );
                                                const belowTop = rect.bottom + 6;
                                                const aboveTop = rect.top - panelHeight - 6;
                                                const hasRoomBelow = belowTop + panelHeight < window.innerHeight - viewportPadding;
                                                const top = hasRoomBelow ? belowTop : Math.max(viewportPadding, aboveTop);

                                                this.style = `left: ${left}px; top: ${top}px; width: ${width}px;`;
                                            }
                                         }"
                                         @mouseenter="show()"
                                         @mouseleave="hideSoon()"
                                         @focusin="show()"
                                         @focusout="hideSoon()"
                                         @scroll.window="open && position()"
                                         @resize.window="open && position()">
                                        <button type="button"
                                                x-ref="trigger"
                                                class="inline-flex w-full flex-col items-end rounded-[6px] border border-slate-200 bg-slate-50/70 px-2 py-1.5 text-right transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-slate-200">
                                            <span class="text-[13px] font-semibold {{ $profitValue >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                                                {{ $formatMultiplier($profitMargin) }}
                                            </span>
                                            <span class="mt-0.5 text-[11px] font-semibold {{ $profitValue >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ $signedProfitLabel }}</span>
                                        </button>

                                        <template x-teleport="body">
                                            <div x-show="open"
                                                 x-cloak
                                                 x-ref="panel"
                                                 x-transition.opacity.duration.120ms
                                                 :style="style"
                                                 @mouseenter="window.clearTimeout(hideTimer)"
                                                 @mouseleave="hideSoon()"
                                                 class="fixed z-[9999] rounded-[8px] border border-slate-200 bg-white p-3 text-left shadow-xl">
                                                <div class="flex items-start justify-between gap-3">
                                                    <div>
                                                        <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">Kârlılık hesabı</p>
                                                        <p class="mt-1.5 text-[11px] leading-4 text-slate-600">Tablodaki oran, net kârın ürün maliyetine yüzdesel oranını gösterir.</p>
                                                    </div>
                                                    <span class="rounded-full {{ $profitValue >= 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }} px-2 py-0.5 text-[10px] font-semibold">{{ $signedProfitLabel }} · {{ $formatMultiplier($profitMargin) }}</span>
                                                </div>

                                                <div class="mt-3 space-y-1.5">
                                                    <div class="flex items-start justify-between gap-3 text-[11px] leading-4">
                                                        <span class="text-slate-500">Ciro</span>
                                                        <span class="font-medium text-slate-900">{{ $formatMoney($displaySalePrice) }}</span>
                                                    </div>
                                                    <div class="flex items-start justify-between gap-3 text-[11px] leading-4">
                                                        <span class="text-slate-500">Tahmini komisyon / kesinti</span>
                                                        <span class="font-medium text-rose-600">{{ $formatSignedMoney(-1 * $selectedCommissionAmount) }}</span>
                                                    </div>
                                                    <div class="flex items-start justify-between gap-3 text-[11px] leading-4">
                                                        <span class="text-slate-500">Maliyet + ambalaj</span>
                                                        <span class="font-medium text-rose-600">{{ $formatSignedMoney(-1 * $selectedCostPlusPackaging) }}</span>
                                                    </div>
                                                    <div class="flex items-start justify-between gap-3 text-[11px] leading-4">
                                                        <span class="text-slate-500">Kargo maliyeti</span>
                                                        <span class="font-medium text-amber-600">{{ $formatSignedMoney(-1 * $selectedCargoCost) }}</span>
                                                    </div>
                                                    <div class="flex items-start justify-between gap-3 text-[11px] leading-4">
                                                        <span class="text-slate-500">Ek gider</span>
                                                        <span class="font-medium text-amber-600">{{ $formatSignedMoney(-1 * $selectedExtraCostTotal) }}</span>
                                                    </div>
                                                </div>

                                                <div class="mt-3 rounded-[8px] border border-slate-200 bg-slate-50/70 px-3 py-2">
                                                    <div class="flex items-start justify-between gap-3 text-[11px] leading-4">
                                                        <span class="font-medium text-slate-600">Kâr</span>
                                                        <span class="font-semibold {{ $profitValue >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ $signedProfitLabel }}</span>
                                                    </div>
                                                </div>

                                                <div class="mt-3 space-y-1 border-t border-slate-100 pt-3 text-[10px] leading-4 text-slate-500">
                                                    <p>Kâr = Ciro - komisyon / kesinti - (maliyet + ambalaj) - kargo maliyeti - ek gider</p>
                                                    <p>Kârlılık = Kâr / Maliyet</p>
                                                    <p>{{ $formatMultiplier($profitMargin) }} = {{ $formatMoney($profitValue) }} / {{ $formatMoney($selectedCostPlusPackaging) }}</p>
                                                </div>

                                                <div class="mt-3 max-h-72 space-y-1.5 overflow-y-auto border-t border-slate-100 pt-3 pr-1">
                                                    @foreach($commissionScenarios as $scenario)
                                                        @php
                                                            $scenarioProfit = (float) ($scenario['profit'] ?? 0);
                                                            $scenarioMargin = $scenario['profit_margin'] ?? null;
                                                        @endphp
                                                        <div class="flex items-center justify-between gap-3 rounded-[6px] border border-slate-200 bg-white px-2 py-1.5">
                                                            <div class="min-w-0">
                                                                <p class="truncate text-[11px] font-semibold text-slate-900">{{ $scenario['store_name'] ?? '-' }}</p>
                                                                <p class="text-[10px] text-slate-500">{{ $scenario['marketplace_label'] ?? '-' }} · %{{ number_format((float) ($scenario['commission_rate'] ?? 0), 1, ',', '.') }}</p>
                                                            </div>
                                                            <div class="shrink-0 text-right">
                                                                <p class="text-[11px] font-semibold {{ $scenarioProfit >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ $formatMoney($scenarioProfit) }}</p>
                                                                <p class="text-[10px] text-slate-500">{{ $formatMultiplier($scenarioMargin) }}</p>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </td>
                            @endif

                            @if(in_array('durum', $visibleColumns, true))
                                <td class="px-2 py-3 align-top text-right">
                                    <x-zolm.status-badge size="sm" :tone="$product->status === 'active' ? 'success' : ($product->status === 'out_of_stock' ? 'danger' : 'warning')">
                                        {{ $product->status_label }}
                                    </x-zolm.status-badge>
                                </td>
                            @endif

                            @if(in_array('islem', $visibleColumns, true))
                                <td x-data="{ actionMenuOpen: false }"
                                    :class="{ 'is-action-menu-open': actionMenuOpen }"
                                    @keydown.escape.window="actionMenuOpen = false"
                                    class="cell-actions relative px-2 py-3 align-top text-right overflow-visible">
                                    <div
                                         class="relative flex items-start justify-end">
                                        <button type="button"
                                                @click.stop="actionMenuOpen = !actionMenuOpen"
                                                :aria-expanded="actionMenuOpen.toString()"
                                                aria-haspopup="menu"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-[6px] border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50 hover:text-slate-700">
                                            <span class="sr-only">İşlem menüsünü aç</span>
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5h.01M12 12h.01M12 19h.01" />
                                            </svg>
                                        </button>

                                        <div x-show="actionMenuOpen"
                                             x-cloak
                                             x-transition
                                             @click.outside="actionMenuOpen = false"
                                             class="action-menu-panel absolute right-0 top-10 w-60 rounded-[8px] border border-slate-200 bg-white p-2 shadow-xl">
                                            <div class="space-y-1">
                                                <p class="px-2 text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-400">Düzenleme</p>
                                                <button type="button"
                                                        @click="actionMenuOpen = false"
                                                        wire:click="openEditProductTab({{ $product->id }}, 'basic')"
                                                        wire:loading.attr="disabled"
                                                        wire:loading.class="cursor-wait opacity-60"
                                                        wire:target="openEditProductTab({{ $product->id }}, 'basic')"
                                                        title="Ürünü düzenle"
                                                        class="flex w-full items-center gap-2.5 rounded-[6px] px-3 py-2 text-left transition hover:bg-slate-50 disabled:cursor-not-allowed">
                                                    <span class="text-slate-400">
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5M16.5 3.5a2.121 2.121 0 113 3L12 14l-4 1 1-4 7.5-7.5z" />
                                                        </svg>
                                                    </span>
                                                    <span class="min-w-0 truncate text-xs font-medium text-slate-700">Genel düzenle</span>
                                                </button>
                                                <button type="button"
                                                        @click="actionMenuOpen = false"
                                                        wire:click="openEditProductTab({{ $product->id }}, 'listing_quality')"
                                                        wire:loading.attr="disabled"
                                                        wire:loading.class="cursor-wait opacity-60"
                                                        wire:target="openEditProductTab({{ $product->id }}, 'listing_quality')"
                                                        title="Ürün kartı, kanal ilanı ve yorum sinyallerini birlikte analiz et"
                                                        class="flex w-full items-center gap-2.5 rounded-[6px] px-3 py-2 text-left transition hover:bg-slate-50 disabled:cursor-not-allowed">
                                                    <span class="text-slate-400">
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                                        </svg>
                                                    </span>
                                                    <span class="min-w-0 truncate text-xs font-medium text-slate-700">Listing kalitesini analiz et</span>
                                                </button>
                                                <button type="button"
                                                        @click="actionMenuOpen = false"
                                                        wire:click="openEditProductTab({{ $product->id }}, 'pricing')"
                                                        wire:loading.attr="disabled"
                                                        wire:loading.class="cursor-wait opacity-60"
                                                        wire:target="openEditProductTab({{ $product->id }}, 'pricing')"
                                                        title="COGS, fiyat ve komisyon"
                                                        class="flex w-full items-center gap-2.5 rounded-[6px] px-3 py-2 text-left transition hover:bg-slate-50 disabled:cursor-not-allowed">
                                                    <span class="text-slate-400">
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8V6m0 12v-2m8-4a8 8 0 11-16 0 8 8 0 0116 0z" />
                                                        </svg>
                                                    </span>
                                                    <span class="min-w-0 truncate text-xs font-medium text-slate-700">Fiyat & maliyet</span>
                                                </button>
                                                <button type="button"
                                                        @click="actionMenuOpen = false"
                                                        wire:click="openEditProductTab({{ $product->id }}, 'images')"
                                                        wire:loading.attr="disabled"
                                                        wire:loading.class="cursor-wait opacity-60"
                                                        wire:target="openEditProductTab({{ $product->id }}, 'images')"
                                                        title="Kapak ve galeri görselleri"
                                                        class="flex w-full items-center gap-2.5 rounded-[6px] px-3 py-2 text-left transition hover:bg-slate-50 disabled:cursor-not-allowed">
                                                    <span class="text-slate-400">
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                        </svg>
                                                    </span>
                                                    <span class="min-w-0 truncate text-xs font-medium text-slate-700">Görseller</span>
                                                </button>
                                                <button type="button"
                                                        @click="actionMenuOpen = false"
                                                        wire:click="openEditProductTab({{ $product->id }}, 'logistics')"
                                                        wire:loading.attr="disabled"
                                                        wire:loading.class="cursor-wait opacity-60"
                                                        wire:target="openEditProductTab({{ $product->id }}, 'logistics')"
                                                        title="Stok, kritik eşik ve lojistik"
                                                        class="flex w-full items-center gap-2.5 rounded-[6px] px-3 py-2 text-left transition hover:bg-slate-50 disabled:cursor-not-allowed">
                                                    <span class="text-slate-400">
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10" />
                                                        </svg>
                                                    </span>
                                                    <span class="min-w-0 truncate text-xs font-medium text-slate-700">Stok & lojistik</span>
                                                </button>
                                            </div>

                                            <div class="mt-2 space-y-1 border-t border-slate-200 pt-2">
                                                <p class="px-2 text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-400">Senkron</p>
                                                <button type="button"
                                                        @click="actionMenuOpen = false"
                                                        wire:click="refreshCurrentStatus({{ $product->id }})"
                                                        wire:loading.attr="disabled"
                                                        wire:loading.class="cursor-wait opacity-60"
                                                        wire:target="refreshCurrentStatus({{ $product->id }})"
                                                        title="Pazaryerlerinden bu ürünün güncel fiyat, stok ve kanal bilgisini al"
                                                        class="flex w-full items-center gap-2.5 rounded-[6px] px-3 py-2 text-left transition hover:bg-slate-50 disabled:cursor-not-allowed">
                                                    <span class="text-slate-400">
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 4v6h6M20 20v-6h-6M5.5 15A7 7 0 0018 18.5M18.5 9A7 7 0 006 5.5" />
                                                        </svg>
                                                    </span>
                                                    <span class="min-w-0 truncate text-xs font-medium text-slate-700">Güncel durum al</span>
                                                </button>
                                                <button type="button"
                                                        @click="actionMenuOpen = false"
                                                        wire:click="openQuickMatchModal({{ $product->id }})"
                                                        wire:loading.attr="disabled"
                                                        wire:loading.class="cursor-wait opacity-60"
                                                        wire:target="openQuickMatchModal({{ $product->id }})"
                                                        title="Bekleyen pazaryeri kayıtlarını bu ürünle hızlı eşleştir"
                                                        class="flex w-full items-center gap-2.5 rounded-[6px] px-3 py-2 text-left transition hover:bg-slate-50 disabled:cursor-not-allowed">
                                                    <span class="text-slate-400">
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10 13a5 5 0 007.07 0l1.41-1.41a5 5 0 00-7.07-7.07L10.5 5.43M14 11a5 5 0 00-7.07 0l-1.41 1.41a5 5 0 007.07 7.07l.91-.91" />
                                                        </svg>
                                                    </span>
                                                    <span class="min-w-0 truncate text-xs font-medium text-slate-700">Hızlı eşleştir</span>
                                                </button>
                                            </div>

                                            <div class="mt-2 space-y-1 border-t border-slate-200 pt-2">
                                                <p class="px-2 text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-400">Hızlı durum</p>
                                                @if($product->status !== 'active')
                                                    <button type="button"
                                                            @click="actionMenuOpen = false"
                                                            wire:click="updateProductStatus({{ $product->id }}, 'active')"
                                                            wire:loading.attr="disabled"
                                                            wire:loading.class="cursor-wait opacity-60"
                                                            wire:target="updateProductStatus({{ $product->id }}, 'active')"
                                                            title="Ürünü aktif akışa geri taşı"
                                                            class="flex w-full items-center gap-2.5 rounded-[6px] px-3 py-2 text-left transition hover:bg-emerald-50 disabled:cursor-not-allowed">
                                                        <span class="text-emerald-500">
                                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 13l4 4L19 7" />
                                                            </svg>
                                                        </span>
                                                        <span class="min-w-0 truncate text-xs font-medium text-slate-700">Satışa al</span>
                                                    </button>
                                                @endif
                                                @if($product->status !== 'suspended')
                                                    <button type="button"
                                                            @click="actionMenuOpen = false"
                                                            wire:click="updateProductStatus({{ $product->id }}, 'suspended')"
                                                            wire:loading.attr="disabled"
                                                            wire:loading.class="cursor-wait opacity-60"
                                                            wire:target="updateProductStatus({{ $product->id }}, 'suspended')"
                                                            title="Satışı geçici olarak durdur"
                                                            class="flex w-full items-center gap-2.5 rounded-[6px] px-3 py-2 text-left transition hover:bg-amber-50 disabled:cursor-not-allowed">
                                                        <span class="text-amber-500">
                                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10 9v6m4-6v6M5 19h14M7 5h10l1 2H6l1-2z" />
                                                            </svg>
                                                        </span>
                                                        <span class="min-w-0 truncate text-xs font-medium text-slate-700">Beklet</span>
                                                    </button>
                                                @endif
                                                @if($product->status !== 'out_of_stock')
                                                    <button type="button"
                                                            @click="actionMenuOpen = false"
                                                            wire:click="updateProductStatus({{ $product->id }}, 'out_of_stock')"
                                                            wire:loading.attr="disabled"
                                                            wire:loading.class="cursor-wait opacity-60"
                                                            wire:target="updateProductStatus({{ $product->id }}, 'out_of_stock')"
                                                            title="Stok bitti durumuna geçir"
                                                            class="flex w-full items-center gap-2.5 rounded-[6px] px-3 py-2 text-left transition hover:bg-slate-50 disabled:cursor-not-allowed">
                                                        <span class="text-slate-400">
                                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 13V7a2 2 0 00-2-2h-3V3H9v2H6a2 2 0 00-2 2v6m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0H4m4 4h8" />
                                                            </svg>
                                                        </span>
                                                        <span class="min-w-0 truncate text-xs font-medium text-slate-700">Tükendi</span>
                                                    </button>
                                                @endif
                                            </div>

                                            <div class="mt-2 space-y-1 border-t border-slate-200 pt-2">
                                                <p class="px-2 text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-400">Kayıt işlemleri</p>
                                                <button type="button"
                                                        @click="actionMenuOpen = false"
                                                        wire:click="duplicateProduct({{ $product->id }})"
                                                        wire:loading.attr="disabled"
                                                        wire:loading.class="cursor-wait opacity-60"
                                                        wire:target="duplicateProduct({{ $product->id }})"
                                                        title="Kopya ürün kartı oluştur"
                                                        class="flex w-full items-center gap-2.5 rounded-[6px] px-3 py-2 text-left transition hover:bg-slate-50 disabled:cursor-not-allowed">
                                                    <span class="text-slate-400">
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-2 10h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                                        </svg>
                                                    </span>
                                                    <span class="min-w-0 truncate text-xs font-medium text-slate-700">Çoğalt</span>
                                                </button>
                                                <button type="button"
                                                        @click="actionMenuOpen = false"
                                                        wire:click="deleteProduct({{ $product->id }})"
                                                        wire:loading.attr="disabled"
                                                        wire:loading.class="cursor-wait opacity-60"
                                                        wire:target="deleteProduct({{ $product->id }})"
                                                        wire:confirm="Bu ürünü silmek istediğinize emin misiniz?"
                                                        title="Kalıcı olarak kaldır"
                                                        class="flex w-full items-center gap-2.5 rounded-[6px] px-3 py-2 text-left transition hover:bg-rose-50 disabled:cursor-not-allowed">
                                                    <span class="text-rose-500">
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16" />
                                                        </svg>
                                                    </span>
                                                    <span class="min-w-0 truncate text-xs font-medium text-rose-700">Sil</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            @endif
                        </tr>

                        <tr wire:key="mp-product-detail-{{ $product->id }}" x-show="expanded.includes({{ $product->id }})" x-cloak x-transition class="expanded-row bg-slate-50/60">
                            <td colspan="{{ count($visibleColumns) + 1 }}" class="p-0">
                                <div class="px-3 py-4 lg:px-4 w-full">
                                    @include('livewire.partials.mp-product-v2-detail', ['product' => $product])
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($visibleColumns) + 1 }}" class="px-6 py-12 text-center text-sm text-slate-500">
                                @if($showResetFilters)
                                    <div class="flex flex-col items-center justify-center gap-4">
                                        <p>Bu filtrelerle eşleşen ürün bulunamadı.</p>
                                        <button type="button"
                                                wire:click="resetFilters"
                                                class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                            Filtreleri temizle
                                        </button>
                                    </div>
                                @else
                                    Kayıtlı ürün bulunamadı. Excel yükleyebilir veya manuel ürün ekleyebilirsiniz.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Pagination --}}
    @include('livewire.partials.mp-pagination-bar', ['paginator' => $products])
    
    </section>

    @include('livewire.partials.mp-products-edit-modal')
    @include('livewire.partials.mp-products-quick-match-modal')
    @include('livewire.partials.mp-products-import-modal')
    @include('livewire.partials.mp-products-cost-update-modal')
    @include('livewire.partials.mp-products-cogs-wizard-modal')
    @include('livewire.partials.mp-products-match-wizard-modal')
</div>
