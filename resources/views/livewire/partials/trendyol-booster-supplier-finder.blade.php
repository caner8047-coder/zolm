@php
    $supplierResearch = $supplierResearchDashboard['latest'] ?? null;
    $supplierOffers = collect($supplierResearchDashboard['offers'] ?? []);
    $trendyolOffers = collect($supplierResearchDashboard['trendyol_offers'] ?? []);
    $externalOffers = collect($supplierResearchDashboard['external_offers'] ?? []);
    $coverage = collect($supplierResearchDashboard['coverage'] ?? []);
    $supplierMargin = $this->supplierMarginDashboard;
    $supplierMarginRows = collect($supplierMargin['rows'] ?? []);
    $riskClass = match($supplierResearch?->risk_level) {
        'high' => 'border-rose-200 bg-rose-50 text-rose-700',
        'medium' => 'border-amber-200 bg-amber-50 text-amber-700',
        'low' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
        default => 'border-slate-200 bg-slate-50 text-slate-600',
    };
    $riskLabel = match($supplierResearch?->risk_level) {
        'high' => 'Yüksek rekabet',
        'medium' => 'Orta rekabet',
        'low' => 'Kontrollü rekabet',
        default => 'Veri birikiyor',
    };
    $columnWidths = [
        'platform' => '130px',
        'seller' => '310px',
        'price' => '130px',
        'stock' => '120px',
        'match' => '120px',
        'radar' => '150px',
        'actions' => '90px',
    ];
    $sortIcon = fn (string $column): string => $supplierOfferSort === $column
        ? ($supplierOfferDirection === 'asc' ? '↑' : '↓')
        : '↕';
@endphp

<div
    x-data="{
        extensionReady: false,
        extensionVersion: '',
        extensionMessage: '',
        bridgeBusy: false,
        bridgeRequestId: '',
        bridgeTimer: null,
        columnsOpen: false,
        guidanceOpen: false,
        init() {
            window.postMessage({ source: 'zolm-booster-page', type: 'PING' }, window.location.origin);
        },
        research(wire) {
            const sourceUrl = this.$refs.supplierUrl.value.trim();

            if (!sourceUrl) {
                wire.runSupplierResearch();
                return;
            }
            if (!this.extensionReady) {
                this.extensionMessage = 'Chrome Companion bağlı değil; sunucu araştırması çalıştırılıyor.';
                wire.runSupplierResearch();
                return;
            }

            this.bridgeBusy = true;
            this.extensionMessage = '';
            this.bridgeRequestId = `${Date.now()}-${Math.random().toString(16).slice(2)}`;
            window.postMessage({
                source: 'zolm-booster-page',
                type: 'SUPPLIER_RESEARCH_QUERY',
                request_id: this.bridgeRequestId,
                source_url: sourceUrl,
            }, window.location.origin);

            this.bridgeTimer = window.setTimeout(() => {
                if (!this.bridgeBusy) return;
                this.bridgeBusy = false;
                this.extensionMessage = 'Chrome pazar araştırması zaman aşımına uğradı; sunucu araştırması çalıştırılıyor.';
                wire.runSupplierResearch();
            }, 90000);
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

            if (data.type !== 'SUPPLIER_RESEARCH_RESULT' || data.request_id !== this.bridgeRequestId) return;

            window.clearTimeout(this.bridgeTimer);
            this.bridgeBusy = false;
            const response = data.response || {};
            this.extensionReady = true;
            this.extensionMessage = '';
            if (!response.ok) {
                this.extensionMessage = response.message || 'Chrome Companion yanıtı başarısız oldu; sunucu araştırması çalıştırılıyor.';
                wire.runSupplierResearch();
                return;
            }
            wire.supplierResearchBridgeCompleted(
                response.message || (response.ok ? 'Pazar araştırması tamamlandı.' : 'Pazar araştırması tamamlanamadı.'),
                Boolean(response.ok)
            );
        },
    }"
    x-on:message.window="handleBridge($event, $wire)"
    class="space-y-4 lg:space-y-6"
    data-testid="booster-supplier-finder"
>
    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex min-w-0 items-start gap-3">
                <div x-data="{ imageFailed: false }" class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-[8px] border border-slate-200 bg-slate-50/70">
                    @if($supplierResearch?->image_url)
                        <img x-show="!imageFailed" x-on:error="imageFailed = true" src="{{ $supplierResearch->image_url }}" alt="{{ $supplierResearch->title }}" class="h-full w-full object-cover">
                        <span x-show="imageFailed" x-cloak><x-lucide.icon name="package" class="h-6 w-6 text-slate-400" /></span>
                    @else
                        <x-lucide.icon name="scan-search" class="h-6 w-6 text-slate-400" />
                    @endif
                </div>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Booster Tedarikçi Radar</p>
                        <span x-show="extensionReady" x-cloak class="rounded-[6px] border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700">Chrome bağlı <span x-text="extensionVersion"></span></span>
                        <span x-show="extensionMessage" x-cloak x-text="extensionMessage" class="rounded-[6px] border border-rose-200 bg-rose-50 px-2 py-0.5 text-[11px] text-rose-700"></span>
                    </div>
                    <h2 class="mt-1 text-xl font-semibold tracking-tight text-slate-900 lg:text-2xl">
                        {{ $supplierResearch?->title ?: 'Bir Trendyol ürününden pazar haritası çıkarın' }}
                    </h2>
                    <p class="mt-1 max-w-3xl text-sm text-slate-500">
                        Önce ürün sayfasındaki gerçek Trendyol satıcıları okunur; ardından Google Alışveriş ve hedef kanallarda ürün kimliği güçlü eşleşen teklifler karşılaştırılır.
                    </p>
                    @if($supplierResearch)
                        <div class="mt-3 flex flex-wrap gap-2 text-xs">
                            @if($supplierResearch->brand)<span class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-2 py-1 font-medium text-slate-700">{{ $supplierResearch->brand }}</span>@endif
                            @if($supplierResearch->category_name)<span class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-2 py-1 text-slate-600">{{ $supplierResearch->category_name }}</span>@endif
                            <span class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-2 py-1 text-slate-600">{{ $supplierResearch->scan_count }} tarama</span>
                            <span class="rounded-[6px] border px-2 py-1 font-medium {{ $riskClass }}">{{ $riskLabel }}</span>
                        </div>
                    @endif
                </div>
            </div>

            <div class="grid min-w-0 grid-cols-3 gap-2 sm:w-auto sm:min-w-[330px]">
                <div class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                    <p class="truncate text-[11px] text-slate-500">Kanal</p>
                    <p class="mt-1 text-lg font-semibold text-slate-900">{{ $supplierResearch?->platform_count ?? 0 }}</p>
                </div>
                <div class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                    <p class="truncate text-[11px] text-slate-500">Satıcı</p>
                    <p class="mt-1 text-lg font-semibold text-slate-900">{{ $supplierResearch?->seller_count ?? 0 }}</p>
                </div>
                <div class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                    <p class="truncate text-[11px] text-slate-500">Pazar uyumu</p>
                    <p class="mt-1 text-lg font-semibold text-slate-900">{{ $supplierResearch?->market_fit_score !== null ? $supplierResearch->market_fit_score : '—' }}<span class="text-xs font-normal text-slate-400">/100</span></p>
                </div>
            </div>
        </div>
    </section>

    <section class="overflow-hidden rounded-[10px] border border-slate-200 bg-white shadow-sm">
        <button type="button" @click="guidanceOpen = !guidanceOpen" class="flex min-h-[44px] w-full items-center justify-between gap-3 p-4 text-left lg:px-6">
            <span class="flex min-w-0 items-center gap-3">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-[6px] border border-indigo-200 bg-indigo-50 text-indigo-700"><x-lucide.icon name="radar" class="h-4 w-4" /></span>
                <span class="min-w-0">
                    <span class="block text-sm font-semibold text-slate-900">Bu radar nasıl karar üretir?</span>
                    <span class="block truncate text-xs text-slate-500">Ürün kimliği → fiyat haritası → zaman sinyali</span>
                </span>
            </span>
            <x-lucide.icon name="chevron-down" class="h-4 w-4 shrink-0 text-slate-400 transition" x-bind:class="guidanceOpen && 'rotate-180'" />
        </button>
        <div x-show="guidanceOpen" x-collapse x-cloak class="border-t border-slate-200 bg-slate-50/60 p-4 lg:px-6">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3 lg:gap-4">
                <div class="rounded-[8px] border border-slate-200 bg-white p-3"><p class="text-xs font-semibold text-slate-900">1. Ürün kimliğini doğrula</p><p class="mt-1 text-xs leading-5 text-slate-500">Marka, model kodu ve ayırt edici teknik özellikler güçlü eşleşmiyorsa sonuç kaydedilmez ve gösterilmez.</p></div>
                <div class="rounded-[8px] border border-slate-200 bg-white p-3"><p class="text-xs font-semibold text-slate-900">2. Rekabeti ölç</p><p class="mt-1 text-xs leading-5 text-slate-500">Satıcı yoğunluğu, kanal sayısı, taban–medyan fiyat ve fiyat makası birlikte değerlendirilir.</p></div>
                <div class="rounded-[8px] border border-slate-200 bg-white p-3 sm:col-span-2 xl:col-span-1"><p class="text-xs font-semibold text-slate-900">3. Zaman sinyali üret</p><p class="mt-1 text-xs leading-5 text-slate-500">İkinci taramadan itibaren stok erimesi tahmini satışa, fiyat değişimleri rekabet baskısına dönüşür.</p></div>
            </div>
        </div>
    </section>

    <section class="overflow-hidden rounded-[10px] border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 p-4 lg:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <label class="min-w-0 flex-1">
                    <span class="text-xs font-medium text-slate-600">Trendyol ürün linki</span>
                    <input x-ref="supplierUrl" type="url" wire:model.defer="storeWatchUrl" placeholder="https://www.trendyol.com/marka/urun-p-123456"
                           class="mt-1.5 min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none focus:border-slate-400 focus:ring-1 focus:ring-slate-300 sm:py-2 sm:text-sm">
                    @error('storeWatchUrl') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                </label>
                <button type="button" @click="research($wire)" x-bind:disabled="bridgeBusy" wire:loading.attr="disabled" wire:target="runSupplierResearch"
                        @disabled(!$supplierResearchReady)
                        class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] bg-slate-900 px-4 py-3 text-base font-medium text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto sm:py-2 sm:text-sm">
                    <x-lucide.icon name="search" class="h-4 w-4" />
                    <span x-show="!bridgeBusy" wire:loading.remove wire:target="runSupplierResearch">Pazarı Araştır</span>
                    <span x-show="bridgeBusy" x-cloak>Trendyol + Google Alışveriş taranıyor...</span>
                    <span wire:loading wire:target="runSupplierResearch">Araştırılıyor...</span>
                </button>
            </div>
            <div class="mt-3 flex flex-col gap-2 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between">
                <span>Chrome bağlıysa Trendyol satıcıları ve Google Alışveriş ürün kartları tarayıcı oturumunuzdan okunur.</span>
                @if($supplierResearch?->last_checked_at)<span class="shrink-0">Son tarama: {{ $supplierResearch->last_checked_at->format('d.m.Y H:i') }}</span>@endif
            </div>
        </div>

        @if($supplierResearch)
            <div class="grid grid-cols-1 gap-3 border-b border-slate-200 bg-slate-50/60 p-4 sm:grid-cols-2 xl:grid-cols-4 lg:gap-4 lg:px-6">
                <div class="min-w-0 rounded-[8px] border border-slate-200 bg-white p-3"><p class="text-xs text-slate-500">Pazar fiyat aralığı</p><p class="mt-1 truncate text-sm font-semibold text-slate-900">{{ $supplierResearch->min_price !== null ? $this->formatMoney($supplierResearch->min_price) : 'Veri yok' }} – {{ $supplierResearch->max_price !== null ? $this->formatMoney($supplierResearch->max_price) : 'Veri yok' }}</p></div>
                <div class="min-w-0 rounded-[8px] border border-slate-200 bg-white p-3"><p class="text-xs text-slate-500">Medyan / fiyat makası</p><p class="mt-1 truncate text-sm font-semibold text-slate-900">{{ $supplierResearch->median_price !== null ? $this->formatMoney($supplierResearch->median_price) : 'Veri yok' }} · {{ $supplierResearch->price_spread_percent !== null ? '%'.number_format((float) $supplierResearch->price_spread_percent, 1, ',', '.') : '—' }}</p></div>
                <div class="min-w-0 rounded-[8px] border border-slate-200 bg-white p-3"><p class="text-xs text-slate-500">Güçlü eşleşen teklif</p><p class="mt-1 truncate text-sm font-semibold text-slate-900">{{ $supplierResearch->verified_offer_count }} teklif · güven %{{ $supplierResearch->confidence_score }}</p></div>
                <div class="min-w-0 rounded-[8px] border border-slate-200 bg-white p-3"><p class="text-xs text-slate-500">Radar satış sinyali</p><p class="mt-1 truncate text-sm font-semibold text-slate-900">{{ $supplierResearch->scan_count >= 2 ? number_format($supplierOffers->sum('estimated_sales'), 0, ',', '.') . ' tahmini' : '2. taramada başlar' }}</p></div>
            </div>

            <section class="border-b border-slate-200 p-4 lg:p-6" data-testid="booster-supplier-margin-planner">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Tedarikçi marj eşleştirme</p>
                        <h3 class="mt-1 text-base font-semibold text-slate-900">Hangi teklif hedef marjı taşıyor?</h3>
                        <p class="mt-1 text-xs text-slate-500">Kaynak ürünün güncel satış fiyatına göre teklifleri aynı maliyet senaryosunda karşılaştırın.</p>
                    </div>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:w-[600px]">
                        <label class="min-w-0"><span class="text-[11px] text-slate-500">Komisyon %</span><input type="number" min="0" max="100" step="0.1" wire:model.live.debounce.400ms="supplierCommissionRate" class="mt-1 min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-2 text-base sm:text-sm"></label>
                        <label class="min-w-0"><span class="text-[11px] text-slate-500">Kargo TL</span><input type="number" min="0" step="0.01" wire:model.live.debounce.400ms="supplierShippingCost" class="mt-1 min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-2 text-base sm:text-sm"></label>
                        <label class="min-w-0"><span class="text-[11px] text-slate-500">Paket TL</span><input type="number" min="0" step="0.01" wire:model.live.debounce.400ms="supplierPackagingCost" class="mt-1 min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-2 text-base sm:text-sm"></label>
                        <label class="min-w-0"><span class="text-[11px] text-slate-500">Hedef marj %</span><input type="number" min="-100" max="100" step="0.1" wire:model.live.debounce.400ms="supplierTargetMargin" class="mt-1 min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-2 text-base sm:text-sm"></label>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3"><p class="text-xs text-slate-500">Azami alış maliyeti</p><p class="mt-1 text-lg font-semibold text-slate-900">{{ $this->formatMoney($supplierMargin['max_purchase_cost'] ?? 0) }}</p></div>
                    <div class="rounded-[8px] border border-emerald-200 bg-emerald-50/70 p-3"><p class="text-xs text-emerald-700">Hedefi geçen</p><p class="mt-1 text-lg font-semibold text-emerald-800">{{ (int) ($supplierMargin['go_count'] ?? 0) }} teklif</p></div>
                    <div class="rounded-[8px] border border-amber-200 bg-amber-50/70 p-3"><p class="text-xs text-amber-700">Pazarlık gereken</p><p class="mt-1 text-lg font-semibold text-amber-800">{{ (int) ($supplierMargin['negotiate_count'] ?? 0) }} teklif</p></div>
                </div>

                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @forelse($supplierMarginRows->take(6) as $marginRow)
                        @php($marginTone = match($marginRow['decision']) { 'go' => 'border-emerald-200 bg-emerald-50/70 text-emerald-700', 'negotiate' => 'border-amber-200 bg-amber-50/70 text-amber-700', 'reject' => 'border-rose-200 bg-rose-50/70 text-rose-700', default => 'border-slate-200 bg-slate-50/70 text-slate-600' })
                        <article class="min-w-0 rounded-[8px] border border-slate-200 bg-white p-3">
                            <div class="flex items-start justify-between gap-2"><div class="min-w-0"><p class="truncate text-sm font-semibold text-slate-900">{{ $marginRow['seller'] }}</p><p class="mt-0.5 truncate text-xs text-slate-500">{{ $marginRow['platform'] }} · eşleşme %{{ $marginRow['match_score'] }}</p></div><span class="shrink-0 rounded-[6px] border px-2 py-1 text-[11px] font-medium {{ $marginTone }}">{{ $marginRow['decision_label'] }}</span></div>
                            <div class="mt-3 grid grid-cols-3 gap-2 text-center text-xs"><div class="rounded-[6px] bg-slate-50 p-2"><p class="text-slate-400">Alış</p><p class="mt-1 font-mono font-semibold text-slate-800">{{ $this->formatMoney($marginRow['purchase_cost']) }}</p></div><div class="rounded-[6px] bg-slate-50 p-2"><p class="text-slate-400">Net kâr</p><p class="mt-1 font-mono font-semibold {{ $marginRow['net_profit'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $this->formatMoney($marginRow['net_profit']) }}</p></div><div class="rounded-[6px] bg-slate-50 p-2"><p class="text-slate-400">Marj</p><p class="mt-1 font-mono font-semibold text-slate-800">{{ $marginRow['margin'] !== null ? '%'.number_format($marginRow['margin'], 1, ',', '.') : '—' }}</p></div></div>
                            @if($marginRow['decision'] === 'negotiate')<p class="mt-2 text-xs text-amber-700">Hedef için {{ $this->formatMoney(max(0, $marginRow['negotiation_gap'])) }} daha düşük alış fiyatı gerekir.</p>@endif
                        </article>
                    @empty
                        <div class="rounded-[8px] border border-dashed border-slate-300 p-4 text-sm text-slate-500 sm:col-span-2 xl:col-span-3">Marj senaryosu için doğrulanmış ve fiyatı olan teklif bekleniyor.</div>
                    @endforelse
                </div>
                <p class="mt-3 rounded-[6px] border border-dashed border-slate-300 bg-slate-50/60 px-3 py-2 text-xs leading-5 text-slate-500">{{ $supplierMargin['evidence_note'] }}</p>
            </section>

            <div class="border-b border-slate-200 p-4 lg:px-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2"><span class="flex h-6 w-6 items-center justify-center rounded-[6px] bg-slate-900 text-xs font-semibold text-white">1</span><h3 class="text-sm font-semibold text-slate-900">Trendyol’daki satıcılar</h3></div>
                        <p class="mt-1 text-xs text-slate-500">Aynı ürün sayfasında görünen teklifler; mağaza vitrini değil, doğrudan ürün satıcıları.</p>
                    </div>
                    <span class="rounded-[6px] border border-orange-200 bg-orange-50 px-2.5 py-1 text-xs font-medium text-orange-700">{{ $trendyolOffers->count() }} satıcı</span>
                </div>
                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3 lg:gap-4">
                    @forelse($trendyolOffers as $offer)
                        <article class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0"><p class="truncate text-sm font-semibold text-slate-900">{{ $offer->seller_name ?: 'Trendyol satıcısı' }}</p><p class="mt-1 truncate text-xs text-slate-500">{{ $offer->seller_id ? 'Mağaza ID '.$offer->seller_id : 'Mağaza ID alınamadı' }}</p></div>
                                <span class="shrink-0 font-mono text-sm font-semibold text-slate-900">{{ $offer->sale_price > 0 ? $this->formatMoney($offer->sale_price) : '—' }}</span>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2 text-[11px]">
                                <span class="rounded-[6px] border border-slate-200 bg-white px-2 py-1 text-slate-600">{{ $offer->stock !== null ? number_format($offer->stock, 0, ',', '.').' stok' : 'Stok yayınlanmıyor' }}</span>
                                @if(data_get($offer->raw_payload, 'seller_score') !== null)<span class="rounded-[6px] border border-amber-200 bg-amber-50 px-2 py-1 text-amber-700">{{ number_format((float) data_get($offer->raw_payload, 'seller_score'), 1, ',', '.') }} puan</span>@endif
                            </div>
                        </article>
                    @empty
                        <div class="rounded-[8px] border border-dashed border-slate-300 bg-slate-50/60 p-4 text-sm text-slate-500 sm:col-span-2 xl:col-span-3">Trendyol satıcı teklifleri henüz okunmadı. Chrome Booster eklentisini yeniden yükleyip araştırmayı tekrar çalıştırın.</div>
                    @endforelse
                </div>
            </div>

            <div class="p-4 lg:p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2"><span class="flex h-6 w-6 items-center justify-center rounded-[6px] bg-slate-900 text-xs font-semibold text-white">2</span><h3 class="text-sm font-semibold text-slate-900">Google Alışveriş ve diğer kanallar</h3></div>
                        <p class="mt-1 text-xs text-slate-500">Model kodu, marka ve teknik özelliklerle güçlü ürün kimliği yakalayan alışveriş kartları gösterilir.</p>
                    </div>
                    <div class="relative flex shrink-0 items-center gap-2">
                        <span class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-2.5 py-1 text-xs text-slate-600">{{ count($visibleColumns) }}/{{ count($supplierOfferColumnDefinitions) }} kolon</span>
                        <button type="button" @click="columnsOpen = !columnsOpen" class="inline-flex min-h-[44px] items-center gap-2 rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"><x-lucide.icon name="columns-3" class="h-4 w-4" /> Kolonlar</button>
                        <div x-show="columnsOpen" @click.outside="columnsOpen = false" x-cloak class="absolute right-0 top-full z-30 mt-2 w-56 rounded-[8px] border border-slate-200 bg-white p-2 shadow-md">
                            @foreach($supplierOfferColumnDefinitions as $column => $label)
                                <label class="flex min-h-[36px] items-center gap-2 rounded-[6px] px-2 py-1.5 text-sm text-slate-700 hover:bg-slate-50">
                                    <input type="checkbox" wire:click="toggleColumn('{{ $column }}')" @checked(in_array($column, $visibleColumns, true)) @disabled($column === 'seller') class="rounded border-slate-300 text-slate-900 focus:ring-slate-400">
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach($coverage as $platform)
                        <span class="rounded-[6px] border px-2 py-1 text-[11px] {{ $platform['found'] ? 'border-emerald-200 bg-emerald-50 font-medium text-emerald-700' : 'border-slate-200 bg-slate-50 text-slate-400' }}">
                            {{ $platform['label'] }}{{ $platform['found'] ? ' · '.$platform['offer_count'] : '' }}
                        </span>
                    @endforeach
                </div>

                @if($supplierResearch->verdict)
                    <div class="mt-4 flex items-start gap-3 rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                        <x-lucide.icon name="activity" class="mt-0.5 h-4 w-4 shrink-0 text-slate-500" />
                        <div class="min-w-0"><p class="text-xs font-semibold text-slate-900">Pazar yorumu</p><p class="mt-1 text-xs leading-5 text-slate-600">{{ $supplierResearch->verdict }}</p><p class="mt-1 text-[11px] text-slate-400">Bu skor başarı garantisi değildir; pazar yoğunluğu ve görünür talep sinyallerinin karar desteğidir.</p></div>
                    </div>
                @endif

                <div class="mt-4 space-y-3 md:hidden">
                    @forelse($externalOffers as $offer)
                        <article class="rounded-[8px] border border-slate-200 bg-white p-4 shadow-sm">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0"><span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-0.5 text-[11px] font-medium text-slate-600">{{ $offer->platform_label }}</span><p class="mt-2 line-clamp-2 text-sm font-semibold text-slate-900">{{ $offer->seller_name ?: $offer->title }}</p><p class="mt-1 line-clamp-2 text-xs text-slate-500">{{ $offer->seller_name ? $offer->title : 'Satıcı adı sonuçta yayınlanmıyor' }}</p></div>
                                <span class="shrink-0 font-mono text-sm font-semibold text-slate-900">{{ $offer->sale_price > 0 ? $this->formatMoney($offer->sale_price) : 'Fiyat yok' }}</span>
                            </div>
                            <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 p-2"><p class="text-[11px] text-slate-500">Eşleşme</p><p class="mt-0.5 text-xs font-semibold text-emerald-700">%{{ $offer->match_score }} · Güçlü</p></div>
                                <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 p-2"><p class="text-[11px] text-slate-500">Radar</p><p class="mt-0.5 text-xs font-semibold text-slate-900">{{ $supplierResearch->scan_count >= 2 ? $offer->estimated_sales.' tahmini satış' : '2. taramada' }}</p></div>
                            </div>
                            <a href="{{ $offer->source_url }}" target="_blank" rel="noopener" class="mt-3 inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 hover:bg-slate-50"><x-lucide.icon name="external-link" class="h-4 w-4" /> Ürünü aç</a>
                        </article>
                    @empty
                        <div class="rounded-[8px] border border-dashed border-slate-300 bg-slate-50/60 p-5 text-sm text-slate-500">Google Alışveriş’te güçlü ürün kimliği eşleşen başka bir teklif bulunmadı.</div>
                    @endforelse
                </div>

                <div class="mt-4 hidden overflow-x-auto rounded-lg border border-slate-200 md:block" x-data="columnResize()">
                    <table class="w-full min-w-[980px] table-fixed text-left text-[13px]">
                        <thead class="bg-slate-50/80 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                            <tr>
                                @foreach($visibleColumns as $column)
                                    <th class="relative border-b border-slate-200 px-3 py-2" style="width: {{ $columnWidths[$column] ?? '140px' }}">
                                        @if(isset($sortableColumns[$column]))
                                            <button type="button" wire:click="sortTable('{{ $column }}')" class="inline-flex items-center gap-1 text-left hover:text-slate-900">{{ $supplierOfferColumnDefinitions[$column] }} <span class="text-slate-400">{{ $sortIcon($column) }}</span></button>
                                        @else
                                            {{ $supplierOfferColumnDefinitions[$column] }}
                                        @endif
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($externalOffers as $offer)
                                <tr class="transition hover:bg-slate-50/60">
                                    @foreach($visibleColumns as $column)
                                        <td class="overflow-hidden px-3 py-3 align-middle">
                                            @switch($column)
                                                @case('platform')
                                                    <span class="inline-flex max-w-full truncate rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-1 text-xs font-medium text-slate-700">{{ $offer->platform_label }}</span>
                                                    @break
                                                @case('seller')
                                                    <div class="min-w-0"><p class="truncate font-medium text-slate-900" title="{{ $offer->seller_name ?: $offer->title }}">{{ $offer->seller_name ?: $offer->title }}</p><p class="mt-0.5 truncate text-[11px] text-slate-500" title="{{ $offer->title }}">{{ $offer->seller_name ? $offer->title : 'Satıcı adı yayınlanmıyor' }}</p></div>
                                                    @break
                                                @case('price')
                                                    <p class="truncate font-mono font-semibold text-slate-900">{{ $offer->sale_price > 0 ? $this->formatMoney($offer->sale_price) : 'Alınamadı' }}</p>
                                                    @if((float) $offer->price_delta !== 0.0)<p class="mt-0.5 text-[11px] {{ $offer->price_delta < 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $offer->price_delta > 0 ? '+' : '' }}{{ $this->formatMoney($offer->price_delta) }}</p>@endif
                                                    @break
                                                @case('stock')
                                                    <p class="truncate text-xs font-medium {{ $offer->availability === 'out_of_stock' ? 'text-rose-700' : 'text-slate-700' }}">{{ $offer->stock !== null ? number_format($offer->stock, 0, ',', '.').' adet' : ($offer->availability === 'in_stock' ? 'Stokta' : ($offer->availability === 'out_of_stock' ? 'Tükendi' : 'Yayınlanmıyor')) }}</p>
                                                    @break
                                                @case('match')
                                                    <span class="inline-flex rounded-[6px] border border-emerald-200 bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700">%{{ $offer->match_score }} · Güçlü</span>
                                                    @break
                                                @case('radar')
                                                    <p class="truncate text-xs font-medium text-slate-900">{{ $supplierResearch->scan_count >= 2 ? $offer->estimated_sales.' tahmini satış' : '2. taramada başlar' }}</p><p class="mt-0.5 truncate text-[11px] text-slate-500">{{ $offer->previous_stock !== null ? 'Önceki stok '.$offer->previous_stock : 'İlk gözlem' }}</p>
                                                    @break
                                                @case('actions')
                                                    <a href="{{ $offer->source_url }}" target="_blank" rel="noopener" class="inline-flex min-h-[36px] items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50"><x-lucide.icon name="external-link" class="h-3.5 w-3.5" /></a>
                                                    @break
                                            @endswitch
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr><td colspan="{{ max(1, count($visibleColumns)) }}" class="px-4 py-10 text-center text-sm text-slate-500">Diğer pazaryerlerinde eşleşen ürün bulunamadı.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex flex-col gap-2 border-t border-slate-200 pt-4 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between">
                    <span>Aktif sonuç: {{ $externalOffers->count() }} dış teklif · {{ $supplierResearchDashboard['covered_platforms'] ?? 0 }}/{{ $supplierResearchDashboard['target_platforms'] ?? 0 }} kanal bulundu</span>
                    @if($supplierResearch->search_url)<a href="{{ $supplierResearch->search_url }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 font-medium text-slate-700 underline decoration-slate-300 underline-offset-2 hover:text-slate-900">Google Alışveriş’i aç <x-lucide.icon name="external-link" class="h-3.5 w-3.5" /></a>@endif
                </div>
            </div>
        @else
            <div class="p-6 text-center lg:p-10">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-[8px] border border-slate-200 bg-slate-50 text-slate-400"><x-lucide.icon name="radar" class="h-5 w-5" /></div>
                <h3 class="mt-3 text-sm font-semibold text-slate-900">İlk pazar araştırmasını başlatın</h3>
                <p class="mx-auto mt-1 max-w-xl text-sm text-slate-500">Ürün linkini girin; Trendyol satıcıları, diğer satış kanalları, fiyat bandı ve takip için ilk baz ölçüm tek seferde oluşturulsun.</p>
            </div>
        @endif
    </section>
</div>
