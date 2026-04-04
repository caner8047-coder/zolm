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
    ]));
    $advancedFiltersActive = $marketplaceFilter !== 'all'
        || $filterBrand !== 'all'
        || $listingStatusFilter !== 'all'
        || $legalEntityFilter !== 'all'
        || $filterStockLevel !== 'all'
        || $filterCostDefined !== 'all';
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
        init() {
            try {
                this.workspaceCollapsed = JSON.parse(window.localStorage.getItem('products-workspace-collapsed') ?? 'false');
            } catch (error) {
                this.workspaceCollapsed = false;
            }
        },
        toggleWorkspace() {
            this.workspaceCollapsed = !this.workspaceCollapsed;

            try {
                window.localStorage.setItem('products-workspace-collapsed', JSON.stringify(this.workspaceCollapsed));
            } catch (error) {}
        }
     }">
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
                background:
                    linear-gradient(180deg, rgba(248, 250, 252, 0.96), rgba(255, 255, 255, 1));
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
                background:
                    linear-gradient(180deg, rgba(248, 250, 252, 0.88), rgba(255, 255, 255, 1));
            }

            .mp-products-v2-table {
                table-layout: fixed;
                width: 100%;
            }

            .mp-products-v2-table th {
                white-space: nowrap;
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

    {{-- Flash mesajları --}}
    @if(session()->has('success'))
        <div class="rounded-[8px] border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    @if(session()->has('warning'))
        <div class="rounded-[8px] border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            {{ session('warning') }}
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
                                Master ürün kartlarını maliyet otoritesi olarak koruyun; kanal listinglerini, stok ve fiyat akışlarını Venture uyumlu tek workspace içinde yönetin.
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
                                <button wire:click="exportExcel"
                                        wire:loading.attr="disabled"
                                        wire:target="exportExcel"
                                        class="inline-flex min-h-[48px] items-center justify-center gap-2 rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                    <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    Dışa Aktar
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
                                    <p class="text-slate-500">Master görünüm</p>
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
                                    <x-zolm.help-tip title="Listeleme kapsamı" summary="Master ürünlerin ne kadarının en az bir kanalda listelendiğini yüzde olarak gösterir." source="Master ürün sayısı ve aktif listeleme kayıtları." refresh="Yeni listeleme açıldığında, kapandığında veya senkron geldiğinde." impact="Kanal yayılımını ve büyüme boşluğunu hızlıca gösterir." />
                                </div>
                                <p class="mt-3 text-3xl font-bold tracking-tight text-slate-950">%{{ $listedCoverage }}</p>
                                <div class="mt-3 h-2 rounded-full bg-slate-100">
                                    <div class="h-2 rounded-full bg-slate-900 transition-all" style="width: {{ min(100, max(0, $listedCoverage)) }}%"></div>
                                </div>
                            </div>
                            <div class="rounded-[10px] border border-slate-200 bg-white px-4 py-4">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Yayında</p>
                                <p class="mt-2 text-2xl font-bold tracking-tight text-slate-950">{{ $formatCount($stats['listed_products']) }}</p>
                                <p class="mt-2 text-xs text-emerald-600">{{ $formatCount($stats['active_listings']) }} aktif listing</p>
                            </div>
                            <div class="rounded-[10px] border border-slate-200 bg-white px-4 py-4">
                                <div class="flex items-center gap-1.5">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Eşleşme riski</p>
                                    <x-zolm.help-tip title="Eşleşme riski" summary="Master ürünle kanal listelemesi arasında net ilişki kurulamayan kayıtları gösterir." source="Eşleşme sorun kayıtları ve bağsız listeleme sayısı." refresh="Sorun çözüldüğünde veya yeni listeleme geldiğinde." impact="Fiyat, kâr ve stok akışındaki güvenilirliği etkiler." />
                                </div>
                                <p class="mt-2 text-2xl font-bold tracking-tight text-slate-950">{{ $formatCount($stats['pending_match_issues']) }}</p>
                                <p class="mt-2 text-xs text-amber-600">{{ $formatCount($sidebarSummary['orphan_listings'] ?? 0) }} orphan listing</p>
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
                                <p class="mt-2 text-sm font-medium text-slate-700">toplam master ürün</p>
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
                            <p class="mt-3 text-sm font-semibold text-slate-900">Master ürün ve kanal akışı tek çalışma panelinde</p>
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
                            <input wire:model.live.debounce.300ms="search"
                                   type="text"
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

                    <div x-show="advancedFilters" x-cloak x-transition class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
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
                                            wire:target="bulkUpdateStatus,bulkDelete"
                                            class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] border border-indigo-300 bg-indigo-50 px-4 py-3 text-sm font-medium text-indigo-700 transition hover:bg-indigo-100 disabled:cursor-not-allowed disabled:opacity-60">
                                        Toplu İşlem
                                        <span class="rounded-[6px] bg-indigo-100 px-1.5 py-0.5 text-[10px] font-medium text-indigo-700">{{ count($selectedProducts) }}</span>
                                    </button>
                                    <div x-show="bulkOpen"
                                         @click.outside="bulkOpen = false"
                                         x-transition
                                         class="absolute left-0 right-0 top-full z-30 mt-2 rounded-[8px] border border-slate-200 bg-white py-1 shadow-xl sm:left-auto sm:w-52">
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
                  wire:target="search,filterStatus,filterCategory,listingCoverageFilter,marketplaceFilter,filterBrand,listingStatusFilter,legalEntityFilter,filterStockLevel,filterCostDefined,perPage,resetFilters,sortTable"
                  class="inline-flex items-center gap-1.5 text-xs text-indigo-600">
                <svg class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="9" class="opacity-25" stroke="currentColor" stroke-width="3"></circle>
                    <path d="M21 12a9 9 0 0 0-9-9" class="opacity-75" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path>
                </svg>
                Liste güncelleniyor
            </span>
        </div>
    </div>

    {{-- Mobil kart görünümü --}}
    <div class="space-y-3 md:hidden">
        @forelse($products as $product)
            @php
                $listingCount = (int) ($product->listing_count_metric ?? 0);
                $profitValue = (float) ($product->profit_metric ?? 0);
                $channelStock = (int) ($product->channel_stock_total_metric ?? 0);
            @endphp

            <article class="rounded-[8px] border border-slate-200 bg-white shadow-sm">
                <div class="p-4">
                    <div class="flex items-start gap-3">
                        <input type="checkbox"
                               value="{{ $product->id }}"
                               wire:model.live="selectedProducts"
                               @click.stop
                               class="mt-1 rounded border-slate-300 text-slate-900 shadow-sm focus:ring-indigo-200">

                        <button type="button"
                                class="flex min-w-0 flex-1 flex-col gap-3 text-left"
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
                                        <div class="min-w-0 flex-1">
                                            <p class="product-name-clamp text-sm font-semibold text-slate-900">{{ $product->product_name ?: 'İsimsiz ürün' }}</p>
                                        </div>
                                        <svg class="mt-0.5 h-4 w-4 shrink-0 text-slate-400 transition"
                                             :class="{ 'rotate-90 text-slate-900': expanded.includes({{ $product->id }}) }"
                                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </div>
                                    <p class="mt-1 text-xs text-slate-500">{{ $product->stock_code ?: '-' }} · {{ $product->barcode ?: '-' }}</p>
                                    <div class="mt-1 flex flex-wrap items-center gap-2 text-[11px] text-slate-400">
                                        @if($product->brand)
                                            <span>{{ $product->brand }}</span>
                                        @endif
                                        @if($product->category_name)
                                            <span>· {{ $product->category_name }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <x-zolm.status-badge :tone="$product->status === 'active' ? 'success' : ($product->status === 'out_of_stock' ? 'danger' : 'warning')">
                                    {{ $product->status_label }}
                                </x-zolm.status-badge>
                                <div class="text-right">
                                    <p class="text-sm font-semibold text-slate-900">{{ $formatMoney($product->sale_price) }}</p>
                                    <p class="mt-1 text-sm font-semibold {{ $profitValue >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ $formatMoney($profitValue) }}</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-3 py-2">
                                    <p class="text-[10px] uppercase tracking-[0.16em] text-slate-500">Listeleme</p>
                                    <p class="mt-1 text-sm font-medium text-slate-900">{{ $listingCount }}</p>
                                </div>
                                <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-3 py-2">
                                    <p class="text-[10px] uppercase tracking-[0.16em] text-slate-500">Kanal stok</p>
                                    <p class="mt-1 text-sm font-medium text-slate-900">{{ $channelStock }}</p>
                                </div>
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
                    Kayıtlı master ürün bulunamadı. Excel içe aktarma veya manuel ürün ekleme ile başlayabilirsiniz.
                @endif
            </div>
        @endforelse
    </div>

    {{-- Desktop tablo --}}
    <div class="products-ledger-shell hidden md:block rounded-[10px] border border-slate-200 p-3 shadow-sm">
        @php
            $columnMeta = [
                'urun' => ['label' => 'Ürün'],
                'kanal' => ['label' => 'Kanal', 'width' => '92px'],
                'fiyat' => ['label' => 'Fiyat', 'width' => '92px'],
                'cogs' => ['label' => 'Maliyet', 'width' => '92px'],
                'kargo' => ['label' => 'Kargo', 'width' => '82px'],
                'stok' => ['label' => 'Stok', 'width' => '68px'],
                'kdv' => ['label' => 'KDV', 'width' => '52px'],
                'roi' => ['label' => 'Kâr Oranı', 'width' => '92px'],
                'durum' => ['label' => 'Durum', 'width' => '86px'],
                'islem' => ['label' => 'İşlem', 'width' => '64px'],
            ];
            $columnHelp = [
                'cogs' => [
                    'title' => 'Maliyet',
                    'summary' => 'Ürünün birim maliyetini gösterir; kâr hesabının ana maliyet ayağıdır.',
                    'source' => 'Master ürün kartındaki maliyet alanı.',
                    'refresh' => 'Ürün düzenleme veya Excel içe aktarım sonrası.',
                    'impact' => 'Kâr oranı ve gerçek kâr hesaplarının doğruluğunu belirler.',
                ],
                'kdv' => [
                    'title' => 'KDV',
                    'summary' => 'Ürünün satışa uygulanan KDV oranını gösterir.',
                    'source' => 'Master ürün verisi ve kanal satış kuralı.',
                    'refresh' => 'Ürün kartı güncellendiğinde.',
                    'impact' => 'Net gelir ve vergi etkisi hesaplarında kullanılır.',
                ],
                'roi' => [
                    'title' => 'Kâr Oranı',
                    'summary' => 'Fiyat, maliyet ve kesinti bileşenlerine göre ürünün kârlılık oranını verir.',
                    'source' => 'Satış fiyatı, maliyet, komisyon, kargo ve KDV verileri.',
                    'refresh' => 'Ürün veya finans parametresi değiştiğinde yeniden hesaplanır.',
                    'impact' => 'Hızlı fiyatlama ve kârlılık kararlarını yönlendirir.',
                ],
            ];
        @endphp

        <div class="mb-3 flex flex-col gap-3 rounded-[8px] border border-slate-200 bg-white/90 px-4 py-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Ürün Kayıt Defteri</p>
                <p class="mt-1 text-sm font-semibold text-slate-900">Canlı ürün tablosu</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2.5 py-1 text-slate-600">{{ $formatCount($products->total()) }} ürün</span>
                <div class="hidden md:block">
                    @include('livewire.partials.mp-column-toggle', ['columnDefs' => $columnDefs, 'visibleColumns' => $visibleColumns])
                </div>

                @if(count($selectedProducts) > 0)
                    <div x-data="{ bulkOpen: false }" class="relative w-full sm:w-auto">
                        <button @click="bulkOpen = !bulkOpen"
                                type="button"
                                wire:loading.attr="disabled"
                                wire:target="bulkUpdateStatus,bulkDelete"
                                class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] border border-indigo-300 bg-indigo-50 px-4 py-3 text-sm font-medium text-indigo-700 transition hover:bg-indigo-100 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto sm:py-2">
                            Toplu İşlem
                            <span class="rounded-[6px] bg-indigo-100 px-1.5 py-0.5 text-[10px] font-medium text-indigo-700">{{ count($selectedProducts) }}</span>
                        </button>
                        <div x-show="bulkOpen"
                             @click.outside="bulkOpen = false"
                             x-transition
                             class="absolute right-0 top-full z-30 mt-2 w-48 rounded-[8px] border border-slate-200 bg-white py-1 shadow-xl">
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

        <div class="w-full overflow-x-auto" x-data="columnResize()">
            <table class="mp-products-v2-table divide-y divide-slate-200 table-fixed">
                <thead class="bg-slate-50 text-slate-500">
                    <tr>
                        <th class="w-9 px-1.5 py-3 text-center text-[11px] font-semibold uppercase tracking-[0.14em]">
                            <input type="checkbox" wire:model.live="selectAll" class="rounded border-slate-300 text-slate-900 shadow-sm focus:ring-indigo-200">
                        </th>
                        @foreach($columnMeta as $columnKey => $meta)
                            @if(in_array($columnKey, $visibleColumns, true))
                                <th class="relative px-2 py-3 text-left text-[11px] font-semibold uppercase tracking-[0.14em]"
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
                <tbody class="divide-y divide-slate-200 bg-white text-[13px] text-slate-700">
                    @forelse($products as $product)
                        @php
                            $listingCount = (int) ($product->listing_count_metric ?? 0);
                            $profitValue = (float) ($product->profit_metric ?? 0);
                            $profitMargin = $product->profit_margin;
                            $hasDistinctStockCode = filled($product->stock_code) && $product->stock_code !== $product->barcode;
                            $desiValue = (float) ($product->desi ?? 0);
                            $desiLabel = floor($desiValue) === $desiValue
                                ? number_format($desiValue, 0, ',', '.')
                                : number_format($desiValue, 1, ',', '.');
                            $shippingSummary = ((int) ($product->pieces ?? 1)) . 'P ' . $desiLabel . 'D';
                            $signedProfitLabel = ($profitValue > 0 ? '+' : '') . $formatMoney($profitValue);
                        @endphp

                        <tr wire:key="mp-product-row-{{ $product->id }}" class="transition hover:bg-slate-50/80">
                            <td class="w-9 px-1.5 py-3 align-top text-center">
                                <input type="checkbox" value="{{ $product->id }}" wire:model.live="selectedProducts" class="rounded border-slate-300 text-slate-900 shadow-sm focus:ring-indigo-200">
                            </td>

                            @if(in_array('urun', $visibleColumns, true))
                                <td class="pl-0.5 pr-2 py-3 align-top">
                                    <div class="flex items-start gap-2">
                                        <button type="button"
                                                class="group relative mt-0.5 inline-flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-[8px] border border-slate-200 bg-white transition hover:border-slate-300"
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

                                        <div class="min-w-0">
                                            <div class="product-name-clamp text-[14px] font-semibold leading-5 text-slate-900">{{ $product->product_name ?: 'İsimsiz ürün' }}</div>
                                            <div class="mt-1 text-[10px] text-slate-500">
                                                <span>{{ $product->barcode ?: '-' }}</span>
                                                @if($hasDistinctStockCode)
                                                    <span> · {{ $product->stock_code }}</span>
                                                @endif
                                            </div>
                                            <div class="mt-1 flex items-center gap-2 text-[10px] text-slate-400">
                                                @if($product->brand)
                                                    <span>{{ $product->brand }}</span>
                                                @endif
                                                @if($product->category_name)
                                                    <span>· {{ $product->category_name }}</span>
                                                @endif
                                            </div>
                                            @if(!in_array('kanal', $visibleColumns, true))
                                                <div class="mt-1 text-[10px] text-slate-400">
                                                    {{ $listingCount }} listing · {{ (int) ($product->active_listing_count_metric ?? 0) }} yayında
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            @endif

                            @if(in_array('kanal', $visibleColumns, true))
                                <td class="px-2 py-3 align-top">
                                    <div class="font-semibold text-slate-900">{{ $listingCount }} listing</div>
                                    <div class="mt-1 text-[10px] text-slate-500">{{ (int) ($product->active_listing_count_metric ?? 0) }} yayında</div>
                                    <div class="mt-1 text-[10px] {{ (int) ($product->pending_issue_count_metric ?? 0) > 0 ? 'text-amber-600' : 'text-slate-400' }}">
                                        {{ (int) ($product->pending_issue_count_metric ?? 0) > 0 ? ($product->pending_issue_count_metric . ' eşleşme sorunu') : 'Sorun görünmüyor' }}
                                    </div>
                                </td>
                            @endif

                            @if(in_array('fiyat', $visibleColumns, true))
                                <td class="px-2 py-3 align-top text-right">
                                    <div class="font-semibold text-slate-900">{{ $formatMoney($product->sale_price) }}</div>
                                    <div class="mt-1 text-[10px] text-slate-500">%{{ number_format((float) ($product->commission_rate ?? 0), 1, ',', '.') }} kom.</div>
                                </td>
                            @endif

                            @if(in_array('cogs', $visibleColumns, true))
                                <td class="px-2 py-3 align-top text-right">
                                    @if((float) ($product->cogs ?? 0) > 0)
                                        <div class="font-semibold text-slate-900">{{ $formatMoney($product->cogs) }}</div>
                                    @else
                                        <span class="inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700">Eksik</span>
                                    @endif
                                    <div class="mt-1 text-[10px] text-slate-500">
                                        {{ (float) ($product->packaging_cost ?? 0) > 0 ? '+' . $formatMoney($product->packaging_cost) . ' amb.' : 'Amb. ' . $formatMoney($product->packaging_cost) }}
                                    </div>
                                </td>
                            @endif

                            @if(in_array('kargo', $visibleColumns, true))
                                <td class="px-2 py-3 align-top text-right">
                                    <div class="font-semibold text-slate-700">{{ $shippingSummary }}</div>
                                    <div class="mt-1 text-[10px] {{ (float) ($product->cargo_cost ?? 0) > 0 ? 'text-amber-600' : 'text-slate-500' }}">
                                        {{ (float) ($product->cargo_cost ?? 0) > 0 ? $formatMoney($product->cargo_cost) : '—' }}
                                    </div>
                                </td>
                            @endif

                            @if(in_array('stok', $visibleColumns, true))
                                <td class="px-2 py-3 align-top text-center">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $product->stock_level_color }}">
                                        {{ $formatCount($product->stock_quantity) }}
                                    </span>
                                    <div class="mt-1 text-[10px] text-slate-500">Kanal {{ $formatCount($product->channel_stock_total_metric ?? 0) }}</div>
                                </td>
                            @endif

                            @if(in_array('kdv', $visibleColumns, true))
                                <td class="px-2 py-3 align-top text-center">
                                    <span class="inline-flex rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-semibold text-indigo-700">
                                        %{{ number_format((float) ($product->vat_rate ?? 0), 0, ',', '.') }}
                                    </span>
                                </td>
                            @endif

                            @if(in_array('roi', $visibleColumns, true))
                                <td class="px-2 py-3 align-top text-right">
                                    <div class="font-semibold {{ $profitValue >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                                        {{ $profitMargin !== null ? '%' . number_format((float) $profitMargin, 1, ',', '.') : '—' }}
                                    </div>
                                    <div class="mt-1 text-[10px] {{ $profitValue >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ $signedProfitLabel }}</div>
                                </td>
                            @endif

                            @if(in_array('durum', $visibleColumns, true))
                                <td class="px-2 py-3 align-top">
                                    <x-zolm.status-badge :tone="$product->status === 'active' ? 'success' : ($product->status === 'out_of_stock' ? 'danger' : 'warning')">
                                        {{ $product->status_label }}
                                    </x-zolm.status-badge>
                                </td>
                            @endif

                            @if(in_array('islem', $visibleColumns, true))
                                <td class="relative px-2 py-3 align-top text-right overflow-visible">
                                    <div x-data="{ actionMenuOpen: false }"
                                         @keydown.escape.window="actionMenuOpen = false"
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
                                             class="absolute right-0 top-10 z-30 w-60 rounded-[8px] border border-slate-200 bg-white p-2 shadow-xl">
                                            <div class="space-y-1">
                                                <p class="px-2 text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-400">Düzenleme</p>
                                                <button type="button"
                                                        @click="actionMenuOpen = false"
                                                        wire:click="openEditProductTab({{ $product->id }}, 'basic')"
                                                        wire:loading.attr="disabled"
                                                        wire:loading.class="cursor-wait opacity-60"
                                                        wire:target="openEditProductTab({{ $product->id }}, 'basic')"
                                                        title="Temel bilgiler ve ürün kartı"
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
                                                        wire:click="openEditProductTab({{ $product->id }}, 'pricing')"
                                                        wire:loading.attr="disabled"
                                                        wire:loading.class="cursor-wait opacity-60"
                                                        wire:target="openEditProductTab({{ $product->id }}, 'pricing')"
                                                        title="Maliyet, fiyat ve komisyon"
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
    @include('livewire.partials.mp-products-import-modal')
</div>
