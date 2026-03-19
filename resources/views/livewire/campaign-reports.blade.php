<div class="space-y-6">
    {{-- Başlık ve Özeti --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl lg:text-2xl font-bold text-gray-900">⚡ Tüm Kampanya Raporları</h1>
            <p class="text-sm lg:text-base text-gray-500 mt-1">Trendyol kampanya modüllerinden alınan tüm geçmiş analiz ve optimizasyon raporları</p>
        </div>
    </div>

    {{-- Filtreleme Butonları --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-2 overflow-x-auto">
        <div class="flex gap-2 min-w-max">
            <button wire:click="setFilter('all')" 
                class="px-4 py-2 text-sm font-medium rounded-lg transition-colors {{ $activeFilter === 'all' ? 'bg-gray-900 text-white shadow' : 'text-gray-600 hover:bg-gray-100' }}">
                Tümü
            </button>
            <button wire:click="setFilter('tariff')" 
                class="px-4 py-2 text-sm font-medium rounded-lg transition-colors flex items-center gap-2 {{ $activeFilter === 'tariff' ? 'bg-gray-900 text-white shadow' : 'text-gray-600 hover:bg-gray-100' }}">
                <span class="w-2 h-2 rounded-full bg-gray-500"></span>
                Ürün Komisyon
            </button>
            <button wire:click="setFilter('plus')" 
                class="px-4 py-2 text-sm font-medium rounded-lg transition-colors flex items-center gap-2 {{ $activeFilter === 'plus' ? 'bg-indigo-600 text-white shadow' : 'text-gray-600 hover:bg-indigo-50' }}">
                <span class="w-2 h-2 rounded-full {{ $activeFilter === 'plus' ? 'bg-white' : 'bg-indigo-500' }}"></span>
                Plus Komisyon
            </button>
            <button wire:click="setFilter('badge')" 
                class="px-4 py-2 text-sm font-medium rounded-lg transition-colors flex items-center gap-2 {{ $activeFilter === 'badge' ? 'bg-amber-500 text-white shadow' : 'text-gray-600 hover:bg-amber-50' }}">
                <span class="w-2 h-2 rounded-full {{ $activeFilter === 'badge' ? 'bg-white' : 'bg-amber-500' }}"></span>
                Avantajlı Ürün
            </button>
            <button wire:click="setFilter('flash')" 
                class="px-4 py-2 text-sm font-medium rounded-lg transition-colors flex items-center gap-2 {{ $activeFilter === 'flash' ? 'bg-purple-600 text-white shadow' : 'text-gray-600 hover:bg-purple-50' }}">
                <span class="w-2 h-2 rounded-full {{ $activeFilter === 'flash' ? 'bg-white' : 'bg-purple-500' }}"></span>
                Flaş Ürünler
            </button>
        </div>
    </div>

    {{-- Raporlar Grid --}}
    @if($reports->isEmpty())
        <div class="bg-white rounded-xl border border-gray-200 p-12 text-center shadow-sm">
            <svg class="w-16 h-16 text-gray-300 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                      d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <h3 class="mt-4 text-lg font-medium text-gray-900">Sonuç Bulunamadı</h3>
            <p class="mt-2 text-sm text-gray-500">
                @if($activeFilter === 'all')
                    Henüz hiçbir kampanya modülünde rapor oluşturulmamış.
                @else
                    Seçili kampanya modülünde rapor bulunamadı.
                @endif
            </p>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 lg:gap-6">
            @foreach($reports as $rpt)
                @php
                    // Define theme variables according to the campaign type
                    $theme = [
                        'color_text' => 'text-gray-600',
                        'color_bg' => 'bg-gray-100',
                        'button_bg' => 'bg-gray-900 text-white hover:bg-gray-800',
                        'label' => 'Bilinmeyen',
                        'icon' => '📦',
                    ];

                    switch ($rpt->campaign_type) {
                        case 'tariff':
                            $theme = [
                                'color_text' => 'text-gray-700',
                                'color_bg' => 'bg-gray-100',
                                'button_bg' => 'bg-gray-900 text-white hover:bg-gray-800',
                                'label' => 'Ürün Komisyon',
                                'icon' => '📉',
                            ];
                            break;
                        case 'plus':
                            $theme = [
                                'color_text' => 'text-indigo-700',
                                'color_bg' => 'bg-indigo-100',
                                'button_bg' => 'bg-indigo-600 text-white hover:bg-indigo-700',
                                'label' => 'Plus Komisyon',
                                'icon' => '⭐',
                            ];
                            break;
                        case 'badge':
                            $theme = [
                                'color_text' => 'text-amber-700',
                                'color_bg' => 'bg-amber-100',
                                'button_bg' => 'bg-amber-500 text-white hover:bg-amber-600',
                                'label' => 'Avantajlı Ürün',
                                'icon' => '🏷️',
                            ];
                            break;
                        case 'flash':
                            $theme = [
                                'color_text' => 'text-purple-700',
                                'color_bg' => 'bg-purple-100',
                                'button_bg' => 'bg-purple-600 text-white hover:bg-purple-700',
                                'label' => 'Flaş Ürünler',
                                'icon' => '⚡',
                            ];
                            break;
                    }
                @endphp

                <div class="bg-white rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition p-4 lg:p-5 relative overflow-hidden group">
                    {{-- Tip Etiketi (Sağ üst) --}}
                    <div class="absolute top-0 right-0 rounded-bl-xl px-3 py-1 font-bold text-[10px] {{ $theme['color_bg'] }} {{ $theme['color_text'] }}">
                        {{ $theme['label'] }}
                    </div>

                    <div class="flex items-start justify-between mb-3 mt-2 pr-20">
                        <div class="flex-1 min-w-0">
                            <h4 class="font-medium text-gray-900 truncate" title="{{ $rpt->name }}">{{ $rpt->name }}</h4>
                            <p class="text-xs text-gray-400 mt-0.5">{{ $rpt->created_at->format('d.m.Y H:i') }}</p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-3 gap-2 text-center bg-gray-50 rounded-lg p-3 mb-3">
                        <div>
                            <div class="text-[10px] text-gray-400 uppercase tracking-wider font-semibold">Ürün</div>
                            <div class="text-lg font-bold text-gray-900">{{ $rpt->total_products }}</div>
                        </div>
                        <div>
                            <div class="text-[10px] {{ str_replace('bg-', 'text-', $theme['color_bg']) }} uppercase tracking-wider font-semibold">Fırsat</div>
                            <div class="text-lg font-bold {{ $theme['color_text'] }}">{{ $rpt->opportunity_count }}</div>
                        </div>
                        <div>
                            <div class="text-[10px] text-green-500 uppercase tracking-wider font-semibold">Ek Kâr</div>
                            <div class="text-sm font-bold text-green-600 mt-0.5">+{{ number_format($rpt->total_extra_profit, 0) }} ₺</div>
                        </div>
                    </div>

                    @if($rpt->original_filename)
                        <p class="text-[10px] text-gray-400 truncate mb-3 flex items-center gap-1">
                            <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
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
                            ];
                            $reportRoute = isset($routeMap[$rpt->campaign_type]) ? route($routeMap[$rpt->campaign_type], ['report' => $rpt->id]) : '#';
                        @endphp
                        <a href="{{ $reportRoute }}" target="_blank"
                            class="flex-1 px-3 py-2 text-sm font-medium rounded-lg transition-colors min-h-[44px] flex items-center justify-center gap-2 {{ $theme['button_bg'] }}">
                            <span>Görüntüle</span>
                            <svg class="w-4 h-4 opacity-70 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                            </svg>
                        </a>
                        <button wire:click="deleteReport({{ $rpt->id }})"
                            wire:confirm="Bu raporu kalıcı olarak silmek istediğinize emin misiniz? (Silinen rapor geri alınamaz)"
                            class="px-3 py-2 text-xs text-red-600 border border-red-200 rounded-lg hover:bg-red-50 min-h-[44px] flex-shrink-0" title="Raporu Sil">
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
