@php
    $reportSummary = data_get($bestsellerReportDashboard, 'analysis.summary', []);
    $selectedReport = data_get($bestsellerReportDashboard, 'selected');
    $reportAnalysis = data_get($bestsellerReportDashboard, 'analysis', []);
    $sortIcon = function (string $column) use ($bestsellerSortableColumns, $bestsellerSortField, $bestsellerSortDirection): string {
        $field = $bestsellerSortableColumns[$column] ?? null;
        if ($field !== $bestsellerSortField) {
            return '↕';
        }

        return $bestsellerSortDirection === 'asc' ? '↑' : '↓';
    };
    $columnWidths = [
        'rank' => '64px',
        'product' => '270px',
        'seller' => '170px',
        'price' => '110px',
        'stock' => '110px',
        'campaign' => '180px',
        'sales' => '140px',
        'rating' => '110px',
        'actions' => '110px',
    ];
@endphp

<div
    x-data="{
        extensionReady: false,
        extensionVersion: '',
        extensionMessage: '',
        minimumExtensionVersion: '0.9.10',
        bridgeBusy: false,
        bridgeRequestId: '',
        bridgeTimer: null,
        trackingRequestId: '',
        trackingProductId: '',
        trackingTimer: null,
        init() {
            window.postMessage({ source: 'zolm-booster-page', type: 'PING' }, window.location.origin);
        },
        versionAtLeast(version, minimum) {
            const normalize = (value) => String(value || '0').split('.').map((part) => Number.parseInt(part, 10) || 0);
            const current = normalize(version);
            const required = normalize(minimum);
            const length = Math.max(current.length, required.length);

            for (let index = 0; index < length; index += 1) {
                const left = current[index] || 0;
                const right = required[index] || 0;
                if (left > right) return true;
                if (left < right) return false;
            }

            return true;
        },
        extensionOutdated() {
            return this.extensionReady && !this.versionAtLeast(this.extensionVersion, this.minimumExtensionVersion);
        },
        searchBestsellers(wire) {
            if (!this.extensionReady) {
                wire.searchBestsellers();
                return;
            }

            const search = (this.$refs.bestsellerSearchInput?.value || wire.bestsellerSearch || '').trim();
            if (search.length < 2) {
                wire.searchBestsellers();
                return;
            }

            if (this.extensionOutdated()) {
                wire.searchBestsellers();
                return;
            }

            this.bridgeBusy = true;
            this.extensionMessage = '';
            this.bridgeRequestId = `${Date.now()}-${Math.random().toString(16).slice(2)}`;
            wire.set('isBestsellerLoading', true);
            wire.set('bestsellerSearch', search);
            wire.set('bestsellerResults', []);

            window.postMessage({
                source: 'zolm-booster-page',
                type: 'BESTSELLER_QUERY',
                request_id: this.bridgeRequestId,
                source_url: 'https://www.trendyol.com/cok-satanlar?type=bestSeller',
                keyword: search,
                min_price: this.$refs.bestsellerMinPriceInput?.value || @js($bestsellerMinPrice),
                max_price: this.$refs.bestsellerMaxPriceInput?.value || @js($bestsellerMaxPrice)
            }, window.location.origin);

            this.bridgeTimer = window.setTimeout(() => {
                if (!this.bridgeBusy) return;
                this.bridgeBusy = false;
                this.extensionMessage = 'Chrome eklentisi zaman aşımına uğradı; sunucu okuyucusu kullanılıyor.';
                wire.searchBestsellers();
            }, 150000);
        },
        trackBestseller(wire, index, productId, sourceUrl) {
            if (this.trackingProductId) return;
            if (!this.extensionReady || this.extensionOutdated()) {
                wire.trackBestseller(index);
                return;
            }

            this.trackingProductId = String(productId || '');
            this.trackingRequestId = `${Date.now()}-${Math.random().toString(16).slice(2)}`;
            window.postMessage({
                source: 'zolm-booster-page',
                type: 'BESTSELLER_TRACK',
                request_id: this.trackingRequestId,
                source_url: sourceUrl,
            }, window.location.origin);

            this.trackingTimer = window.setTimeout(() => {
                if (!this.trackingProductId) return;
                const failedProductId = this.trackingProductId;
                this.trackingProductId = '';
                wire.bestsellerTrackingBridgeCompleted(null, failedProductId, 'Takip isteği zaman aşımına uğradı.', false);
            }, 60000);
        },
        handleBridge(event, wire) {
            const data = event.data || {};
            if (event.origin !== window.location.origin || data.source !== 'zolm-booster-extension') return;

            if (data.type === 'READY') {
                this.extensionReady = true;
                this.extensionVersion = data.version || '';
                this.extensionMessage = '';
                return;
            }

            if (data.type === 'BRIDGE_ERROR') {
                this.extensionReady = false;
                this.extensionMessage = data.message || 'Chrome Companion köprüsü yanıt vermedi.';
                return;
            }

            if (data.type === 'BESTSELLER_QUERY_RESULT' && data.request_id === this.bridgeRequestId) {
                this.bridgeBusy = false;
                window.clearTimeout(this.bridgeTimer);
                const response = data.response || {};
                const search = (this.$refs.bestsellerSearchInput?.value || wire.bestsellerSearch || '').trim();
                this.extensionReady = true;
                this.extensionMessage = '';
                if (response.ok !== true) {
                    this.extensionMessage = response.message || 'Chrome Companion yanıtı başarısız oldu; sunucu okuyucusu kullanılıyor.';
                    wire.searchBestsellers();
                    return;
                }
                const matchedLabel = response.matched_sub_category || response.matched_category || search;
                wire.bestsellerBridgeCompleted(
                    response.products || [],
                    search,
                    response.source_url || '',
                    response.message || '',
                    response.ok === true,
                    matchedLabel,
                );
                return;
            }

            if (data.type === 'BESTSELLER_TRACK_RESULT' && data.request_id === this.trackingRequestId) {
                window.clearTimeout(this.trackingTimer);
                const productId = this.trackingProductId;
                const response = data.response || {};
                this.trackingProductId = '';
                wire.bestsellerTrackingBridgeCompleted(
                    response.tracked_product_id || null,
                    productId,
                    response.message || '',
                    response.ok === true,
                );
            }
        }
    }"
    @message.window="handleBridge($event, $wire)"
    class="flex w-full flex-col gap-4 sm:flex-row sm:flex-wrap lg:gap-6"
    data-testid="bestseller-workspace"
>
    <section class="w-full rounded-[10px] border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 p-4 lg:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Pazar sinyal defteri</p>
                        <span x-show="extensionReady && !extensionOutdated()" x-cloak class="rounded-[6px] border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700">
                            Eklenti bağlı <span x-text="extensionVersion"></span>
                        </span>
                        <span x-show="extensionMessage" x-cloak x-text="extensionMessage" class="rounded-[6px] border border-rose-200 bg-rose-50 px-2 py-0.5 text-[11px] text-rose-700"></span>
                        <span x-show="extensionOutdated()" x-cloak class="rounded-[6px] border border-amber-200 bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-700">
                            Eklenti eski <span x-text="extensionVersion"></span> · 0.9.10 gerekli
                        </span>
                    </div>
                    <h2 class="mt-1 text-lg font-semibold text-slate-900">Trendyol Çok Satanlar</h2>
                    <p class="mt-1 text-sm text-slate-500">Canlı listeyi inceleyin; aynı sorguyu günlük kaydederek gerçek sıralama eğilimini görün.</p>
                </div>

                <div class="grid w-full grid-cols-2 gap-2 rounded-[8px] border border-slate-200 bg-slate-50/60 p-1 sm:w-auto">
                    <button
                        type="button"
                        wire:click="setBestsellerMode('live')"
                        class="min-h-[44px] rounded-[6px] px-3 py-2 text-sm font-medium transition {{ $bestsellerMode === 'live' ? 'bg-slate-900 text-white shadow-sm' : 'bg-transparent text-slate-600 hover:bg-white' }}"
                    >
                        Canlı sonuçlar
                    </button>
                    <button
                        type="button"
                        wire:click="setBestsellerMode('reports')"
                        class="min-h-[44px] rounded-[6px] px-3 py-2 text-sm font-medium transition {{ $bestsellerMode === 'reports' ? 'bg-slate-900 text-white shadow-sm' : 'bg-transparent text-slate-600 hover:bg-white' }}"
                    >
                        Kayıtlı raporlar · {{ count(data_get($bestsellerReportDashboard, 'reports', [])) }}
                    </button>
                </div>
            </div>
        </div>

        @if($bestsellerMode === 'live')
            <div class="space-y-4 p-4 lg:p-6">
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3 lg:p-4">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
                        <label class="block min-w-0 flex-1">
                            <span class="text-xs font-medium text-slate-600">Kategori veya anahtar kelime</span>
                            <input
                                x-ref="bestsellerSearchInput"
                                type="text"
                                wire:model.defer="bestsellerSearch"
                                @keydown.enter="searchBestsellers($wire)"
                                placeholder="Örn: Puf, akıllı saat, kadın giyim"
                                class="mt-1.5 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none transition focus:border-slate-900 sm:py-2 sm:text-sm"
                            >
                        </label>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:w-[330px]">
                            <label class="block min-w-0">
                                <span class="text-xs font-medium text-slate-600">En düşük fiyat (₺)</span>
                                <input x-ref="bestsellerMinPriceInput" type="number" wire:model.defer="bestsellerMinPrice" @keydown.enter="searchBestsellers($wire)" placeholder="Min ₺" class="mt-1.5 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none focus:border-slate-900 sm:py-2 sm:text-sm">
                            </label>
                            <label class="block min-w-0">
                                <span class="text-xs font-medium text-slate-600">En yüksek fiyat (₺)</span>
                                <input x-ref="bestsellerMaxPriceInput" type="number" wire:model.defer="bestsellerMaxPrice" @keydown.enter="searchBestsellers($wire)" placeholder="Maks ₺" class="mt-1.5 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none focus:border-slate-900 sm:py-2 sm:text-sm">
                            </label>
                        </div>
                        <button
                            type="button"
                            @click="searchBestsellers($wire)"
                            wire:loading.attr="disabled"
                            wire:target="searchBestsellers"
                            x-bind:disabled="bridgeBusy"
                            class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] bg-slate-900 px-4 py-3 text-base font-medium text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto sm:py-2 sm:text-sm"
                        >
                            <x-lucide.icon name="search" class="h-4 w-4" />
                            <span x-show="!bridgeBusy" wire:loading.remove wire:target="searchBestsellers">Ara</span>
                            <span x-show="!bridgeBusy" wire:loading wire:target="searchBestsellers">Aranıyor...</span>
                            <span x-show="bridgeBusy" x-cloak>Detaylar alınıyor...</span>
                        </button>
                    </div>

                    <div class="mt-3 flex flex-col gap-2 border-t border-slate-200 pt-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0 text-xs text-slate-500">
                            @if($bestsellerSearch !== '')
                                Aktif filtre: <span class="font-semibold text-slate-700">{{ $bestsellerSearch }}</span>
                                @if($bestsellerMinPrice || $bestsellerMaxPrice)
                                    · {{ $bestsellerMinPrice ? number_format($bestsellerMinPrice, 0, ',', '.') . ' ₺' : '0 ₺' }}–{{ $bestsellerMaxPrice ? number_format($bestsellerMaxPrice, 0, ',', '.') . ' ₺' : 'sınırsız' }}
                                @endif
                            @else
                                Arama filtresi bekleniyor.
                            @endif
                        </div>
                        <p class="text-xs text-slate-500">Stok, satıcı ve kampanya ürün detayından ölçülür; zaman damgasıyla kaydedilir.</p>
                    </div>
                    <div x-show="extensionOutdated()" x-cloak class="mt-3 rounded-[6px] border border-amber-200 bg-amber-50 px-3 py-2 text-xs leading-5 text-amber-800">
                        Chrome eklentisi eski olduğu için arama server okuyucuyla denenir; satıcı, anlık stok ve kupon/kampanya için chrome://extensions ekranından eklentiyi 0.9.10 paketine güncelleyin.
                    </div>
                </div>

                @if(count($bestsellerResults) > 0)
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <h3 class="text-sm font-semibold text-slate-900">Canlı sıralama defteri</h3>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ count($bestsellerResults) }} ürün · {{ collect($bestsellerResults)->whereNotNull('price')->count() }} fiyat · {{ collect($bestsellerResults)->where('enrichment_status', 'enriched')->count() }} zenginleştirilmiş kayıt
                            </p>
                        </div>
                        <div class="flex flex-col gap-2 sm:flex-row">
                            <div x-data="{ open: false }" class="relative">
                                <button type="button" @click="open = !open" class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 sm:w-auto sm:py-2">
                                    <x-lucide.icon name="columns-3" class="h-4 w-4" /> Kolonlar · {{ count($bestsellerVisibleColumns) }}
                                </button>
                                <div x-show="open" x-cloak @click.outside="open = false" class="absolute right-0 top-full z-30 mt-2 w-64 rounded-[8px] border border-slate-200 bg-white p-2 shadow-xl">
                                    @foreach($bestsellerColumnDefinitions as $key => $label)
                                        <label class="flex cursor-pointer items-center gap-3 rounded-[6px] px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">
                                            <input type="checkbox" wire:click="toggleBestsellerColumn('{{ $key }}')" @checked(in_array($key, $bestsellerVisibleColumns, true)) class="h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-900">
                                            <span>{{ $label }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                            <button
                                type="button"
                                wire:click="saveBestsellerReport"
                                wire:loading.attr="disabled"
                                wire:target="saveBestsellerReport"
                                @disabled(!$bestsellerReportsReady)
                                class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto sm:py-2"
                            >
                                <x-lucide.icon name="save" class="h-4 w-4" />
                                <span wire:loading.remove wire:target="saveBestsellerReport">{{ $bestsellerCurrentReport ? 'Yeni ölçümü kaydet' : 'Raporu kaydet' }}</span>
                                <span wire:loading wire:target="saveBestsellerReport">Kaydediliyor...</span>
                            </button>
                        </div>
                    </div>

                    <div class="space-y-3 md:hidden">
                        @foreach($bestsellerTableRows as $item)
                            @php
                                $productId = (string) ($item['trendyol_product_id'] ?? '');
                                $tracked = in_array($productId, $bestsellerTrackedProductIds, true);
                                $campaigns = array_values((array) ($item['campaigns'] ?? []));
                                $salesSignal = trim((string) ($item['sold_text'] ?? ''));
                                $stockStatus = (string) ($item['stock_status'] ?? 'unknown');
                                $stockLabel = ($item['stock_quantity'] ?? null) !== null
                                    ? number_format($item['stock_quantity'], 0, '', '.') . ' adet'
                                    : ($stockStatus === 'in_stock' ? 'Stokta' : ($stockStatus === 'out_of_stock' ? 'Tükendi' : 'Stok bilinmiyor'));
                                $stockClass = $stockStatus === 'out_of_stock' || ($item['stock_quantity'] ?? null) === 0 ? 'text-rose-700' : 'text-slate-500';
                            @endphp
                            <article class="rounded-[8px] border border-slate-200 bg-white p-4 shadow-sm" wire:key="bestseller-mobile-{{ $productId }}">
                                <div class="flex items-start gap-3">
                                    <span class="inline-flex h-8 min-w-8 shrink-0 items-center justify-center rounded-[6px] bg-slate-900 px-2 text-sm font-bold text-white">#{{ $item['rank'] }}</span>
                                    @if(!empty($item['image_url']))
                                        <img src="{{ $item['image_url'] }}" alt="{{ $item['title'] }}" class="h-12 w-12 shrink-0 rounded-[6px] border border-slate-200 object-cover">
                                    @endif
                                    <div class="min-w-0 flex-1">
                                        <p class="line-clamp-2 text-sm font-semibold text-slate-900">{{ $item['title'] }}</p>
                                        <p class="mt-1 truncate text-xs text-slate-500">{{ $item['brand'] ?: 'Markasız' }} · {{ $item['seller_name'] ?: 'Satıcı alınamadı' }}</p>
                                    </div>
                                </div>
                                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <div class="min-w-0 rounded-[6px] border border-slate-200 bg-slate-50/70 p-3">
                                        <p class="text-xs text-slate-500">Fiyat / görünen stok</p>
                                        <p class="mt-1 truncate text-sm font-semibold text-slate-900">{{ ($item['price'] ?? null) !== null ? number_format($item['price'], 2, ',', '.') . ' ₺' : 'Fiyat alınamadı' }}</p>
                                        <p class="mt-1 text-xs {{ $stockClass }}">{{ $stockLabel }}</p>
                                    </div>
                                    <div class="min-w-0 rounded-[6px] border border-slate-200 bg-slate-50/70 p-3">
                                        <p class="text-xs text-slate-500">Satış sinyali / kampanya</p>
                                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ $salesSignal !== '' ? $salesSignal : (($item['estimated_sales_3d'] ?? null) !== null ? number_format($item['estimated_sales_3d'], 0, '', '.') . '+ / 3 gün' : 'Satış alınamadı') }}</p>
                                        <p class="mt-1 truncate text-xs text-slate-500">{{ $campaigns[0] ?? ((int) ($item['campaign_count'] ?? 0) > 0 ? $item['campaign_count'] . ' kampanya' : 'Kampanya yok') }}</p>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    @click="trackBestseller($wire, {{ $item['_result_index'] }}, @js($productId), @js($item['source_url']))"
                                    x-bind:disabled="trackingProductId === @js($productId) || @js($tracked)"
                                    class="mt-4 inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] px-4 py-3 text-sm font-medium transition {{ $tracked ? 'border border-emerald-200 bg-emerald-50 text-emerald-700' : 'bg-slate-900 text-white hover:bg-slate-800' }} disabled:cursor-not-allowed disabled:opacity-70"
                                >
                                    <x-lucide.icon name="{{ $tracked ? 'check' : 'plus' }}" class="h-4 w-4" />
                                    <span x-show="trackingProductId !== @js($productId)">{{ $tracked ? 'Takipte' : 'Takibe al' }}</span>
                                    <span x-show="trackingProductId === @js($productId)" x-cloak>Ekleniyor...</span>
                                </button>
                            </article>
                        @endforeach
                    </div>

                    <div class="hidden overflow-x-auto rounded-[8px] border border-slate-200 bg-white md:block" x-data="columnResize()">
                        <table class="w-full min-w-[1050px] table-fixed text-left text-[13px]">
                            <thead class="bg-slate-50/80 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                                <tr>
                                    @foreach($bestsellerVisibleColumns as $column)
                                        <th class="relative border-b border-slate-200 px-2 py-2" style="width: {{ $columnWidths[$column] ?? '140px' }}">
                                            @if(isset($bestsellerSortableColumns[$column]))
                                                <button type="button" wire:click="sortBestsellerTable('{{ $column }}')" class="inline-flex items-center gap-1 text-left transition hover:text-slate-900">
                                                    {{ $bestsellerColumnDefinitions[$column] }} <span class="text-slate-400">{{ $sortIcon($column) }}</span>
                                                </button>
                                            @else
                                                {{ $bestsellerColumnDefinitions[$column] }}
                                            @endif
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($bestsellerTableRows as $item)
                                    @php
                                        $productId = (string) ($item['trendyol_product_id'] ?? '');
                                        $tracked = in_array($productId, $bestsellerTrackedProductIds, true);
                                        $campaigns = array_values((array) ($item['campaigns'] ?? []));
                                        $salesSignal = trim((string) ($item['sold_text'] ?? ''));
                                        $stockStatus = (string) ($item['stock_status'] ?? 'unknown');
                                        $stockLabel = ($item['stock_quantity'] ?? null) !== null
                                            ? number_format($item['stock_quantity'], 0, '', '.') . ' adet'
                                            : ($stockStatus === 'in_stock' ? 'Stokta' : ($stockStatus === 'out_of_stock' ? 'Tükendi' : 'Stok bilinmiyor'));
                                        $stockHint = ($item['stock_quantity'] ?? null) !== null
                                            ? ((int) $item['stock_quantity'] === 0 ? 'Tükendi sinyali' : 'Seçili varyant')
                                            : ($stockStatus === 'in_stock' ? 'Adet paylaşılmıyor' : ($stockStatus === 'out_of_stock' ? 'Stok yok' : 'Detay alınamadı'));
                                    @endphp
                                    <tr class="transition hover:bg-slate-50/60" wire:key="bestseller-row-{{ $productId }}">
                                        @foreach($bestsellerVisibleColumns as $column)
                                            <td class="overflow-hidden px-2 py-2 align-middle">
                                                @switch($column)
                                                    @case('rank')
                                                        <span class="inline-flex h-7 min-w-7 items-center justify-center rounded-[4px] bg-slate-900 px-1.5 text-xs font-bold text-white">#{{ $item['rank'] }}</span>
                                                        @break
                                                    @case('product')
                                                        <div class="flex min-w-0 items-center gap-2">
                                                            @if(!empty($item['image_url']))
                                                                <img src="{{ $item['image_url'] }}" alt="{{ $item['title'] }}" class="h-9 w-9 shrink-0 rounded-[4px] border border-slate-200 object-cover">
                                                            @else
                                                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[4px] border border-slate-200 bg-slate-50 text-slate-400"><x-lucide.icon name="image" class="h-3.5 w-3.5" /></span>
                                                            @endif
                                                            <div class="min-w-0">
                                                                <a href="{{ $item['source_url'] ?? '#' }}" target="_blank" class="truncate font-medium text-slate-900 transition hover:text-slate-600 hover:underline" title="{{ $item['title'] }}">{{ $item['title'] }}</a>
                                                                <p class="mt-0.5 truncate text-[11px] text-slate-500">{{ $item['brand'] ?: 'Markasız' }}</p>
                                                            </div>
                                                        </div>
                                                        @break
                                                    @case('seller')
                                                        @if(!empty($item['seller_name']))
                                                            <div x-data="{ pop: false }" class="relative" @mouseenter="pop = true" @mouseleave="pop = false">
                                                                <a href="https://www.trendyol.com/magaza/profil-m-{{ $item['seller_id'] ?? '' }}" target="_blank" class="truncate font-medium text-slate-900 transition hover:text-slate-600 hover:underline" title="{{ $item['seller_name'] }}">{{ $item['seller_name'] }}</a>
                                                                <p class="mt-0.5 text-[11px] text-slate-500">{{ ($item['seller_score'] ?? null) !== null ? number_format($item['seller_score'], 1, ',', '.') . ' satıcı puanı' : 'Kazanan satıcı' }}</p>
                                                                
                                                                <div x-show="pop" x-cloak class="absolute left-0 top-full z-50 mt-1 w-64 rounded-[8px] border border-slate-200 bg-white p-3 shadow-xl">
                                                                    <div class="flex items-center justify-between">
                                                                        <p class="truncate text-xs font-semibold text-slate-900">{{ $item['seller_name'] }}</p>
                                                                        @if(!empty($item['seller_score']))
                                                                            <span class="flex items-center gap-0.5 text-[11px] font-medium text-amber-600">{{ number_format($item['seller_score'], 1, ',', '.') }}<x-lucide.icon name="star" class="h-3 w-3 fill-current" /></span>
                                                                        @endif
                                                                    </div>
                                                                    <div class="mt-3" x-data="{ state: 'idle', info: null }">
                                                                        <template x-if="state === 'idle'">
                                                                            <button type="button" @click.prevent="state = 'loading'; $wire.analyzeBestsellerSeller('{{ $item['seller_id'] ?? '' }}', '{{ $item['seller_name'] }}').then(r => { info = r; state = 'done'; })" class="inline-flex min-h-[32px] w-full items-center justify-center gap-1.5 rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-1.5 text-[11px] font-medium text-slate-700 transition hover:bg-slate-100">
                                                                                <x-lucide.icon name="bar-chart-2" class="h-3.5 w-3.5" /> Rakip Analizini Getir
                                                                            </button>
                                                                        </template>
                                                                        <template x-if="state === 'loading'">
                                                                            <div class="flex items-center justify-center gap-2 py-2 text-[11px] text-slate-500">
                                                                                <x-lucide.icon name="loader-2" class="h-3.5 w-3.5 animate-spin" /> Analiz ediliyor...
                                                                            </div>
                                                                        </template>
                                                                        <template x-if="state === 'done' && info">
                                                                            <div class="space-y-2 text-[11px]">
                                                                                <div class="flex justify-between border-b border-slate-100 pb-1.5"><span class="text-slate-500">ZOLM Takibi:</span> <span class="font-medium text-slate-900" x-text="info.tracked_count"></span></div>
                                                                                <div class="flex justify-between border-b border-slate-100 pb-1.5"><span class="text-slate-500">Tahmini Başarı:</span> <span class="font-medium text-emerald-700" x-text="info.success_score"></span></div>
                                                                                <a :href="info.url" target="_blank" class="mt-2 block w-full rounded-[6px] bg-slate-900 py-1.5 text-center text-[11px] font-medium text-white transition hover:bg-slate-800">Trendyol Mağazasına Git &rarr;</a>
                                                                            </div>
                                                                        </template>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        @else
                                                            <span class="rounded-[4px] border border-amber-200 bg-amber-50 px-1.5 py-0.5 text-[11px] text-amber-700">Alınamadı</span>
                                                        @endif
                                                        @break
                                                    @case('price')
                                                        @if(($item['price'] ?? null) !== null)
                                                            <p class="font-semibold text-slate-900">{{ number_format($item['price'], 2, ',', '.') }} ₺</p>
                                                            <p class="mt-0.5 text-[11px] text-slate-500">Anlık fiyat</p>
                                                        @else
                                                            <span class="rounded-[4px] border border-amber-200 bg-amber-50 px-1.5 py-0.5 text-[11px] text-amber-700">Fiyat alınamadı</span>
                                                        @endif
                                                        @break
                                                    @case('stock')
                                                        <p class="font-semibold {{ $stockStatus === 'out_of_stock' || ($item['stock_quantity'] ?? null) === 0 ? 'text-rose-700' : 'text-slate-900' }}">{{ $stockLabel }}</p>
                                                        <p class="mt-0.5 text-[11px] text-slate-500">{{ $stockHint }}</p>
                                                        @break
                                                    @case('campaign')
                                                        @if(count($campaigns) > 0)
                                                            <p class="truncate text-[11px] font-semibold text-emerald-700" title="{{ $campaigns[0] }}">{{ $campaigns[0] }}</p>
                                                            <p class="mt-0.5 text-[10px] text-slate-500">{{ count($campaigns) > 1 ? '+' . (count($campaigns) - 1) . ' kampanya daha' : 'Aktif kampanya' }}</p>
                                                        @elseif((int) ($item['campaign_count'] ?? 0) > 0)
                                                            <span class="rounded-[4px] border border-emerald-200 bg-emerald-50 px-1.5 py-0.5 text-[11px] text-emerald-700">{{ $item['campaign_count'] }} kampanya</span>
                                                        @else
                                                            <span class="text-[11px] text-slate-500">Kampanya yok</span>
                                                        @endif
                                                        @break
                                                    @case('sales')
                                                        <p class="font-semibold text-slate-900">{{ $salesSignal !== '' ? $salesSignal : (($item['estimated_sales_3d'] ?? null) !== null ? number_format($item['estimated_sales_3d'], 0, '', '.') . '+ / 3 gün' : 'Alınamadı') }}</p>
                                                        <p class="mt-0.5 text-[11px] text-emerald-700">{{ ($item['estimated_revenue_3d'] ?? null) !== null ? number_format($item['estimated_revenue_3d'], 0, ',', '.') . ' ₺ tahmini ciro' : 'Tahmini ciro yok' }}</p>
                                                        @break
                                                    @case('rating')
                                                        @if(($item['rating'] ?? null) !== null)
                                                            <p class="inline-flex items-center gap-1 font-semibold text-amber-600">{{ number_format($item['rating'], 1, ',', '.') }} <x-lucide.icon name="star" class="h-3.5 w-3.5 fill-current" /></p>
                                                            <p class="mt-0.5 text-[11px] text-slate-500">{{ number_format($item['rating_count'] ?? 0, 0, '', '.') }} değerlendirme</p>
                                                        @else
                                                            <span class="text-[11px] text-slate-500">Puan yok</span>
                                                        @endif
                                                        @break
                                                    @case('actions')
                                                        <button
                                                            type="button"
                                                            @click="trackBestseller($wire, {{ $item['_result_index'] }}, @js($productId), @js($item['source_url']))"
                                                            x-bind:disabled="trackingProductId === @js($productId) || @js($tracked)"
                                                            class="inline-flex min-h-[36px] w-full items-center justify-center gap-1.5 rounded-[6px] px-3 py-1.5 text-xs font-medium transition {{ $tracked ? 'border border-emerald-200 bg-emerald-50 text-emerald-700' : 'bg-slate-900 text-white hover:bg-slate-800' }} disabled:cursor-not-allowed disabled:opacity-70"
                                                        >
                                                            <x-lucide.icon name="{{ $tracked ? 'check' : 'plus' }}" class="h-3.5 w-3.5" />
                                                            <span x-show="trackingProductId !== @js($productId)">{{ $tracked ? 'Takipte' : 'Takibe al' }}</span>
                                                            <span x-show="trackingProductId === @js($productId)" x-cloak>Ekleniyor</span>
                                                        </button>
                                                        @break
                                                @endswitch
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="rounded-[8px] border border-sky-200 bg-sky-50/70 p-3 text-xs leading-5 text-sky-800">
                        “3 günde satıldı” yuvarlanmış bir Trendyol sinyalidir; satış ve ciro değerleri kesin sipariş verisi değil tahmindir. Stok, seçili varyantın ve kazanan satıcının görünen miktarıdır.
                    </div>
                @else
                    <div class="rounded-[8px] border border-dashed border-slate-300 bg-slate-50/40 px-4 py-10 text-center">
                        <span class="mx-auto flex h-10 w-10 items-center justify-center rounded-[8px] border border-slate-200 bg-white text-slate-500"><x-lucide.icon name="search" class="h-5 w-5" /></span>
                        <p class="mt-3 text-sm font-semibold text-slate-900">Canlı sıralama bekleniyor</p>
                        <p class="mt-1 text-sm text-slate-500">Bir kategori veya kelime arayarak ürünleri satıcı, stok ve kampanya sinyalleriyle birlikte alın.</p>
                    </div>
                @endif
            </div>
        @else
            <div class="p-4 lg:p-6">
                @if(!$bestsellerReportsReady)
                    <div class="rounded-[8px] border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">Rapor tabloları migration sonrasında kullanılabilir.</div>
                @elseif(empty(data_get($bestsellerReportDashboard, 'reports', [])))
                    <div class="rounded-[8px] border border-dashed border-slate-300 bg-slate-50/40 px-4 py-12 text-center">
                        <span class="mx-auto flex h-11 w-11 items-center justify-center rounded-[8px] border border-slate-200 bg-white text-slate-500"><x-lucide.icon name="line-chart" class="h-5 w-5" /></span>
                        <h3 class="mt-3 text-base font-semibold text-slate-900">Henüz kayıtlı rapor yok</h3>
                        <p class="mt-1 text-sm text-slate-500">Canlı sonuçlardan ilk raporu kaydettiğinizde günlük sıralama serisi burada oluşur.</p>
                        <button type="button" wire:click="setBestsellerMode('live')" class="mt-4 inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white sm:w-auto sm:py-2">Canlı aramaya dön</button>
                    </div>
                @else
                    <div class="grid grid-cols-1 gap-4 xl:grid-cols-12 lg:gap-4">
                        <aside class="min-w-0 xl:col-span-3">
                            <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                                <div class="flex items-center justify-between gap-2">
                                    <div>
                                        <h3 class="text-sm font-semibold text-slate-900">Rapor serileri</h3>
                                        <p class="mt-1 text-xs text-slate-500">Aynı filtreler tek seride birleşir.</p>
                                    </div>
                                    <span class="rounded-[6px] border border-slate-200 bg-white px-2 py-0.5 text-xs font-mono text-slate-600">{{ count($bestsellerReportDashboard['reports']) }}</span>
                                </div>
                                <div class="mt-3 space-y-2">
                                    @foreach($bestsellerReportDashboard['reports'] as $report)
                                        <button type="button" wire:click="viewBestsellerReport({{ $report['id'] }})" class="block w-full rounded-[8px] border p-3 text-left transition {{ data_get($selectedReport, 'id') === $report['id'] ? 'border-slate-900 bg-white shadow-sm' : 'border-slate-200 bg-white/70 hover:border-slate-300' }}">
                                            <p class="truncate text-sm font-semibold text-slate-900">{{ $report['name'] }}</p>
                                            <p class="mt-1 text-xs text-slate-500">{{ $report['run_count'] }} ölçüm · {{ $report['latest_product_count'] }} ürün</p>
                                            <p class="mt-2 text-[11px] text-slate-400">Son kayıt {{ $report['last_captured_label'] }}</p>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </aside>

                        <div class="min-w-0 space-y-4 xl:col-span-9">
                            <div class="rounded-[8px] border border-slate-200 bg-white p-4">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0">
                                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">Trend çalışma alanı</p>
                                        <h3 class="mt-1 truncate text-lg font-semibold text-slate-900">{{ data_get($selectedReport, 'name') }}</h3>
                                        <p class="mt-1 text-sm text-slate-500">{{ data_get($selectedReport, 'run_count', 0) }} tarihsel ölçüm · son kayıt {{ data_get($selectedReport, 'last_captured_label', '-') }}</p>
                                    </div>
                                    <button type="button" wire:click="loadBestsellerReport({{ data_get($selectedReport, 'id') }})" class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white hover:bg-slate-800 sm:w-auto sm:py-2">
                                        <x-lucide.icon name="refresh-cw" class="h-4 w-4" /> Yeni ölçüm al
                                    </button>
                                </div>

                                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                    <div class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                                        <p class="text-xs text-slate-500">Ölçüm / kalıcı ürün</p>
                                        <p class="mt-1 text-lg font-semibold text-slate-900">{{ data_get($reportSummary, 'run_count', 0) }} / {{ data_get($reportSummary, 'persistent_count', 0) }}</p>
                                    </div>
                                    <div class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                                        <p class="text-xs text-slate-500">Yükselen / düşen</p>
                                        <p class="mt-1 text-lg font-semibold"><span class="text-emerald-700">{{ data_get($reportSummary, 'rising_count', 0) }}</span> <span class="text-slate-300">/</span> <span class="text-rose-700">{{ data_get($reportSummary, 'falling_count', 0) }}</span></p>
                                    </div>
                                    <div class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/70 p-3 sm:col-span-2 xl:col-span-1">
                                        <p class="text-xs text-slate-500">Yeni giriş / listeden çıkan</p>
                                        <p class="mt-1 text-lg font-semibold text-slate-900">{{ data_get($reportSummary, 'new_entry_count', 0) }} / {{ data_get($reportSummary, 'exit_count', 0) }}</p>
                                    </div>
                                </div>
                            </div>

                            @if(data_get($reportSummary, 'run_count', 0) < 2)
                                <div class="rounded-[8px] border border-sky-200 bg-sky-50/70 p-4">
                                    <p class="text-sm font-semibold text-sky-900">İlk ölçüm hazır.</p>
                                    <p class="mt-1 text-sm text-sky-800">Yükseliş, düşüş ve olası neden analizi ikinci günlük kayıttan sonra başlayacak.</p>
                                </div>
                            @endif

                            <div class="rounded-[8px] border border-slate-200 bg-white p-4">
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <h3 class="text-sm font-semibold text-slate-900">Ürün sıralama eğrileri</h3>
                                        <p class="mt-1 text-xs text-slate-500">Daha yukarıdaki çizgi daha iyi sırayı gösterir; boşluk ürünün o ölçümde listede olmadığını belirtir.</p>
                                    </div>
                                    <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-mono text-slate-600">Son {{ count(data_get($reportAnalysis, 'runs', [])) }} ölçüm</span>
                                </div>
                                <div class="mt-4 space-y-3">
                                    @forelse(data_get($reportAnalysis, 'products', []) as $product)
                                        @php
                                            $rankPoints = collect($product['rank_points']);
                                            $pointCount = max(1, $rankPoints->count() - 1);
                                            $coordinates = $rankPoints->map(function ($rank, $index) use ($pointCount) {
                                                if ($rank === null) return null;
                                                $x = 6 + (($index / $pointCount) * 208);
                                                $y = 6 + (((max(1, min(20, (int) $rank)) - 1) / 19) * 44);
                                                return number_format($x, 1, '.', '') . ',' . number_format($y, 1, '.', '');
                                            })->filter()->implode(' ');
                                        @endphp
                                        <div class="grid grid-cols-1 gap-3 rounded-[8px] border border-slate-200 bg-slate-50/60 p-3 sm:grid-cols-[minmax(0,1fr)_220px_110px] sm:items-center">
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-semibold text-slate-900" title="{{ $product['title'] }}">{{ $product['title'] }}</p>
                                                <p class="mt-1 text-xs text-slate-500">{{ $product['appearances'] }}/{{ data_get($reportSummary, 'run_count', 0) }} görünüm · en iyi #{{ $product['best_rank'] }} · kalıcılık %{{ $product['persistence_percent'] }}</p>
                                            </div>
                                            <svg viewBox="0 0 220 56" class="h-14 w-full" role="img" aria-label="{{ $product['title'] }} sıra grafiği">
                                                <line x1="6" y1="6" x2="214" y2="6" stroke="#e2e8f0" stroke-width="1" />
                                                <line x1="6" y1="50" x2="214" y2="50" stroke="#e2e8f0" stroke-width="1" />
                                                @if($coordinates !== '')
                                                    <polyline points="{{ $coordinates }}" fill="none" stroke="#0f172a" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                                                @endif
                                            </svg>
                                            <div class="text-left sm:text-right">
                                                <p class="text-lg font-semibold text-slate-900">{{ $product['current_rank'] ? '#' . $product['current_rank'] : 'Liste dışı' }}</p>
                                                <p class="mt-1 text-xs {{ ($product['rank_delta'] ?? 0) > 0 ? 'text-emerald-700' : (($product['rank_delta'] ?? 0) < 0 ? 'text-rose-700' : 'text-slate-500') }}">
                                                    {{ ($product['rank_delta'] ?? null) === null ? 'İlk ölçüm' : (($product['rank_delta'] > 0 ? '+' : '') . $product['rank_delta'] . ' sıra') }}
                                                </p>
                                            </div>
                                        </div>
                                    @empty
                                        <div class="rounded-[8px] border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500">Grafik için ürün kaydı bulunamadı.</div>
                                    @endforelse
                                </div>
                            </div>

                            <div class="rounded-[8px] border border-slate-200 bg-white p-4">
                                <h3 class="text-sm font-semibold text-slate-900">Son ölçüm ve olası nedenler</h3>
                                <p class="mt-1 text-xs text-slate-500">Yorumlar gözlenen fiyat, stok ve kampanya değişimlerinin korelasyonuna dayanır; kesin nedensellik iddiası değildir.</p>
                                <div class="mt-4 space-y-2">
                                    @foreach(data_get($reportAnalysis, 'latest_items', []) as $item)
                                        @php
                                            $causeTone = data_get($item, 'cause.tone', 'neutral');
                                            $causeClasses = match($causeTone) {
                                                'positive' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
                                                'negative' => 'border-rose-200 bg-rose-50 text-rose-800',
                                                'info' => 'border-sky-200 bg-sky-50 text-sky-800',
                                                default => 'border-slate-200 bg-slate-50 text-slate-700',
                                            };
                                        @endphp
                                        <div class="grid grid-cols-1 gap-3 rounded-[8px] border border-slate-200 p-3 sm:grid-cols-[64px_minmax(0,1fr)_180px] sm:items-center">
                                            <div>
                                                <p class="text-base font-semibold text-slate-900">#{{ $item['rank'] }}</p>
                                                <p class="mt-1 text-xs {{ ($item['rank_delta'] ?? 0) > 0 ? 'text-emerald-700' : (($item['rank_delta'] ?? 0) < 0 ? 'text-rose-700' : 'text-slate-500') }}">{{ ($item['rank_delta'] ?? null) === null ? 'Yeni' : (($item['rank_delta'] > 0 ? '+' : '') . $item['rank_delta']) }}</p>
                                            </div>
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-semibold text-slate-900">{{ $item['title'] }}</p>
                                                <p class="mt-1 text-xs text-slate-500">{{ $item['seller_name'] ?: 'Satıcı alınamadı' }} · {{ $item['stock_quantity'] !== null ? number_format($item['stock_quantity'], 0, '', '.') . ' stok' : 'stok bilinmiyor' }} · {{ $item['campaign_count'] }} kampanya</p>
                                            </div>
                                            <div class="rounded-[6px] border px-3 py-2 {{ $causeClasses }}">
                                                <p class="text-xs font-semibold">{{ data_get($item, 'cause.label') }}</p>
                                                <p class="mt-1 line-clamp-2 text-[11px] leading-4 opacity-80" title="{{ data_get($item, 'cause.detail') }}">{{ data_get($item, 'cause.detail') }}</p>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </section>
</div>
