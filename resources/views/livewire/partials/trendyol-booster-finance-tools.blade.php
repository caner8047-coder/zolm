@php
    $financial = (array) ($financeDashboard['financial'] ?? []);
    $target = (array) ($financeDashboard['target'] ?? []);
    $money = fn (mixed $value): string => number_format((float) ($value ?? 0), 2, ',', '.') . ' TL';
    $percent = fn (mixed $value): string => '%' . number_format((float) ($value ?? 0), 1, ',', '.');
    $number = fn (mixed $value, int $decimals = 0): string => number_format((float) ($value ?? 0), $decimals, ',', '.');
    $commissionGross = round((float) $salePrice * ((float) $commissionRate / 100), 2);
    $grossTotalCost = round((float) $cogs + (float) $cargoCost + $commissionGross, 2);
    $grossProfit = round((float) $salePrice - $grossTotalCost, 2);
    $breakEvenGross = (100 - (float) $commissionRate) > 0
        ? round(((float) $cogs + (float) $cargoCost) / (1 - ((float) $commissionRate / 100)), 2)
        : 0;
    $mode = match ($activeModule) {
        'gross_profit' => [
            'eyebrow' => 'Brüt hesaplama girdileri',
            'title' => 'Vergi ve KDV ayrıştırması olmadan hızlı ürün kârlılığı.',
            'icon' => 'banknote',
        ],
        'net_profit' => [
            'eyebrow' => 'Hesaplama detayları',
            'title' => 'Sadece tutarları girin; komisyon, KDV, stopaj ve gelir vergisi dahil net kârınızı görün.',
            'icon' => 'badge-check',
        ],
        'target_planner' => [
            'eyebrow' => 'Hedef bilgileri',
            'title' => 'Ciro hedefi, hedef net marj ve ortalama satış fiyatından sipariş planı çıkarın.',
            'icon' => 'radar',
        ],
        default => [
            'eyebrow' => 'Hesaplama detayları',
            'title' => 'Satış, alış, komisyon, kargo, KDV, stopaj ve gelir vergisini girin; net kalan kârı hesaplayın.',
            'icon' => 'gauge',
        ],
    };
    $commissionRecommendation = (array) data_get($costRecommendation, 'commission', []);
    $sellerMetrics = (array) data_get($costRecommendation, 'commission.seller_metrics', []);
    $desiRecommendation = (array) data_get($costRecommendation, 'desi', []);
    $shippingRecommendation = (array) data_get($costRecommendation, 'shipping', []);
    $costWarnings = (array) data_get($costRecommendation, 'warnings', []);
@endphp

<div
    x-data="{
        extensionReady: false,
        extensionMessage: '',
        busy: false,
        requestId: '',
        init() {
            window.postMessage({ source: 'zolm-booster-page', type: 'PING' }, window.location.origin);
        },
        fetchLivePrice(wire) {
            const url = this.$refs.financeProductUrl?.value.trim() || '';
            if (!url) {
                wire.fetchProductFromUrl();
                return;
            }
            if (!this.extensionReady) {
                this.extensionMessage = 'Chrome Companion bağlı değil; sunucu okuyucusu çalıştırılıyor.';
                wire.fetchProductFromUrl();
                return;
            }

            this.busy = true;
            this.extensionMessage = '';
            this.requestId = `${Date.now()}-${Math.random().toString(16).slice(2)}`;
            window.postMessage({
                source: 'zolm-booster-page',
                type: 'PRODUCT_ANALYSIS_QUERY',
                request_id: this.requestId,
                source_url: url,
            }, window.location.origin);

            window.setTimeout(() => {
                if (!this.busy) return;
                this.busy = false;
                this.requestId = '';
                this.extensionMessage = 'Chrome Companion yanıt vermedi; sunucu okuyucusu çalıştırılıyor.';
                wire.fetchProductFromUrl();
            }, 45000);
        },
        receive(event, wire) {
            const data = event.data || {};
            if (event.origin !== window.location.origin || data.source !== 'zolm-booster-extension') return;
            if (data.type === 'READY') {
                this.extensionReady = true;
                this.extensionMessage = '';
                return;
            }
            if (data.type === 'BRIDGE_ERROR') {
                this.extensionReady = false;
                this.extensionMessage = data.message || 'Chrome Companion köprüsü yanıt vermedi.';
                return;
            }
            if (data.type !== 'PRODUCT_ANALYSIS_QUERY_RESULT' || data.request_id !== this.requestId) return;

            this.busy = false;
            this.requestId = '';
            this.extensionReady = true;
            this.extensionMessage = '';
            const response = data.response || {};
            if (!response.ok) {
                this.extensionMessage = response.message || 'Chrome Companion yanıtı başarısız oldu; sunucu okuyucusu kullanılıyor.';
                wire.fetchProductFromUrl();
                return;
            }
            wire.financeProductBridgeCompleted(
                response.analysis?.tracked_product_id || null,
                response.analysis?.current?.sale_price || null,
                response.message || 'Canlı ürün fiyatı okundu.',
                Boolean(response.ok),
            );
        },
    }"
    x-on:message.window="receive($event, $wire)"
    class="space-y-4 lg:space-y-6"
    data-testid="booster-finance-tools"
>
    @if($activeModule !== 'target_planner')
        @if($activeModule !== 'gross_profit')
            <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
                    <label class="min-w-0 flex-1">
                        <span class="text-xs font-medium text-slate-600">Ürün Linki (Opsiyonel)</span>
                        <input x-ref="financeProductUrl" type="url" wire:model.defer="productUrl" placeholder="Fiyatını otomatik almak istediğiniz ürün linkini yapıştırın"
                               class="mt-1.5 min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none focus:border-slate-400 sm:py-2 sm:text-sm">
                        <span class="mt-1 block text-xs text-slate-500">Ürün linki eklediğinizde KDV dahil satış fiyatı otomatik doldurulur.</span>
                        @error('productUrl') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                    </label>
                    <button type="button" x-on:click="fetchLivePrice($wire)" x-bind:disabled="busy" wire:loading.attr="disabled" wire:target="fetchProductFromUrl,financeProductBridgeCompleted"
                            class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] bg-slate-900 px-4 py-3 text-base font-medium text-white transition hover:bg-slate-800 disabled:opacity-60 sm:w-auto sm:py-2 sm:text-sm">
                        <x-lucide.icon name="refresh-cw" class="h-4 w-4" />
                        <span x-show="!busy" wire:loading.remove wire:target="fetchProductFromUrl,financeProductBridgeCompleted">Getir</span>
                        <span x-show="busy" x-cloak>Canlı fiyat okunuyor...</span>
                        <span wire:loading wire:target="fetchProductFromUrl,financeProductBridgeCompleted">Alınıyor...</span>
                    </button>
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                    <span x-show="extensionReady" x-cloak class="rounded-[6px] border border-emerald-200 bg-emerald-50 px-2 py-1 font-medium text-emerald-700">Chrome Companion hazır · canlı fiyat okunacak</span>
                    <span x-show="!extensionReady" class="rounded-[6px] border border-amber-200 bg-amber-50 px-2 py-1 text-amber-700">Companion bulunamazsa sunucu okuyucusu kullanılır</span>
                    <span x-show="extensionMessage" x-cloak x-text="extensionMessage" class="rounded-[6px] border border-rose-200 bg-rose-50 px-2 py-1 text-rose-700"></span>
                </div>
            </section>
        @endif

        @if($activeModule !== 'gross_profit' && !empty($costRecommendation))
            <section class="overflow-hidden rounded-[10px] border border-slate-200 bg-white shadow-sm" data-testid="booster-cost-recommendation">
                <div class="flex flex-col gap-2 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between lg:px-6">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase text-slate-500">Otomatik maliyet önerisi</p>
                        <h2 class="mt-1 text-lg font-semibold text-slate-900">Komisyon, desi ve kargo tahmini</h2>
                    </div>
                    <span class="w-fit rounded-[6px] border border-sky-200 bg-sky-50 px-2 py-1 font-mono text-xs text-sky-700">
                        Genel güven %{{ $number(data_get($costRecommendation, 'overall_confidence', 0), 0) }}
                    </span>
                </div>

                <div class="grid grid-cols-1 divide-y divide-slate-200 sm:grid-cols-2 sm:divide-x sm:divide-y-0 xl:grid-cols-3">
                    <div class="min-w-0 p-4 lg:p-6">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-xs font-semibold uppercase text-slate-500">Komisyon</p>
                            <span class="rounded-[6px] bg-slate-100 px-2 py-0.5 font-mono text-xs text-slate-600">Güven %{{ $number($commissionRecommendation['confidence'] ?? 0, 0) }}</span>
                        </div>
                        <p class="mt-2 text-xl font-semibold text-slate-900">{{ $percent($commissionRecommendation['rate'] ?? 0) }}</p>
                        <p class="mt-1 text-xs leading-5 text-slate-500">{{ $commissionRecommendation['source_label'] ?? 'Kaynak bulunamadı' }}</p>
                        <p class="mt-2 text-xs text-slate-600">
                            Satıcı seviyesi:
                            <span class="font-semibold text-slate-900">
                                {{ $commissionRecommendation['seller_level'] ? 'Seviye '.$commissionRecommendation['seller_level'] : 'Bilinmiyor' }}
                            </span>
                            @if(($commissionRecommendation['seller_level_source'] ?? '') === 'connected_store_180d')
                                <span class="text-emerald-700">(180 günlük mağaza verisi)</span>
                            @elseif(($commissionRecommendation['seller_level_source'] ?? '') === 'page')
                                <span class="text-sky-700">(Trendyol verisi)</span>
                            @endif
                        </p>
                        @if(!empty($sellerMetrics['revenue_180d']) || !empty($sellerMetrics['orders_180d']))
                            <div class="mt-2 rounded-[6px] border border-slate-200 bg-slate-50/60 p-2 text-xs leading-5 text-slate-600">
                                <p>180 gün: <span class="font-mono font-medium text-slate-900">{{ $money($sellerMetrics['revenue_180d'] ?? 0) }}</span> net ciro · <span class="font-mono font-medium text-slate-900">{{ $number($sellerMetrics['orders_180d'] ?? 0) }}</span> net sipariş</p>
                                <p>Son 30 gün: <span class="font-mono font-medium text-slate-900">{{ $number($sellerMetrics['orders_30d'] ?? 0) }}</span> net sipariş</p>
                                @if(!empty($sellerMetrics['category_threshold_name']))
                                    <p>Kategori baremi: <span class="font-medium text-slate-900">{{ $sellerMetrics['category_threshold_name'] }}</span></p>
                                @endif
                            </div>
                        @elseif(!empty($commissionRecommendation['seller_level_note']))
                            <p class="mt-2 text-xs leading-5 text-slate-500">{{ $commissionRecommendation['seller_level_note'] }}</p>
                        @endif
                        @if(!empty($commissionRecommendation['matched_product_group']))
                            <p class="mt-1 break-words text-xs text-slate-500">{{ $commissionRecommendation['matched_product_group'] }}</p>
                        @endif
                    </div>

                    <div class="min-w-0 p-4 lg:p-6">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-xs font-semibold uppercase text-slate-500">Desi</p>
                            <span class="rounded-[6px] bg-slate-100 px-2 py-0.5 font-mono text-xs text-slate-600">Güven %{{ $number($desiRecommendation['confidence'] ?? 0, 0) }}</span>
                        </div>
                        <p class="mt-2 text-xl font-semibold text-slate-900">{{ $number($desiRecommendation['billable_desi'] ?? 0) }} desi</p>
                        <p class="mt-1 text-xs leading-5 text-slate-500">{{ $desiRecommendation['source_label'] ?? 'Kaynak bulunamadı' }}</p>
                        @if(!empty($desiRecommendation['dimensions']))
                            <p class="mt-2 font-mono text-xs text-slate-600">
                                {{ $number(data_get($desiRecommendation, 'dimensions.width_cm', 0), 1) }} ×
                                {{ $number(data_get($desiRecommendation, 'dimensions.length_cm', 0), 1) }} ×
                                {{ $number(data_get($desiRecommendation, 'dimensions.height_cm', 0), 1) }} cm
                            </p>
                        @endif
                    </div>

                    <div class="min-w-0 p-4 sm:col-span-2 xl:col-span-1 lg:p-6">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-xs font-semibold uppercase text-slate-500">Kargo</p>
                            <span class="rounded-[6px] bg-slate-100 px-2 py-0.5 font-mono text-xs text-slate-600">Güven %{{ $number($shippingRecommendation['confidence'] ?? 0, 0) }}</span>
                        </div>
                        <p class="mt-2 text-xl font-semibold text-slate-900">{{ $money($shippingRecommendation['cost_gross'] ?? 0) }}</p>
                        <p class="mt-1 text-xs leading-5 text-slate-500">
                            {{ $shippingRecommendation['cargo_company'] ?? '-' }} · KDV dahil · {{ $shippingRecommendation['source_label'] ?? 'Tarife yok' }}
                        </p>
                        <p class="mt-2 text-xs text-slate-600">KDV hariç: {{ $money($shippingRecommendation['cost_net'] ?? 0) }}</p>
                    </div>
                </div>

                @if(!empty($shippingRecommendation['scenarios']) || !empty($commissionRecommendation['alternatives']))
                    <div class="flex flex-col gap-3 border-t border-slate-200 bg-slate-50/60 p-4 lg:flex-row lg:items-center lg:justify-between lg:px-6">
                        @if(!empty($shippingRecommendation['scenarios']))
                            <div class="min-w-0">
                                <p class="mb-2 text-xs font-medium text-slate-500">Kargo senaryosu</p>
                                <div class="inline-flex w-full overflow-hidden rounded-[6px] border border-slate-200 bg-white sm:w-auto">
                                    @foreach($shippingRecommendation['scenarios'] as $scenario)
                                        @php $scenarioLabel = ['low' => 'Düşük', 'base' => 'Beklenen', 'high' => 'Yüksek'][$scenario['key']] ?? $scenario['key']; @endphp
                                        <button type="button" wire:click="applyCostScenario('{{ $scenario['key'] }}')"
                                                class="min-h-[44px] flex-1 border-r border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 last:border-r-0 hover:bg-slate-100 sm:flex-none">
                                            {{ $scenarioLabel }} · {{ $scenario['desi'] }}D
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if(!empty($commissionRecommendation['alternatives']))
                            <div class="min-w-0 lg:text-right">
                                <p class="mb-2 text-xs font-medium text-slate-500">Satıcı seviye senaryosu</p>
                                <div class="inline-flex w-full overflow-hidden rounded-[6px] border border-slate-200 bg-white sm:w-auto">
                                    @foreach($commissionRecommendation['alternatives'] as $alternative)
                                        <button type="button" wire:click="applyCommissionLevel({{ $alternative['level'] }})"
                                                class="min-h-[44px] flex-1 border-r border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 last:border-r-0 hover:bg-slate-100 sm:flex-none">
                                            S{{ $alternative['level'] }} · {{ $percent($alternative['rate']) }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endif

                @if(!empty($costWarnings))
                    <div class="border-t border-amber-200 bg-amber-50 px-4 py-3 lg:px-6">
                        @foreach($costWarnings as $warning)
                            <p class="text-xs leading-5 text-amber-800">{{ $warning }}</p>
                        @endforeach
                    </div>
                @endif
            </section>
        @endif

        <section class="overflow-hidden rounded-[10px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 p-4 lg:p-6">
                <div class="flex items-center gap-2">
                    <x-lucide.icon name="{{ $mode['icon'] }}" class="h-4 w-4 text-slate-500" />
                    <p class="text-xs font-semibold uppercase text-slate-500">{{ $mode['eyebrow'] }}</p>
                </div>
                <h2 class="mt-1 text-lg font-semibold text-slate-900">{{ $mode['title'] }}</h2>
            </div>

            <div class="grid grid-cols-1 gap-3 p-4 sm:grid-cols-2 xl:grid-cols-4 lg:gap-4 lg:p-6">
                @foreach([
                    ['salePrice', $activeModule === 'gross_profit' ? 'Satış Fiyatı (TL)' : 'KDV Dahil Satış Fiyatı (TL)', 'TL'],
                    ['cogs', $activeModule === 'gross_profit' ? 'Alış Fiyatı (TL)' : 'KDV Dahil Alış Fiyatı (TL)', 'TL'],
                    ['commissionRate', 'Komisyon Oranı (%)', '%'],
                    ['cargoCost', $activeModule === 'gross_profit' ? 'Kargo Fiyatı (TL)' : 'KDV Dahil Kargo (TL)', 'TL'],
                ] as [$model, $label, $suffix])
                    <label class="min-w-0">
                        <span class="text-xs font-medium text-slate-600">{{ $label }}</span>
                        <div class="relative mt-1.5">
                            <input type="number" min="0" step="0.01" wire:model.live.debounce.250ms="{{ $model }}"
                                   placeholder="Değer girin"
                                   class="min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 pr-10 text-base text-slate-900 outline-none focus:border-slate-400 sm:py-2 sm:text-sm">
                            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-slate-400">{{ $suffix }}</span>
                        </div>
                    </label>
                @endforeach

                @if($activeModule !== 'gross_profit')
                    <label class="min-w-0">
                        <span class="text-xs font-medium text-slate-600">KDV Oranı (%)</span>
                        <select wire:model.live="vatRate" class="mt-1.5 min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none focus:border-slate-400 sm:py-2 sm:text-sm">
                            @foreach([0, 1, 10, 20] as $rate)
                                <option value="{{ $rate }}">%{{ $rate }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="min-w-0">
                        <span class="text-xs font-medium text-slate-600">E-Ticaret Stopaj Oranı (%)</span>
                        <input type="number" min="0" step="0.01" wire:model.live.debounce.250ms="withholdingRate" placeholder="Değer girin"
                               class="mt-1.5 min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none focus:border-slate-400 sm:py-2 sm:text-sm">
                    </label>
                    <label class="min-w-0">
                        <span class="text-xs font-medium text-slate-600">Gelir Vergisi Oranı (%)</span>
                        <input type="number" min="0" step="0.01" wire:model.live.debounce.250ms="incomeTaxRate" placeholder="Değer girin"
                               class="mt-1.5 min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none focus:border-slate-400 sm:py-2 sm:text-sm">
                    </label>
                @endif
            </div>
        </section>

        @if($activeModule === 'gross_profit')
            <section class="grid grid-cols-1 gap-4 lg:grid-cols-2 lg:gap-6">
                <div class="overflow-hidden rounded-[10px] border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 p-4 lg:px-6">
                        <p class="text-xs font-semibold uppercase text-slate-500">Maliyet dağılımı</p>
                    </div>
                    <div class="divide-y divide-slate-100 px-4 lg:px-6">
                        @foreach([
                            ['Alış Maliyeti', (float) $cogs],
                            ['Kargo Maliyeti', (float) $cargoCost],
                            ['Komisyon Tutarı (' . $percent($commissionRate) . ')', $commissionGross],
                            ['Toplam Brüt Maliyet', $grossTotalCost],
                            ['Başabaş Satış', $breakEvenGross],
                            ['Satış Geliri', (float) $salePrice],
                        ] as [$label, $value])
                            <div class="flex items-center justify-between gap-4 py-3 text-sm">
                                <span class="text-slate-600">{{ $label }}</span>
                                <span class="shrink-0 font-mono font-medium text-slate-900">{{ $money($value) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="overflow-hidden rounded-[10px] border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 p-4 lg:px-6">
                        <p class="text-xs font-semibold uppercase text-slate-500">Brüt sonuç</p>
                        <h2 class="mt-1 text-lg font-semibold {{ $grossProfit >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $grossProfit > 0 ? '+' : '' }}{{ $money($grossProfit) }}</h2>
                    </div>
                    <div class="grid grid-cols-1 gap-3 p-4 sm:grid-cols-3 lg:p-6">
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3"><p class="text-xs text-slate-500">Satıştan marj</p><p class="mt-1 font-semibold text-slate-900">{{ $salePrice > 0 ? $percent(($grossProfit / (float) $salePrice) * 100) : '%0,0' }}</p></div>
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3"><p class="text-xs text-slate-500">Alıştan Kâr/Zarar</p><p class="mt-1 font-semibold text-slate-900">{{ $cogs > 0 ? $percent(($grossProfit / (float) $cogs) * 100) : '%0,0' }}</p></div>
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3"><p class="text-xs text-slate-500">Toplam maliyete göre</p><p class="mt-1 font-semibold text-slate-900">{{ $grossTotalCost > 0 ? $percent(($grossProfit / $grossTotalCost) * 100) : '%0,0' }}</p></div>
                    </div>
                    <div class="divide-y divide-slate-100 border-t border-slate-200 px-4 lg:px-6">
                        @foreach([
                            ['Satış Fiyatı', (float) $salePrice],
                            ['Alış Fiyatı', -(float) $cogs],
                            ['Kargo', -(float) $cargoCost],
                            ['Komisyon (' . $percent($commissionRate) . ')', -$commissionGross],
                            ['Brüt Kâr/Zarar', $grossProfit],
                        ] as [$label, $value])
                            <div class="flex items-center justify-between gap-4 py-3 text-sm {{ $label === 'Brüt Kâr/Zarar' ? 'font-semibold' : '' }}">
                                <span class="text-slate-600">{{ $label }}</span>
                                <span class="shrink-0 font-mono {{ $value < 0 ? 'text-rose-700' : ($label === 'Brüt Kâr/Zarar' ? 'text-emerald-700' : 'text-slate-900') }}">{{ $money($value) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @else
            <section class="overflow-hidden rounded-[10px] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 p-4 lg:px-6">
                    <p class="text-xs font-semibold uppercase text-slate-500">Detaylı döküm</p>
                    <h2 class="mt-1 text-xl font-semibold {{ (float) ($financial['net_profit'] ?? 0) >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                        Net Kâr {{ (float) ($financial['net_profit'] ?? 0) > 0 ? '+' : '' }}{{ $money($financial['net_profit'] ?? 0) }}
                    </h2>
                    <p class="mt-1 text-xs text-slate-500">Satışa marj: {{ $percent($financial['profit_margin_percent'] ?? 0) }} · Maliyete marj: {{ $percent($financial['roi_percent'] ?? 0) }}</p>
                </div>
                <div class="divide-y divide-slate-100 px-4 lg:px-6">
                    @foreach([
                        ['Satış Fiyatı (KDV Dahil)', $financial['sale_gross'] ?? 0, 'KDV hariç: ' . $money($financial['sale_net'] ?? 0) . ' | KDV (' . $percent($vatRate) . '): ' . $money($financial['sales_vat'] ?? 0), 'income'],
                        ['Alış Maliyeti', -($financial['cogs_gross'] ?? 0), $money($financial['product_cost_net'] ?? 0) . ' + ' . $money($financial['purchase_vat_credit'] ?? 0) . ' KDV', 'cost'],
                        ['Komisyon (' . $percent($commissionRate) . ')', -($financial['commission_gross'] ?? 0), $money($financial['commission_net'] ?? 0) . ' + ' . $money($financial['commission_vat_credit'] ?? 0) . ' KDV', 'cost'],
                        ['Kargo', -($financial['cargo_gross'] ?? 0), $money($financial['cargo_net'] ?? 0) . ' + ' . $money($financial['cargo_vat_credit'] ?? 0) . ' KDV', 'cost'],
                        ['E-Ticaret Stopajı (' . $percent($withholdingRate) . ')', -($financial['withholding'] ?? 0), 'Gelir vergisinden mahsup edilir (peşin ödeme)', 'info'],
                        ['KDV Ödenecek', -($financial['payable_vat'] ?? 0), $money($financial['sales_vat'] ?? 0) . ' - ' . $money($financial['input_vat_credit'] ?? 0) . ' indirim', 'tax'],
                        ['Gelir Vergisi (' . $percent($incomeTaxRate) . ')', -($financial['income_tax'] ?? 0), 'Vergiye esas kâr: ' . $money($financial['taxable_profit'] ?? 0), 'tax'],
                        ['Net Kâr', $financial['net_profit'] ?? 0, 'Stopaj net kârdan ikinci kez düşülmez.', 'total'],
                    ] as [$label, $value, $note, $tone])
                        <div class="flex flex-col gap-1 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="text-sm font-medium {{ $tone === 'total' ? 'text-slate-950' : 'text-slate-700' }}">{{ $label }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">{{ $note }}</p>
                            </div>
                            <span class="shrink-0 font-mono text-sm font-semibold {{ $value < 0 ? 'text-rose-700' : ($tone === 'total' ? 'text-emerald-700' : 'text-slate-900') }}">{{ $money($value) }}</span>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    @else
        <section class="overflow-hidden rounded-[10px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 p-4 lg:p-6">
                <div class="flex items-center gap-2">
                    <x-lucide.icon name="radar" class="h-4 w-4 text-slate-500" />
                    <p class="text-xs font-semibold uppercase text-slate-500">Hedef bilgileri</p>
                </div>
                <h2 class="mt-1 text-lg font-semibold text-slate-900">Ciro ve kâr hedefinizi, ortalama satış fiyatını ve komisyon oranını girin.</h2>
            </div>

            <div class="grid grid-cols-1 gap-3 p-4 sm:grid-cols-2 xl:grid-cols-3 lg:gap-4 lg:p-6">
                @foreach([
                    ['targetRevenue', 'Aylık Ciro Hedefi (TL)', 'TL'],
                    ['targetMarginPercent', 'Hedef Net Kâr Marjı (%)', '%'],
                    ['salePrice', 'Ortalama Satış Fiyatı (TL, KDV dahil)', 'TL'],
                    ['commissionRate', 'Ortalama Komisyon (%)', '%'],
                    ['cargoCost', 'Ortalama Kargo (TL, KDV dahil)', 'TL'],
                    ['targetPeriodDays', 'Aylık Satış Günü', 'gün'],
                ] as [$model, $label, $suffix])
                    <label class="min-w-0">
                        <span class="text-xs font-medium text-slate-600">{{ $label }}</span>
                        <div class="relative mt-1.5">
                            <input type="number" min="0" step="0.01" wire:model.live.debounce.250ms="{{ $model }}" placeholder="Değer girin"
                                   class="min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 pr-10 text-base text-slate-900 outline-none focus:border-slate-400 sm:py-2 sm:text-sm">
                            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-slate-400">{{ $suffix }}</span>
                        </div>
                    </label>
                @endforeach
            </div>
        </section>

        <section class="overflow-hidden rounded-[10px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 p-4 lg:p-6">
                <p class="text-xs font-semibold uppercase text-slate-500">Vergi varsayımları</p>
                <p class="mt-1 text-sm text-slate-500">Bu değerler net kâr ve gerekli alış maliyetini hesaplamak için arka planda kullanılır.</p>
            </div>
            <div class="grid grid-cols-1 gap-3 p-4 sm:grid-cols-3 lg:p-6">
                <label>
                    <span class="text-xs font-medium text-slate-600">KDV Oranı (%)</span>
                    <select wire:model.live="vatRate" class="mt-1.5 min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none focus:border-slate-400 sm:py-2 sm:text-sm">
                        @foreach([0, 1, 10, 20] as $rate)
                            <option value="{{ $rate }}">%{{ $rate }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span class="text-xs font-medium text-slate-600">E-Ticaret Stopajı (%)</span>
                    <input type="number" min="0" step="0.01" wire:model.live.debounce.250ms="withholdingRate"
                           class="mt-1.5 min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none focus:border-slate-400 sm:py-2 sm:text-sm">
                </label>
                <label>
                    <span class="text-xs font-medium text-slate-600">Gelir Vergisi (%)</span>
                    <input type="number" min="0" step="0.01" wire:model.live.debounce.250ms="incomeTaxRate"
                           class="mt-1.5 min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none focus:border-slate-400 sm:py-2 sm:text-sm">
                </label>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-4 xl:grid-cols-2 lg:gap-6">
            <div class="overflow-hidden rounded-[10px] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 p-4 lg:px-6">
                    <p class="text-xs font-semibold uppercase text-slate-500">Aylık toplam tablosu</p>
                </div>
                <div class="divide-y divide-slate-100 px-4 lg:px-6">
                    @foreach([
                        ['Toplam Ciro', $target['target_revenue'] ?? 0],
                        ['Toplam Alış Maliyeti', $target['total_purchase_cost'] ?? 0],
                        ['Toplam Komisyon', $target['total_commission'] ?? 0],
                        ['Toplam Kargo', $target['total_cargo'] ?? 0],
                        ['Toplam Ödenecek KDV', $target['total_payable_vat'] ?? 0],
                        ['Toplam Gelir Vergisi', $target['total_income_tax'] ?? 0],
                        ['Toplam Stopaj (peşin vergi)', $target['total_withholding'] ?? 0],
                        ['Toplam Net Kâr', $target['total_net_profit'] ?? 0],
                    ] as [$label, $value])
                        <div class="flex items-center justify-between gap-4 py-3 text-sm">
                            <span class="text-slate-600">{{ $label }}</span>
                            <span class="shrink-0 font-mono font-medium text-slate-900">{{ $money($value) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="overflow-hidden rounded-[10px] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 p-4 lg:px-6">
                    <p class="text-xs font-semibold uppercase text-slate-500">Hedef planı</p>
                    <h2 class="mt-1 text-xl font-semibold text-emerald-700">Aylık Net Kâr +{{ $money($target['target_profit'] ?? 0) }}</h2>
                    <p class="mt-1 text-xs text-slate-500">Hedef marj: {{ $percent($target['target_margin_percent'] ?? 0) }} · Birim net kâr: {{ $money($target['required_unit_profit'] ?? 0) }}</p>
                </div>
                <div class="grid grid-cols-1 gap-3 p-4 sm:grid-cols-3 lg:p-6">
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3"><p class="text-xs text-slate-500">Aylık Sipariş</p><p class="mt-1 text-lg font-semibold text-slate-900">{{ $number($target['planned_units'] ?? 0) }} adet</p></div>
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3"><p class="text-xs text-slate-500">Haftalık Sipariş</p><p class="mt-1 text-lg font-semibold text-slate-900">{{ $number($target['weekly_units'] ?? 0, 1) }} adet</p></div>
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3"><p class="text-xs text-slate-500">Günlük Sipariş</p><p class="mt-1 text-lg font-semibold text-slate-900">{{ $number($target['daily_units'] ?? 0, 2) }} adet</p></div>
                </div>
                <div class="divide-y divide-slate-100 border-t border-slate-200 px-4 lg:px-6">
                    @foreach([
                        ['Gerekli Maks. Alış Fiyatı', $target['max_purchase_gross'] ?? 0, 'Bu tutarın altına alırsanız hedef marjı aşarsınız.'],
                        ['Birim Komisyon', $target['unit_financial']['commission_gross'] ?? 0, $percent($commissionRate) . ' komisyon'],
                        ['Birim KDV Ödenecek', $target['unit_financial']['payable_vat'] ?? 0, 'Satış KDV - indirim KDV'],
                        ['Birim Gelir Vergisi', $target['unit_financial']['income_tax'] ?? 0, 'Vergiye esas birim kâr: ' . $money($target['unit_financial']['taxable_profit'] ?? 0)],
                    ] as [$label, $value, $note])
                        <div class="flex flex-col gap-1 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-slate-700">{{ $label }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">{{ $note }}</p>
                            </div>
                            <span class="shrink-0 font-mono text-sm font-semibold text-slate-900">{{ $money($value) }}</span>
                        </div>
                    @endforeach
                </div>
                @if(($target['status'] ?? '') === 'unreachable')
                    <div class="border-t border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 lg:px-6">
                        Hedef bu parametrelerle ulaşılamaz. Satış fiyatını artırın, komisyonu/kargo maliyetini düşürün veya hedef marjı azaltın.
                    </div>
                @elseif(($target['status'] ?? '') === 'missing_input')
                    <div class="border-t border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-500 lg:px-6">
                        Hedef planı için ciro hedefi ve ortalama satış fiyatını girin.
                    </div>
                @endif
            </div>
        </section>
    @endif

    @if($activeModule !== 'gross_profit')
        <details class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
            <summary class="cursor-pointer text-sm font-semibold text-slate-900">Hesaplama Nasıl Yapılıyor?</summary>
            <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                @foreach([
                    ['KDV Ayrıştırması', 'Girilen tutarlar KDV dahil kabul edilir; oranına göre net tutar ve KDV payı ayrılır.'],
                    ['Komisyon', 'Komisyon KDV dahil satış fiyatı üzerinden hesaplanır ve komisyon KDV payı indirime konu edilir.'],
                    ['Ödenecek KDV', 'Satış KDV tutarından alış, komisyon ve kargo indirim KDV toplamı düşülür; negatifse sıfır kabul edilir.'],
                    ['Stopaj Notu', 'Stopaj peşin vergi olarak izlenir; net kârdan ikinci kez düşülmez.'],
                    ['Gelir Vergisi', 'Vergiye esas kâr üzerinden seçilen gelir vergisi oranı uygulanır.'],
                    ['Net Kâr', 'Satıştan alış, komisyon, kargo, ödenecek KDV ve gelir vergisi düşülür.'],
                ] as [$title, $description])
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                        <p class="text-sm font-semibold text-slate-900">{{ $title }}</p>
                        <p class="mt-1 text-xs leading-5 text-slate-500">{{ $description }}</p>
                    </div>
                @endforeach
            </div>
        </details>
    @endif
</div>
