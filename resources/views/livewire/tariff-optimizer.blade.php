<div class="space-y-6 overflow-x-hidden">
    {{-- Başlık --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl lg:text-2xl font-bold text-gray-900">📊 Kâr Motoru</h1>
            <p class="text-sm lg:text-base text-gray-500 mt-1">Tarife optimizasyonu ile net kârınızı maksimize edin</p>
        </div>
    </div>

    {{-- Mesaj --}}
    @if($message)
        <div class="rounded-lg p-4 {{ 
            $messageType === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 
            ($messageType === 'error' ? 'bg-red-50 text-red-800 border border-red-200' : 
            'bg-blue-50 text-blue-800 border border-blue-200') 
        }}">
            {{ $message }}
        </div>
    @endif

    {{-- Tab Navigation --}}
    <div class="border-b border-gray-200">
        <nav class="flex gap-4 sm:gap-8">
            <button wire:click="switchTab('analyze')"
                class="py-3 px-1 text-sm font-medium border-b-2 transition-colors whitespace-nowrap
                    {{ $activeTab === 'analyze' ? 'border-gray-900 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                🔬 Yeni Analiz
            </button>
            <button wire:click="switchTab('history')"
                class="py-3 px-1 text-sm font-medium border-b-2 transition-colors whitespace-nowrap
                    {{ $activeTab === 'history' ? 'border-gray-900 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                📋 Geçmiş Raporlar
                @if($this->reports->count() > 0)
                    <span class="ml-1.5 px-2 py-0.5 text-xs bg-gray-200 text-gray-700 rounded-full">{{ $this->reports->count() }}</span>
                @endif
            </button>
        </nav>
    </div>

    {{-- ===================================================== --}}
    {{-- TAB 1: YENİ ANALİZ --}}
    {{-- ===================================================== --}}
    @if($activeTab === 'analyze')

        {{-- ADIM 1: Setup & Yükleme --}}
        @if($step === 1)
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 lg:gap-6">
                {{-- Sol: Maliyet Veritabanı Durumu --}}
                <div class="bg-gradient-to-br from-blue-50 to-indigo-100 rounded-xl p-4 lg:p-6 border border-blue-200">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 rounded-lg bg-white shadow flex items-center justify-center flex-shrink-0">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="font-medium text-gray-900">Maliyet Veritabanı</h3>
                            <p class="text-sm text-gray-500 mt-1">Üretim ve kargo maliyetleriniz</p>
                            <div class="mt-3">
                                @if($this->costCount > 0)
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                        ✓ {{ $this->costCount }} ürünün maliyeti kayıtlı
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                                        ⚠ Henüz maliyet yüklenmedi
                                    </span>
                                @endif
                            </div>
                            <div class="mt-4">
                                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Maliyet dosyası yükle</label>
                                <input 
                                    type="file" 
                                    wire:model="costFile"
                                    accept=".xlsx,.xls"
                                    class="block w-full text-sm text-gray-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-100 file:text-blue-700 hover:file:bg-blue-200"
                                >
                                @if($costFile)
                                    <span class="text-xs text-green-600 mt-1 block">✓ {{ $costFile->getClientOriginalName() }}</span>
                                @endif
                                @error('costFile') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                            </div>
                            @if($costFile)
                                <button 
                                    wire:click="importCosts"
                                    wire:loading.attr="disabled"
                                    class="mt-3 w-full sm:w-auto px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition flex justify-center items-center gap-2 min-h-[44px]"
                                >
                                    <span wire:loading.remove wire:target="importCosts">Maliyetleri Güncelle</span>
                                    <span wire:loading wire:target="importCosts">
                                        <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        Yükleniyor...
                                    </span>
                                </button>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Sağ: Tarife Excel Yükleme --}}
                <div class="bg-gradient-to-br from-emerald-50 to-green-100 rounded-xl p-4 lg:p-6 border border-emerald-200">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 rounded-lg bg-white shadow flex items-center justify-center flex-shrink-0">
                            <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="font-medium text-gray-900">Haftalık Tarife Analizi</h3>
                            <p class="text-sm text-gray-500 mt-1">Trendyol komisyon tarife Excel dosyasını yükleyin</p>
                            <div class="mt-4">
                                <input 
                                    type="file" 
                                    wire:model="tariffFile"
                                    accept=".xlsx,.xls"
                                    class="block w-full text-sm text-gray-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-emerald-100 file:text-emerald-700 hover:file:bg-emerald-200"
                                >
                                @if($tariffFile)
                                    <span class="text-xs text-green-600 mt-1 block">✓ {{ $tariffFile->getClientOriginalName() }}</span>
                                @endif
                                @error('tariffFile') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                            </div>
                            <div class="mt-3">
                                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Rapor Adı (Opsiyonel)</label>
                                <input 
                                    type="text" 
                                    wire:model="reportName"
                                    placeholder="Örn: 11 Şubat 2026 Analizi"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 text-base sm:text-sm min-h-[44px]"
                                >
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Maliyet uyarısı --}}
            @if($this->costCount === 0)
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-sm text-yellow-700">
                    <div class="flex items-start gap-2">
                        <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                        </svg>
                        <span>Maliyet veritabanı boş. Önce sol panelden maliyet dosyanızı (fiyatli_maliyet_eklenmis.xlsx) yükleyin. Aksi halde motor maliyetleri 0 kabul ederek çalışır.</span>
                    </div>
                </div>
            @endif

            {{-- Motoru Çalıştır Butonu --}}
            @if($tariffFile)
                <div class="flex justify-center">
                    <button 
                        wire:click="analyze"
                        wire:loading.attr="disabled"
                        class="w-full sm:w-auto px-8 py-4 bg-gradient-to-r from-emerald-600 to-green-600 text-white font-bold rounded-xl hover:from-emerald-700 hover:to-green-700 transition shadow-lg disabled:opacity-50 flex justify-center items-center gap-3 text-lg min-h-[56px]"
                    >
                        <span wire:loading.remove wire:target="analyze">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                        </span>
                        <span wire:loading wire:target="analyze">
                            <svg class="animate-spin h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </span>
                        <span wire:loading.remove wire:target="analyze">Motoru Çalıştır</span>
                        <span wire:loading wire:target="analyze">Analiz ediliyor...</span>
                    </button>
                </div>
            @endif
        @endif

        {{-- ADIM 2: İşlem Animasyonu --}}
        @if($step === 2)
            <div class="flex flex-col items-center justify-center py-12 lg:py-20">
                <div class="relative">
                    <div class="w-24 h-24 rounded-full border-4 border-emerald-200 border-t-emerald-600 animate-spin"></div>
                    <div class="absolute inset-0 flex items-center justify-center">
                        <svg class="w-10 h-10 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </div>
                </div>
                <h2 class="mt-6 text-xl lg:text-2xl font-bold text-gray-900">Kâr Motoru Çalışıyor</h2>
                <div class="mt-4 space-y-2 text-sm lg:text-base text-gray-500 text-center" x-data="{ step: 0 }" x-init="setInterval(() => step = (step + 1) % 4, 1500)">
                    <p x-show="step === 0" x-transition>📊 Tarife verileri okunuyor...</p>
                    <p x-show="step === 1" x-transition>🔗 Maliyetler eşleştiriliyor...</p>
                    <p x-show="step === 2" x-transition>🔍 Komisyon tuzakları taranıyor...</p>
                    <p x-show="step === 3" x-transition>💰 Kârlılık hesaplanıyor...</p>
                </div>
            </div>
        @endif

        {{-- ADIM 3: Sonuç Raporu --}}
        @if($step === 3 && $this->activeReport)
            @php $report = $this->activeReport; @endphp



            {{-- Üst Butonlar --}}
            <div class="flex flex-col sm:flex-row justify-between items-center gap-3">
                <button wire:click="resetAnalysis"
                    class="w-full sm:w-auto px-4 py-3 sm:py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 flex justify-center items-center gap-2 min-h-[44px]">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Yeni Analiz
                </button>
                <div class="flex items-center gap-2 w-full sm:w-auto">
                    @if(count($selectedItems) > 0)
                        <span class="text-sm text-gray-500">{{ count($selectedItems) }} ürün seçili</span>
                    @endif
                    <button wire:click="exportSelected"
                        class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 flex justify-center items-center gap-2 min-h-[44px]">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Düzeltme Dosyasını İndir
                    </button>
                </div>
            </div>

            {{-- KPI Kartları --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4">
                <div class="bg-white rounded-xl p-3 sm:p-4 border border-gray-200 shadow-sm">
                    <div class="text-gray-500 text-xs sm:text-sm">Analiz Edilen</div>
                    <div class="text-xl sm:text-2xl font-bold text-gray-900">{{ number_format($report->total_products) }}</div>
                    <div class="text-xs text-gray-400 mt-1">ürün</div>
                </div>
                <div class="bg-white rounded-xl p-3 sm:p-4 border border-emerald-200 shadow-sm">
                    <div class="text-emerald-500 text-xs sm:text-sm">Fırsat Bulunan</div>
                    <div class="text-xl sm:text-2xl font-bold text-emerald-600">{{ number_format($report->opportunity_count) }}</div>
                    <div class="text-xs text-emerald-400 mt-1">ürün</div>
                </div>
                <div class="bg-white rounded-xl p-3 sm:p-4 border border-green-200 shadow-sm">
                    <div class="text-green-500 text-xs sm:text-sm">Toplam Ek Kâr</div>
                    <div class="text-xl sm:text-2xl font-bold text-green-600">+{{ number_format($report->total_extra_profit, 2) }} ₺</div>
                    <div class="text-xs text-green-400 mt-1">birim başına potansiyel</div>
                </div>
                <div class="bg-white rounded-xl p-3 sm:p-4 border border-yellow-200 shadow-sm">
                    <div class="text-yellow-500 text-xs sm:text-sm">Eşleşmeyen</div>
                    <div class="text-xl sm:text-2xl font-bold text-yellow-600">{{ number_format($report->unmatched_count) }}</div>
                    <div class="text-xs text-yellow-400 mt-1">maliyetsiz ürün</div>
                </div>
            </div>

            {{-- Toplu İşlem Alanı --}}
            <div class="flex flex-col sm:flex-row items-center gap-3 bg-gray-50 rounded-lg p-3 lg:p-4">
                <button wire:click="selectAllOpportunities" class="w-full sm:w-auto px-3 py-2 text-sm bg-emerald-100 text-emerald-700 rounded-lg hover:bg-emerald-200 min-h-[44px]">
                    ✓ Tüm Fırsatları Seç
                </button>
                <button wire:click="deselectAll" class="w-full sm:w-auto px-3 py-2 text-sm bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 min-h-[44px]">
                    Seçimi Temizle
                </button>
                @if(count($selectedItems) > 0)
                    <span class="text-sm font-medium text-orange-600">
                        🛒 {{ count($selectedItems) }} ürün seçildi
                    </span>
                @endif
                <div class="text-sm text-gray-500 sm:ml-auto">
                    {{ $report->original_filename }} · {{ $report->created_at->format('d.m.Y H:i') }}
                </div>
            </div>

            {{-- ═════════════════════════════════════════════════════════ --}}
            {{-- KARLILIK FİLTRESİ --}}
            {{-- ═════════════════════════════════════════════════════════ --}}
            <div class="bg-white rounded-lg border border-gray-200 p-3 mb-3" x-data="{ showCustom: false }">
                {{-- Satır 1: Hedef Tarife Seçimi --}}
                <div class="flex flex-wrap items-center gap-2 mb-2">
                    <span class="text-xs font-semibold text-gray-600 mr-1">📋 Hedef Tarife:</span>
                    @foreach([0 => '1. Tarife', 1 => '2. Tarife', 2 => '3. Tarife', 3 => '4. Tarife'] as $tIdx => $tName)
                        <button wire:click="setProfitabilityFilter({{ $tIdx }}, {{ $profitabilityMin ?? 0 }}, {{ $profitabilityMax ?? 999 }})"
                            class="px-3 py-1.5 text-[11px] font-semibold rounded-lg border-2 transition-all {{ $profitabilityTariffIndex === $tIdx ? ($tIdx === 0 ? 'bg-orange-500 text-white border-orange-500 shadow-md' : 'bg-indigo-600 text-white border-indigo-600 shadow-md') : 'bg-white text-gray-600 border-gray-200 hover:border-indigo-300 hover:text-indigo-600' }}">
                            {{ $tName }}
                        </button>
                    @endforeach

                    @if($profitabilityTariffIndex !== null)
                        <button wire:click="clearProfitabilityFilter" class="px-2 py-1 text-[11px] text-red-500 hover:text-red-700 font-medium ml-1">
                            ✕ Temizle
                        </button>
                    @endif
                </div>

                {{-- Satır 2: Karlılık Aralığı (tarife seçildiyse göster) --}}
                @if($profitabilityTariffIndex !== null)
                    <div class="flex flex-wrap items-center gap-1.5 pt-2 border-t border-gray-100">
                        <span class="text-xs font-semibold text-gray-500 mr-1">🎯 Karlılık %:</span>
                        @php
                            $ranges = [[0,5],[5,10],[10,15],[15,20],[20,25],[25,30],[30,null]];
                        @endphp
                        @foreach($ranges as [$rMin, $rMax])
                            <button wire:click="setProfitabilityFilter({{ $profitabilityTariffIndex }}, {{ $rMin }}, {{ $rMax ?? 'null' }})"
                                class="px-2 py-1 text-[10px] font-medium rounded-full border transition-all {{ $profitabilityMin == $rMin && ($profitabilityMax == $rMax || ($rMax === null && $profitabilityMax == 999)) ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm' : 'bg-white text-gray-500 border-gray-200 hover:border-emerald-400 hover:text-emerald-600' }}">
                                {{ $rMax !== null ? "%{$rMin}-{$rMax}" : "%{$rMin}+" }}
                            </button>
                        @endforeach

                        {{-- Özel Aralık Toggle --}}
                        <button x-on:click="showCustom = !showCustom"
                            class="px-2 py-1 text-[10px] font-medium rounded-full border transition-all bg-white text-gray-500 border-gray-200 hover:border-emerald-400">
                            ⚙️ Özel
                        </button>
                    </div>

                    {{-- Özel Aralık Input --}}
                    <div x-show="showCustom" x-transition class="flex items-center gap-2 mt-2 pt-2 border-t border-gray-100">
                        <span class="text-xs text-gray-500">Min %</span>
                        <input type="number" x-ref="customMin" value="{{ $profitabilityMin ?? 0 }}" class="w-16 text-xs text-center border border-gray-300 rounded px-1 py-1" min="0" max="100" step="1">
                        <span class="text-xs text-gray-400">—</span>
                        <span class="text-xs text-gray-500">Max %</span>
                        <input type="number" x-ref="customMax" value="{{ $profitabilityMax != 999 ? $profitabilityMax : 100 }}" class="w-16 text-xs text-center border border-gray-300 rounded px-1 py-1" min="0" max="100" step="1">
                        <button x-on:click="$wire.setProfitabilityFilter({{ $profitabilityTariffIndex }}, $refs.customMin.value, $refs.customMax.value)"
                            class="px-3 py-1 text-xs bg-emerald-600 text-white rounded hover:bg-emerald-700 font-medium">Filtrele</button>
                    </div>
                @endif
            </div>

            {{-- TABS: ÜRÜN BAZLI / KATEGORİ BAZLI --}}
            <div class="flex gap-2 mb-4 border-b border-gray-200">
                <button wire:click="$set('tabView', 'products')" 
                    class="px-4 py-2 text-sm font-medium transition-colors border-b-2 {{ $tabView === 'products' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                    📦 Ürün Bazlı
                </button>
                <button wire:click="$set('tabView', 'categories')" 
                    class="px-4 py-2 text-sm font-medium transition-colors border-b-2 {{ $tabView === 'categories' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                    📊 Kategori Analizi
                </button>
            </div>

            {{-- ═════════════════════════════════════════════════════════ --}}
            {{-- TRENDYOL-STİL RATE CARD TABLOSU --}}
            {{-- ═════════════════════════════════════════════════════════ --}}
            @if($tabView === 'products')
            <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">

                {{-- Desktop Tablo --}}
                <div class="hidden xl:block overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="bg-gray-800 text-white">
                                <th class="px-2 py-3 text-center align-middle" rowspan="2" style="min-width:60px">
                                    <span class="text-[10px]">Tarife<br>Seç</span>
                                </th>
                                <th class="px-3 py-3 text-left align-middle" rowspan="2" style="min-width:180px; max-width:250px">
                                    Ürün Bilgileri
                                </th>
                                <th class="px-2 py-3 text-center align-middle" rowspan="2" style="min-width:80px">
                                    <div class="text-[10px] leading-tight">Kargo M</div>
                                    <div class="text-[10px] leading-tight text-gray-400">Üretim M</div>
                                </th>
                                <th class="px-2 py-1.5 text-center bg-orange-600 text-white font-bold border-l border-orange-700 border-r border-orange-700" colspan="1">
                                    <span class="text-[10px]">1. Tarife</span>
                                </th>
                                <th class="px-2 py-1.5 text-center bg-gray-700 font-medium border-r border-gray-600" colspan="1">
                                    <span class="text-[10px]">2. Tarife</span>
                                </th>
                                <th class="px-2 py-1.5 text-center bg-gray-700 font-medium border-r border-gray-600" colspan="1">
                                    <span class="text-[10px]">3. Tarife</span>
                                </th>
                                <th class="px-2 py-1.5 text-center bg-gray-700 font-medium border-r border-gray-600" colspan="1">
                                    <span class="text-[10px]">4. Tarife</span>
                                </th>
                                <th class="px-2 py-3 text-center align-middle bg-emerald-700" rowspan="2" style="min-width:120px">
                                    <span class="text-[10px]">Fiyat Güncelle</span>
                                </th>
                            </tr>
                            <tr class="bg-gray-700 text-gray-300">
                                <th class="px-1 py-1 text-center text-[9px] bg-orange-500/20 border-l border-r border-orange-700">Fiyat · Kom · Kâr</th>
                                <th class="px-1 py-1 text-center text-[9px] border-r border-gray-600">Fiyat · Kom · Kâr</th>
                                <th class="px-1 py-1 text-center text-[9px] border-r border-gray-600">Fiyat · Kom · Kâr</th>
                                <th class="px-1 py-1 text-center text-[9px] border-r border-gray-600">Fiyat · Kom · Kâr</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($report->items as $item)
                                @php
                                    $scenarios = $item->scenario_details;
                                    $sc1 = $scenarios[0] ?? null;
                                    $sc2 = $scenarios[1] ?? null;
                                    $sc3 = $scenarios[2] ?? null;
                                    $sc4 = $scenarios[3] ?? null;
                                    $totalCost = $item->production_cost + $item->shipping_cost;
                                    $selectedIdx = $item->selected_tariff_index;

                                    // Karlılık filtresi: seçilen tarifenin karlılığına göre filtrele
                                    if ($profitabilityTariffIndex !== null && $profitabilityMin !== null && $totalCost > 0) {
                                        $targetSc = $scenarios[$profitabilityTariffIndex] ?? null;
                                        if ($targetSc) {
                                            $profitPct = ($targetSc['net_profit'] / $totalCost) * 100;
                                            if ($profitPct < $profitabilityMin || $profitPct > $profitabilityMax) continue;
                                        } else {
                                            continue; // Bu ürünün hedef tarifesi yok
                                        }
                                    }
                                @endphp
                                <tr wire:key="desktop-item-{{ $item->id }}" class="hover:bg-gray-50 group transition {{ $item->action === 'warning' ? 'bg-red-50/50' : '' }}">
                                    {{-- Tarife Seç Butonları --}}
                                    <td class="px-1 py-2 text-center align-middle">
                                        <div class="flex flex-col gap-0.5 items-center">
                                            @for($ti = 0; $ti < 4; $ti++)
                                                @if(isset($scenarios[$ti]))
                                                    <button 
                                                        wire:click="selectTariff({{ $item->id }}, {{ $ti }})"
                                                        class="w-6 h-5 rounded text-[9px] font-bold transition-all {{ $selectedIdx === $ti ? 'bg-orange-500 text-white ring-2 ring-orange-300 shadow-sm' : 'bg-gray-100 text-gray-500 hover:bg-orange-100 hover:text-orange-600' }}"
                                                        title="{{ ($ti + 1) }}. Tarife Seç">
                                                        {{ $ti + 1 }}
                                                    </button>
                                                @endif
                                            @endfor
                                        </div>
                                    </td>

                                    {{-- Ürün Bilgileri --}}
                                    <td class="px-3 py-3 align-top" style="max-width:250px">
                                        <div class="flex items-start gap-2">
                                            <div class="min-w-0 flex-1">
                                                <p class="font-medium text-gray-900 text-xs leading-tight break-words" title="{{ $item->product_name }}">
                                                    {{ $item->product_name ?: $item->stock_code }}
                                                </p>
                                                <p class="text-[10px] text-indigo-500 font-mono mt-0.5">{{ $item->stock_code }}</p>
                                                @if($item->action === 'update')
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold bg-green-100 text-green-700 mt-1">🚀 FIRSAT</span>
                                                @elseif($item->action === 'warning')
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold bg-red-100 text-red-600 mt-1">⚠ ZARAR</span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>

                                    {{-- Kargo Maliyeti / Üretim Maliyeti --}}
                                    <td class="px-2 py-2 text-center align-middle border-r border-gray-100">
                                        <div class="text-xs font-bold text-gray-800">{{ number_format($item->shipping_cost, 0) }}₺</div>
                                        <div class="text-[10px] text-gray-400">{{ number_format($item->production_cost, 0) }}₺</div>
                                    </td>

                                    {{-- 4 Tarife Hücreleri --}}
                                    @foreach([$sc1, $sc2, $sc3, $sc4] as $idx => $sc)
                                        @php
                                            $isSelected = ($selectedIdx === $idx);
                                            $isBest = $sc && ($sc['is_best'] ?? false);
                                            $hasTariffSelected = ($selectedIdx !== null);
                                            // Seçili tarife: turuncu çerçeve. Başka tarife seçiliyse geri kalanlar gri.
                                            $cellClass = '';
                                            if ($isSelected) {
                                                $cellClass = 'border-2 border-orange-400 bg-orange-50/30';
                                            } elseif ($hasTariffSelected) {
                                                // Başka tarife seçili — bu hücre gri
                                                $cellClass = 'border-gray-100 bg-gray-50/50';
                                            } elseif ($isBest) {
                                                $cellClass = ($idx === 0) ? 'bg-orange-50 border-orange-200' : 'bg-green-50 border-green-200';
                                            } else {
                                                $cellClass = 'border-gray-100';
                                            }
                                        @endphp
                                        <td class="px-2 py-2 text-center align-middle border-r {{ $cellClass }} {{ $idx === 0 ? 'border-l' : '' }} transition-all cursor-pointer"
                                            wire:click="selectTariff({{ $item->id }}, {{ $idx }})"
                                            title="{{ ($idx + 1) }}. Tarife seçmek için tıklayın">
                                            @if($sc)
                                                <div class="space-y-0.5">
                                                    <div class="font-bold text-gray-900">{{ number_format($sc['price'], 0) }}₺</div>
                                                    <div class="{{ $idx === 0 ? 'text-orange-600 font-semibold' : 'text-gray-500 font-medium' }}">%{{ number_format($sc['commission'], 1) }}</div>
                                                    <div class="font-bold text-xs {{ $sc['net_profit'] >= 0 ? 'text-green-600' : 'text-red-600' }} cursor-help" title="Karlılık: %{{ $item->production_cost > 0 ? number_format(($sc['net_profit'] / $item->production_cost) * 100, 1) : '0' }}">
                                                        {{ number_format($sc['net_profit'], 0) }}₺
                                                    </div>
                                                    @if($isSelected)
                                                        <div class="text-[7px] bg-orange-500 text-white px-1 py-0.5 rounded font-bold inline-block">SEÇİLDİ</div>
                                                    @elseif($isBest && !$hasTariffSelected)
                                                        <div class="text-[7px] {{ $idx === 0 ? 'bg-orange-500' : 'bg-green-600' }} text-white px-1 py-0.5 rounded font-bold inline-block">EN İYİ</div>
                                                    @endif
                                                </div>
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                    @endforeach

                                    {{-- Fiyat Güncelle --}}
                                    <td class="px-2 py-2 text-center align-middle">
                                        <div class="flex flex-col items-center gap-1 relative" x-data>
                                            @php
                                                $displayPrice = $item->custom_price ?: ($item->suggested_price ?: $item->current_price);
                                                $suggestion = $suggestedPrices[$item->id] ?? null;
                                            @endphp
                                            
                                            <div class="relative flex items-center gap-1">
                                                <div class="relative">
                                                    <input type="number" 
                                                        x-ref="price{{ $item->id }}"
                                                        value="{{ number_format($displayPrice, 2, '.', '') }}"
                                                        class="w-20 text-xs text-center border border-gray-300 rounded-md px-1 py-1 font-bold focus:ring-2 focus:ring-orange-400 focus:border-orange-400 {{ $item->custom_price ? 'bg-orange-50 border-orange-300' : '' }}"
                                                        step="0.01"
                                                        min="0"
                                                        title="Özel fiyat gir">
                                                </div>
                                                
                                                {{-- Magic Wand Button --}}
                                                <button wire:click="getAiPriceSuggestion({{ $item->id }})" 
                                                    class="p-1.5 rounded-md bg-indigo-50 text-indigo-600 hover:bg-indigo-100 hover:text-indigo-700 transition-colors"
                                                    title="AI ile Fiyat Öner">
                                                    @if($suggestion && ($suggestion['loading'] ?? false))
                                                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                                    @else
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
                                                    @endif
                                                </button>
                                            </div>

                                            {{-- Suggestion Popover --}}
                                            @if($suggestion && !($suggestion['loading'] ?? false) && !isset($suggestion['error']))
                                                <div x-data="{ showPop: true }" x-show="showPop" x-transition
                                                     class="absolute top-full left-1/2 -translate-x-1/2 mt-1 z-50 w-32 bg-white rounded-md shadow-lg border border-indigo-100 p-1.5 text-center">
                                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2px;">
                                                        <span class="text-[8px] text-gray-400">AI Önerisi</span>
                                                        <span x-on:click.stop="showPop = false" 
                                                            style="cursor:pointer; background:#ef4444; color:white; font-size:9px; font-weight:bold; padding:0 4px; border-radius:3px; display:inline-block; line-height:1.5;">✕</span>
                                                    </div>
                                                    <div class="font-bold text-indigo-600 text-xs">{{ number_format($suggestion['price'], 2) }} ₺</div>
                                                    <p class="text-[7px] text-gray-400 italic my-0.5 leading-tight">{{ Str::limit($suggestion['reason'], 30) }}</p>
                                                    <button wire:click="applySuggestedPrice({{ $item->id }})" 
                                                        class="w-full bg-indigo-600 text-white text-[8px] py-0.5 rounded hover:bg-indigo-700 transition">
                                                        Uygula
                                                    </button>
                                                </div>
                                            @endif

                                            <button 
                                                x-on:click="$wire.updateCustomPrice({{ $item->id }}, $refs.price{{ $item->id }}.value)"
                                                class="bg-orange-500 hover:bg-orange-600 text-white text-[9px] font-bold px-3 py-1 rounded transition-colors shadow-sm w-full">
                                                Kaydet
                                            </button>
                                            
                                            @if($item->custom_price)
                                                <div class="flex items-center gap-1">
                                                    <span class="text-[8px] text-emerald-600 font-medium">✅ Düzenlendi</span>
                                                    @if($item->custom_price != ($suggestion['price'] ?? 0))
                                                        {{-- Eğer AI önerisi varsa ama farklı bir fiyat girildiyse --}}
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    </td>

                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-8 text-center text-gray-500">Sonuç bulunamadı</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Tablet Görünümü (lg ama xl değil) --}}
                <div class="hidden lg:block xl:hidden overflow-x-auto">
                    <table class="w-full text-xs" style="min-width:800px">
                        <thead class="bg-gray-800 text-white">
                            <tr>
                                <th class="px-1 py-2 text-center text-[10px]" style="width:50px">Tarife<br>Seç</th>
                                <th class="px-3 py-2 text-left text-[10px]" style="min-width:130px">Ürün</th>
                                <th class="px-2 py-2 text-center text-[10px]"><div>Kargo</div><div class="text-gray-400">Üretim</div></th>
                                <th class="px-2 py-2 text-center text-[10px] bg-orange-600 border-l border-r border-orange-700">1. Tarife</th>
                                <th class="px-2 py-2 text-center text-[10px]">2. Tarife</th>
                                <th class="px-2 py-2 text-center text-[10px]">3. Tarife</th>
                                <th class="px-2 py-2 text-center text-[10px]">4. Tarife</th>
                                <th class="px-2 py-2 text-center text-[10px] bg-emerald-700">Fiyat<br>Güncelle</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($report->items as $item)
                                @php
                                    $scenarios = $item->scenario_details;
                                    $sc1 = $scenarios[0] ?? null; $sc2 = $scenarios[1] ?? null;
                                    $sc3 = $scenarios[2] ?? null; $sc4 = $scenarios[3] ?? null;
                                    $totalCost = $item->production_cost + $item->shipping_cost;
                                    $selectedIdx = $item->selected_tariff_index;

                                    // Karlılık filtresi
                                    if ($profitabilityTariffIndex !== null && $profitabilityMin !== null && $item->production_cost > 0) {
                                        $targetSc = $scenarios[$profitabilityTariffIndex] ?? null;
                                        if ($targetSc) {
                                            $profitPct = ($targetSc['net_profit'] / $item->production_cost) * 100;
                                            if ($profitPct < $profitabilityMin || $profitPct > $profitabilityMax) continue;
                                        } else {
                                            continue;
                                        }
                                    }
                                @endphp
                                <tr class="hover:bg-gray-50 {{ $item->action === 'warning' ? 'bg-red-50/50' : '' }}">
                                    <td class="px-1 py-2 text-center">
                                        <div class="flex flex-col gap-0.5 items-center">
                                            @for($ti = 0; $ti < 4; $ti++)
                                                @if(isset($scenarios[$ti]))
                                                    <button wire:click="selectTariff({{ $item->id }}, {{ $ti }})"
                                                        class="w-5 h-4 rounded text-[8px] font-bold {{ $selectedIdx === $ti ? 'bg-orange-500 text-white ring-1 ring-orange-300' : 'bg-gray-100 text-gray-500 hover:bg-orange-100' }}">
                                                        {{ $ti + 1 }}
                                                    </button>
                                                @endif
                                            @endfor
                                        </div>
                                    </td>
                                    <td class="px-3 py-2">
                                        <p class="font-medium text-gray-900" title="{{ $item->product_name }}">{{ $item->product_name ?: $item->stock_code }}</p>
                                        <p class="text-[9px] text-indigo-500 font-mono">{{ $item->stock_code }}</p>
                                        @if($item->action === 'update')<span class="text-[8px] bg-green-100 text-green-700 px-1 rounded font-bold">FIRSAT</span>@elseif($item->action === 'warning')<span class="text-[8px] bg-red-100 text-red-600 px-1 rounded font-bold">ZARAR</span>@endif
                                    </td>
                                    <td class="px-1 py-2 text-center">
                                        <div class="text-[10px] font-bold text-gray-800">{{ number_format($item->shipping_cost, 0) }}₺</div>
                                        <div class="text-[9px] text-gray-400">{{ number_format($item->production_cost, 0) }}₺</div>
                                    </td>
                                    @foreach([$sc1, $sc2, $sc3, $sc4] as $idx => $sc)
                                        @php
                                            $isSelected = ($selectedIdx === $idx);
                                            $isBest = $sc && ($sc['is_best'] ?? false);
                                            $cellCls = $isSelected ? 'border-2 border-orange-400 bg-orange-50/30' : ($isBest ? ($idx === 0 ? 'bg-orange-50' : 'bg-green-50') : '');
                                        @endphp
                                        <td class="px-1 py-2 text-center cursor-pointer {{ $cellCls }} {{ $idx === 0 ? 'border-l border-r border-orange-100' : 'border-r border-gray-50' }}"
                                            wire:click="selectTariff({{ $item->id }}, {{ $idx }})">
                                            @if($sc)
                                                <div class="text-[10px] font-bold">{{ number_format($sc['price'], 0) }}₺</div>
                                                <div class="text-[9px] {{ $idx === 0 ? 'text-orange-600' : 'text-gray-400' }}">%{{ number_format($sc['commission'], 1) }}</div>
                                                <div class="text-[10px] font-bold {{ $sc['net_profit'] >= 0 ? 'text-green-600' : 'text-red-600' }} cursor-help" title="Karlılık: %{{ $item->production_cost > 0 ? number_format(($sc['net_profit'] / $item->production_cost) * 100, 1) : '0' }}">{{ number_format($sc['net_profit'], 0) }}₺</div>
                                                @if($isSelected)<div class="text-[7px] bg-orange-500 text-white px-0.5 rounded font-bold inline-block mt-0.5">SEÇİLDİ</div>
                                                @elseif($isBest)<div class="text-[7px] bg-orange-500 text-white px-0.5 rounded font-bold inline-block mt-0.5">EN İYİ</div>@endif
                                            @else <span class="text-gray-300">—</span> @endif
                                        </td>
                                    @endforeach
                                    <td class="px-1 py-2 text-center align-middle">
                                        <div class="flex flex-col items-center gap-1 relative" x-data="{ showSuggestion: true }">
                                            @php
                                                $dp = $item->custom_price ?: ($item->suggested_price ?: $item->current_price);
                                                $suggestion = $suggestedPrices[$item->id] ?? null;
                                            @endphp
                                            
                                            <div class="relative flex items-center gap-1 justify-center">
                                                <input type="number" x-ref="tp{{ $item->id }}" value="{{ number_format($dp, 2, '.', '') }}" class="w-16 text-[10px] text-center border rounded px-1 py-0.5 font-bold {{ $item->custom_price ? 'bg-orange-50 border-orange-300' : 'border-gray-300' }}" step="0.01" min="0">
                                                
                                                <button wire:click="getAiPriceSuggestion({{ $item->id }})" class="p-1 rounded bg-indigo-50 text-indigo-600 hover:bg-indigo-100 transition-colors" title="AI Fiyat Öner">
                                                    @if($suggestion && ($suggestion['loading'] ?? false))
                                                        <svg class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                                    @else
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
                                                    @endif
                                                </button>
                                            </div>

                                            @if($suggestion && !($suggestion['loading'] ?? false) && !isset($suggestion['error']))
                                                <div x-show="showSuggestion" x-transition
                                                     class="absolute top-full left-1/2 -translate-x-1/2 mt-1 z-50 w-44 bg-white rounded-lg shadow-xl border border-indigo-200 p-2">
                                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                                                        <span class="text-[10px] text-gray-500">AI Önerisi</span>
                                                        <span x-on:click.stop="showSuggestion = false" 
                                                            style="cursor:pointer; background:#ef4444; color:white; font-size:11px; font-weight:bold; padding:1px 6px; border-radius:4px; display:inline-block; line-height:1.4;">X</span>
                                                    </div>
                                                    <div class="font-bold text-indigo-700 text-base text-center mb-1">{{ number_format($suggestion['price'], 2) }} ₺</div>
                                                    <p class="text-[8px] text-gray-400 italic mb-2 leading-tight text-center">{{ Str::limit($suggestion['reason'], 50) }}</p>
                                                    <button wire:click="applySuggestedPrice({{ $item->id }})" class="w-full bg-indigo-600 text-white text-[10px] py-1 rounded hover:bg-indigo-700 transition font-medium">Uygula</button>
                                                </div>
                                            @endif

                                            <button x-on:click="$wire.updateCustomPrice({{ $item->id }}, $refs.tp{{ $item->id }}.value)"
                                                class="bg-orange-500 text-white text-[8px] font-bold px-2 py-0.5 rounded mt-0.5 block mx-auto w-full">Güncelle</button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="p-8 text-center text-gray-500">Sonuç bulunamadı</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Mobil Kart Görünümü --}}
                <div class="lg:hidden divide-y divide-gray-200">
                    @forelse($report->items as $item)
                        @php
                            $scenarios = $item->scenario_details;
                            $totalCost = $item->production_cost + $item->shipping_cost;
                            $selectedIdx = $item->selected_tariff_index;

                            // Karlılık filtresi
                            if ($profitabilityTariffIndex !== null && $profitabilityMin !== null && $item->production_cost > 0) {
                                $targetSc = $scenarios[$profitabilityTariffIndex] ?? null;
                                if ($targetSc) {
                                    $profitPct = ($targetSc['net_profit'] / $item->production_cost) * 100;
                                    if ($profitPct < $profitabilityMin || $profitPct > $profitabilityMax) continue;
                                } else {
                                    continue;
                                }
                            }
                        @endphp
                        <div wire:key="mobile-item-{{ $item->id }}" class="p-4 {{ $item->action === 'update' ? 'bg-green-50/50' : ($item->action === 'warning' ? 'bg-red-50/50' : '') }}">
                            {{-- Üst Kısım --}}
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex items-start gap-2 min-w-0 flex-1">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-gray-900 line-clamp-2">{{ $item->product_name ?: $item->stock_code }}</p>
                                        <p class="text-[10px] text-indigo-500 font-mono mt-0.5">{{ $item->stock_code }}</p>
                                    </div>
                                </div>
                                @if($item->action === 'update')
                                    <span class="text-[9px] bg-green-100 text-green-700 px-1.5 py-0.5 rounded font-bold flex-shrink-0 ml-2">🚀 FIRSAT</span>
                                @elseif($item->action === 'warning')
                                    <span class="text-[9px] bg-red-100 text-red-600 px-1.5 py-0.5 rounded font-bold flex-shrink-0 ml-2">⚠ ZARAR</span>
                                @endif
                            </div>

                            {{-- Kargo / Üretim Maliyet Satırı --}}
                            <div class="flex justify-center gap-4 text-[10px] text-gray-400 mb-2">
                                <span>Kargo: <span class="font-medium text-gray-700">{{ number_format($item->shipping_cost, 0) }}₺</span></span>
                                <span>Üretim: <span class="font-medium text-gray-700">{{ number_format($item->production_cost, 0) }}₺</span></span>
                            </div>

                            {{-- 4 Tarife Grid --}}
                            <div class="grid grid-cols-4 gap-1.5">
                                @if($scenarios)
                                    @foreach($scenarios as $idx => $sc)
                                        @php
                                            $isSelected = ($selectedIdx === $idx);
                                            $isBest = ($sc['is_best'] ?? false);
                                            $hasTariffSelected = ($selectedIdx !== null);
                                        @endphp
                                        <div wire:click="selectTariff({{ $item->id }}, {{ $idx }})"
                                             class="rounded-lg p-2 text-center cursor-pointer transition-all {{ $isSelected ? 'bg-orange-50 border-2 border-orange-400 ring-2 ring-orange-200 shadow-md' : ($hasTariffSelected ? 'bg-gray-50 border border-gray-200' : ($isBest ? ($idx === 0 ? 'bg-orange-50 border-2 border-orange-300 ring-1 ring-orange-200' : 'bg-green-50 border-2 border-green-300 ring-1 ring-green-200') : 'bg-gray-50 border border-gray-200 hover:border-orange-200')) }}">
                                            <div class="text-[9px] font-bold {{ $idx === 0 ? 'text-orange-600' : 'text-gray-500' }} mb-1">
                                                {{ $idx === 0 ? 'Mevcut' : ($idx+1) . '. Tarife' }}
                                            </div>
                                            <div class="text-xs font-bold text-gray-900">{{ number_format($sc['price'], 0) }}₺</div>
                                            <div class="text-[9px] {{ $idx === 0 ? 'text-orange-500' : 'text-gray-400' }}">%{{ number_format($sc['commission'], 1) }}</div>
                                            <div class="text-xs font-bold mt-0.5 {{ $sc['net_profit'] >= 0 ? 'text-green-600' : 'text-red-500' }}" title="Karlılık: %{{ $item->production_cost > 0 ? number_format(($sc['net_profit'] / $item->production_cost) * 100, 1) : '0' }}">
                                                {{ number_format($sc['net_profit'], 0) }}₺
                                            </div>
                                            @if($isSelected)
                                                <div class="text-[7px] bg-orange-500 text-white px-1 py-0.5 rounded font-bold mt-0.5 inline-block">SEÇİLDİ</div>
                                            @elseif($isBest && !$hasTariffSelected)
                                                <div class="text-[7px] bg-orange-500 text-white px-1 py-0.5 rounded font-bold mt-0.5 inline-block">EN İYİ</div>
                                            @endif
                                        </div>
                                    @endforeach
                                @else
                                    <div class="col-span-4 text-center text-gray-400 text-xs py-2">Senaryo verisi yok</div>
                                @endif
                            </div>

                            {{-- Fiyat Güncelle --}}
                            <div x-data="{ showSuggestion: true }" class="flex flex-col items-center gap-2 mt-3 pt-2 border-t border-gray-100 relative">
                                @php 
                                    $dp = $item->custom_price ?: ($item->suggested_price ?: $item->current_price);
                                    $suggestion = $suggestedPrices[$item->id] ?? null;
                                @endphp
                                
                                <div class="flex items-center gap-2">
                                    <input type="number" x-ref="mp{{ $item->id }}" value="{{ number_format($dp, 2, '.', '') }}" 
                                        class="w-28 text-xs text-center border rounded-md px-2 py-1.5 font-bold {{ $item->custom_price ? 'bg-orange-50 border-orange-300' : 'border-gray-300' }}" step="0.01" min="0">
                                    
                                    <button wire:click="getAiPriceSuggestion({{ $item->id }})" class="p-2 rounded-md bg-indigo-50 text-indigo-600 hover:bg-indigo-100 transition-colors shadow-sm" title="AI Fiyat Öner">
                                        @if($suggestion && ($suggestion['loading'] ?? false))
                                            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                        @else
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
                                        @endif
                                    </button>
                                </div>

                                @if($suggestion && !($suggestion['loading'] ?? false) && !isset($suggestion['error']))
                                    <div x-show="showSuggestion" x-transition
                                         class="w-full bg-indigo-50 rounded px-2 py-1.5 border border-indigo-200 flex items-center justify-between gap-2">
                                        <span class="text-[9px] text-indigo-500 font-semibold whitespace-nowrap">AI:</span>
                                        <span class="font-bold text-indigo-700 text-xs whitespace-nowrap">{{ number_format($suggestion['price'], 2) }} ₺</span>
                                        <button type="button" wire:click="applySuggestedPrice({{ $item->id }})" class="bg-indigo-600 text-white text-[8px] px-2 py-0.5 rounded hover:bg-indigo-700 transition font-medium whitespace-nowrap">Uygula</button>
                                        <span x-on:click.stop="showSuggestion = false" 
                                            style="cursor:pointer; background:#ef4444; color:white; font-size:9px; font-weight:bold; padding:0 4px; border-radius:3px; display:inline-block; line-height:1.5;">✕</span>
                                    </div>
                                @endif

                                <button x-on:click="$wire.updateCustomPrice({{ $item->id }}, $refs.mp{{ $item->id }}.value)"
                                    class="bg-orange-500 hover:bg-orange-600 text-white text-xs font-bold px-4 py-1.5 rounded-md transition-colors shadow-sm w-full">
                                    Fiyat Güncelle
                                </button>
                                @if($item->custom_price)
                                    <span class="text-[9px] text-emerald-600 font-medium">✅</span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="p-8 text-center text-gray-500">Sonuç bulunamadı</div>
                    @endforelse
                </div>
            </div>

        @endif

        {{-- KATEGORİ ANALİZ TABLOSU --}}
        @if($tabView === 'categories')
            <div class="overflow-x-auto p-4 bg-white rounded-lg border border-gray-200">
                <div class="mb-4 bg-indigo-50 border border-indigo-100 rounded-lg p-4 flex items-center gap-4">
                    <div class="p-2 bg-indigo-100 rounded-lg text-indigo-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    </div>
                    <div>
                        <h4 class="font-bold text-indigo-900">Kategori Performans Özeti</h4>
                        <p class="text-xs text-indigo-700">
                            {{ count($this->categoryReport) }} kategori analiz edildi.
                            Ürün isimlerine göre otomatik kategorize edilmiştir. En karlı kategoriler en üstte sıralanır.
                        </p>
                    </div>
                </div>

                @if(count($this->categoryReport) === 0)
                    <div class="p-8 text-center text-gray-500 border-2 border-dashed border-gray-200 rounded-lg">
                        <p>Kategori verisi bulunamadı.</p>
                        <p class="text-xs mt-1">Ürün isimleri ayrıştırılamadı veya rapor boş.</p>
                    </div>
                @else


                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50 text-gray-700 font-bold uppercase text-xs">
                        <tr>
                            <th class="px-4 py-3 rounded-l-lg">Kategori</th>
                            <th class="px-4 py-3 text-center">Ürün Adedi</th>
                            <th class="px-4 py-3 text-right">Toplam Maliyet</th>
                            <th class="px-4 py-3 text-right">Potansiyel Ciro</th>
                            <th class="px-4 py-3 text-right">Net Kâr</th>
                            <th class="px-4 py-3 text-center rounded-r-lg">Marj %</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($this->categoryReport as $cat)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $cat['name'] }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="bg-gray-100 text-gray-600 px-2 py-1 rounded text-xs font-bold">{{ $cat['count'] }}</span>
                                </td>
                                <td class="px-4 py-3 text-right text-gray-500">{{ number_format($cat['cost'], 2) }} ₺</td>
                                <td class="px-4 py-3 text-right font-medium">{{ number_format($cat['revenue'], 2) }} ₺</td>
                                <td class="px-4 py-3 text-right font-bold {{ $cat['profit'] > 0 ? 'text-emerald-600' : 'text-red-600' }}">
                                    {{ number_format($cat['profit'], 2) }} ₺
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @php 
                                        $margin = $cat['revenue'] > 0 ? ($cat['profit'] / $cat['revenue']) * 100 : 0;
                                        $color = $margin >= 30 ? 'bg-emerald-100 text-emerald-700' : ($margin >= 15 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700');
                                    @endphp
                                    <span class="{{ $color }} px-2 py-1 rounded text-xs font-bold">
                                        %{{ number_format($margin, 1) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif
            </div>
        @endif

    @endif

    @endif



    {{-- ===================================================== --}}
    {{-- TAB 2: GEÇMİŞ RAPORLAR --}}
    {{-- ===================================================== --}}
    @if($activeTab === 'history')
        @if($this->reports->isEmpty())
            <div class="text-center py-12">
                <svg class="w-16 h-16 text-gray-300 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <h3 class="mt-4 text-lg font-medium text-gray-900">Henüz rapor yok</h3>
                <p class="mt-2 text-sm text-gray-500">İlk analizi çalıştırarak buraya rapor ekleyin.</p>
                <button wire:click="switchTab('analyze')" class="mt-4 px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 min-h-[44px]">
                    Yeni Analiz Başlat
                </button>
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 lg:gap-6">
                @foreach($this->reports as $rpt)
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition p-4 lg:p-5">
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex-1 min-w-0">
                                <h4 class="font-medium text-gray-900 truncate">{{ $rpt->name }}</h4>
                                <p class="text-xs text-gray-400 mt-0.5">{{ $rpt->created_at->format('d.m.Y H:i') }}</p>
                            </div>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium {{ 
                                $rpt->status === 'applied' ? 'bg-blue-100 text-blue-800' : 
                                ($rpt->status === 'exported' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800') 
                            }}">
                                {{ $rpt->status === 'applied' ? 'Uygulandı' : ($rpt->status === 'exported' ? 'İndirildi' : 'Tamamlandı') }}
                            </span>
                        </div>
                        
                        <div class="grid grid-cols-3 gap-2 text-center bg-gray-50 rounded-lg p-3 mb-3">
                            <div>
                                <div class="text-xs text-gray-400">Ürün</div>
                                <div class="text-lg font-bold text-gray-900">{{ $rpt->total_products }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-emerald-500">Fırsat</div>
                                <div class="text-lg font-bold text-emerald-600">{{ $rpt->opportunity_count }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-green-500">Ek Kâr</div>
                                <div class="text-sm font-bold text-green-600">+{{ number_format($rpt->total_extra_profit, 0) }} ₺</div>
                            </div>
                        </div>

                        @if($rpt->original_filename)
                            <p class="text-[10px] text-gray-400 truncate mb-3">📄 {{ $rpt->original_filename }}</p>
                        @endif

                        <div class="flex gap-2">
                            <button wire:click="viewReport({{ $rpt->id }})"
                                class="flex-1 px-3 py-2 text-sm bg-gray-900 text-white rounded-lg hover:bg-gray-800 min-h-[44px]">
                                Görüntüle
                            </button>
                            <button wire:click="deleteReport({{ $rpt->id }})"
                                wire:confirm="Bu raporu silmek istediğinize emin misiniz?"
                                class="px-3 py-2 text-sm text-red-600 border border-red-200 rounded-lg hover:bg-red-50 min-h-[44px]">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @endif

    {{-- ===================================================== --}}
    {{-- CHAT DRAWER & FLOATING BUTTON (Minimal) --}}
    {{-- ===================================================== --}}
    
    
    {{-- Floating Action Button (Minimal) --}}
    @if($activeReportId)
        <button wire:click="toggleChat"
            class="fixed bottom-5 right-5 w-11 h-11 bg-indigo-600 hover:bg-indigo-700 text-white rounded-full flex items-center justify-center transition-all duration-200 hover:scale-105 active:scale-95 shadow-lg shadow-indigo-600/25"
            style="z-index: 9998;">
            @if(!$showChat)
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/>
                </svg>
            @else
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            @endif
        </button>

        {{-- Chat Drawer --}}
        <div x-data="{ show: @entangle('showChat') }"
             x-show="show"
             x-cloak
             @keydown.escape.window="show = false"
             class="fixed inset-y-0 right-0 w-full sm:w-96 bg-white shadow-2xl flex flex-col"
             style="z-index: 9999;"
             x-transition:enter="transition transform ease-out duration-250"
             x-transition:enter-start="translate-x-full"
             x-transition:enter-end="translate-x-0"
             x-transition:leave="transition transform ease-in duration-200"
             x-transition:leave-start="translate-x-0"
             x-transition:leave-end="translate-x-full">
            
            {{-- Header --}}
            <div class="px-4 py-2.5 bg-indigo-600 text-white flex justify-between items-center shrink-0">
                <div class="flex items-center gap-2.5">
                    <div class="w-7 h-7 rounded-lg bg-white/15 flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-sm">ZOLM AI</h3>
                        <p class="text-[10px] text-indigo-200">Rapor Asistanı</p>
                    </div>
                </div>
                <button @click="show = false" class="w-7 h-7 rounded-md flex items-center justify-center hover:bg-white/10 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- AI Araçları --}}
            @if($this->activeReport)
                <div x-data="{ toolsOpen: false }" class="px-3 py-2 bg-gray-50 border-b border-gray-100">
                    <button type="button" @click="toolsOpen = !toolsOpen" class="w-full flex items-center justify-between text-xs">
                        <span class="flex items-center gap-1.5 font-semibold text-gray-600">
                            <svg class="w-3.5 h-3.5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.42 15.17l-5.658 3.286 1.082-6.302-4.58-4.466 6.325-.919L11.42 1l2.83 5.769 6.326.919-4.58 4.466 1.081 6.302z"/></svg>
                            AI Araçları
                        </span>
                        <svg class="w-3.5 h-3.5 text-gray-400 transition-transform" :class="toolsOpen && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>

                    <div x-show="toolsOpen" x-transition x-cloak class="mt-2 space-y-1.5">
                        @php $report = $this->activeReport; @endphp

                        {{-- AI Danışman --}}
                        <div x-data="{ aiExpanded: false }" class="bg-white rounded-lg border border-gray-200 p-2">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <div class="w-5 h-5 rounded bg-indigo-100 flex items-center justify-center shrink-0">
                                        <svg class="w-3 h-3 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                    </div>
                                    <span class="text-xs font-medium text-gray-700">AI Danışman</span>
                                </div>
                                @if($report->ai_analysis)
                                    <button type="button" @click="aiExpanded = !aiExpanded" class="px-2 py-0.5 text-[10px] font-medium rounded text-indigo-600 hover:bg-indigo-50 transition">
                                        <span x-text="aiExpanded ? 'Gizle' : 'Göster'">Göster</span>
                                    </button>
                                @elseif(!$isProcessing)
                                    <button wire:click="generateAIAnalysis" class="px-2 py-0.5 bg-indigo-600 text-white text-[10px] font-medium rounded hover:bg-indigo-700 transition">Analiz</button>
                                @endif
                            </div>
                            @if($isProcessing && str_contains($message, 'Yapay zeka'))
                                <div class="mt-1.5 animate-pulse"><div class="h-1 bg-indigo-100 rounded w-3/4"></div></div>
                            @endif
                            @if($report->ai_analysis)
                                <div x-show="aiExpanded" x-transition x-cloak class="mt-2 prose prose-indigo prose-xs max-w-none bg-gray-50 rounded p-2 text-[11px] leading-relaxed max-h-48 overflow-y-auto border border-gray-100">
                                    {!! Str::markdown($report->ai_analysis) !!}
                                </div>
                            @endif
                        </div>

                        {{-- Zarar Denetimi --}}
                        @if($report->items->where('action', 'warning')->count() > 0)
                            <div x-data="{ lossExpanded: false }" class="bg-white rounded-lg border border-gray-200 p-2">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <div class="w-5 h-5 rounded bg-red-50 flex items-center justify-center shrink-0">
                                            <svg class="w-3 h-3 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                                        </div>
                                        <span class="text-xs font-medium text-gray-700">Zarar Denetimi</span>
                                        <span class="text-[9px] text-red-500 font-medium">{{ $report->items->where('action', 'warning')->count() }}</span>
                                    </div>
                                    @if($report->loss_analysis)
                                        <button type="button" @click="lossExpanded = !lossExpanded" class="px-2 py-0.5 text-[10px] font-medium rounded text-red-600 hover:bg-red-50 transition">
                                            <span x-text="lossExpanded ? 'Gizle' : 'Göster'">Göster</span>
                                        </button>
                                    @elseif(!$isProcessing)
                                        <button wire:click="analyzeLosses" class="px-2 py-0.5 bg-red-600 text-white text-[10px] font-medium rounded hover:bg-red-700 transition">Denetle</button>
                                    @endif
                                </div>
                                @if($isProcessing && str_contains($message, 'Zarar'))
                                    <div class="mt-1.5 animate-pulse"><div class="h-1 bg-red-100 rounded w-3/4"></div></div>
                                @endif
                                @if($report->loss_analysis)
                                    <div x-show="lossExpanded" x-transition x-cloak class="mt-2 prose prose-red prose-xs max-w-none bg-gray-50 rounded p-2 text-[11px] leading-relaxed max-h-48 overflow-y-auto border border-gray-100">
                                        {!! Str::markdown($report->loss_analysis) !!}
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Messages Area --}}
            <div class="flex-1 overflow-y-auto p-3 space-y-3 bg-gray-50" id="chat-messages">
                @if($this->currentConversation)
                    @foreach($this->currentConversation->messages as $msg)
                        @if($msg['role'] === 'system') @continue @endif
                        <div class="flex {{ $msg['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
                            <div class="max-w-[85%] rounded-xl p-2.5 text-sm {{ 
                                $msg['role'] === 'user' 
                                    ? 'bg-indigo-600 text-white rounded-br-sm' 
                                    : 'bg-white text-gray-700 rounded-bl-sm border border-gray-100 shadow-sm' 
                            }}">
                                @if($msg['role'] !== 'user')
                                    <div class="prose prose-sm max-w-none text-gray-700" style="font-size: 13px;">
                                        {!! Str::markdown($msg['content']) !!}
                                    </div>
                                @else
                                    {{ $msg['content'] }}
                                @endif
                                <div class="text-[9px] mt-1 {{ $msg['role'] === 'user' ? 'text-indigo-200' : 'text-gray-400' }} text-right">
                                    {{ \Carbon\Carbon::parse($msg['timestamp'])->format('H:i') }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                    
                    @if($isChatting)
                        <div class="flex justify-start">
                            <div class="bg-white text-gray-400 rounded-xl rounded-bl-sm p-2.5 text-xs border border-gray-100 shadow-sm">
                                <div class="flex items-center gap-1">
                                    <div class="w-1.5 h-1.5 rounded-full bg-indigo-400 animate-bounce" style="animation-delay: 0ms;"></div>
                                    <div class="w-1.5 h-1.5 rounded-full bg-indigo-400 animate-bounce" style="animation-delay: 150ms;"></div>
                                    <div class="w-1.5 h-1.5 rounded-full bg-indigo-400 animate-bounce" style="animation-delay: 300ms;"></div>
                                </div>
                            </div>
                        </div>
                    @endif
                @else
                    <div class="text-center mt-12">
                        <div class="w-10 h-10 mx-auto rounded-xl bg-indigo-50 flex items-center justify-center mb-3">
                            <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/></svg>
                        </div>
                        <p class="text-gray-500 text-xs">Raporunuz hakkında soru sorun</p>
                    </div>
                @endif
            </div>

            {{-- Input Area --}}
            <div class="p-2.5 bg-white border-t border-gray-100 shrink-0">
                <form wire:submit.prevent="sendMessage" class="flex gap-2">
                    <input type="text" 
                        wire:model="chatMessage" 
                        placeholder="Bir soru sorun..." 
                        class="flex-1 rounded-lg text-sm min-h-[38px] px-3 border border-gray-200 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 placeholder-gray-400 bg-gray-50"
                        {{ $isChatting ? 'disabled' : '' }}>
                    <button type="submit" 
                        class="w-9 h-9 bg-indigo-600 text-white rounded-lg flex items-center justify-center hover:bg-indigo-700 transition disabled:opacity-50"
                        {{ $isChatting ? 'disabled' : '' }}>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                    </button>
                </form>
            </div>
        </div>
    @endif
</div>
@script
<script>
    Livewire.on('chat-scroll', () => {
        const el = document.getElementById('chat-messages');
        if(el) setTimeout(() => el.scrollTop = el.scrollHeight, 100);
    });
</script>
@endscript

