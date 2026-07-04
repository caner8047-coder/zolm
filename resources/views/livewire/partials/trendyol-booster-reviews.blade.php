@php
    $reviewStats = $this->reviewStats;
    $reviewGroups = $this->reviewGroups;
    $reviewSources = $this->reviewSources;
@endphp

<section
    x-data="{
        extensionReady: Boolean(window.__zolmBoosterCompanionReady),
        extensionVersion: window.__zolmBoosterCompanionVersion || '',
        minimumVersion: '0.12.0',
        versionAtLeast(version, minimum) {
            const normalize = (value) => String(value || '').split('.').map((part) => Number.parseInt(part, 10) || 0);
            const current = normalize(version);
            const required = normalize(minimum);

            for (let index = 0; index < Math.max(current.length, required.length); index++) {
                if ((current[index] || 0) > (required[index] || 0)) return true;
                if ((current[index] || 0) < (required[index] || 0)) return false;
            }

            return true;
        },
        startScan(wire, type) {
            if (!this.extensionReady) {
                wire.reviewScanBridgeError('Chrome Companion bağlı değil. Eklentiyi yeniden yükleyip ZOLM sayfasını yenileyin.');
                return;
            }

            if (!this.versionAtLeast(this.extensionVersion, this.minimumVersion)) {
                wire.reviewScanBridgeError(`Yorum taraması için Companion v${this.minimumVersion} veya üzeri gerekli. Bağlı sürüm: v${this.extensionVersion || 'bilinmiyor'}.`);
                return;
            }

            wire.startReviewScan(type);
        },
        handleCompanionStatus(event) {
            const data = event.data || {};
            if (event.origin !== window.location.origin || data.source !== 'zolm-booster-extension') return;

            if (data.type === 'READY') {
                this.extensionReady = true;
                this.extensionVersion = data.version || '';
            } else if (data.type === 'BRIDGE_ERROR') {
                this.extensionReady = false;
            }
        },
    }"
    x-on:message.window="handleCompanionStatus($event)"
    class="rounded-[10px] border border-slate-200 bg-white shadow-sm"
>
    <div class="border-b border-slate-200 p-4 lg:p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase text-slate-500">Pazar araçları</p>
                <h2 class="mt-1 text-lg font-semibold text-slate-900">Trendyol Yorumlar</h2>
                <p class="mt-1 max-w-2xl text-sm text-slate-500">
                    Mağazanızdaki Trendyol yorumlarını toplayın, filtreleyin ve WooCommerce ürün sayfalarında modern bir widget ile gösterin.
                </p>
            </div>
            <div class="flex w-full flex-col gap-2 sm:w-auto sm:items-end">
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <span x-show="extensionReady && versionAtLeast(extensionVersion, minimumVersion)"
                          class="inline-flex items-center gap-1.5 rounded-[6px] border border-emerald-200 bg-emerald-50 px-2 py-1 font-medium text-emerald-700">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                        Companion v<span x-text="extensionVersion"></span> hazır
                    </span>
                    <span x-show="extensionReady && !versionAtLeast(extensionVersion, minimumVersion)"
                          class="inline-flex items-center gap-1.5 rounded-[6px] border border-amber-200 bg-amber-50 px-2 py-1 font-medium text-amber-700">
                        Companion v<span x-text="extensionVersion || 'bilinmiyor'"></span> eski
                    </span>
                    <span x-show="!extensionReady"
                          class="inline-flex items-center gap-1.5 rounded-[6px] border border-red-200 bg-red-50 px-2 py-1 font-medium text-red-700">
                        Companion bağlı değil
                    </span>
                </div>
                <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                <button type="button"
                        x-on:click="startScan($wire, 'delta')"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-[8px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:opacity-50 sm:w-auto sm:py-2"
                        @if($activeReviewSyncRunId || !$reviewSourceId) disabled @endif>
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Δ Delta Tara
                </button>
                <button type="button"
                        x-on:click="startScan($wire, 'full')"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-[8px] bg-slate-900 px-4 py-3 text-sm font-medium text-white shadow-sm transition hover:bg-slate-800 disabled:opacity-50 sm:w-auto sm:py-2"
                        @if($activeReviewSyncRunId || !$reviewSourceId) disabled @endif>
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    Tümünü Tara
                </button>
                </div>
            </div>
        </div>
    </div>

    <div class="border-b border-slate-200 bg-slate-50/60 p-4 lg:p-6">
        <div class="flex flex-col gap-4">
            <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">Taranacak Trendyol mağazası</h3>
                    <p class="text-sm text-slate-500">Mağaza kimliği doğrulanmadan ürün ve yorum taraması başlamaz.</p>
                </div>
                @if(count($reviewSources) > 0)
                    <select wire:change="selectReviewSource($event.target.value)"
                            class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-700 sm:w-auto sm:text-sm">
                        <option value="">Yeni mağaza tanımla</option>
                        @foreach($reviewSources as $source)
                            <option value="{{ $source['id'] }}" @selected($reviewSourceId === $source['id'])>
                                {{ $source['store_name'] }} · {{ $source['merchant_id'] }}
                            </option>
                        @endforeach
                    </select>
                @endif
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-12">
                <label class="min-w-0 xl:col-span-3">
                    <span class="mb-1 block text-xs font-medium text-slate-600">Mağaza adı</span>
                    <input wire:model.defer="reviewSourceName" type="text" placeholder="Zem Home"
                           class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 sm:text-sm">
                    @error('reviewSourceName') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="min-w-0 xl:col-span-2">
                    <span class="mb-1 block text-xs font-medium text-slate-600">Merchant ID</span>
                    <input wire:model.defer="reviewSourceMerchantId" inputmode="numeric" type="text" placeholder="121057"
                           class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 sm:text-sm">
                    @error('reviewSourceMerchantId') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="min-w-0 sm:col-span-2 xl:col-span-5">
                    <span class="mb-1 block text-xs font-medium text-slate-600">Trendyol mağaza bağlantısı</span>
                    <input wire:model.defer="reviewSourceUrl" type="url" placeholder="https://www.trendyol.com/magaza/zem-home-m-121057"
                           class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 sm:text-sm">
                    @error('reviewSourceUrl') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
                <div class="flex items-end sm:col-span-2 xl:col-span-2">
                    <button type="button" wire:click="verifyReviewSource" wire:loading.attr="disabled" wire:target="verifyReviewSource"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-[6px] border border-slate-300 bg-white px-4 py-3 text-sm font-medium text-slate-800 shadow-sm hover:bg-slate-50 disabled:opacity-50 sm:py-2">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Mağazayı doğrula
                    </button>
                </div>
            </div>

            @if(!empty($reviewSourcePreview))
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div class="rounded-[8px] border border-emerald-200 bg-white p-3">
                        <p class="text-xs text-slate-500">Doğrulanan mağaza</p>
                        <p class="mt-1 truncate text-sm font-semibold text-slate-900">{{ $reviewSourcePreview['store_name'] ?? $reviewSourceName }}</p>
                    </div>
                    <div class="rounded-[8px] border border-slate-200 bg-white p-3">
                        <p class="text-xs text-slate-500">Merchant ID</p>
                        <p class="mt-1 font-mono text-sm font-semibold text-slate-900">{{ $reviewSourcePreview['store_id'] ?? $reviewSourceMerchantId }}</p>
                    </div>
                    <div class="rounded-[8px] border border-slate-200 bg-white p-3">
                        <p class="text-xs text-slate-500">Taramaya hazır ürün</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ number_format((int) ($reviewSourcePreview['product_count'] ?? 0), 0, ',', '.') }}</p>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Sync Progress Bar --}}
    @if($activeReviewSyncRunId)
        <div wire:poll.3s="reviewScanPoll" class="border-b border-amber-200 bg-amber-50 px-4 py-3 lg:px-6">
            <div class="flex items-center gap-3">
                <svg class="h-5 w-5 animate-spin text-amber-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                <div class="flex-1">
                    <p class="text-sm font-medium text-amber-900">
                        Yorum taraması sürüyor... %{{ $reviewSyncProgress ?? 0 }} tamamlandı
                    </p>
                    <div class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-amber-200">
                        <div class="h-full rounded-full bg-amber-600 transition-all duration-500" style="width: {{ $reviewSyncProgress ?? 0 }}%"></div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Stats Bar --}}
    @if($reviewStats && ($reviewStats['total'] ?? 0) > 0)
        <div class="border-b border-slate-200 px-4 py-4 lg:px-6">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7">
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                    <p class="text-xs text-slate-500">Toplam</p>
                    <p class="mt-1 text-lg font-semibold text-slate-900">{{ $reviewStats['total'] }}</p>
                </div>
                <div class="rounded-[8px] border border-emerald-200 bg-emerald-50/70 p-3">
                    <p class="text-xs text-emerald-600">Onaylı</p>
                    <p class="mt-1 text-lg font-semibold text-emerald-900">{{ $reviewStats['approved'] }}</p>
                </div>
                <div class="rounded-[8px] border border-amber-200 bg-amber-50/70 p-3">
                    <p class="text-xs text-amber-600">Bekleyen</p>
                    <p class="mt-1 text-lg font-semibold text-amber-900">{{ $reviewStats['pending'] }}</p>
                </div>
                <div class="rounded-[8px] border border-red-200 bg-red-50/70 p-3">
                    <p class="text-xs text-red-600">Spam</p>
                    <p class="mt-1 text-lg font-semibold text-red-900">{{ $reviewStats['spam'] }}</p>
                </div>
                <div class="rounded-[8px] border border-blue-200 bg-blue-50/70 p-3">
                    <p class="text-xs text-blue-600">WC'ye gönderildi</p>
                    <p class="mt-1 text-lg font-semibold text-blue-900">{{ $reviewStats['pushed'] }}</p>
                </div>
                <div class="rounded-[8px] border border-violet-200 bg-violet-50/70 p-3 col-span-2">
                    <p class="text-xs text-violet-600">Ortalama puan</p>
                    <div class="mt-1 flex items-center gap-1.5">
                        <span class="text-lg font-semibold text-violet-900">{{ number_format($reviewStats['average_rating'] ?? 0, 1) }}</span>
                        <div class="flex">
                            @for($i = 1; $i <= 5; $i++)
                                <svg class="h-4 w-4 {{ $i <= round($reviewStats['average_rating'] ?? 0) ? 'text-amber-400' : 'text-slate-300' }}" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            @endfor
                        </div>
                    </div>
                </div>
            </div>

            {{-- Rating Distribution --}}
            @php
                $dist = $reviewStats['rating_distribution'] ?? [];
            @endphp
            @if(!empty($dist))
                <div class="mt-4 flex items-center gap-4">
                    <span class="text-xs font-medium text-slate-500">Yıldız dağılımı:</span>
                    @foreach([5,4,3,2,1] as $star)
                        <div class="flex items-center gap-1.5">
                            <span class="text-xs text-slate-600">{{ $star }}★</span>
                            <div class="h-1.5 w-16 overflow-hidden rounded-full bg-slate-200">
                                <div class="h-full rounded-full bg-amber-400" style="width: {{ ($reviewStats['approved'] > 0 ? ($dist[$star] ?? 0) / $reviewStats['approved'] * 100 : 0) }}%"></div>
                            </div>
                            <span class="text-xs text-slate-500">{{ $dist[$star] ?? 0 }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    {{-- Ürün Eşleme Paneli --}}
    @if($reviewStats && ($reviewStats['total'] ?? 0) > 0)
        @php
            $matchGroups = $this->productMatchGroups;
            $matchedCount = collect($matchGroups)->where('match_status', 'matched')->sum('review_count');
            $suggestedCount = collect($matchGroups)->where('match_status', 'suggested')->sum('review_count');
            $unmatchedCount = collect($matchGroups)->where('match_status', 'unmatched')->sum('review_count');
            $pendingMatchCount = collect($matchGroups)->where('match_status', 'pending')->sum('review_count');
            $wcProducts = $this->wcProductList;
        @endphp
        <div x-data="{ matchPanelOpen: false }" class="border-b border-slate-200">
            <button type="button" x-on:click="matchPanelOpen = !matchPanelOpen"
                    class="flex w-full items-center justify-between px-4 py-3 text-left transition hover:bg-slate-50 lg:px-6">
                <div class="flex items-center gap-3">
                    <svg class="h-5 w-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                    <span class="text-sm font-semibold text-slate-900">Ürün Eşleme</span>
                    <div class="flex items-center gap-2">
                        @if($matchedCount > 0)
                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">✓ {{ $matchedCount }}</span>
                        @endif
                        @if($suggestedCount > 0)
                            <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">💡 {{ $suggestedCount }}</span>
                        @endif
                        @if($unmatchedCount + $pendingMatchCount > 0)
                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">⚠ {{ $unmatchedCount + $pendingMatchCount }}</span>
                        @endif
                    </div>
                </div>
                <svg class="h-5 w-5 text-slate-400 transition" :class="matchPanelOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>

            <div x-show="matchPanelOpen" x-collapse class="border-t border-slate-100 bg-slate-50/60 px-4 py-4 lg:px-6">
                {{-- Aksiyon Butonları --}}
                <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-slate-500">
                        {{ count($matchGroups) }} Trendyol ürünü · {{ count($wcProducts) }} WooCommerce ürünü algılandı
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <button wire:click="refreshWcProducts" wire:loading.attr="disabled" wire:target="refreshWcProducts"
                                class="inline-flex items-center gap-1.5 rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 shadow-sm hover:bg-slate-50 disabled:opacity-50">
                            <svg class="h-3.5 w-3.5" wire:loading.class="animate-spin" wire:target="refreshWcProducts" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            WC Ürünleri Yenile
                        </button>
                        <button wire:click="autoMatchReviews" wire:loading.attr="disabled" wire:target="autoMatchReviews"
                                wire:confirm="Tüm bekleyen yorumlar WooCommerce ürünleriyle otomatik eşlenecek. Devam?"
                                class="inline-flex items-center gap-1.5 rounded-[6px] border border-indigo-200 bg-indigo-50 px-3 py-2 text-xs font-medium text-indigo-700 shadow-sm hover:bg-indigo-100 disabled:opacity-50">
                            <svg class="h-3.5 w-3.5" wire:loading.class="animate-spin" wire:target="autoMatchReviews" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                            Otomatik Eşle
                        </button>
                        @if($matchedCount > 0)
                            <button wire:click="pushMatchedReviews" wire:loading.attr="disabled" wire:target="pushMatchedReviews"
                                    wire:confirm="Eşleşmiş ve onaylı tüm yorumları WooCommerce'e göndermek istiyor musunuz?"
                                    class="inline-flex items-center gap-1.5 rounded-[6px] bg-slate-900 px-3 py-2 text-xs font-medium text-white shadow-sm hover:bg-slate-800 disabled:opacity-50">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                                Tümünü WC'ye Gönder
                            </button>
                        @endif
                    </div>
                </div>

                {{-- Ürün Eşleme Listesi --}}
                @if(!empty($matchGroups))
                    <div class="space-y-2">
                        @foreach($matchGroups as $mg)
                            <div class="flex flex-col gap-2 rounded-[8px] border {{ $mg['match_status'] === 'matched' ? 'border-emerald-200 bg-white' : ($mg['match_status'] === 'suggested' ? 'border-blue-200 bg-white' : 'border-slate-200 bg-white') }} p-3 sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex items-center gap-3 min-w-0">
                                    @if($mg['product_image_url'])
                                        <img src="{{ $mg['product_image_url'] }}" alt="" class="h-8 w-8 flex-shrink-0 rounded-[6px] border border-slate-200 object-cover">
                                    @endif
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-slate-900">{{ $mg['product_title'] }}</p>
                                        <div class="flex items-center gap-2 text-xs text-slate-500">
                                            <span>{{ $mg['review_count'] }} yorum</span>
                                            <span>·</span>
                                            <span>{{ $mg['approved_count'] }} onaylı</span>
                                            @if($mg['pushed_count'] > 0)
                                                <span>·</span>
                                                <span class="text-blue-600">{{ $mg['pushed_count'] }} gönderildi</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    @if($mg['match_status'] === 'matched')
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">
                                            ✓ WC#{{ $mg['wc_product_id'] }}
                                            @if($mg['match_score'] < 1.0)
                                                <span class="text-emerald-500">({{ number_format($mg['match_score'] * 100) }}%)</span>
                                            @endif
                                        </span>
                                        <button wire:click="pushProductReviewsToWoo('{{ $mg['trendyol_product_id'] }}')"
                                                title="Bu ürünün yorumlarını WC'ye gönder"
                                                class="rounded-[6px] border border-blue-200 bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 hover:bg-blue-100">WC'ye gönder</button>
                                        <button wire:click="unmatchProduct('{{ $mg['trendyol_product_id'] }}')"
                                                title="Eşlemeyi kaldır"
                                                class="rounded-[6px] border border-slate-200 px-2 py-1 text-xs text-slate-500 hover:bg-slate-100">✕</button>
                                    @elseif($mg['match_status'] === 'suggested')
                                        <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700">
                                            💡 WC#{{ $mg['wc_product_id'] }} önerildi ({{ number_format(($mg['match_score'] ?? 0) * 100) }}%)
                                        </span>
                                        <button wire:click="confirmMatchSuggestion('{{ $mg['trendyol_product_id'] }}')"
                                                class="rounded-[6px] bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700 hover:bg-emerald-100">Onayla</button>
                                        <button wire:click="unmatchProduct('{{ $mg['trendyol_product_id'] }}')"
                                                class="rounded-[6px] bg-red-50 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-100">Reddet</button>
                                    @else
                                        {{-- Unmatched / Pending — Manuel Eşleme Dropdown --}}
                                        @if(count($wcProducts) > 0)
                                            <select wire:change="manualMatchProduct('{{ $mg['trendyol_product_id'] }}', $event.target.value)"
                                                    class="max-w-[240px] rounded-[6px] border border-slate-200 bg-white px-2 py-1 text-xs text-slate-700">
                                                <option value="">WC ürünü seç…</option>
                                                @foreach($wcProducts as $wcp)
                                                    <option value="{{ $wcp['id'] }}">{{ Str::limit($wcp['name'], 50) }} (WC#{{ $wcp['id'] }})</option>
                                                @endforeach
                                            </select>
                                        @endif
                                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700">
                                            {{ $mg['match_status'] === 'pending' ? '⏳ Bekliyor' : '⚠ Eşleşmedi' }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Filters --}}
    <div class="border-b border-slate-200 px-4 py-3 lg:px-6">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-wrap items-center gap-2">
                <input type="text" wire:model.live.debounce.300ms="reviewSearch" placeholder="Yorum ara..."
                       class="w-full max-w-[240px] rounded-[8px] border border-slate-200 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-slate-400 focus:ring-1 focus:ring-slate-400 sm:w-auto">
                <select wire:model.live="reviewFilterRating"
                        class="rounded-[8px] border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-slate-400">
                    <option value="all">⭐ Tümü</option>
                    <option value="5">5 yıldız</option>
                    <option value="4">4 yıldız</option>
                    <option value="3">3 yıldız</option>
                    <option value="2">2 yıldız</option>
                    <option value="1">1 yıldız</option>
                    <option value="4plus">4+ yıldız</option>
                </select>
                <select wire:model.live="reviewFilterStatus"
                        class="rounded-[8px] border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-slate-400">
                    <option value="all">Tüm durumlar</option>
                    <option value="pending">Bekleyen</option>
                    <option value="approved">Onaylı</option>
                    <option value="rejected">Reddedilen</option>
                </select>
                <select wire:model.live="reviewSortBy"
                        class="rounded-[8px] border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-slate-400">
                    <option value="reviewed_at_desc">En yeni</option>
                    <option value="reviewed_at_asc">En eski</option>
                    <option value="rating_desc">En yüksek puan</option>
                    <option value="rating_asc">En düşük puan</option>
                    <option value="helpful_desc">En faydalı</option>
                </select>
                @if($reviewFilterHasPhoto !== null)
                    <span class="inline-flex items-center gap-1.5 rounded-[8px] border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                        📷 Fotoğraf: {{ $reviewFilterHasPhoto ? 'Var' : 'Yok' }}
                        <button wire:click="$set('reviewFilterHasPhoto', null)" class="text-slate-400 hover:text-slate-600">✕</button>
                    </span>
                @else
                    <button wire:click="$set('reviewFilterHasPhoto', true)"
                            class="rounded-[8px] border border-slate-200 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50">📷 Fotoğraf var</button>
                @endif
            </div>
        </div>
    </div>

    {{-- Review List --}}
    <div class="divide-y divide-slate-100">
        @forelse($reviewGroups as $group)
            <div class="p-4 lg:p-6">
                {{-- Product Header --}}
                <div class="mb-4 flex items-start gap-3">
                    @if($group['product_image_url'])
                        <img src="{{ $group['product_image_url'] }}" alt="" class="h-12 w-12 rounded-[8px] border border-slate-200 object-cover">
                    @else
                        <div class="flex h-12 w-12 items-center justify-center rounded-[8px] border border-slate-200 bg-slate-50 text-slate-400">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                        </div>
                    @endif
                    <div class="min-w-0 flex-1">
                        <h3 class="truncate text-sm font-semibold text-slate-900">{{ $group['product_title'] }}</h3>
                        <div class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                            <span>Trendyol ID: {{ $group['trendyol_product_id'] }}</span>
                            @if($group['trendyol_product_barcode'])
                                <span>·</span>
                                <span>Barkod: {{ $group['trendyol_product_barcode'] }}</span>
                            @endif
                            @if(($group['match_status'] ?? '') === 'matched' && $group['wc_product_id'])
                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-emerald-700">✓ WC#{{ $group['wc_product_id'] }}</span>
                            @elseif(($group['match_status'] ?? '') === 'suggested')
                                <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2 py-0.5 text-blue-700">💡 Önerildi ({{ number_format(($group['match_score'] ?? 0) * 100) }}%)</span>
                            @else
                                <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-amber-700">⚠ Eşleşmedi</span>
                            @endif
                        </div>
                    </div>
                    <div class="flex flex-col items-end gap-1 text-xs text-slate-500">
                        <span>{{ $group['reviews_count'] }} yorum</span>
                        @if($group['match_status'] === 'matched' && $group['wc_product_id'])
                            <button wire:click="pushProductReviewsToWoo('{{ $group['trendyol_product_id'] }}')"
                                    wire:confirm="Bu ürünün tüm onaylı yorumlarını WooCommerce'e göndermek istiyor musunuz?"
                                    class="text-blue-600 hover:text-blue-800 hover:underline">WC'ye gönder</button>
                        @endif
                    </div>
                </div>

                {{-- Reviews for this product --}}
                <div class="space-y-3">
                    @foreach($group['reviews'] as $review)
                        <div class="rounded-[8px] border border-slate-200 p-3 {{ $review->is_spam ? 'border-red-200 bg-red-50/40' : '' }} {{ $review->status === 'approved' ? 'border-l-4 border-l-emerald-400' : '' }} {{ $review->status === 'rejected' ? 'border-l-4 border-l-red-400' : '' }}">
                            <div class="flex items-start gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-sm font-medium text-slate-900">{{ $review->reviewer_name_masked }}</span>
                                        <span class="inline-flex items-center gap-0.5 rounded-full bg-orange-50 px-1.5 py-0.5 text-[10px] font-medium text-orange-700">
                                            <svg class="h-2.5 w-2.5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a8 8 0 100 16 8 8 0 000-16zm3 9H7v-2h6v2z"/></svg>
                                            Trendyol
                                        </span>
                                        <div class="flex">
                                            @for($i = 1; $i <= 5; $i++)
                                                <svg class="h-3.5 w-3.5 {{ $i <= $review->rating ? 'text-amber-400' : 'text-slate-300' }}" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                            @endfor
                                        </div>
                                        @if($review->reviewed_at)
                                            <span class="text-xs text-slate-400">· {{ $review->reviewed_at->diffForHumans() }}</span>
                                        @endif
                                    </div>
                                    <p class="mt-1.5 text-sm text-slate-700">{{ $review->comment }}</p>

                                    {{-- Review Media --}}
                                    @if(!empty($review->review_media))
                                        <div class="mt-2 flex flex-wrap gap-1.5">
                                            @foreach($review->review_media as $media)
                                                @if(!empty($media['url']))
                                                    <img src="{{ $media['url'] }}" alt="" class="h-16 w-16 rounded-[6px] border border-slate-200 object-cover cursor-pointer hover:opacity-80">
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif

                                    {{-- Helpful count --}}
                                    @if($review->helpful_count > 0)
                                        <p class="mt-1.5 text-xs text-slate-400">👍 {{ $review->helpful_count }} kişi faydalı buldu</p>
                                    @endif

                                    {{-- Spam flags --}}
                                    @if($review->is_spam && !empty($review->spam_flags))
                                        <p class="mt-1 text-xs text-red-500">⚠ Spam: {{ implode(', ', $review->spam_flags) }}</p>
                                    @endif
                                </div>

                                {{-- Action buttons --}}
                                <div class="flex flex-col gap-1">
                                    @if($review->status === 'pending')
                                        <button wire:click="approveReview({{ $review->id }})" class="rounded-[6px] bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700 hover:bg-emerald-100">Onayla</button>
                                        <button wire:click="rejectReview({{ $review->id }})" class="rounded-[6px] bg-red-50 px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-100">Reddet</button>
                                    @elseif($review->status === 'approved')
                                        <button wire:click="rejectReview({{ $review->id }})" class="rounded-[6px] bg-slate-50 px-2 py-1 text-xs font-medium text-slate-600 hover:bg-slate-100">Geri al</button>
                                        @if($review->wc_push_status !== 'pushed')
                                            <button wire:click="pushReviewToWoo({{ $review->id }})" class="rounded-[6px] bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 hover:bg-blue-100">WC'ye gönder</button>
                                        @else
                                            <span class="rounded-[6px] bg-blue-50 px-2 py-1 text-xs font-medium text-blue-600">✓ Gönderildi</span>
                                        @endif
                                    @elseif($review->status === 'rejected')
                                        <button wire:click="approveReview({{ $review->id }})" class="rounded-[6px] bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700 hover:bg-emerald-100">Onayla</button>
                                    @endif
                                    @if($review->is_featured)
                                        <span class="rounded-[6px] bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700">★ Öne çıkan</span>
                                    @else
                                        <button wire:click="toggleReviewFeatured({{ $review->id }})" class="rounded-[6px] bg-slate-50 px-2 py-1 text-xs text-slate-500 hover:bg-slate-100">Öne çıkar</button>
                                    @endif
                                    <button wire:click="deleteReview({{ $review->id }})" wire:confirm="Bu yorumu silmek istediğinize emin misiniz? (soft-delete — geri alınabilir)" class="rounded-[6px] bg-slate-50 px-2 py-1 text-xs text-red-500 hover:bg-red-50">Sil</button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="p-12 text-center">
                <svg class="mx-auto h-12 w-12 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                <h3 class="mt-2 text-sm font-semibold text-slate-900">Henüz yorum yok</h3>
                <p class="mt-1 text-sm text-slate-500">Mağaza yorumlarınızı taramak için yukarıdaki düğmeyi kullanın.</p>
            </div>
        @endforelse
    </div>
</section>

@script
<script>
    Livewire.on('booster:review-scan-bridge', (event) => {
        const payload = event[0] || event;
        const minimumVersion = '0.12.0';
        const versionAtLeast = (version, minimum) => {
            const normalize = (value) => String(value || '').split('.').map((part) => Number.parseInt(part, 10) || 0);
            const current = normalize(version);
            const required = normalize(minimum);

            for (let index = 0; index < Math.max(current.length, required.length); index++) {
                if ((current[index] || 0) > (required[index] || 0)) return true;
                if ((current[index] || 0) < (required[index] || 0)) return false;
            }

            return true;
        };

        if (!window.__zolmBoosterCompanionReady || !versionAtLeast(window.__zolmBoosterCompanionVersion, minimumVersion)) {
            Livewire.dispatch('review-scan-bridge-error', {
                message: `Yorum taraması için Chrome Companion v${minimumVersion} veya üzeri gerekli. Bağlı sürüm: v${window.__zolmBoosterCompanionVersion || 'yok'}.`
            });
            return;
        }

        const requestId = 'review-scan-' + Date.now() + '-' + Math.random().toString(36).slice(2, 8);
        const timer = window.setTimeout(() => {
            window.removeEventListener('message', messageHandler);
            Livewire.dispatch('review-scan-bridge-error', {
                message: 'Chrome eklentisi yanıt vermedi. Eklentinin yüklü ve Trendyol mağaza sayfasında olduğunuzdan emin olun.'
            });
        }, 30 * 60 * 1000);

        const messageHandler = (e) => {
            if (e.source !== window || e.origin !== window.location.origin) return;
            if (e.data?.type === 'REVIEW_SCAN_RESULT' && e.data?.request_id === requestId) {
                window.clearTimeout(timer);
                window.removeEventListener('message', messageHandler);
                const response = e.data.response || { ok: false, message: 'Geçersiz yanıt.' };
                if (response.ok) {
                    Livewire.dispatch('review-scan-bridge-completed', { stats: response.stats, syncRunId: response.sync_run_id });
                } else {
                    Livewire.dispatch('review-scan-bridge-error', { message: response.message || 'Tarama başarısız.' });
                }
            }
        };

        window.addEventListener('message', messageHandler);

        window.postMessage({
            source: 'zolm-booster-page',
            type: 'REVIEW_SCAN_QUERY',
            request_id: requestId,
            source_url: window.location.href,
            options: {
                sync_run_id: payload.sync_run_id || null,
                sync_type: payload.sync_type || 'delta',
                last_synced_at: payload.last_synced_at || null,
                review_source_id: payload.review_source_id || null,
                store_url: payload.store_url || '',
                store_name: payload.store_name || '',
                merchant_id: payload.merchant_id || '',
            },
        }, window.location.origin);
    });

    Livewire.on('booster:review-store-preview-bridge', (event) => {
        const payload = event[0] || event;
        const requestId = 'review-store-preview-' + Date.now() + '-' + Math.random().toString(36).slice(2, 8);
        const timer = window.setTimeout(() => {
            window.removeEventListener('message', messageHandler);
            Livewire.dispatch('review-store-preview-failed', { message: 'Trendyol mağaza doğrulaması zaman aşımına uğradı.' });
        }, 120000);

        const messageHandler = (e) => {
            if (e.source !== window || e.origin !== window.location.origin) return;
            if (e.data?.type !== 'REVIEW_STORE_PREVIEW_RESULT' || e.data?.request_id !== requestId) return;

            window.clearTimeout(timer);
            window.removeEventListener('message', messageHandler);
            const response = e.data.response || { ok: false, message: 'Geçersiz doğrulama yanıtı.' };
            if (response.ok) {
                Livewire.dispatch('review-store-preview-completed', { preview: response });
            } else {
                Livewire.dispatch('review-store-preview-failed', { message: response.message || 'Mağaza doğrulanamadı.' });
            }
        };

        window.addEventListener('message', messageHandler);
        window.postMessage({
            source: 'zolm-booster-page',
            type: 'REVIEW_STORE_PREVIEW_QUERY',
            request_id: requestId,
            source_url: payload.store_url || '',
            options: {
                store_url: payload.store_url || '',
                store_name: payload.store_name || '',
                merchant_id: payload.merchant_id || '',
            },
        }, window.location.origin);
    });

    Livewire.on('review-scan-bridge-completed', (event) => {
        const data = event[0] || event;
        Livewire.dispatch('review-scan-poll');
    });

    Livewire.on('review-scan-bridge-error', (event) => {
        // Sunucu tarafındaki listener senkronizasyonu failed durumuna geçirir.
    });
</script>
@endscript
