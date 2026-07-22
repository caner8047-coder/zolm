@php
    $isMarketResearch = ($researchKind ?? 'comparison') === 'market';
    $urlProperty = $isMarketResearch ? 'marketUrls' : 'comparisonUrls';
    $results = $isMarketResearch ? $marketResults : $comparisonResults;
    $summary = $isMarketResearch ? $marketSummary : $comparisonSummary;
    $runMethod = $isMarketResearch ? 'runMarketComparison' : 'runProductComparison';
@endphp

<div
    x-data="{
        extensionReady: false,
        extensionMessage: '',
        busy: false,
        autoStart: @js(!$isMarketResearch && $comparisonAutoStart),
        autoStarted: false,
        wireRef: null,
        init() { window.postMessage({ source: 'zolm-booster-page', type: 'PING' }, window.location.origin); },
        receive(event) {
            const data = event.data || {};
            if (event.origin !== window.location.origin || data.source !== 'zolm-booster-extension') return;
            if (data.type === 'READY') {
                this.extensionReady = true;
                this.extensionMessage = '';
                this.maybeAutoRun();
                return;
            }
            if (data.type === 'BRIDGE_ERROR') {
                this.extensionReady = false;
                this.extensionMessage = data.message || 'Chrome Companion köprüsü yanıt vermedi.';
            }
        },
        maybeAutoRun() {
            if (!this.autoStart || this.autoStarted || this.busy || !this.extensionReady || !this.wireRef) return;
            const urlCount = Array.from(this.$root.querySelectorAll('[data-research-url]')).filter((input) => input.value.trim()).length;
            if (urlCount < 2) return;
            this.autoStarted = true;
            this.run(this.wireRef);
        },
        query(url, timeoutMs = 50000) {
            return new Promise((resolve, reject) => {
                const requestId = `${Date.now()}-${Math.random().toString(16).slice(2)}`;
                const timer = window.setTimeout(() => { window.removeEventListener('message', handler); reject(new Error('Trendyol sayfası zaman aşımına uğradı.')); }, timeoutMs);
                const handler = (event) => {
                    const data = event.data || {};
                    if (event.origin !== window.location.origin || data.source !== 'zolm-booster-extension' || data.type !== 'PRODUCT_ANALYSIS_QUERY_RESULT' || data.request_id !== requestId) return;
                    window.clearTimeout(timer); window.removeEventListener('message', handler);
                    const response = data.response || {};
                    if (!response.ok || !response.payload) { reject(new Error(response.message || 'Ürün okunamadı.')); return; }
                    resolve(response.payload);
                };
                window.addEventListener('message', handler);
                window.postMessage({ source: 'zolm-booster-page', type: 'PRODUCT_ANALYSIS_QUERY', request_id: requestId, source_url: url }, window.location.origin);
            });
        },
        async run(wire) {
            const urls = Array.from(this.$root.querySelectorAll('[data-research-url]')).map((input) => input.value.trim()).filter(Boolean);
            if (!this.extensionReady) { wire.{{ $runMethod }}(); return; }
            if (urls.length < 2) { wire.{{ $runMethod }}(); return; }
            this.busy = true;
            this.extensionMessage = '';
            try {
                const payloads = [];
                for (const url of urls) payloads.push(await this.query(url));
                this.extensionReady = true;
                this.extensionMessage = '';
                await wire.comparisonBridgeCompleted(payloads, '{{ $researchKind }}', '', true);
            } catch (error) {
                this.extensionMessage = error.message || 'Chrome Companion yanıtı başarısız oldu; sunucu karşılaştırması çalıştırılıyor.';
                await wire.{{ $runMethod }}();
            } finally {
                this.busy = false;
                if (this.autoStart) {
                    this.autoStart = false;
                    await wire.set('comparisonAutoStart', false);
                }
            }
        }
    }"
    x-init="wireRef = $wire; maybeAutoRun()"
    x-on:message.window="receive($event)"
    class="space-y-4 lg:space-y-6"
>
    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase text-slate-500">{{ $isMarketResearch ? 'Rakip grubu' : 'Karşılaştırma seti' }}</p>
                <h2 class="mt-1 text-lg font-semibold text-slate-900">2–4 Trendyol ürünü ekleyin</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $isMarketResearch ? 'Pazarın fiyat aralığını ve ilgi yoğunluğunu ürün grubuyla ölçün.' : 'Benzer ürünleri aynı veri sözlüğüyle yan yana değerlendirin.' }}</p>
            </div>
            <span x-show="extensionReady" x-cloak class="rounded-[6px] border border-emerald-200 bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700">Canlı tarayıcı verisi</span>
            <span x-show="!extensionReady" class="rounded-[6px] border border-amber-200 bg-amber-50 px-2 py-1 text-xs text-amber-700">Sunucu okuyucusu hazır</span>
            <span x-show="extensionMessage" x-cloak x-text="extensionMessage" class="rounded-[6px] border border-rose-200 bg-rose-50 px-2 py-1 text-xs text-rose-700"></span>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
            @foreach(range(0, 3) as $index)
                <label class="min-w-0">
                    <span class="text-xs font-medium text-slate-600">Ürün {{ $index + 1 }} {{ $index < 2 ? '· zorunlu' : '· isteğe bağlı' }}</span>
                    <input data-research-url type="url" wire:model.defer="{{ $urlProperty }}.{{ $index }}" placeholder="https://www.trendyol.com/...-p-..."
                           class="mt-1.5 min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none focus:border-slate-400 sm:py-2 sm:text-sm">
                </label>
            @endforeach
        </div>
        <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:justify-end">
            @if($results !== [])
                <button type="button" wire:click="trackAllResearchProducts('{{ $researchKind }}')"
                        class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 sm:w-auto">
                    <x-lucide.icon name="radar" class="h-4 w-4" /> Tümünü takibe al
                </button>
            @endif
            <button type="button" x-on:click="run($wire)" x-bind:disabled="busy" wire:loading.attr="disabled" wire:target="{{ $runMethod }}"
                    class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] bg-slate-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-60 sm:w-auto">
                <x-lucide.icon name="columns-3" class="h-4 w-4" />
                <span x-show="!busy" wire:loading.remove wire:target="{{ $runMethod }}">{{ $isMarketResearch ? 'Pazarı karşılaştır' : 'Ürünleri karşılaştır' }}</span>
                <span x-show="busy" x-cloak>Canlı ürünler okunuyor...</span>
                <span wire:loading wire:target="{{ $runMethod }}">Hesaplanıyor...</span>
            </button>
        </div>
    </section>

    @if($results !== [])
        <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach([
                    ['Ürün', $summary['product_count'] ?? count($results), ''],
                    ['En düşük', $summary['minimum_price'] ?? null, ' TL'],
                    ['Ortalama', $summary['average_price'] ?? null, ' TL'],
                    ['Fiyat aralığı', $summary['price_spread_percent'] ?? null, '%'],
                ] as [$label, $value, $suffix])
                    <div class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                        <p class="text-xs text-slate-500">{{ $label }}</p>
                        <p class="mt-1 truncate text-base font-semibold text-slate-900">{{ $value === null ? '-' : number_format((float) $value, $suffix === ' TL' ? 2 : 1, ',', '.') . $suffix }}</p>
                    </div>
                @endforeach
            </div>

            <div class="mt-4 hidden overflow-x-auto rounded-lg border border-slate-200 md:block">
                <table class="w-full table-fixed text-left">
                    <thead class="bg-slate-50/70 text-xs font-semibold uppercase text-slate-500">
                        <tr>
                            <th class="w-[32%] px-3 py-3">Ürün</th>
                            <th class="px-3 py-3">Fiyat</th>
                            <th class="px-3 py-3">Puan</th>
                            <th class="px-3 py-3">Favori</th>
                            <th class="px-3 py-3">Stok</th>
                            <th class="px-3 py-3">Skor</th>
                            <th class="w-36 px-3 py-3">Aksiyon</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($results as $index => $row)
                            <tr wire:key="research-{{ $researchKind }}-{{ data_get($row, 'page.trendyol_product_id', $index) }}" class="text-sm">
                                <td class="px-3 py-3"><p class="truncate font-medium text-slate-900">{{ data_get($row, 'page.title', 'Trendyol ürünü') }}</p><p class="mt-0.5 truncate text-xs text-slate-500">{{ data_get($row, 'page.brand', '-') }}</p></td>
                                <td class="px-3 py-3 font-medium text-slate-900">{{ number_format((float) data_get($row, 'page.sale_price', 0), 2, ',', '.') }} TL</td>
                                <td class="px-3 py-3 text-slate-700">{{ data_get($row, 'metrics.average_rating') !== null ? number_format((float) data_get($row, 'metrics.average_rating'), 1, ',', '.') : '-' }}</td>
                                <td class="px-3 py-3 text-slate-700">{{ data_get($row, 'metrics.favorite_count') !== null ? number_format((int) data_get($row, 'metrics.favorite_count'), 0, ',', '.') : '-' }}</td>
                                <td class="px-3 py-3 text-slate-700">{{ data_get($row, 'page.total_stock') !== null ? number_format((int) data_get($row, 'page.total_stock'), 0, ',', '.') : '-' }}</td>
                                <td class="px-3 py-3"><span class="rounded-[6px] border border-sky-200 bg-sky-50 px-2 py-1 font-mono text-xs text-sky-700">{{ $row['comparison_score'] ?? 0 }}/100</span></td>
                                <td class="px-3 py-3"><button type="button" wire:click="trackResearchProduct('{{ $researchKind }}', {{ $index }})" class="min-h-[40px] w-full rounded-[6px] bg-slate-900 px-3 text-xs font-medium text-white">Takibe al</button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-3 md:hidden">
                @foreach($results as $index => $row)
                    <article class="rounded-[8px] border border-slate-200 bg-white p-3">
                        <div class="flex items-start justify-between gap-3"><div class="min-w-0"><p class="truncate text-sm font-semibold text-slate-900">{{ data_get($row, 'page.title', 'Trendyol ürünü') }}</p><p class="mt-1 text-xs text-slate-500">{{ data_get($row, 'page.brand', '-') }}</p></div><span class="rounded-[6px] border border-sky-200 bg-sky-50 px-2 py-1 font-mono text-xs text-sky-700">{{ $row['comparison_score'] ?? 0 }}</span></div>
                        <div class="mt-3 grid grid-cols-3 gap-2 text-xs"><div><p class="text-slate-400">Fiyat</p><p class="mt-1 font-medium text-slate-900">{{ number_format((float) data_get($row, 'page.sale_price', 0), 2, ',', '.') }} TL</p></div><div><p class="text-slate-400">Puan</p><p class="mt-1 font-medium text-slate-900">{{ data_get($row, 'metrics.average_rating', '-') }}</p></div><div><p class="text-slate-400">Favori</p><p class="mt-1 font-medium text-slate-900">{{ data_get($row, 'metrics.favorite_count') !== null ? number_format((int) data_get($row, 'metrics.favorite_count'), 0, ',', '.') : '-' }}</p></div></div>
                        <button type="button" wire:click="trackResearchProduct('{{ $researchKind }}', {{ $index }})" class="mt-3 min-h-[44px] w-full rounded-[6px] bg-slate-900 px-3 text-sm font-medium text-white">Takibe al</button>
                    </article>
                @endforeach
            </div>
        </section>
    @endif
</div>
