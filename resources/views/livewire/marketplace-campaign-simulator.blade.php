<div class="space-y-4 lg:space-y-6">
    <!-- Header -->
    <div>
        <h1 class="text-xl lg:text-2xl font-semibold text-slate-900 tracking-tight">Kampanya & Fiyatlama Simülatörü</h1>
        <p class="text-sm text-slate-500 mt-1">Ürünlerinizi kampanyalara sokmadan önce hangi pazaryerinde ne kadar kâr edeceğinizi önceden görün.</p>
    </div>

    <!-- Mode Toggle -->
    <div class="inline-flex rounded-[8px] bg-slate-100 p-1">
        <button type="button" wire:click="$set('simulationMode', 'single')"
                class="px-4 py-2 text-sm font-medium rounded-[6px] transition-all {{ $simulationMode === 'single' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700' }}">
            Tekil Ürün
        </button>
        <button type="button" wire:click="$set('simulationMode', 'portfolio')"
                class="px-4 py-2 text-sm font-medium rounded-[6px] transition-all {{ $simulationMode === 'portfolio' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700' }}">
            Portföy (Toplu)
        </button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 lg:gap-6">
        
        <!-- Sol: Kontrol Paneli -->
        <div class="lg:col-span-1 space-y-4 lg:space-y-6">
            
            <!-- Adım 1 -->
            <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm p-4 lg:p-6">
                <h2 class="text-base font-medium text-slate-900 mb-4">
                    {{ $simulationMode === 'single' ? '1. Ürün Seçimi' : '1. Portföy Kapsamı' }}
                </h2>
                
                @if($simulationMode === 'single')
                    <div class="relative">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Ürün Ara (İsim, Barkod, Stok Kodu)</label>
                        <input type="text" wire:model.live.debounce.300ms="searchQuery" 
                               class="w-full rounded-[6px] border border-slate-200 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-slate-400 focus:outline-none focus:ring-1 focus:ring-slate-400 transition-shadow" 
                               placeholder="Örn: Telefon Kılıfı">
                        
                        @if(!empty($products) && !$selectedProductId)
                            <div class="absolute z-10 w-full mt-1 bg-white border border-slate-200 rounded-[6px] shadow-lg max-h-60 overflow-auto">
                                <ul class="py-1">
                                    @foreach($products as $prod)
                                        <li>
                                            <button wire:click="selectProduct({{ $prod->id }})" class="w-full text-left px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 transition-colors">
                                                <div class="font-medium text-slate-900">{{ $prod->product_name }}</div>
                                                <div class="text-xs text-slate-500">Stok: {{ $prod->stock_code }} | Barkod: {{ $prod->barcode }}</div>
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>

                    @if($selectedProductId)
                        <div class="mt-4 p-3 rounded-[8px] border border-emerald-200 bg-emerald-50/50 flex items-center justify-between">
                            <div>
                                <div class="text-sm font-medium text-emerald-900 line-clamp-1">{{ $searchQuery }}</div>
                                <div class="text-xs text-emerald-700/80 mt-0.5">Seçili Ürün</div>
                            </div>
                            <button wire:click="$set('selectedProductId', null)" class="text-emerald-700 hover:text-emerald-900 px-2 py-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>
                    @endif
                @else
                    <div class="space-y-4">
                        <p class="text-xs text-slate-500">Tüm ürünlerinizin veya belirli bir kategorinin toplu simülasyonunu yapın. Performans için 100 ürünlük örneklem kullanılır.</p>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Kategori (İsteğe Bağlı)</label>
                            <input type="text" wire:model.live.debounce.500ms="portfolioCategory" wire:change="runPortfolioSimulation"
                                   class="w-full rounded-[6px] border border-slate-200 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-slate-400 focus:outline-none focus:ring-1 focus:ring-slate-400 transition-shadow" 
                                   placeholder="Örn: Elektronik">
                        </div>
                    </div>
                @endif
            </div>

            <!-- Adım 2 -->
            <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm p-4 lg:p-6 transition-opacity duration-300 {{ ($simulationMode === 'single' && !$selectedProductId) ? 'opacity-50 pointer-events-none' : 'opacity-100' }}">
                <h2 class="text-base font-medium text-slate-900 mb-4">2. Senaryo Ayarları</h2>
                
                <div class="space-y-4">
                    @if($simulationMode === 'single')
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Hedef Satış Fiyatı (TRY)</label>
                            <input type="number" step="0.01" wire:model.live.debounce.500ms="targetPrice" wire:change="runSimulation"
                                   class="w-full rounded-[6px] border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-slate-400 focus:outline-none focus:ring-1 focus:ring-slate-400 transition-shadow">
                        </div>
                    @else
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Fiyat Değişimi (%)</label>
                            <input type="number" step="1" wire:model.live.debounce.500ms="portfolioPriceChangePercent" wire:change="runPortfolioSimulation"
                                   class="w-full rounded-[6px] border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-slate-400 focus:outline-none focus:ring-1 focus:ring-slate-400 transition-shadow"
                                   placeholder="Örn: 10 (+%10 fiyat artışı)">
                            <p class="text-xs text-slate-500 mt-1">Örn: -5 yazarsanız fiyatlar %5 düşer.</p>
                        </div>
                    @endif
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Pazaryeri Kampanya İndirimi (%)</label>
                        <input type="number" step="0.1" wire:model.live.debounce.500ms="commissionDiscount" wire:change="{{ $simulationMode === 'single' ? 'runSimulation' : 'runPortfolioSimulation' }}"
                               class="w-full rounded-[6px] border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-slate-400 focus:outline-none focus:ring-1 focus:ring-slate-400 transition-shadow"
                               placeholder="Örn: 2 (Komisyondan %2 düşer)">
                        <p class="text-xs text-slate-500 mt-1">Pazaryerinin kampanyaya katılım için sunduğu komisyon indirimi.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Ortalama Kargo Maliyeti (TRY)</label>
                        <input type="number" step="0.01" wire:model.live.debounce.500ms="customCargoCost" wire:change="{{ $simulationMode === 'single' ? 'runSimulation' : 'runPortfolioSimulation' }}"
                               class="w-full rounded-[6px] border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-slate-400 focus:outline-none focus:ring-1 focus:ring-slate-400 transition-shadow">
                    </div>
                </div>
            </div>

        </div>

        <!-- Sağ: Simülasyon Sonuçları -->
        <div class="lg:col-span-2">
            <div class="rounded-[10px] border border-slate-200 bg-white shadow-sm p-4 lg:p-6 min-h-[300px] h-full">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-base font-medium text-slate-900">3. Pazaryeri Karşılaştırması</h2>
                    <span wire:loading wire:target="runSimulation,runPortfolioSimulation" class="text-xs text-slate-500 flex items-center gap-1">
                        <svg class="animate-spin w-3 h-3" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        Hesaplanıyor...
                    </span>
                </div>

                @if($simulationMode === 'single')
                    @if(empty($results))
                        <div class="flex flex-col items-center justify-center py-16 text-center">
                            <div class="w-12 h-12 rounded-full bg-slate-50 flex items-center justify-center mb-3">
                                <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                            </div>
                            <h3 class="text-sm font-medium text-slate-900">Sonuç Yok</h3>
                            <p class="text-sm text-slate-500 mt-1 max-w-sm">Simülasyon sonuçlarını görmek için sol taraftan bir ürün seçin ve hedef fiyatınızı belirleyin.</p>
                        </div>
                    @else
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            @foreach($results as $res)
                                <div class="rounded-[8px] border {{ $res['is_profitable'] ? 'border-emerald-200 bg-emerald-50/30' : 'border-red-200 bg-red-50/30' }} p-4 relative overflow-hidden group transition-all hover:shadow-md">
                                    <!-- Marketplace Badge -->
                                    <div class="absolute top-0 right-0 bg-white/80 backdrop-blur-sm border-b border-l {{ $res['is_profitable'] ? 'border-emerald-200' : 'border-red-200' }} px-3 py-1 rounded-bl-[8px] text-xs font-semibold text-slate-700 capitalize">
                                        {{ $res['marketplace'] }}
                                    </div>
                                    
                                    <!-- Main Metric -->
                                    <div class="mt-2">
                                        <div class="text-xs text-slate-500 mb-1">Net Kâr</div>
                                        <div class="text-2xl font-bold tracking-tight {{ $res['is_profitable'] ? 'text-emerald-700' : 'text-red-600' }}">
                                            {{ number_format($res['profit_value'], 2, ',', '.') }} ₺
                                        </div>
                                    </div>

                                    <!-- Breakdown -->
                                    <div class="mt-4 pt-4 border-t {{ $res['is_profitable'] ? 'border-emerald-200/60' : 'border-red-200/60' }} space-y-2">
                                        <div class="flex justify-between text-sm">
                                            <span class="text-slate-600">Satış Fiyatı</span>
                                            <span class="font-medium text-slate-900">{{ number_format($res['target_price'], 2, ',', '.') }} ₺</span>
                                        </div>
                                        <div class="flex justify-between text-sm">
                                            <span class="text-slate-600">Komisyon (%{{ $res['commission_rate'] }})</span>
                                            <span class="text-red-500">-{{ number_format($res['commission_amount'], 2, ',', '.') }} ₺</span>
                                        </div>
                                        <div class="flex justify-between text-sm">
                                            <span class="text-slate-600">Kargo & KDV</span>
                                            <span class="text-red-500">-{{ number_format($res['cargo_cost'] + $res['vat_effect'], 2, ',', '.') }} ₺</span>
                                        </div>
                                        <div class="flex justify-between text-sm">
                                            <span class="text-slate-600">Ürün Maliyeti</span>
                                            <span class="text-red-500">-{{ number_format($res['cogs'] + $res['packaging'], 2, ',', '.') }} ₺</span>
                                        </div>
                                    </div>

                                    <!-- Footer Metrics -->
                                    <div class="mt-4 flex items-center justify-between">
                                        <div class="flex items-center gap-1.5">
                                            <div class="px-2 py-0.5 text-xs font-mono rounded {{ $res['profit_margin_percent'] > 15 ? 'bg-emerald-100 text-emerald-800' : ($res['profit_margin_percent'] > 0 ? 'bg-amber-100 text-amber-800' : 'bg-red-100 text-red-800') }}">
                                                %{{ number_format($res['profit_margin_percent'], 1, ',', '') }} Marj
                                            </div>
                                        </div>
                                        <div class="text-xs font-medium text-slate-500">
                                            ROI: {{ number_format($res['margin_multiplier'], 2, ',', '.') }}x
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @else
                    @if(empty($portfolioResults))
                        <div class="flex flex-col items-center justify-center py-16 text-center">
                            <div class="w-12 h-12 rounded-full bg-slate-50 flex items-center justify-center mb-3">
                                <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                            </div>
                            <h3 class="text-sm font-medium text-slate-900">Portföy Simülasyonu Hazır Değil</h3>
                            <p class="text-sm text-slate-500 mt-1 max-w-sm">Simülasyon sonuçlarını görmek için sol taraftan fiyat artış oranını girin ve bekleyin.</p>
                        </div>
                    @else
                        <div class="grid grid-cols-1 gap-4">
                            @foreach($portfolioResults as $res)
                                <div class="rounded-[8px] border {{ $res['profit_difference'] >= 0 ? 'border-emerald-200 bg-emerald-50/20' : 'border-red-200 bg-red-50/20' }} p-4 relative overflow-hidden group">
                                    <!-- Marketplace Badge -->
                                    <div class="absolute top-0 right-0 bg-white/80 backdrop-blur-sm border-b border-l {{ $res['profit_difference'] >= 0 ? 'border-emerald-200' : 'border-red-200' }} px-3 py-1 rounded-bl-[8px] text-xs font-semibold text-slate-700 capitalize">
                                        {{ $res['marketplace'] }} ({{ $res['total_products'] }} Ürün)
                                    </div>
                                    
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-2">
                                        <!-- Mevcut Kâr -->
                                        <div>
                                            <div class="text-xs text-slate-500 mb-1">Mevcut Kâr (Aylık)</div>
                                            <div class="text-lg font-bold text-slate-900">
                                                {{ number_format($res['current_total_profit'], 0, ',', '.') }} ₺
                                            </div>
                                            <div class="text-xs text-slate-400 mt-0.5">%{{ number_format($res['current_margin_percent'], 1, ',', '') }} Marj</div>
                                        </div>
                                        
                                        <!-- Simüle Edilen Kâr -->
                                        <div>
                                            <div class="text-xs text-slate-500 mb-1">Simüle Edilen Kâr</div>
                                            <div class="text-lg font-bold {{ $res['simulated_total_profit'] > 0 ? 'text-emerald-700' : 'text-red-600' }}">
                                                {{ number_format($res['simulated_total_profit'], 0, ',', '.') }} ₺
                                            </div>
                                            <div class="text-xs {{ $res['simulated_margin_percent'] > $res['current_margin_percent'] ? 'text-emerald-600' : 'text-red-500' }} mt-0.5">%{{ number_format($res['simulated_margin_percent'], 1, ',', '') }} Marj</div>
                                        </div>

                                        <!-- Fark -->
                                        <div>
                                            <div class="text-xs text-slate-500 mb-1">Fark (₺)</div>
                                            <div class="text-lg font-bold {{ $res['profit_difference'] > 0 ? 'text-emerald-600' : 'text-red-500' }}">
                                                {{ $res['profit_difference'] > 0 ? '+' : '' }}{{ number_format($res['profit_difference'], 0, ',', '.') }} ₺
                                            </div>
                                        </div>

                                        <!-- Riskli Ürünler -->
                                        <div>
                                            <div class="text-xs text-slate-500 mb-1">Zarara Düşecek Ürün</div>
                                            <div class="text-lg font-bold {{ $res['unprofitable_count'] > 0 ? 'text-red-600' : 'text-emerald-600' }}">
                                                {{ $res['unprofitable_count'] }} 
                                            </div>
                                            <div class="text-xs text-slate-500 mt-0.5">/ {{ $res['total_products'] }}</div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endif
            </div>
        </div>

    </div>
</div>
