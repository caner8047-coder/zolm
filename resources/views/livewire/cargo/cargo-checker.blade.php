<div class="space-y-6 overflow-x-hidden">
    {{-- Mesaj --}}
    @if($message)
        <div class="rounded-lg p-4 {{ 
            $messageType === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 
            ($messageType === 'error' ? 'bg-red-50 text-red-800 border border-red-200' : 
            ($messageType === 'warning' ? 'bg-yellow-50 text-yellow-800 border border-yellow-200' : 
            'bg-blue-50 text-blue-800 border border-blue-200')) 
        }}">
            {{ $message }}
        </div>
    @endif

    {{-- Sonuç yoksa: Dosya yükleme formu --}}
    @if(!$hasResults)
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 lg:gap-6">
            {{-- Sol: Ürün Listesi Durumu --}}
            <div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl p-4 lg:p-6 border border-gray-200">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-lg bg-white shadow flex items-center justify-center">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-medium text-gray-900">Güncel Ürün Desi ve Fiyat Listesi</h3>
                        <p class="text-sm text-gray-500 mt-1">Sistemde kayıtlı ürün ve desi listesi</p>
                        <div class="mt-3">
                            @if($this->productCount > 0)
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                    ✓ {{ $this->productCount }} ürün kayıtlı
                                </span>
                            @else
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                                    ⚠ Ürün listesi boş
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Orta: Kargo Raporu --}}
            <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl p-4 lg:p-6 border border-purple-200">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-lg bg-white shadow flex items-center justify-center">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-medium text-gray-900">Kargo Raporu</h3>
                        <p class="text-sm text-gray-500 mt-1">Kargo firmasından alınan çıkış desi ve fiyatlar (xlsx)</p>
                        <div class="mt-3">
                            <input 
                                type="file" 
                                wire:model="cargoFile"
                                accept=".xlsx,.xls"
                                class="block w-full text-sm text-gray-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-purple-100 file:text-purple-700 hover:file:bg-purple-200"
                            >
                            @if($cargoFile)
                                <span class="text-xs text-green-600 mt-1 block">✓ {{ $cargoFile->getClientOriginalName() }}</span>
                            @endif
                            @error('cargoFile') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Sağ: Sipariş Detayları --}}
            <div class="bg-gradient-to-br from-orange-50 to-orange-100 rounded-xl p-4 lg:p-6 border border-orange-200">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-lg bg-white shadow flex items-center justify-center">
                        <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-medium text-gray-900">Sipariş Detayları</h3>
                        <p class="text-sm text-gray-500 mt-1">Müşteri ismi ve detaylı ürün bilgisi (xlsx)</p>
                        <div class="mt-3">
                            <input 
                                type="file" 
                                wire:model="orderFile"
                                accept=".xlsx,.xls"
                                class="block w-full text-sm text-gray-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-orange-100 file:text-orange-700 hover:file:bg-orange-200"
                            >
                            @if($orderFile)
                                <span class="text-xs text-green-600 mt-1 block">✓ {{ $orderFile->getClientOriginalName() }}</span>
                            @endif
                            @error('orderFile') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Kargo Firması ve Rapor Adı --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 lg:gap-4 bg-gray-50 rounded-lg p-3 lg:p-4">
            <div>
                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Kargo Firması</label>
                <select 
                    wire:model="cargoCompany"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-base sm:text-sm min-h-[44px]"
                >
                    @foreach($this->cargoCompanies as $name => $label)
                        <option value="{{ $name }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Rapor Adı (Opsiyonel)</label>
                <input 
                    type="text" 
                    wire:model="reportName"
                    placeholder="Örn: Sürat Kargo Ocak 2026"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-base sm:text-sm min-h-[44px]"
                >
            </div>
        </div>

        {{-- Kontrol Et Butonu --}}
        <div class="flex justify-center">
            <button 
                wire:click="runComparison"
                wire:loading.attr="disabled"
                class="w-full sm:w-auto px-6 py-3 bg-blue-600 text-white font-medium rounded-xl hover:bg-blue-700 transition shadow-lg disabled:opacity-50 flex justify-center items-center gap-3 min-h-[44px]"
            >
                <span wire:loading.remove wire:target="runComparison">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                </span>
                <span wire:loading wire:target="runComparison">
                    <svg class="animate-spin h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </span>
                <span wire:loading.remove wire:target="runComparison">Kontrol Et</span>
                <span wire:loading wire:target="runComparison">Kontrol Ediliyor...</span>
            </button>
        </div>
    @endif

    {{-- Sonuç varsa: İstatistikler ve Tablo --}}
    @if($hasResults)
        {{-- Yeni Karşılaştırma Butonu --}}
        {{-- Yeni Karşılaştırma Butonu --}}
        <div class="flex flex-col sm:flex-row justify-between items-center gap-3">
            <button 
                wire:click="resetForm"
                class="w-full sm:w-auto px-4 py-3 sm:py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 flex justify-center items-center gap-2 min-h-[44px]"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Yeni Karşılaştırma
            </button>

            <button 
                wire:click="exportReport"
                class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 flex justify-center items-center gap-2 min-h-[44px]"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Excel İndir
            </button>
        </div>

        {{-- İstatistik Kartları --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3 lg:gap-4">
            <div class="bg-white rounded-xl p-3 sm:p-4 border border-gray-200 shadow-sm">
                <div class="text-gray-500 text-xs sm:text-sm">Incelenen Sipariş</div>
                <div class="text-xl sm:text-2xl font-bold text-gray-900">{{ number_format($stats['total_orders']) }}</div>
            </div>
            <div class="bg-white rounded-xl p-3 sm:p-4 border border-gray-200 shadow-sm">
                <div class="text-gray-500 text-xs sm:text-sm">Eşleşen</div>
                <div class="text-xl sm:text-2xl font-bold text-green-600">{{ number_format($stats['matched_orders']) }}</div>
            </div>
            <div class="bg-white rounded-xl p-3 sm:p-4 border border-gray-200 shadow-sm">
                <div class="text-gray-500 text-xs sm:text-sm">Hatalı</div>
                <div class="text-xl sm:text-2xl font-bold text-red-600">{{ number_format($stats['error_count']) }}</div>
            </div>
            <div class="bg-white rounded-xl p-3 sm:p-4 border border-purple-200 shadow-sm">
                <div class="text-purple-500 text-xs sm:text-sm">↩️ İade/Değişim</div>
                <div class="text-xl sm:text-2xl font-bold text-purple-600">{{ number_format($stats['iade_count']) }}</div>
                @if(($stats['iade_tutar'] ?? 0) > 0)
                    <div class="text-xs text-purple-400 mt-1">{{ number_format($stats['iade_tutar'], 2) }} ₺</div>
                @endif
            </div>
            <div class="bg-white rounded-xl p-3 sm:p-4 border border-orange-200 shadow-sm">
                <div class="text-orange-500 text-xs sm:text-sm">🔧 Parça Gönderisi</div>
                <div class="text-xl sm:text-2xl font-bold text-orange-600">{{ number_format($stats['parca_count'] ?? 0) }}</div>
                @if(($stats['parca_tutar'] ?? 0) > 0)
                    <div class="text-xs text-orange-400 mt-1">{{ number_format($stats['parca_tutar'], 2) }} ₺</div>
                @endif
            </div>
            <div class="bg-white rounded-xl p-3 sm:p-4 border border-gray-200 shadow-sm">
                <div class="text-gray-500 text-xs sm:text-sm">Tutar Farkı</div>
                <div class="text-xl sm:text-2xl font-bold {{ $stats['total_tutar_diff'] > 0 ? 'text-red-600' : 'text-green-600' }}">
                    {{ $stats['total_tutar_diff'] > 0 ? '+' : '' }}{{ number_format($stats['total_tutar_diff'], 2) }} ₺
                </div>
            </div>
        </div>

        {{-- Desi Karşılaştırma --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 lg:gap-4">
            <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-3 sm:p-4 border border-blue-200">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-blue-800 font-medium">Beklenen Toplam Desi</span>
                    <span class="text-xl font-bold text-blue-900">{{ number_format($stats['total_expected_desi'] ?? 0, 2) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-blue-800 font-medium">Gerçek Toplam Desi</span>
                    <span class="text-xl font-bold text-blue-900">{{ number_format($stats['total_actual_desi'] ?? 0, 2) }}</span>
                </div>
                <hr class="my-2 border-blue-200">
                <div class="flex justify-between items-center">
                    <span class="text-blue-800 font-medium">Desi Farkı</span>
                    <span class="text-xl font-bold {{ ($stats['total_desi_diff'] ?? 0) > 0 ? 'text-red-600' : 'text-green-600' }}">
                        {{ ($stats['total_desi_diff'] ?? 0) > 0 ? '+' : '' }}{{ number_format($stats['total_desi_diff'] ?? 0, 2) }}
                    </span>
                </div>
            </div>
            <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl p-3 sm:p-4 border border-green-200">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-green-800 font-medium">Beklenen Toplam Tutar</span>
                    <span class="text-xl font-bold text-green-900">{{ number_format($stats['total_expected_tutar'] ?? 0, 2) }} ₺</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-green-800 font-medium">Gerçek Toplam Tutar</span>
                    <span class="text-xl font-bold text-green-900">{{ number_format($stats['total_actual_tutar'] ?? 0, 2) }} ₺</span>
                </div>
                <hr class="my-2 border-green-200">
                <div class="flex justify-between items-center">
                    <span class="text-green-800 font-medium">Tutar Farkı</span>
                    <span class="text-xl font-bold {{ ($stats['total_tutar_diff'] ?? 0) > 0 ? 'text-red-600' : 'text-green-600' }}">
                        {{ ($stats['total_tutar_diff'] ?? 0) > 0 ? '+' : '' }}{{ number_format($stats['total_tutar_diff'] ?? 0, 2) }} ₺
                    </span>
                </div>
            </div>
        </div>

        {{-- Filtreler --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4 bg-gray-50 rounded-lg p-3 lg:p-4">
            <div class="flex-1">
                <input 
                    type="text" 
                    wire:model.live.debounce.300ms="searchCustomer"
                    placeholder="Müşteri adı ara..."
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-base sm:text-sm min-h-[44px]"
                >
            </div>
            <select 
                wire:model.live="filterErrorType"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-base sm:text-sm min-h-[44px]"
            >
                @foreach($this->errorTypes as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <select 
                wire:model.live="filterMatched"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-base sm:text-sm min-h-[44px]"
            >
                <option value="all">Tüm Eşleşmeler</option>
                <option value="matched">Eşleşenler</option>
                <option value="unmatched">Eşleşmeyenler</option>
            </select>
            <select 
                wire:model.live="filterType"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-base sm:text-sm min-h-[44px]"
            >
                <option value="siparis">📦 Siparişler</option>
                <option value="iade">↩️ İade/Değişim</option>
                <option value="parca">🔧 Parça Gönderileri</option>
                <option value="all">Tümü</option>
            </select>
        </div>

        {{-- Sonuç Tablosu --}}
        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full divide-y divide-gray-200 table-fixed">
                    <thead class="bg-gray-800 text-white">
                        <tr>
                            <th class="px-2 py-2 text-left font-medium text-xs whitespace-nowrap w-[15%] sm:w-20">Tarih</th>
                            <th class="px-2 py-2 text-left font-medium text-xs whitespace-nowrap w-[15%] hidden lg:table-cell">Müşteri</th>
                            <th class="px-2 py-2 text-left font-medium text-xs whitespace-nowrap w-[12%] hidden xl:table-cell">Takip</th>
                            <th class="px-2 py-2 text-left font-medium text-xs whitespace-nowrap w-[35%] sm:w-[25%]">Ürün</th>
                            <th class="px-1 py-2 text-center font-medium text-xs whitespace-nowrap w-10 hidden sm:table-cell">Adet</th>
                            <th class="px-1 py-2 text-center font-medium text-xs whitespace-nowrap w-10">Parça</th>
                            <th class="px-1 py-2 text-center font-medium text-xs whitespace-nowrap hidden sm:table-cell">B.Desi</th>
                            <th class="px-1 py-2 text-center font-medium text-xs whitespace-nowrap">G.Desi</th>
                            <th class="px-1 py-2 text-center font-medium text-xs whitespace-nowrap hidden sm:table-cell">B.Tutar</th>
                            <th class="px-1 py-2 text-center font-medium text-xs whitespace-nowrap">G.Tutar</th>
                            <th class="px-1 py-2 text-center font-medium text-xs whitespace-nowrap w-24">Durum</th>
                            <th class="px-1 py-2 text-center font-medium text-xs w-12 whitespace-nowrap">İşlem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($this->filteredItems as $item)
                            <tr class="{{ 
                                $item->is_iade 
                                    ? 'bg-purple-50 hover:bg-purple-100' 
                                    : ($item->is_parca_gonderi 
                                        ? 'bg-orange-50 hover:bg-orange-100'
                                        : ($item->has_error 
                                            ? ($item->isAgainstUs() ? 'bg-red-50' : 'bg-yellow-50') 
                                            : 'hover:bg-gray-50')) 
                            }}">
                                <td class="px-2 py-2 whitespace-nowrap text-gray-500 text-xs truncate">
                                    {{ $item->tarih?->format('d.m.Y') ?? '-' }}
                                </td>
                                <td class="px-2 py-2 hidden lg:table-cell group relative">
                                    {{-- Müşteri Adı + Sipariş Detay Tooltip --}}
                                    <div class="relative" x-data="{ showTooltip: false }">
                                        <div class="truncate cursor-help" @mouseenter="showTooltip = true" @mouseleave="showTooltip = false">
                                            <span class="font-medium text-gray-900 text-xs border-b border-dashed border-gray-400">{{ $item->musteri_adi }}</span>
                                        </div>
                                        
                                        {{-- Tooltip Popup --}}
                                        @if($item->siparis_detay && count($item->siparis_detay) > 0)
                                            <div 
                                                x-show="showTooltip"
                                                x-transition:enter="transition ease-out duration-200"
                                                x-transition:enter-start="opacity-0 scale-95"
                                                x-transition:enter-end="opacity-100 scale-100"
                                                x-transition:leave="transition ease-in duration-150"
                                                x-transition:leave-start="opacity-100 scale-100"
                                                x-transition:leave-end="opacity-0 scale-95"
                                                class="absolute z-[100] left-0 mt-2 w-80 bg-gray-900 text-white rounded-lg shadow-xl p-3 text-xs whitespace-normal"
                                                style="display: none;"
                                            >
                                                <div class="font-bold text-yellow-400 mb-2 flex items-center gap-2">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                                    </svg>
                                                    Sipariş Detayı
                                                </div>
                                                <table class="w-full">
                                                    <thead>
                                                        <tr class="text-gray-400 border-b border-gray-700">
                                                            <th class="text-left py-1">Stok Kodu</th>
                                                            <th class="text-left py-1">Ürün</th>
                                                            <th class="text-center py-1">Adet</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($item->siparis_detay as $detay)
                                                            <tr class="{{ isset($detay['not_found']) ? 'text-red-400' : '' }}">
                                                                <td class="py-1 font-mono text-[10px]">{{ Str::limit($detay['stok_kodu'] ?? '-', 10) }}</td>
                                                                <td class="py-1">{{ Str::limit($detay['urun_adi'] ?? '-', 20) }}</td>
                                                                <td class="py-1 text-center">{{ $detay['adet'] ?? 1 }}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-2 py-2 hidden xl:table-cell truncate">
                                    @if($item->tracking_url)
                                        <a href="{{ $item->tracking_url }}" target="_blank" 
                                           title="{{ $item->takip_kodu }}"
                                           class="text-blue-600 hover:text-blue-800 hover:underline font-mono text-xs block truncate">
                                            {{ $item->takip_kodu }}
                                            <svg class="w-3 h-3 inline ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                            </svg>
                                        </a>
                                    @else
                                        <span class="font-mono text-xs text-gray-500" title="{{ $item->takip_kodu }}">{{ Str::limit($item->takip_kodu, 15) }}</span>
                                    @endif
                                </td>
                                <td class="px-2 py-2 truncate max-w-0">
                                    <div class="truncate">
                                        <span class="text-gray-900 text-xs font-medium" title="{{ $item->urun_adi }}">{{ $item->urun_adi }}</span>
                                    </div>
                                    <div class="truncate lg:hidden text-[10px] text-gray-500">
                                        {{ $item->musteri_adi }}
                                    </div>
                                </td>
                                <td class="px-1 py-2 text-center text-xs hidden sm:table-cell">{{ $item->adet }}</td>
                                <td class="px-1 py-2 text-center text-xs">
                                    <span class="{{ $item->parca_fark != 0 ? 'text-red-600 font-bold' : '' }}">
                                        {{ $item->gercek_parca }}
                                    </span>
                                </td>
                                <td class="px-1 py-2 text-center text-gray-600 text-xs hidden sm:table-cell">{{ number_format($item->beklenen_desi, 1) }}</td>
                                <td class="px-1 py-2 text-center text-xs {{ $item->desi_fark > 2 ? 'text-red-600 font-bold' : ($item->desi_fark < -2 ? 'text-yellow-600 font-bold' : '') }}">
                                    {{ number_format($item->gercek_desi, 1) }}
                                </td>
                                <td class="px-1 py-2 text-center text-gray-600 text-xs hidden sm:table-cell">{{ number_format($item->beklenen_tutar, 0) }}</td>
                                <td class="px-1 py-2 text-center text-xs {{ $item->tutar_fark > 5 ? 'text-red-600 font-bold' : ($item->tutar_fark < -5 ? 'text-yellow-600 font-bold' : '') }}">
                                    {{ number_format($item->gercek_tutar, 0) }}
                                </td>
                                <td class="px-1 py-2 text-center">
                                    @php $errorInfo = $item->error_info; @endphp
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-medium whitespace-nowrap
                                        {{ $errorInfo['color'] === 'red' ? 'bg-red-100 text-red-800' : 
                                           ($errorInfo['color'] === 'yellow' ? 'bg-yellow-100 text-yellow-800' : 
                                           ($errorInfo['color'] === 'green' ? 'bg-green-100 text-green-800' : 
                                           'bg-gray-100 text-gray-800')) }}">
                                        {{ Str::limit($errorInfo['label'], 10) }}
                                    </span>
                                </td>
                                <td class="px-1 py-2 text-center">
                                    @if(auth()->user()?->isAdmin() && $item->stok_kodu && !$item->is_iade)
                                        <button 
                                            wire:click="openProductEditModal('{{ $item->stok_kodu }}', {{ $item->gercek_desi }}, {{ $item->gercek_tutar }}, {{ $item->gercek_parca }})"
                                            class="p-1 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors"
                                            title="Güncelle"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                        </button>
                                    @else
                                        <span class="text-gray-300 text-xs">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="px-4 py-8 text-center text-gray-500">
                                    Sonuç bulunamadı
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if(method_exists($this->filteredItems, 'hasPages') && $this->filteredItems->hasPages())
                <div class="px-4 py-3 border-t border-gray-200">
                    {{ $this->filteredItems->links() }}
                </div>
            @endif
        </div>
    @endif

    {{-- Ürün Düzenleme Modalı --}}
    @if($showEditModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                {{-- Backdrop --}}
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="closeProductEditModal"></div>

                {{-- Modal Panel --}}
                <div class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900" id="modal-title">
                                ⚡ Hızlı Ürün Güncelleme
                            </h3>
                            <p class="text-sm text-gray-500 mt-1">
                                Kargo raporundaki gerçek değerleri ürün listesine uygula
                            </p>
                        </div>
                        <button wire:click="closeProductEditModal" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <div class="space-y-4">
                        {{-- Stok Kodu --}}
                        <div class="bg-gray-50 rounded-lg p-3">
                            <label class="block text-sm font-medium text-gray-700">Stok Kodu</label>
                            <div class="text-lg font-mono font-bold text-gray-900 mt-1">{{ $editStokKodu }}</div>
                        </div>

                        <div class="grid grid-cols-3 gap-4">
                            {{-- Desi --}}
                            <div>
                                <label for="editDesi" class="block text-sm font-medium text-gray-700">Desi</label>
                                <input type="number" step="0.01" wire:model="editDesi" id="editDesi"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-center font-bold">
                            </div>

                            {{-- Parça --}}
                            <div>
                                <label for="editParca" class="block text-sm font-medium text-gray-700">Parça</label>
                                <input type="number" wire:model="editParca" id="editParca" min="1"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-center font-bold">
                            </div>

                            {{-- Tutar --}}
                            <div>
                                <label for="editTutar" class="block text-sm font-medium text-gray-700">Tutar (₺)</label>
                                <input type="number" step="0.01" wire:model="editTutar" id="editTutar"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-center font-bold">
                            </div>
                        </div>

                        {{-- Bilgi Notu --}}
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-sm text-blue-700">
                            <div class="flex items-start gap-2">
                                <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                          d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span>Bu değerler ürün listesine kaydedilecek ve sonraki karşılaştırmalarda "Beklenen" olarak kullanılacak.</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex flex-col sm:flex-row justify-end gap-3">
                        <button type="button" wire:click="closeProductEditModal"
                                class="w-full sm:w-auto px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                            İptal
                        </button>
                        <button type="button" wire:click="updateProductFromModal"
                                class="w-full sm:w-auto px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex justify-center items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Güncelle
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
