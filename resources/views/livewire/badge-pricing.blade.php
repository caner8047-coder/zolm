<div class="space-y-6 overflow-x-hidden">
    {{-- Başlık --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl lg:text-2xl font-bold text-gray-900">🏷️ Avantajlı Ürün Etiketleri</h1>
            <p class="text-sm lg:text-base text-gray-500 mt-1">Yıldız seviyesi fiyat analizi ile görünürlüğünüzü artırın</p>
        </div>
    </div>

    @if($message)
    <div class="rounded-lg p-4 {{ $messageType === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : ($messageType === 'error' ? 'bg-red-50 text-red-800 border border-red-200' : 'bg-blue-50 text-blue-800 border border-blue-200') }}">
        {{ $message }}
    </div>
    @endif

    {{-- Tab Navigation --}}
    <div class="border-b border-gray-200">
        <nav class="flex gap-4 sm:gap-8">
            <button wire:click="switchTab('analyze')" class="py-3 px-1 text-sm font-medium border-b-2 transition-colors whitespace-nowrap {{ $activeTab === 'analyze' ? 'border-amber-600 text-amber-700' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                🔬 Yeni Analiz
            </button>
            <button wire:click="switchTab('history')" class="py-3 px-1 text-sm font-medium border-b-2 transition-colors whitespace-nowrap {{ $activeTab === 'history' ? 'border-amber-600 text-amber-700' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                📋 Geçmiş Raporlar
                @if($this->reports->count() > 0)
                    <span class="ml-1.5 px-2 py-0.5 text-xs bg-gray-200 text-gray-700 rounded-full">{{ $this->reports->count() }}</span>
                @endif
            </button>
        </nav>
    </div>

    {{-- ================================================ --}}
    {{-- TAB: YENİ ANALİZ --}}
    {{-- ================================================ --}}
    @if($activeTab === 'analyze')

        @if($step === 1)
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 lg:gap-6">
            {{-- Sol: Ürün Veritabanı --}}
            <div class="bg-gradient-to-br from-amber-50 to-orange-100 rounded-xl p-4 lg:p-6 border border-amber-200">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-lg bg-white shadow flex items-center justify-center flex-shrink-0">
                        <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="font-medium text-gray-900">Ürün Veritabanı</h3>
                        <p class="text-sm text-gray-500 mt-1">Pazaryeri Ürünlerim'deki maliyet verileri</p>
                        <div class="mt-3 space-y-2">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-amber-100 text-amber-800">📦 {{ $this->productCount }} toplam ürün</span>
                            @if($this->costCount > 0)
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">✓ {{ $this->costCount }} maliyeti tanımlı</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Sağ: Excel Yükle --}}
            <div class="bg-gradient-to-br from-emerald-50 to-green-100 rounded-xl p-4 lg:p-6 border border-emerald-200">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-lg bg-white shadow flex items-center justify-center flex-shrink-0">
                        <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="font-medium text-gray-900">Avantajlı Etiketler Excel</h3>
                        <p class="text-sm text-gray-500 mt-1">Trendyol → Avantajlı Ürün Etiketleri dosyası</p>
                        <div class="mt-4">
                            <input type="file" wire:model="excelFile" accept=".xlsx,.xls"
                                class="block w-full text-sm text-gray-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-emerald-100 file:text-emerald-700 hover:file:bg-emerald-200">
                            @if($excelFile)
                            <span class="text-xs text-green-600 mt-1 block">✓ {{ $excelFile->getClientOriginalName() }}</span>
                            @endif
                            @error('excelFile') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>
                        <div class="mt-3">
                            <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Rapor Adı (Opsiyonel)</label>
                            <input type="text" wire:model="reportName" placeholder="Örn: 5 Mart 2026 Etiket Analizi"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 text-base sm:text-sm min-h-[44px]">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if($excelFile)
        <div class="flex justify-center">
            <button wire:click="analyze" wire:loading.attr="disabled"
                class="w-full sm:w-auto px-8 py-4 bg-gradient-to-r from-amber-500 to-orange-500 text-white font-bold rounded-xl hover:from-amber-600 hover:to-orange-600 transition shadow-lg disabled:opacity-50 flex justify-center items-center gap-3 text-lg min-h-[56px]">
                <span wire:loading.remove wire:target="analyze">⭐ Analiz Et</span>
                <span wire:loading wire:target="analyze">
                    <svg class="animate-spin h-6 w-6 inline" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    Analiz ediliyor...
                </span>
            </button>
        </div>
        @endif
        @endif

        @if($step === 2)
        <div class="flex flex-col items-center justify-center py-12 lg:py-20">
            <div class="w-24 h-24 rounded-full border-4 border-amber-200 border-t-amber-600 animate-spin"></div>
            <h2 class="mt-6 text-xl font-bold text-gray-900">Yıldız Analizi Çalışıyor</h2>
            <p class="mt-2 text-sm text-gray-500">3 yıldız seviyesi hesaplanıyor...</p>
        </div>
        @endif

        {{-- ADIM 3: Sonuçlar --}}
        @if($step === 3 && $this->activeReport)
            @php $report = $this->activeReport; @endphp

            {{-- Üst Butonlar --}}
            <div class="flex flex-col sm:flex-row justify-between items-center gap-3">
                <div class="flex gap-2 w-full sm:w-auto">
                    <button wire:click="resetAnalysis" class="flex-1 sm:flex-none px-4 py-3 sm:py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 min-h-[44px]">Yeni Analiz</button>
                    <button x-on:click="$dispatch('openAiPanel', { tab: 'campaign' })" class="flex-1 sm:flex-none px-4 py-3 sm:py-2 bg-indigo-100 text-indigo-700 font-medium rounded-lg hover:bg-indigo-200 border border-indigo-200 flex items-center justify-center gap-1.5 min-h-[44px] transition-colors">
                        🤖 AI Analiz
                    </button>
                    <button x-on:click="$dispatch('openAiPanel', { tab: 'loss' })" class="flex-1 sm:flex-none px-4 py-3 sm:py-2 bg-red-100 text-red-700 font-medium rounded-lg hover:bg-red-200 border border-red-200 flex items-center justify-center gap-1.5 min-h-[44px] transition-colors">
                        🔴 Zarar Denetimi
                    </button>
                </div>

                <div class="flex items-center gap-2 w-full sm:w-auto">
                    @if(count($selectedItems) > 0)
                    <span class="text-sm text-gray-500">{{ count($selectedItems) }} seçili</span>
                    @endif
                    <button wire:click="exportSelected" class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 flex justify-center items-center gap-2 min-h-[44px]">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Excel İndir
                    </button>
                </div>
            </div>

            {{-- KPI Kartlar --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="bg-white rounded-xl p-3 border border-gray-200">
                    <div class="text-gray-500 text-xs">Analiz</div>
                    <div class="text-xl font-bold text-gray-900">{{ $report->total_products }}</div>
                </div>
                <div class="bg-white rounded-xl p-3 border border-green-200">
                    <div class="text-green-500 text-xs">Yıldız Avantajlı</div>
                    <div class="text-xl font-bold text-green-600">{{ $report->opportunity_count }}</div>
                </div>
                <div class="bg-white rounded-xl p-3 border border-amber-200">
                    <div class="text-amber-500 text-xs">Toplam Kâr Potansiyeli</div>
                    <div class="text-xl font-bold {{ $report->total_extra_profit >= 0 ? 'text-green-600' : 'text-red-600' }}">{{ number_format($report->total_extra_profit, 0) }} ₺</div>
                </div>
                <div class="bg-white rounded-xl p-3 border border-gray-200">
                    <div class="text-gray-500 text-xs">Rapor</div>
                    <div class="text-sm font-medium text-gray-700 truncate">{{ $report->name }}</div>
                    <div class="text-xs text-gray-400">{{ $report->created_at->format('d.m.Y H:i') }}</div>
                </div>
            </div>

            {{-- Toplu İşlem --}}
            <div class="flex flex-col sm:flex-row items-center gap-3 bg-gray-50 rounded-lg p-3">
                <button wire:click="selectAllOpportunities" class="w-full sm:w-auto px-3 py-2 text-sm bg-amber-100 text-amber-700 rounded-lg hover:bg-amber-200 min-h-[44px]">✓ Avantajlıları Seç</button>
                <button wire:click="deselectAll" class="w-full sm:w-auto px-3 py-2 text-sm bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 min-h-[44px]">Seçimi Temizle</button>
                @if(count($selectedItems) > 0)
                <span class="text-sm font-medium text-amber-600">🛒 {{ count($selectedItems) }} ürün seçildi</span>
                @endif
            </div>

            {{-- AI Panel --}}
            @include('livewire.partials.campaign-ai-panel', ['themeColor' => 'amber'])

            {{-- TABLO --}}
            <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                {{-- Desktop --}}
                <div class="hidden lg:block overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="bg-gray-800 text-white">
                                <th class="px-2 py-3 text-center align-middle" rowspan="2" style="min-width:50px"><span class="text-[10px]">Seç</span></th>
                                <th class="px-3 py-3 text-left align-middle" rowspan="2" style="min-width:160px; max-width:220px">Ürün</th>
                                <th class="px-2 py-3 text-center align-middle" rowspan="2" style="min-width:60px"><span class="text-[10px]">Maliyet</span></th>
                                <th class="px-2 py-1.5 text-center bg-gray-600 border-l border-r border-gray-500"><span class="text-[10px]">MEVCUT</span></th>
                                <th class="px-2 py-1.5 text-center bg-amber-600"><span class="text-[10px]">⭐ 1 YILDIZ</span></th>
                                <th class="px-2 py-1.5 text-center bg-orange-600"><span class="text-[10px]">⭐⭐ 2 YILDIZ</span></th>
                                <th class="px-2 py-1.5 text-center bg-red-600"><span class="text-[10px]">⭐⭐⭐ 3 YILDIZ</span></th>
                                <th class="px-2 py-3 text-center align-middle bg-emerald-700" rowspan="2" style="min-width:90px"><span class="text-[10px]">Fiyat</span></th>
                            </tr>
                            <tr class="bg-gray-700 text-gray-300">
                                <th class="px-1 py-1 text-center text-[9px] border-l border-r border-gray-500">Fiyat · Kâr · %</th>
                                <th class="px-1 py-1 text-center text-[9px]">Fiyat · Kâr · %</th>
                                <th class="px-1 py-1 text-center text-[9px]">Fiyat · Kâr · %</th>
                                <th class="px-1 py-1 text-center text-[9px]">Fiyat · Kâr · %</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($report->items as $item)
                            @php
                                $scenarios = $item->scenario_details;
                                $totalCost = $item->totalCost();
                                $selectedIdx = $item->selected_tariff_index;
                                $starLabels = ['Mevcut', '⭐1', '⭐⭐2', '⭐⭐⭐3'];
                                $starColors = [
                                    'border-gray-400 bg-gray-50/30',
                                    'border-amber-400 bg-amber-50/30',
                                    'border-orange-400 bg-orange-50/30',
                                    'border-red-400 bg-red-50/30',
                                ];
                            @endphp
                            <tr wire:key="badge-item-{{ $item->id }}" class="hover:bg-gray-50 {{ $item->action === 'warning' ? 'bg-red-50/50' : '' }}">
                                {{-- Seç --}}
                                <td class="px-1 py-2 text-center align-middle">
                                    <div class="flex flex-col gap-0.5">
                                        @foreach($starLabels as $idx => $label)
                                            @if(isset($scenarios[$idx]))
                                            <button wire:click="selectTariff({{ $item->id }}, {{ $idx }})"
                                                class="px-1 py-0.5 rounded text-[8px] font-bold transition-all {{ $selectedIdx === $idx ? $starColors[$idx] . ' ring-2 text-gray-900' : 'bg-gray-100 text-gray-400 hover:bg-amber-50 hover:text-amber-600' }}">
                                                {{ $label }}
                                            </button>
                                            @endif
                                        @endforeach
                                    </div>
                                </td>

                                {{-- Ürün --}}
                                <td class="px-3 py-2 align-top" style="max-width:220px">
                                    <p class="font-medium text-gray-900 text-xs leading-tight break-words">{{ $item->product_name ?: $item->stock_code }}</p>
                                    <p class="text-[10px] text-amber-500 font-mono mt-0.5">{{ $item->stock_code }}</p>
                                    @if($item->action === 'update')
                                    <span class="text-[9px] bg-green-100 text-green-700 px-1 py-0.5 rounded font-bold mt-0.5 inline-block">⭐ AVANTAJLI</span>
                                    @elseif($item->action === 'warning')
                                    <span class="text-[9px] bg-red-100 text-red-600 px-1 py-0.5 rounded font-bold mt-0.5 inline-block">⚠ ZARAR</span>
                                    @endif
                                </td>

                                {{-- Maliyet --}}
                                <td class="px-2 py-2 text-center align-middle border-r border-gray-100">
                                    <div class="text-xs font-bold text-gray-800">{{ number_format($totalCost, 0) }}₺</div>
                                </td>

                                {{-- 4 Senaryo --}}
                                @foreach([0, 1, 2, 3] as $idx)
                                @php
                                    $sc = $scenarios[$idx] ?? null;
                                    $isSelected = ($selectedIdx === $idx);
                                    $isBest = $sc && ($sc['is_best'] ?? false);
                                    $hasTariffSelected = ($selectedIdx !== null);
                                    $cellClass = $isSelected
                                        ? 'border-2 ' . $starColors[$idx] . ' ring-1 ring-amber-200'
                                        : ($hasTariffSelected ? 'border-gray-100 bg-gray-50/50' : ($isBest ? 'bg-amber-50/80 border border-amber-200' : 'border-gray-100'));
                                @endphp
                                <td class="px-1.5 py-2 text-center align-middle {{ $cellClass }} transition-all cursor-pointer"
                                    wire:click="selectTariff({{ $item->id }}, {{ $idx }})">
                                    @if($sc && $sc['price'] > 0)
                                    <div class="space-y-0.5">
                                        <div class="font-bold text-gray-900">{{ number_format($sc['price'], 0) }}₺</div>
                                        <div class="font-bold text-xs {{ $sc['net_profit'] >= 0 ? 'text-green-600' : 'text-red-600' }}">{{ number_format($sc['net_profit'], 0) }}₺</div>
                                        <div class="text-[9px] {{ $sc['margin_pct'] >= 0 ? 'text-blue-500' : 'text-red-400' }}">%{{ number_format($sc['margin_pct'], 1) }}</div>
                                        @if($isSelected)
                                        <div class="text-[7px] bg-amber-500 text-white px-1 py-0.5 rounded font-bold inline-block">SEÇİLDİ</div>
                                        @elseif($isBest && !$hasTariffSelected)
                                        <div class="text-[7px] bg-green-500 text-white px-1 py-0.5 rounded font-bold inline-block">EN İYİ</div>
                                        @endif
                                    </div>
                                    @else
                                    <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                                @endforeach

                                {{-- Fiyat Güncelle & AI Öneri --}}
                                <td class="px-2 py-2 text-center align-middle" x-data="{ editing: false }">
                                    @php $dp = $item->custom_price ?: $item->current_price; @endphp
                                    <div class="flex flex-col items-center gap-1 relative">
                                        <div class="relative w-full">
                                            <input type="number" x-ref="bp{{ $item->id }}" value="{{ number_format($dp, 2, '.', '') }}"
                                                @focus="editing = true" @blur="setTimeout(() => editing = false, 200)"
                                                class="w-full text-xs text-center border rounded-md px-1 py-1.5 font-bold focus:ring-2 focus:ring-amber-400 pr-6 {{ $item->custom_price ? 'bg-amber-50 border-amber-300' : 'border-gray-300' }}" step="0.01" min="0">
                                            
                                            {{-- AI Fiyat Önerisi İkonu --}}
                                            <button wire:click.prevent="getAiPriceSuggestion({{ $item->id }})" 
                                                class="absolute right-1.5 top-1/2 -translate-y-1/2 text-[10px] hover:scale-125 transition-transform" 
                                                title="AI Fiyat Önerisi İste">
                                                @if(isset($this->suggestedPrices[$item->id]['loading']) && $this->suggestedPrices[$item->id]['loading'])
                                                    <span class="inline-block animate-spin">⏳</span>
                                                @else
                                                    ✨
                                                @endif
                                            </button>
                                        </div>

                                        {{-- Kaydet Butonu (Sadece odaklandığında) --}}
                                        <button x-show="editing" x-transition x-cloak x-on:click="$wire.updateCustomPrice({{ $item->id }}, $refs.bp{{ $item->id }}.value)"
                                            class="bg-amber-500 hover:bg-amber-600 text-white text-[9px] font-bold px-3 py-1 rounded transition-colors w-full absolute -bottom-6 z-10 shadow-lg">Save</button>

                                        {{-- Durum İkonları --}}
                                        <div class="flex items-center gap-1 justify-center mt-0.5">
                                            @if($item->custom_price)
                                                <span class="text-[8px] text-emerald-600 font-medium" title="Özel fiyat belirlendi">✅</span>
                                            @endif
                                            @if(isset($this->suggestedPrices[$item->id]['error']))
                                                <span class="text-[8px] text-red-500 font-medium" title="{{ $this->suggestedPrices[$item->id]['error'] }}">❌ Hatası</span>
                                            @endif
                                        </div>

                                        {{-- AI Öneri Pop-up --}}
                                        @if(isset($this->suggestedPrices[$item->id]['price']))
                                            <div class="absolute right-full top-0 mr-2 w-52 bg-white border border-amber-200 rounded-lg shadow-2xl z-50 p-2.5 text-left animate-in fade-in slide-in-from-right-2">
                                                <div class="text-[9px] font-bold text-amber-700 mb-1 border-b pb-1">🤖 AI Fiyat Önerisi: {{ number_format($this->suggestedPrices[$item->id]['price'], 2) }} ₺</div>
                                                <div class="text-[8px] text-gray-600 leading-relaxed">{{ $this->suggestedPrices[$item->id]['reason'] }}</div>
                                                <div class="flex gap-1 mt-1.5 pt-1.5 border-t">
                                                    <button wire:click.prevent="applySuggestedPrice({{ $item->id }})" class="flex-1 bg-amber-50 text-amber-700 hover:bg-amber-100 text-[8px] py-1 rounded font-bold transition">Uygula</button>
                                                    <button wire:click.prevent="clearAiSuggestion({{ $item->id }})" class="flex-1 bg-gray-50 text-gray-500 hover:bg-gray-100 text-[8px] py-1 rounded font-bold transition">Kapat</button>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="8" class="px-4 py-8 text-center text-gray-500">Sonuç bulunamadı</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Mobil --}}
                <div class="lg:hidden divide-y divide-gray-200">
                    @forelse($report->items as $item)
                    @php
                        $scenarios = $item->scenario_details;
                        $totalCost = $item->totalCost();
                        $selectedIdx = $item->selected_tariff_index;
                        $starLabels = ['Mevcut', '⭐1', '⭐⭐2', '⭐⭐⭐3'];
                    @endphp
                    <div wire:key="badge-mobile-{{ $item->id }}" class="p-4 {{ $item->action === 'warning' ? 'bg-red-50/50' : '' }}">
                        <div class="flex items-start justify-between mb-2">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-gray-900 line-clamp-2">{{ $item->product_name ?: $item->stock_code }}</p>
                                <p class="text-[10px] text-amber-500 font-mono">{{ $item->stock_code }}</p>
                            </div>
                            @if($item->action === 'update')
                            <span class="text-[9px] bg-green-100 text-green-700 px-1.5 py-0.5 rounded font-bold ml-2">⭐</span>
                            @elseif($item->action === 'warning')
                            <span class="text-[9px] bg-red-100 text-red-600 px-1.5 py-0.5 rounded font-bold ml-2">⚠</span>
                            @endif
                        </div>
                        <div class="text-[10px] text-gray-400 text-center mb-2">Maliyet: <span class="font-medium text-gray-700">{{ number_format($totalCost, 0) }}₺</span></div>

                        {{-- 4 Senaryo Grid --}}
                        <div class="grid grid-cols-2 gap-1.5">
                            @foreach($scenarios as $idx => $sc)
                            @php
                                $isSelected = ($selectedIdx === $idx);
                                $isBest = ($sc['is_best'] ?? false);
                                $hasTariffSelected = ($selectedIdx !== null);
                            @endphp
                            @if($sc['price'] > 0)
                            <div wire:click="selectTariff({{ $item->id }}, {{ $idx }})"
                                class="rounded-lg p-2 text-center cursor-pointer transition-all {{ $isSelected ? 'bg-amber-50 border-2 border-amber-400 ring-2 ring-amber-200 shadow-md' : ($isBest && !$hasTariffSelected ? 'bg-amber-50 border-2 border-amber-300' : 'bg-gray-50 border border-gray-200 hover:border-amber-200') }}">
                                <div class="text-[9px] font-bold text-amber-600 mb-0.5">{{ $starLabels[$idx] ?? '' }}</div>
                                <div class="text-xs font-bold text-gray-900">{{ number_format($sc['price'], 0) }}₺</div>
                                <div class="text-xs font-bold mt-0.5 {{ $sc['net_profit'] >= 0 ? 'text-green-600' : 'text-red-500' }}">{{ number_format($sc['net_profit'], 0) }}₺</div>
                                <div class="text-[8px] {{ $sc['margin_pct'] >= 0 ? 'text-blue-500' : 'text-red-400' }}">%{{ number_format($sc['margin_pct'], 1) }}</div>
                                @if($isSelected)
                                <div class="text-[7px] bg-amber-500 text-white px-1 py-0.5 rounded font-bold mt-0.5 inline-block">SEÇİLDİ</div>
                                @elseif($isBest && !$hasTariffSelected)
                                <div class="text-[7px] bg-green-500 text-white px-1 py-0.5 rounded font-bold mt-0.5 inline-block">EN İYİ</div>
                                @endif
                            </div>
                            @endif
                            @endforeach
                        </div>

                        {{-- Fiyat ve AI Öneri --}}
                        <div x-data class="mt-3 pt-3 border-t border-gray-100 relative">
                            <div class="flex items-center gap-2">
                                @php $dp = $item->custom_price ?: $item->current_price; @endphp
                                <div class="relative flex-1">
                                    <input type="number" x-ref="mbp{{ $item->id }}" value="{{ number_format($dp, 2, '.', '') }}"
                                        class="w-full text-xs text-center border rounded-md px-2 py-1.5 font-bold {{ $item->custom_price ? 'bg-amber-50 border-amber-300' : 'border-gray-300' }} pr-8" step="0.01" min="0">
                                    <button wire:click.prevent="getAiPriceSuggestion({{ $item->id }})" 
                                        class="absolute right-2 top-1/2 -translate-y-1/2 text-sm hover:scale-125 transition-transform">
                                        @if(isset($this->suggestedPrices[$item->id]['loading']) && $this->suggestedPrices[$item->id]['loading'])
                                            <span class="inline-block animate-spin text-xs">⏳</span>
                                        @else
                                            ✨
                                        @endif
                                    </button>
                                </div>
                                <button x-on:click="$wire.updateCustomPrice({{ $item->id }}, $refs.mbp{{ $item->id }}.value)"
                                    class="bg-amber-500 text-white text-xs font-bold px-4 py-1.5 rounded-md self-stretch hover:bg-amber-600 transition">Kaydet</button>
                            </div>

                            @if(isset($this->suggestedPrices[$item->id]['price']))
                                <div class="mt-2 text-[10px] bg-amber-50 text-amber-800 p-2 rounded-lg border border-amber-100">
                                    <div class="font-bold mb-1 flex justify-between items-center">
                                        <span>🤖 Öneri: {{ number_format($this->suggestedPrices[$item->id]['price'], 2) }} ₺</span>
                                        <button wire:click.prevent="clearAiSuggestion({{ $item->id }})" class="text-gray-400 hover:text-gray-600">&times;</button>
                                    </div>
                                    <p class="mb-2 leading-tight opacity-90">{{ $this->suggestedPrices[$item->id]['reason'] }}</p>
                                    <button wire:click.prevent="applySuggestedPrice({{ $item->id }})" class="w-full bg-amber-100 hover:bg-amber-200 text-amber-700 font-bold py-1.5 rounded transition">Uygula</button>
                                </div>
                            @endif
                        </div>
                    </div>
                    @empty
                    <div class="p-8 text-center text-gray-500">Sonuç bulunamadı</div>
                    @endforelse
                </div>
            </div>
        @endif

    @endif

    {{-- ================================================ --}}
    {{-- TAB: GEÇMİŞ RAPORLAR --}}
    {{-- ================================================ --}}
    @if($activeTab === 'history')
    <div class="space-y-3">
        @forelse($this->reports as $rpt)
        <div class="bg-white rounded-xl border border-gray-200 p-4 hover:shadow-md transition-shadow cursor-pointer flex flex-col sm:flex-row sm:items-center justify-between gap-3"
             wire:click="viewReport({{ $rpt->id }})">
            <div>
                <p class="font-medium text-gray-900">{{ $rpt->name }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ $rpt->created_at->format('d.m.Y H:i') }} · {{ $rpt->original_filename }}</p>
                <div class="flex flex-wrap gap-2 mt-2">
                    <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded">{{ $rpt->total_products }} ürün</span>
                    <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded">{{ $rpt->opportunity_count }} avantajlı</span>
                    <span class="text-xs {{ $rpt->total_extra_profit >= 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }} px-2 py-0.5 rounded">
                        {{ $rpt->total_extra_profit >= 0 ? '+' : '' }}{{ number_format($rpt->total_extra_profit, 0) }} ₺
                    </span>
                </div>
            </div>
            <div class="flex gap-2">
                <button wire:click.stop="viewReport({{ $rpt->id }})" class="px-3 py-2 text-sm bg-amber-100 text-amber-700 rounded-lg hover:bg-amber-200 min-h-[44px]">Görüntüle</button>
                <button wire:click.stop="deleteReport({{ $rpt->id }})" class="px-3 py-2 text-sm bg-red-100 text-red-700 rounded-lg hover:bg-red-200 min-h-[44px]"
                    onclick="return confirm('Silmek istediğinize emin misiniz?')">Sil</button>
            </div>
        </div>
        @empty
        <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
            <p class="text-gray-500">Henüz etiket analiz raporu yok.</p>
            <button wire:click="switchTab('analyze')" class="mt-3 px-4 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700 text-sm">İlk Analizi Yap</button>
        </div>
        @endforelse
    </div>
    @endif
</div>
