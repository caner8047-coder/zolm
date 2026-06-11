@php
    $listings = $product->channelListings ?? collect();
    $listingCount = $listings->count();
    $activeStatuses = ['active', 'approved', 'live', 'on_sale', 'onsale', 'published', 'publish', 'enabled'];
    $activeListingCount = $listings->filter(fn ($listing) => in_array(strtolower((string) $listing->listing_status), $activeStatuses, true))->count();
    $pendingIssues = $listings->sum(fn ($listing) => ($listing->matchIssues ?? collect())->where('match_status', 'pending')->count());

    $formatMoney = fn ($value) => '₺' . number_format((float) $value, 2, ',', '.');
    $formatCount = fn ($value) => number_format((float) $value, 0, ',', '.');
    $formatPercent = fn ($value) => $value !== null ? '%' . number_format((float) $value, 1, ',', '.') : '—';
    $formatMultiplier = fn ($value) => $value !== null ? '%' . number_format((((float) $value) - 1) * 100, 1, ',', '.') : '—';

    $commissionScenarios = $this->productCommissionScenarios($product);
    $selectedProfitScenario = $this->selectedProductCommissionScenario($product, $commissionScenarios);
    $masterSalePrice = (float) ($product->sale_price ?? 0);
    $salePrice = (float) ($selectedProfitScenario['sale_price'] ?? $product->sale_price ?? 0);
    $profitValue = (float) ($selectedProfitScenario['profit'] ?? $product->profit_metric ?? $product->profit ?? 0);
    $profitMargin = $selectedProfitScenario['profit_margin'] ?? null;
    $masterStock = (int) ($product->stock_quantity ?? 0);
    $totalUnitCost = (float) $product->total_cost;
    $commissionRate = (float) ($selectedProfitScenario['commission_rate'] ?? $product->channel_commission_rate_metric ?? $product->commission_rate ?? 0);
    $commissionAmount = (float) ($selectedProfitScenario['commission_amount'] ?? ($salePrice * ($commissionRate / 100)));
    $receivableAmount = (float) ($selectedProfitScenario['receivable'] ?? ($salePrice - $commissionAmount));
    $selectedScenarioLabel = (string) ($selectedProfitScenario['selection_label'] ?? $selectedProfitScenario['label'] ?? $this->productProfitDefaultMarketplaceLabel());
    $extraCostFixed = (float) ($selectedProfitScenario['extra_cost_fixed'] ?? $product->extra_cost_fixed ?? 0);
    $extraCostPercentage = (float) ($selectedProfitScenario['extra_cost_percentage'] ?? $product->extra_cost_percentage ?? 0);
    $extraCostPercentageAmount = (float) ($selectedProfitScenario['extra_cost_percentage_amount'] ?? ($salePrice * ($extraCostPercentage / 100)));
    $extraCostTotal = $extraCostFixed + $extraCostPercentageAmount;
    $returnRate = $product->return_rate !== null ? (float) $product->return_rate : null;
    $changeLogs = $this->productChangeHistory($product, 12);

    $priceMismatchCount = $listings->filter(fn ($listing) => abs(((float) ($listing->sale_price ?? 0)) - $masterSalePrice) > 1)->count();
    $stockMismatchCount = $listings->filter(fn ($listing) => (int) ($listing->stock_quantity ?? 0) !== $masterStock)->count();
    $latestListingSyncAt = $product->latest_listing_sync_at_metric
        ? \Illuminate\Support\Carbon::parse($product->latest_listing_sync_at_metric)
        : null;

    $todoItems = [];

    if ($pendingIssues > 0) {
        $todoItems[] = [
            'tone' => 'warning',
            'title' => 'Önce eşleştirmeyi düzelt',
            'body' => "{$pendingIssues} kanal kaydı ZOLM ürününe tam bağlanmamış. Eşleştirme ekranında doğru ürünü seç.",
        ];
    }

    if ($priceMismatchCount > 0) {
        $todoItems[] = [
            'tone' => 'warning',
            'title' => 'Fiyat farklı görünüyor',
            'body' => "{$priceMismatchCount} mağazada fiyat ZOLM fiyatından farklı. Doğruysa bırak, değilse fiyat gönder.",
        ];
    }

    if ($stockMismatchCount > 0) {
        $todoItems[] = [
            'tone' => 'warning',
            'title' => 'Stok farklı görünüyor',
            'body' => "{$stockMismatchCount} mağazada stok ZOLM stokuyla aynı değil. Doğruysa bırak, değilse stok gönder.",
        ];
    }

    if ($profitValue < 0) {
        $todoItems[] = [
            'tone' => 'danger',
            'title' => 'Bu satış zarar yazabilir',
            'body' => 'Satış fiyatı maliyet ve komisyonu karşılamıyor. Fiyat veya maliyet bilgisini kontrol et.',
        ];
    }

    if ($returnRate !== null && $returnRate >= 15) {
        $todoItems[] = [
            'tone' => 'warning',
            'title' => 'İade oranı yüksek',
            'body' => 'Sipariş geçmişinden gelen iade oranı yüksek. Kanal bazlı fiyat, açıklama ve paketleme kontrolü yapılmalı.',
        ];
    }

    if ($todoItems === []) {
        $todoItems[] = [
            'tone' => 'success',
            'title' => 'Her şey yolunda görünüyor',
            'body' => 'Eşleşme, fiyat ve stok için acil bir işlem görünmüyor.',
        ];
    }

    $formatChangeValue = function ($log, $value) {
        if ($value === null || $value === '') {
            return 'Boş';
        }

        return match ((string) $log->value_type) {
            'money' => '₺' . number_format((float) $value, 2, ',', '.'),
            'percent' => '%' . number_format((float) $value, 2, ',', '.'),
            'integer' => number_format((float) $value, 0, ',', '.'),
            'boolean' => (bool) $value ? 'Açık' : 'Kapalı',
            default => (string) $value,
        };
    };

@endphp

<div class="space-y-3">
    <section class="rounded-[8px] border border-slate-200 bg-white p-4">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Kısa Özet</p>
                <h3 class="mt-1 text-base font-semibold text-slate-900">Bu ürünün hızlı durumu</h3>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <p class="text-xs text-slate-500">
                    Son ürün çekme:
                    <span class="font-medium text-slate-900">{{ $latestListingSyncAt?->format('d.m.Y H:i') ?: 'Henüz yok' }}</span>
                </p>
                <button type="button"
                        wire:click="refreshCurrentStatus({{ $product->id }})"
                        wire:loading.attr="disabled"
                        wire:target="refreshCurrentStatus({{ $product->id }})"
                        title="Pazaryerlerinden bu ürünün güncel fiyat, stok ve kanal bilgisini al"
                        class="inline-flex min-h-[34px] items-center justify-center gap-2 rounded-[6px] border border-slate-200 bg-white px-3 text-xs font-medium text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60">
                    <svg class="h-3.5 w-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 4v6h6M20 20v-6h-6M5.5 15A7 7 0 0018 18.5M18.5 9A7 7 0 006 5.5" />
                    </svg>
                    Güncel durum al
                </button>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                <p class="text-xs text-slate-500">Tahmini kâr</p>
                <p class="mt-1 text-lg font-semibold {{ $profitValue >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                    {{ $formatMoney($profitValue) }}
                    <span class="ml-1 text-xs font-semibold">{{ $formatMultiplier($profitMargin) }}</span>
                </p>
                <p class="mt-1 text-xs text-slate-500">{{ $selectedScenarioLabel }} kom.: {{ $formatMoney($commissionAmount) }}</p>
            </div>

            <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                <p class="text-xs text-slate-500">Varsayılan fiyat</p>
                <p class="mt-1 text-lg font-semibold text-slate-900">{{ $formatMoney($salePrice) }}</p>
                <p class="mt-1 text-xs text-slate-500">Hakediş: {{ $formatMoney($receivableAmount) }}</p>
            </div>

            <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                <p class="text-xs text-slate-500">ZOLM stoğu</p>
                <p class="mt-1 text-lg font-semibold text-slate-900">{{ $formatCount($masterStock) }}</p>
                <p class="mt-1 text-xs text-slate-500">Ürün kartındaki ana stok</p>
            </div>

            <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                <p class="text-xs text-slate-500">Mağaza durumu</p>
                <p class="mt-1 text-lg font-semibold text-slate-900">{{ $activeListingCount }}/{{ $listingCount }}</p>
                <p class="mt-1 text-xs text-slate-500">Yayında olan mağaza</p>
            </div>

            <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                <p class="text-xs text-slate-500">Ek gider</p>
                <p class="mt-1 text-lg font-semibold text-slate-900">{{ $formatMoney($extraCostTotal) }}</p>
                <p class="mt-1 text-xs text-slate-500">Sabit {{ $formatMoney($extraCostFixed) }} · %{{ number_format($extraCostPercentage, 1, ',', '.') }}</p>
            </div>

            <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                <p class="text-xs text-slate-500">İade / teslimat</p>
                <p class="mt-1 text-lg font-semibold {{ ($returnRate ?? 0) >= 15 ? 'text-rose-600' : 'text-slate-900' }}">{{ $formatPercent($returnRate) }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ $product->fast_delivery_type ?: 'Standart teslimat' }}</p>
            </div>
        </div>
    </section>

    <section class="rounded-[8px] border border-slate-200 bg-white p-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Değişim Defteri</p>
                <h3 class="mt-1 text-base font-semibold text-slate-900">Fiyat, maliyet ve lojistik geçmişi</h3>
            </div>
            <span class="inline-flex w-fit items-center rounded-[6px] border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-600">
                Son {{ $changeLogs->count() }} kayıt
            </span>
        </div>

        @if($changeLogs->isNotEmpty())
            <div class="mt-4 overflow-hidden rounded-lg border border-slate-200">
                <div class="hidden grid-cols-[minmax(130px,0.8fr)_minmax(150px,1fr)_minmax(180px,1.4fr)_minmax(140px,1fr)] gap-3 border-b border-slate-200 bg-slate-50/70 px-3 py-2 text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400 md:grid">
                    <span>Tarih</span>
                    <span>Alan</span>
                    <span>Değişim</span>
                    <span>Kaynak</span>
                </div>
                <div class="divide-y divide-slate-100 bg-white">
                    @foreach($changeLogs as $change)
                        <article class="grid grid-cols-1 gap-2 px-3 py-3 text-sm md:grid-cols-[minmax(130px,0.8fr)_minmax(150px,1fr)_minmax(180px,1.4fr)_minmax(140px,1fr)] md:items-center md:gap-3">
                            <div class="min-w-0">
                                <p class="font-medium text-slate-900">{{ $change->changed_at?->format('d.m.Y H:i') ?: '-' }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">{{ $change->changedByUser?->name ?: 'Sistem' }}</p>
                            </div>
                            <div class="min-w-0">
                                <p class="font-semibold text-slate-900">{{ $change->field_label }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    {{ $change->change_scope === 'listing' ? ($change->store?->store_name ?: 'Kanal kaydı') : 'Ana ürün' }}
                                </p>
                            </div>
                            <div class="min-w-0">
                                <div class="flex min-w-0 flex-wrap items-center gap-1.5">
                                    <span class="max-w-full truncate rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-1 text-xs font-medium text-slate-600">
                                        {{ $formatChangeValue($change, $change->old_value) }}
                                    </span>
                                    <span class="text-slate-300">→</span>
                                    <span class="max-w-full truncate rounded-[6px] border border-emerald-200 bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700">
                                        {{ $formatChangeValue($change, $change->new_value) }}
                                    </span>
                                </div>
                                @if($change->delta_number !== null)
                                    <p class="mt-1 text-xs {{ (float) $change->delta_number >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                                        {{ (float) $change->delta_number >= 0 ? '+' : '' }}{{ $formatChangeValue($change, $change->delta_number) }}
                                        @if($change->delta_percent !== null)
                                            · %{{ number_format((float) $change->delta_percent, 1, ',', '.') }}
                                        @endif
                                    </p>
                                @endif
                            </div>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-slate-700">{{ $change->source_label ?: $change->source }}</p>
                                @if($change->note)
                                    <p class="mt-0.5 truncate text-xs text-slate-500" title="{{ $change->note }}">{{ $change->note }}</p>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        @else
            <div class="mt-4 rounded-[8px] border border-dashed border-slate-300 bg-slate-50 px-4 py-5 text-sm text-slate-500">
                Bu ürün için henüz fiyat, maliyet veya lojistik değişim kaydı oluşmadı.
            </div>
        @endif
    </section>

    <section class="rounded-[8px] border border-slate-200 bg-white p-4">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Yapılacak İş</p>
        <div class="mt-3 grid grid-cols-1 gap-2 lg:grid-cols-2">
            @foreach($todoItems as $item)
                <div class="rounded-[8px] border p-3 {{ $item['tone'] === 'danger' ? 'border-rose-200 bg-rose-50' : ($item['tone'] === 'warning' ? 'border-amber-200 bg-amber-50' : 'border-emerald-200 bg-emerald-50') }}">
                    <p class="text-sm font-semibold {{ $item['tone'] === 'danger' ? 'text-rose-800' : ($item['tone'] === 'warning' ? 'text-amber-800' : 'text-emerald-800') }}">{{ $item['title'] }}</p>
                    <p class="mt-1 text-sm {{ $item['tone'] === 'danger' ? 'text-rose-700' : ($item['tone'] === 'warning' ? 'text-amber-700' : 'text-emerald-700') }}">{{ $item['body'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <section class="rounded-[8px] border border-slate-200 bg-white p-4">
        <div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Mağazalardaki Durum</p>
                <h3 class="mt-1 text-base font-semibold text-slate-900">{{ $listingCount }} bağlı mağaza kaydı</h3>
            </div>
        </div>

        @if($listings->isNotEmpty())
            <div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-2 2xl:grid-cols-4">
                @foreach($listings as $listing)
                    @php
                        $store = $listing->store;
                        $channelProduct = $listing->channelProduct;
                        $listingPrice = (float) ($listing->sale_price ?? 0);
                        $listingStock = (int) ($listing->stock_quantity ?? 0);
                        $priceDelta = $listingPrice - $masterSalePrice;
                        $stockDelta = $listingStock - $masterStock;
                        $pendingIssueCount = ($listing->matchIssues ?? collect())->where('match_status', 'pending')->count();
                        $pricePushEnabled = (bool) data_get($store, 'syncProfile.price_push_enabled');
                        $stockPushEnabled = (bool) data_get($store, 'syncProfile.stock_push_enabled');
                        $storeActive = (bool) data_get($store, 'is_active', false);
                        $capabilities = $store?->marketplace
                            ? app(\App\Services\Marketplace\MarketplaceConnectorManager::class)->resolve((string) $store->marketplace)->capabilities()
                            : [];
                        $pricePushSupported = (bool) ($capabilities['price_push'] ?? false);
                        $stockPushSupported = (bool) ($capabilities['stock_push'] ?? false);
                        $canPushPrice = $storeActive && $pricePushEnabled && $pricePushSupported;
                        $canPushStock = $storeActive && $stockPushEnabled && $stockPushSupported;
                        $priceDisabledReason = !$pricePushSupported
                            ? 'Bu kanal fiyat gönderimini desteklemiyor.'
                            : (!$pricePushEnabled ? 'Entegrasyon ayarlarında fiyat gönderimi kapalı.' : (!$storeActive ? 'Mağaza pasif.' : null));
                        $stockDisabledReason = !$stockPushSupported
                            ? 'Bu kanal stok gönderimini desteklemiyor.'
                            : (!$stockPushEnabled ? 'Entegrasyon ayarlarında stok gönderimi kapalı.' : (!$storeActive ? 'Mağaza pasif.' : null));
                        $priceSame = abs($priceDelta) <= 1;
                        $stockSame = $stockDelta === 0;
                        $listingPublicUrl = $this->marketplacePublicProductUrl($listing);
                        $listingScenario = $this->listingCommissionScenario($product, $listing);
                        $listingCommissionRate = (float) ($listingScenario['commission_rate'] ?? 0);
                        $listingCommissionAmount = (float) ($listingScenario['commission_amount'] ?? 0);
                        $listingReceivable = (float) ($listingScenario['receivable'] ?? 0);
                        $listingProfit = (float) ($listingScenario['profit'] ?? 0);
                        $listingProfitMargin = $listingScenario['profit_margin'] ?? null;
                        $listingDeliveryLabel = $this->listingDeliveryTermLabel($listing);
                    @endphp

                    <article class="group rounded-[8px] border border-slate-200 bg-slate-50/70 p-3 transition hover:border-slate-300 hover:bg-white hover:shadow-sm">
                        <div class="flex min-w-0 items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex min-w-0 items-center gap-2">
                                    <p class="truncate text-sm font-semibold text-slate-900">{{ $store?->store_name ?: 'Mağaza adı yok' }}</p>
                                    <x-zolm.status-badge tone="default">{{ $this->humanMarketplace($store?->marketplace) }}</x-zolm.status-badge>
                                </div>
                                @if($listingPublicUrl)
                                    <a href="{{ $listingPublicUrl }}"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       class="mt-1 inline-flex max-w-full items-center gap-1 truncate text-xs font-medium text-slate-600 underline-offset-2 hover:text-slate-900 hover:underline"
                                       title="Pazaryerinde aç: {{ $channelProduct?->title ?: '-' }}">
                                        <span class="truncate">{{ $channelProduct?->title ?: 'Kanal başlığı yok' }}</span>
                                        <span class="shrink-0 text-slate-400">↗</span>
                                    </a>
                                @else
                                    <p class="mt-1 truncate text-xs text-slate-500" title="{{ $channelProduct?->title ?: '-' }}">
                                        {{ $channelProduct?->title ?: 'Kanal başlığı yok' }}
                                    </p>
                                @endif
                            </div>
                            <div class="flex shrink-0 items-center gap-1.5">
                                @if($listingPublicUrl)
                                    <a href="{{ $listingPublicUrl }}"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       class="inline-flex h-7 items-center justify-center rounded-[6px] border border-slate-200 bg-white px-2 text-[11px] font-medium text-slate-600 transition hover:border-slate-300 hover:bg-slate-100 hover:text-slate-900"
                                       title="Pazaryerinde aç">
                                        Pazaryeri ↗
                                    </a>
                                @endif
                                <x-zolm.status-badge :tone="$this->listingStatusTone($listing->listing_status)">{{ $this->listingStatusLabel($listing->listing_status) }}</x-zolm.status-badge>
                            </div>
                        </div>

                        <div class="mt-3 grid grid-cols-3 gap-2">
                            <div class="rounded-[6px] border border-slate-200 bg-white px-2 py-2">
                                <p class="text-[10px] font-semibold uppercase tracking-[0.08em] text-slate-400">Fiyat</p>
                                <p class="mt-1 truncate text-sm font-semibold text-slate-900">{{ $formatMoney($listingPrice) }}</p>
                                <p class="mt-0.5 truncate text-[11px] {{ $priceSame ? 'text-emerald-600' : 'text-amber-700' }}">
                                    {{ $priceSame ? 'Aynı' : (($priceDelta > 0 ? '+' : '') . $formatMoney($priceDelta)) }}
                                </p>
                                <p class="mt-0.5 truncate text-[11px] text-slate-500">
                                    Kom. %{{ number_format($listingCommissionRate, 1, ',', '.') }}
                                </p>
                            </div>

                            <div class="rounded-[6px] border border-slate-200 bg-white px-2 py-2">
                                <p class="text-[10px] font-semibold uppercase tracking-[0.08em] text-slate-400">Stok</p>
                                <p class="mt-1 truncate text-sm font-semibold text-slate-900">{{ $formatCount($listingStock) }}</p>
                                <p class="mt-0.5 truncate text-[11px] {{ $stockSame ? 'text-emerald-600' : 'text-amber-700' }}">
                                    {{ $stockSame ? 'Aynı' : (($stockDelta > 0 ? '+' : '') . $formatCount($stockDelta)) }}
                                </p>
                            </div>

                            <div class="rounded-[6px] border border-slate-200 bg-white px-2 py-2">
                                <p class="text-[10px] font-semibold uppercase tracking-[0.08em] text-slate-400">Eşleşme</p>
                                <p class="mt-1 truncate text-sm font-semibold {{ $pendingIssueCount > 0 ? 'text-amber-700' : 'text-emerald-600' }}">
                                    {{ $pendingIssueCount > 0 ? 'Bak' : 'Bağlı' }}
                                </p>
                                <p class="mt-0.5 truncate text-[11px] text-slate-500">{{ $pendingIssueCount }} sorun</p>
                            </div>
                        </div>

                        <div class="mt-2 grid grid-cols-3 gap-2">
                            <div class="rounded-[6px] border border-slate-200 bg-white px-2 py-2">
                                <p class="text-[10px] font-semibold uppercase tracking-[0.08em] text-slate-400">Komisyon</p>
                                <p class="mt-1 truncate text-sm font-semibold text-slate-900">%{{ number_format($listingCommissionRate, 1, ',', '.') }}</p>
                                <p class="mt-0.5 truncate text-[11px] text-slate-500">{{ $formatMoney($listingCommissionAmount) }}</p>
                            </div>

                            <div class="rounded-[6px] border border-slate-200 bg-white px-2 py-2">
                                <p class="text-[10px] font-semibold uppercase tracking-[0.08em] text-slate-400">Hakediş</p>
                                <p class="mt-1 truncate text-sm font-semibold text-slate-900">{{ $formatMoney($listingReceivable) }}</p>
                                <p class="mt-0.5 truncate text-[11px] text-slate-500">{{ $listingScenario['commission_source'] ?? '-' }}</p>
                            </div>

                            <div class="rounded-[6px] border border-slate-200 bg-white px-2 py-2">
                                <p class="text-[10px] font-semibold uppercase tracking-[0.08em] text-slate-400">Kâr</p>
                                <p class="mt-1 truncate text-sm font-semibold {{ $listingProfit >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ $formatMoney($listingProfit) }}</p>
                                <p class="mt-0.5 truncate text-[11px] text-slate-500">{{ $formatMultiplier($listingProfitMargin) }}</p>
                            </div>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-1.5 text-[11px] text-slate-500">
                            <span class="rounded-[6px] border border-slate-200 bg-white px-2 py-1">
                                SKU <span class="font-mono text-slate-700">{{ $channelProduct?->stock_code ?: '-' }}</span>
                            </span>
                            <span class="rounded-[6px] border border-slate-200 bg-white px-2 py-1">
                                Barkod <span class="font-mono text-slate-700">{{ $channelProduct?->barcode ?: '-' }}</span>
                            </span>
                            <span class="rounded-[6px] border border-slate-200 bg-white px-2 py-1">
                                Termin <span class="font-medium text-slate-700">{{ $listingDeliveryLabel }}</span>
                            </span>
                        </div>

                        @if(config('marketplace.features.listing_push_enabled', true))
                            <div class="mt-3 grid grid-cols-2 gap-2">
                                <button type="button"
                                        wire:click="syncListingPrice({{ $listing->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="syncListingPrice({{ $listing->id }})"
                                        title="{{ $priceDisabledReason ?: 'ZOLM fiyatını bu kanala gönder' }}"
                                        @disabled(!$canPushPrice)
                                        class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] border px-2 py-2 text-xs font-medium transition sm:min-h-[38px] {{ $canPushPrice ? 'border-slate-200 bg-white text-slate-700 hover:bg-slate-100' : 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400' }}">
                                    Fiyat gönder
                                </button>

                                <button type="button"
                                        wire:click="syncListingStock({{ $listing->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="syncListingStock({{ $listing->id }})"
                                        title="{{ $stockDisabledReason ?: 'ZOLM stoğunu bu kanala gönder' }}"
                                        @disabled(!$canPushStock)
                                        class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] border px-2 py-2 text-xs font-medium transition sm:min-h-[38px] {{ $canPushStock ? 'border-slate-200 bg-white text-slate-700 hover:bg-slate-100' : 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400' }}">
                                    Stok gönder
                                </button>
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
        @else
            <div class="mt-4 rounded-[8px] border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                Bu ürüne bağlı mağaza kaydı yok. Ürünleri çektiğinizde burada görünür.
            </div>
        @endif
    </section>
</div>
