<div class="min-h-screen bg-gray-50">
    <!-- Header -->
    <div class="bg-white border-b border-gray-200 px-4 lg:px-6 py-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-xl lg:text-2xl font-bold text-gray-900">Tedarik Raporları</h1>
                <p class="text-xs lg:text-sm text-gray-500 mt-1">Fabrika ekibi için anlık tedarik takibi</p>
            </div>
            <div class="flex items-center gap-2 sm:gap-3">
                <!-- Excel Yükle -->
                <button wire:click="$set('showImportModal', true)"
                    class="flex-1 sm:flex-none inline-flex items-center justify-center px-4 py-3 sm:py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors text-sm">
                    <svg class="w-5 h-5 sm:mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    <span class="hidden sm:inline">Excel Yükle</span>
                </button>
                <!-- Listeyi Güncelle -->
                <button wire:click="$refresh"
                    class="flex-1 sm:flex-none inline-flex items-center justify-center px-4 py-3 sm:py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors text-sm">
                    <svg class="w-5 h-5 sm:mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    <span class="hidden sm:inline">Güncelle</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Flash Messages -->
    @if(session()->has('success'))
    <div class="mx-4 lg:mx-6 mt-4 p-4 bg-green-50 border border-green-200 rounded-lg flex items-center">
        <svg class="w-5 h-5 text-green-500 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span class="text-green-700 text-sm">{{ session('success') }}</span>
    </div>
    @endif

    @if(session()->has('error'))
    <div class="mx-4 lg:mx-6 mt-4 p-4 bg-red-50 border border-red-200 rounded-lg flex items-center">
        <svg class="w-5 h-5 text-red-500 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span class="text-red-700 text-sm">{{ session('error') }}</span>
    </div>
    @endif

    <!-- Stats Cards -->
    <div class="px-4 lg:px-6 py-4">
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4">
            <!-- Bekleyen -->
            <div class="bg-white rounded-xl border border-gray-200 p-4 lg:p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs lg:text-sm text-gray-500">Gönderilmeyi Bekleyen</p>
                        <p class="text-2xl lg:text-3xl font-bold text-gray-900 mt-1">{{ $stats['bekleyen'] }}</p>
                    </div>
                    <div class="w-10 h-10 lg:w-12 lg:h-12 bg-amber-100 rounded-lg flex items-center justify-center">
                        <span class="text-xl lg:text-2xl">⏳</span>
                    </div>
                </div>
            </div>

            <!-- Bugün Gönderilen -->
            <div class="bg-white rounded-xl border border-gray-200 p-4 lg:p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs lg:text-sm text-gray-500">Bugün Gönderilen</p>
                        <p class="text-2xl lg:text-3xl font-bold text-emerald-600 mt-1">{{ $stats['bugun_gonderilen'] }}</p>
                    </div>
                    <div class="w-10 h-10 lg:w-12 lg:h-12 bg-emerald-100 rounded-lg flex items-center justify-center">
                        <span class="text-xl lg:text-2xl">✅</span>
                    </div>
                </div>
            </div>

            <!-- Geciken -->
            <div class="bg-white rounded-xl border border-gray-200 p-4 lg:p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs lg:text-sm text-gray-500">Geciken Tedarik</p>
                        <p class="text-2xl lg:text-3xl font-bold text-red-600 mt-1">{{ $stats['gecikmis'] }}</p>
                    </div>
                    <div class="w-10 h-10 lg:w-12 lg:h-12 bg-red-100 rounded-lg flex items-center justify-center">
                        <span class="text-xl lg:text-2xl">⚠️</span>
                    </div>
                </div>
            </div>

            <!-- Sebebiyet Dağılımı -->
            <div class="bg-white rounded-xl border border-gray-200 p-4 lg:p-5 col-span-2 lg:col-span-1">
                <p class="text-xs lg:text-sm text-gray-500 mb-2">Tedarik Sebebiyeti</p>
                <div class="flex flex-row lg:flex-col xl:flex-row items-center gap-3 lg:gap-2 xl:gap-4 text-xs lg:text-sm">
                    <div class="flex items-center">
                        <span class="w-2.5 h-2.5 lg:w-3 lg:h-3 bg-orange-500 rounded-full mr-1"></span>
                        <span>Kargo: {{ $stats['sebebiyet']['kargo'] }}</span>
                    </div>
                    <div class="flex items-center">
                        <span class="w-2.5 h-2.5 lg:w-3 lg:h-3 bg-yellow-500 rounded-full mr-1"></span>
                        <span>Paket: {{ $stats['sebebiyet']['paketleme'] }}</span>
                    </div>
                    <div class="flex items-center">
                        <span class="w-2.5 h-2.5 lg:w-3 lg:h-3 bg-blue-500 rounded-full mr-1"></span>
                        <span>Üretim: {{ $stats['sebebiyet']['uretim'] }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters & Actions -->
    <div class="px-4 lg:px-6 py-2">
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <!-- Ana Filtreler -->
            <div class="space-y-3 lg:space-y-0 lg:flex lg:flex-wrap lg:items-center lg:gap-4">
                <!-- Arama -->
                <div class="w-full lg:flex-1 lg:min-w-[200px]">
                    <div class="relative">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input type="text" wire:model.live.debounce.300ms="search" 
                            placeholder="Müşteri, sipariş no, ürün ara..."
                            class="w-full pl-10 pr-4 py-3 lg:py-2 text-base lg:text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                    </div>
                </div>

                <!-- Dropdown Filtreler -->
                <div class="grid grid-cols-2 gap-2 lg:flex lg:gap-4">
                    <!-- Durum Filtre -->
                    <select wire:model.live="durumFiltre" class="w-full lg:w-auto px-3 lg:px-4 py-3 lg:py-2 text-base lg:text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500">
                        <option value="hepsi">Tüm Durumlar</option>
                        @foreach($durumOptions as $key => $option)
                        <option value="{{ $key }}">{{ $option['icon'] }} {{ $option['label'] }}</option>
                        @endforeach
                    </select>

                    <!-- Sebebiyet Filtre -->
                    <select wire:model.live="sebebiyetFiltre" class="w-full lg:w-auto px-3 lg:px-4 py-3 lg:py-2 text-base lg:text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500">
                        <option value="hepsi">Tüm Sebebiyetler</option>
                        @foreach($sebebiyetOptions as $key => $option)
                        @if($key !== 'yok')
                        <option value="{{ $key }}">{{ $option['label'] }}</option>
                        @endif
                        @endforeach
                    </select>

                    <!-- Gecikme Filtre -->
                    <select wire:model.live="gecikmeFiltre" class="w-full lg:w-auto px-3 lg:px-4 py-3 lg:py-2 text-base lg:text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500">
                        <option value="hepsi">Tümü</option>
                        <option value="gecikmis">🔴 Gecikenler</option>
                        <option value="zamaninda">🟢 Zamanında</option>
                    </select>

                    <!-- Sıfırla -->
                    <button wire:click="resetFilters" class="px-4 py-3 lg:py-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors text-sm">
                        Sıfırla
                    </button>
                </div>
            </div>

            <!-- Toplu İşlemler -->
            @if(count($selectedIds) > 0)
            <div class="flex flex-wrap items-center gap-2 mt-3 pt-3 border-t border-gray-100">
                <span class="text-sm text-gray-500">{{ count($selectedIds) }} seçili</span>
                <button wire:click="openBulkModal('durum')" 
                    class="px-3 py-2 bg-blue-100 text-blue-700 rounded-lg text-sm hover:bg-blue-200">
                    Durum
                </button>
                <button wire:click="openBulkModal('sebebiyet')" 
                    class="px-3 py-2 bg-yellow-100 text-yellow-700 rounded-lg text-sm hover:bg-yellow-200">
                    Sebebiyet
                </button>
                <button wire:click="openBulkModal('sil')" 
                    class="px-3 py-2 bg-red-100 text-red-700 rounded-lg text-sm hover:bg-red-200">
                    🗑️ Sil
                </button>
            </div>
            @endif

            <!-- Tarih Filtreleme -->
            <div class="flex flex-col lg:flex-row lg:flex-wrap lg:items-center gap-3 mt-4 pt-4 border-t border-gray-100">
                <span class="text-sm text-gray-500 font-medium">📅 Tarih Filtresi:</span>
                
                <div class="grid grid-cols-2 lg:flex gap-2 lg:gap-4">
                    <!-- Tarih Alanı Seçimi -->
                    <select wire:model.live="tarihAlani" class="px-3 py-2.5 lg:py-1.5 text-base lg:text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500">
                        <option value="soz_tarihi">Söz Tarihi</option>
                        <option value="kayit_tarihi">Kayıt Tarihi</option>
                    </select>

                    <!-- Başlangıç Tarihi -->
                    <input type="date" wire:model.live="baslangicTarihi" 
                        class="px-3 py-2.5 lg:py-1.5 text-base lg:text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500"
                        placeholder="Başlangıç">

                    <!-- Bitiş Tarihi -->
                    <input type="date" wire:model.live="bitisTarihi" 
                        class="px-3 py-2.5 lg:py-1.5 text-base lg:text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500"
                        placeholder="Bitiş">

                    <!-- Excel Export Butonu -->
                    <button wire:click="exportExcel" 
                        class="col-span-2 lg:col-span-1 flex items-center justify-center gap-2 px-4 py-2.5 lg:py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors text-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Rapor İndir
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Desktop: Table View (Grouped by Order) -->
    <div class="hidden lg:block px-6 py-4">
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-3 text-left w-10">
                                <input type="checkbox" wire:model.live="selectAll" wire:click="toggleSelectAll"
                                    class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase cursor-pointer hover:bg-gray-100"
                                wire:click="sortBy('kayit_tarihi')">
                                Kayıt Tar.
                                @if($sortField === 'kayit_tarihi')
                                <span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Müşteri</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Tedarik Edilecek Ürün(ler)</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Adet</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase cursor-pointer hover:bg-gray-100"
                                wire:click="sortBy('soz_tarihi')">
                                Söz Tarihi
                                @if($sortField === 'soz_tarihi')
                                <span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Sebebiyet</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Durum</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Etiket</th>
                        </tr>
                    </thead>
                        @forelse($this->groupedOrders as $group)
                        @php 
                            $hasMultipleItems = $group['item_count'] > 1;
                            $firstItem = $group['items']->first();
                        @endphp
                        
                        <!-- Her grup için ayrı tbody (Alpine.js scope için) -->
                        <tbody x-data="{ expanded: false }" class="divide-y divide-gray-100 border-b border-gray-200">
                        
                        <!-- Ana Sipariş Satırı -->
                        <tr class="hover:bg-gray-50 {{ $group['is_gecikmis'] ? 'bg-red-50' : '' }} {{ $hasMultipleItems ? 'cursor-pointer' : '' }}"
                            @if($hasMultipleItems) @click="expanded = !expanded" @endif>
                            
                            <!-- Checkbox -->
                            <td class="px-4 py-3" @click.stop>
                                @foreach($group['items'] as $item)
                                <input type="checkbox" wire:model.live="selectedIds" value="{{ $item->id }}"
                                    class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 {{ !$loop->first ? 'hidden' : '' }}">
                                @endforeach
                            </td>

                            <!-- Kayıt Tarihi -->
                            <td class="px-4 py-3 text-sm text-gray-600">
                                {{ $group['kayit_tarihi']?->format('d.m.Y') ?? '-' }}
                            </td>

                            <!-- Müşteri (Tooltip ile detay) -->
                            <td class="px-4 py-3">
                                <div x-data="{ showTooltip: false }" class="relative" @click.stop>
                                    <span @mouseenter="showTooltip = true" @mouseleave="showTooltip = false"
                                        class="text-sm font-medium text-gray-900 cursor-pointer hover:text-emerald-600 border-b border-dashed border-gray-300">
                                        {{ Str::limit($group['musteri_adi'], 20) }}
                                    </span>
                                    
                                    <!-- Tooltip -->
                                    <div x-show="showTooltip" x-transition
                                        class="absolute z-50 left-0 top-full mt-2 w-64 p-3 bg-gray-900 text-white text-sm rounded-lg shadow-xl">
                                        <div class="space-y-1">
                                            <p class="font-semibold">{{ $group['musteri_adi'] }}</p>
                                            <p class="text-gray-300">{{ $group['tam_adres'] ?: 'Adres bilgisi yok' }}</p>
                                            <p class="text-gray-300">📞 {{ $group['telefon'] ?: 'Telefon yok' }}</p>
                                        </div>
                                        <div class="absolute -top-1 left-4 w-2 h-2 bg-gray-900 rotate-45"></div>
                                    </div>
                                </div>
                            </td>

                            <!-- Ürünler -->
                            <td class="px-4 py-3 max-w-sm">
                                @if($hasMultipleItems)
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex items-center justify-center w-5 h-5 bg-emerald-100 text-emerald-700 rounded-full text-xs font-bold">
                                            {{ $group['item_count'] }}
                                        </span>
                                        <span class="text-xs text-gray-700 leading-tight">
                                            {{ Str::limit($firstItem->urun_adi, 50) }}
                                            <span class="text-gray-400">ve {{ $group['item_count'] - 1 }} ürün daha</span>
                                        </span>
                                        <svg :class="{ 'rotate-180': expanded }" class="w-4 h-4 text-gray-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </div>
                                @else
                                    <span class="text-xs text-gray-700 leading-tight block" title="{{ $firstItem->urun_adi }}">
                                        {{ $firstItem->urun_adi }}
                                    </span>
                                @endif
                            </td>

                            <!-- Toplam Adet -->
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center justify-center w-8 h-8 {{ $hasMultipleItems ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100' }} rounded-full text-sm font-semibold">
                                    {{ $group['total_adet'] }}
                                </span>
                            </td>

                            <!-- Söz Tarihi -->
                            <td class="px-4 py-3 text-sm {{ $group['is_gecikmis'] ? 'text-red-600 font-semibold' : 'text-gray-600' }}">
                                {{ $group['soz_tarihi']?->format('d.m.Y') ?? '-' }}
                                @if($group['is_gecikmis'])
                                <span class="ml-1 text-xs">⚠️</span>
                                @endif
                            </td>

                            <!-- Sebebiyet (ilk ürün için) -->
                            <td class="px-4 py-3 text-center" @click.stop>
                                <select wire:change="updateSebebiyet({{ $firstItem->id }}, $event.target.value)"
                                    class="text-xs px-2 py-1 border border-gray-200 rounded-lg focus:ring-1 focus:ring-emerald-500
                                        {{ $firstItem->sebebiyet === 'uretim' ? 'bg-blue-50 text-blue-700' : '' }}
                                        {{ $firstItem->sebebiyet === 'paketleme' ? 'bg-yellow-50 text-yellow-700' : '' }}
                                        {{ $firstItem->sebebiyet === 'kargo' ? 'bg-orange-50 text-orange-700' : '' }}">
                                    @foreach($sebebiyetOptions as $key => $option)
                                    <option value="{{ $key }}" {{ $firstItem->sebebiyet === $key ? 'selected' : '' }}>
                                        {{ $option['label'] }}
                                    </option>
                                    @endforeach
                                </select>
                            </td>

                            <!-- Durum (ilk ürün için) -->
                            <td class="px-4 py-3 text-center" @click.stop>
                                <select wire:change="updateDurum({{ $firstItem->id }}, $event.target.value)"
                                    class="text-xs px-2 py-1 border border-gray-200 rounded-lg focus:ring-1 focus:ring-emerald-500
                                        {{ $firstItem->durum === 'gonderildi' ? 'bg-green-50 text-green-700' : '' }}
                                        {{ $firstItem->durum === 'kargo' ? 'bg-orange-50 text-orange-700' : '' }}
                                        {{ $firstItem->durum === 'paketleme' ? 'bg-yellow-50 text-yellow-700' : '' }}
                                        {{ $firstItem->durum === 'uretim' ? 'bg-blue-50 text-blue-700' : '' }}">
                                    @foreach($durumOptions as $key => $option)
                                    <option value="{{ $key }}" {{ $firstItem->durum === $key ? 'selected' : '' }}>
                                        {{ $option['icon'] }} {{ $option['label'] }}
                                    </option>
                                    @endforeach
                                </select>
                            </td>

                            <!-- Kargo Etiketi -->
                            <td class="px-4 py-3 text-center" @click.stop>
                                <a href="{{ route('supply.label', $firstItem->id) }}" target="_blank"
                                    class="inline-flex items-center px-2 py-1 bg-gray-100 text-gray-700 rounded-lg text-xs hover:bg-gray-200 transition-colors">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                    </svg>
                                    Etiket
                                </a>
                            </td>
                        </tr>

                        <!-- Alt Ürün Satırları (çoklu ürün varsa) -->
                        @if($hasMultipleItems)
                            @foreach($group['items'] as $item)
                            <tr x-show="expanded" x-transition
                                class="bg-gray-50/50 border-l-4 border-emerald-300">
                                <td class="px-4 py-2" @click.stop>
                                    <input type="checkbox" wire:model.live="selectedIds" value="{{ $item->id }}"
                                        class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                                </td>
                                <td class="px-4 py-2 text-xs text-gray-400">└</td>
                                <td class="px-4 py-2 text-xs text-gray-500">Ürün {{ $loop->iteration }}</td>
                                <td class="px-4 py-2">
                                    <span class="text-xs text-gray-700" title="{{ $item->urun_adi }}">
                                        {{ $item->urun_adi }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-center">
                                    <span class="inline-flex items-center justify-center w-6 h-6 bg-gray-100 rounded-full text-xs font-medium">
                                        {{ $item->adet }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-xs text-gray-500">
                                    {{ $item->soz_tarihi?->format('d.m.Y') ?? '-' }}
                                </td>
                                <td class="px-4 py-2 text-center" @click.stop>
                                    <select wire:change="updateSebebiyet({{ $item->id }}, $event.target.value)"
                                        class="text-xs px-1 py-0.5 border border-gray-200 rounded focus:ring-1 focus:ring-emerald-500
                                            {{ $item->sebebiyet === 'uretim' ? 'bg-blue-50 text-blue-700' : '' }}
                                            {{ $item->sebebiyet === 'paketleme' ? 'bg-yellow-50 text-yellow-700' : '' }}
                                            {{ $item->sebebiyet === 'kargo' ? 'bg-orange-50 text-orange-700' : '' }}">
                                        @foreach($sebebiyetOptions as $key => $option)
                                        <option value="{{ $key }}" {{ $item->sebebiyet === $key ? 'selected' : '' }}>{{ $option['label'] }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-4 py-2 text-center" @click.stop>
                                    <select wire:change="updateDurum({{ $item->id }}, $event.target.value)"
                                        class="text-xs px-1 py-0.5 border border-gray-200 rounded focus:ring-1 focus:ring-emerald-500
                                            {{ $item->durum === 'gonderildi' ? 'bg-green-50 text-green-700' : '' }}
                                            {{ $item->durum === 'kargo' ? 'bg-orange-50 text-orange-700' : '' }}
                                            {{ $item->durum === 'paketleme' ? 'bg-yellow-50 text-yellow-700' : '' }}
                                            {{ $item->durum === 'uretim' ? 'bg-blue-50 text-blue-700' : '' }}">
                                        @foreach($durumOptions as $key => $option)
                                        <option value="{{ $key }}" {{ $item->durum === $key ? 'selected' : '' }}>{{ $option['icon'] }} {{ $option['label'] }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-4 py-2 text-center" @click.stop>
                                    <a href="{{ route('supply.label', $item->id) }}" target="_blank"
                                        class="text-xs text-gray-500 hover:text-gray-700">🏷️</a>
                                </td>
                            </tr>
                            @endforeach
                        @endif
                        </tbody>
                        @empty
                        <tbody>
                        <tr>
                            <td colspan="9" class="px-4 py-12 text-center text-gray-500">
                                <div class="flex flex-col items-center">
                                    <svg class="w-12 h-12 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                                    </svg>
                                    <p class="text-lg font-medium">Henüz tedarik siparişi yok</p>
                                    <p class="text-sm mt-1">Excel dosyası yükleyerek başlayın</p>
                                </div>
                            </td>
                        </tr>
                        </tbody>
                        @endforelse
                </table>
            </div>

            <!-- Pagination -->
            @if($orders->hasPages())
            <div class="px-4 py-3 border-t border-gray-200">
                {{ $orders->links() }}
            </div>
            @endif
        </div>
    </div>

    <!-- Mobile: Card View (Grouped by Order) -->
    <div class="lg:hidden px-4 py-4 space-y-3">
        @forelse($this->groupedOrders as $group)
        @php 
            $hasMultipleItems = $group['item_count'] > 1;
            $firstItem = $group['items']->first();
        @endphp
        <div x-data="{ expanded: false }" 
            class="bg-white rounded-xl border {{ $group['is_gecikmis'] ? 'border-red-300 bg-red-50' : 'border-gray-200' }} overflow-hidden">
            
            <!-- Ana Sipariş Kartı -->
            <div class="p-4" @if($hasMultipleItems) @click="expanded = !expanded" @endif>
                <!-- Üst Kısım: Müşteri + Tarih -->
                <div class="flex items-start gap-3">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between">
                            <p class="font-semibold text-gray-900 truncate">{{ $group['musteri_adi'] }}</p>
                            <span class="text-xs {{ $group['is_gecikmis'] ? 'text-red-600 font-semibold' : 'text-gray-500' }}">
                                {{ $group['soz_tarihi']?->format('d.m') ?? '-' }}
                                @if($group['is_gecikmis']) ⚠️ @endif
                            </span>
                        </div>
                        
                        <!-- Ürün Özeti -->
                        @if($hasMultipleItems)
                            <div class="flex items-center gap-2 mt-2">
                                <span class="inline-flex items-center justify-center w-6 h-6 bg-emerald-100 text-emerald-700 rounded-full text-xs font-bold">
                                    {{ $group['item_count'] }}
                                </span>
                                <p class="text-xs text-gray-600 line-clamp-1">
                                    {{ Str::limit($firstItem->urun_adi, 30) }} ve {{ $group['item_count'] - 1 }} ürün daha
                                </p>
                                <svg :class="{ 'rotate-180': expanded }" class="w-4 h-4 text-gray-400 transition-transform ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </div>
                        @else
                            <p class="text-xs text-gray-600 mt-1 line-clamp-2">{{ $firstItem->urun_adi }}</p>
                        @endif
                        
                        <div class="flex items-center gap-2 mt-2">
                            <span class="inline-flex items-center px-2 py-0.5 {{ $hasMultipleItems ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100' }} rounded text-xs font-medium">
                                {{ $group['total_adet'] }} adet
                            </span>
                            <span class="text-xs text-gray-400">{{ $group['siparis_no'] }}</span>
                        </div>
                    </div>
                </div>

                <!-- Aksiyonlar (ilk ürün için) -->
                <div class="flex items-center gap-2 mt-4 pt-3 border-t border-gray-100" @click.stop>
                    <select wire:change="updateSebebiyet({{ $firstItem->id }}, $event.target.value)"
                        class="flex-1 text-sm px-3 py-2.5 border border-gray-200 rounded-lg
                            {{ $firstItem->sebebiyet === 'uretim' ? 'bg-blue-50 text-blue-700' : '' }}
                            {{ $firstItem->sebebiyet === 'paketleme' ? 'bg-yellow-50 text-yellow-700' : '' }}
                            {{ $firstItem->sebebiyet === 'kargo' ? 'bg-orange-50 text-orange-700' : '' }}">
                        @foreach($sebebiyetOptions as $key => $option)
                        <option value="{{ $key }}" {{ $firstItem->sebebiyet === $key ? 'selected' : '' }}>{{ $option['label'] }}</option>
                        @endforeach
                    </select>

                    <select wire:change="updateDurum({{ $firstItem->id }}, $event.target.value)"
                        class="flex-1 text-sm px-3 py-2.5 border border-gray-200 rounded-lg
                            {{ $firstItem->durum === 'gonderildi' ? 'bg-green-50 text-green-700' : '' }}
                            {{ $firstItem->durum === 'kargo' ? 'bg-orange-50 text-orange-700' : '' }}
                            {{ $firstItem->durum === 'paketleme' ? 'bg-yellow-50 text-yellow-700' : '' }}
                            {{ $firstItem->durum === 'uretim' ? 'bg-blue-50 text-blue-700' : '' }}">
                        @foreach($durumOptions as $key => $option)
                        <option value="{{ $key }}" {{ $firstItem->durum === $key ? 'selected' : '' }}>{{ $option['icon'] }} {{ $option['label'] }}</option>
                        @endforeach
                    </select>

                    <a href="{{ route('supply.label', $firstItem->id) }}" target="_blank"
                        class="px-3 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                        🏷️
                    </a>
                </div>
            </div>

            <!-- Genişletilmiş Ürün Listesi -->
            @if($hasMultipleItems)
            <div x-show="expanded" x-transition class="border-t border-gray-200 bg-gray-50">
                @foreach($group['items'] as $item)
                <div class="p-3 {{ !$loop->last ? 'border-b border-gray-100' : '' }}">
                    <div class="flex items-start gap-2">
                        <span class="text-xs text-gray-400 mt-0.5">{{ $loop->iteration }}.</span>
                        <div class="flex-1">
                            <p class="text-xs text-gray-700">{{ $item->urun_adi }}</p>
                            <div class="flex items-center gap-2 mt-2">
                                <span class="text-xs bg-gray-100 px-2 py-0.5 rounded">{{ $item->adet }} adet</span>
                                <select wire:change="updateDurum({{ $item->id }}, $event.target.value)"
                                    class="text-xs px-2 py-1 border border-gray-200 rounded
                                        {{ $item->durum === 'gonderildi' ? 'bg-green-50 text-green-700' : '' }}
                                        {{ $item->durum === 'kargo' ? 'bg-orange-50 text-orange-700' : '' }}">
                                    @foreach($durumOptions as $key => $option)
                                    <option value="{{ $key }}" {{ $item->durum === $key ? 'selected' : '' }}>{{ $option['icon'] }} {{ $option['label'] }}</option>
                                    @endforeach
                                </select>
                                <a href="{{ route('supply.label', $item->id) }}" target="_blank" class="text-xs text-gray-500">🏷️</a>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>
        @empty
        <div class="bg-white rounded-xl border border-gray-200 p-8 text-center">
            <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
            </svg>
            <p class="text-lg font-medium text-gray-900">Henüz tedarik siparişi yok</p>
            <p class="text-sm text-gray-500 mt-1">Excel dosyası yükleyerek başlayın</p>
        </div>
        @endforelse

        <!-- Mobile Pagination -->
        @if($orders->hasPages())
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            {{ $orders->links() }}
        </div>
        @endif
    </div>

    <!-- Import Modal -->
    @if($showImportModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
        <div class="flex items-end sm:items-center justify-center min-h-screen p-0 sm:p-4">
            <!-- Overlay -->
            <div class="fixed inset-0 bg-black/50" wire:click="$set('showImportModal', false)"></div>
            
            <!-- Modal -->
            <div class="relative bg-white w-full sm:rounded-xl shadow-xl sm:max-w-md p-6 rounded-t-2xl sm:rounded-b-xl">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Excel Dosyası Yükle</h3>
                
                <form wire:submit="importExcel">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Siparişler Excel Dosyası (.xlsx)
                        </label>
                        <input type="file" wire:model="excelFile" accept=".xlsx,.xls"
                            class="w-full px-3 py-3 text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500">
                        @error('excelFile')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4">
                        <p class="text-sm text-blue-700">
                            <strong>Not:</strong> Sadece renk etiketi (BW kolonu) dolu olan satırlar içeri aktarılır.
                        </p>
                    </div>

                    <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
                        <button type="button" wire:click="$set('showImportModal', false)"
                            class="w-full sm:w-auto px-4 py-3 sm:py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">
                            İptal
                        </button>
                        <button type="submit" 
                            class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors flex items-center justify-center"
                            wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="importExcel">İçeri Aktar</span>
                            <span wire:loading wire:target="importExcel" class="flex items-center">
                                <svg class="animate-spin w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Yükleniyor...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    <!-- Bulk Action Modal -->
    @if($showBulkModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
        <div class="flex items-end sm:items-center justify-center min-h-screen p-0 sm:p-4">
            <div class="fixed inset-0 bg-black/50" wire:click="$set('showBulkModal', false)"></div>
            
            <div class="relative bg-white w-full sm:rounded-xl shadow-xl sm:max-w-md p-6 rounded-t-2xl sm:rounded-b-xl">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">
                    @if($bulkAction === 'durum')
                        Toplu Durum Değiştir
                    @elseif($bulkAction === 'sebebiyet')
                        Toplu Sebebiyet Ata
                    @else
                        Siparişleri Sil
                    @endif
                </h3>
                
                <p class="text-sm text-gray-600 mb-4">
                    {{ count($selectedIds) }} sipariş için işlem yapılacak.
                </p>

                @if($bulkAction === 'durum')
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Yeni Durum</label>
                    <select wire:model="bulkDurum" class="w-full px-3 py-3 text-base border border-gray-300 rounded-lg">
                        @foreach($durumOptions as $key => $option)
                        <option value="{{ $key }}">{{ $option['icon'] }} {{ $option['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                @elseif($bulkAction === 'sebebiyet')
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Sebebiyet</label>
                    <select wire:model="bulkSebebiyet" class="w-full px-3 py-3 text-base border border-gray-300 rounded-lg">
                        @foreach($sebebiyetOptions as $key => $option)
                        <option value="{{ $key }}">{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                @else
                <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                    <p class="text-sm text-red-700">
                        ⚠️ <strong>Dikkat!</strong> Bu işlem geri alınamaz. Seçili siparişler kalıcı olarak silinecektir.
                    </p>
                </div>
                @endif

                <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
                    <button type="button" wire:click="$set('showBulkModal', false)"
                        class="w-full sm:w-auto px-4 py-3 sm:py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">
                        İptal
                    </button>
                    @if($bulkAction === 'sil')
                    <button wire:click="bulkDelete"
                        class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                        🗑️ Sil
                    </button>
                    @else
                    <button wire:click="{{ $bulkAction === 'durum' ? 'bulkUpdateDurum' : 'bulkUpdateSebebiyet' }}"
                        class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors">
                        Uygula
                    </button>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif

</div>
