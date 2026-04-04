@php
    $listings = $product->channelListings ?? collect();
    $listingCount = $listings->count();
    $activeStatuses = ['active', 'approved', 'live', 'on_sale', 'published'];
    $activeListingCount = $listings->filter(fn ($listing) => in_array(strtolower((string) $listing->listing_status), $activeStatuses, true))->count();
    $inactiveListingCount = max(0, $listingCount - $activeListingCount);
    $pendingIssues = $listings->sum(fn ($listing) => ($listing->matchIssues ?? collect())->where('match_status', 'pending')->count());

    $formatMoney = fn ($value) => '₺' . number_format((float) $value, 2, ',', '.');
    $formatCount = fn ($value) => number_format((float) $value, 0, ',', '.');

    $salePrice = (float) ($product->sale_price ?? 0);
    $marketPrice = (float) ($product->market_price ?? 0);
    $cogs = (float) ($product->cogs ?? 0);
    $packagingCost = (float) ($product->packaging_cost ?? 0);
    $cargoCost = (float) ($product->cargo_cost ?? 0);
    $totalUnitCost = (float) $product->total_cost;
    $commissionRate = (float) ($product->commission_rate ?? 0);
    $vatRate = (float) ($product->vat_rate ?? 0);

    $commissionAmount = $salePrice * ($commissionRate / 100);
    $netAfterCommission = $salePrice - $commissionAmount;
    $profitValue = isset($product->profit_metric)
        ? (float) $product->profit_metric
        : ($salePrice - $totalUnitCost - $commissionAmount);
    $profitMargin = $salePrice > 0 ? round(($profitValue / $salePrice) * 100, 1) : 0;

    $breakEvenPrice = $commissionRate >= 100
        ? null
        : (($totalUnitCost > 0) ? ($totalUnitCost / max(0.01, (1 - ($commissionRate / 100)))) : 0);
    $priceBuffer = $breakEvenPrice !== null ? ($salePrice - $breakEvenPrice) : null;

    $masterStock = (int) ($product->stock_quantity ?? 0);
    $priceMismatchThreshold = 1.00;
    $priceMismatchCount = $listings->filter(fn ($listing) => abs(((float) ($listing->sale_price ?? 0)) - $salePrice) > $priceMismatchThreshold)->count();
    $stockMismatchCount = $listings->filter(fn ($listing) => (int) ($listing->stock_quantity ?? 0) !== $masterStock)->count();

    $now = now();
    $staleSyncHours = 24;
    $staleSyncCount = $listings->filter(function ($listing) use ($now, $staleSyncHours) {
        if (!$listing->last_synced_at) {
            return true;
        }

        return $listing->last_synced_at->diffInHours($now) > $staleSyncHours;
    })->count();

    $latestListingSyncAt = $product->latest_listing_sync_at_metric
        ? \Illuminate\Support\Carbon::parse($product->latest_listing_sync_at_metric)
        : null;

    $allPushRuns = $listings->flatMap(fn ($listing) => $listing->pushRuns ?? collect());
    $failedPush24h = $allPushRuns->filter(function ($run) use ($now) {
        $runAt = $run->created_at;

        return $run->status === 'failed' && $runAt && $runAt->gte($now->copy()->subHours(24));
    })->count();
    $latestPushAt = $allPushRuns->max('created_at');

    $activeRatio = $listingCount > 0 ? round(($activeListingCount / $listingCount) * 100, 1) : 0;
    $riskPoint = ($pendingIssues * 10) + ($staleSyncCount * 8) + ($priceMismatchCount * 6) + ($stockMismatchCount * 6) + ($failedPush24h * 8) + ($inactiveListingCount * 4);
    $healthScore = max(0, min(100, 100 - $riskPoint));

    $healthTone = 'success';
    $healthLabel = 'Sağlıklı';
    if ($healthScore < 50) {
        $healthTone = 'danger';
        $healthLabel = 'Kritik izleme';
    } elseif ($healthScore < 75) {
        $healthTone = 'warning';
        $healthLabel = 'İyileştirme gerekli';
    }

    $marketplaceBreakdown = $listings
        ->groupBy(fn ($listing) => (string) data_get($listing, 'store.marketplace', 'unknown'))
        ->map(function ($group, $marketplaceKey) use ($activeStatuses) {
            $issueCount = $group->sum(fn ($listing) => ($listing->matchIssues ?? collect())->where('match_status', 'pending')->count());
            $activeCount = $group->filter(fn ($listing) => in_array(strtolower((string) $listing->listing_status), $activeStatuses, true))->count();

            return [
                'marketplace' => $marketplaceKey,
                'total' => $group->count(),
                'active' => $activeCount,
                'issues' => $issueCount,
            ];
        })
        ->values();

    $insights = [];
    if ($profitValue < 0) {
        $insights[] = [
            'tone' => 'danger',
            'title' => 'Ürün şu an zarar yazıyor.',
            'detail' => 'Satış fiyatı, maliyet ve komisyon toplamını karşılamıyor. Öncelik fiyat veya komisyon optimizasyonu olmalı.',
        ];
    } elseif ($priceBuffer !== null && $priceBuffer < ($salePrice * 0.05)) {
        $insights[] = [
            'tone' => 'warning',
            'title' => 'Kâr tamponu düşük.',
            'detail' => 'Başa baş fiyat ile mevcut satış fiyatı arasındaki fark dar. Kampanya döneminde hızla negatife dönebilir.',
        ];
    }

    if ($priceMismatchCount > 0) {
        $insights[] = [
            'tone' => 'warning',
            'title' => 'Kanal fiyatlarında sapma var.',
            'detail' => $priceMismatchCount . ' listeleme master fiyattan farklı görünüyor. Fiyat gönderimi veya kanal bazlı fiyat politikası kontrol edilmeli.',
        ];
    }

    if ($stockMismatchCount > 0) {
        $insights[] = [
            'tone' => 'warning',
            'title' => 'Stok eşitsizliği tespit edildi.',
            'detail' => $stockMismatchCount . ' listeleme master stokla uyuşmuyor. Stok gönderimi gecikmesi satış kaybı yaratabilir.',
        ];
    }

    if ($pendingIssues > 0) {
        $insights[] = [
            'tone' => 'warning',
            'title' => 'Açık eşleşme sorun kayıtları var.',
            'detail' => $pendingIssues . ' kayıt eşleşme bekliyor. Doğru eşleşmeyen listelemelerde kâr raporları yanıltıcı olabilir.',
        ];
    }

    if ($staleSyncCount > 0 || $failedPush24h > 0) {
        $insights[] = [
            'tone' => 'info',
            'title' => 'Senkronizasyon sağlığı izlenmeli.',
            'detail' => 'Geciken senkron veya son 24 saatte hata alan gönderim kayıtları var. Kontrol merkezi üzerinden onarım önerilir.',
        ];
    }

    if ($insights === []) {
        $insights[] = [
            'tone' => 'success',
            'title' => 'Bu ürünün kanal operasyonu dengeli görünüyor.',
            'detail' => 'Fiyat, stok, eşleşme ve gönderim sinyalleri şu an sağlıklı.',
        ];
    }
@endphp

<div class="space-y-3 lg:space-y-4">
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 lg:gap-4">
        <div class="min-w-0 rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Tahmini net kâr</p>
            <p class="mt-2 text-xl font-semibold {{ $profitValue >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ $formatMoney($profitValue) }}</p>
            <p class="mt-1 text-sm text-slate-500">Marj %{{ number_format($profitMargin, 1, ',', '.') }} · Kom. {{ $formatMoney($commissionAmount) }}</p>
        </div>

        <div class="min-w-0 rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Başa baş fiyat</p>
            <p class="mt-2 text-xl font-semibold text-slate-900">{{ $breakEvenPrice !== null ? $formatMoney($breakEvenPrice) : '-' }}</p>
            <p class="mt-1 text-sm {{ ($priceBuffer ?? 0) >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                Fiyat tamponu {{ $priceBuffer !== null ? $formatMoney($priceBuffer) : '-' }}
            </p>
        </div>

        <div class="min-w-0 rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Kanal uyum skoru</p>
            <p class="mt-2 text-xl font-semibold text-slate-900">{{ $healthScore }}/100</p>
            <div class="mt-2 flex items-center gap-2">
                <x-zolm.status-badge :tone="$healthTone">{{ $healthLabel }}</x-zolm.status-badge>
                <span class="text-xs text-slate-500">{{ $activeListingCount }}/{{ $listingCount }} aktif</span>
            </div>
        </div>

        <div class="min-w-0 rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Senkron sağlık</p>
            <p class="mt-2 text-xl font-semibold text-slate-900">{{ $staleSyncCount }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ $staleSyncHours }}+ saat geciken listeleme</p>
            <p class="mt-1 text-xs {{ $failedPush24h > 0 ? 'text-rose-600' : 'text-slate-500' }}">24 saatte {{ $failedPush24h }} gönderim hatası</p>
        </div>
    </div>

    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Akıllı yorumlar</p>
                <p class="mt-2 text-sm text-slate-500">Sistem verilerinden türetilen ürün risk, fiyat ve operasyon sinyalleri.</p>
            </div>
            <div class="text-sm text-slate-500">
                Son listing sync:
                <span class="font-medium text-slate-900">{{ $latestListingSyncAt?->format('d.m.Y H:i') ?: 'Henüz yok' }}</span>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-1 lg:grid-cols-2 gap-3">
            @foreach($insights as $insight)
                <div class="rounded-[8px] border p-3 {{ $insight['tone'] === 'danger' ? 'border-rose-200 bg-rose-50' : ($insight['tone'] === 'warning' ? 'border-amber-200 bg-amber-50' : ($insight['tone'] === 'info' ? 'border-indigo-200 bg-indigo-50' : 'border-emerald-200 bg-emerald-50')) }}">
                    <p class="text-sm font-semibold {{ $insight['tone'] === 'danger' ? 'text-rose-700' : ($insight['tone'] === 'warning' ? 'text-amber-700' : ($insight['tone'] === 'info' ? 'text-indigo-700' : 'text-emerald-700')) }}">
                        {{ $insight['title'] }}
                    </p>
                    <p class="mt-1 text-sm {{ $insight['tone'] === 'danger' ? 'text-rose-700' : ($insight['tone'] === 'warning' ? 'text-amber-700' : ($insight['tone'] === 'info' ? 'text-indigo-700' : 'text-emerald-700')) }}">
                        {{ $insight['detail'] }}
                    </p>
                </div>
            @endforeach
        </div>
    </section>

    <div class="grid grid-cols-1 2xl:grid-cols-12 gap-3 lg:gap-4">
        <section class="min-w-0 2xl:col-span-4 rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Master ekonomi kartı</p>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex items-start justify-between gap-3">
                    <dt class="text-slate-500">Stok kodu</dt>
                    <dd class="text-right font-mono font-medium text-slate-900">{{ $product->stock_code ?: '-' }}</dd>
                </div>
                <div class="flex items-start justify-between gap-3">
                    <dt class="text-slate-500">Barkod</dt>
                    <dd class="text-right font-mono font-medium text-slate-900">{{ $product->barcode ?: '-' }}</dd>
                </div>
                <div class="flex items-start justify-between gap-3">
                    <dt class="text-slate-500">Satış fiyatı</dt>
                    <dd class="text-right font-medium text-slate-900">{{ $formatMoney($salePrice) }}</dd>
                </div>
                <div class="flex items-start justify-between gap-3">
                    <dt class="text-slate-500">Piyasa fiyatı</dt>
                    <dd class="text-right font-medium text-slate-900">{{ $formatMoney($marketPrice) }}</dd>
                </div>
                <div class="flex items-start justify-between gap-3">
                    <dt class="text-slate-500">Net (komisyon sonrası)</dt>
                    <dd class="text-right font-medium text-slate-900">{{ $formatMoney($netAfterCommission) }}</dd>
                </div>
                <div class="flex items-start justify-between gap-3">
                    <dt class="text-slate-500">Maliyet + Amb + Kargo</dt>
                    <dd class="text-right font-medium text-slate-900">{{ $formatMoney($totalUnitCost) }}</dd>
                </div>
                <div class="flex items-start justify-between gap-3">
                    <dt class="text-slate-500">KDV / Komisyon</dt>
                    <dd class="text-right font-medium text-slate-900">%{{ number_format($vatRate, 0, ',', '.') }} / %{{ number_format($commissionRate, 1, ',', '.') }}</dd>
                </div>
                <div class="flex items-start justify-between gap-3">
                    <dt class="text-slate-500">Lojistik</dt>
                    <dd class="text-right font-medium text-slate-900">{{ number_format((float) ($product->desi ?? 0), 1, ',', '.') }} desi · {{ (int) ($product->pieces ?? 1) }} adet</dd>
                </div>
                <div class="flex items-start justify-between gap-3">
                    <dt class="text-slate-500">Durum</dt>
                    <dd>
                        <x-zolm.status-badge :tone="$product->status === 'active' ? 'success' : ($product->status === 'out_of_stock' ? 'danger' : 'warning')">
                            {{ $product->status_label }}
                        </x-zolm.status-badge>
                    </dd>
                </div>
            </dl>
        </section>

        <section class="min-w-0 2xl:col-span-8 rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Kanal dağılım özeti</p>
            @if($marketplaceBreakdown->isNotEmpty())
                <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">
                    @foreach($marketplaceBreakdown as $item)
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-sm font-semibold text-slate-900">{{ $this->humanMarketplace($item['marketplace']) }}</p>
                                <span class="text-xs text-slate-500">{{ $item['active'] }}/{{ $item['total'] }} aktif</span>
                            </div>
                            <p class="mt-2 text-sm text-slate-500">
                                Sorun:
                                <span class="font-medium {{ $item['issues'] > 0 ? 'text-amber-600' : 'text-emerald-600' }}">
                                    {{ $item['issues'] }}
                                </span>
                            </p>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="mt-3 rounded-[8px] border border-dashed border-slate-300 bg-slate-50 px-3 py-3 text-sm text-slate-500">
                    Henüz bağlı kanal listeleme kaydı yok.
                </div>
            @endif

            <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                    <p class="text-[10px] uppercase tracking-[0.16em] text-slate-500">Fiyat sapması</p>
                    <p class="mt-2 text-base font-semibold {{ $priceMismatchCount > 0 ? 'text-amber-600' : 'text-emerald-600' }}">{{ $priceMismatchCount }}</p>
                    <p class="text-xs text-slate-500">Master fiyattan farklı listeleme</p>
                </div>
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                    <p class="text-[10px] uppercase tracking-[0.16em] text-slate-500">Stok sapması</p>
                    <p class="mt-2 text-base font-semibold {{ $stockMismatchCount > 0 ? 'text-amber-600' : 'text-emerald-600' }}">{{ $stockMismatchCount }}</p>
                    <p class="text-xs text-slate-500">Master stokla uyuşmayan</p>
                </div>
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                    <p class="text-[10px] uppercase tracking-[0.16em] text-slate-500">Açık sorun</p>
                    <p class="mt-2 text-base font-semibold {{ $pendingIssues > 0 ? 'text-amber-600' : 'text-emerald-600' }}">{{ $pendingIssues }}</p>
                    <p class="text-xs text-slate-500">Eşleşme bekleyen kayıt</p>
                </div>
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                    <p class="text-[10px] uppercase tracking-[0.16em] text-slate-500">Son gönderim</p>
                    <p class="mt-2 text-base font-semibold text-slate-900">{{ $latestPushAt ? \Illuminate\Support\Carbon::parse($latestPushAt)->format('d.m H:i') : '-' }}</p>
                    <p class="text-xs {{ $failedPush24h > 0 ? 'text-rose-600' : 'text-slate-500' }}">24 saatte {{ $failedPush24h }} hata</p>
                </div>
            </div>
        </section>
    </div>

    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
            <div class="min-w-0">
                <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Kanal listelemeleri</p>
                <h3 class="mt-1 text-lg font-semibold text-slate-900">{{ $listingCount }} bağlı listeleme</h3>
            </div>
            <div class="text-sm text-slate-500">
                Son listeleme senkronu:
                <span class="font-medium text-slate-900">{{ $latestListingSyncAt?->format('d.m.Y H:i') ?: 'Henüz yok' }}</span>
            </div>
        </div>

        @if($listings->isNotEmpty())
            <div class="mt-4 space-y-3">
                @foreach($listings as $listing)
                    @php
                        $store = $listing->store;
                        $channelProduct = $listing->channelProduct;
                        $issueItems = $listing->matchIssues ?? collect();
                        $pushRuns = ($listing->pushRuns ?? collect())->take(3);
                        $latestFailedPush = ($listing->pushRuns ?? collect())->firstWhere('status', 'failed');

                        $pricePushEnabled = (bool) data_get($store, 'syncProfile.price_push_enabled');
                        $stockPushEnabled = (bool) data_get($store, 'syncProfile.stock_push_enabled');
                        $storeActive = (bool) data_get($store, 'is_active', false);
                        $canPushPrice = $storeActive && $pricePushEnabled;
                        $canPushStock = $storeActive && $stockPushEnabled;

                        $listingPrice = (float) ($listing->sale_price ?? 0);
                        $priceDelta = $listingPrice - $salePrice;
                        $listingStock = (int) ($listing->stock_quantity ?? 0);
                        $stockDelta = $listingStock - $masterStock;

                        $syncAgeHours = $listing->last_synced_at ? $listing->last_synced_at->diffInHours(now()) : null;
                        $isStale = $syncAgeHours === null || $syncAgeHours > $staleSyncHours;
                        $pendingIssueCount = $issueItems->where('match_status', 'pending')->count();
                    @endphp

                    <article class="rounded-[10px] border border-slate-200 bg-slate-50/50 p-4">
                        <div class="flex flex-col xl:flex-row xl:items-start xl:justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="text-base font-semibold text-slate-900">{{ $store?->store_name ?: 'Mağaza adı yok' }}</p>
                                    <x-zolm.status-badge tone="default">{{ $this->humanMarketplace($store?->marketplace) }}</x-zolm.status-badge>
                                    <x-zolm.status-badge :tone="$this->listingStatusTone($listing->listing_status)">{{ $this->listingStatusLabel($listing->listing_status) }}</x-zolm.status-badge>
                                    @if($isStale)
                                        <x-zolm.status-badge tone="warning">Senkron gecikmiş</x-zolm.status-badge>
                                    @endif
                                </div>
                                <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
                                    <span>Mağaza kodu: {{ $store?->store_code ?: '-' }}</span>
                                    <span>Firma: {{ data_get($store, 'legalEntity.name') ?: '-' }}</span>
                                    <span>Listeleme ID: {{ $listing->listing_id ?: '-' }}</span>
                                </div>
                            </div>

                            @if(config('marketplace.features.listing_push_enabled', true))
                                <div class="flex flex-wrap items-center gap-2">
                                    <button type="button"
                                            wire:click="syncListingPrice({{ $listing->id }})"
                                            wire:loading.attr="disabled"
                                            wire:loading.class="cursor-wait opacity-60"
                                            wire:target="syncListingPrice({{ $listing->id }})"
                                            @disabled(!$canPushPrice)
                                            class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] border px-4 py-3 sm:py-2 text-sm font-medium transition {{ $canPushPrice ? 'border-slate-200 bg-white text-slate-700 hover:bg-slate-100' : 'border-slate-200 bg-slate-100 text-slate-400 cursor-not-allowed' }}">
                                        Fiyat gönder
                                    </button>

                                    <button type="button"
                                            wire:click="syncListingStock({{ $listing->id }})"
                                            wire:loading.attr="disabled"
                                            wire:loading.class="cursor-wait opacity-60"
                                            wire:target="syncListingStock({{ $listing->id }})"
                                            @disabled(!$canPushStock)
                                            class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] border px-4 py-3 sm:py-2 text-sm font-medium transition {{ $canPushStock ? 'border-slate-200 bg-white text-slate-700 hover:bg-slate-100' : 'border-slate-200 bg-slate-100 text-slate-400 cursor-not-allowed' }}">
                                        Stok gönder
                                    </button>
                                </div>
                            @endif
                        </div>

                        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 2xl:grid-cols-5 gap-3">
                            <div class="rounded-[8px] border border-slate-200 bg-white p-3">
                                <p class="text-[10px] uppercase tracking-[0.16em] text-slate-500">Kanal fiyatı</p>
                                <p class="mt-2 text-sm font-semibold text-slate-900">{{ $formatMoney($listingPrice) }}</p>
                                <p class="mt-1 text-xs {{ $priceDelta == 0.0 ? 'text-slate-500' : ($priceDelta > 0 ? 'text-emerald-600' : 'text-rose-600') }}">
                                    Master farkı {{ ($priceDelta > 0 ? '+' : '') . $formatMoney($priceDelta) }}
                                </p>
                            </div>

                            <div class="rounded-[8px] border border-slate-200 bg-white p-3">
                                <p class="text-[10px] uppercase tracking-[0.16em] text-slate-500">Kanal stoğu</p>
                                <p class="mt-2 text-sm font-semibold text-slate-900">{{ $formatCount($listingStock) }}</p>
                                <p class="mt-1 text-xs {{ $stockDelta === 0 ? 'text-slate-500' : ($stockDelta > 0 ? 'text-emerald-600' : 'text-amber-600') }}">
                                    Master farkı {{ $stockDelta > 0 ? '+' : '' }}{{ $formatCount($stockDelta) }}
                                </p>
                            </div>

                            <div class="rounded-[8px] border border-slate-200 bg-white p-3">
                                <p class="text-[10px] uppercase tracking-[0.16em] text-slate-500">Eşleşme kalitesi</p>
                                <p class="mt-2 text-sm font-semibold {{ $pendingIssueCount > 0 ? 'text-amber-600' : 'text-emerald-600' }}">{{ $pendingIssueCount }}</p>
                                <p class="mt-1 text-xs text-slate-500">Açık sorun sayısı</p>
                            </div>

                            <div class="rounded-[8px] border border-slate-200 bg-white p-3">
                                <p class="text-[10px] uppercase tracking-[0.16em] text-slate-500">Senkron yaşı</p>
                                <p class="mt-2 text-sm font-semibold {{ $isStale ? 'text-amber-600' : 'text-slate-900' }}">
                                    {{ $syncAgeHours !== null ? $syncAgeHours . 's' : '-' }}
                                </p>
                                <p class="mt-1 text-xs text-slate-500">{{ $listing->last_synced_at?->format('d.m.Y H:i') ?: 'Henüz yok' }}</p>
                            </div>

                            <div class="rounded-[8px] border border-slate-200 bg-white p-3">
                                <p class="text-[10px] uppercase tracking-[0.16em] text-slate-500">Gönderim sağlığı</p>
                                <div class="mt-2 flex flex-wrap items-center gap-1">
                                    <x-zolm.status-badge :tone="$pricePushEnabled ? 'success' : 'default'">F</x-zolm.status-badge>
                                    <x-zolm.status-badge :tone="$stockPushEnabled ? 'success' : 'default'">S</x-zolm.status-badge>
                                    @if($latestFailedPush)
                                        <x-zolm.status-badge tone="danger">Hata</x-zolm.status-badge>
                                    @endif
                                </div>
                                <p class="mt-1 text-xs text-slate-500">{{ $storeActive ? 'Mağaza aktif' : 'Mağaza pasif' }}</p>
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-1 2xl:grid-cols-2 gap-3">
                            <div class="min-w-0 rounded-[8px] border border-slate-200 bg-white p-3">
                                <p class="text-xs uppercase tracking-[0.16em] text-slate-500">Kanal ürün kimliği</p>
                                <dl class="mt-3 space-y-2 text-sm">
                                    <div class="flex items-start justify-between gap-3">
                                        <dt class="text-slate-500">Harici ürün</dt>
                                        <dd class="min-w-0 break-words text-right font-medium text-slate-900">{{ $channelProduct?->external_product_id ?: '-' }}</dd>
                                    </div>
                                    <div class="flex items-start justify-between gap-3">
                                        <dt class="text-slate-500">Kanal stok kodu</dt>
                                        <dd class="min-w-0 break-words text-right font-mono font-medium text-slate-900">{{ $channelProduct?->stock_code ?: '-' }}</dd>
                                    </div>
                                    <div class="flex items-start justify-between gap-3">
                                        <dt class="text-slate-500">Kanal barkodu</dt>
                                        <dd class="min-w-0 break-words text-right font-mono font-medium text-slate-900">{{ $channelProduct?->barcode ?: '-' }}</dd>
                                    </div>
                                    <div class="flex items-start justify-between gap-3">
                                        <dt class="text-slate-500">Başlık</dt>
                                        <dd class="min-w-0 break-words text-right font-medium text-slate-900">{{ $channelProduct?->title ?: '-' }}</dd>
                                    </div>
                                </dl>
                            </div>

                            <div class="min-w-0 rounded-[8px] border border-slate-200 bg-white p-3">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-xs uppercase tracking-[0.16em] text-slate-500">Gönderim geçmişi</p>
                                    @if(config('marketplace.features.listing_push_enabled', true) && $latestFailedPush)
                                        <button type="button"
                                                wire:click="retryPushRun({{ $latestFailedPush->id }})"
                                                wire:loading.attr="disabled"
                                                wire:loading.class="cursor-wait opacity-60"
                                                wire:target="retryPushRun({{ $latestFailedPush->id }})"
                                                class="inline-flex min-h-[36px] items-center justify-center rounded-[6px] border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-700 transition hover:bg-amber-100 disabled:cursor-not-allowed">
                                            Son hatayı yeniden dene
                                        </button>
                                    @endif
                                </div>

                                @if($pushRuns->isNotEmpty())
                                    <div class="mt-3 space-y-2">
                                        @foreach($pushRuns as $run)
                                            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-2">
                                                <div class="flex items-center justify-between gap-2">
                                                    <p class="text-xs font-semibold text-slate-900">{{ $run->push_type === 'price' ? 'Fiyat gönderimi' : 'Stok gönderimi' }}</p>
                                                    <x-zolm.status-badge :tone="match($run->status) {
                                                        'completed' => 'success',
                                                        'failed' => 'danger',
                                                        'retrying' => 'warning',
                                                        'processing', 'queued' => 'info',
                                                        default => 'default',
                                                    }">
                                                        {{ match($run->status) {
                                                            'completed' => 'Tamam',
                                                            'failed' => 'Hata',
                                                            'retrying' => 'Tekrar',
                                                            'processing' => 'İşleniyor',
                                                            'queued' => 'Sırada',
                                                            default => ucfirst((string) $run->status),
                                                        } }}
                                                    </x-zolm.status-badge>
                                                </div>
                                                <p class="mt-1 text-xs text-slate-500">
                                                    {{ $run->created_at?->format('d.m.Y H:i') ?: '-' }} · Deneme {{ $run->attempt_count ?? 0 }}
                                                </p>
                                                @if($run->error_message)
                                                    <p class="mt-1 text-xs text-rose-600 line-clamp-2">{{ $run->error_message }}</p>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="mt-3 rounded-[8px] border border-dashed border-slate-300 bg-slate-50 px-3 py-3 text-sm text-slate-500">
                                        Bu listeleme için henüz gönderim kaydı oluşmadı.
                                    </div>
                                @endif
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @else
            <div class="mt-4 rounded-[10px] border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-sm text-slate-500">
                Bu master ürüne bağlı kanal listeleme kaydı henüz oluşmamış. Ürün senkronu çalıştığında burada otomatik görünecek.
            </div>
        @endif
    </section>
</div>
