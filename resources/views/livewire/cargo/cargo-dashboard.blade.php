<div class="space-y-6 overflow-x-hidden" wire:key="dashboard-{{ $period }}">
    {{-- Başlık ve Dönem Seçimi --}}
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h2 class="text-xl lg:text-2xl font-bold text-gray-900">📊 Kargo Dashboard</h2>
            <p class="text-gray-500 text-sm lg:text-base">Operasyonel maliyet ve trend analizi</p>
        </div>

        {{-- Dönem Seçimi --}}
        <div class="flex flex-wrap items-center gap-2 bg-gray-100 rounded-lg p-1 w-full md:w-auto">
            <button 
                wire:click="$set('period', 'today')"
                class="flex-1 sm:flex-none px-3 py-1.5 rounded-md text-sm font-medium transition {{ $period === 'today' ? 'bg-white shadow text-blue-600' : 'text-gray-600 hover:text-gray-900' }}"
            >
                Bugün
            </button>
            <button 
                wire:click="$set('period', '7days')"
                class="flex-1 sm:flex-none px-3 py-1.5 rounded-md text-sm font-medium transition {{ $period === '7days' ? 'bg-white shadow text-blue-600' : 'text-gray-600 hover:text-gray-900' }}"
            >
                7 Gün
            </button>
            <button 
                wire:click="$set('period', '30days')"
                class="flex-1 sm:flex-none px-3 py-1.5 rounded-md text-sm font-medium transition {{ $period === '30days' ? 'bg-white shadow text-blue-600' : 'text-gray-600 hover:text-gray-900' }}"
            >
                30 Gün
            </button>
            <button 
                wire:click="$set('period', 'thisMonth')"
                class="flex-1 sm:flex-none px-3 py-1.5 rounded-md text-sm font-medium transition {{ $period === 'thisMonth' ? 'bg-white shadow text-blue-600' : 'text-gray-600 hover:text-gray-900' }}"
            >
                Bu Ay
            </button>
        </div>
    </div>

    {{-- Özet Kartlar --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-3 lg:gap-4">
        {{-- Toplam Sipariş --}}
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-3 lg:p-4 text-white shadow-lg">
            <div class="text-blue-100 text-xs font-medium">📦 Toplam Sipariş</div>
            <div class="text-xl lg:text-2xl font-bold mt-1">{{ number_format($this->summaryStats['total_orders']) }}</div>
            <div class="text-blue-200 text-xs mt-1">{{ $this->summaryStats['total_reports'] }} rapor</div>
        </div>

        {{-- Eşleşen --}}
        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl p-3 lg:p-4 text-white shadow-lg">
            <div class="text-green-100 text-xs font-medium">✅ Eşleşen</div>
            <div class="text-xl lg:text-2xl font-bold mt-1">{{ number_format($this->summaryStats['matched_orders']) }}</div>
            @php $matchRate = $this->summaryStats['total_orders'] > 0 ? ($this->summaryStats['matched_orders'] / $this->summaryStats['total_orders']) * 100 : 0; @endphp
            <div class="text-green-200 text-xs mt-1">%{{ number_format($matchRate, 1) }} başarı</div>
        </div>

        {{-- Hatalı --}}
        <div class="bg-gradient-to-br from-red-500 to-red-600 rounded-xl p-3 lg:p-4 text-white shadow-lg">
            <div class="text-red-100 text-xs font-medium">⚠️ Hatalı</div>
            <div class="text-xl lg:text-2xl font-bold mt-1">{{ number_format($this->summaryStats['error_count']) }}</div>
            @php $errorRate = $this->summaryStats['total_orders'] > 0 ? ($this->summaryStats['error_count'] / $this->summaryStats['total_orders']) * 100 : 0; @endphp
            <div class="text-red-200 text-xs mt-1">%{{ number_format($errorRate, 1) }} hata</div>
        </div>

        {{-- İade/Değişim --}}
        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl p-3 lg:p-4 text-white shadow-lg">
            <div class="text-purple-100 text-xs font-medium">↩️ İade/Değişim</div>
            <div class="text-xl lg:text-2xl font-bold mt-1">{{ number_format($this->summaryStats['iade_count']) }}</div>
            <div class="text-purple-200 text-xs mt-1">{{ number_format($this->summaryStats['iade_tutar'], 0) }} ₺</div>
        </div>

        {{-- Parça Gönderisi --}}
        <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl p-3 lg:p-4 text-white shadow-lg">
            <div class="text-orange-100 text-xs font-medium">🔧 Parça Gönderisi</div>
            <div class="text-xl lg:text-2xl font-bold mt-1">{{ number_format($this->summaryStats['parca_count']) }}</div>
            <div class="text-orange-200 text-xs mt-1">{{ number_format($this->summaryStats['parca_tutar'], 0) }} ₺</div>
        </div>

        {{-- Net Maliyet --}}
        <div class="bg-gradient-to-br from-gray-700 to-gray-800 rounded-xl p-3 lg:p-4 text-white shadow-lg">
            <div class="text-gray-300 text-xs font-medium">💰 Net Tutar Farkı</div>
            <div class="text-xl lg:text-2xl font-bold mt-1 {{ $this->summaryStats['total_tutar_diff'] > 0 ? 'text-red-400' : 'text-green-400' }}">
                {{ $this->summaryStats['total_tutar_diff'] > 0 ? '+' : '' }}{{ number_format($this->summaryStats['total_tutar_diff'], 0) }} ₺
            </div>
            <div class="text-gray-400 text-xs mt-1">{{ $this->summaryStats['total_tutar_diff'] > 0 ? 'Fazla ödeme' : 'Az ödeme' }}</div>
        </div>
    </div>

    {{-- Bize Karşı vs Lehimize --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 lg:gap-4">
        {{-- Bize Karşı --}}
        <div class="bg-white rounded-xl border border-red-200 p-4 lg:p-5 shadow-sm">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                    <span class="text-xl">📉</span>
                </div>
                <div>
                    <div class="text-red-600 font-semibold">Bize Karşı</div>
                    <div class="text-xs text-gray-500">Fazla ödeme yaptığımız</div>
                </div>
            </div>
            <div class="text-2xl sm:text-3xl font-bold text-red-600">{{ number_format($this->costAnalysis['against_us_tutar'], 0) }} ₺</div>
            <div class="text-sm text-gray-500 mt-1">{{ $this->costAnalysis['against_us_count'] }} adet hata</div>
        </div>

        {{-- Lehimize --}}
        <div class="bg-white rounded-xl border border-green-200 p-4 lg:p-5 shadow-sm">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                    <span class="text-xl">📈</span>
                </div>
                <div>
                    <div class="text-green-600 font-semibold">Lehimize</div>
                    <div class="text-xs text-gray-500">Az ödeme yaptığımız</div>
                </div>
            </div>
            <div class="text-2xl sm:text-3xl font-bold text-green-600">{{ number_format($this->costAnalysis['for_us_tutar'], 0) }} ₺</div>
            <div class="text-sm text-gray-500 mt-1">{{ $this->costAnalysis['for_us_count'] }} adet hata</div>
        </div>

        {{-- Net Sonuç --}}
        <div class="bg-white rounded-xl border-2 {{ $this->costAnalysis['net_cost'] > 0 ? 'border-red-300 bg-red-50' : 'border-green-300 bg-green-50' }} p-4 lg:p-5 shadow-sm sm:col-span-2 lg:col-span-1">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 {{ $this->costAnalysis['net_cost'] > 0 ? 'bg-red-200' : 'bg-green-200' }} rounded-full flex items-center justify-center">
                    <span class="text-xl">💵</span>
                </div>
                <div>
                    <div class="{{ $this->costAnalysis['net_cost'] > 0 ? 'text-red-600' : 'text-green-600' }} font-semibold">Net Sonuç</div>
                    <div class="text-xs text-gray-500">Dönem sonu fark</div>
                </div>
            </div>
            <div class="text-2xl sm:text-3xl font-bold {{ $this->costAnalysis['net_cost'] > 0 ? 'text-red-600' : 'text-green-600' }}">
                {{ $this->costAnalysis['net_cost'] > 0 ? '+' : '' }}{{ number_format($this->costAnalysis['net_cost'], 0) }} ₺
            </div>
            <div class="text-sm {{ $this->costAnalysis['net_cost'] > 0 ? 'text-red-500' : 'text-green-500' }} mt-1">
                {{ $this->costAnalysis['net_cost'] > 0 ? '⚠️ İşletme zararı' : '✅ İşletme karı' }}
            </div>
        </div>
    </div>

    {{-- Alt Bölüm: Detaylı Hata Dağılımı ve Top Ürünler --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 lg:gap-6">
        {{-- Hata Dağılımı - Detaylı --}}
        <div class="lg:col-span-1 bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 bg-gradient-to-r from-gray-800 to-gray-900 text-white">
                <h3 class="text-base lg:text-lg font-semibold flex items-center gap-2">
                    🎯 Hata Tipi Dağılımı
                </h3>
                <p class="text-gray-300 text-xs mt-1">Toplam {{ $this->errorTypeDistribution['total'] }} hata</p>
            </div>
            
            <div class="p-4">
                {{-- Detaylı Liste --}}
                <div class="space-y-3">
                    @forelse($this->errorTypeDistribution['details'] ?? [] as $error)
                        <div class="group">
                            <div class="flex items-center justify-between mb-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-lg">{{ $error['icon'] }}</span>
                                    <span class="text-sm font-medium text-gray-700">{{ $error['name'] }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-bold" style="color: {{ $error['color'] }}">{{ $error['count'] }}</span>
                                    <span class="text-xs text-gray-400">({{ $error['percentage'] }}%)</span>
                                </div>
                            </div>
                            {{-- Progress bar --}}
                            <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                                <div 
                                    class="h-full rounded-full transition-all" 
                                    style="width: {{ $error['percentage'] }}%; background-color: {{ $error['color'] }}"
                                ></div>
                            </div>
                            {{-- Tutar alt satır --}}
                            <div class="flex justify-between text-xs text-gray-400 mt-1">
                                <span>{{ number_format($error['desi'], 1) }} desi fark</span>
                                <span class="font-medium text-gray-600">{{ number_format($error['tutar'], 0) }} ₺</span>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-gray-400 py-4">
                            <span class="text-3xl">✅</span>
                            <p class="mt-2 text-sm">Bu dönemde hata yok</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>


        {{-- En Çok Hatalı Ürünler --}}
        <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 p-4 lg:p-5 shadow-sm">
            <h3 class="text-base lg:text-lg font-semibold text-gray-900 mb-4">🔥 En Çok Hatalı Ürünler (Top 10)</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-2 py-2 sm:px-3 text-left font-medium text-gray-500 text-xs whitespace-nowrap">#</th>
                            <th class="px-2 py-2 sm:px-3 text-left font-medium text-gray-500 text-xs whitespace-nowrap">Stok Kodu</th>
                            <th class="px-2 py-2 sm:px-3 text-left font-medium text-gray-500 text-xs whitespace-nowrap">Ürün</th>
                            <th class="px-2 py-2 sm:px-3 text-center font-medium text-gray-500 text-xs whitespace-nowrap">Hata</th>
                            <th class="px-2 py-2 sm:px-3 text-right font-medium text-gray-500 text-xs whitespace-nowrap">Tutar Farkı</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($this->topErrorProducts as $index => $product)
                            <tr class="hover:bg-gray-50">
                                <td class="px-2 py-2 sm:px-3 text-gray-400 whitespace-nowrap">{{ $index + 1 }}</td>
                                <td class="px-2 py-2 sm:px-3 font-mono text-xs text-gray-700 whitespace-nowrap">{{ $product['stok_kodu'] }}</td>
                                <td class="px-2 py-2 sm:px-3 text-gray-900 whitespace-nowrap">{{ $product['urun_adi'] }}</td>
                                <td class="px-2 py-2 sm:px-3 text-center whitespace-nowrap">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                        {{ $product['error_count'] }}
                                    </span>
                                </td>
                                <td class="px-2 py-2 sm:px-3 text-right font-medium whitespace-nowrap {{ $product['total_tutar_fark'] > 0 ? 'text-red-600' : 'text-green-600' }}">
                                    {{ $product['total_tutar_fark'] > 0 ? '+' : '' }}{{ number_format($product['total_tutar_fark'], 0) }} ₺
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-3 py-8 text-center text-gray-500">
                                    Bu dönemde hatalı ürün bulunamadı
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Operasyonel Maliyet Özeti --}}
    <div class="bg-gradient-to-r from-indigo-600 to-purple-600 rounded-xl p-4 lg:p-6 text-white shadow-lg">
        <h3 class="text-base lg:text-lg font-semibold mb-4">📊 Dönem Operasyonel Maliyet Özeti</h3>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4">
            <div class="bg-white/10 backdrop-blur rounded-lg p-3 lg:p-4">
                <div class="text-indigo-200 text-xs">İade Maliyeti</div>
                <div class="text-xl lg:text-2xl font-bold">{{ number_format($this->summaryStats['iade_tutar'], 0) }} ₺</div>
            </div>
            <div class="bg-white/10 backdrop-blur rounded-lg p-3 lg:p-4">
                <div class="text-indigo-200 text-xs">Parça Maliyeti</div>
                <div class="text-xl lg:text-2xl font-bold">{{ number_format($this->summaryStats['parca_tutar'], 0) }} ₺</div>
            </div>
            <div class="bg-white/10 backdrop-blur rounded-lg p-3 lg:p-4">
                <div class="text-indigo-200 text-xs">Hata Farkı</div>
                <div class="text-xl lg:text-2xl font-bold">{{ number_format($this->costAnalysis['net_cost'], 0) }} ₺</div>
            </div>
            <div class="bg-white/10 backdrop-blur rounded-lg p-3 lg:p-4">
                <div class="text-indigo-200 text-xs">Toplam Kayıp</div>
                @php $totalLoss = $this->summaryStats['iade_tutar'] + $this->summaryStats['parca_tutar'] + max(0, $this->costAnalysis['net_cost']); @endphp
                <div class="text-xl lg:text-2xl font-bold text-yellow-300">{{ number_format($totalLoss, 0) }} ₺</div>
            </div>
        </div>
    </div>
</div>
