@php
    $simulation = $preview['simulation'];
    $breakdown = $simulation['breakdown'];
    $score = (int) $preview['score'];
    $decision = (string) $preview['decision'];
    $scoreWidth = min(100, max(0, $score));
    $costReady = (bool) ($preview['normalized']['cost_ready'] ?? false);
@endphp

<div class="flex flex-col gap-4 p-4 lg:gap-6 lg:p-6">
    @unless($tablesReady)
        <section class="rounded-[10px] border border-amber-200 bg-amber-50 p-4 shadow-sm lg:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-amber-700">Kurulum</p>
                    <h2 class="mt-2 text-xl font-bold text-amber-950 lg:text-2xl">Trendyol Booster tablosu hazır değil</h2>
                    <p class="mt-1 text-sm text-amber-800">Migration sonrası takip kayıtları ve analiz geçmişi açılacak.</p>
                </div>
                <div class="rounded-[8px] border border-amber-200 bg-white px-3 py-2 font-mono text-sm text-amber-900">
                    php artisan migrate
                </div>
            </div>
        </section>
    @endunless

    @if($tablesReady && !$snapshotsReady)
        <section class="rounded-[10px] border border-amber-200 bg-amber-50 p-4 shadow-sm lg:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-amber-700">Faz 3</p>
                    <h2 class="mt-2 text-xl font-bold text-amber-950 lg:text-2xl">Snapshot tablosu hazır değil</h2>
                    <p class="mt-1 text-sm text-amber-800">Fiyat/stok kontrolleri migration sonrası açılacak.</p>
                </div>
                <div class="rounded-[8px] border border-amber-200 bg-white px-3 py-2 font-mono text-sm text-amber-900">
                    php artisan migrate
                </div>
            </div>
        </section>
    @endif

    @if($tablesReady && !$competitorsReady)
        <section class="rounded-[10px] border border-amber-200 bg-amber-50 p-4 shadow-sm lg:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-amber-700">Faz 4</p>
                    <h2 class="mt-2 text-xl font-bold text-amber-950 lg:text-2xl">Rakip radarı tablosu hazır değil</h2>
                    <p class="mt-1 text-sm text-amber-800">Rakip linkleri ve fırsat kuyruğu migration sonrası açılacak.</p>
                </div>
                <div class="rounded-[8px] border border-amber-200 bg-white px-3 py-2 font-mono text-sm text-amber-900">
                    php artisan migrate
                </div>
            </div>
        </section>
    @endif

    @if($tablesReady && !$keywordsReady)
        <section class="rounded-[10px] border border-amber-200 bg-amber-50 p-4 shadow-sm lg:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-amber-700">Faz 5</p>
                    <h2 class="mt-2 text-xl font-bold text-amber-950 lg:text-2xl">Kelime radarı tablosu hazır değil</h2>
                    <p class="mt-1 text-sm text-amber-800">Anahtar kelime görünürlük takibi migration sonrası açılacak.</p>
                </div>
                <div class="rounded-[8px] border border-amber-200 bg-white px-3 py-2 font-mono text-sm text-amber-900">
                    php artisan migrate
                </div>
            </div>
        </section>
    @endif

    @if($tablesReady && !$costPresetsReady)
        <section class="rounded-[10px] border border-amber-200 bg-amber-50 p-4 shadow-sm lg:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-amber-700">Faz 6</p>
                    <h2 class="mt-2 text-xl font-bold text-amber-950 lg:text-2xl">Maliyet preset tablosu hazır değil</h2>
                    <p class="mt-1 text-sm text-amber-800">Komisyon-kargo kütüphanesi migration sonrası açılacak.</p>
                </div>
                <div class="rounded-[8px] border border-amber-200 bg-white px-3 py-2 font-mono text-sm text-amber-900">
                    php artisan migrate
                </div>
            </div>
        </section>
    @endif

    @if($tablesReady && !$campaignsReady)
        <section class="rounded-[10px] border border-amber-200 bg-amber-50 p-4 shadow-sm lg:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-amber-700">Faz 7</p>
                    <h2 class="mt-2 text-xl font-bold text-amber-950 lg:text-2xl">Kampanya senaryo tablosu hazır değil</h2>
                    <p class="mt-1 text-sm text-amber-800">Kampanya karar entegrasyonu migration sonrası açılacak.</p>
                </div>
                <div class="rounded-[8px] border border-amber-200 bg-white px-3 py-2 font-mono text-sm text-amber-900">
                    php artisan migrate
                </div>
            </div>
        </section>
    @endif

    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
        <div class="grid grid-cols-1 gap-4 xl:grid-cols-12 xl:items-start lg:gap-6">
            <div class="min-w-0 xl:col-span-7">
                <div class="inline-flex items-center gap-2 rounded-[6px] border border-slate-200 bg-slate-50/70 px-2.5 py-1 font-mono text-xs text-slate-500">
                    <x-lucide.icon name="activity" class="h-3.5 w-3.5" />
                    {{ $workspaceCopy['eyebrow'] }}
                </div>
                <h1 class="mt-3 text-xl font-bold text-slate-900 lg:text-2xl">{{ $workspaceCopy['title'] }}</h1>
                <span class="sr-only">Ürün karar radarı</span>
                <p class="mt-1 max-w-2xl text-sm text-slate-500">
                    {{ $workspaceCopy['description'] }}
                </p>
            </div>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 xl:col-span-5">
                <div class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                    <p class="text-xs text-slate-500">Takip</p>
                    <p class="mt-1 truncate text-lg font-semibold text-slate-900">{{ $dashboard['total'] }}</p>
                </div>
                <div class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                    <p class="text-xs text-slate-500">Fırsat</p>
                    <p class="mt-1 truncate text-lg font-semibold text-emerald-700">{{ $dashboard['strong_count'] }}</p>
                </div>
                <div class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                    <p class="text-xs text-slate-500">Risk</p>
                    <p class="mt-1 truncate text-lg font-semibold text-rose-700">{{ $dashboard['risk_count'] }}</p>
                </div>
                <div class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                    <p class="text-xs text-slate-500">Ortalama skor</p>
                    <p class="mt-1 truncate text-lg font-semibold text-slate-900">{{ number_format((float) $dashboard['average_score'], 1, ',', '.') }}</p>
                </div>
            </div>
        </div>
    </section>

    <section
        class="rounded-[10px] border border-slate-200 bg-white p-3 shadow-sm lg:p-4"
        x-data="{
            moduleSearch: '',
            shortcutDragging: false,
            shortcutMoved: false,
            suppressShortcutClick: false,
            shortcutStartX: 0,
            shortcutScrollLeft: 0,
            showLeftFade: false,
            showRightFade: true,
            checkScrollFade() {
                if (!this.$refs.shortcutScroller) return;
                const { scrollLeft, scrollWidth, clientWidth } = this.$refs.shortcutScroller;
                this.showLeftFade = scrollLeft > 0;
                this.showRightFade = Math.ceil(scrollLeft + clientWidth) < scrollWidth;
            },
            init() {
                this.$nextTick(() => { this.checkScrollFade(); });
                window.addEventListener('resize', () => { this.checkScrollFade(); });
            },
            normalize(value) {
                return (value || '').toString().toLocaleLowerCase('tr-TR');
            },
            moduleMatches(value) {
                const term = this.normalize(this.moduleSearch.trim());
                return term === '' || this.normalize(value).includes(term);
            },
            startShortcutDrag(event) {
                if (event.button !== 0 || !this.$refs.shortcutScroller) {
                    return;
                }

                this.shortcutDragging = true;
                this.shortcutMoved = false;
                this.shortcutStartX = event.pageX;
                this.shortcutScrollLeft = this.$refs.shortcutScroller.scrollLeft;
            },
            moveShortcutDrag(event) {
                if (!this.shortcutDragging || !this.$refs.shortcutScroller) {
                    return;
                }

                const distance = event.pageX - this.shortcutStartX;

                if (Math.abs(distance) > 4) {
                    this.shortcutMoved = true;
                    this.suppressShortcutClick = true;
                }

                if (this.shortcutMoved) {
                    event.preventDefault();
                    this.$refs.shortcutScroller.scrollLeft = this.shortcutScrollLeft - distance;
                    this.checkScrollFade();
                }
            },
            endShortcutDrag() {
                this.shortcutDragging = false;
                this.shortcutMoved = false;
            },
            cancelShortcutClick(event) {
                if (!this.suppressShortcutClick) {
                    return;
                }

                event.preventDefault();
                event.stopImmediatePropagation();
                this.suppressShortcutClick = false;
            }
        }"
    >
        <div class="flex flex-col gap-3 xl:flex-row xl:items-center">
            <label class="group flex min-h-[44px] w-full shrink-0 items-center overflow-hidden rounded-[8px] border border-slate-200 bg-white shadow-sm transition focus-within:border-slate-400 focus-within:ring-4 focus-within:ring-slate-100 xl:w-[275px]">
                <span class="sr-only">Booster modülü ara</span>
                <span class="pointer-events-none flex h-11 w-10 shrink-0 items-center justify-center border-r border-slate-100 bg-slate-50/70 text-slate-400 transition group-focus-within:text-slate-700">
                    <x-lucide.icon name="search" class="h-4 w-4" />
                </span>
                <input
                    type="search"
                    x-model="moduleSearch"
                    x-ref="moduleSearchInput"
                    @keydown.escape="moduleSearch = ''"
                    data-testid="booster-module-search"
                    placeholder="Modül ara..."
                    class="h-11 min-w-0 flex-1 appearance-none border-0 bg-transparent px-3 text-base font-medium text-slate-900 outline-none placeholder:font-normal placeholder:text-slate-400 sm:text-sm"
                >
                <button
                    type="button"
                    x-show="moduleSearch.length > 0"
                    x-cloak
                    @click.prevent="moduleSearch = ''; $refs.moduleSearchInput.focus()"
                    class="mr-2 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-[6px] text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                    aria-label="Modül aramasını temizle"
                >
                    <x-lucide.icon name="x" class="h-3.5 w-3.5" />
                </button>
            </label>

            <div class="relative flex min-w-0 flex-1 items-center overflow-hidden">
                {{-- Left Fade --}}
                <div x-show="showLeftFade" x-transition.opacity class="pointer-events-none absolute bottom-0 left-0 top-0 z-10 w-8 bg-gradient-to-r from-white to-transparent"></div>

                <div
                    x-ref="shortcutScroller"
                    data-testid="booster-module-tabs"
                    @pointerdown="startShortcutDrag($event)"
                    @pointermove.window="moveShortcutDrag($event)"
                    @pointerup.window="endShortcutDrag()"
                    @pointercancel.window="endShortcutDrag()"
                    @click.capture="cancelShortcutClick($event)"
                    @scroll.passive="checkScrollFade()"
                    class="flex min-w-0 flex-1 snap-x gap-2 overflow-x-auto pb-1 pr-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                    :class="shortcutDragging ? 'cursor-grabbing select-none' : 'cursor-grab'"
                >
                @foreach($moduleGroups as $group)
                    @foreach($group['items'] as $item)
                        @php
                            $isSoon = (bool) ($item['soon'] ?? false);
                            $isFavoriteShortcut = (bool) data_get($item, 'query.favorites', false);
                            $moduleKey = $item['module'] ?? null;
                            $searchText = trim($item['label'] . ' ' . $group['label'] . ($isSoon ? ' Yakında' : ''));
                            $isActiveShortcut = $isFavoriteShortcut
                                ? $activeModule === 'tracking' && $favoritesOnly
                                : $activeModule === $moduleKey && ! ($moduleKey === 'tracking' && $favoritesOnly);
                            $shortcutKey = $isFavoriteShortcut ? 'favorites' : ($moduleKey ?: \Illuminate\Support\Str::slug($item['label']));
                        @endphp

                        @if($isSoon)
                            <button
                                type="button"
                                disabled
                                wire:key="booster-shortcut-{{ $group['key'] }}-{{ $shortcutKey }}"
                                x-show="moduleMatches(@js($searchText))"
                                title="{{ $item['label'] }} yakında kullanıma açılacak"
                                class="inline-flex min-h-[44px] shrink-0 snap-start cursor-not-allowed items-center justify-center gap-2 rounded-[6px] border border-slate-200 bg-slate-50/70 px-3 py-2 text-sm font-medium text-slate-400"
                            >
                                <x-lucide.icon name="{{ $item['icon'] }}" class="h-4 w-4" />
                                <span>{{ $item['label'] }}</span>
                                <span class="inline-flex items-center gap-1 rounded-full border border-amber-200 bg-amber-50 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700">
                                    <x-lucide.icon name="clock" class="h-2.5 w-2.5" />
                                    Yakında
                                </span>
                            </button>
                        @elseif($isFavoriteShortcut)
                            <button
                                type="button"
                                wire:key="booster-shortcut-{{ $group['key'] }}-favorites"
                                wire:click="openFavorites"
                                title="{{ $group['label'] }}"
                                x-show="moduleMatches(@js($searchText))"
                                class="inline-flex min-h-[44px] shrink-0 snap-start items-center justify-center gap-2 rounded-[6px] border px-3 py-2 text-sm font-medium transition {{ $isActiveShortcut ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}"
                            >
                                <x-lucide.icon name="{{ $item['icon'] }}" class="h-4 w-4 {{ $isActiveShortcut ? 'fill-white/20' : '' }}" />
                                {{ $item['label'] }}
                            </button>
                        @else
                            <button
                                type="button"
                                wire:key="booster-shortcut-{{ $group['key'] }}-{{ $shortcutKey }}"
                                wire:click="setActiveModule('{{ $moduleKey }}')"
                                title="{{ $group['label'] }}"
                                x-show="moduleMatches(@js($searchText))"
                                class="inline-flex min-h-[44px] shrink-0 snap-start items-center justify-center gap-2 rounded-[6px] border px-3 py-2 text-sm font-medium transition {{ $isActiveShortcut ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}"
                            >
                                <x-lucide.icon name="{{ $item['icon'] }}" class="h-4 w-4 {{ $isActiveShortcut ? 'fill-white/20' : '' }}" />
                                {{ $item['label'] }}
                            </button>
                        @endif
                    @endforeach
                @endforeach
            </div>

            {{-- Right Fade --}}
            <div x-show="showRightFade" x-transition.opacity class="pointer-events-none absolute bottom-0 right-0 top-0 z-10 w-8 bg-gradient-to-l from-white to-transparent"></div>
        </div>
    </section>

    @if($activeModule === 'decision' && (int) $dashboard['total'] === 0)
        <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase text-slate-500">Booster modülleri</p>
                    <h2 class="mt-1 text-lg font-semibold text-slate-900">Takibe alınan ürünlerde açılacak yüzeyler</h2>
                </div>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    <span class="rounded-[6px] border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">Analiz aktif</span>
                    <span class="rounded-[6px] border {{ $snapshotsReady ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700' }} px-2.5 py-1 text-xs font-medium">Takip {{ $snapshotsReady ? 'aktif' : 'kurulum' }}</span>
                    <span class="rounded-[6px] border {{ $competitorsReady && $keywordsReady ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700' }} px-2.5 py-1 text-xs font-medium">Radar {{ $competitorsReady && $keywordsReady ? 'aktif' : 'kurulum' }}</span>
                    <span class="rounded-[6px] border {{ $campaignsReady ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700' }} px-2.5 py-1 text-xs font-medium">Kampanya {{ $campaignsReady ? 'aktif' : 'kurulum' }}</span>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                    <div class="flex items-center gap-2">
                        <x-lucide.icon name="gauge" class="h-4 w-4 text-slate-500" />
                        <p class="text-sm font-semibold text-slate-900">Karar skoru</p>
                    </div>
                    <p class="mt-2 text-xs leading-5 text-slate-500">Net kâr, marj, başabaş ve hedef fiyat aynı kartta toplanır.</p>
                </div>
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                    <div class="flex items-center gap-2">
                        <x-lucide.icon name="refresh-cw" class="h-4 w-4 text-slate-500" />
                        <p class="text-sm font-semibold text-slate-900">Fiyat ve stok</p>
                    </div>
                    <p class="mt-2 text-xs leading-5 text-slate-500">Snapshot geçmişiyle son fiyat farkı ve stok durumu izlenir.</p>
                </div>
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                    <div class="flex items-center gap-2">
                        <x-lucide.icon name="radar" class="h-4 w-4 text-slate-500" />
                        <p class="text-sm font-semibold text-slate-900">Rakip ve kelime</p>
                    </div>
                    <p class="mt-2 text-xs leading-5 text-slate-500">Rakip fiyat baskısı ve arama görünürlüğü ürün kartında görünür.</p>
                </div>
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                    <div class="flex items-center gap-2">
                        <x-lucide.icon name="badge-percent" class="h-4 w-4 text-slate-500" />
                        <p class="text-sm font-semibold text-slate-900">Kampanya karar</p>
                    </div>
                    <p class="mt-2 text-xs leading-5 text-slate-500">İndirim, komisyon desteği, reklam payı ve adet varsayımı test edilir.</p>
                </div>
            </div>
        </section>
    @endif

    @if($message)
        <div class="rounded-[8px] border px-4 py-3 text-sm {{ $messageType === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : ($messageType === 'warning' ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-rose-200 bg-rose-50 text-rose-800') }}">
            {{ $message }}
        </div>
    @endif

    @if($activeModule === 'analysis')
        @include('livewire.partials.trendyol-booster-analysis')
    @elseif($activeModule === 'comparison')
        @include('livewire.partials.trendyol-booster-comparison', ['researchKind' => 'comparison'])
    @elseif($activeModule === 'sell_decision')
        @include('livewire.partials.trendyol-booster-sell-decision')
    @elseif(in_array($activeModule, ['profit_loss', 'gross_profit', 'net_profit', 'target_planner'], true))
        @include('livewire.partials.trendyol-booster-finance-tools')
    @elseif($activeModule === 'keyword_tracking')
        @include('livewire.partials.trendyol-booster-keyword-tracking')
    @elseif($activeModule === 'supplier_finder')
        @include('livewire.partials.trendyol-booster-supplier-finder')
    @elseif($activeModule === 'decision')
    <div
        x-data="{
            extensionReady: false,
            extensionVersion: '',
            bridgeBusy: false,
            bridgeRequestId: '',
            bridgeTimer: null,
            init() {
                console.log('[ZOLM Page] Sending PING to window...');
                window.postMessage({ source: 'zolm-booster-page', type: 'PING' }, window.location.origin);
            },
            analyzeProduct(wire) {
                const sourceUrl = this.$refs.productUrl.value.trim();

                if (!sourceUrl) {
                    wire.fetchProductFromUrl();
                    return;
                }

                if (!this.extensionReady) {
                    console.warn('[ZOLM Page] Extension not ready. Falling back to backend server scan.');
                    wire.fetchProductFromUrl();
                    return;
                }

                console.log('[ZOLM Page] Sending analysis request to extension for URL:', sourceUrl);
                this.bridgeBusy = true;
                this.bridgeRequestId = `${Date.now()}-${Math.random().toString(16).slice(2)}`;
                window.postMessage({
                    source: 'zolm-booster-page',
                    type: 'PRODUCT_ANALYSIS_QUERY',
                    request_id: this.bridgeRequestId,
                    source_url: sourceUrl,
                }, window.location.origin);

                this.bridgeTimer = window.setTimeout(() => {
                    if (!this.bridgeBusy) return;
                    this.bridgeBusy = false;
                    wire.productAnalysisBridgeCompleted(null, 'Chrome ürün analizi zaman aşımına uğradı. Eklentiyi yeniden yükleyip tekrar deneyin.', false);
                }, 45000);
            },
            handleBridge(event, wire) {
                const data = event.data || {};

                if (event.origin !== window.location.origin || data.source !== 'zolm-booster-extension') return;

                console.log('[ZOLM Page] Received message from extension bridge:', data);

                if (data.type === 'READY') {
                    console.log('[ZOLM Page] ZOLM Chrome Companion is connected successfully! Version:', data.version);
                    this.extensionReady = true;
                    this.extensionVersion = data.version || '';
                    return;
                }

                if (data.type !== 'PRODUCT_ANALYSIS_QUERY_RESULT' || data.request_id !== this.bridgeRequestId) return;

                window.clearTimeout(this.bridgeTimer);
                this.bridgeBusy = false;
                const response = data.response || {};
                wire.productAnalysisBridgeCompleted(
                    response.analysis?.tracked_product_id || null,
                    response.message || (response.ok ? 'Ürün analizi tamamlandı.' : 'Ürün analizi tamamlanamadı.'),
                    Boolean(response.ok)
                );
            },
        }"
        x-on:message.window="handleBridge($event, $wire)"
        class="grid grid-cols-1 gap-4 xl:grid-cols-12 lg:gap-6"
    >
        @if($productAnalysisReady && !empty($productAnalysis))
            @php
                $analysisCurrent = $productAnalysis['current'] ?? [];
                $analysisPrevious = $productAnalysis['previous'] ?? null;
                $analysisMetricRows = [
                    ['key' => 'sale_price', 'label' => 'Ürün fiyatı', 'type' => 'money'],
                    ['key' => 'evaluation_count', 'label' => 'Ürün değerlendirme sayısı', 'type' => 'integer'],
                    ['key' => 'review_count', 'label' => 'Ürün yorum sayısı', 'type' => 'integer'],
                    ['key' => 'average_rating', 'label' => 'Ürün puanı', 'type' => 'decimal'],
                    ['key' => 'favorite_count', 'label' => 'Favorilenme', 'type' => 'integer'],
                    ['key' => 'basket_count', 'label' => 'Sepete eklenme', 'type' => 'integer'],
                    ['key' => 'view_count_24h', 'label' => 'Son 24 saatte görüntüleme', 'type' => 'integer'],
                ];
                $formatAnalysisMetric = function (mixed $value, string $type): string {
                    if ($value === null || $value === '') return 'Yayınlanmıyor';
                    return match ($type) {
                        'money' => number_format((float) $value, 2, ',', '.') . ' TL',
                        'decimal' => number_format((float) $value, 1, ',', '.'),
                        default => number_format((int) $value, 0, ',', '.'),
                    };
                };
            @endphp

            <section data-testid="booster-product-analysis" class="min-w-0 overflow-hidden rounded-[10px] border border-slate-200 bg-white shadow-sm xl:col-span-12">
                <div class="border-b border-slate-200 p-4 lg:p-6">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="flex min-w-0 items-start gap-3 sm:gap-4">
                            <div class="h-16 w-16 shrink-0 overflow-hidden rounded-[8px] border border-slate-200 bg-slate-50 sm:h-20 sm:w-20">
                                @if(!empty($productAnalysis['image_url']))
                                    <img src="{{ $productAnalysis['image_url'] }}" alt="{{ $productAnalysis['title'] }}" class="h-full w-full object-cover" loading="lazy">
                                @else
                                    <div class="flex h-full w-full items-center justify-center text-slate-400"><x-lucide.icon name="image" class="h-6 w-6" /></div>
                                @endif
                            </div>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-2 py-1 font-mono text-[11px] text-slate-500">Ürün ID: {{ $productAnalysis['trendyol_product_id'] ?: '-' }}</span>
                                    <span class="rounded-[6px] border border-emerald-200 bg-emerald-50 px-2 py-1 text-[11px] font-medium text-emerald-700">Canlı Trendyol</span>
                                </div>
                                <h2 class="mt-2 break-words text-base font-semibold text-slate-900 lg:text-lg">{{ $productAnalysis['title'] ?: 'Ürün başlığı bulunamadı' }}</h2>
                                <p class="mt-1 text-sm text-slate-500">{{ collect([$productAnalysis['brand'] ?? null, $productAnalysis['category_name'] ?? null])->filter()->join(' · ') ?: 'Marka ve kategori bilgisi yok' }}</p>
                                <p class="mt-2 text-xs text-slate-500">
                                    Son tarama: {{ $this->formatAnalysisDate($analysisCurrent['checked_at'] ?? null) }}
                                    @if($analysisPrevious)
                                        · Önceki tarama: {{ $this->formatAnalysisDate($analysisPrevious['checked_at'] ?? null) }}
                                    @endif
                                </p>
                            </div>
                        </div>
                        <button
                            type="button"
                            wire:click="toggleProductFavorite({{ $productAnalysis['tracked_product_id'] }})"
                            class="inline-flex min-h-[44px] w-full shrink-0 items-center justify-center gap-2 rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-base font-medium text-slate-700 transition hover:bg-slate-50 sm:w-auto sm:py-2 sm:text-sm"
                        >
                            <x-lucide.icon name="heart" class="h-4 w-4 {{ !empty($productAnalysis['is_favorite']) ? 'fill-rose-500 text-rose-500' : '' }}" />
                            {{ !empty($productAnalysis['is_favorite']) ? 'Favorilerde' : 'Favoriye al' }}
                        </button>
                    </div>
                </div>

                <div class="space-y-5 p-4 lg:p-6">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3 lg:gap-4">
                        @foreach([
                            ['sale_price', 'Fiyat', 'money'],
                            ['evaluation_count', 'Değerlendirme', 'integer'],
                            ['review_count', 'Yorum', 'integer'],
                            ['average_rating', 'Puan', 'decimal'],
                            ['favorite_count', 'Favori', 'integer'],
                            ['basket_count', 'Sepet', 'integer'],
                            ['view_count_24h', 'Görüntüleme (24s)', 'integer'],
                        ] as [$metricKey, $metricLabel, $metricType])
                            <div class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/60 p-4">
                                <p class="text-xs text-slate-500">{{ $metricLabel }}</p>
                                <p class="mt-1 truncate text-lg font-semibold text-slate-900">{{ $formatAnalysisMetric($analysisCurrent[$metricKey] ?? null, $metricType) }}</p>
                            </div>
                        @endforeach
                    </div>

                    <div class="overflow-hidden rounded-lg border border-slate-200">
                        <div class="hidden grid-cols-[minmax(0,2fr)_minmax(120px,1fr)_minmax(120px,1fr)_minmax(120px,1fr)] gap-3 border-b border-slate-200 bg-slate-50/60 px-4 py-3 text-xs font-semibold uppercase text-slate-500 md:grid">
                            <span>Metrik</span><span>Önceki</span><span>Güncel</span><span>Değişim</span>
                        </div>
                        <div class="divide-y divide-slate-100 bg-white">
                            @foreach($analysisMetricRows as $metric)
                                @php
                                    $metricKey = $metric['key'];
                                    $previousValue = $analysisPrevious[$metricKey] ?? null;
                                    $currentValue = $analysisCurrent[$metricKey] ?? null;
                                    $hasPreviousMetric = $analysisPrevious !== null && $previousValue !== null;
                                    $metricChanged = $hasPreviousMetric && $currentValue !== null && (float) $previousValue !== (float) $currentValue;
                                    $metricDelta = $metricChanged ? (float) $currentValue - (float) $previousValue : 0;
                                    $changeLabel = !$hasPreviousMetric
                                        ? 'Yeni kayıt'
                                        : ($metricChanged
                                            ? (($metricDelta > 0 ? '+' : '') . $formatAnalysisMetric($metricDelta, $metric['type']))
                                            : 'Değişmedi');
                                @endphp
                                <div class="p-4 md:grid md:grid-cols-[minmax(0,2fr)_minmax(120px,1fr)_minmax(120px,1fr)_minmax(120px,1fr)] md:items-center md:gap-3 md:py-3">
                                    <p class="text-sm font-medium text-slate-900">{{ $metric['label'] }}</p>
                                    <dl class="mt-3 grid grid-cols-3 gap-2 text-xs md:contents md:text-sm">
                                        <div class="min-w-0 md:block"><dt class="text-slate-400 md:hidden">Önceki</dt><dd class="mt-1 truncate text-slate-600 md:mt-0">{{ $formatAnalysisMetric($previousValue, $metric['type']) }}</dd></div>
                                        <div class="min-w-0 md:block"><dt class="text-slate-400 md:hidden">Güncel</dt><dd class="mt-1 truncate font-medium text-slate-900 md:mt-0">{{ $formatAnalysisMetric($currentValue, $metric['type']) }}</dd></div>
                                        <div class="min-w-0 md:block"><dt class="text-slate-400 md:hidden">Değişim</dt><dd class="mt-1 truncate {{ $metricChanged ? ($metricDelta > 0 ? 'text-emerald-700' : 'text-rose-700') : 'text-slate-500' }} md:mt-0">{{ $changeLabel }}</dd></div>
                                    </dl>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="overflow-hidden rounded-lg border border-slate-200">
                        <div class="flex flex-col gap-1 border-b border-slate-200 bg-slate-50/60 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <h3 class="text-sm font-semibold text-slate-900">Yeniden eskiye son 10 yorum</h3>
                            <span class="text-xs text-slate-500">{{ $this->formatAnalysisDate($analysisCurrent['checked_at'] ?? null) }}</span>
                        </div>
                        <div class="divide-y divide-slate-100 bg-white">
                            @forelse(($productAnalysis['recent_reviews'] ?? []) as $review)
                                <article class="grid grid-cols-1 gap-2 p-4 sm:grid-cols-[minmax(110px,0.7fr)_70px_minmax(0,3fr)] sm:items-start sm:gap-4">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-900">{{ $review['user_name'] ?: 'Anonim' }}</p>
                                        <p class="mt-1 text-[11px] text-slate-400">{{ $this->formatAnalysisDate($review['reviewed_at'] ?? null) }}</p>
                                    </div>
                                    <span class="font-mono text-sm font-semibold text-amber-600">{{ (int) ($review['rate'] ?? 0) }}/5</span>
                                    <p class="min-w-0 break-words text-sm leading-6 text-slate-600">{{ $review['comment'] }}</p>
                                </article>
                            @empty
                                <div class="p-4 text-sm text-slate-500">Bu taramada yorum verisi alınamadı.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </section>
        @endif

        <section class="min-w-0 rounded-[10px] border border-slate-200 bg-white shadow-sm xl:col-span-7">
            <div class="border-b border-slate-200 px-4 py-4 lg:px-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase text-slate-500">Analiz girdileri</p>
                        <h2 class="mt-1 text-lg font-semibold text-slate-900">Link ve ZOLM maliyeti</h2>
                    </div>
                    <button
                        type="button"
                        wire:click="analyzeAndTrack"
                        wire:loading.attr="disabled"
                        wire:target="analyzeAndTrack"
                        @disabled(!$tablesReady)
                        class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] bg-slate-900 px-4 py-3 text-base font-medium text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto sm:py-2 sm:text-sm"
                    >
                        <x-lucide.icon name="check-circle" class="h-4 w-4" />
                        <span wire:loading.remove wire:target="analyzeAndTrack">Takibe al</span>
                        <span wire:loading wire:target="analyzeAndTrack">Kaydediliyor...</span>
                    </button>
                </div>
            </div>

            <div class="space-y-5 p-4 lg:p-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                    <label class="block min-w-0 flex-1">
                        <span class="text-xs font-medium text-slate-600">Trendyol ürün linki</span>
                        <input
                            type="url"
                            x-ref="productUrl"
                            wire:model.live.debounce.350ms="productUrl"
                            placeholder="https://www.trendyol.com/...-p-123456"
                            class="mt-1.5 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none focus:border-slate-400 sm:py-2 sm:text-sm"
                        >
                        @error('productUrl')
                            <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                    </label>
                    <button
                        type="button"
                        x-on:click="analyzeProduct($wire)"
                        x-bind:disabled="bridgeBusy"
                        wire:loading.attr="disabled"
                        wire:target="fetchProductFromUrl"
                        class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-base font-medium text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto sm:py-2 sm:text-sm"
                    >
                        <x-lucide.icon name="search" class="h-4 w-4" />
                        <span x-show="!bridgeBusy" wire:loading.remove wire:target="fetchProductFromUrl">Analiz et</span>
                        <span x-show="bridgeBusy" x-cloak>Tarayıcıdan okunuyor...</span>
                        <span wire:loading wire:target="fetchProductFromUrl">Alınıyor...</span>
                    </button>
                </div>
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <span x-show="extensionReady" x-cloak class="rounded-[6px] border border-emerald-200 bg-emerald-50 px-2 py-1 font-medium text-emerald-700">
                        Chrome köprüsü hazır<span x-show="extensionVersion" x-text="` · v${extensionVersion}`"></span>
                    </span>
                    <span x-show="!extensionReady" class="rounded-[6px] border border-amber-200 bg-amber-50 px-2 py-1 text-amber-700">Tam analiz için ZOLM Chrome Companion gerekir.</span>
                    <span class="text-slate-500">Fiyat, değerlendirme, yorum, puan ve favori verileri tek taramada kaydedilir.</span>
                </div>

                <div class="grid grid-cols-1 gap-3 lg:grid-cols-12 lg:gap-4">
                    <label class="lg:col-span-5">
                        <span class="text-xs font-medium text-slate-600">ZOLM ürün ara</span>
                        <input
                            type="search"
                            wire:model.live.debounce.300ms="productSearch"
                            placeholder="Ürün, stok kodu, barkod veya marka"
                            class="mt-1.5 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none focus:border-slate-400 sm:py-2 sm:text-sm"
                        >
                    </label>
                    <label class="lg:col-span-7">
                        <span class="text-xs font-medium text-slate-600">ZOLM ürün kartı</span>
                        <select
                            wire:model.live="selectedProductId"
                            class="mt-1.5 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none focus:border-slate-400 sm:py-2 sm:text-sm"
                        >
                            <option value="">Manuel analiz</option>
                            @foreach($productOptions as $product)
                                <option value="{{ $product->id }}">
                                    {{ $product->product_name ?: 'Adsız ürün' }} · {{ $product->stock_code ?: $product->barcode }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3 lg:gap-4">
                    <label>
                        <span class="text-xs font-medium text-slate-600">Başlık</span>
                        <input type="text" wire:model.live.debounce.300ms="title"
                               class="mt-1.5 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none focus:border-slate-400 sm:py-2 sm:text-sm">
                    </label>
                    <label>
                        <span class="text-xs font-medium text-slate-600">Marka</span>
                        <input type="text" wire:model.live.debounce.300ms="brand"
                               class="mt-1.5 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none focus:border-slate-400 sm:py-2 sm:text-sm">
                    </label>
                    <label>
                        <span class="text-xs font-medium text-slate-600">Kategori</span>
                        <input type="text" wire:model.live.debounce.300ms="categoryName"
                               class="mt-1.5 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none focus:border-slate-400 sm:py-2 sm:text-sm">
                    </label>
                </div>

                <div class="border-t border-slate-100 pt-5">
                    <p class="text-xs font-semibold uppercase text-slate-500">Fiyat ve maliyet</p>
                    <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3 lg:gap-4">
                        @foreach([
                            ['salePrice', 'Trendyol satış fiyatı', 'TL'],
                            ['cogs', 'Ürün maliyeti', 'TL'],
                            ['packagingCost', 'Ambalaj', 'TL'],
                            ['cargoCost', 'Kargo', 'TL'],
                            ['returnCargoCost', 'İade kargo', 'TL'],
                            ['commissionRate', 'Komisyon', '%'],
                        ] as [$model, $label, $suffix])
                            <label>
                                <span class="text-xs font-medium text-slate-600">{{ $label }}</span>
                                <div class="relative mt-1.5">
                                    <input type="number" min="0" step="0.01" wire:model.live.debounce.250ms="{{ $model }}"
                                           class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 pr-10 text-base text-slate-900 outline-none focus:border-slate-400 sm:py-2 sm:text-sm">
                                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-slate-400">{{ $suffix }}</span>
                                </div>
                                @error($model)
                                    <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>
                                @enderror
                            </label>
                        @endforeach
                    </div>

                    @if($costPresetsReady)
                        <div class="mt-4 rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                            <div class="grid grid-cols-1 gap-2 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto_auto]">
                                <label class="min-w-0">
                                    <span class="text-xs font-medium text-slate-600">Maliyet preset</span>
                                    <select
                                        wire:model.live="selectedCostPresetId"
                                        class="mt-1.5 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none focus:border-slate-400 sm:py-2 sm:text-sm"
                                    >
                                        <option value="">Preset seç</option>
                                        @foreach($costPresets as $preset)
                                            <option value="{{ $preset->id }}">{{ $preset->name }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="min-w-0">
                                    <span class="text-xs font-medium text-slate-600">Preset adı</span>
                                    <input
                                        type="text"
                                        wire:model.defer="costPresetName"
                                        placeholder="{{ $categoryName ?: 'Trendyol Genel' }}"
                                        class="mt-1.5 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none focus:border-slate-400 sm:py-2 sm:text-sm"
                                    >
                                </label>
                                <button type="button" wire:click="applyCostPreset"
                                        wire:loading.attr="disabled"
                                        wire:target="applyCostPreset"
                                        @disabled(!$selectedCostPresetId)
                                        class="inline-flex min-h-[44px] w-full items-center justify-center self-end rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60 lg:w-auto">
                                    Uygula
                                </button>
                                <button type="button" wire:click="saveCostPreset"
                                        wire:loading.attr="disabled"
                                        wire:target="saveCostPreset"
                                        class="inline-flex min-h-[44px] w-full items-center justify-center self-end rounded-[6px] bg-slate-900 px-3 py-2 text-sm font-medium text-white transition hover:bg-slate-800 disabled:opacity-60 lg:w-auto">
                                    Kaydet
                                </button>
                            </div>
                            @if($selectedCostPresetId)
                                <div class="mt-2 flex justify-end">
                                    <button type="button" wire:click="deleteCostPreset({{ $selectedCostPresetId }})"
                                            wire:confirm="Bu maliyet preset silinsin mi?"
                                            class="inline-flex min-h-8 items-center justify-center rounded-[6px] border border-rose-200 bg-white px-2 text-[11px] font-medium text-rose-700 transition hover:bg-rose-50">
                                        Seçili preset sil
                                    </button>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="border-t border-slate-100 pt-5">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4 lg:gap-4">
                        @foreach([
                            ['returnRate', 'İade rezervi'],
                            ['serviceFeeRate', 'Hizmet oranı'],
                            ['advertisingRate', 'Reklam payı'],
                            ['targetMarginPercent', 'Hedef marj'],
                        ] as [$model, $label])
                            <label>
                                <span class="text-xs font-medium text-slate-600">{{ $label }}</span>
                                <div class="relative mt-1.5">
                                    <input type="number" min="0" step="0.01" wire:model.live.debounce.250ms="{{ $model }}"
                                           class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 pr-9 text-base text-slate-900 outline-none focus:border-slate-400 sm:py-2 sm:text-sm">
                                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-slate-400">%</span>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="border-t border-slate-100 pt-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase text-slate-500">Kontrol anahtarları</p>
                            <p class="mt-1 text-sm text-slate-500">Takip ve vergi etkisi</p>
                        </div>
                        <div class="grid w-full grid-cols-1 gap-2 sm:w-auto sm:grid-cols-2 xl:grid-cols-5">
                            @foreach([
                                ['watchPrice', 'Fiyat'],
                                ['watchStock', 'Stok'],
                                ['watchKeyword', 'Kelime'],
                                ['vatEnabled', 'KDV'],
                                ['withholdingEnabled', 'Stopaj'],
                            ] as [$model, $label])
                                <label class="flex min-h-11 cursor-pointer items-center justify-between gap-3 rounded-[6px] border border-slate-200 bg-slate-50/60 px-3 py-2 text-sm text-slate-700">
                                    <span>{{ $label }}</span>
                                    <input type="checkbox" wire:model.live="{{ $model }}" class="h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-500">
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div class="min-w-0 space-y-4 xl:col-span-5">
            <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-5 xl:sticky xl:top-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase text-slate-500">Karar skoru</p>
                        <h2 class="mt-1 text-lg font-semibold text-slate-900">{{ $this->decisionLabel($decision) }}</h2>
                    </div>
                    <span class="inline-flex items-center justify-center rounded-[6px] border px-2.5 py-1 font-mono text-xs {{ $this->decisionClasses($decision) }}">
                        {{ $score }}/100
                    </span>
                </div>

                <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-100">
                    <div class="h-full rounded-full {{ $score >= 75 ? 'bg-emerald-500' : ($score >= 55 ? 'bg-sky-500' : ($score >= 35 ? 'bg-amber-500' : 'bg-rose-500')) }}" style="width: {{ $scoreWidth }}%"></div>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-3">
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                        <p class="text-xs text-slate-500">Net kâr</p>
                        <p class="mt-1 text-base font-semibold {{ ! $costReady ? 'text-amber-700' : ((float) $simulation['net_profit'] >= 0 ? 'text-emerald-700' : 'text-rose-700') }}">
                            {{ $costReady ? $this->formatMoney($simulation['net_profit']) : 'Maliyet eksik' }}
                        </p>
                    </div>
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                        <p class="text-xs text-slate-500">Marj</p>
                        <p class="mt-1 text-base font-semibold text-slate-900">{{ $costReady ? '%' . number_format((float) $simulation['profit_margin_percent'], 1, ',', '.') : '-' }}</p>
                    </div>
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                        <p class="text-xs text-slate-500">Başabaş</p>
                        <p class="mt-1 text-base font-semibold text-slate-900">{{ $this->formatMoney($simulation['break_even_price']) }}</p>
                    </div>
                    <div class="rounded-[8px] border border-slate-900 bg-slate-900 p-3 text-white">
                        <p class="text-xs text-slate-300">Hedef fiyat</p>
                        <p class="mt-1 text-base font-semibold">{{ $simulation['target_price'] !== null ? $this->formatMoney($simulation['target_price']) : 'Yok' }}</p>
                    </div>
                </div>

                <div class="mt-5 space-y-3">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-slate-900">Kesinti özeti</p>
                        <span class="font-mono text-xs text-slate-500">{{ $this->formatMoney($breakdown['total_deductions']) }}</span>
                    </div>
                    @foreach([
                        ['commission', 'Komisyon', 'bg-amber-500'],
                        ['cargo', 'Kargo', 'bg-sky-500'],
                        ['service_fee', 'Hizmet', 'bg-slate-500'],
                        ['advertising', 'Reklam', 'bg-indigo-500'],
                        ['return_reserve', 'İade', 'bg-rose-500'],
                        ['withholding', 'Stopaj', 'bg-violet-500'],
                        ['net_vat', 'Net KDV', 'bg-emerald-500'],
                    ] as [$key, $label, $color])
                        @php
                            $value = (float) ($breakdown[$key] ?? 0);
                            $width = (float) $simulation['sale_price'] > 0 ? min(100, abs($value) / (float) $simulation['sale_price'] * 100) : 0;
                        @endphp
                        <div>
                            <div class="flex items-center justify-between gap-3 text-xs">
                                <span class="text-slate-600">{{ $label }}</span>
                                <span class="font-medium text-slate-900">{{ $this->formatMoney($value) }}</span>
                            </div>
                            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full {{ $color }}" style="width: {{ $width }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if(!empty($preview['reasons']))
                    <div class="mt-5 space-y-2">
                        @foreach($preview['reasons'] as $reason)
                            <p class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-3 py-2 text-xs leading-5 text-slate-600">{{ $reason }}</p>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </div>

    <section data-testid="booster-analysis-ledger" class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase text-slate-500">Analiz ledger</p>
                <h2 class="mt-1 text-lg font-semibold text-slate-900">Analiz edilmiş ürünler</h2>
                <p class="mt-1 text-sm text-slate-500">Geçmişi kıyaslayın, anlık yenileyin ve takip düzenini tek yüzeyden yönetin.</p>
            </div>
            <div class="grid grid-cols-3 gap-2">
                @foreach([
                    ['label' => 'Gösterilen', 'value' => $dashboard['filtered_count'] ?? 0],
                    ['label' => 'Favori', 'value' => $dashboard['favorite_count'] ?? 0],
                    ['label' => 'Oto', 'value' => $dashboard['auto_refresh_count'] ?? 0],
                ] as $ledgerStat)
                    <div class="min-w-[76px] rounded-[6px] border border-slate-200 bg-slate-50/70 px-3 py-2">
                        <p class="text-[11px] text-slate-500">{{ $ledgerStat['label'] }}</p>
                        <p class="mt-0.5 font-mono text-sm font-semibold text-slate-900">{{ $ledgerStat['value'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50/60 p-3">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(240px,1fr)_220px_190px_auto_auto] xl:items-end">
                <label class="min-w-0">
                    <span class="text-xs font-medium text-slate-600">Ürün ara</span>
                    <div class="relative mt-1.5">
                        <x-lucide.icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input type="search" wire:model.live.debounce.300ms="analysisSearch" placeholder="Ürün, marka veya ID"
                               class="min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white py-3 pl-9 pr-3 text-base text-slate-900 outline-none focus:border-slate-400 sm:py-2 sm:text-sm">
                    </div>
                </label>
                <label>
                    <span class="text-xs font-medium text-slate-600">Kategori</span>
                    <select wire:model.live="analysisCategory" class="mt-1.5 min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 outline-none focus:border-slate-400 sm:text-sm">
                        <option value="all">Tüm kategoriler</option>
                        @foreach($this->analysisCategories as $category)
                            <option value="{{ $category }}">{{ $category }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span class="text-xs font-medium text-slate-600">Sırala</span>
                    <select wire:model.live="analysisSort" class="mt-1.5 min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 outline-none focus:border-slate-400 sm:text-sm">
                        <option value="latest">Son analiz</option>
                        <option value="oldest">En eski</option>
                        <option value="category">Kategori A–Z</option>
                        <option value="price_asc">Fiyat artan</option>
                        <option value="price_desc">Fiyat azalan</option>
                        <option value="score_desc">Skor yüksek</option>
                        <option value="favorite_first">Favoriler önce</option>
                    </select>
                </label>
                <button type="button" wire:click="toggleFavoritesOnly"
                        class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] border px-3 py-2 text-sm font-medium transition {{ $favoritesOnly ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50' }}">
                    <x-lucide.icon name="heart" class="h-4 w-4 {{ $favoritesOnly ? 'fill-rose-500 text-rose-500' : '' }}" />
                    Favoriler
                </button>
                <button type="button" wire:click="resetAnalysisFilters"
                        class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50">
                    Temizle
                </button>
            </div>
            <p class="mt-2 text-xs text-slate-500">
                {{ $dashboard['filtered_count'] ?? 0 }} / {{ $dashboard['total'] ?? 0 }} ürün gösteriliyor
                @if($analysisCategory !== 'all') · Kategori: {{ $analysisCategory }} @endif
                @if($favoritesOnly) · Yalnızca favoriler @endif
            </p>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-2 xl:grid-cols-3 lg:gap-4">
            @forelse($dashboard['products'] as $tracked)
                @php
                    $snapshot = $snapshotsReady ? $tracked->latestSnapshot : null;
                    $analysisSnapshots = $snapshotsReady ? $tracked->analysisSnapshots : collect();
                    $analysisNow = $analysisSnapshots->get(0);
                    $analysisBefore = $analysisSnapshots->get(1);
                    $delta = $snapshot?->price_delta ?? 0;
                    $stockStatus = $snapshot?->stock_status ?? 'unknown';
                    $comparisonRows = [
                        ['key' => 'sale_price', 'label' => 'Fiyat', 'type' => 'money'],
                        ['key' => 'evaluation_count', 'label' => 'Değerlendirme', 'type' => 'integer'],
                        ['key' => 'review_count', 'label' => 'Yorum', 'type' => 'integer'],
                        ['key' => 'favorite_count', 'label' => 'Favori', 'type' => 'integer'],
                        ['key' => 'basket_count', 'label' => 'Sepet', 'type' => 'integer'],
                        ['key' => 'view_count_24h', 'label' => 'Görüntüleme (24s)', 'type' => 'integer'],
                    ];
                    $formatLedgerMetric = function (mixed $value, string $type): string {
                        if ($value === null || $value === '') return '-';
                        return $type === 'money'
                            ? number_format((float) $value, 2, ',', '.') . ' TL'
                            : number_format((int) $value, 0, ',', '.');
                    };
                @endphp
                <article wire:key="analysis-ledger-{{ $tracked->id }}" x-data="{ actionsOpen: false }"
                         x-on:keydown.escape.window="actionsOpen = false"
                         class="min-w-0 rounded-[8px] border border-slate-200 bg-white p-3 shadow-sm transition hover:shadow-md lg:p-4">
                    <div class="flex items-start gap-3">
                        <div class="h-11 w-11 shrink-0 overflow-hidden rounded-[6px] border border-slate-200 bg-slate-50">
                            @if($tracked->image_url)
                                <img src="{{ $tracked->image_url }}" alt="" class="h-full w-full object-cover" loading="lazy">
                            @else
                                <div class="flex h-full w-full items-center justify-center text-slate-400"><x-lucide.icon name="image" class="h-4 w-4" /></div>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex min-w-0 items-center gap-2">
                                <p class="truncate text-sm font-semibold text-slate-900">{{ $tracked->title ?: $tracked->product?->product_name ?: 'Trendyol ürünü' }}</p>
                                <span class="shrink-0 rounded-[6px] border px-2 py-0.5 font-mono text-[11px] {{ $this->decisionClasses($tracked->decision_status) }}">{{ $tracked->opportunity_score }}</span>
                            </div>
                            <p class="mt-1 truncate text-xs text-slate-500">{{ $tracked->brand ?: 'Marka yok' }} · {{ $tracked->category_name ?: 'Kategori yok' }}</p>
                        </div>
                        <button type="button" wire:click="toggleProductFavorite({{ $tracked->id }})" aria-label="{{ $tracked->is_favorite ? 'Favoriden çıkar' : 'Favoriye ekle' }}"
                                class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-[6px] border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50 hover:text-rose-600">
                            <x-lucide.icon name="heart" class="h-4 w-4 {{ $tracked->is_favorite ? 'fill-rose-500 text-rose-500' : '' }}" />
                        </button>
                        <button type="button" x-on:click="actionsOpen = true" aria-label="Ürün aksiyonları"
                                class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-[6px] border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50">
                            <x-lucide.icon name="more-vertical" class="h-4 w-4" />
                        </button>
                    </div>

                    <div class="mt-3 grid grid-cols-3 gap-2">
                        <div class="min-w-0 rounded-[6px] border border-slate-200 bg-slate-50/70 px-2 py-2">
                            <p class="text-[10px] uppercase text-slate-400">Fiyat</p>
                            <p class="mt-0.5 truncate text-xs font-semibold text-slate-900">{{ $this->formatMoney($tracked->sale_price) }}</p>
                        </div>
                        <div class="min-w-0 rounded-[6px] border border-slate-200 bg-slate-50/70 px-2 py-2">
                            <p class="text-[10px] uppercase text-slate-400">Puan</p>
                            <p class="mt-0.5 truncate text-xs font-semibold text-slate-900">{{ $analysisNow?->average_rating !== null ? number_format((float) $analysisNow->average_rating, 1, ',', '.') : '-' }}</p>
                        </div>
                        <div class="min-w-0 rounded-[6px] border border-slate-200 bg-slate-50/70 px-2 py-2">
                            <p class="text-[10px] uppercase text-slate-400">Favori</p>
                            <p class="mt-0.5 truncate text-xs font-semibold text-slate-900">{{ $analysisNow?->favorite_count !== null ? number_format((int) $analysisNow->favorite_count, 0, ',', '.') : '-' }}</p>
                        </div>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-1.5">
                        <span class="rounded-[6px] border px-2 py-1 text-[11px] font-medium {{ $this->priceDeltaClasses($delta) }}">{{ $this->priceDeltaLabel($delta) }}</span>
                        <span class="rounded-[6px] border px-2 py-1 text-[11px] font-medium {{ $this->stockStatusClasses($stockStatus) }}">{{ $this->stockStatusLabel($stockStatus) }}</span>
                        @if($tracked->analysis_auto_refresh_enabled)
                            <span class="rounded-[6px] border border-sky-200 bg-sky-50 px-2 py-1 text-[11px] font-medium text-sky-700">Oto güncelleme</span>
                        @endif
                        <span class="ml-auto text-[11px] text-slate-400">{{ $analysisNow?->checked_at?->format('d.m H:i') ?? 'Analiz yok' }}</span>
                    </div>

                    <div x-cloak x-show="actionsOpen" x-transition.opacity class="fixed inset-0 z-[90] flex items-end justify-center p-0 sm:items-center sm:p-4" role="dialog" aria-modal="true">
                        <button type="button" x-on:click="actionsOpen = false" class="absolute inset-0 bg-slate-950/35" aria-label="Aksiyonları kapat"></button>
                        <div class="relative z-10 flex max-h-[92vh] w-full max-w-4xl flex-col overflow-hidden rounded-t-[10px] border border-slate-200 bg-white shadow-2xl sm:rounded-[10px]">
                            <div class="flex items-start justify-between gap-3 border-b border-slate-200 px-4 py-3 lg:px-5">
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold uppercase text-slate-500">Ürün aksiyonları</p>
                                    <h3 class="mt-1 truncate text-base font-semibold text-slate-900">{{ $tracked->title ?: 'Trendyol ürünü' }}</h3>
                                    <p class="mt-1 text-xs text-slate-500">Son analiz: {{ $analysisNow?->checked_at?->format('d.m.Y H:i') ?? 'Henüz yok' }}</p>
                                </div>
                                <button type="button" x-on:click="actionsOpen = false" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-[6px] border border-slate-200 text-slate-500 hover:bg-slate-50" aria-label="Kapat">
                                    <x-lucide.icon name="x" class="h-4 w-4" />
                                </button>
                            </div>

                            <div class="overflow-y-auto p-4 lg:p-5">
                                <div class="grid grid-cols-1 gap-3 lg:grid-cols-12">
                                    <section class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3 lg:col-span-7">
                                        <div class="flex items-center justify-between gap-3">
                                            <div>
                                                <p class="text-sm font-semibold text-slate-900">Geçmiş–güncel kıyaslama</p>
                                                <p class="mt-0.5 text-xs text-slate-500">Son iki tam analiz kaydı</p>
                                            </div>
                                            <button type="button" wire:click="refreshProductAnalysisNow({{ $tracked->id }})" wire:loading.attr="disabled" wire:target="refreshProductAnalysisNow"
                                                    class="inline-flex min-h-[40px] items-center justify-center gap-2 rounded-[6px] bg-slate-900 px-3 py-2 text-xs font-medium text-white disabled:opacity-60">
                                                <x-lucide.icon name="refresh-cw" class="h-3.5 w-3.5" />
                                                <span wire:loading.remove wire:target="refreshProductAnalysisNow">Anlık yenile</span>
                                                <span wire:loading wire:target="refreshProductAnalysisNow">Yenileniyor...</span>
                                            </button>
                                        </div>
                                        <div class="mt-3 overflow-hidden rounded-lg border border-slate-200 bg-white">
                                            <div class="grid grid-cols-[minmax(0,1.4fr)_1fr_1fr_auto] gap-2 border-b border-slate-200 bg-slate-50/70 px-3 py-2 text-[10px] font-semibold uppercase text-slate-400">
                                                <span>Metrik</span><span>Eski</span><span>Güncel</span><span>Fark</span>
                                            </div>
                                            <div class="divide-y divide-slate-100">
                                                @foreach($comparisonRows as $row)
                                                    @php
                                                        $oldValue = $analysisBefore?->{$row['key']};
                                                        $newValue = $analysisNow?->{$row['key']};
                                                        $difference = $oldValue !== null && $newValue !== null ? (float) $newValue - (float) $oldValue : null;
                                                    @endphp
                                                    <div class="grid grid-cols-[minmax(0,1.4fr)_1fr_1fr_auto] items-center gap-2 px-3 py-2 text-xs">
                                                        <span class="truncate font-medium text-slate-700">{{ $row['label'] }}</span>
                                                        <span class="truncate text-slate-500">{{ $formatLedgerMetric($oldValue, $row['type']) }}</span>
                                                        <span class="truncate font-medium text-slate-900">{{ $formatLedgerMetric($newValue, $row['type']) }}</span>
                                                        <span class="font-mono {{ $difference === null || $difference == 0 ? 'text-slate-400' : ($difference > 0 ? 'text-emerald-700' : 'text-rose-700') }}">
                                                            {{ $difference === null ? '-' : (($difference > 0 ? '+' : '') . $formatLedgerMetric($difference, $row['type'])) }}
                                                        </span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </section>

                                    <section class="rounded-[8px] border border-slate-200 bg-white p-3 lg:col-span-5">
                                        <p class="text-sm font-semibold text-slate-900">Hızlı aksiyonlar</p>
                                        <div class="mt-3 grid grid-cols-2 gap-2">
                                            <button type="button" wire:click="showProductAnalysis({{ $tracked->id }})" x-on:click="actionsOpen = false; setTimeout(() => document.querySelector('[data-testid=booster-product-analysis]')?.scrollIntoView({ behavior: 'smooth' }), 250)"
                                                    class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                                <x-lucide.icon name="history" class="h-4 w-4" /> Detaylı kıyas
                                            </button>
                                            <button type="button" wire:click="loadTrackedProduct({{ $tracked->id }})" x-on:click="actionsOpen = false"
                                                    class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50">Forma yükle</button>
                                            <a href="{{ $tracked->source_url }}" target="_blank" rel="noopener noreferrer"
                                               class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                                <x-lucide.icon name="external-link" class="h-4 w-4" /> Trendyol
                                            </a>
                                            <button type="button" wire:click="checkTrackedProduct({{ $tracked->id }})" wire:loading.attr="disabled" wire:target="checkTrackedProduct"
                                                    class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-60">Fiyat/Stok</button>
                                        </div>

                                        <div class="mt-3 rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                                            <div class="flex items-center justify-between gap-3">
                                                <div>
                                                    <p class="text-xs font-semibold text-slate-900">Otomatik tam analiz</p>
                                                    <p class="mt-0.5 text-[11px] text-slate-500">Fiyat, yorum, favori, sepet ve görüntüleme</p>
                                                </div>
                                                <button type="button" wire:click="toggleAnalysisAutoRefresh({{ $tracked->id }})"
                                                        aria-label="Otomatik tam analizi {{ $tracked->analysis_auto_refresh_enabled ? 'kapat' : 'aç' }}"
                                                        class="relative h-6 w-11 rounded-full transition {{ $tracked->analysis_auto_refresh_enabled ? 'bg-slate-900' : 'bg-slate-300' }}" role="switch" aria-checked="{{ $tracked->analysis_auto_refresh_enabled ? 'true' : 'false' }}">
                                                    <span class="absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition {{ $tracked->analysis_auto_refresh_enabled ? 'left-[22px]' : 'left-0.5' }}"></span>
                                                </button>
                                            </div>
                                            <select wire:change="updateAnalysisRefreshInterval({{ $tracked->id }}, $event.target.value)"
                                                    class="mt-3 min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 sm:text-sm">
                                                @foreach([60 => 'Her saat', 360 => '6 saatte bir', 720 => '12 saatte bir', 1440 => 'Her gün', 10080 => 'Her hafta'] as $minutes => $label)
                                                    <option value="{{ $minutes }}" @selected((int) ($tracked->analysis_refresh_interval_minutes ?: 1440) === $minutes)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            <p class="mt-2 text-[11px] text-slate-500">Son durum: {{ match($tracked->last_analysis_refresh_status) { 'error' => 'Hata', 'partial' => 'Kısmi', 'success' => 'Başarılı', default => 'Bekliyor' } }} · Sonraki: {{ $tracked->next_analysis_refresh_at?->format('d.m H:i') ?? '-' }}</p>
                                        </div>

                                        <div class="mt-3 grid grid-cols-2 gap-2">
                                            <button type="button" wire:click="toggleProductFavorite({{ $tracked->id }})"
                                                    class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                                <x-lucide.icon name="heart" class="h-4 w-4 {{ $tracked->is_favorite ? 'fill-rose-500 text-rose-500' : '' }}" />
                                                {{ $tracked->is_favorite ? 'Favoriden çıkar' : 'Favoriye ekle' }}
                                            </button>
                                            <button type="button" wire:click="removeTrackedProduct({{ $tracked->id }})" wire:confirm="Bu analiz kaydı ve tüm geçmişi kalıcı olarak silinsin mi?"
                                                    class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-[6px] border border-rose-200 bg-white px-3 py-2 text-xs font-medium text-rose-700 hover:bg-rose-50">
                                                <x-lucide.icon name="trash-2" class="h-4 w-4" /> Analizi sil
                                            </button>
                                        </div>
                                    </section>
                                </div>

                                <div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-3">
                                    @if($competitorsReady)
                                        <section class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                                            <div class="flex items-center justify-between"><p class="text-xs font-semibold uppercase text-slate-500">Rakip radar</p><span class="font-mono text-xs text-slate-500">{{ $tracked->competitors->count() }}</span></div>
                                            <div class="mt-2 space-y-1.5">
                                                @foreach($tracked->competitors as $competitor)
                                                    <div class="flex items-center gap-2 rounded-[6px] border border-slate-200 bg-white px-2 py-2">
                                                        <p class="min-w-0 flex-1 truncate text-xs font-medium text-slate-700">{{ $competitor->title ?: 'Rakip ürün' }}</p>
                                                        <button type="button" wire:click="refreshCompetitor({{ $competitor->id }})" class="text-[11px] text-slate-500">Yenile</button>
                                                        <button type="button" wire:click="removeCompetitor({{ $competitor->id }})" wire:confirm="Bu rakip silinsin mi?" class="text-[11px] text-rose-600">Sil</button>
                                                    </div>
                                                @endforeach
                                            </div>
                                            <div class="mt-2 flex gap-2">
                                                <input type="url" wire:model.defer="competitorUrls.{{ $tracked->id }}" placeholder="Rakip Trendyol linki" class="min-h-[44px] min-w-0 flex-1 rounded-[6px] border border-slate-200 bg-white px-3 text-base sm:text-sm">
                                                <button type="button" wire:click="addCompetitor({{ $tracked->id }})" class="min-h-[44px] rounded-[6px] border border-slate-200 bg-white px-3 text-xs font-medium text-slate-700">Ekle</button>
                                            </div>
                                        </section>
                                    @endif

                                    @if($keywordsReady)
                                        <section class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                                            <div class="flex items-center justify-between"><p class="text-xs font-semibold uppercase text-slate-500">Kelime radar</p><span class="font-mono text-xs text-slate-500">{{ $tracked->keywords->count() }}</span></div>
                                            <div class="mt-2 space-y-1.5">
                                                @foreach($tracked->keywords as $keyword)
                                                    <div class="flex items-center gap-2 rounded-[6px] border border-slate-200 bg-white px-2 py-2">
                                                        <p class="min-w-0 flex-1 truncate text-xs font-medium text-slate-700">{{ $keyword->keyword }} · {{ $keyword->observed_rank ?: '-' }}. sıra</p>
                                                        <button type="button" wire:click="refreshKeyword({{ $keyword->id }})" class="text-[11px] text-slate-500">Yenile</button>
                                                        <button type="button" wire:click="removeKeyword({{ $keyword->id }})" wire:confirm="Bu kelime silinsin mi?" class="text-[11px] text-rose-600">Sil</button>
                                                    </div>
                                                @endforeach
                                            </div>
                                            <div class="mt-2 grid grid-cols-[minmax(0,1fr)_72px_auto] gap-2">
                                                <input type="search" wire:model.defer="keywordInputs.{{ $tracked->id }}" placeholder="Anahtar kelime" class="min-h-[44px] min-w-0 rounded-[6px] border border-slate-200 bg-white px-3 text-base sm:text-sm">
                                                <input type="number" min="1" max="500" wire:model.defer="keywordTargets.{{ $tracked->id }}" placeholder="20" class="min-h-[44px] rounded-[6px] border border-slate-200 bg-white px-2 text-base sm:text-sm">
                                                <button type="button" wire:click="addKeyword({{ $tracked->id }})" class="min-h-[44px] rounded-[6px] border border-slate-200 bg-white px-3 text-xs font-medium text-slate-700">Ekle</button>
                                            </div>
                                        </section>
                                    @endif

                                    @if($campaignsReady)
                                        <section class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                                            <div class="flex items-center justify-between"><p class="text-xs font-semibold uppercase text-slate-500">Kampanya karar</p><span class="font-mono text-xs text-slate-500">{{ $tracked->campaignScenarios->count() }}</span></div>
                                            <div class="mt-2 space-y-1.5">
                                                @foreach($tracked->campaignScenarios as $scenario)
                                                    <div class="flex items-center gap-2 rounded-[6px] border border-slate-200 bg-white px-2 py-2">
                                                        <p class="min-w-0 flex-1 truncate text-xs font-medium text-slate-700">{{ $scenario->name }} · {{ $this->formatMoney($scenario->campaign_price) }}</p>
                                                        <button type="button" wire:click="deleteCampaignScenario({{ $scenario->id }})" wire:confirm="Bu senaryo silinsin mi?" class="text-[11px] text-rose-600">Sil</button>
                                                    </div>
                                                @endforeach
                                            </div>
                                            <div class="mt-2 grid grid-cols-2 gap-2">
                                                <input type="text" wire:model.defer="campaignNames.{{ $tracked->id }}" placeholder="Kampanya adı" class="min-h-[44px] rounded-[6px] border border-slate-200 bg-white px-3 text-base sm:text-sm">
                                                <input type="number" min="0" step="0.01" wire:model.defer="campaignDiscountRates.{{ $tracked->id }}" placeholder="İndirim %" class="min-h-[44px] rounded-[6px] border border-slate-200 bg-white px-3 text-base sm:text-sm">
                                                <input type="number" min="0" step="0.01" wire:model.defer="campaignCommissionDiscountRates.{{ $tracked->id }}" placeholder="Komisyon %" class="min-h-[44px] rounded-[6px] border border-slate-200 bg-white px-3 text-base sm:text-sm">
                                                <input type="number" min="1" wire:model.defer="campaignExpectedUnits.{{ $tracked->id }}" placeholder="Adet" class="min-h-[44px] rounded-[6px] border border-slate-200 bg-white px-3 text-base sm:text-sm">
                                            </div>
                                            <button type="button" wire:click="simulateCampaign({{ $tracked->id }})" class="mt-2 min-h-[44px] w-full rounded-[6px] bg-slate-900 px-3 text-xs font-medium text-white">Senaryo hesapla</button>
                                        </section>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </article>
            @empty
                <div class="rounded-[8px] border border-dashed border-slate-300 bg-slate-50/70 p-5 lg:col-span-2 xl:col-span-3">
                    <p class="text-xs font-semibold uppercase text-slate-500">Analiz ledger</p>
                    <h3 class="mt-1 text-base font-semibold text-slate-900">Filtrelerle eşleşen ürün bulunamadı</h3>
                    <p class="mt-1 text-sm text-slate-500">Arama veya kategori filtresini temizleyin; yeni analizler otomatik olarak bu listeye eklenir.</p>
                    <button type="button" wire:click="resetAnalysisFilters" class="mt-3 min-h-[44px] rounded-[6px] border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700">Filtreleri temizle</button>
                </div>
            @endforelse
        </div>
    </section>
    @elseif($activeModule === 'market')
        @include('livewire.partials.trendyol-booster-market')
    @elseif($activeModule === 'tracking')
        @include('livewire.partials.trendyol-booster-tracking')
    @elseif($activeModule === 'bestseller')
        @include('livewire.partials.trendyol-booster-bestseller')
    @elseif($activeModule === 'stock')
        <section
            x-data="{
                extensionReady: false,
                extensionVersion: '',
                extensionMessage: '',
                bridgeBusy: false,
                bridgeRequestId: '',
                bridgeTimer: null,
                init() {
                    window.postMessage({ source: 'zolm-booster-page', type: 'PING' }, window.location.origin);
                },
                async queryStock(wire, sourceUrl = null) {
                    const effectiveUrl = (sourceUrl || this.$refs.stockUrl.value || '').trim();

                    if (sourceUrl) {
                        this.$refs.stockUrl.value = effectiveUrl;
                        this.$refs.stockUrl.dispatchEvent(new Event('input', { bubbles: true }));
                        await wire.set('stockUrl', effectiveUrl, false);
                    }

                    if (!effectiveUrl) {
                        wire.runStockQuery();
                        return;
                    }
                    if (!this.extensionReady) {
                        this.extensionMessage = 'Chrome Companion bağlı değil; sunucu sorgusu çalıştırılıyor.';
                        wire.runStockQuery();
                        return;
                    }

                    this.bridgeBusy = true;
                    this.extensionMessage = '';
                    this.bridgeRequestId = `${Date.now()}-${Math.random().toString(16).slice(2)}`;
                    window.postMessage({
                        source: 'zolm-booster-page',
                        type: 'STOCK_QUERY',
                        request_id: this.bridgeRequestId,
                        source_url: effectiveUrl,
                    }, window.location.origin);

                    this.bridgeTimer = window.setTimeout(() => {
                        if (!this.bridgeBusy) return;
                        this.bridgeBusy = false;
                        this.extensionMessage = 'Chrome stok sorgusu zaman aşımına uğradı; sunucu sorgusu çalıştırılıyor.';
                        wire.runStockQuery();
                    }, 45000);
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

                    if (data.type !== 'STOCK_QUERY_RESULT' || data.request_id !== this.bridgeRequestId) return;

                    window.clearTimeout(this.bridgeTimer);
                    this.bridgeBusy = false;
                    this.extensionReady = true;
                    this.extensionMessage = '';
                    const response = data.response || {};
                    const stock = response.stock || {};
                    const message = response.ok
                        ? `${stock.seller_count || 0} satıcıda ${stock.total_stock || 0} stok okundu ve kaydedildi.`
                        : (response.message || 'Chrome stok sorgusu tamamlanamadı.');
                    wire.stockBridgeCompleted(message, Boolean(response.ok));
                },
            }"
            x-on:message.window="handleBridge($event, $wire)"
            class="rounded-[10px] border border-slate-200 bg-white shadow-sm"
        >
            <div class="border-b border-slate-200 px-4 py-4 lg:px-6">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase text-slate-500">Stok sorgulama</p>
                        <h2 class="mt-1 text-lg font-semibold text-slate-900">Stok ve satıcı takibi</h2>
                    </div>
                    <div class="flex flex-col items-stretch gap-2 sm:items-end">
                        <span x-show="extensionReady" x-cloak class="text-right text-xs font-medium text-emerald-700">
                            Chrome köprüsü hazır<span x-show="extensionVersion" x-text="` · v${extensionVersion}`"></span>
                        </span>
                        <span x-show="extensionMessage" x-cloak x-text="extensionMessage" class="text-right text-xs font-medium text-rose-700"></span>
                    </div>
                </div>
            </div>

            <div class="space-y-4 p-4 lg:p-6">
                <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-4">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
                        <label class="block min-w-0 flex-1">
                            <span class="text-xs font-medium text-slate-600">Trendyol ürün linki</span>
                            <input type="url" x-ref="stockUrl" wire:model.defer="stockUrl" placeholder="https://www.trendyol.com/...-p-123456"
                                   class="mt-1.5 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none focus:border-slate-400 sm:py-2 sm:text-sm">
                            @error('stockUrl') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </label>
                        <button type="button" x-on:click="queryStock($wire)" x-bind:disabled="bridgeBusy" wire:loading.attr="disabled" wire:target="runStockQuery"
                                @disabled(!$stockReady)
                                class="inline-flex min-h-[44px] w-full shrink-0 items-center justify-center gap-2 rounded-[6px] bg-slate-900 px-4 py-3 text-base font-medium text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60 sm:py-2 sm:text-sm lg:w-auto">
                            <x-lucide.icon name="boxes" class="h-4 w-4" />
                            <span x-show="!bridgeBusy" wire:loading.remove wire:target="runStockQuery">Stok sorgula</span>
                            <span x-show="bridgeBusy" x-cloak>Tarayıcıdan okunuyor...</span>
                            <span wire:loading wire:target="runStockQuery">Sorgulanıyor...</span>
                        </button>
                    </div>
                </div>

                @php
                    $latestStockCheck = $stockDashboard['latest_checks']->first();
                    $selectedStockCheck = $stockDashboard['selected_check'] ?? $latestStockCheck;
                    $selectedStockFavoriteCount = $selectedStockCheck ? data_get($selectedStockCheck->raw_payload, 'page.favorite_count') : null;
                    $selectedStockSellerNames = $selectedStockCheck?->sellers?->pluck('seller_name')->filter()->join(', ');
                    $selectedStockProduct = $selectedStockCheck?->trackedProduct;
                    $selectedStockTrackingActive = $selectedStockProduct?->tracking_status === 'active' && $selectedStockProduct?->watch_stock;
                    $stockTrend = collect($stockDashboard['trend'] ?? []);
                    $stockProductGroups = collect($stockDashboard['product_groups'] ?? []);
                    $stockCategoryOptions = collect($stockDashboard['stock_categories'] ?? []);
                    $stockProductTotal = (int) ($stockDashboard['product_group_total'] ?? $stockProductGroups->count());
                    $stockFilteredCheckCount = (int) ($stockDashboard['filtered_check_count'] ?? $stockProductGroups->sum('query_count'));
                    $stockActiveFilterCount = collect([
                        trim($stockHistorySearch) !== '',
                        $stockHistoryCategory !== 'all',
                        $stockHistoryStatus !== 'all',
                    ])->filter()->count();
                    $stockTrendData = $stockTrend->pluck('stock')->map(fn ($value) => (int) $value)->all();
                    $favoriteTrendData = $stockTrend->pluck('favorites')->filter(fn ($value) => $value !== null)->map(fn ($value) => (int) $value)->all();
                    $firstTrendPoint = $stockTrend->first();
                    $lastTrendPoint = $stockTrend->last();
                    $selectedStockDelta = $selectedStockCheck?->stock_delta;
                    $selectedStockDeltaTone = $selectedStockDelta === null ? 'text-slate-900' : ((int) $selectedStockDelta < 0 ? 'text-emerald-700' : ((int) $selectedStockDelta > 0 ? 'text-rose-700' : 'text-slate-900'));
                @endphp
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                        <p class="text-xs text-slate-500">Stok</p>
                        <p class="mt-1 text-lg font-semibold text-slate-900">{{ number_format((int) $stockDashboard['last_total_stock'], 0, ',', '.') }}</p>
                    </div>
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                        <p class="text-xs text-slate-500">Favori</p>
                        <p class="mt-1 text-lg font-semibold text-slate-900">
                            @if($selectedStockFavoriteCount !== null)
                                {{ number_format((int) $selectedStockFavoriteCount, 0, ',', '.') }}
                            @else
                                -
                            @endif
                        </p>
                    </div>
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                        <p class="text-xs text-slate-500">Satıcı</p>
                        <p class="mt-1 text-lg font-semibold text-slate-900">{{ $stockDashboard['seller_count'] }}</p>
                    </div>
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                        <label class="block">
                            <span class="text-xs text-slate-500">Sorgu zamanı</span>
                            @if($stockDashboard['latest_checks']->isNotEmpty())
                                <select wire:model.live.number="selectedStockCheckId"
                                        class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-2 py-2 text-sm font-semibold text-slate-900 outline-none focus:border-slate-400">
                                    <option value="">Son kayıt (otomatik)</option>
                                    @foreach($stockDashboard['latest_checks'] as $optionCheck)
                                        <option value="{{ $optionCheck->id }}">
                                            {{ $optionCheck->checked_at?->format('d.m.Y H:i') ?: 'Tarihsiz kayıt' }} · {{ \Illuminate\Support\Str::limit($optionCheck->title ?: 'Trendyol ürünü', 34) }}
                                        </option>
                                    @endforeach
                                </select>
                            @else
                                <p class="mt-1 text-sm font-semibold text-slate-900">-</p>
                            @endif
                        </label>
                    </div>
                </div>

                @if($selectedStockCheck)
                    <section class="rounded-lg border border-slate-200 bg-white p-4">
                        <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                            <div class="flex min-w-0 gap-3">
                                @if($selectedStockCheck->image_url)
                                    <div class="h-20 w-16 shrink-0 overflow-hidden rounded-[6px] border border-slate-100 bg-slate-50">
                                        <img src="{{ $selectedStockCheck->image_url }}" alt="" loading="lazy" class="h-full w-full object-cover">
                                    </div>
                                @else
                                    <div class="flex h-20 w-16 shrink-0 items-center justify-center rounded-[6px] border border-slate-100 bg-slate-50">
                                        <x-lucide.icon name="image" class="h-5 w-5 text-slate-300" />
                                    </div>
                                @endif
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold uppercase text-slate-500">Seçilen kayıt</p>
                                    <h3 class="mt-1 line-clamp-2 text-base font-semibold text-slate-900">{{ $selectedStockCheck->title ?: 'Trendyol ürünü' }}</h3>
                                    <p class="mt-1 truncate text-xs text-slate-500">{{ $selectedStockSellerNames ?: 'Satıcı bilgisi yok' }}</p>
                                    <div class="mt-2 flex flex-wrap gap-1.5 text-[11px]">
                                        <span class="rounded-[4px] border border-slate-200 bg-slate-50 px-2 py-1">Stok {{ number_format((int) $selectedStockCheck->total_stock, 0, ',', '.') }}</span>
                                        <span class="rounded-[4px] border border-slate-200 bg-slate-50 px-2 py-1">Satıcı {{ (int) $selectedStockCheck->seller_count }}</span>
                                        <span class="rounded-[4px] border border-slate-200 bg-slate-50 px-2 py-1">Tarih {{ $selectedStockCheck->checked_at?->format('d.m.Y H:i') ?: '-' }}</span>
                                        @if($selectedStockFavoriteCount !== null)
                                            <span class="rounded-[4px] border border-emerald-200 bg-emerald-50 px-2 py-1 text-emerald-700">Favori {{ number_format((int) $selectedStockFavoriteCount, 0, ',', '.') }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="flex w-full items-center gap-2 xl:w-auto xl:justify-end">
                                <a href="{{ $selectedStockCheck->source_url }}" target="_blank" rel="noopener noreferrer" title="Trendyol'da aç"
                                   class="inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-[6px] border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50">
                                    <x-lucide.icon name="external-link" class="h-4 w-4" />
                                </a>
                                <button type="button" x-on:click="queryStock($wire, @js($selectedStockCheck->source_url))" x-bind:disabled="bridgeBusy"
                                        title="Yeniden stok sorgula" aria-label="Yeniden stok sorgula"
                                        class="inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-[6px] border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50 disabled:cursor-wait disabled:opacity-70">
                                    <span x-show="!bridgeBusy"><x-lucide.icon name="refresh-cw" class="h-4 w-4" /></span>
                                    <span x-show="bridgeBusy" x-cloak><x-lucide.icon name="refresh-cw" class="h-4 w-4 animate-spin" /></span>
                                </button>
                                <button type="button" wire:click="toggleStockCheckFavorite({{ $selectedStockCheck->id }})" wire:loading.attr="disabled" wire:target="toggleStockCheckFavorite({{ $selectedStockCheck->id }})"
                                        title="{{ $selectedStockProduct?->is_favorite ? 'Favorilerden çıkar' : 'Favorile' }}" aria-label="{{ $selectedStockProduct?->is_favorite ? 'Favorilerden çıkar' : 'Favorile' }}"
                                        class="inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-[6px] border {{ $selectedStockProduct?->is_favorite ? 'border-rose-200 bg-rose-50 text-rose-600' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }} transition">
                                    <x-lucide.icon name="heart" class="h-4 w-4" />
                                </button>
                                <button type="button" wire:click="followStockCheck({{ $selectedStockCheck->id }})" wire:loading.attr="disabled" wire:target="followStockCheck({{ $selectedStockCheck->id }})" @disabled($selectedStockTrackingActive)
                                        class="inline-flex min-h-[44px] flex-1 items-center justify-center gap-2 rounded-[6px] bg-slate-900 px-4 py-3 text-base font-medium text-white transition hover:bg-slate-800 disabled:cursor-default disabled:bg-emerald-700 sm:flex-none sm:py-2 sm:text-sm">
                                    <x-lucide.icon name="{{ $selectedStockTrackingActive ? 'check' : 'radar' }}" class="h-4 w-4" />
                                    {{ $selectedStockTrackingActive ? 'Takipte' : 'Takibe al' }}
                                </button>
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-[minmax(0,1.4fr)_minmax(260px,0.6fr)]">
                            <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <h4 class="text-sm font-semibold text-slate-900">Stok trendi</h4>
                                        <p class="mt-0.5 text-xs text-slate-500">Son {{ $stockTrend->count() }} sorgu kaydı</p>
                                    </div>
                                    <span class="inline-flex items-center gap-1 rounded-[4px] border border-slate-200 bg-white px-2 py-1 text-[11px] font-medium {{ $selectedStockDeltaTone }}">
                                        <x-lucide.icon name="{{ (int) ($selectedStockDelta ?? 0) < 0 ? 'trending-down' : ((int) ($selectedStockDelta ?? 0) > 0 ? 'trending-up' : 'minus') }}" class="h-3 w-3" />
                                        {{ $selectedStockDelta === null ? 'Baz kayıt' : (((int) $selectedStockDelta > 0 ? '+' : '').number_format((int) $selectedStockDelta, 0, ',', '.')) }}
                                    </span>
                                </div>
                                <div class="mt-3 h-20 w-full">
                                    <x-zolm.sparkline :data="$stockTrendData" width="420" height="80" color="text-indigo-500" :fill="true" />
                                </div>
                                <div class="mt-2 flex items-center justify-between text-[11px] text-slate-500">
                                    <span>{{ data_get($firstTrendPoint, 'label', '-') }} · {{ number_format((int) data_get($firstTrendPoint, 'stock', 0), 0, ',', '.') }}</span>
                                    <span>{{ data_get($lastTrendPoint, 'label', '-') }} · {{ number_format((int) data_get($lastTrendPoint, 'stock', 0), 0, ',', '.') }}</span>
                                </div>
                            </div>

                            <div class="rounded-[8px] border border-slate-200 bg-white p-3">
                                <h4 class="text-sm font-semibold text-slate-900">Favori ve satış sinyali</h4>
                                <div class="mt-3 h-14 w-full">
                                    <x-zolm.sparkline :data="$favoriteTrendData" width="260" height="56" color="text-emerald-500" :fill="true" />
                                </div>
                                <div class="mt-3 space-y-2 text-xs">
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-slate-500">Stok düşüşü</span>
                                        <span class="font-semibold text-slate-900">{{ number_format((int) $selectedStockCheck->estimated_sales, 0, ',', '.') }}</span>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-slate-500">Önceki stok</span>
                                        <span class="font-semibold text-slate-900">{{ $selectedStockCheck->previous_total_stock !== null ? number_format((int) $selectedStockCheck->previous_total_stock, 0, ',', '.') : '-' }}</span>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-slate-500">Durum</span>
                                        <span class="font-semibold {{ $selectedStockCheck->stock_status === 'in_stock' ? 'text-emerald-700' : 'text-rose-700' }}">{{ $selectedStockCheck->stock_status === 'in_stock' ? 'Stokta' : 'Stok dışı' }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                @endif

                <div class="rounded-lg border border-slate-200 bg-white">
                    <div class="flex flex-col gap-2 border-b border-slate-200 bg-slate-50/60 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex min-w-0 items-center gap-2">
                            <h3 class="text-sm font-semibold text-slate-900">Kayıt geçmişi</h3>
                            @if($stockActiveFilterCount > 0)
                                <span class="rounded-[4px] border border-slate-200 bg-white px-1.5 py-0.5 text-[10px] font-medium text-slate-600">{{ $stockActiveFilterCount }} filtre aktif</span>
                            @endif
                        </div>
                        <span class="text-xs text-slate-500">{{ $stockProductGroups->count() }} / {{ $stockProductTotal }} ürün · {{ $stockFilteredCheckCount }} kayıt</span>
                    </div>
                    <div class="border-b border-slate-200 bg-white p-3">
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(260px,1fr)_190px_180px_190px_44px]">
                            <label class="relative min-w-0">
                                <span class="sr-only">Kayıt geçmişinde ara</span>
                                <x-lucide.icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                <input type="search" wire:model.live.debounce.300ms="stockHistorySearch"
                                       placeholder="Ürün, marka, satıcı veya ürün no ara"
                                       class="min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white py-2 pl-9 pr-3 text-base text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-slate-400 sm:text-sm">
                            </label>
                            <label class="min-w-0">
                                <span class="sr-only">Kategori filtresi</span>
                                <select wire:model.live="stockHistoryCategory" class="min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-700 outline-none focus:border-slate-400 sm:text-sm">
                                    <option value="all">Tüm kategoriler</option>
                                    @foreach($stockCategoryOptions as $stockCategoryOption)
                                        <option value="{{ $stockCategoryOption }}">{{ $stockCategoryOption }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="min-w-0">
                                <span class="sr-only">Durum filtresi</span>
                                <select wire:model.live="stockHistoryStatus" class="min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-700 outline-none focus:border-slate-400 sm:text-sm">
                                    <option value="all">Tüm durumlar</option>
                                    <option value="favorites">Favorilenenler</option>
                                    <option value="tracking">Takiptekiler</option>
                                    <option value="declining">Stoku azalanlar</option>
                                    <option value="out_of_stock">Tükenenler</option>
                                </select>
                            </label>
                            <label class="min-w-0">
                                <span class="sr-only">Kayıt sıralaması</span>
                                <select wire:model.live="stockHistorySort" class="min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-700 outline-none focus:border-slate-400 sm:text-sm">
                                    <option value="latest">Son sorgulanan</option>
                                    <option value="name_asc">Ürün adı A-Z</option>
                                    <option value="stock_asc">Stok: azdan çoğa</option>
                                    <option value="stock_desc">Stok: çoktan aza</option>
                                    <option value="favorites_desc">Favori: çoktan aza</option>
                                    <option value="queries_desc">En çok sorgulanan</option>
                                </select>
                            </label>
                            <button type="button" wire:click="resetStockHistoryFilters" title="Filtreleri temizle" aria-label="Filtreleri temizle"
                                    @disabled($stockActiveFilterCount === 0 && $stockHistorySort === 'latest')
                                    class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-slate-500 transition hover:bg-slate-50 hover:text-slate-900 disabled:cursor-not-allowed disabled:opacity-40 sm:col-span-2 sm:w-11 sm:justify-self-end xl:col-span-1 xl:justify-self-auto">
                                <x-lucide.icon name="rotate-ccw" class="h-4 w-4" />
                                <span class="sm:hidden">Filtreleri temizle</span>
                            </button>
                        </div>
                        @if(!empty($stockDashboard['history_truncated']))
                            <p class="mt-2 text-[11px] text-amber-700">Performans için son 500 sorgu içinde filtreleniyor.</p>
                        @endif
                    </div>
                    <div class="grid grid-cols-1 gap-3 p-3 lg:grid-cols-2 xl:grid-cols-3">
                    @forelse($stockProductGroups as $stockGroup)
                        @php
                            $groupLatestCheck = $stockGroup['latest_check'];
                            $groupChecks = collect($stockGroup['checks'] ?? []);
                            $groupTrend = collect($stockGroup['trend'] ?? []);
                            $groupStockTrendData = $groupTrend->pluck('stock')->map(fn ($value) => (int) $value)->all();
                            $groupFirstTrendPoint = $groupTrend->first();
                            $groupLastTrendPoint = $groupTrend->last();
                            $groupFavoriteCount = $stockGroup['latest_favorite_count'];
                            $groupCategoryName = $stockGroup['category_name'] ?? '';
                            $groupSellerNames = $stockGroup['seller_names'] ?: $groupLatestCheck?->sellers?->pluck('seller_name')->filter()->join(', ');
                            $groupTrackedProduct = $groupLatestCheck?->trackedProduct;
                            $groupTrackingActive = $groupTrackedProduct?->tracking_status === 'active' && $groupTrackedProduct?->watch_stock;
                            $groupIsSelected = $groupChecks->contains(fn ($groupCheck) => (int) $groupCheck->id === (int) ($stockDashboard['selected_check_id'] ?? 0));
                            $groupStockDelta = (int) ($groupLatestCheck?->stock_delta ?? 0);
                            $groupQueryCount = (int) ($stockGroup['query_count'] ?? $groupChecks->count());
                        @endphp
                        @if($groupLatestCheck)
                            <article x-data="{ datesOpen: false }" wire:key="stock-product-group-{{ $stockGroup['key_hash'] }}" class="rounded-[8px] border {{ $groupIsSelected ? 'border-slate-900 bg-white shadow-sm' : 'border-slate-200 bg-white' }} p-3 transition hover:shadow-sm">
                                <div class="flex gap-3">
                                    @if($groupLatestCheck->image_url)
                                        <div class="h-20 w-14 shrink-0 overflow-hidden rounded-[6px] border border-slate-100 bg-slate-50">
                                            <img src="{{ $groupLatestCheck->image_url }}" alt="" loading="lazy" class="h-full w-full object-cover">
                                        </div>
                                    @else
                                        <div class="flex h-20 w-14 shrink-0 items-center justify-center rounded-[6px] border border-slate-100 bg-slate-50">
                                            <x-lucide.icon name="image" class="h-5 w-5 text-slate-300" />
                                        </div>
                                    @endif
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="min-w-0">
                                                <p class="line-clamp-2 text-sm font-semibold leading-snug text-slate-900">{{ $groupLatestCheck->title ?: 'Trendyol ürünü' }}</p>
                                                <p class="mt-1 truncate text-xs text-slate-500">{{ collect([$groupSellerNames ?: 'Satıcı bilgisi yok', $groupCategoryName ?: 'Kategori yok'])->join(' · ') }}</p>
                                            </div>
                                            <div class="relative shrink-0" x-on:click.outside="datesOpen = false" x-on:keydown.escape.window="datesOpen = false">
                                                <button type="button" x-on:click="datesOpen = !datesOpen" x-bind:aria-expanded="datesOpen" title="Sorgu tarihlerini göster"
                                                        class="inline-flex items-center gap-1 rounded-[6px] border {{ $groupIsSelected ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-slate-50 text-slate-600 hover:bg-white' }} px-2 py-1 text-[11px] font-medium transition">
                                                    <x-lucide.icon name="clock-3" class="h-3 w-3" />
                                                    {{ $groupLatestCheck->checked_at?->format('d.m H:i') ?: '-' }}
                                                    <x-lucide.icon name="chevron-down" class="h-3 w-3 transition" x-bind:class="datesOpen ? 'rotate-180' : ''" />
                                                </button>

                                                <div x-show="datesOpen" x-cloak x-transition.origin.top.right
                                                     class="absolute right-0 z-50 mt-1.5 w-44 overflow-hidden rounded-[8px] border border-slate-200 bg-white p-1 shadow-md">
                                                    <div class="max-h-56 space-y-0.5 overflow-y-auto overscroll-contain">
                                                        @foreach($groupChecks as $groupCheck)
                                                            @php
                                                                $dateIsSelected = (int) ($stockDashboard['selected_check_id'] ?? 0) === (int) $groupCheck->id;
                                                            @endphp
                                                            <button type="button" wire:click="$set('selectedStockCheckId', {{ $groupCheck->id }})" x-on:click="datesOpen = false"
                                                                    class="flex min-h-9 w-full items-center justify-between gap-2 rounded-[6px] px-2.5 py-2 text-left text-xs font-medium transition {{ $dateIsSelected ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}">
                                                                <span class="truncate">{{ $groupCheck->checked_at?->format('d.m.Y H:i') ?: 'Tarihsiz' }}</span>
                                                                @if($dateIsSelected)
                                                                    <x-lucide.icon name="check" class="h-3.5 w-3.5 shrink-0" />
                                                                @endif
                                                            </button>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-3 grid grid-cols-3 gap-2 text-xs">
                                    <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 p-2">
                                        <p class="text-[10px] text-slate-500">Son stok</p>
                                        <p class="mt-0.5 font-semibold text-slate-900">{{ number_format((int) $groupLatestCheck->total_stock, 0, ',', '.') }}</p>
                                    </div>
                                    <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 p-2">
                                        <p class="text-[10px] text-slate-500">Son favori</p>
                                        <p class="mt-0.5 font-semibold text-slate-900">{{ $groupFavoriteCount !== null ? number_format((int) $groupFavoriteCount, 0, ',', '.') : '-' }}</p>
                                    </div>
                                    <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 p-2">
                                        <p class="text-[10px] text-slate-500">Sorgu</p>
                                        <p class="mt-0.5 font-semibold text-slate-900">{{ $groupQueryCount }}</p>
                                    </div>
                                </div>

                                <div class="mt-3 rounded-[8px] border border-slate-200 bg-slate-50/60 p-2.5">
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="min-w-0">
                                            <p class="text-xs font-semibold text-slate-900">Ürün stok trendi</p>
                                            <p class="mt-0.5 text-[11px] text-slate-500">Son {{ $groupTrend->count() }} sorgu</p>
                                        </div>
                                        <span class="inline-flex shrink-0 items-center gap-1 rounded-[4px] border border-slate-200 bg-white px-1.5 py-0.5 text-[10px] font-medium {{ $groupStockDelta < 0 ? 'text-emerald-700' : ($groupStockDelta > 0 ? 'text-rose-700' : 'text-slate-500') }}">
                                            <x-lucide.icon name="{{ $groupStockDelta < 0 ? 'trending-down' : ($groupStockDelta > 0 ? 'trending-up' : 'minus') }}" class="h-3 w-3" />
                                            {{ $groupStockDelta > 0 ? '+' : '' }}{{ number_format($groupStockDelta, 0, ',', '.') }}
                                        </span>
                                    </div>
                                    <div class="mt-2 h-14 w-full">
                                        <x-zolm.sparkline :data="$groupStockTrendData" width="260" height="56" color="text-indigo-500" :fill="true" />
                                    </div>
                                    <div class="mt-1.5 flex items-center justify-between gap-2 text-[10px] text-slate-500">
                                        <span class="truncate">{{ data_get($groupFirstTrendPoint, 'label', '-') }} · {{ number_format((int) data_get($groupFirstTrendPoint, 'stock', 0), 0, ',', '.') }}</span>
                                        <span class="truncate text-right">{{ data_get($groupLastTrendPoint, 'label', '-') }} · {{ number_format((int) data_get($groupLastTrendPoint, 'stock', 0), 0, ',', '.') }}</span>
                                    </div>
                                </div>

                                <div class="mt-3 flex items-center justify-between gap-2">
                                    <span class="inline-flex min-w-0 items-center gap-1 rounded-[4px] border border-slate-200 bg-slate-50 px-1.5 py-0.5 text-[10px] font-medium text-slate-500">
                                        <x-lucide.icon name="calendar-days" class="h-3 w-3" />
                                        {{ $groupQueryCount }} tarih
                                    </span>
                                    <div class="ml-auto flex shrink-0 items-center gap-1">
                                        <a href="{{ $groupLatestCheck->source_url }}" target="_blank" rel="noopener noreferrer" title="Trendyol'da aç"
                                           class="inline-flex h-9 w-9 items-center justify-center rounded-[6px] border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50 hover:text-slate-900">
                                            <x-lucide.icon name="external-link" class="h-3.5 w-3.5" />
                                        </a>
                                        <button type="button" x-on:click="queryStock($wire, @js($groupLatestCheck->source_url))" x-bind:disabled="bridgeBusy"
                                                title="Yeniden stok sorgula" aria-label="Yeniden stok sorgula"
                                                class="inline-flex h-9 w-9 items-center justify-center rounded-[6px] border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50 hover:text-slate-900 disabled:cursor-wait disabled:opacity-70">
                                            <span x-show="!bridgeBusy"><x-lucide.icon name="refresh-cw" class="h-3.5 w-3.5" /></span>
                                            <span x-show="bridgeBusy" x-cloak><x-lucide.icon name="refresh-cw" class="h-3.5 w-3.5 animate-spin" /></span>
                                        </button>
                                        <button type="button" wire:click="toggleStockCheckFavorite({{ $groupLatestCheck->id }})" wire:loading.attr="disabled" wire:target="toggleStockCheckFavorite({{ $groupLatestCheck->id }})"
                                                title="{{ $groupTrackedProduct?->is_favorite ? 'Favorilerden çıkar' : 'Favorile' }}"
                                                class="inline-flex h-9 w-9 items-center justify-center rounded-[6px] border {{ $groupTrackedProduct?->is_favorite ? 'border-rose-200 bg-rose-50 text-rose-600' : 'border-slate-200 bg-white text-slate-500 hover:bg-slate-50 hover:text-slate-900' }} transition">
                                            <x-lucide.icon name="heart" class="h-3.5 w-3.5" />
                                        </button>
                                        <button type="button" wire:click="followStockCheck({{ $groupLatestCheck->id }})" wire:loading.attr="disabled" wire:target="followStockCheck({{ $groupLatestCheck->id }})" @disabled($groupTrackingActive)
                                                title="{{ $groupTrackingActive ? 'Takipte' : 'Takibe al' }}"
                                                class="inline-flex h-9 w-9 items-center justify-center rounded-[6px] bg-slate-900 text-white transition hover:bg-slate-800 disabled:cursor-default disabled:bg-emerald-700">
                                            <x-lucide.icon name="{{ $groupTrackingActive ? 'check' : 'radar' }}" class="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                </div>
                            </article>
                        @endif
                    @empty
                        <div class="flex flex-col items-start gap-3 rounded-[8px] border border-dashed border-slate-300 bg-slate-50/70 p-4 text-sm text-slate-500 lg:col-span-2 xl:col-span-3 sm:flex-row sm:items-center sm:justify-between">
                            <span>{{ $stockProductTotal > 0 ? 'Filtrelerle eşleşen ürün bulunamadı.' : 'Henüz stok kaydı yok.' }}</span>
                            @if($stockProductTotal > 0)
                                <button type="button" wire:click="resetStockHistoryFilters" class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 sm:w-auto sm:py-2">
                                    <x-lucide.icon name="rotate-ccw" class="h-4 w-4" />
                                    Filtreleri temizle
                                </button>
                            @endif
                        </div>
                    @endforelse
                    </div>
                </div>
            </div>
        </section>
    @elseif($activeModule === 'keyword')
        <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
                <label class="min-w-0 flex-1">
                    <span class="text-xs font-medium text-slate-600">Satmak İstediğiniz Ürün</span>
                    <input type="search" wire:model.defer="keywordLookupInput" placeholder="Satmak istediğiniz ürün adını yazın"
                           class="mt-1.5 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none focus:border-slate-400 sm:py-2 sm:text-sm">
                    @error('keywordLookupInput') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                </label>
                <button type="button" wire:click="runKeywordLookup" wire:loading.attr="disabled" wire:target="runKeywordLookup"
                        @disabled(!$keywordLookupReady)
                        class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] bg-slate-900 px-4 py-3 text-base font-medium text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto sm:py-2 sm:text-sm">
                    <x-lucide.icon name="search" class="h-4 w-4" />
                    Popüler Kelimeleri Bul
                </button>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-3 xl:grid-cols-4">
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3"><p class="text-xs text-slate-500">Toplam arama</p><p class="mt-1 text-lg font-semibold text-slate-900">{{ $keywordLookupDashboard['total'] }}</p></div>
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3"><p class="text-xs text-slate-500">Tekil kelime</p><p class="mt-1 text-lg font-semibold text-slate-900">{{ $keywordLookupDashboard['unique_keywords'] }}</p></div>
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3"><p class="text-xs text-slate-500">Son sonuç</p><p class="mt-1 text-lg font-semibold text-slate-900">{{ $keywordLookupDashboard['last_result_count'] }}</p></div>
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3"><p class="text-xs text-slate-500">SEO modu</p><p class="mt-1 text-lg font-semibold text-emerald-700">Hazır</p></div>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-2">
                @forelse($keywordLookupDashboard['latest'] as $lookup)
                    @php
                        $stopWords = ['ve', 'ile', 'icin', 'için', 'bir', 'cok', 'çok', 'adet', 'yeni', 'model', 'renk', 'ozel', 'özel'];
                        $popularWords = collect((array) $lookup->top_products)
                            ->flatMap(fn ($product) => preg_split('/\s+/u', mb_strtolower((string) ($product['title'] ?? ''), 'UTF-8')) ?: [])
                            ->map(fn ($word) => trim(preg_replace('/[^\pL\pN]+/u', '', $word) ?: ''))
                            ->filter(fn ($word) => mb_strlen($word, 'UTF-8') > 2 && ! in_array($word, $stopWords, true))
                            ->countBy()
                            ->sortDesc()
                            ->take(16);
                    @endphp
                    <article class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-900">{{ $lookup->keyword }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $lookup->searched_at?->format('d.m.Y H:i') }}</p>
                            </div>
                            <span class="rounded-[6px] border border-slate-200 bg-white px-2 py-1 font-mono text-xs text-slate-600">{{ $lookup->result_count }} sonuç</span>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2">
                            @forelse($popularWords as $word => $count)
                                <span class="rounded-[6px] border border-slate-200 bg-white px-2 py-1 text-xs font-medium text-slate-700">{{ $word }} <span class="font-mono text-slate-400">{{ $count }}</span></span>
                            @empty
                                <span class="text-xs text-slate-500">Popüler kelime çıkarılamadı.</span>
                            @endforelse
                        </div>
                        <div class="mt-3 space-y-2 border-t border-slate-200 pt-3">
                            @forelse(array_slice((array) $lookup->top_products, 0, 4) as $product)
                                <div class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600">
                                    <span class="font-mono text-slate-400">#{{ $product['trendyol_product_id'] ?? '-' }}</span>
                                    <span class="ml-2">{{ $product['title'] ?: 'Ürün başlığı okunamadı' }}</span>
                                </div>
                            @empty
                                <div class="rounded-[6px] border border-dashed border-slate-300 bg-white px-3 py-2 text-xs text-slate-500">Trendyol sonucu okunamadı veya erişim sınırlı.</div>
                            @endforelse
                        </div>
                    </article>
                @empty
                    <div class="rounded-[8px] border border-dashed border-slate-300 bg-slate-50/70 p-4 text-sm text-slate-500 lg:col-span-2">Popüler kelimeler bekleniyor. Ürün adını girin; Trendyol arama sonuçlarından başlık ve açıklama için kullanılacak kelimeler çıkarılsın.</div>
                @endforelse
            </div>
        </section>
    @elseif($activeModule === 'competitor')
        @if($selectedStoreWatchId && $this->storeDetail)
            @include('livewire.partials.trendyol-booster-store-detail')
        @else
            <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
                    <label class="min-w-0 flex-1">
                        <span class="text-xs font-medium text-slate-600">Trendyol mağaza linki</span>
                        <input type="url" wire:model.defer="storeWatchUrl" placeholder="https://www.trendyol.com/magaza/...-m-123"
                               class="mt-1.5 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none focus:border-slate-400 sm:py-2 sm:text-sm">
                        @error('storeWatchUrl') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </label>
                    <button type="button" wire:click="scanStoreWatch" wire:loading.attr="disabled" wire:target="scanStoreWatch"
                            @disabled(!$storeWatchReady)
                            class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] bg-slate-900 px-4 py-3 text-base font-medium text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto sm:py-2 sm:text-sm">
                        <x-lucide.icon name="users" class="h-4 w-4" />
                        Rakibi tara
                    </button>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-3 xl:grid-cols-6">
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3"><p class="text-xs text-slate-500">Mağaza</p><p class="mt-1 text-lg font-semibold text-slate-900">{{ $storeWatchDashboard['total'] }}</p></div>
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3"><p class="text-xs text-slate-500">Yeni ürün</p><p class="mt-1 text-lg font-semibold text-emerald-700">{{ $storeWatchDashboard['new_products'] }}</p></div>
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3"><p class="text-xs text-slate-500">Fiyat değişimi</p><p class="mt-1 text-lg font-semibold text-amber-700">{{ $storeWatchDashboard['price_changes'] }}</p></div>
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3"><p class="text-xs text-slate-500">Kampanyalı</p><p class="mt-1 text-lg font-semibold text-sky-700">{{ $storeWatchDashboard['campaign_count'] ?? 0 }}</p></div>
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3"><p class="text-xs text-slate-500">Yorum</p><p class="mt-1 text-lg font-semibold text-slate-900">{{ number_format($storeWatchDashboard['total_reviews'] ?? 0, 0, '', '.') }}</p></div>
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3"><p class="text-xs text-slate-500">Tarama</p><p class="mt-1 text-lg font-semibold text-slate-900">{{ collect($storeWatchDashboard['latest'])->sum('scan_count') }}</p></div>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-3 xl:grid-cols-2">
                    @forelse($storeWatchDashboard['latest'] as $watch)
                        @php
                            $latestStoreSnapshot = $watch->latestSnapshot;
                            $storeShowsPreservedCatalog = $latestStoreSnapshot?->status === 'failed';
                        @endphp
                        <article class="rounded-[8px] border {{ $storeShowsPreservedCatalog ? 'border-amber-200 bg-amber-50/40' : 'border-slate-200 bg-slate-50/60' }} p-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold uppercase text-slate-500">Rakip mağaza</p>
                                    <div class="mt-1 flex items-center gap-2">
                                        <h3 class="truncate text-base font-semibold text-slate-900">{{ $watch->store_name }}</h3>
                                        @if($storeShowsPreservedCatalog)
                                            <span class="rounded-[4px] bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-800">Korunan veri</span>
                                        @endif
                                        @if($watch->store_rating)
                                            <span class="rounded-[4px] bg-emerald-100 px-1.5 py-0.5 text-xs font-semibold text-emerald-800">{{ number_format($watch->store_rating, 1, ',', '.') }}</span>
                                        @endif
                                    </div>
                                    <p class="mt-1 text-xs text-slate-500">
                                        ID: {{ $watch->store_id ?: '-' }} · {{ $watch->last_checked_at?->format('d.m.Y H:i') }}
                                        @if($latestStoreSnapshot)
                                            · Tarama #{{ $latestStoreSnapshot->scan_number }}
                                        @endif
                                    </p>
                                    @if($storeShowsPreservedCatalog)
                                        <p class="mt-1 text-xs text-amber-700">Son deneme okunamadı; ürünler son başarılı katalogdan gösteriliyor.</p>
                                    @endif
                                </div>
                                <button type="button" wire:click="viewStoreDetail({{ $watch->id }})" class="inline-flex min-h-9 shrink-0 items-center justify-center gap-1.5 rounded-[6px] bg-slate-900 px-3 py-2 text-xs font-semibold text-white transition hover:bg-slate-800">
                                    <x-lucide.icon name="external-link" class="h-3.5 w-3.5" />
                                    Detaylı İncele
                                </button>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2 text-xs">
                                <span class="rounded-[6px] border border-slate-200 bg-white px-2 py-1">Ürün {{ $watch->best_seller_count }}</span>
                                <span class="rounded-[6px] border border-emerald-200 bg-emerald-50 px-2 py-1 text-emerald-700">Yeni {{ $watch->new_product_count }}</span>
                                <span class="rounded-[6px] border border-amber-200 bg-amber-50 px-2 py-1 text-amber-700">Fiyat {{ $watch->price_change_count }}</span>
                                @if($watch->latestSnapshot && (($watch->latestSnapshot->total_favorites ?? 0) > 0))
                                    <span class="rounded-[6px] border border-slate-200 bg-white px-2 py-1">Favori {{ number_format((int) $watch->latestSnapshot->total_favorites, 0, '', '.') }}</span>
                                @endif
                                @if($watch->campaign_count > 0)
                                    <span class="rounded-[6px] border border-sky-200 bg-sky-50 px-2 py-1 text-sky-700">Kampanya {{ $watch->campaign_count }}</span>
                                @endif
                            </div>
                        <div class="mt-3 max-h-[400px] overflow-y-auto space-y-2 pr-2">
                            @forelse($watch->items as $item)
                                <div class="rounded-[6px] border border-slate-200 bg-white p-3 flex gap-3">
                                    @if($item->image_url)
                                        <div class="h-16 w-12 shrink-0 rounded-[4px] border border-slate-100 overflow-hidden bg-slate-50">
                                            <img src="{{ $item->image_url }}" alt="" loading="lazy" class="h-full w-full object-cover">
                                        </div>
                                    @endif
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-start justify-between gap-2">
                                            @if($item->source_url)
                                                <a href="{{ $item->source_url }}" target="_blank" rel="noopener noreferrer" class="min-w-0 truncate text-sm font-medium text-slate-900 underline-offset-2 hover:text-slate-700 hover:underline" title="{{ $item->title }}">
                                                    <span class="text-xs text-slate-500">#{{ $item->rank }}</span> {{ $item->title }}
                                                </a>
                                            @else
                                                <p class="min-w-0 truncate text-sm font-medium text-slate-900" title="{{ $item->title }}">
                                                    <span class="text-xs text-slate-500">#{{ $item->rank }}</span> {{ $item->title }}
                                                </p>
                                            @endif
                                            <div class="text-right shrink-0">
                                                @if($item->original_price && $item->original_price > $item->sale_price)
                                                    <span class="text-[10px] text-slate-400 line-through block">{{ $this->formatMoney($item->original_price) }}</span>
                                                @endif
                                                <span class="text-xs font-semibold {{ $item->price_delta < 0 ? 'text-emerald-600' : ($item->price_delta > 0 ? 'text-rose-600' : 'text-slate-700') }}">{{ $this->formatMoney($item->sale_price) }}</span>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-1.5 flex flex-wrap items-center gap-2">
                                            @if($item->is_new)
                                                <span class="rounded-[4px] bg-emerald-100 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-800">YENİ</span>
                                            @endif
                                            @if($item->is_first_seller)
                                                <span class="rounded-[4px] bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-800">1. SATICI</span>
                                            @endif
                                            @if($item->rating)
                                                <span class="flex items-center text-[10px] text-slate-600 font-medium">
                                                    <svg class="h-3 w-3 text-amber-400 mr-0.5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>
                                                    {{ number_format($item->rating, 1, ',', '.') }}
                                                    @if($item->review_count) <span class="text-slate-400 ml-1">({{ number_format($item->review_count, 0, '', '.') }})</span> @endif
                                                </span>
                                            @endif
                                            @if($item->price_delta != 0)
                                                <span class="text-[10px] font-medium {{ $item->price_delta < 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ $item->price_delta > 0 ? '+' : '' }}{{ $this->formatMoney($item->price_delta) }}</span>
                                            @endif
                                        </div>

                                        @if(!empty($item->campaign_badges))
                                            <div class="mt-1.5 flex flex-wrap gap-1">
                                                @foreach(array_slice($item->campaign_badges, 0, 3) as $badge)
                                                    <span class="rounded-[4px] border border-sky-200 bg-sky-50 px-1.5 py-0.5 text-[9px] font-medium text-sky-700">{{ $badge }}</span>
                                                @endforeach
                                                @if(count($item->campaign_badges) > 3)
                                                    <span class="rounded-[4px] border border-slate-200 bg-slate-50 px-1.5 py-0.5 text-[9px] font-medium text-slate-500">+{{ count($item->campaign_badges) - 3 }}</span>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div class="rounded-[6px] border border-dashed border-slate-300 bg-white px-3 py-2 text-xs text-slate-500">Mağaza ürünleri bulunamadı.</div>
                            @endforelse
                        </div>
                    </article>
                @empty
                    <div class="rounded-[8px] border border-dashed border-slate-300 bg-slate-50/70 p-4 text-sm text-slate-500 xl:col-span-2">Henüz rakip mağaza takibi yok. Yukarıdan bir mağaza tarayabilirsiniz.</div>
                @endforelse
            </div>
        </section>
        @endif
    @elseif($activeModule === 'trends')
        <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 p-4 lg:p-6">
                <div class="flex flex-col gap-3 xl:flex-row xl:items-end xl:justify-between">
                    <div class="grid min-w-0 flex-1 grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(220px,1fr)_180px_minmax(260px,1.2fr)]">
                        <label>
                            <span class="text-xs font-medium text-slate-600">Kelime ara</span>
                            <input type="search" wire:model.live.debounce.300ms="trendSearch" placeholder="Kategori veya kelime"
                                   class="mt-1.5 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none focus:border-slate-400 sm:py-2 sm:text-sm">
                        </label>
                        <label>
                            <span class="text-xs font-medium text-slate-600">Rekabet</span>
                            <select wire:model.live="trendCompetition" class="mt-1.5 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none focus:border-slate-400 sm:py-2 sm:text-sm">
                                <option value="all">Tümü</option>
                                <option value="low">Düşük</option>
                                <option value="medium">Orta</option>
                                <option value="high">Yüksek</option>
                            </select>
                        </label>
                        <label class="sm:col-span-2 xl:col-span-1">
                            <span class="text-xs font-medium text-slate-600">Sırasını izleyeceğim ürün</span>
                            <select wire:model.live="trendTargetProductId" class="mt-1.5 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none focus:border-slate-400 sm:py-2 sm:text-sm">
                                <option value="">Ürün seçin</option>
                                @foreach($trackedProductOptions as $productOption)
                                    <option value="{{ $productOption->id }}">{{ $productOption->title ?: 'Ürün '.$productOption->trendyol_product_id }}</option>
                                @endforeach
                            </select>
                            @error('trendTargetProductId') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                    </div>
                    <button type="button" wire:click="discoverTrendKeywords" wire:loading.attr="disabled" wire:target="discoverTrendKeywords" @disabled(!$trendReady)
                            class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] bg-slate-900 px-4 py-3 text-base font-medium text-white transition hover:bg-slate-800 disabled:opacity-60 sm:w-auto sm:py-2 sm:text-sm">
                        <x-lucide.icon name="scan-search" class="h-4 w-4" />
                        <span wire:loading.remove wire:target="discoverTrendKeywords">Rakiplerden keşfet</span>
                        <span wire:loading wire:target="discoverTrendKeywords">Kataloglar işleniyor...</span>
                    </button>
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500">
                    <span>{{ $trendDashboard['source_store_count'] }} aktif rakip mağaza</span>
                    <span>Son keşif: {{ $trendDashboard['last_scanned_at'] ? \Carbon\Carbon::parse($trendDashboard['last_scanned_at'])->format('d.m.Y H:i') : 'Henüz yapılmadı' }}</span>
                    <span class="text-slate-400">Puan; rakip ürün tekrarı, yorum, favori, puan ve kampanya sinyallerinden hesaplanır.</span>
                </div>
            </div>

            <div class="space-y-4 p-4 lg:p-6">
                <div class="grid grid-cols-2 gap-3 xl:grid-cols-4">
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3"><p class="text-xs text-slate-500">Keşfedilen kelime</p><p class="mt-1 text-lg font-semibold text-slate-900">{{ $trendDashboard['total'] }}</p></div>
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3"><p class="text-xs text-slate-500">Yükselen</p><p class="mt-1 text-lg font-semibold text-emerald-700">{{ $trendDashboard['rising_count'] }}</p></div>
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3"><p class="text-xs text-slate-500">Düşük rekabet fırsatı</p><p class="mt-1 text-lg font-semibold text-sky-700">{{ $trendDashboard['opportunity_count'] }}</p></div>
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3"><p class="text-xs text-slate-500">Taranan rakip ürün</p><p class="mt-1 text-lg font-semibold text-amber-700">{{ $trendDashboard['source_product_count'] }}</p></div>
                </div>

                <div data-testid="booster-trend-mobile-ledger" class="space-y-3 md:hidden">
                    @forelse($trendDashboard['rows'] as $row)
                        <article class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-slate-900">{{ $row->keyword }}</p>
                                    <p class="mt-1 truncate text-xs text-slate-500">{{ $row->category_name ?: 'Kategori belirtilmedi' }}</p>
                                </div>
                                <span class="shrink-0 rounded-[6px] border px-2 py-1 text-xs {{ $row->trend_direction === 'rising' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : ($row->trend_direction === 'falling' ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-slate-200 bg-white text-slate-600') }}">
                                    {{ ['new' => 'Yeni', 'rising' => 'Yükseliyor', 'falling' => 'Düşüyor', 'stable' => 'Sabit'][$row->trend_direction] ?? 'Sabit' }}
                                </span>
                            </div>
                            <dl class="mt-4 divide-y divide-slate-200 border-t border-slate-200 text-xs">
                                <div class="flex items-center justify-between gap-3 py-2">
                                    <dt class="text-slate-500">Trend puanı</dt>
                                    <dd class="text-right font-medium text-slate-900">{{ $row->signal_score }}/100</dd>
                                </div>
                                <div class="flex items-center justify-between gap-3 py-2">
                                    <dt class="text-slate-500">Rakip kapsamı</dt>
                                    <dd class="text-right font-medium text-slate-900">{{ $row->product_count }} ürün / {{ $row->store_count }} mağaza</dd>
                                </div>
                                <div class="flex items-center justify-between gap-3 py-2">
                                    <dt class="text-slate-500">Sosyal kanıt</dt>
                                    <dd class="text-right font-medium text-slate-700">{{ number_format($row->total_favorite_count, 0, ',', '.') }} favori · {{ number_format($row->total_review_count, 0, ',', '.') }} yorum</dd>
                                </div>
                            </dl>
                            <button type="button" wire:click="trackTrendKeyword({{ $row->id }})" wire:loading.attr="disabled" wire:target="trackTrendKeyword({{ $row->id }})"
                                    class="mt-3 inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white disabled:opacity-60">
                                <x-lucide.icon name="target" class="h-4 w-4" /> Sıra takibine al
                            </button>
                        </article>
                    @empty
                        <div class="rounded-[8px] border border-dashed border-slate-300 bg-slate-50/70 p-4 text-sm text-slate-500">Önce Rakip Takibi bölümünde bir mağazayı tarayın, ardından “Rakiplerden keşfet” düğmesini kullanın.</div>
                    @endforelse
                </div>

                <div data-testid="booster-trend-table" class="hidden overflow-x-auto rounded-[8px] border border-slate-200 md:block">
                    <table class="min-w-full table-fixed divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                            <tr>
                                <th class="w-[24%] px-3 py-3 text-left">Kelime</th>
                                <th class="w-[13%] px-3 py-3 text-left">Hareket</th>
                                <th class="w-[11%] px-3 py-3 text-right">Puan</th>
                                <th class="w-[16%] px-3 py-3 text-left">Rakip kapsamı</th>
                                <th class="w-[15%] px-3 py-3 text-left">Sosyal kanıt</th>
                                <th class="w-[10%] px-3 py-3 text-left">Rekabet</th>
                                <th class="w-[11%] px-3 py-3 text-right">Aksiyon</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse($trendDashboard['rows'] as $row)
                                <tr>
                                    <td class="px-3 py-3">
                                        <p class="truncate font-medium text-slate-900">{{ $row->keyword }}</p>
                                        <p class="truncate text-xs text-slate-500">{{ $row->category_name ?: 'Kategori belirtilmedi' }}</p>
                                    </td>
                                    <td class="px-3 py-3 text-xs {{ $row->trend_direction === 'rising' ? 'text-emerald-700' : ($row->trend_direction === 'falling' ? 'text-rose-700' : 'text-slate-600') }}">{{ ['new' => 'Yeni', 'rising' => 'Yükseliyor', 'falling' => 'Düşüyor', 'stable' => 'Sabit'][$row->trend_direction] ?? 'Sabit' }}</td>
                                    <td class="px-3 py-3 text-right font-semibold text-slate-900">{{ $row->signal_score }}/100</td>
                                    <td class="px-3 py-3 text-slate-600">{{ $row->product_count }} ürün · {{ $row->store_count }} mağaza</td>
                                    <td class="px-3 py-3 text-xs text-slate-600">{{ number_format($row->total_favorite_count, 0, ',', '.') }} favori<br>{{ number_format($row->total_review_count, 0, ',', '.') }} yorum</td>
                                    <td class="px-3 py-3">
                                        <span class="rounded-[6px] border px-2 py-1 text-xs {{ $row->competition_level === 'low' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : ($row->competition_level === 'high' ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-amber-200 bg-amber-50 text-amber-700') }}">
                                            {{ ['low' => 'Düşük', 'medium' => 'Orta', 'high' => 'Yüksek'][$row->competition_level] ?? 'Bilinmiyor' }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-3 text-right">
                                        <button type="button" wire:click="trackTrendKeyword({{ $row->id }})" title="Sıra takibine al"
                                                class="inline-flex h-9 w-9 items-center justify-center rounded-[6px] bg-slate-900 text-white transition hover:bg-slate-800 disabled:opacity-60">
                                            <x-lucide.icon name="target" class="h-4 w-4" />
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-3 py-8 text-center text-sm text-slate-500">Rakip mağaza verisi yok. Rakip Takibi bölümünde mağazayı taradıktan sonra kelime keşfini başlatın.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    @elseif($activeModule === 'price')
        <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
            <div class="grid grid-cols-2 gap-3 xl:grid-cols-4">
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3"><p class="text-xs text-slate-500">Takipteki ürün</p><p class="mt-1 text-lg font-semibold text-slate-900">{{ $priceDashboard['total'] }}</p></div>
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3"><p class="text-xs text-slate-500">Fiyatı düşen</p><p class="mt-1 text-lg font-semibold text-emerald-700">{{ $priceDashboard['dropped'] }}</p></div>
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3"><p class="text-xs text-slate-500">En büyük düşüş</p><p class="mt-1 text-lg font-semibold text-slate-900">%{{ number_format(abs((float) $priceDashboard['biggest_drop']), 1, ',', '.') }}</p></div>
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3"><p class="text-xs text-slate-500">Son kontrol</p><p class="mt-1 text-lg font-semibold text-slate-900">{{ $priceDashboard['last_checked_at']?->format('d.m H:i') ?? '-' }}</p></div>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-3 xl:grid-cols-2">
                @forelse($priceDashboard['products'] as $product)
                    <article class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-900">{{ $product->title ?: 'Trendyol ürünü' }}</p>
                                <p class="mt-1 text-xs text-slate-500">Son kontrol: {{ $product->latestSnapshot?->checked_at?->format('d.m.Y H:i') ?? '-' }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-base font-semibold text-slate-900">{{ $this->formatMoney($product->sale_price) }}</p>
                                <p class="text-xs {{ (float) ($product->latestSnapshot?->price_delta ?? 0) < 0 ? 'text-emerald-700' : 'text-slate-500' }}">{{ $this->priceDeltaLabel($product->latestSnapshot?->price_delta ?? 0) }}</p>
                            </div>
                        </div>
                        <div class="mt-4 flex h-24 items-end gap-1 rounded-[6px] border border-slate-200 bg-white p-3">
                            @php
                                $snapshots = $product->snapshots->sortBy('checked_at')->values();
                                $maxPrice = max(1, (float) $snapshots->max('sale_price'));
                            @endphp
                            @forelse($snapshots as $snapshot)
                                @php $height = max(8, min(100, ((float) $snapshot->sale_price / $maxPrice) * 100)); @endphp
                                <div class="min-w-2 flex-1 rounded-t bg-slate-900/80" style="height: {{ $height }}%"></div>
                            @empty
                                <p class="self-center text-xs text-slate-500">Henüz fiyat snapshot yok.</p>
                            @endforelse
                        </div>
                    </article>
                @empty
                    <div class="rounded-[8px] border border-dashed border-slate-300 bg-slate-50/70 p-4 text-sm text-slate-500 xl:col-span-2">Fiyat takibinde ürün yok.</div>
                @endforelse
            </div>
        </section>
    @elseif($activeModule === 'history')
        <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 p-4 lg:p-6">
                <div class="grid grid-cols-1 gap-3 lg:grid-cols-[minmax(0,1fr)_220px]">
                    <input type="search" wire:model.live.debounce.300ms="historySearch" placeholder="Ürün, araç, kelime veya barkod arayın"
                           class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none focus:border-slate-400 sm:py-2 sm:text-sm">
                    <select wire:model.live="historyType" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none focus:border-slate-400 sm:py-2 sm:text-sm">
                        <option value="all">Tüm araçlar</option>
                        <option value="product_decision">Ürün kararı</option>
                        <option value="stock_check">Stok sorgulama</option>
                        <option value="keyword_lookup">Kelime arama</option>
                        <option value="competitor_tracking">Rakip takibi</option>
                        <option value="price_tracking">Fiyat takibi</option>
                        <option value="commission_rates">Komisyon oranları</option>
                    </select>
                </div>
            </div>

            <div class="space-y-4 p-4 lg:p-6">
                <div class="grid grid-cols-2 gap-3 xl:grid-cols-4">
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3"><p class="text-xs text-slate-500">Toplam kayıt</p><p class="mt-1 text-lg font-semibold text-slate-900">{{ $activityDashboard['total'] }}</p></div>
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3"><p class="text-xs text-slate-500">Stok sorgusu</p><p class="mt-1 text-lg font-semibold text-emerald-700">{{ $activityDashboard['stock'] }}</p></div>
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3"><p class="text-xs text-slate-500">Kelime sorgusu</p><p class="mt-1 text-lg font-semibold text-sky-700">{{ $activityDashboard['keyword'] }}</p></div>
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3"><p class="text-xs text-slate-500">Son kayıt</p><p class="mt-1 text-lg font-semibold text-slate-900">{{ $activityDashboard['last']?->format('d.m H:i') ?? '-' }}</p></div>
                </div>

                <div data-testid="booster-history-mobile-ledger" class="space-y-3 md:hidden">
                    @forelse($activityDashboard['logs'] as $log)
                        <article class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-xs text-slate-500">{{ $log->recorded_at?->format('d.m.Y H:i') }}</p>
                                    <p class="mt-1 truncate text-sm font-semibold text-slate-900">{{ $log->subject ?: 'Konu belirtilmedi' }}</p>
                                </div>
                                <span class="shrink-0 rounded-[6px] border border-slate-200 bg-white px-2 py-1 text-xs font-medium text-slate-700">{{ $log->title }}</span>
                            </div>
                            <p class="mt-3 text-xs leading-5 text-slate-600">{{ $log->summary ?: 'Özet bulunmuyor.' }}</p>
                            <div class="mt-3 flex items-center justify-between gap-3 border-t border-slate-200 pt-3 text-xs">
                                <span class="text-slate-500">Sonuç</span>
                                <span class="text-right font-semibold text-slate-900">{{ $log->result_label ?: '-' }} {{ $log->result_value !== null ? number_format((float) $log->result_value, 0, ',', '.') : '' }}</span>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-[8px] border border-dashed border-slate-300 bg-slate-50/70 p-4 text-sm text-slate-500">Analiz geçmişi henüz boş.</div>
                    @endforelse
                </div>

                <div data-testid="booster-history-table" class="hidden overflow-x-auto rounded-[8px] border border-slate-200 md:block">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                            <tr>
                                <th class="px-3 py-3 text-left">Tarih</th>
                                <th class="px-3 py-3 text-left">Araç</th>
                                <th class="px-3 py-3 text-left">Konu</th>
                                <th class="px-3 py-3 text-left">Özet</th>
                                <th class="px-3 py-3 text-right">Sonuç</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse($activityDashboard['logs'] as $log)
                                <tr>
                                    <td class="whitespace-nowrap px-3 py-3 text-slate-500">{{ $log->recorded_at?->format('d.m.Y H:i') }}</td>
                                    <td class="px-3 py-3"><span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-1 text-xs font-medium text-slate-700">{{ $log->title }}</span></td>
                                    <td class="max-w-[240px] truncate px-3 py-3 font-medium text-slate-900">{{ $log->subject ?: '-' }}</td>
                                    <td class="max-w-[360px] truncate px-3 py-3 text-slate-600">{{ $log->summary ?: '-' }}</td>
                                    <td class="whitespace-nowrap px-3 py-3 text-right font-medium text-slate-900">{{ $log->result_label ?: '-' }} {{ $log->result_value !== null ? number_format((float) $log->result_value, 0, ',', '.') : '' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-3 py-6 text-center text-sm text-slate-500">Analiz geçmişi henüz boş.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    @elseif($activeModule === 'commissions')
        <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm">

            {{-- Command Bar: başlık + güncelle butonu + KPI strip + arama --}}
            <div class="border-b border-slate-200 px-4 py-3 lg:px-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Hesaplama</p>
                        <h2 class="mt-0.5 text-lg font-semibold text-slate-900">Komisyon Oranları</h2>
                    </div>
                    <div class="flex items-center gap-3">
                        <div wire:loading wire:target="commissionPdfFile" class="flex items-center gap-1.5 text-xs text-slate-500">
                            <x-lucide.icon name="loader-2" class="h-3.5 w-3.5 animate-spin" />
                            <span>PDF İşleniyor...</span>
                        </div>
                        <label class="inline-flex min-h-[36px] shrink-0 cursor-pointer items-center gap-1.5 rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50 sm:self-center">
                            <x-lucide.icon name="upload-cloud" class="h-3.5 w-3.5" />
                            <span>PDF'ten Güncelle</span>
                            <input type="file" wire:model="commissionPdfFile" accept=".pdf,application/pdf" class="hidden">
                        </label>
                    </div>
                </div>
            </div>

            @if($commissionImportStatus)
                <div class="border-b px-4 py-3 {{ $commissionImportStatus['status'] === 'ok' ? 'border-emerald-100 bg-emerald-50 text-emerald-800' : 'border-rose-100 bg-rose-50 text-rose-800' }}">
                    <div class="flex items-start gap-2 text-sm">
                        @if($commissionImportStatus['status'] === 'ok')
                            <x-lucide.icon name="check-circle-2" class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" />
                        @else
                            <x-lucide.icon name="alert-circle" class="mt-0.5 h-4 w-4 shrink-0 text-rose-600" />
                        @endif
                        <div class="flex-1">
                            <p class="font-medium">{{ $commissionImportStatus['message'] }}</p>
                        </div>
                        <button type="button" wire:click="$set('commissionImportStatus', null)" class="shrink-0 opacity-50 transition hover:opacity-100">
                            <x-lucide.icon name="x" class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            @endif

            {{-- KPI + Arama + Filtreler --}}
            <div class="border-b border-slate-100 bg-slate-50/60 px-4 py-2.5 lg:px-6">
                {{-- Üst satır: KPI + arama --}}
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                    <div class="flex items-center gap-1.5 text-xs">
                        <span class="text-slate-500">Kayıt</span>
                        <span class="font-semibold tabular-nums text-slate-900">{{ number_format($commissionDashboard['total']) }}</span>
                    </div>
                    <div class="h-3 w-px bg-slate-200"></div>
                    <div class="flex items-center gap-1.5 text-xs">
                        <span class="text-slate-500">En yüksek</span>
                        <span class="rounded-[4px] border border-rose-200 bg-rose-50 px-1.5 py-0.5 font-mono font-semibold text-rose-700">
                            %{{ number_format((float) $commissionDashboard['highest'], 1, ',', '.') }}
                        </span>
                    </div>
                    <div class="h-3 w-px bg-slate-200"></div>
                    <div class="flex items-center gap-1.5 text-xs">
                        <span class="text-slate-500">Güncelleme</span>
                        <span class="font-medium text-slate-700">{{ $commissionDashboard['last_update'] ?: '-' }}</span>
                    </div>
                    <div class="ml-auto flex-1 sm:max-w-[280px]">
                        <label class="flex items-center gap-2 rounded-[6px] border border-slate-200 bg-white px-2.5 py-1.5 transition-colors focus-within:border-slate-400">
                            <x-lucide.icon name="search" class="h-3.5 w-3.5 shrink-0 text-slate-400" />
                            <input
                                type="search"
                                wire:model.live.debounce.250ms="commissionSearch"
                                placeholder="Kategori, alt kategori, ürün grubu..."
                                class="min-w-0 flex-1 border-0 bg-transparent text-xs text-slate-900 outline-none placeholder:text-slate-400"
                            >
                            @if($commissionSearch)
                                <button wire:click="$set('commissionSearch','')" class="shrink-0 text-slate-400 hover:text-slate-700">
                                    <x-lucide.icon name="x" class="h-3 w-3" />
                                </button>
                            @endif
                        </label>
                    </div>
                </div>

                {{-- Alt satır: Filtreler --}}
                <div class="mt-2.5 flex flex-wrap items-center gap-2">

                    {{-- Kategori dropdown --}}
                    <div class="flex items-center gap-1.5">
                        <span class="text-[11px] font-medium text-slate-500">Kategori</span>
                        <div class="relative">
                            <select
                                wire:model.live="commissionCategoryFilter"
                                class="appearance-none rounded-[6px] border py-1 pl-2.5 pr-7 text-xs outline-none transition-colors focus:border-slate-400
                                       {{ $commissionCategoryFilter ? 'border-indigo-300 bg-indigo-50 text-indigo-700 font-medium' : 'border-slate-200 bg-white text-slate-600' }}"
                            >
                                <option value="">Tüm kategoriler</option>
                                @foreach($commissionDashboard['categories'] as $cat)
                                    <option value="{{ $cat }}">{{ $cat }}</option>
                                @endforeach
                            </select>
                            <x-lucide.icon name="chevron-down" class="pointer-events-none absolute right-1.5 top-1/2 h-3 w-3 -translate-y-1/2 text-slate-400" />
                        </div>
                    </div>

                    <div class="h-3 w-px bg-slate-200"></div>

                    {{-- Komisyon aralığı segmented --}}
                    <div class="flex items-center gap-1.5">
                        <span class="text-[11px] font-medium text-slate-500">Komisyon</span>
                        <div class="flex overflow-hidden rounded-[6px] border border-slate-200 bg-white text-xs">
                            <button wire:click="$set('commissionRateRange','')"
                                class="px-2.5 py-1 transition-colors {{ $commissionRateRange==='' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-50' }}">Tümü</button>
                            <button wire:click="$set('commissionRateRange','high')"
                                class="border-l border-slate-200 px-2.5 py-1 transition-colors {{ $commissionRateRange==='high' ? 'bg-rose-600 text-white' : 'text-slate-600 hover:bg-rose-50' }}">≥%25</button>
                            <button wire:click="$set('commissionRateRange','mid')"
                                class="border-l border-slate-200 px-2.5 py-1 transition-colors {{ $commissionRateRange==='mid' ? 'bg-amber-500 text-white' : 'text-slate-600 hover:bg-amber-50' }}">%15–24</button>
                            <button wire:click="$set('commissionRateRange','low')"
                                class="border-l border-slate-200 px-2.5 py-1 transition-colors {{ $commissionRateRange==='low' ? 'bg-emerald-600 text-white' : 'text-slate-600 hover:bg-emerald-50' }}">&lt;%15</button>
                        </div>
                    </div>

                    <div class="h-3 w-px bg-slate-200"></div>

                    {{-- Vade segmented --}}
                    <div class="flex items-center gap-1.5">
                        <span class="text-[11px] font-medium text-slate-500">Vade</span>
                        <div class="flex overflow-hidden rounded-[6px] border border-slate-200 bg-white text-xs">
                            <button wire:click="$set('commissionMaturityFilter','')"
                                class="px-2.5 py-1 transition-colors {{ $commissionMaturityFilter==='' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-50' }}">Tümü</button>
                            <button wire:click="$set('commissionMaturityFilter','14')"
                                class="border-l border-slate-200 px-2.5 py-1 transition-colors {{ $commissionMaturityFilter==='14' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-50' }}">14 gün</button>
                            <button wire:click="$set('commissionMaturityFilter','21')"
                                class="border-l border-slate-200 px-2.5 py-1 transition-colors {{ $commissionMaturityFilter==='21' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-50' }}">21 gün</button>
                        </div>
                    </div>

                    {{-- Aktif filtre badge + Temizle --}}
                    @php
                        $activeFilterCount = (int)($commissionSearch !== '')
                            + (int)($commissionCategoryFilter !== '')
                            + (int)($commissionRateRange !== '')
                            + (int)($commissionMaturityFilter !== '');
                    @endphp
                    @if($activeFilterCount > 0)
                        <div class="ml-auto flex items-center gap-2">
                            <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-[11px] font-semibold text-indigo-700">
                                {{ $activeFilterCount }} filtre aktif
                            </span>
                            <button wire:click="resetCommissionFilters"
                                class="flex items-center gap-1 rounded-[5px] border border-slate-200 bg-white px-2 py-1 text-[11px] text-slate-500 hover:bg-slate-50 hover:text-slate-700">
                                <x-lucide.icon name="x" class="h-3 w-3" />
                                Temizle
                            </button>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Mobil Kart Görünümü --}}
            <div data-testid="booster-commission-mobile-ledger" class="divide-y divide-slate-100 md:hidden">
                @forelse($commissionDashboard['rows'] as $row)
                    @php $rate = (float)$row->commission_rate; @endphp
                    <div class="px-4 py-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-900">{{ $row->category_name }}</p>
                                <p class="mt-0.5 text-[11px] text-slate-500">{{ $row->sub_category_name ?: '—' }}</p>
                                <p class="mt-1 text-xs leading-5 text-slate-600">{{ $row->product_group ?: '—' }}</p>
                            </div>
                            <div class="shrink-0 text-right">
                                <span class="rounded-[4px] px-1.5 py-0.5 font-mono text-xs font-semibold
                                    {{ $rate >= 25 ? 'border border-rose-200 bg-rose-50 text-rose-700' : ($rate >= 15 ? 'border border-amber-200 bg-amber-50 text-amber-700' : 'border border-emerald-200 bg-emerald-50 text-emerald-700') }}">
                                    %{{ number_format($rate, 1, ',', '.') }}
                                </span>
                                <p class="mt-1 text-[10px] text-slate-400">{{ $row->maturity_days }}g</p>
                            </div>
                        </div>
                        <dl class="mt-2 grid grid-cols-5 gap-2 text-[11px]">
                            <div><dt class="text-slate-400">Sv.5</dt><dd class="font-medium text-slate-700">{{ $row->level_5_rate !== null ? '%'.number_format((float)$row->level_5_rate,1,',','.') : '—' }}</dd></div>
                            <div><dt class="text-slate-400">Sv.4</dt><dd class="font-medium text-slate-700">{{ $row->level_4_rate !== null ? '%'.number_format((float)$row->level_4_rate,1,',','.') : '—' }}</dd></div>
                            <div><dt class="text-slate-400">Sv.3</dt><dd class="font-medium text-slate-700">{{ $row->level_3_rate !== null ? '%'.number_format((float)$row->level_3_rate,1,',','.') : '—' }}</dd></div>
                            <div><dt class="text-slate-400">Sv.2</dt><dd class="font-medium text-slate-700">{{ $row->level_2_rate !== null ? '%'.number_format((float)$row->level_2_rate,1,',','.') : '—' }}</dd></div>
                            <div><dt class="text-slate-400">Sv.1</dt><dd class="font-medium text-slate-700">{{ $row->level_1_rate !== null ? '%'.number_format((float)$row->level_1_rate,1,',','.') : '—' }}</dd></div>
                        </dl>
                    </div>
                @empty
                    <div class="px-4 py-10 text-center text-sm text-slate-400">Komisyon verisi yok.</div>
                @endforelse
            </div>

            {{-- Masaüstü Tablo --}}
            <div data-testid="booster-commission-table" class="hidden md:block">
                @if($commissionDashboard['rows']->isEmpty())
                    <div class="px-6 py-14 text-center">
                        <p class="text-sm font-medium text-slate-600">Sonuç bulunamadı</p>
                        <p class="mt-1 text-xs text-slate-400">{{ $commissionSearch ? '"'.$commissionSearch.'" için kayıt yok.' : 'Oranları Güncelle ile veri yükleyin.' }}</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse table-fixed" style="font-size:12px; min-width: 980px;">
                            <thead>
                                <tr class="border-b border-slate-200 bg-slate-50">
                                    <th class="sticky left-0 z-10 bg-slate-50 py-2.5 pl-4 pr-3 text-left font-semibold text-slate-600" style="width: 140px;">
                                        <button type="button" wire:click="sortCommissions('category_name')" class="flex items-center gap-1 hover:text-slate-900">
                                            Kategori
                                            @if($commissionSort==='category_name') <x-lucide.icon name="{{ $commissionDirection==='asc'?'chevron-up':'chevron-down' }}" class="h-3 w-3" /> @else <x-lucide.icon name="chevrons-up-down" class="h-3 w-3 text-slate-300" /> @endif
                                        </button>
                                    </th>
                                    <th class="py-2.5 pl-2 pr-3 text-left font-semibold text-slate-600" style="width: 160px;">Alt Kategori</th>
                                    <th class="py-2.5 pl-2 pr-3 text-left font-semibold text-slate-600" style="width: auto;">Ürün Grubu</th>
                                    <th class="py-2.5 pl-2 pr-3 text-center font-semibold text-slate-600" style="width: 68px;">
                                        <button type="button" wire:click="sortCommissions('maturity_days')" class="mx-auto flex items-center gap-1 hover:text-slate-900">
                                            Vade
                                            @if($commissionSort==='maturity_days') <x-lucide.icon name="{{ $commissionDirection==='asc'?'chevron-up':'chevron-down' }}" class="h-3 w-3" /> @else <x-lucide.icon name="chevrons-up-down" class="h-3 w-3 text-slate-300" /> @endif
                                        </button>
                                    </th>
                                    <th class="py-2.5 pl-2 pr-3 text-right font-semibold text-slate-600" style="width:86px">
                                        <button type="button" wire:click="sortCommissions('commission_rate')" class="ml-auto flex items-center gap-1 hover:text-slate-900">
                                            Komisyon
                                            @if($commissionSort==='commission_rate') <x-lucide.icon name="{{ $commissionDirection==='asc'?'chevron-up':'chevron-down' }}" class="h-3 w-3" /> @else <x-lucide.icon name="chevrons-up-down" class="h-3 w-3 text-slate-300" /> @endif
                                        </button>
                                    </th>
                                    <th class="py-2.5 pl-2 pr-3 text-right font-semibold text-slate-500" style="width:64px">Sv.5</th>
                                    <th class="py-2.5 pl-2 pr-3 text-right font-semibold text-slate-500" style="width:64px">Sv.4</th>
                                    <th class="py-2.5 pl-2 pr-3 text-right font-semibold text-slate-500" style="width:64px">Sv.3</th>
                                    <th class="py-2.5 pl-2 pr-3 text-right font-semibold text-slate-500" style="width:64px">Sv.2</th>
                                    <th class="py-2.5 pl-2 pr-4 text-right font-semibold text-slate-500" style="width:64px">Sv.1</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $prevCat = null; $ri = 0; @endphp
                                @foreach($commissionDashboard['rows'] as $row)
                                    @php
                                        $catNew = $row->category_name !== $prevCat;
                                        $prevCat = $row->category_name;
                                        $rate = (float)$row->commission_rate;
                                        $isHigh = $rate >= 25;
                                        $isMid  = $rate >= 15 && !$isHigh;
                                        $ri++;
                                    @endphp
                                    @if($catNew && !$loop->first)
                                        <tr><td colspan="8" class="h-px bg-slate-100 p-0"></td></tr>
                                    @endif
                                    <tr class="border-b border-slate-50 hover:bg-slate-50/60 {{ $ri%2===0?'bg-white':'bg-slate-50/20' }}">
                                        <td class="sticky left-0 z-10 bg-inherit py-2 pl-4 pr-3 font-semibold text-slate-900" title="{{ $row->category_name }}">
                                            <span class="block truncate">{{ $catNew ? $row->category_name : '' }}</span>
                                        </td>
                                        <td class="py-2 pl-2 pr-3 text-slate-600" title="{{ $row->sub_category_name }}">
                                            <span class="block truncate">{{ $row->sub_category_name ?: '—' }}</span>
                                        </td>
                                        <td class="py-2 pl-2 pr-3 text-slate-600">
                                            <span class="block overflow-hidden text-ellipsis whitespace-nowrap" title="{{ $row->product_group }}">{{ $row->product_group ?: '—' }}</span>
                                        </td>
                                        <td class="py-2 pl-2 pr-3 text-center tabular-nums text-slate-500">{{ $row->maturity_days }}g</td>
                                        <td class="py-2 pl-2 pr-3 text-right tabular-nums">
                                            <span class="inline-block rounded-[4px] px-1.5 py-0.5 font-mono font-semibold
                                                {{ $isHigh?'border border-rose-200 bg-rose-50 text-rose-700':($isMid?'border border-amber-200 bg-amber-50 text-amber-700':'border border-emerald-200 bg-emerald-50 text-emerald-700') }}">
                                                %{{ number_format($rate, 1, ',', '.') }}
                                            </span>
                                        </td>
                                        <td class="py-2 pl-2 pr-3 text-right tabular-nums text-slate-500">{{ $row->level_5_rate!==null?'%'.number_format((float)$row->level_5_rate,1,',','.'):'—' }}</td>
                                        <td class="py-2 pl-2 pr-3 text-right tabular-nums text-slate-500">{{ $row->level_4_rate!==null?'%'.number_format((float)$row->level_4_rate,1,',','.'):'—' }}</td>
                                        <td class="py-2 pl-2 pr-3 text-right tabular-nums text-slate-500">{{ $row->level_3_rate!==null?'%'.number_format((float)$row->level_3_rate,1,',','.'):'—' }}</td>
                                        <td class="py-2 pl-2 pr-3 text-right tabular-nums text-slate-500">{{ $row->level_2_rate!==null?'%'.number_format((float)$row->level_2_rate,1,',','.'):'—' }}</td>
                                        <td class="py-2 pl-2 pr-4 text-right tabular-nums text-slate-500">{{ $row->level_1_rate!==null?'%'.number_format((float)$row->level_1_rate,1,',','.'):'—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="border-t border-slate-100 bg-slate-50/60 px-4 py-2 text-[11px] text-slate-400 lg:px-6">
                        {{ $commissionDashboard['rows']->count() }} kayıt
                        @if($commissionSearch) · <span class="font-medium text-slate-600">"{{ $commissionSearch }}"</span> filtresi aktif @endif
                        &nbsp;·&nbsp;
                        <span class="inline-block h-2 w-2 rounded-full bg-rose-400"></span> ≥%25 yüksek
                        <span class="ml-1 inline-block h-2 w-2 rounded-full bg-amber-400"></span> ≥%15 orta
                        <span class="ml-1 inline-block h-2 w-2 rounded-full bg-emerald-400"></span> düşük
                    </div>
                @endif
            </div>
        </section>    @elseif($activeModule === 'shipping_rates')
        @include('livewire.partials.trendyol-booster-shipping-rates')
        
    @elseif($activeModule === 'reviews')
        @include('livewire.partials.trendyol-booster-reviews')

    @elseif($activeModule === 'notifications')
        <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 p-4 lg:p-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase text-slate-500">Booster bildirimleri</p>
                        <h2 class="mt-1 text-lg font-semibold text-slate-900">Canlı ve e-posta uyarı tercihleri</h2>
                        <p class="mt-1 max-w-2xl text-sm text-slate-500">
                            Fiyat, stok, rakip mağaza ve anahtar kelime sinyalleri aynı tercih kaydından yönetilir.
                        </p>
                    </div>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        <div class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                            <p class="text-xs text-slate-500">Aktif grup</p>
                            <p class="mt-1 text-lg font-semibold text-slate-900">{{ $boosterNotificationPreferences['active_groups'] }}/{{ $boosterNotificationPreferences['total_groups'] }}</p>
                        </div>
                        <div class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                            <p class="text-xs text-slate-500">Mail bekleyen</p>
                            <p class="mt-1 text-lg font-semibold text-slate-900">{{ $boosterNotificationPreferences['pending_email_count'] }}</p>
                        </div>
                        <div class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/70 p-3 col-span-2 sm:col-span-1">
                            <p class="text-xs text-slate-500">E-posta özeti</p>
                            <p class="mt-1 text-sm font-semibold {{ $boosterNotificationPreferences['email_digest_enabled'] ? 'text-emerald-700' : 'text-slate-900' }}">
                                {{ $boosterNotificationPreferences['email_digest_enabled'] ? 'Aktif' : 'Kapalı' }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-4 p-4 lg:p-6">
                @unless($boosterNotificationPreferences['available'])
                    <div class="rounded-[8px] border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        Bildirim merkezi tabloları hazır değil. Migration sonrası tercihler açılacak.
                    </div>
                @endunless

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach($boosterNotificationPreferences['groups'] as $group)
                        <div class="flex min-w-0 flex-col rounded-[8px] border border-slate-200 bg-slate-50/60 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-slate-900">{{ $group['label'] }}</p>
                                    <p class="mt-2 text-xs leading-5 text-slate-500">{{ $group['description'] }}</p>
                                </div>
                                <span class="shrink-0 rounded-[6px] border px-2 py-1 text-[11px] font-medium {{ $group['active'] ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-white text-slate-500' }}">
                                    {{ $group['active'] ? 'Açık' : 'Kapalı' }}
                                </span>
                            </div>

                            <div class="mt-4 flex items-center justify-between gap-3">
                                <span class="text-xs text-slate-500">{{ count($group['types']) }} tip</span>
                                <button
                                    type="button"
                                    wire:click="toggleBoosterNotificationGroup('{{ $group['key'] }}')"
                                    @disabled(!$boosterNotificationPreferences['available'])
                                    class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] border px-4 py-3 text-base font-medium transition disabled:cursor-not-allowed disabled:opacity-60 sm:py-2 sm:text-sm {{ $group['active'] ? 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50' : 'border-slate-900 bg-slate-900 text-white hover:bg-slate-800' }}"
                                >
                                    {{ $group['active'] ? 'Kapat' : 'Aç' }}
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="rounded-[8px] border border-slate-200 bg-white p-4">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-900">E-posta digest durumu</p>
                            <p class="mt-1 text-sm text-slate-500">
                                Saatlik digest limiti: {{ $boosterNotificationPreferences['email_digest_max_notifications'] }} bildirim.
                            </p>
                        </div>
                        <div class="rounded-[6px] border border-slate-200 bg-slate-50 px-3 py-2 font-mono text-xs text-slate-600">
                            MARKETPLACE_TRENDYOL_BOOSTER_EMAIL_DIGEST_ENABLED={{ $boosterNotificationPreferences['email_digest_enabled'] ? 'true' : 'false' }}
                        </div>
                    </div>
                </div>
            </div>
        </section>
    @endif
</div>

@script
<script>
    window.startZolmBoosterCompanionPing = window.startZolmBoosterCompanionPing || (() => {
        const ping = () => {
            window.postMessage({ source: 'zolm-booster-page', type: 'PING' }, window.location.origin);
        };

        ping();

        if (window.__zolmBoosterCompanionPingTimer) {
            window.clearInterval(window.__zolmBoosterCompanionPingTimer);
        }

        let attempts = 0;
        window.__zolmBoosterCompanionPingTimer = window.setInterval(() => {
            attempts += 1;
            ping();

            if (attempts >= 20) {
                window.clearInterval(window.__zolmBoosterCompanionPingTimer);
                window.__zolmBoosterCompanionPingTimer = null;
            }
        }, 1000);
    });

    window.startZolmBoosterCompanionPing();

    if (!window.__zolmBoosterCompanionFocusPingBound) {
        window.__zolmBoosterCompanionFocusPingBound = true;
        window.__zolmBoosterCompanionReady = false;
        window.addEventListener('message', (event) => {
            const data = event.data || {};
            if (event.origin !== window.location.origin || data.source !== 'zolm-booster-extension') {
                return;
            }

            if (data.type === 'READY') {
                window.__zolmBoosterCompanionReady = true;
                window.__zolmBoosterCompanionVersion = data.version || '';
            }

            if (data.type === 'BRIDGE_ERROR') {
                window.__zolmBoosterCompanionReady = false;
            }
        });
        window.addEventListener('focus', window.startZolmBoosterCompanionPing);
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                window.startZolmBoosterCompanionPing();
            }
        });
    }

    $wire.on('booster:store-scan-bridge', (event) => {
        const payload = event[0] || event;
        const requestId = 'store_' + Date.now();
        if (window.__zolmBoosterCompanionReady !== true && payload.optional) {
            return;
        }

        const timer = window.setTimeout(() => {
            window.removeEventListener('message', messageHandler);
            if (payload.optional) {
                return;
            }
            $wire.storeScanBridgeCompleted({ ok: false, message: 'Chrome Companion mağaza taramasına yanıt vermedi. Sunucu tarama sonucu kullanıldıysa kayıtlar korunur.' });
        }, 120000);

        const messageHandler = (e) => {
            if (e.origin !== window.location.origin || e.data?.source !== 'zolm-booster-extension') {
                return;
            }

            if (e.data?.type === 'BRIDGE_ERROR') {
                window.clearTimeout(timer);
                window.removeEventListener('message', messageHandler);
                if (payload.optional) {
                    return;
                }
                $wire.storeScanBridgeCompleted({ ok: false, message: e.data.message || 'Chrome Companion köprüsü yanıt vermedi.' });
                return;
            }

            if (e.data?.type === 'STORE_SCAN_RESULT' && e.data?.request_id === requestId) {
                window.clearTimeout(timer);
                window.removeEventListener('message', messageHandler);
                const response = e.data.response || { ok: false, message: 'Geçersiz yanıt.' };
                if (payload.optional && (response.ok === false || !Array.isArray(response.items) || response.items.length === 0)) {
                    return;
                }
                $wire.storeScanBridgeCompleted(response);
            }
        };

        window.addEventListener('message', messageHandler);

        window.postMessage({
            source: 'zolm-booster-page',
            type: 'STORE_SCAN_QUERY',
            request_id: requestId,
            source_url: payload.url,
        }, window.location.origin);
    });
</script>
@endscript
