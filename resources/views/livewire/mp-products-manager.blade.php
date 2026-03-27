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

    $activeFilters = array_values(array_filter([
        $search !== '' ? 'Arama: ' . $search : null,
        $filterCategory !== 'all' ? 'Kategori: ' . $filterCategory : null,
        $filterBrand !== 'all' ? 'Marka: ' . $filterBrand : null,
        $marketplaceFilter !== 'all' ? 'Kanal: ' . $this->humanMarketplace($marketplaceFilter) : null,
        $listingCoverageFilter !== 'all'
            ? 'Kapsam: ' . match ($listingCoverageFilter) {
                'listed' => 'Listelenen',
                'unlisted' => 'Kanalı olmayan',
                'multi_channel' => 'Çok kanallı',
                'issues' => 'Sorunlu',
                default => $listingCoverageFilter,
            }
            : null,
    ]));
    $guidanceItems = collect($diagnosticsGuidance['items'] ?? []);
    $primaryGuidance = $guidanceItems->first();
    $secondaryGuidance = $guidanceItems->slice(1)->take(4)->values();
@endphp

@once
    <style>
        .mp-products-v2-table {
            table-layout: fixed;
            width: 100%;
        }

        .mp-products-v2-table th,
        .mp-products-v2-table tbody > tr:not(.expanded-row) > td {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
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

<div class="w-full space-y-6 overflow-hidden" x-data="{ expanded: [], advancedFilters: false }">

    {{-- Flash mesajları --}}
    @if(session()->has('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    @if(session()->has('warning'))
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            {{ session('warning') }}
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
                        Pazaryeri Ürünleri
                    </div>
                    <h1 class="text-2xl lg:text-3xl font-bold text-slate-900">Ürün Yönetimi</h1>
                    <p class="mt-2 text-sm lg:text-base text-slate-500">
                        İç ürün kartlarını maliyet otoritesi olarak koruyun; kanal listinglerini, stok ve fiyat akışlarını aynı ekranda yönetin.
                    </p>
                </div>
                <div class="mt-8 flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                    <button wire:click="openImportModal"
                            class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">
                        <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                        </svg>
                        Excel İçe Aktar
                    </button>
                    <button wire:click="exportExcel"
                            class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">
                        <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Dışa Aktar
                    </button>
                    <button wire:click="openCreateModal"
                            class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-3 sm:py-2 text-sm font-medium text-white transition hover:bg-indigo-700">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        Yeni Ürün
                    </button>
                </div>
            </div>

            {{-- Sağ kısım: Metrikler --}}
            <div class="xl:col-span-5 grid grid-cols-2 gap-3 lg:gap-4">
                <div class="flex flex-col justify-center rounded-xl border border-slate-200 bg-slate-50 p-4 lg:p-5 min-w-0">
                    <p class="text-[10px] lg:text-xs uppercase tracking-[0.2em] font-medium text-slate-500 truncate">Master Ürün</p>
                    <p class="mt-2 text-2xl lg:text-3xl font-bold text-slate-900 truncate">{{ $formatCount($stats['total_products']) }}</p>
                    <p class="mt-1 text-[11px] font-medium text-slate-500 truncate">{{ $stats['listed_products'] }} listelenen · {{ $stats['unlisted_products'] }} kanalsız</p>
                </div>
                <div class="flex flex-col justify-center rounded-xl border border-slate-200 bg-slate-50 p-4 lg:p-5 min-w-0">
                    <p class="text-[10px] lg:text-xs uppercase tracking-[0.2em] font-medium text-slate-500 truncate">Toplam Listing</p>
                    <p class="mt-2 text-2xl lg:text-3xl font-bold text-slate-900 truncate">{{ $formatCount($stats['total_listings']) }}</p>
                    <p class="mt-1 text-[11px] font-medium text-slate-500 truncate">{{ $stats['active_listings'] }} yayında</p>
                </div>
                <div class="flex flex-col justify-center rounded-xl border border-slate-200 bg-slate-50 p-4 lg:p-5 min-w-0">
                    <p class="text-[10px] lg:text-xs uppercase tracking-[0.2em] font-medium text-slate-500 truncate">Çok Kanallı</p>
                    <p class="mt-2 text-2xl lg:text-3xl font-bold text-slate-900 truncate">{{ $formatCount($stats['multi_channel_products']) }}</p>
                    <p class="mt-1 text-[11px] font-medium text-slate-500 truncate">Birden fazla kanalda bağlı</p>
                </div>
                <div class="flex flex-col justify-center rounded-xl border {{ $stats['pending_match_issues'] > 0 ? 'border-amber-200 bg-amber-50' : 'border-slate-200 bg-slate-50' }} p-4 lg:p-5 min-w-0">
                    <p class="text-[10px] lg:text-xs uppercase tracking-[0.2em] font-medium {{ $stats['pending_match_issues'] > 0 ? 'text-amber-800' : 'text-slate-500' }} truncate">Eşleşme Sorunu</p>
                    <p class="mt-2 text-2xl lg:text-3xl font-bold {{ $stats['pending_match_issues'] > 0 ? 'text-amber-700' : 'text-slate-900' }} truncate">{{ $formatCount($stats['pending_match_issues']) }}</p>
                    <p class="mt-1 text-[11px] font-medium {{ $stats['pending_match_issues'] > 0 ? 'text-amber-700' : 'text-slate-500' }} truncate">{{ $stats['queued_pushes'] }} sıralı · {{ $stats['failed_pushes'] }} hata</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Guidance banner --}}
    @include('livewire.partials.mp-guidance-banner', [
        'diagnosticsGuidance' => $diagnosticsGuidance,
        'guidanceItems' => $diagnosticsGuidance['items'] ?? [],
        'primaryGuidance' => $primaryGuidance,
        'secondaryGuidance' => $secondaryGuidance,
    ])

    {{-- ═══════════════════════════════════════════════ --}}
    {{-- BLOK 2: TABLO BAŞLANGICI --}}
    {{-- ═══════════════════════════════════════════════ --}}
    <section class="rounded-2xl border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">

        {{-- FİLTRELER --}}
        <div class="space-y-4 mb-6">
        <div class="flex flex-col lg:flex-row gap-3">
            <div class="relative flex-1">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input wire:model.live.debounce.300ms="search"
                       type="text"
                       placeholder="Ürün adı, barkod, stok kodu arayın..."
                       class="w-full rounded-2xl border border-slate-200 bg-slate-50 py-3 pl-10 pr-4 text-sm font-medium text-slate-900 shadow-sm transition focus:border-slate-900 focus:bg-white focus:outline-none">
            </div>
            <select wire:model.live="filterStatus"
                    class="w-full lg:w-44 rounded-lg border border-slate-200 bg-white px-4 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                <option value="all">Tüm Durumlar</option>
                <option value="active">Satışta</option>
                <option value="out_of_stock">Tükendi</option>
                <option value="pending">Onay bekliyor</option>
                <option value="suspended">Beklemede</option>
            </select>
            <select wire:model.live="filterCategory"
                    class="w-full lg:w-44 rounded-lg border border-slate-200 bg-white px-4 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                <option value="all">Tüm Kategoriler</option>
                @foreach($this->categories as $category)
                    <option value="{{ $category }}">{{ $category }}</option>
                @endforeach
            </select>
            <select wire:model.live="listingCoverageFilter"
                    class="w-full lg:w-44 rounded-lg border border-slate-200 bg-white px-4 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                <option value="all">Kanal Kapsamı</option>
                <option value="listed">Listelenen</option>
                <option value="unlisted">Kanalsız</option>
                <option value="multi_channel">Çok kanallı</option>
                <option value="issues">Sorunlu</option>
            </select>
            <div class="flex items-center gap-2">
                <button type="button"
                        @click="advancedFilters = !advancedFilters"
                        class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                    <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                    </svg>
                    Gelişmiş
                </button>
                @if(count($activeFilters) > 0)
                    <button wire:click="resetFilters"
                            class="inline-flex min-h-[44px] items-center justify-center rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-medium text-rose-600 transition hover:bg-rose-100">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                @endif
            </div>
        </div>

        {{-- Gelişmiş filtreler --}}
        <div x-show="advancedFilters" x-cloak x-transition class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-6 gap-3">
            <select wire:model.live="marketplaceFilter"
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                <option value="all">Tüm Pazaryerleri</option>
                @foreach($marketplaceOptions as $marketplace)
                    <option value="{{ $marketplace }}">{{ $this->humanMarketplace($marketplace) }}</option>
                @endforeach
            </select>
            <select wire:model.live="filterBrand"
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                <option value="all">Tüm Markalar</option>
                @foreach($this->brands as $brand)
                    <option value="{{ $brand }}">{{ $brand }}</option>
                @endforeach
            </select>
            <select wire:model.live="listingStatusFilter"
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                <option value="all">Listing Durumu</option>
                <option value="active">Yayında</option>
                <option value="passive">Pasif</option>
                <option value="draft">Taslak</option>
            </select>
            <select wire:model.live="legalEntityFilter"
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                <option value="all">Tüm Firmalar</option>
                @foreach($legalEntities as $entity)
                    <option value="{{ $entity->id }}">{{ $entity->name }}</option>
                @endforeach
            </select>
            <select wire:model.live="filterStockLevel"
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                <option value="all">Stok Seviyesi</option>
                <option value="in_stock">Stokta</option>
                <option value="critical">Kritik</option>
                <option value="out_of_stock">Tükendi</option>
            </select>
            <select wire:model.live="filterCostDefined"
                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                <option value="all">Maliyet Durumu</option>
                <option value="yes">COGS tanımlı</option>
                <option value="no">COGS eksik</option>
            </select>
        </div>
    </div>

    {{-- Tablo araç çubuğu --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
        <div class="flex items-center gap-3 text-sm text-slate-500">
            <span>{{ $formatCount($products->total()) }} ürün</span>
            @if(count($activeFilters) > 0)
                <span class="text-xs">· {{ implode(' · ', array_slice($activeFilters, 0, 3)) }}</span>
            @endif
        </div>
        <div class="flex items-center gap-2">
            @include('livewire.partials.mp-column-toggle', ['columnDefs' => $columnDefs, 'visibleColumns' => $visibleColumns])

            @if(count($selectedProducts) > 0)
                <div x-data="{ bulkOpen: false }" class="relative">
                    <button @click="bulkOpen = !bulkOpen"
                            type="button"
                            class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-lg border border-indigo-300 bg-indigo-50 px-4 py-3 sm:py-2 text-sm font-medium text-indigo-700 transition hover:bg-indigo-100">
                        Toplu İşlem
                        <span class="rounded-full bg-indigo-100 px-1.5 py-0.5 text-[10px] font-medium text-indigo-700">{{ count($selectedProducts) }}</span>
                    </button>
                    <div x-show="bulkOpen"
                         @click.outside="bulkOpen = false"
                         x-transition
                         class="absolute right-0 top-full z-30 mt-2 w-48 rounded-xl border border-slate-200 bg-white py-1 shadow-xl">
                        <button wire:click="bulkUpdateStatus('active')" @click="bulkOpen = false" class="flex w-full items-center gap-2 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50">Satışa al</button>
                        <button wire:click="bulkUpdateStatus('suspended')" @click="bulkOpen = false" class="flex w-full items-center gap-2 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50">Beklet</button>
                        <button wire:click="bulkDelete" wire:confirm="Seçili ürünleri silmek istediğinize emin misiniz?" @click="bulkOpen = false" class="flex w-full items-center gap-2 px-3 py-2 text-sm text-rose-600 transition hover:bg-rose-50">Sil</button>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════ --}}
    {{-- TABLO --}}
    {{-- ═══════════════════════════════════════════════ --}}
    {{-- Mobil kart görünümü --}}
    <div class="space-y-3 md:hidden">
        @forelse($products as $product)
            @php
                $listingCount = (int) ($product->listing_count_metric ?? 0);
                $profitValue = (float) ($product->profit_metric ?? 0);
                $channelStock = (int) ($product->channel_stock_total_metric ?? 0);
            @endphp

            <article class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <button type="button"
                        class="w-full p-4 text-left"
                        @click="expanded.includes({{ $product->id }}) ? expanded = expanded.filter(i => i !== {{ $product->id }}) : expanded.push({{ $product->id }})">
                    <div class="flex items-start gap-3">
                        <input type="checkbox"
                               value="{{ $product->id }}"
                               wire:model.live="selectedProducts"
                               class="mt-1 rounded border-slate-300 text-slate-900 shadow-sm focus:ring-indigo-200">

                        @if($product->main_image)
                            <img src="{{ $product->main_image }}" alt="" class="h-14 w-14 rounded-xl border border-slate-200 object-cover">
                        @else
                            <div class="flex h-14 w-14 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 text-slate-400">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                        @endif

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <svg class="h-4 w-4 shrink-0 text-slate-400 transition"
                                     :class="{ 'rotate-90 text-slate-900': expanded.includes({{ $product->id }}) }"
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                                <p class="truncate text-sm font-semibold text-slate-900">{{ $product->product_name ?: 'İsimsiz ürün' }}</p>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">{{ $product->stock_code ?: '-' }} · {{ $product->barcode ?: '-' }}</p>
                            <div class="mt-3 grid grid-cols-2 gap-2">
                                <div class="rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2">
                                    <p class="text-[10px] uppercase tracking-[0.16em] text-slate-500">Listing</p>
                                    <p class="mt-1 text-sm font-medium text-slate-900">{{ $listingCount }}</p>
                                </div>
                                <div class="rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2">
                                    <p class="text-[10px] uppercase tracking-[0.16em] text-slate-500">Kanal stok</p>
                                    <p class="mt-1 text-sm font-medium text-slate-900">{{ $channelStock }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="text-right">
                            <x-zolm.status-badge :tone="$product->status === 'active' ? 'success' : ($product->status === 'out_of_stock' ? 'danger' : 'warning')">
                                {{ $product->status_label }}
                            </x-zolm.status-badge>
                            <p class="mt-3 text-sm font-semibold text-slate-900">{{ $formatMoney($product->sale_price) }}</p>
                            <p class="mt-1 text-sm font-semibold {{ $profitValue >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ $formatMoney($profitValue) }}</p>
                        </div>
                    </div>
                </button>

                <div x-show="expanded.includes({{ $product->id }})" x-cloak x-transition class="border-t border-slate-200 bg-slate-50/60 p-4">
                    @include('livewire.partials.mp-product-v2-detail', ['product' => $product])
                </div>
            </article>
        @empty
            <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center text-sm text-slate-500">
                Kayıtlı master ürün bulunamadı. Excel içe aktarma veya manuel ürün ekleme ile başlayabilirsiniz.
            </div>
        @endforelse
    </div>

    {{-- Desktop tablo --}}
    <div class="hidden md:block overflow-hidden rounded-lg border border-slate-200">
        @php
            $columnMeta = [
                'urun' => ['label' => 'Ürün', 'width' => '300px'],
                'kanal' => ['label' => 'Kanal', 'width' => '160px'],
                'fiyat' => ['label' => 'Fiyat', 'width' => '120px'],
                'maliyet' => ['label' => 'Maliyet', 'width' => '130px'],
                'stok' => ['label' => 'Stok', 'width' => '120px'],
                'karlilik' => ['label' => 'Kârlılık', 'width' => '130px'],
                'durum' => ['label' => 'Durum', 'width' => '120px'],
                'islem' => ['label' => 'İşlem', 'width' => '100px'],
            ];
        @endphp

        <div class="overflow-x-auto -mx-4 lg:-mx-6 px-4 lg:px-6" x-data="columnResize()">
            <table class="mp-products-v2-table min-w-[1000px] w-full divide-y divide-slate-200 table-fixed">
                <thead class="bg-slate-50 text-slate-500">
                    <tr>
                        <th class="w-10 px-3 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em]">
                            <input type="checkbox" wire:model.live="selectAll" class="rounded border-slate-300 text-slate-900 shadow-sm focus:ring-indigo-200">
                        </th>
                        <th class="w-10 px-3 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em]"></th>
                        @foreach($columnMeta as $columnKey => $meta)
                            @if(in_array($columnKey, $visibleColumns, true))
                                <th class="relative px-3 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em]"
                                    style="width: {{ $meta['width'] }}; min-width: {{ $meta['width'] }};"
                                    @if(isset($sortableColumns[$columnKey])) wire:click="sortTable('{{ $columnKey }}')" @endif>
                                    <div class="flex items-center justify-between gap-2">
                                        <span>{{ $meta['label'] }}</span>
                                        @if(isset($sortableColumns[$columnKey]))
                                            <span class="text-[10px] {{ ($sortableColumns[$columnKey] ?? null) === $sortField ? 'text-slate-700' : 'text-slate-300' }}">{{ $sortIcon($columnKey) }}</span>
                                        @endif
                                    </div>
                                    <span class="col-resize-handle" @mousedown.prevent="startResize($event, $el.parentElement)"></span>
                                </th>
                            @endif
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white text-sm text-slate-700">
                    @forelse($products as $product)
                        @php
                            $listingCount = (int) ($product->listing_count_metric ?? 0);
                            $profitValue = (float) ($product->profit_metric ?? 0);
                        @endphp

                        <tr class="transition hover:bg-slate-50/80">
                            <td class="px-3 py-4 align-top">
                                <input type="checkbox" value="{{ $product->id }}" wire:model.live="selectedProducts" class="rounded border-slate-300 text-slate-900 shadow-sm focus:ring-indigo-200">
                            </td>
                            <td class="px-3 py-4 align-top">
                                <button type="button"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50 hover:text-slate-900"
                                        @click="expanded.includes({{ $product->id }}) ? expanded = expanded.filter(i => i !== {{ $product->id }}) : expanded.push({{ $product->id }})">
                                    <svg class="h-4 w-4 transition" :class="{ 'rotate-90': expanded.includes({{ $product->id }}) }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </button>
                            </td>

                            @if(in_array('urun', $visibleColumns, true))
                                <td class="px-3 py-4 align-top">
                                    <div class="flex items-start gap-3">
                                        @if($product->main_image)
                                            <img src="{{ $product->main_image }}" alt="" class="h-12 w-12 rounded-xl border border-slate-200 object-cover">
                                        @else
                                            <div class="flex h-12 w-12 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 text-slate-400">
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                            </div>
                                        @endif

                                        <div class="min-w-0">
                                            <div class="font-semibold text-slate-900 truncate">{{ $product->product_name ?: 'İsimsiz ürün' }}</div>
                                            <div class="mt-1 text-xs text-slate-500 truncate">{{ $product->stock_code ?: '-' }} · {{ $product->barcode ?: '-' }}</div>
                                            <div class="mt-2 flex items-center gap-2 text-[11px] text-slate-400">
                                                @if($product->brand)
                                                    <span>{{ $product->brand }}</span>
                                                @endif
                                                @if($product->category_name)
                                                    <span>· {{ $product->category_name }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            @endif

                            @if(in_array('kanal', $visibleColumns, true))
                                <td class="px-3 py-4 align-top">
                                    <div class="font-semibold text-slate-900">{{ $listingCount }} listing</div>
                                    <div class="mt-1 text-xs text-slate-500">{{ (int) ($product->active_listing_count_metric ?? 0) }} yayında</div>
                                    <div class="mt-2 text-[11px] {{ (int) ($product->pending_issue_count_metric ?? 0) > 0 ? 'text-amber-600' : 'text-slate-400' }}">
                                        {{ (int) ($product->pending_issue_count_metric ?? 0) > 0 ? ($product->pending_issue_count_metric . ' eşleşme sorunu') : 'Sorun görünmüyor' }}
                                    </div>
                                </td>
                            @endif

                            @if(in_array('fiyat', $visibleColumns, true))
                                <td class="px-3 py-4 align-top text-right">
                                    <div class="font-semibold text-slate-900">{{ $formatMoney($product->sale_price) }}</div>
                                    <div class="mt-1 text-xs text-slate-500">Piyasa {{ $formatMoney($product->market_price) }}</div>
                                </td>
                            @endif

                            @if(in_array('maliyet', $visibleColumns, true))
                                <td class="px-3 py-4 align-top text-right">
                                    <div class="font-semibold text-slate-900">{{ $formatMoney(($product->cogs ?? 0) + ($product->packaging_cost ?? 0) + ($product->cargo_cost ?? 0)) }}</div>
                                    <div class="mt-1 text-xs text-slate-500">COGS {{ $formatMoney($product->cogs) }}</div>
                                </td>
                            @endif

                            @if(in_array('stok', $visibleColumns, true))
                                <td class="px-3 py-4 align-top text-right">
                                    <div class="font-semibold text-slate-900">{{ $formatCount($product->stock_quantity) }}</div>
                                    <div class="mt-1 text-xs text-slate-500">Kanal {{ $formatCount($product->channel_stock_total_metric ?? 0) }}</div>
                                </td>
                            @endif

                            @if(in_array('karlilik', $visibleColumns, true))
                                <td class="px-3 py-4 align-top text-right">
                                    <div class="font-semibold {{ $profitValue >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ $formatMoney($profitValue) }}</div>
                                    <div class="mt-1 text-xs text-slate-500">%{{ number_format($product->profit_margin ?? 0, 1, ',', '.') }}</div>
                                </td>
                            @endif

                            @if(in_array('durum', $visibleColumns, true))
                                <td class="px-3 py-4 align-top">
                                    <x-zolm.status-badge :tone="$product->status === 'active' ? 'success' : ($product->status === 'out_of_stock' ? 'danger' : 'warning')">
                                        {{ $product->status_label }}
                                    </x-zolm.status-badge>
                                </td>
                            @endif

                            @if(in_array('islem', $visibleColumns, true))
                                <td class="px-3 py-4 align-top text-right">
                                    <div x-data="{ open: false }" class="relative inline-block text-left">
                                        <button @click="open = !open"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50 hover:text-slate-900">
                                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M10 6a2 2 0 110-4 2 2 0 010 4zm0 6a2 2 0 110-4 2 2 0 010 4zm0 6a2 2 0 110-4 2 2 0 010 4z"/>
                                            </svg>
                                        </button>

                                        <div x-show="open"
                                             @click.outside="open = false"
                                             x-transition
                                             class="absolute right-0 top-full z-20 mt-2 w-40 rounded-xl border border-slate-200 bg-white py-1 shadow-xl">
                                            <button wire:click="editProduct({{ $product->id }})" @click="open = false" class="flex w-full items-center gap-2 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50">Düzenle</button>
                                            <button wire:click="duplicateProduct({{ $product->id }})" @click="open = false" class="flex w-full items-center gap-2 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50">Çoğalt</button>
                                            <button wire:click="deleteProduct({{ $product->id }})" wire:confirm="Bu ürünü silmek istediğinize emin misiniz?" @click="open = false" class="flex w-full items-center gap-2 px-3 py-2 text-sm text-rose-600 transition hover:bg-rose-50">Sil</button>
                                        </div>
                                    </div>
                                </td>
                            @endif
                        </tr>

                        <tr x-show="expanded.includes({{ $product->id }})" x-cloak x-transition class="expanded-row bg-slate-50/60">
                            <td colspan="{{ count($visibleColumns) + 2 }}" class="p-0">
                                <div class="px-4 py-4 lg:px-6 w-full max-w-full overflow-hidden">
                                    @include('livewire.partials.mp-product-v2-detail', ['product' => $product])
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($visibleColumns) + 2 }}" class="px-6 py-12 text-center text-sm text-slate-500">
                                Kayıtlı ürün bulunamadı. Excel yükleyebilir veya manuel ürün ekleyebilirsiniz.
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

    {{-- TABLO STİLLERİ --}}
    <style>
        .mp-products-v2-table {
            table-layout: fixed;
            width: 100%;
        }
        .mp-products-v2-table th, .mp-products-v2-table tbody > tr:not(.expanded-row) > td {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
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

