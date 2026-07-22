<div
    x-data="{
        extensionReady: false,
        extensionVersion: '',
        sessionReady: null,
        sessionMessage: '',
        sessionRequestId: '',
        extensionMessage: '',
        busy: false,
        requestId: '',
        pendingAnalysisUrl: '',
        fallbackTimer: null,
        scanBusy: false,
        scanMessage: '',
        init() {
            console.log('[Analysis Tab] Sending PING to window...');
            window.postMessage({ source: 'zolm-booster-page', type: 'PING' }, window.location.origin);
        },
        checkSession() {
            this.sessionRequestId = `session-${Date.now()}-${Math.random().toString(16).slice(2)}`;
            window.postMessage({ source: 'zolm-booster-page', type: 'SESSION_CHECK', request_id: this.sessionRequestId }, window.location.origin);
        },
        run(wire) {
            const url = this.$refs.analysisUrl.value.trim();
            if (!url) {
                wire.analyzeResearchProduct();
                return;
            }
            if (!this.extensionReady) {
                this.extensionMessage = 'Chrome Companion bekleniyor. Eklentiyi chrome://extensions ekranından yeniden yükleyip sayfayı yenileyin.';
                this.busy = true;
                this.pendingAnalysisUrl = url;
                this.requestId = '';
                window.postMessage({ source: 'zolm-booster-page', type: 'PING' }, window.location.origin);
                window.clearTimeout(this.fallbackTimer);
                this.fallbackTimer = window.setTimeout(() => {
                    if (!this.busy || this.requestId) return;
                    this.busy = false;
                    this.pendingAnalysisUrl = '';
                    this.extensionMessage = 'Chrome Companion panel köprüsü bulunamadı; sunucu modu şimdilik kapalı.';
                    wire.productAnalysisBridgeCompleted(null, this.extensionMessage, false);
                }, 1800);
                return;
            }
            this.sendExtension(url, wire);
        },
        sendExtension(url, wire) {
            console.log('[Analysis Tab] Sending analysis request to extension for URL:', url);
            this.extensionMessage = '';
            this.busy = true;
            this.pendingAnalysisUrl = '';
            window.clearTimeout(this.fallbackTimer);
            this.requestId = `${Date.now()}-${Math.random().toString(16).slice(2)}`;
            window.postMessage({ source: 'zolm-booster-page', type: 'PRODUCT_ANALYSIS_QUERY', request_id: this.requestId, source_url: url }, window.location.origin);
            window.setTimeout(() => {
                if (!this.busy) return;
                this.busy = false;
                this.extensionMessage = 'Chrome Companion yanıt vermedi; eklentiyi yeniden yükleyip tekrar deneyin.';
                wire.productAnalysisBridgeCompleted(null, this.extensionMessage, false);
            }, 45000);
        },
        async scanBarcode(event, wire) {
            const file = event.target.files?.[0];
            event.target.value = '';
            if (!file) return;
            if (!('BarcodeDetector' in window)) {
                this.scanMessage = 'Bu tarayıcı barkod çözümlemeyi desteklemiyor; kodu elle yazabilirsiniz.';
                return;
            }
            this.scanBusy = true;
            this.scanMessage = 'Barkod cihazda okunuyor...';
            try {
                const bitmap = await createImageBitmap(file);
                const detector = new BarcodeDetector({ formats: ['ean_13', 'ean_8', 'code_128', 'upc_a', 'upc_e', 'qr_code'] });
                const codes = await detector.detect(bitmap);
                bitmap.close?.();
                const value = String(codes?.[0]?.rawValue || '').trim();
                if (!value) throw new Error('Görüntüde okunabilir barkod bulunamadı.');
                this.scanMessage = `${value} bulundu; Trendyol'da aranıyor...`;
                await wire.resolveMobileDiscovery(value);
            } catch (error) {
                this.scanMessage = error?.message || 'Barkod okunamadı.';
            } finally {
                this.scanBusy = false;
            }
        },
        receive(event, wire) {
            const data = event.data || {};
            if (event.origin !== window.location.origin || data.source !== 'zolm-booster-extension') return;
            console.log('[Analysis Tab] Received message from extension bridge:', data);
            if (data.type === 'READY') {
                console.log('[Analysis Tab] ZOLM Chrome Companion is connected successfully! Version:', data.version);
                this.extensionReady = true;
                this.extensionVersion = data.version || '';
                this.extensionMessage = '';
                this.checkSession();
                if (this.busy && this.pendingAnalysisUrl && !this.requestId) {
                    this.sendExtension(this.pendingAnalysisUrl, wire);
                }
                return;
            }
            if (data.type === 'BRIDGE_ERROR') {
                this.extensionReady = false;
                this.extensionMessage = data.message || 'Chrome Companion köprüsü yanıt vermedi.';
                if (this.busy && this.pendingAnalysisUrl && !this.requestId) {
                    this.busy = false;
                    this.pendingAnalysisUrl = '';
                    wire.productAnalysisBridgeCompleted(null, this.extensionMessage, false);
                }
                return;
            }
            if (data.type === 'SESSION_CHECK_RESULT' && (!this.sessionRequestId || data.request_id === this.sessionRequestId)) {
                const response = data.response || {};
                this.sessionReady = Boolean(response.ok);
                this.sessionMessage = response.ok
                    ? `${response.user?.email || 'ZOLM'} oturumu doğrulandı`
                    : (response.message || 'ZOLM oturumu doğrulanamadı.');
                return;
            }
            if (data.type !== 'PRODUCT_ANALYSIS_QUERY_RESULT' || data.request_id !== this.requestId) return;
            this.busy = false;
            this.extensionReady = true;
            this.extensionMessage = '';
            const response = data.response || {};
            if (!response.ok) {
                this.extensionMessage = response.message || 'Chrome Companion canlı analizi tamamlayamadı.';
                wire.productAnalysisBridgeCompleted(null, this.extensionMessage, false);
                return;
            }
            wire.productAnalysisBridgeCompleted(response.analysis?.tracked_product_id || null, response.message || 'Ürün analizi tamamlandı.', Boolean(response.ok));
        }
    }"
    x-on:message.window="receive($event, $wire)"
    class="space-y-4 lg:space-y-6"
>
    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <label class="min-w-0 flex-1">
                <span class="text-xs font-medium text-slate-600">Trendyol ürün linki</span>
                <input x-ref="analysisUrl" type="url" wire:model.live.debounce.350ms="productUrl"
                       placeholder="https://www.trendyol.com/...-p-123456"
                       class="mt-1.5 min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none focus:border-slate-400 sm:py-2 sm:text-sm">
                @error('productUrl')<p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>@enderror
            </label>
            <button type="button" x-on:click="run($wire)" x-bind:disabled="busy" wire:loading.attr="disabled" wire:target="analyzeResearchProduct"
                    class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] bg-slate-900 px-4 py-3 text-base font-medium text-white transition hover:bg-slate-800 disabled:opacity-60 sm:w-auto sm:py-2 sm:text-sm">
                <x-lucide.icon name="search" class="h-4 w-4" />
                <span x-show="!busy" wire:loading.remove wire:target="analyzeResearchProduct">Analiz et</span>
                <span x-show="busy" x-cloak>Canlı veriler okunuyor...</span>
                <span wire:loading wire:target="analyzeResearchProduct">Analiz ediliyor...</span>
            </button>
        </div>
        <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
            <span x-show="extensionReady" x-cloak class="rounded-[6px] border border-emerald-200 bg-emerald-50 px-2 py-1 font-medium text-emerald-700">Chrome Companion hazır <span x-show="extensionVersion" x-text="`v${extensionVersion}`"></span></span>
            <span x-show="sessionReady === true" x-cloak class="rounded-[6px] border border-emerald-200 bg-emerald-50 px-2 py-1 font-medium text-emerald-700" x-text="sessionMessage"></span>
            <span x-show="sessionReady === false" x-cloak class="rounded-[6px] border border-rose-200 bg-rose-50 px-2 py-1 text-rose-700" x-text="sessionMessage"></span>
            <span x-show="!extensionReady" class="rounded-[6px] border border-amber-200 bg-amber-50 px-2 py-1 text-amber-700">Tam analiz için Chrome Companion gerekir; fiyat okunamazsa kayıt açılmaz</span>
            <span x-show="extensionMessage" x-cloak x-text="extensionMessage" class="rounded-[6px] border border-rose-200 bg-rose-50 px-2 py-1 text-rose-700"></span>
            <span class="text-slate-500">Analiz tek seferliktir; sürekli ölçüm yalnızca Takibe Al ile başlar.</span>
        </div>

        <div class="mt-4 rounded-[8px] border border-slate-200 bg-slate-50/60 p-3" data-testid="booster-mobile-barcode-discovery">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <label class="min-w-0 flex-1"><span class="text-xs font-medium text-slate-600">Mobil barkod / ürün kodu</span><input type="text" inputmode="text" wire:model.defer="mobileDiscoveryQuery" wire:keydown.enter="resolveMobileDiscovery" placeholder="Barkodu okutun veya kodu yazın" class="mt-1.5 min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 sm:py-2 sm:text-sm"></label>
                <div class="grid grid-cols-2 gap-2 sm:flex">
                    <label class="inline-flex min-h-[44px] w-full cursor-pointer items-center justify-center gap-2 rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 sm:w-auto sm:py-2"><x-lucide.icon name="scan-line" class="h-4 w-4" /><span x-text="scanBusy ? 'Okunuyor...' : 'Kameradan okut'"></span><input type="file" accept="image/*" capture="environment" class="sr-only" x-on:change="scanBarcode($event, $wire)" x-bind:disabled="scanBusy"></label>
                    <button type="button" wire:click="resolveMobileDiscovery" wire:loading.attr="disabled" wire:target="resolveMobileDiscovery" class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white sm:w-auto sm:py-2">Kodu ara</button>
                </div>
            </div>
            <p class="mt-2 text-xs text-slate-500">Görüntü cihazınızda çözülür; ZOLM'a yalnızca bulunan kod gönderilir.</p>
            <p x-show="scanMessage" x-cloak x-text="scanMessage" class="mt-2 text-xs font-medium text-slate-700"></p>
        </div>
    </section>

    @if(!empty($productAnalysis))
        @php
            $analysisCurrent = $productAnalysis['current'] ?? [];
            $analysisPrevious = $productAnalysis['previous'] ?? null;
            $directCards = [
                ['Fiyat', $analysisCurrent['sale_price'] ?? null, 'money', null],
                ['Stok', $analysisCurrent['stock_quantity'] ?? null, 'integer', 'Trendyol stok adedini bu ilanda yayınlamıyor.'],
                ['Ürün değerlendirme sayısı', $analysisCurrent['evaluation_count'] ?? null, 'integer', 'Trendyol değerlendirme sayısını bu ilanda yayınlamıyor.'],
                ['Ürün yorum sayısı', $analysisCurrent['review_count'] ?? null, 'integer', 'Trendyol yorum sayısını bu ilanda yayınlamıyor.'],
                ['Favori', $analysisCurrent['favorite_count'] ?? null, 'integer', 'Trendyol favori sayısını bu ilanda yayınlamıyor.'],
                ['Sepete eklenme', $analysisCurrent['basket_count'] ?? null, 'integer', 'Trendyol sosyal kanıt servisi bu ürün için sepete eklenme sayısı yayınlamıyor.'],
                ['Son 24 saatte görüntüleme', $analysisCurrent['view_count_24h'] ?? null, 'integer', 'Trendyol sosyal kanıt servisi bu ürün için görüntüleme sayısı yayınlamıyor.'],
                ['Soru-cevap', $analysisCurrent['question_count'] ?? null, 'integer', 'Sayı ürün sayfasında görünürse Chrome Companion tarafından okunur.'],
                ['Kategori sırası', $analysisCurrent['category_rank'] ?? null, 'rank', 'Bu ürün için Trendyol kategori sırası yayınlamıyor.'],
                ['Satıcı puanı', $analysisCurrent['seller_score'] ?? null, 'decimal', 'Trendyol satıcı puanını bu ilanda yayınlamıyor.'],
            ];
            $formatMetric = function (mixed $value, string $type): string {
                if ($value === null || $value === '') return 'Yayınlanmıyor';
                return match($type) {
                    'money' => number_format((float) $value, 2, ',', '.') . ' TL',
                    'decimal' => number_format((float) $value, 1, ',', '.'),
                    'rank' => '#' . number_format((int) $value, 0, ',', '.'),
                    default => number_format((int) $value, 0, ',', '.'),
                };
            };
            $trackingIsActive = ($productAnalysis['tracking_status'] ?? 'candidate') === 'active';
            $stockSampleCount = (int) data_get($analysisCurrent, 'metrics.stock_velocity_24h.sample_count', 0);
            $engagementSampleCount = (int) data_get($analysisCurrent, 'metrics.engagement_velocity_24h.sample_count', 0);
            $observedHours = data_get($analysisCurrent, 'metrics.engagement_velocity_24h.observed_hours')
                ?? data_get($analysisCurrent, 'metrics.stock_velocity_24h.observed_hours');
            $salesWaitingMessage = !$trackingIsActive
                ? 'Takibe Al ile otomatik ölçümü başlatın. En az 30 dakika arayla iki kullanılabilir ölçüm gerekir.'
                : 'Ölçüm sürüyor. En az 30 dakika arayla iki kullanılabilir stok veya etkileşim ölçümü gerekir.';
            $estimateCards = [
                ['Tahmini günlük satış', $analysisCurrent['estimated_daily_sales'] ?? null, 'adet/gün', 'Stok düşüşü / ölçüm süresi × 24', $salesWaitingMessage],
                ['Tahmini günlük ciro', $analysisCurrent['estimated_daily_revenue'] ?? null, 'TL', 'Tahmini günlük satış × güncel fiyat', 'Günlük satış tahmini oluşunca hesaplanır.'],
                ['Stok bitiş tahmini', $analysisCurrent['estimated_days_of_stock'] ?? null, 'gün', 'Mevcut stok / tahmini günlük satış', 'Stok ve sıfırdan büyük günlük satış tahmini gerekir.'],
                ['Dönüşüm tahmini', $analysisCurrent['estimated_conversion_rate'] ?? null, '%', 'Tahmini günlük satış / 24 saat görüntüleme × 100', 'Günlük satış tahmini ile Trendyol 24 saat görüntüleme verisi gerekir.'],
            ];
        @endphp

        <x-zolm.decision-evidence :evidence="$productAnalysis['evidence'] ?? []" />

        <section data-testid="booster-next-action" class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-5">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Sonraki en iyi adım</p>
                    @if((float) ($productAnalysis['current']['confidence_score'] ?? 0) < 50)
                        <h3 class="mt-1 text-base font-semibold text-slate-900">Önce veriyi güçlendirin, sonra maliyet kararına geçin</h3>
                        <p class="mt-1 text-sm text-slate-500">Güven düşük. Ürünü takibe alıp ikinci bir ölçümle satış sinyalini doğrulayın.</p>
                    @elseif((float) $cogs <= 0)
                        <h3 class="mt-1 text-base font-semibold text-slate-900">Alış maliyetini ekleyip finansal kararı tamamlayın</h3>
                        <p class="mt-1 text-sm text-slate-500">Canlı pazar sinyali hazır; net karar için ürün maliyeti, kargo ve komisyonu doğrulayın.</p>
                    @else
                        <h3 class="mt-1 text-base font-semibold text-slate-900">Ürünü finansal karar katmanına taşıyın</h3>
                        <p class="mt-1 text-sm text-slate-500">Gözlenen sinyali maliyetinizle birleştirip Sat veya Satma kararını üretin.</p>
                    @endif
                </div>
                <div class="grid w-full grid-cols-1 gap-2 sm:w-auto sm:grid-cols-2">
                    <button
                        type="button"
                        wire:click="openTrackedProductFinance({{ $productAnalysis['tracked_product_id'] }})"
                        class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-base font-medium text-slate-700 transition hover:bg-slate-50 sm:py-2 sm:text-sm"
                    >
                        <x-lucide.icon name="calculator" class="h-4 w-4" /> Kâr-Zarar hesabı
                    </button>
                    <button
                        type="button"
                        wire:click="openTrackedProductSellDecision({{ $productAnalysis['tracked_product_id'] }})"
                        class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-[6px] bg-slate-900 px-4 py-3 text-base font-medium text-white transition hover:bg-slate-800 sm:py-2 sm:text-sm"
                    >
                        <x-lucide.icon name="activity" class="h-4 w-4" /> Sat veya Satma
                    </button>
                </div>
            </div>
        </section>

        <section data-testid="booster-product-analysis" class="overflow-hidden rounded-[10px] border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-4 border-b border-slate-200 p-4 sm:flex-row sm:items-start sm:justify-between lg:p-6">
                <div class="flex min-w-0 items-start gap-3">
                    <div class="h-16 w-16 shrink-0 overflow-hidden rounded-[8px] border border-slate-200 bg-slate-50">
                        @if(!empty($productAnalysis['image_url']))
                            <img src="{{ $productAnalysis['image_url'] }}" alt="" class="h-full w-full object-cover">
                        @endif
                    </div>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-2 py-1 font-mono text-[11px] text-slate-500">ID {{ $productAnalysis['trendyol_product_id'] ?: '-' }}</span>
                            <span class="rounded-[6px] border {{ ($productAnalysis['tracking_status'] ?? 'candidate') === 'active' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700' }} px-2 py-1 text-[11px] font-medium">
                                {{ ($productAnalysis['tracking_status'] ?? 'candidate') === 'active' ? 'Takipte' : 'Tek seferlik analiz' }}
                            </span>
                        </div>
                        <h2 class="mt-2 text-base font-semibold text-slate-900 lg:text-lg">{{ $productAnalysis['title'] ?: 'Trendyol ürünü' }}</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ collect([$productAnalysis['brand'] ?? null, $productAnalysis['category_name'] ?? null])->filter()->join(' · ') }}</p>
                    </div>
                </div>
                @if(($productAnalysis['tracking_status'] ?? 'candidate') !== 'active')
                    <button type="button" wire:click="followTrackedProduct({{ $productAnalysis['tracked_product_id'] }}, 'product_analysis')"
                            class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] bg-slate-900 px-4 py-2 text-sm font-medium text-white sm:w-auto">
                        <x-lucide.icon name="radar" class="h-4 w-4" /> Takibe Al
                    </button>
                @else
                    <button type="button" wire:click="setActiveModule('tracking')" class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 sm:w-auto">
                        Booster Radar'da aç
                    </button>
                @endif
            </div>

            <div class="grid grid-cols-1 gap-3 p-4 sm:grid-cols-2 xl:grid-cols-3 lg:gap-4 lg:p-6">
                @foreach($directCards as [$label, $value, $type, $missingReason])
                    <div class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                        <p class="text-xs text-slate-500">{{ $label }}</p>
                        <p class="mt-1 text-base font-semibold text-slate-900">{{ $formatMetric($value, $type) }}</p>
                        @if(($value === null || $value === '') && $missingReason)
                            <p class="mt-1 text-xs leading-4 text-slate-500">{{ $missingReason }}</p>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="border-t border-slate-200 p-4 lg:p-6">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase text-slate-500">Booster Intelligence</p>
                        <h3 class="mt-1 text-base font-semibold text-slate-900">Hesaplanan sinyaller</h3>
                    </div>
                    <span class="rounded-[6px] border border-sky-200 bg-sky-50 px-2 py-1 font-mono text-xs text-sky-700">Güven %{{ $analysisCurrent['confidence_score'] ?? 0 }}</span>
                </div>
                @if(($analysisCurrent['estimated_daily_sales'] ?? null) === null)
                    <div class="mt-3 rounded-[8px] border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                        <p class="font-medium">Ölçüm geçmişi bekleniyor</p>
                        <p class="mt-1 text-xs leading-5 text-amber-800">
                            {{ $salesWaitingMessage }}
                            Mevcut veri: {{ max($stockSampleCount, $engagementSampleCount) }} ölçüm{{ $observedHours !== null ? ', ' . number_format((float) $observedHours, 2, ',', '.') . ' saat gözlem' : '' }}.
                        </p>
                    </div>
                @endif
                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach($estimateCards as [$label, $value, $suffix, $formula, $missingReason])
                        <div class="rounded-[8px] border border-slate-200 bg-white p-3">
                            <div class="flex items-center justify-between gap-2"><p class="text-xs text-slate-500">{{ $label }}</p><span class="text-[10px] font-medium text-amber-700">TAHMİNİ</span></div>
                            <p class="mt-1 text-base font-semibold text-slate-900">{{ $value === null ? 'Veri bekleniyor' : number_format((float) $value, 2, ',', '.') . ' ' . $suffix }}</p>
                            <p class="mt-1 text-xs leading-4 text-slate-500">{{ $value === null ? $missingReason : $formula }}</p>
                            <p class="mt-2 font-mono text-[10px] leading-4 text-slate-400">{{ $formula }}</p>
                        </div>
                    @endforeach
                </div>
                <div class="mt-3 grid grid-cols-3 gap-3">
                    @foreach([['İlgi', $analysisCurrent['interest_score'] ?? 0], ['Rekabet', $analysisCurrent['competition_score'] ?? 0], ['Risk', $analysisCurrent['risk_score'] ?? 0]] as [$label, $value])
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3 text-center"><p class="text-xs text-slate-500">{{ $label }}</p><p class="mt-1 font-mono text-lg font-semibold text-slate-900">{{ $value }}/100</p></div>
                    @endforeach
                </div>
            </div>

            <div class="border-t border-slate-200 p-4 lg:p-6">
                <div class="flex items-center justify-between gap-3"><h3 class="text-sm font-semibold text-slate-900">Yeniden eskiye son 10 yorum</h3><span class="text-xs text-slate-500">{{ count($productAnalysis['recent_reviews'] ?? []) }} yorum</span></div>
                <div class="mt-3 divide-y divide-slate-100 overflow-hidden rounded-lg border border-slate-200">
                    @forelse($productAnalysis['recent_reviews'] ?? [] as $review)
                        <div class="grid grid-cols-1 gap-2 px-3 py-3 sm:grid-cols-[140px_56px_minmax(0,1fr)] sm:items-start"><div><p class="text-xs font-medium text-slate-900">{{ $review['user_name'] ?? 'Anonim' }}</p><p class="mt-0.5 text-[11px] text-slate-400">{{ $review['seller_name'] ?? '' }}</p></div><p class="font-mono text-xs font-semibold text-amber-700">{{ $review['rate'] ?? 0 }}/5</p><p class="text-sm leading-5 text-slate-600">{{ $review['comment'] ?? '' }}</p></div>
                    @empty
                        <p class="px-3 py-4 text-sm text-slate-500">Bu taramada yorum verisi alınamadı.</p>
                    @endforelse
                </div>
            </div>
        </section>
    @else
        <section class="rounded-[10px] border border-dashed border-slate-300 bg-white p-6 text-center shadow-sm">
            <x-lucide.icon name="scan-search" class="mx-auto h-8 w-8 text-slate-400" />
            <h2 class="mt-3 text-base font-semibold text-slate-900">Henüz ürün analiz edilmedi</h2>
            <p class="mt-1 text-sm text-slate-500">Trendyol linkini girerek doğrudan yayınlanan alanları ve ilk karar sinyallerini alın.</p>
        </section>
    @endif

    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase text-slate-500">Analiz ledger</p>
                <h2 class="mt-1 text-lg font-semibold text-slate-900">Analiz edilmiş ürünler</h2>
                <p class="mt-1 text-sm text-slate-500">Tek seferlik analizleri inceleyin veya Booster Radar takibine taşıyın.</p>
            </div>
            <button type="button" wire:click="toggleFavoritesOnly" class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 sm:w-auto"><x-lucide.icon name="heart" class="h-4 w-4 {{ $favoritesOnly ? 'fill-rose-500 text-rose-500' : '' }}" /> Favoriler</button>
        </div>
        @if($favoritesOnly)<p class="mt-3 text-xs font-medium text-rose-700">Yalnızca favoriler gösteriliyor.</p>@endif
        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @forelse($dashboard['products'] as $tracked)
                <article class="rounded-[8px] border border-slate-200 bg-white p-3">
                    <div class="flex items-start gap-3">
                        <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-slate-900">{{ $tracked->title ?: 'Trendyol ürünü' }}</p><p class="mt-1 truncate text-xs text-slate-500">{{ $tracked->brand ?: '-' }} · {{ $tracked->category_name ?: '-' }}</p></div>
                        <button type="button" wire:click="toggleProductFavorite({{ $tracked->id }})" class="inline-flex h-10 w-10 items-center justify-center rounded-[6px] border border-slate-200 text-slate-500"><x-lucide.icon name="heart" class="h-4 w-4 {{ $tracked->is_favorite ? 'fill-rose-500 text-rose-500' : '' }}" /></button>
                    </div>
                    <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <span class="text-sm font-semibold text-slate-900">{{ $this->formatMoney($tracked->sale_price) }}</span>
                        <div class="flex items-center gap-2">
                            <button type="button" wire:click="loadTrackedProduct({{ $tracked->id }})" class="inline-flex min-h-10 flex-1 items-center justify-center gap-1.5 rounded-[6px] border border-slate-200 bg-white px-3 text-xs font-medium text-slate-700 sm:flex-none">
                                <x-lucide.icon name="eye" class="h-4 w-4" /> İncele
                            </button>
                            @if($tracked->tracking_status === 'active')
                                <span class="inline-flex min-h-10 items-center rounded-[6px] border border-emerald-200 bg-emerald-50 px-3 text-[11px] font-medium text-emerald-700">Takipte</span>
                            @else
                                <button type="button" wire:click="followTrackedProduct({{ $tracked->id }}, 'product_analysis')" class="min-h-10 flex-1 rounded-[6px] bg-slate-900 px-3 text-xs font-medium text-white sm:flex-none">Takibe al</button>
                            @endif
                        </div>
                    </div>
                </article>
            @empty
                <div class="rounded-[8px] border border-dashed border-slate-300 bg-slate-50/70 p-5 text-sm text-slate-500 sm:col-span-2 xl:col-span-3">Filtrelerle eşleşen analiz kaydı bulunamadı.</div>
            @endforelse
        </div>
    </section>
</div>
