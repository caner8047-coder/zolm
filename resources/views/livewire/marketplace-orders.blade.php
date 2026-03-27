@php
    $formatMoney = fn ($value) => '₺' . number_format((float) $value, 2, ',', '.');
    $formatCount = fn ($value) => number_format((float) $value, 0, ',', '.');
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
        .orders-v2-table {
            table-layout: fixed;
            width: 100%;
        }

        .orders-v2-table th,
        .orders-v2-table td {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
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

                        const newWidth = Math.max(84, this.startWidth + (moveEvent.pageX - this.startX));
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

<div class="w-full space-y-6">

    {{-- Flash mesajları --}}
    @if(session()->has('sync_message'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
            {{ session('sync_message') }}
        </div>
    @endif

    @if($importMessage)
        <div class="rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-800">
            {{ $importMessage }}
        </div>
    @endif

    @if($actionMessage !== '')
        <div class="rounded-xl border p-4 text-sm {{ $actionMessageTone === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-slate-50 text-slate-700' }}">
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
                        Pazaryeri Siparişleri
                    </div>
                    <h1 class="text-2xl lg:text-3xl font-bold text-slate-900">Sipariş Yönetimi</h1>
                    <p class="mt-2 text-sm lg:text-base text-slate-500">
                        Tüm detaylı operasyonel siparişlerinizi, muhasebe verilerini ve ürün satırlarını tek ekranda inceleyin.
                    </p>
                </div>
                <div class="mt-8 flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                    <button type="button"
                            wire:click="exportCsv"
                            wire:loading.attr="disabled"
                            class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">
                        <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Dışa Aktar (CSV)
                    </button>
                    <a href="{{ route('mp.integrations') }}"
                       class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 py-3 sm:py-2 text-sm font-medium text-white transition hover:bg-slate-800">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Finansal Senkronizasyon
                    </a>
                </div>
            </div>

            {{-- Sağ kısım: Metrikler --}}
            <div class="xl:col-span-5 grid grid-cols-2 gap-3 lg:gap-4">
                <div class="flex flex-col justify-center rounded-xl border border-slate-200 bg-slate-50 p-4 lg:p-5 min-w-0">
                    <p class="text-[10px] lg:text-xs uppercase tracking-[0.2em] font-medium text-slate-500 truncate">Toplam Sipariş</p>
                    <p class="mt-2 text-2xl lg:text-3xl font-bold text-slate-900 truncate">{{ $formatCount($stats['total_orders']) }}</p>
                </div>
                <div class="flex flex-col justify-center rounded-xl border border-slate-200 bg-slate-50 p-4 lg:p-5 min-w-0">
                    <p class="text-[10px] lg:text-xs uppercase tracking-[0.2em] font-medium text-slate-500 truncate">Ciro</p>
                    <p class="mt-2 text-lg lg:text-2xl xl:text-xl font-bold text-slate-900 truncate" title="{{ $formatMoney($stats['total_revenue']) }}">{{ $formatMoney($stats['total_revenue']) }}</p>
                </div>
                <div class="flex flex-col justify-center rounded-xl border border-slate-200 bg-slate-50 p-4 lg:p-5 min-w-0">
                    <p class="text-[10px] lg:text-xs uppercase tracking-[0.2em] font-medium text-slate-500 truncate">Ort. Sepet</p>
                    <p class="mt-2 text-xl lg:text-2xl font-bold text-slate-900 truncate">{{ $formatMoney($stats['total_orders'] > 0 ? $stats['total_revenue'] / $stats['total_orders'] : 0) }}</p>
                </div>
                <div class="flex flex-col justify-center rounded-xl border border-slate-200 bg-slate-50 p-4 lg:p-5 min-w-0">
                    <p class="text-[10px] lg:text-xs uppercase tracking-[0.2em] font-medium text-slate-500 truncate">Durum Dağılımı</p>
                    <p class="mt-2 text-xs lg:text-sm font-semibold text-slate-900 line-clamp-2">
                        <span class="text-emerald-600">● {{ $formatCount($stats['total_orders'] - $stats['finance_waiting_orders']) }}</span>
                        <span class="text-amber-500 ml-1">● {{ $formatCount($stats['finance_waiting_orders']) }}</span>
                        <span class="text-rose-500 ml-1">● {{ $formatCount($stats['match_issue_orders']) }}</span>
                    </p>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════════ --}}
    {{-- OPERASYONEL SİPARİŞ İÇE AKTARIM --}}
    {{-- ═══════════════════════════════════════════════ --}}
    <div x-data="{ importOpen: false }" class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <button type="button"
                @click="importOpen = !importOpen"
                class="flex w-full items-center justify-between gap-3 px-4 py-4 text-left">
            <div>
                <p class="text-base font-semibold text-slate-900">Operasyonel Sipariş İçe Aktarım</p>
                <p class="mt-0.5 text-sm text-slate-500">Müşteri detayı, ürün ve varyant içeren geniş kapsamlı "Sipariş Kayıtları" Excelinizi buraya yükleyin.</p>
            </div>
            <svg class="h-5 w-5 shrink-0 text-slate-400 transition" :class="{ 'rotate-180': importOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 9l-7 7-7-7" />
            </svg>
        </button>
        <div x-cloak x-show="importOpen" x-transition class="border-t border-slate-200 px-4 py-4">
            <form wire:submit.prevent="importOrders" class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-slate-700">Projection mağazası</span>
                        <select wire:model.live="legacyProjectionStoreId"
                                class="w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-base text-slate-900 shadow-sm outline-none transition focus:border-slate-400 focus:ring-0 sm:py-2 sm:text-sm">
                            <option value="">Legacy-only bırak</option>
                            @foreach($legacyProjectionStoreOptions as $optionValue => $optionLabel)
                                <option value="{{ $optionValue }}">{{ $optionLabel }}</option>
                            @endforeach
                        </select>
                    </label>
                    <div class="flex items-end gap-3">
                        <label class="flex-1 block cursor-pointer rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-3 transition hover:border-slate-400 hover:bg-white">
                            <input type="file" wire:model="file" accept=".xlsx,.xls" class="hidden">
                            <div class="flex items-center gap-2">
                                <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                </svg>
                                <span class="text-sm text-slate-600">{{ $file ? $file->getClientOriginalName() : 'Dosya seçilmedi' }}</span>
                            </div>
                        </label>
                    </div>
                </div>

                @error('file') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
                @error('legacyProjectionStoreId') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror

                <div class="flex flex-wrap gap-2">
                    <button type="submit"
                            wire:loading.attr="disabled"
                            wire:target="importOrders,file"
                            class="inline-flex min-h-[44px] items-center justify-center rounded-lg bg-rose-600 px-4 py-3 sm:py-2 text-sm font-medium text-white transition hover:bg-rose-700 disabled:opacity-60">
                        <span wire:loading.remove wire:target="importOrders">Yükle ve İşle</span>
                        <span wire:loading wire:target="importOrders">İşleniyor...</span>
                    </button>
                    <button type="button"
                            wire:click="runSyncEngine"
                            wire:loading.attr="disabled"
                            class="inline-flex min-h-[44px] items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                        Legacy finans sync
                    </button>
                    <button type="button"
                            wire:click="previewLegacyFinancials"
                            wire:loading.attr="disabled"
                            class="inline-flex min-h-[44px] items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                        Önizleme
                    </button>
                    <button type="button"
                            wire:click="projectLegacyFinancials"
                            wire:loading.attr="disabled"
                            @disabled(($legacyFinancialProjectionPreview['projected_rows'] ?? 0) === 0)
                            class="inline-flex min-h-[44px] items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed">
                        Eski finansı taşı
                    </button>
                </div>

                @if($legacyProjectionResult)
                    <div class="rounded-lg border border-slate-200 bg-slate-50/70 p-3">
                        <div class="flex flex-wrap items-center gap-2 text-xs text-slate-600">
                            <span class="font-semibold text-slate-900">{{ !empty($legacyProjectionResult['executed']) ? 'Aktarım sonucu' : 'Önizleme' }}</span>
                            <span class="rounded-full border border-slate-200 bg-white px-2 py-0.5">Aday {{ $formatCount((int) data_get($legacyProjectionResult, 'projected_rows', 0)) }}</span>
                            <span class="rounded-full border border-slate-200 bg-white px-2 py-0.5">Yeni {{ $formatCount((int) data_get($legacyProjectionResult, 'created', 0)) }}</span>
                            <span class="rounded-full border border-slate-200 bg-white px-2 py-0.5">Güncelleme {{ $formatCount((int) data_get($legacyProjectionResult, 'updated', 0)) }}</span>
                            <span class="rounded-full border border-slate-200 bg-white px-2 py-0.5">Etkilenen {{ $formatCount((int) data_get($legacyProjectionResult, 'impacted_orders', 0)) }}</span>
                        </div>
                        <div class="mt-2 text-xs font-mono text-slate-400">
                            {{ $this->legacyProjectionRunCommand }}
                            <br>
                            {{ $this->legacyProjectionDryRunCommand }}
                        </div>
                    </div>
                @endif
            </form>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════ --}}
    {{-- BLOK 2: TABLO VE FİLTRELER --}}
    {{-- ═══════════════════════════════════════════════ --}}
    <section class="rounded-2xl border border-slate-200 bg-white p-4 lg:p-6 shadow-sm"
             x-data="{
                advancedFilters: @js(filled($searchProduct) || filled($profitStateFilter) || filled($financialStateFilter) || filled($legalEntityFilter) || filled($matchStateFilter)),
                bulkToolsOpen: @js(count($selectedOrderIds) > 0 || count($selectedPackageIds) > 0)
             }">

        {{-- Guidance banner --}}
        @php
            $primaryGuidance = $diagnosticsGuidance['items'][0] ?? null;
            $secondaryGuidance = collect($diagnosticsGuidance['items'] ?? [])->skip(1)->take(4)->values();
        @endphp
        @include('livewire.partials.mp-guidance-banner', [
            'diagnosticsGuidance' => $diagnosticsGuidance,
            'guidanceItems' => $diagnosticsGuidance['items'] ?? [],
            'primaryGuidance' => $primaryGuidance,
            'secondaryGuidance' => $secondaryGuidance,
        ])

        @php $legacyGuidance = $this->getLegacyProjectionGuidanceCard(); @endphp
        @if($legacyGuidance)
            <div class="mb-4 rounded-xl border {{ $legacyGuidance['state'] === 'warning' ? 'border-amber-200 bg-amber-50/60' : 'border-emerald-200 bg-emerald-50/60' }} px-4 py-3">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="rounded-full border {{ $legacyGuidance['state'] === 'warning' ? 'border-amber-200 bg-amber-100 text-amber-700' : 'border-emerald-200 bg-emerald-100 text-emerald-700' }} px-2.5 py-0.5 text-xs font-medium">Legacy Finans</span>
                            <p class="text-sm font-semibold text-slate-900">{{ $legacyGuidance['store_name'] }}</p>
                            <span class="text-xs text-slate-400">·</span>
                            <p class="text-xs text-slate-500">{{ $this->humanMarketplace($legacyGuidance['marketplace']) }}</p>
                        </div>
                        <p class="mt-1.5 text-sm font-medium text-slate-800">{{ $legacyGuidance['title'] }}</p>
                        <p class="mt-0.5 text-xs text-slate-500">{{ $legacyGuidance['description'] }}</p>
                        <div class="mt-2 flex items-center gap-3">
                            <span class="text-xs font-medium text-amber-700">Bekleyen {{ $legacyGuidance['pending_rows'] }}</span>
                            <span class="text-xs font-medium text-emerald-700">Kesine dönen {{ $legacyGuidance['confirmed_orders'] }}</span>
                        </div>
                    </div>
                    <div class="flex shrink-0 gap-2">
                        <button type="button" wire:click="focusLegacyProjectionCard" class="inline-flex min-h-[36px] items-center justify-center rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-slate-800">
                            Filtrele ve İncele
                        </button>
                    </div>
                </div>
            </div>
        @endif

        {{-- Filtreler --}}
        <div class="space-y-4 mb-6">
            <div class="flex flex-col lg:flex-row gap-3">
                <div class="relative flex-1">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input type="text"
                           wire:model.live.debounce.400ms="search"
                           placeholder="Sipariş / Paket No arayın..."
                           class="w-full rounded-2xl border border-slate-200 bg-slate-50 py-3 pl-10 pr-4 text-sm font-medium text-slate-900 shadow-sm transition focus:border-slate-900 focus:bg-white focus:outline-none">
                </div>
                <input type="text"
                       wire:model.live.debounce.400ms="searchProduct"
                       placeholder="Barkod / Ürün / Stok Kodu..."
                       class="w-full lg:w-56 rounded-lg border border-slate-200 bg-white px-4 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                <input type="text"
                       wire:model.live.debounce.400ms="searchCustomer"
                       placeholder="Müşteri / Telefon / E-Posta..."
                       class="w-full lg:w-56 rounded-lg border border-slate-200 bg-white px-4 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                <select wire:model.live="statusFilter"
                        class="w-full lg:w-48 rounded-lg border border-slate-200 bg-white px-4 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                    <option value="">Tüm Durumlar</option>
                    @foreach($statusOptions as $status)
                        <option value="{{ $status }}">{{ $this->humanStatus($status) }}</option>
                    @endforeach
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
                        <button type="button"
                                wire:click="resetFilters"
                                class="inline-flex min-h-[44px] items-center justify-center rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-medium text-rose-600 transition hover:bg-rose-100">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    @endif
                </div>
            </div>

            {{-- Gelişmiş filtreler --}}
            <div x-cloak x-show="advancedFilters" x-transition class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-6 gap-3">
                <select wire:model.live="marketplaceFilter"
                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                    <option value="">Tüm Pazaryerleri</option>
                    @foreach($marketplaceOptions as $marketplace)
                        <option value="{{ $marketplace }}">{{ $this->humanMarketplace($marketplace) }}</option>
                    @endforeach
                </select>
                <select wire:model.live="storeFilter"
                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                    <option value="">Tüm Mağazalar</option>
                    @foreach($storeOptions as $store)
                        <option value="{{ $store->id }}">{{ $store->store_name }}</option>
                    @endforeach
                </select>
                <input type="date" wire:model.live="dateFrom"
                       class="w-full rounded-lg border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                <input type="date" wire:model.live="dateTo"
                       class="w-full rounded-lg border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                <select wire:model.live="profitStateFilter"
                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                    <option value="">Kâr Durumu</option>
                    <option value="confirmed">Kesin</option>
                    <option value="estimated">Tahmini</option>
                    <option value="missing">Hesaplanmadı</option>
                </select>
                <select wire:model.live="financialStateFilter"
                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                    <option value="">Finans Akışı</option>
                    <option value="ready">Finans geldi</option>
                    <option value="waiting">Finans bekliyor</option>
                </select>
            </div>

            {{-- Gelişmiş filtreler: firma + eşleşme --}}
            <div x-cloak x-show="advancedFilters" x-transition class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-6 gap-3">
                <select wire:model.live="legalEntityFilter"
                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                    <option value="">Tüm Firmalar</option>
                    @foreach($legalEntityOptions as $entity)
                        <option value="{{ $entity->id }}">{{ $entity->name }}</option>
                    @endforeach
                </select>
                <select wire:model.live="matchStateFilter"
                        class="w-full rounded-lg border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                    <option value="">Eşleşme</option>
                    <option value="full_match">Tam eşleşti</option>
                    <option value="needs_match">Kontrol gerekiyor</option>
                </select>
            </div>
        </div>

        {{-- Tablo araç çubuğu --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-3">
            <div class="flex items-center gap-3 text-sm text-slate-500">
                <span>{{ $formatCount($orders->total()) }} sipariş</span>
                @if(count($activeFilters) > 0)
                    <span class="text-xs">· {{ implode(' · ', $activeFilters) }}</span>
                @endif
            </div>
            <div class="flex items-center gap-2">
                @include('livewire.partials.mp-column-toggle', ['columnDefs' => $columnDefs, 'visibleColumns' => $visibleColumns])

                {{-- Toplu işlemler --}}
                @if(config('marketplace.features.bulk_order_actions_enabled', true) || config('marketplace.features.bulk_package_actions_enabled', true))
                    <div x-data="{ bulkOpen: false }" class="relative">
                        <button @click="bulkOpen = !bulkOpen"
                                type="button"
                                class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 {{ count($selectedOrderIds) > 0 ? 'border-indigo-300 bg-indigo-50 text-indigo-700' : '' }}">
                            Toplu İşlem
                            @if(count($selectedOrderIds) > 0 || count($selectedPackageIds) > 0)
                                <span class="rounded-full bg-indigo-100 px-1.5 py-0.5 text-[10px] font-medium text-indigo-700">
                                    {{ count($selectedOrderIds) + count($selectedPackageIds) }}
                                </span>
                            @endif
                        </button>

                        <div x-show="bulkOpen"
                             @click.outside="bulkOpen = false"
                             x-transition
                             class="absolute right-0 top-full z-30 mt-2 w-80 rounded-2xl border border-slate-200 bg-white p-4 shadow-xl">
                            <p class="text-xs font-medium uppercase tracking-[0.16em] text-slate-500">Toplu İşlemler</p>

                            @if(config('marketplace.features.bulk_order_actions_enabled', true))
                                <div class="mt-3 space-y-2">
                                    <select wire:model.live="bulkActionType"
                                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm">
                                        <option value="">Sipariş aksiyonu seçin</option>
                                        @foreach($this->bulkActionOptions() as $actionValue => $actionLabel)
                                            <option value="{{ $actionValue }}">{{ $actionLabel }}</option>
                                        @endforeach
                                    </select>
                                    <button type="button"
                                            wire:click="runBulkOrderAction"
                                            wire:loading.attr="disabled"
                                            class="w-full rounded-lg bg-slate-900 px-3 py-2 text-sm font-medium text-white transition hover:bg-slate-800 disabled:opacity-60">
                                        Kuyruğa al
                                    </button>
                                </div>
                            @endif

                            @if(config('marketplace.features.bulk_package_actions_enabled', true))
                                <div class="mt-3 space-y-2 border-t border-slate-200 pt-3">
                                    <select wire:model.live="bulkPackageActionType"
                                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm">
                                        <option value="">Paket aksiyonu seçin</option>
                                        @foreach($this->bulkPackageActionOptions() as $actionValue => $actionLabel)
                                            <option value="{{ $actionValue }}">{{ $actionLabel }}</option>
                                        @endforeach
                                    </select>
                                    <button type="button"
                                            wire:click="runBulkPackageAction"
                                            wire:loading.attr="disabled"
                                            class="w-full rounded-lg bg-slate-900 px-3 py-2 text-sm font-medium text-white transition hover:bg-slate-800 disabled:opacity-60">
                                        Kuyruğa al
                                    </button>
                                </div>
                            @endif

                            <button type="button"
                                    wire:click="clearSelection"
                                    class="mt-3 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                Seçimleri temizle
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════ --}}
        {{-- TABLO --}}
        {{-- ═══════════════════════════════════════════════ --}}
        <div x-data="{ expanded: [] }">
            @if(!$hasConfiguredStores)
                <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-xl bg-slate-900 text-white shadow-lg shadow-slate-900/10">
                        <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 7h18M6 10h12M5 13h14M8 17h8" />
                        </svg>
                    </div>
                    <h3 class="mt-4 text-lg font-semibold text-slate-900">Henüz mağaza bağlantısı yok</h3>
                    <p class="mt-2 text-sm text-slate-500">İlk Trendyol mağazanızı eklediğinizde siparişler burada görünecek.</p>
                    <div class="mt-4">
                        <a href="{{ route('mp.integrations') }}" class="inline-flex min-h-[44px] items-center justify-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800">
                            Entegrasyon kurulumuna git
                        </a>
                    </div>
                </div>
            @elseif(!$hasChannelData)
                <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center">
                    <h3 class="mt-4 text-lg font-semibold text-slate-900">Mağazalar hazır, veri bekleniyor</h3>
                    <p class="mt-2 text-sm text-slate-500">İlk senkron tamamlandığında siparişler burada listelenecek.</p>
                </div>
            @else
                {{-- Mobil kart görünümü --}}
                <div class="space-y-3 md:hidden">
                    @foreach($orders as $order)
                        @php
                            $snapshot = data_get($order, 'order_snapshot');
                            $package = data_get($order, 'package_summary');
                            $profitState = $order->profit_state_metric ?? ($snapshot?->profit_state ?: 'estimated');
                            $profitValue = (float) ($order->profit_value_metric ?? ($profitState === 'confirmed' ? $snapshot?->confirmed_profit : $snapshot?->estimated_profit));
                            $grossRevenue = (float) ($order->gross_revenue_metric ?? $snapshot?->gross_revenue);
                            $matchRatio = (int) ($order->matched_lines_count ?? 0) . '/' . (int) ($order->item_lines_count ?? 0);
                        @endphp

                        <article class="rounded-xl border border-slate-200 bg-white shadow-sm">
                            <div class="flex items-center justify-between border-b border-slate-200 px-4 pt-4 pb-3">
                                <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                                    <input type="checkbox"
                                           wire:model.live="selectedOrderIds"
                                           value="{{ $order->id }}"
                                           class="rounded border-slate-300 text-slate-900 shadow-sm focus:ring-indigo-200">
                                    <span>Seç</span>
                                </label>
                                <span class="text-xs text-slate-400">#{{ $order->id }}</span>
                            </div>
                            <button type="button"
                                    class="w-full px-4 pb-4 text-left"
                                    @click="expanded.includes({{ $order->id }}) ? expanded = expanded.filter(i => i !== {{ $order->id }}) : expanded.push({{ $order->id }})">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2">
                                            <svg class="h-4 w-4 shrink-0 text-slate-400 transition"
                                                 :class="{ 'rotate-90 text-slate-900': expanded.includes({{ $order->id }}) }"
                                                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                            </svg>
                                            <p class="truncate text-sm font-semibold text-slate-900">{{ $order->order_number }}</p>
                                        </div>
                                        <p class="mt-1 truncate text-xs text-slate-500">{{ $order->store_name_alias }} · {{ $this->humanMarketplace($order->marketplace_alias) }}</p>
                                        <p class="mt-2 text-sm font-medium text-slate-900">{{ $order->customer_name ?: 'Müşteri bilgisi yok' }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ $order->ordered_at?->format('d.m.Y H:i') ?: '-' }}</p>
                                    </div>

                                    <div class="text-right">
                                        <x-zolm.status-badge :tone="$this->statusTone($order->order_status)">
                                            {{ $this->humanStatus($order->order_status) }}
                                        </x-zolm.status-badge>
                                        <p class="mt-3 text-base font-semibold text-slate-900">{{ $formatMoney($grossRevenue) }}</p>
                                        <p class="mt-1 text-sm font-semibold {{ $profitValue >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ $formatMoney($profitValue) }}</p>
                                    </div>
                                </div>

                                <div class="mt-4 grid grid-cols-2 gap-2">
                                    <div class="rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2">
                                        <p class="text-[10px] uppercase tracking-[0.16em] text-slate-500">Kâr durumu</p>
                                        <p class="mt-1 text-sm font-medium text-slate-900">{{ $this->profitStateLabel($profitState) }}</p>
                                    </div>
                                    <div class="rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2">
                                        <p class="text-[10px] uppercase tracking-[0.16em] text-slate-500">Eşleşme</p>
                                        <p class="mt-1 text-sm font-medium text-slate-900">{{ $matchRatio }}</p>
                                    </div>
                                </div>

                                @if($package)
                                    <p class="mt-3 text-xs text-slate-500">
                                        {{ $package->cargo_company ?: 'Kargo yok' }}
                                        @if($package->cargo_tracking_number)
                                            · {{ $package->cargo_tracking_number }}
                                        @endif
                                    </p>
                                @endif
                            </button>

                            <div x-show="expanded.includes({{ $order->id }})"
                                 x-transition
                                 x-cloak
                                 class="border-t border-slate-200 bg-slate-50/60 p-4">
                                @include('livewire.partials.marketplace-order-v2-detail', ['order' => $order])
                            </div>
                        </article>
                    @endforeach
                </div>

                {{-- Desktop tablo --}}
                <div class="hidden md:block overflow-hidden rounded-lg border border-slate-200">
                    @php
                        $columnMeta = [
                            'siparis' => ['label' => 'Sipariş', 'width' => '190px'],
                            'magaza' => ['label' => 'Mağaza', 'width' => '180px'],
                            'musteri' => ['label' => 'Müşteri', 'width' => '220px'],
                            'lojistik' => ['label' => 'Lojistik', 'width' => '170px'],
                            'ciro' => ['label' => 'Ciro', 'width' => '140px'],
                            'kar' => ['label' => 'Kârlılık', 'width' => '150px'],
                            'durum' => ['label' => 'Durum', 'width' => '130px'],
                        ];
                    @endphp

                    <div class="overflow-x-auto -mx-4 lg:-mx-6 px-4 lg:px-6" x-data="columnResize()">
                        <table class="orders-v2-table min-w-[1000px] w-full divide-y divide-slate-200 table-fixed">
                            <thead class="bg-slate-50 text-slate-500">
                                <tr>
                                    <th class="w-16 px-3 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em]">
                                        <label class="inline-flex items-center gap-2 text-[11px] text-slate-500">
                                            <input type="checkbox"
                                                   wire:model.live="selectPage"
                                                   class="rounded border-slate-300 text-slate-900 shadow-sm focus:ring-indigo-200">
                                            <span>Seç</span>
                                        </label>
                                    </th>
                                    @foreach($columnMeta as $columnKey => $meta)
                                        @if(in_array($columnKey, $visibleColumns, true))
                                            <th class="sortable-th relative px-3 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em]"
                                                style="width: {{ $meta['width'] }}; min-width: {{ $meta['width'] }};"
                                                wire:click="sortTable('{{ $columnKey }}')">
                                                <div class="flex items-center justify-between gap-2">
                                                    <span>{{ $meta['label'] }}</span>
                                                    <span class="text-[10px] {{ ($sortableColumns[$columnKey] ?? null) === $sortField ? 'text-slate-700' : 'text-slate-300' }}">{{ $sortIcon($columnKey) }}</span>
                                                </div>
                                                <span class="col-resize-handle" @mousedown.prevent="startResize($event, $el.parentElement)"></span>
                                            </th>
                                        @endif
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white text-sm text-slate-700">
                                @foreach($orders as $order)
                                    @php
                                        $snapshot = data_get($order, 'order_snapshot');
                                        $package = data_get($order, 'package_summary');
                                        $profitState = $order->profit_state_metric ?? ($snapshot?->profit_state ?: 'estimated');
                                        $profitValue = (float) ($order->profit_value_metric ?? ($profitState === 'confirmed' ? $snapshot?->confirmed_profit : $snapshot?->estimated_profit));
                                        $grossRevenue = (float) ($order->gross_revenue_metric ?? $snapshot?->gross_revenue);
                                        $matchedLines = (int) ($order->matched_lines_count ?? 0);
                                        $itemLines = (int) ($order->item_lines_count ?? 0);
                                    @endphp

                                    <tr class="transition hover:bg-slate-50/80">
                                        <td class="px-3 py-4 align-top">
                                            <div class="flex items-center gap-2">
                                                <input type="checkbox"
                                                       wire:model.live="selectedOrderIds"
                                                       value="{{ $order->id }}"
                                                       class="rounded border-slate-300 text-slate-900 shadow-sm focus:ring-indigo-200">
                                                <button type="button"
                                                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50 hover:text-slate-900"
                                                        @click="expanded.includes({{ $order->id }}) ? expanded = expanded.filter(i => i !== {{ $order->id }}) : expanded.push({{ $order->id }})">
                                                    <svg class="h-4 w-4 transition"
                                                         :class="{ 'rotate-90': expanded.includes({{ $order->id }}) }"
                                                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </td>

                                        @if(in_array('siparis', $visibleColumns, true))
                                            <td class="px-3 py-4 align-top">
                                                <div class="font-semibold text-slate-900">{{ $order->order_number }}</div>
                                                <div class="mt-1 text-xs text-slate-500">{{ $order->ordered_at?->format('d.m.Y H:i') ?: '-' }}</div>
                                                @if($package?->package_number)
                                                    <div class="mt-1 text-[11px] font-mono text-slate-400">PKT {{ $package->package_number }}</div>
                                                @endif
                                            </td>
                                        @endif

                                        @if(in_array('magaza', $visibleColumns, true))
                                            <td class="px-3 py-4 align-top">
                                                <div class="font-medium text-slate-900">{{ $order->store_name_alias }}</div>
                                                <div class="mt-1 text-xs text-slate-500">{{ $this->humanMarketplace($order->marketplace_alias) }}</div>
                                                <div class="mt-2 text-[11px] text-slate-400">{{ $order->legal_entity_name_alias }}</div>
                                            </td>
                                        @endif

                                        @if(in_array('musteri', $visibleColumns, true))
                                            <td class="px-3 py-4 align-top">
                                                <div class="font-medium text-slate-900">{{ $order->customer_name ?: 'Müşteri bilgisi yok' }}</div>
                                                <div class="mt-1 text-xs text-slate-500 truncate">{{ $order->shipment_city ?: '-' }}{{ $order->shipment_district ? ', ' . $order->shipment_district : '' }}</div>
                                                @if($order->customer_phone)
                                                    <div class="mt-1 text-[11px] text-slate-400">{{ $order->customer_phone }}</div>
                                                @endif
                                            </td>
                                        @endif

                                        @if(in_array('lojistik', $visibleColumns, true))
                                            <td class="px-3 py-4 align-top">
                                                <div class="font-medium text-slate-900">{{ $package?->cargo_company ?: 'Kargo bilgisi bekleniyor' }}</div>
                                                <div class="mt-1 text-xs text-slate-500 truncate">{{ $package?->cargo_tracking_number ?: 'Takip no yok' }}</div>
                                                <div class="mt-2 flex items-center gap-2 text-[11px] text-slate-400">
                                                    <span>{{ (int) ($order->financial_event_count ?? 0) }} finans</span>
                                                    <span>·</span>
                                                    <span>{{ $matchedLines }}/{{ $itemLines }} eşleşti</span>
                                                </div>
                                            </td>
                                        @endif

                                        @if(in_array('ciro', $visibleColumns, true))
                                            <td class="px-3 py-4 align-top text-right">
                                                <div class="font-semibold text-slate-900">{{ $formatMoney($grossRevenue) }}</div>
                                                <div class="mt-1 text-xs text-slate-500">{{ $formatCount($order->total_quantity ?? 0) }} adet</div>
                                                @if((float) ($order->total_discount_amount ?? 0) > 0)
                                                    <div class="mt-1 text-[11px] text-rose-500">İnd. {{ $formatMoney($order->total_discount_amount) }}</div>
                                                @endif
                                            </td>
                                        @endif

                                        @if(in_array('kar', $visibleColumns, true))
                                            <td class="px-3 py-4 align-top text-right">
                                                <div class="font-semibold {{ $profitValue >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ $formatMoney($profitValue) }}</div>
                                                <div class="mt-1 text-xs text-slate-500">%{{ number_format((float) ($order->margin_percent_metric ?? 0), 1, ',', '.') }}</div>
                                                <div class="mt-2">
                                                    <x-zolm.status-badge :tone="$this->profitStateTone($profitState)">
                                                        {{ $this->profitStateLabel($profitState) }}
                                                    </x-zolm.status-badge>
                                                </div>
                                            </td>
                                        @endif

                                        @if(in_array('durum', $visibleColumns, true))
                                            <td class="px-3 py-4 align-top">
                                                <x-zolm.status-badge :tone="$this->statusTone($order->order_status)">
                                                    {{ $this->humanStatus($order->order_status) }}
                                                </x-zolm.status-badge>
                                                <div class="mt-2 text-xs text-slate-500">
                                                    {{ (int) ($order->financial_event_count ?? 0) > 0 ? 'Finans geldi' : 'Finans bekleniyor' }}
                                                </div>
                                            </td>
                                        @endif
                                    </tr>

                                    <tr x-show="expanded.includes({{ $order->id }})" x-cloak x-transition class="expanded-row bg-slate-50/60">
                                        <td colspan="{{ count($visibleColumns) + 1 }}" class="p-0">
                                            <div class="px-4 py-4 lg:px-6 w-full max-w-full overflow-hidden">
                                                @include('livewire.partials.marketplace-order-v2-detail', ['order' => $order])
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Pagination --}}
                @include('livewire.partials.mp-pagination-bar', ['paginator' => $orders])
            @endif
        </div>
    </section>

    {{-- TABLO STİLLERİ --}}
    <style>
        .orders-v2-table {
            table-layout: fixed;
            width: 100%;
        }
        .orders-v2-table th, .orders-v2-table tbody > tr:not(.expanded-row) > td {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .orders-v2-table tbody > tr.expanded-row > td {
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
