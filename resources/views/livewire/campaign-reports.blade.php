<div class="space-y-5 lg:space-y-6">
    {{-- Başlık --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl lg:text-2xl font-bold text-slate-900">⚡ Tüm Kampanya Raporları</h1>
            <p class="text-sm text-slate-500 mt-1">Trendyol kampanya modüllerinden alınan tüm geçmiş analiz ve optimizasyon raporları</p>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════ --}}
    {{-- AKILLI EXCEL YÜKLEME ALANI --}}
    {{-- ═══════════════════════════════════════════════ --}}
    <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div x-data="{ smartUploadOpen: false }" class="relative">
            {{-- Accordion Başlık --}}
            <button type="button"
                    @click="smartUploadOpen = !smartUploadOpen"
                    class="flex w-full items-center justify-between gap-3 px-4 py-4 text-left transition hover:bg-slate-50/60">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-[8px] border border-slate-200 bg-slate-50 text-slate-500 shrink-0">
                        <svg class="h-4.5 w-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-900">Akıllı Kampanya Yükleme</p>
                        <p class="mt-0.5 text-xs text-slate-500">Trendyol kampanya Excel'inizi yükleyin — türü otomatik algılanır ve analiz başlar</p>
                    </div>
                </div>
                <svg class="h-5 w-5 shrink-0 text-slate-400 transition" :class="{ 'rotate-180': smartUploadOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            {{-- Accordion İçerik --}}
            <div x-cloak x-show="smartUploadOpen" x-transition class="border-t border-slate-200 px-4 py-4">
                <form wire:submit.prevent="analyzeUpload" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-[1fr_280px] gap-3">
                        {{-- Dosya seç alanı --}}
                        <label class="block cursor-pointer rounded-[8px] border-2 border-dashed border-slate-300 bg-slate-50/60 px-4 py-5 transition hover:border-slate-400 hover:bg-white">
                            <input type="file" wire:model="uploadFile" accept=".xlsx,.xls" class="hidden">
                            <div class="flex items-center gap-3">
                                <span class="inline-flex h-10 w-10 items-center justify-center rounded-[8px] border border-slate-200 bg-white text-slate-400 shrink-0">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </span>
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-slate-700">
                                        {{ $uploadFile ? $uploadFile->getClientOriginalName() : 'Excel dosyası seçin veya sürükleyin' }}
                                    </p>
                                    <p class="mt-0.5 text-xs text-slate-400">
                                        {{ $uploadFile ? number_format($uploadFile->getSize() / 1024, 0) . ' KB' : 'Ürün Komisyon · Plus Komisyon · Avantajlı Ürün · Flaş Ürünler · Sepet İndirimi' }}
                                    </p>
                                </div>
                            </div>
                        </label>

                        {{-- Rapor adı (opsiyonel) + Buton --}}
                        <div class="flex flex-col gap-2">
                            <input type="text"
                                   wire:model="uploadReportName"
                                   placeholder="Rapor adı (opsiyonel)"
                                   class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2.5 text-base text-slate-900 shadow-sm outline-none transition focus:border-slate-400 focus:ring-0 sm:text-sm">
                            <button type="submit"
                                    wire:loading.attr="disabled"
                                    wire:target="analyzeUpload,uploadFile"
                                    @disabled(!$uploadFile)
                                    class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed sm:min-h-[40px]">
                                <span wire:loading.remove wire:target="analyzeUpload">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                </span>
                                <span wire:loading.remove wire:target="analyzeUpload">Algıla ve Analiz Et</span>
                                <span wire:loading wire:target="analyzeUpload" class="flex items-center gap-2">
                                    <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                    Analiz ediliyor...
                                </span>
                            </button>
                        </div>
                    </div>

                    @error('uploadFile')
                        <p class="text-sm text-rose-600">{{ $message }}</p>
                    @enderror

                    {{-- Desteklenen formatlar --}}
                    <div class="flex flex-wrap items-center gap-2 text-[11px]">
                        <span class="rounded-[6px] border border-slate-200 bg-slate-50/80 px-2 py-1 font-medium text-slate-500">📉 Ürün Komisyon</span>
                        <span class="rounded-[6px] border border-indigo-200 bg-indigo-50/80 px-2 py-1 font-medium text-indigo-600">⭐ Plus Komisyon</span>
                        <span class="rounded-[6px] border border-amber-200 bg-amber-50/80 px-2 py-1 font-medium text-amber-600">🏷️ Avantajlı Ürün</span>
                        <span class="rounded-[6px] border border-purple-200 bg-purple-50/80 px-2 py-1 font-medium text-purple-600">⚡ Flaş Ürünler</span>
                        <span class="rounded-[6px] border border-cyan-200 bg-cyan-50/80 px-2 py-1 font-medium text-cyan-700">Sepet İndirimi</span>
                    </div>
                </form>

                {{-- Sonuç mesajı --}}
                @if($uploadMessage)
                    <div class="mt-3 rounded-[8px] border p-3 text-sm {{ $uploadMessageType === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : ($uploadMessageType === 'error' ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-slate-200 bg-slate-50 text-slate-700') }}">
                        <div class="flex items-start justify-between gap-3">
                            <p>{{ $uploadMessage }}</p>
                            <button type="button" wire:click="clearUploadMessage" class="shrink-0 text-current opacity-50 hover:opacity-100 transition">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- Filtreleme Butonları --}}
    <div class="rounded-[10px] border border-slate-200 bg-white p-2 overflow-x-auto shadow-sm">
        <div class="flex gap-2 min-w-max">
            <button wire:click="setFilter('all')"
                class="px-4 py-2 text-sm font-medium rounded-[6px] transition-colors {{ $activeFilter === 'all' ? 'bg-slate-900 text-white shadow' : 'text-slate-600 hover:bg-slate-100' }}">
                Tümü
            </button>
            <button wire:click="setFilter('tariff')"
                class="px-4 py-2 text-sm font-medium rounded-[6px] transition-colors flex items-center gap-2 {{ $activeFilter === 'tariff' ? 'bg-slate-900 text-white shadow' : 'text-slate-600 hover:bg-slate-100' }}">
                <span class="w-2 h-2 rounded-full {{ $activeFilter === 'tariff' ? 'bg-white' : 'bg-slate-400' }}"></span>
                Ürün Komisyon
            </button>
            <button wire:click="setFilter('plus')"
                class="px-4 py-2 text-sm font-medium rounded-[6px] transition-colors flex items-center gap-2 {{ $activeFilter === 'plus' ? 'bg-indigo-600 text-white shadow' : 'text-slate-600 hover:bg-indigo-50' }}">
                <span class="w-2 h-2 rounded-full {{ $activeFilter === 'plus' ? 'bg-white' : 'bg-indigo-500' }}"></span>
                Plus Komisyon
            </button>
            <button wire:click="setFilter('badge')"
                class="px-4 py-2 text-sm font-medium rounded-[6px] transition-colors flex items-center gap-2 {{ $activeFilter === 'badge' ? 'bg-amber-500 text-white shadow' : 'text-slate-600 hover:bg-amber-50' }}">
                <span class="w-2 h-2 rounded-full {{ $activeFilter === 'badge' ? 'bg-white' : 'bg-amber-500' }}"></span>
                Avantajlı Ürün
            </button>
            <button wire:click="setFilter('flash')"
                class="px-4 py-2 text-sm font-medium rounded-[6px] transition-colors flex items-center gap-2 {{ $activeFilter === 'flash' ? 'bg-purple-600 text-white shadow' : 'text-slate-600 hover:bg-purple-50' }}">
                <span class="w-2 h-2 rounded-full {{ $activeFilter === 'flash' ? 'bg-white' : 'bg-purple-500' }}"></span>
                Flaş Ürünler
            </button>
            <button wire:click="setFilter('basket_discount')"
                class="px-4 py-2 text-sm font-medium rounded-[6px] transition-colors flex items-center gap-2 {{ $activeFilter === 'basket_discount' ? 'bg-cyan-700 text-white shadow' : 'text-slate-600 hover:bg-cyan-50' }}">
                <span class="w-2 h-2 rounded-full {{ $activeFilter === 'basket_discount' ? 'bg-white' : 'bg-cyan-600' }}"></span>
                Sepet İndirimi
            </button>
        </div>
    </div>

    {{-- Raporlar Grid --}}
    @if($reports->isEmpty())
        <div class="rounded-[10px] border border-slate-200 bg-white p-12 text-center shadow-sm">
            <svg class="w-16 h-16 text-slate-300 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <h3 class="mt-4 text-lg font-medium text-slate-900">Sonuç Bulunamadı</h3>
            <p class="mt-2 text-sm text-slate-500">
                @if($activeFilter === 'all')
                    Henüz hiçbir kampanya modülünde rapor oluşturulmamış.
                    <br>Yukarıdaki <strong>Akıllı Kampanya Yükleme</strong> alanından ilk Excel'inizi yükleyin.
                @else
                    Seçili kampanya modülünde rapor bulunamadı.
                @endif
            </p>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 lg:gap-5">
            @foreach($reports as $rpt)
                @php
                    $theme = match ($rpt->campaign_type) {
                        'tariff' => [
                            'color_text' => 'text-slate-700',
                            'color_bg'   => 'bg-slate-100',
                            'button_bg'  => 'bg-slate-900 text-white hover:bg-slate-800',
                            'label'      => 'Ürün Komisyon',
                            'icon'       => '📉',
                        ],
                        'plus' => [
                            'color_text' => 'text-indigo-700',
                            'color_bg'   => 'bg-indigo-100',
                            'button_bg'  => 'bg-indigo-600 text-white hover:bg-indigo-700',
                            'label'      => 'Plus Komisyon',
                            'icon'       => '⭐',
                        ],
                        'badge' => [
                            'color_text' => 'text-amber-700',
                            'color_bg'   => 'bg-amber-100',
                            'button_bg'  => 'bg-amber-500 text-white hover:bg-amber-600',
                            'label'      => 'Avantajlı Ürün',
                            'icon'       => '🏷️',
                        ],
                        'flash' => [
                            'color_text' => 'text-purple-700',
                            'color_bg'   => 'bg-purple-100',
                            'button_bg'  => 'bg-purple-600 text-white hover:bg-purple-700',
                            'label'      => 'Flaş Ürünler',
                            'icon'       => '⚡',
                        ],
                        'basket_discount' => [
                            'color_text' => 'text-cyan-800',
                            'color_bg'   => 'bg-cyan-100',
                            'button_bg'  => 'bg-cyan-700 text-white hover:bg-cyan-800',
                            'label'      => 'Sepet İndirimi',
                            'icon'       => '',
                        ],
                        default => [
                            'color_text' => 'text-slate-600',
                            'color_bg'   => 'bg-slate-100',
                            'button_bg'  => 'bg-slate-900 text-white hover:bg-slate-800',
                            'label'      => 'Bilinmeyen',
                            'icon'       => '📦',
                        ],
                    };
                @endphp

                <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm hover:shadow-md transition p-4 lg:p-5 relative overflow-hidden group">
                    {{-- Tip Etiketi (Sağ üst) --}}
                    <div class="absolute top-0 right-0 rounded-bl-[8px] px-3 py-1 font-bold text-[10px] uppercase tracking-[0.08em] {{ $theme['color_bg'] }} {{ $theme['color_text'] }}">
                        {{ $theme['label'] }}
                    </div>

                    <div class="flex items-start justify-between mb-3 mt-2 pr-24">
                        <div class="flex-1 min-w-0">
                            <h4 class="font-semibold text-slate-900 truncate" title="{{ $rpt->name }}">{{ $rpt->name }}</h4>
                            <p class="text-xs text-slate-400 mt-0.5">{{ $rpt->created_at->format('d.m.Y H:i') }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-2 text-center rounded-[8px] border border-slate-200 bg-slate-50/70 p-3 mb-3">
                        <div>
                            <div class="text-[10px] text-slate-400 uppercase tracking-wider font-semibold">Ürün</div>
                            <div class="text-lg font-bold text-slate-900">{{ number_format($rpt->total_products, 0, ',', '.') }}</div>
                        </div>
                        <div>
                            <div class="text-[10px] {{ $theme['color_text'] }} uppercase tracking-wider font-semibold">Fırsat</div>
                            <div class="text-lg font-bold {{ $theme['color_text'] }}">{{ number_format($rpt->opportunity_count, 0, ',', '.') }}</div>
                        </div>
                        <div>
                            <div class="text-[10px] text-emerald-500 uppercase tracking-wider font-semibold">Ek Kâr</div>
                            <div class="text-sm font-bold {{ (float) $rpt->total_extra_profit >= 0 ? 'text-emerald-600' : 'text-rose-600' }} mt-0.5">
                                {{ (float) $rpt->total_extra_profit >= 0 ? '+' : '-' }}{{ number_format(abs((float) $rpt->total_extra_profit), 0, ',', '.') }} ₺
                            </div>
                        </div>
                    </div>

                    @if($rpt->original_filename)
                        <p class="text-[10px] text-slate-400 truncate mb-3 flex items-center gap-1">
                            <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            {{ $rpt->original_filename }}
                        </p>
                    @endif

                    <div class="flex gap-2">
                        @php
                            $routeMap = [
                                'tariff' => 'campaigns.product-commission',
                                'plus' => 'campaigns.plus-commission',
                                'badge' => 'campaigns.badge-pricing',
                                'flash' => 'campaigns.flash-products',
                                'basket_discount' => 'campaigns.basket-discount',
                            ];
                            $reportRoute = isset($routeMap[$rpt->campaign_type]) ? route($routeMap[$rpt->campaign_type], ['report' => $rpt->id]) : '#';
                        @endphp
                        <a href="{{ $reportRoute }}" target="_blank"
                            class="flex-1 min-h-[44px] sm:min-h-[40px] flex items-center justify-center gap-2 rounded-[6px] text-sm font-medium transition-colors {{ $theme['button_bg'] }}">
                            <span>Görüntüle</span>
                            <svg class="w-4 h-4 opacity-70 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                            </svg>
                        </a>
                        <button wire:click="deleteReport({{ $rpt->id }})"
                            wire:confirm="Bu raporu kalıcı olarak silmek istediğinize emin misiniz?"
                            class="px-3 min-h-[44px] sm:min-h-[40px] text-xs text-rose-600 border border-rose-200 rounded-[6px] hover:bg-rose-50 flex-shrink-0 flex items-center justify-center" title="Raporu Sil">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
