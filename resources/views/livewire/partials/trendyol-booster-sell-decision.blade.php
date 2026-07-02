@php
    $result = $sellDecisionResult;
    $hasResult = !empty($result);
    $decisionKey = (string) data_get($result, 'decision', 'wait');
    $decisionMeta = match ($decisionKey) {
        'sell' => [
            'label' => 'Evet, sat',
            'tone' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
            'panel' => 'border-emerald-200 bg-emerald-50/60',
            'bar' => 'bg-emerald-500',
            'icon' => 'check-circle',
        ],
        'test' => [
            'label' => 'Test et',
            'tone' => 'border-sky-200 bg-sky-50 text-sky-700',
            'panel' => 'border-sky-200 bg-sky-50/60',
            'bar' => 'bg-sky-500',
            'icon' => 'radar',
        ],
        'avoid' => [
            'label' => 'Hayır, satma',
            'tone' => 'border-rose-200 bg-rose-50 text-rose-700',
            'panel' => 'border-rose-200 bg-rose-50/60',
            'bar' => 'bg-rose-500',
            'icon' => 'x-circle',
        ],
        default => [
            'label' => 'Bekle, veri topla',
            'tone' => 'border-amber-200 bg-amber-50 text-amber-700',
            'panel' => 'border-amber-200 bg-amber-50/60',
            'bar' => 'bg-amber-500',
            'icon' => 'clock',
        ],
    };
    $score = (int) data_get($result, 'score', 0);
    $confidence = (int) data_get($result, 'confidence', 0);
    $financial = (array) data_get($result, 'financial', []);
    $velocity = (array) data_get($result, 'velocity', []);
    $market = (array) data_get($result, 'market', []);
    $product = (array) data_get($result, 'product', []);
    $money = fn (mixed $value): string => $value === null ? '-' : number_format((float) $value, 2, ',', '.') . ' TL';
    $number = fn (mixed $value, int $decimals = 2): string => $value === null ? '-' : number_format((float) $value, $decimals, ',', '.');
    $percent = fn (mixed $value): string => $value === null ? '-' : '%' . number_format((float) $value, 1, ',', '.');
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
        run(wire) {
            const url = this.$refs.sellDecisionUrl.value.trim();
            if (!url) {
                wire.runSellDecision();
                return;
            }
            if (!this.extensionReady) {
                this.extensionMessage = 'Chrome Companion bağlı değil; sunucu okuyucusu çalıştırılıyor.';
                wire.runSellDecision();
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
                wire.runSellDecision();
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
                wire.runSellDecision();
                return;
            }
            wire.sellDecisionBridgeCompleted(
                response.analysis?.tracked_product_id || null,
                response.message || 'Canlı ürün analizi tamamlandı.',
                Boolean(response.ok),
            );
        },
    }"
    x-on:message.window="receive($event, $wire)"
    class="grid grid-cols-1 gap-4 xl:grid-cols-12 lg:gap-6"
>
    <section class="min-w-0 rounded-[10px] border border-slate-200 bg-white shadow-sm xl:col-span-12">
        <div class="border-b border-slate-200 px-4 py-4 lg:px-6">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase text-slate-500">AI karar komutu</p>
                    <h2 class="mt-1 text-lg font-semibold text-slate-900">Ürün verisini oku, satış hızını ölç, finansal kararı üret</h2>
                </div>
                <button
                    type="button"
                    x-on:click="run($wire)"
                    x-bind:disabled="busy"
                    wire:loading.attr="disabled"
                    wire:target="runSellDecision,sellDecisionBridgeCompleted"
                    class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] bg-slate-900 px-4 py-3 text-base font-medium text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto sm:py-2 sm:text-sm"
                >
                    <x-lucide.icon name="activity" class="h-4 w-4" />
                    <span x-show="!busy" wire:loading.remove wire:target="runSellDecision,sellDecisionBridgeCompleted">Karar üret</span>
                    <span x-show="busy" x-cloak>Canlı veriler okunuyor...</span>
                    <span wire:loading wire:target="runSellDecision,sellDecisionBridgeCompleted">Karar hesaplanıyor...</span>
                </button>
            </div>
        </div>

        <div class="space-y-5 p-4 lg:p-6">
            <div class="grid grid-cols-1 gap-3 lg:grid-cols-12 lg:gap-4">
                <label class="lg:col-span-7">
                    <span class="text-xs font-medium text-slate-600">Trendyol ürün linki</span>
                    <input
                        x-ref="sellDecisionUrl"
                        type="url"
                        wire:model.live.debounce.350ms="productUrl"
                        placeholder="https://www.trendyol.com/...-p-123456"
                        class="mt-1.5 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none focus:border-slate-400 sm:py-2 sm:text-sm"
                    >
                    @error('productUrl')
                        <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </label>
                <label class="lg:col-span-5">
                    <span class="text-xs font-medium text-slate-600">ZOLM ürün kartı</span>
                    <select
                        wire:model.live="selectedProductId"
                        class="mt-1.5 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none focus:border-slate-400 sm:py-2 sm:text-sm"
                    >
                        <option value="">Manuel karar</option>
                        @foreach($productOptions as $option)
                            <option value="{{ $option->id }}">{{ $option->product_name ?: 'Adsız ürün' }} · {{ $option->stock_code ?: $option->barcode }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="flex flex-wrap items-center gap-2 text-xs">
                <span x-show="extensionReady" x-cloak class="rounded-[6px] border border-emerald-200 bg-emerald-50 px-2 py-1 font-medium text-emerald-700">Chrome Companion hazır · canlı fiyat okunacak</span>
                <span x-show="!extensionReady" class="rounded-[6px] border border-amber-200 bg-amber-50 px-2 py-1 text-amber-700">Companion bulunamazsa sunucu okuyucusu ve kayıtlı son veri kullanılır</span>
                <span x-show="extensionMessage" x-cloak x-text="extensionMessage" class="rounded-[6px] border border-rose-200 bg-rose-50 px-2 py-1 text-rose-700"></span>
                <span class="text-slate-500">Fiyat, değerlendirme, yorum ve görünür talep sinyalleri aynı taramada kaydedilir.</span>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4 lg:gap-4">
                @foreach([
                    ['salePrice', 'Satış fiyatı', 'TL'],
                    ['cogs', 'Alış maliyeti', 'TL'],
                    ['cargoCost', 'Kargo', 'TL'],
                    ['commissionRate', 'Komisyon', '%'],
                    ['packagingCost', 'Ambalaj', 'TL'],
                    ['returnCargoCost', 'İade kargo', 'TL'],
                    ['returnRate', 'İade oranı', '%'],
                    ['serviceFeeRate', 'Hizmet oranı', '%'],
                    ['incomeTaxRate', 'Gelir vergisi', '%'],
                    ['vatRate', 'Satış KDV', '%'],
                    ['costVatRate', 'Alış KDV', '%'],
                    ['expenseVatRate', 'Gider KDV', '%'],
                ] as [$model, $label, $suffix])
                    <label class="min-w-0">
                        <span class="text-xs font-medium text-slate-600">{{ $label }}</span>
                        <div class="relative mt-1.5">
                            <input
                                type="number"
                                min="0"
                                step="0.01"
                                wire:model.live.debounce.250ms="{{ $model }}"
                                class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 pr-10 text-base text-slate-900 outline-none focus:border-slate-400 sm:py-2 sm:text-sm"
                            >
                            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-slate-400">{{ $suffix }}</span>
                        </div>
                    </label>
                @endforeach
            </div>

            <div class="flex flex-col gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:items-center sm:justify-between">
                <div class="grid w-full grid-cols-1 gap-2 sm:grid-cols-3 xl:w-auto">
                    @foreach([
                        ['vatEnabled', 'KDV ayrıştır'],
                        ['withholdingEnabled', 'Stopaj göster'],
                        ['sellDecisionUseMarketSearch', 'Pazar ara'],
                    ] as [$model, $label])
                        <label class="flex min-h-11 cursor-pointer items-center justify-between gap-3 rounded-[6px] border border-slate-200 bg-slate-50/60 px-3 py-2 text-sm text-slate-700">
                            <span>{{ $label }}</span>
                            <input type="checkbox" wire:model.live="{{ $model }}" class="h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-500">
                        </label>
                    @endforeach
                </div>
                <div class="rounded-[6px] border border-violet-200 bg-violet-50 px-3 py-2 text-xs leading-5 text-violet-800">
                    Stopaj peşin vergi olarak izlenir; net kârda ikinci kez düşülmez.
                </div>
            </div>

            @if(!empty($costRecommendation))
                <div class="border-t border-slate-200 pt-5" data-testid="booster-sell-cost-recommendation">
                    <div class="mb-3 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-xs font-semibold uppercase text-slate-500">Otomatik maliyet önerisi</p>
                        <span class="w-fit rounded-[6px] border border-sky-200 bg-sky-50 px-2 py-1 font-mono text-xs text-sky-700">
                            Genel güven %{{ number_format((float) data_get($costRecommendation, 'overall_confidence', 0), 0, ',', '.') }}
                        </span>
                    </div>
                    <div class="grid grid-cols-1 divide-y divide-slate-200 overflow-hidden rounded-lg border border-slate-200 bg-slate-50/60 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                        <div class="min-w-0 p-3">
                            <p class="text-xs text-slate-500">Komisyon</p>
                            <p class="mt-1 text-base font-semibold text-slate-900">{{ $percent(data_get($costRecommendation, 'commission.rate')) }}</p>
                            <p class="mt-1 truncate text-xs text-slate-500">{{ data_get($costRecommendation, 'commission.seller_level') ? 'Seviye '.data_get($costRecommendation, 'commission.seller_level').' · ' : '' }}{{ data_get($costRecommendation, 'commission.source_label', '-') }}</p>
                        </div>
                        <div class="min-w-0 p-3">
                            <p class="text-xs text-slate-500">Faturalanacak desi</p>
                            <p class="mt-1 text-base font-semibold text-slate-900">{{ (int) data_get($costRecommendation, 'desi.billable_desi', 0) }} desi</p>
                            <p class="mt-1 truncate text-xs text-slate-500">{{ data_get($costRecommendation, 'desi.source_label', '-') }}</p>
                        </div>
                        <div class="min-w-0 p-3">
                            <p class="text-xs text-slate-500">Kargo (KDV dahil)</p>
                            <p class="mt-1 text-base font-semibold text-slate-900">{{ $money(data_get($costRecommendation, 'shipping.cost_gross')) }}</p>
                            <p class="mt-1 truncate text-xs text-slate-500">{{ data_get($costRecommendation, 'shipping.cargo_company', '-') }}</p>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </section>

    @if(! $hasResult)
        <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm xl:col-span-12 lg:p-6">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3 lg:gap-4">
                <div class="rounded-[8px] border border-emerald-200 bg-emerald-50/60 p-4">
                    <div class="flex items-center gap-2">
                        <x-lucide.icon name="check-circle" class="h-4 w-4 text-emerald-700" />
                        <p class="text-sm font-semibold text-emerald-900">Evet, sat</p>
                    </div>
                    <p class="mt-2 text-sm leading-6 text-emerald-800">Net kâr, satış hızı ve veri güveni aynı yönde güçlü olduğunda açılır.</p>
                </div>
                <div class="rounded-[8px] border border-amber-200 bg-amber-50/60 p-4">
                    <div class="flex items-center gap-2">
                        <x-lucide.icon name="clock" class="h-4 w-4 text-amber-700" />
                        <p class="text-sm font-semibold text-amber-900">Bekle veya test et</p>
                    </div>
                    <p class="mt-2 text-sm leading-6 text-amber-800">Kâr pozitif ama hız, rekabet veya veri güveni kararsızsa küçük parti önerilir.</p>
                </div>
                <div class="rounded-[8px] border border-rose-200 bg-rose-50/60 p-4 sm:col-span-2 xl:col-span-1">
                    <div class="flex items-center gap-2">
                        <x-lucide.icon name="x-circle" class="h-4 w-4 text-rose-700" />
                        <p class="text-sm font-semibold text-rose-900">Hayır, satma</p>
                    </div>
                    <p class="mt-2 text-sm leading-6 text-rose-800">Vergi sonrası zarar veya zayıf talep sinyali varsa ürün elenir.</p>
                </div>
            </div>
        </section>
    @else
        <section class="min-w-0 rounded-[10px] border {{ $decisionMeta['panel'] }} p-4 shadow-sm xl:col-span-12 lg:p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex min-w-0 items-start gap-3 sm:gap-4">
                    <div class="h-16 w-16 shrink-0 overflow-hidden rounded-[8px] border border-white/80 bg-white sm:h-20 sm:w-20">
                        @if(!empty($product['image_url']))
                            <img src="{{ $product['image_url'] }}" alt="{{ $product['title'] ?? 'Ürün' }}" class="h-full w-full object-cover" loading="lazy">
                        @else
                            <div class="flex h-full w-full items-center justify-center text-slate-400"><x-lucide.icon name="image" class="h-6 w-6" /></div>
                        @endif
                    </div>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-[6px] border bg-white/70 px-2 py-1 font-mono text-[11px] text-slate-600">ID: {{ $product['trendyol_product_id'] ?: '-' }}</span>
                            <span class="rounded-[6px] border bg-white/70 px-2 py-1 text-[11px] font-medium text-slate-700">Güven: {{ $confidence }}/100</span>
                        </div>
                        <h2 class="mt-2 break-words text-lg font-semibold text-slate-950 lg:text-xl">{{ $product['title'] ?: 'Ürün başlığı yok' }}</h2>
                        <p class="mt-1 text-sm text-slate-600">{{ collect([$product['brand'] ?? null, $product['category_name'] ?? null])->filter()->join(' · ') ?: 'Marka ve kategori bilgisi yok' }}</p>
                    </div>
                </div>
                <div class="min-w-0 text-left lg:text-right">
                    <span class="inline-flex items-center gap-2 rounded-[6px] border px-3 py-2 text-sm font-semibold {{ $decisionMeta['tone'] }}">
                        <x-lucide.icon name="{{ $decisionMeta['icon'] }}" class="h-4 w-4" />
                        {{ $decisionMeta['label'] }}
                    </span>
                    <p class="mt-2 text-3xl font-bold text-slate-950">{{ $score }}/100</p>
                    <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-white/70 lg:w-56">
                        <div class="h-full rounded-full {{ $decisionMeta['bar'] }}" style="width: {{ min(100, max(0, $score)) }}%"></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm xl:col-span-12 lg:p-6">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4 lg:gap-4">
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-4">
                    <p class="text-xs text-slate-500">Tahmini satış hızı</p>
                    <p class="mt-1 text-xl font-semibold text-slate-900">{{ $number($velocity['estimated_daily_sales'] ?? null) }} adet/gün</p>
                    <p class="mt-1 text-xs text-slate-500">Aralık: {{ $number($velocity['estimated_daily_sales_low'] ?? null) }} - {{ $number($velocity['estimated_daily_sales_high'] ?? null) }}</p>
                </div>
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-4">
                    <p class="text-xs text-slate-500">Yorum hızı</p>
                    <p class="mt-1 text-xl font-semibold text-slate-900">{{ $number($velocity['review_per_day'] ?? null) }} yorum/gün</p>
                    <p class="mt-1 text-xs text-slate-500">Toplam: {{ $number($velocity['total_reviews'] ?? null, 0) }}</p>
                </div>
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-4">
                    <p class="text-xs text-slate-500">Değerlendirme hızı</p>
                    <p class="mt-1 text-xl font-semibold text-slate-900">{{ $number($velocity['evaluation_per_day'] ?? null) }} adet/gün</p>
                    <p class="mt-1 text-xs text-slate-500">Toplam: {{ $number($velocity['total_evaluations'] ?? null, 0) }}</p>
                </div>
                <div class="rounded-[8px] border border-slate-900 bg-slate-900 p-4 text-white">
                    <p class="text-xs text-slate-300">Vergi sonrası net kâr</p>
                    <p class="mt-1 text-xl font-semibold">{{ $money($financial['net_profit'] ?? null) }}</p>
                    <p class="mt-1 text-xs text-slate-300">Marj: {{ $percent($financial['profit_margin_percent'] ?? null) }}</p>
                </div>
            </div>
        </section>

        <section class="min-w-0 rounded-[10px] border border-slate-200 bg-white shadow-sm xl:col-span-7">
            <div class="border-b border-slate-200 px-4 py-4 lg:px-6">
                <h3 class="text-base font-semibold text-slate-900">Satış hızı sinyalleri</h3>
            </div>
            <div class="space-y-4 p-4 lg:p-6">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:gap-4">
                    @foreach([
                        ['Stok düşüşü', $velocity['stock_drop_per_day'] ?? null, 'adet/gün'],
                        ['Favori artışı', $velocity['favorite_per_day'] ?? null, 'favori/gün'],
                        ['Soru artışı', $velocity['question_per_day'] ?? null, 'soru/gün'],
                        ['Stok bitiş tahmini', $velocity['days_of_stock'] ?? null, 'gün'],
                    ] as [$label, $value, $unit])
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                            <p class="text-xs text-slate-500">{{ $label }}</p>
                            <p class="mt-1 text-base font-semibold text-slate-900">{{ $number($value) }} {{ $unit }}</p>
                        </div>
                    @endforeach
                </div>

                <div class="overflow-hidden rounded-lg border border-slate-200">
                    <div class="grid grid-cols-[minmax(0,1.2fr)_minmax(90px,0.7fr)_minmax(0,1.4fr)] gap-3 border-b border-slate-200 bg-slate-50/60 px-4 py-3 text-xs font-semibold uppercase text-slate-500">
                        <span>Sinyal</span><span>Değer</span><span>Not</span>
                    </div>
                    <div class="divide-y divide-slate-100 bg-white">
                        @forelse(($velocity['sources'] ?? []) as $source)
                            <div class="grid grid-cols-1 gap-2 p-4 md:grid-cols-[minmax(0,1.2fr)_minmax(90px,0.7fr)_minmax(0,1.4fr)] md:items-center md:gap-3 md:py-3">
                                <p class="text-sm font-medium text-slate-900">{{ $source['label'] ?? '-' }}</p>
                                <p class="font-mono text-sm text-slate-700">{{ $number($source['value'] ?? null) }} {{ $source['unit'] ?? '' }}</p>
                                <p class="text-xs leading-5 text-slate-500">{{ $source['note'] ?? '' }}</p>
                            </div>
                        @empty
                            <div class="p-4 text-sm text-slate-500">İlk taramada hız sinyali sınırlı. İkinci snapshot sonrası gerçek artışlar hesaplanır.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>

        <section class="min-w-0 rounded-[10px] border border-slate-200 bg-white shadow-sm xl:col-span-5">
            <div class="border-b border-slate-200 px-4 py-4 lg:px-6">
                <h3 class="text-base font-semibold text-slate-900">Finans kırılımı</h3>
            </div>
            <div class="space-y-3 p-4 lg:p-6">
                @foreach([
                    ['Satış', $financial['sale_gross'] ?? null],
                    ['Alış + ambalaj', $financial['product_cost_gross'] ?? null],
                    ['Komisyon', $financial['commission_gross'] ?? null],
                    ['Kargo', $financial['cargo_gross'] ?? null],
                    ['Ödenecek KDV', $financial['payable_vat'] ?? null],
                    ['Gelir vergisi', $financial['income_tax'] ?? null],
                    ['Stopaj bilgi', $financial['withholding'] ?? null],
                ] as [$label, $value])
                    <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-2 last:border-b-0 last:pb-0">
                        <span class="text-sm text-slate-600">{{ $label }}</span>
                        <span class="font-mono text-sm font-medium text-slate-900">{{ $money($value) }}</span>
                    </div>
                @endforeach
                <div class="rounded-[6px] border border-violet-200 bg-violet-50 px-3 py-2 text-xs leading-5 text-violet-800">
                    {{ $financial['withholding_note'] ?? 'Stopaj bilgi amaçlıdır.' }}
                </div>
                @if((float) ($financial['advertising_excluded'] ?? 0) > 0)
                    <div class="rounded-[6px] border border-amber-200 bg-amber-50 px-3 py-2 text-xs leading-5 text-amber-800">
                        Reklam payı ayrı hassasiyet olarak izlenir: reklam sonrası net {{ $money($financial['net_profit_after_ads'] ?? null) }}.
                    </div>
                @endif
            </div>
        </section>

        <section class="min-w-0 rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm xl:col-span-12 lg:p-6">
            <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
                <div class="min-w-0 xl:col-span-1">
                    <p class="text-xs font-semibold uppercase text-slate-500">Pazar görünürlüğü</p>
                    <h3 class="mt-1 text-base font-semibold text-slate-900">{{ $market['visibility_label'] ?? 'Arama verisi yok' }}</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-500">
                        Görünen rakip: {{ (int) ($market['visible_competitors'] ?? 0) }} · Sıra: {{ $market['product_rank'] ?? '-' }}
                    </p>
                </div>
                <div class="space-y-2 xl:col-span-1">
                    <p class="text-xs font-semibold uppercase text-slate-500">Neden</p>
                    @foreach(($result['reasons'] ?? []) as $reason)
                        <p class="rounded-[6px] border border-slate-200 bg-slate-50/60 px-3 py-2 text-sm leading-6 text-slate-600">{{ $reason }}</p>
                    @endforeach
                </div>
                <div class="space-y-2 xl:col-span-1">
                    <p class="text-xs font-semibold uppercase text-slate-500">Aksiyon</p>
                    @foreach(($result['actions'] ?? []) as $action)
                        <p class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm leading-6 text-slate-700">{{ $action }}</p>
                    @endforeach
                </div>
            </div>
            <div class="mt-4 rounded-[8px] border border-slate-200 bg-slate-50/60 p-4 text-sm leading-6 text-slate-700">
                {{ $result['expert_summary'] ?? '' }}
            </div>
        </section>
    @endif
</div>
