<div class="px-4 lg:px-6 py-6 mx-auto max-w-7xl" x-data="{ viewMode: 'table' }">

    {{-- ═══ HEADER ═══ --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h2 class="text-xl lg:text-2xl font-bold tracking-tight text-gray-900 flex items-center gap-2">
                📦 Pazaryeri Ürünlerim
            </h2>
            <p class="mt-1 text-sm lg:text-base text-gray-500">
                Ürün kataloğunuzu yönetin, Trendyol listesini import edin ve maliyetlerinizi takip edin.
            </p>
        </div>
        <div class="flex flex-col sm:flex-row gap-2">
            <button wire:click="openImportModal" class="w-full sm:w-auto px-4 py-3 sm:py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                Excel İçe Aktar
            </button>
            <button wire:click="exportExcel" class="w-full sm:w-auto px-4 py-3 sm:py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Dışa Aktar
            </button>
            <button wire:click="openCreateModal" class="w-full sm:w-auto px-4 py-3 sm:py-2 text-sm font-medium text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 shadow-sm transition-colors flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Yeni Ürün
            </button>
        </div>
    </div>

    {{-- ═══ TOAST ═══ --}}
    @if (session()->has('success'))
        <div class="mb-4 rounded-lg bg-green-50 p-4 border border-green-200 shadow-sm flex items-start gap-3" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" x-transition>
            <svg class="h-5 w-5 text-green-400 mt-0.5 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
            <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
        </div>
    @endif

    {{-- ═══ İSTATİSTİK KARTLARI ═══ --}}
    @php $s = $this->stats; @endphp
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 lg:gap-4 mb-6">
        <div class="bg-white rounded-xl border border-gray-200 p-4 lg:p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs sm:text-sm font-medium text-gray-500">Toplam Ürün</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($s['total']) }}</p>
                </div>
                <div class="w-10 h-10 bg-indigo-50 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                </div>
            </div>
            <p class="text-xs text-gray-400 mt-2">{{ $s['active'] }} aktif · {{ $s['out_of_stock'] }} tükenen</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 lg:p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs sm:text-sm font-medium text-gray-500">Toplam Stok</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($s['total_stock']) }}</p>
                </div>
                <div class="w-10 h-10 bg-emerald-50 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                </div>
            </div>
            <p class="text-xs text-gray-400 mt-2">Stok değeri: {{ number_format($s['stock_value'], 0, ',', '.') }} ₺</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 lg:p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs sm:text-sm font-medium text-gray-500">Ort. Maliyet</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($s['avg_cogs'], 2, ',', '.') }} ₺</p>
                </div>
                <div class="w-10 h-10 bg-amber-50 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
            <p class="text-xs text-gray-400 mt-2">{{ $s['with_cost'] }} tanımlı · {{ $s['without_cost'] }} eksik</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 lg:p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs sm:text-sm font-medium text-gray-500">Maliyet Kapsam</p>
                    <p class="text-2xl font-bold {{ $s['total'] > 0 && ($s['with_cost'] / $s['total'] * 100) >= 80 ? 'text-emerald-600' : 'text-amber-600' }} mt-1">
                        %{{ $s['total'] > 0 ? round($s['with_cost'] / $s['total'] * 100) : 0 }}
                    </p>
                </div>
                <div class="w-10 h-10 bg-purple-50 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                </div>
            </div>
            <p class="text-xs text-gray-400 mt-2">COGS tanımlı ürün oranı</p>
        </div>
    </div>

    {{-- ═══ FİLTRE ÇUBUĞU ═══ --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 lg:p-5 mb-4">
        {{-- Arama --}}
        <div class="flex flex-col sm:flex-row gap-3 lg:gap-4">
            <div class="flex-1 relative">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Ürün adı, barkod, stok kodu veya model kodu..."
                       class="w-full pl-10 pr-4 py-2 text-base sm:text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div class="flex gap-2">
                {{-- Görünüm toggle --}}
                <button @click="viewMode = viewMode === 'table' ? 'card' : 'table'" class="px-3 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-gray-500" title="Görünüm değiştir">
                    <svg x-show="viewMode === 'table'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                    <svg x-show="viewMode === 'card'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                </button>
                <button wire:click="resetFilters" class="px-3 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-xs sm:text-sm text-gray-600">
                    Sıfırla
                </button>
            </div>
        </div>

        {{-- Filtreler --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 mt-3">
            <select wire:model.live="filterStatus" class="w-full px-3 py-2 text-base sm:text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                <option value="all">Tüm Durumlar</option>
                <option value="active">Satışta</option>
                <option value="out_of_stock">Tükendi</option>
                <option value="pending">Onay Bekliyor</option>
                <option value="suspended">Beklemede</option>
            </select>
            <select wire:model.live="filterCategory" class="w-full px-3 py-2 text-base sm:text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                <option value="all">Tüm Kategoriler</option>
                @foreach($this->categories as $cat)
                    <option value="{{ $cat }}">{{ $cat }}</option>
                @endforeach
            </select>
            <select wire:model.live="filterBrand" class="w-full px-3 py-2 text-base sm:text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                <option value="all">Tüm Markalar</option>
                @foreach($this->brands as $b)
                    <option value="{{ $b }}">{{ $b }}</option>
                @endforeach
            </select>
            <select wire:model.live="filterStockLevel" class="w-full px-3 py-2 text-base sm:text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                <option value="all">Tüm Stok Seviyeleri</option>
                <option value="in_stock">Stokta (>10)</option>
                <option value="critical">Kritik (1-10)</option>
                <option value="out_of_stock">Tükendi (0)</option>
            </select>
            <select wire:model.live="filterCostDefined" class="w-full px-3 py-2 text-base sm:text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                <option value="all">Maliyet Durumu</option>
                <option value="yes">COGS Tanımlı</option>
                <option value="no">COGS Eksik</option>
            </select>
        </div>
    </div>

    {{-- ═══ TOPLU İŞLEM ÇUBUĞU ═══ --}}
    @if(count($selectedProducts) > 0)
    <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-3 mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <span class="text-sm font-medium text-indigo-800">{{ count($selectedProducts) }} ürün seçildi</span>
        <div class="flex gap-2">
            <button wire:click="bulkUpdateStatus('active')" class="px-3 py-1.5 text-xs font-medium bg-emerald-100 text-emerald-700 rounded-md hover:bg-emerald-200">Satışa Al</button>
            <button wire:click="bulkUpdateStatus('suspended')" class="px-3 py-1.5 text-xs font-medium bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200">Beklet</button>
            <button wire:click="bulkDelete" wire:confirm="Seçili {{ count($selectedProducts) }} ürünü silmek istediğinize emin misiniz?" class="px-3 py-1.5 text-xs font-medium bg-red-100 text-red-700 rounded-md hover:bg-red-200">Sil</button>
        </div>
    </div>
    @endif

    {{-- ═══ TABLO GÖRÜNÜMÜ ═══ --}}
    <div x-show="viewMode === 'table'" class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden mb-4">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs text-gray-700 uppercase bg-gray-50/80 border-b border-gray-200">
                    <tr>
                        <th class="px-4 py-3 w-8"><input type="checkbox" wire:model.live="selectAll" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"></th>
                        <th class="px-4 py-3 font-semibold cursor-pointer hover:text-indigo-600" wire:click="sortBy('product_name')">
                            Ürün
                            @if($sortField === 'product_name') <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
                        </th>
                        <th class="px-4 py-3 font-semibold text-right cursor-pointer hover:text-indigo-600" wire:click="sortBy('sale_price')">
                            Fiyat
                            @if($sortField === 'sale_price') <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
                        </th>
                        <th class="px-4 py-3 font-semibold text-right cursor-pointer hover:text-indigo-600" wire:click="sortBy('cogs')">
                            COGS
                            @if($sortField === 'cogs') <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
                        </th>
                        <th class="px-4 py-3 font-semibold text-center cursor-pointer hover:text-indigo-600" wire:click="sortBy('stock_quantity')">
                            Stok
                            @if($sortField === 'stock_quantity') <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
                        </th>
                        <th class="px-4 py-3 font-semibold text-center">KDV</th>
                        <th class="px-4 py-3 font-semibold text-center">Durum</th>
                        <th class="px-4 py-3 font-semibold text-right">İşlem</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($this->products as $p)
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-4 py-3"><input type="checkbox" value="{{ $p->id }}" wire:model.live="selectedProducts" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"></td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                @if($p->main_image)
                                    <img src="{{ $p->main_image }}" alt="" class="w-10 h-10 rounded-lg object-cover border border-gray-200 flex-shrink-0">
                                @else
                                    <div class="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center flex-shrink-0">
                                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    </div>
                                @endif
                                <div class="min-w-0">
                                    <div class="font-medium text-gray-900 truncate max-w-[240px]">{{ $p->product_name ?? 'İsimsiz Ürün' }}</div>
                                    <div class="text-xs text-gray-500 flex items-center gap-2 mt-0.5">
                                        <span title="Barkod">{{ $p->barcode }}</span>
                                        @if($p->stock_code && $p->stock_code !== $p->barcode)
                                            <span class="text-gray-300">·</span>
                                            <span title="Stok Kodu">{{ $p->stock_code }}</span>
                                        @endif
                                    </div>
                                    @if($p->category_name)
                                        <span class="text-xs text-indigo-600">{{ $p->category_name }}</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if($p->sale_price > 0)
                                <div class="font-medium text-gray-900">{{ number_format($p->sale_price, 2, ',', '.') }} ₺</div>
                                @if($p->commission_rate > 0)
                                    <div class="text-xs text-gray-400">%{{ number_format($p->commission_rate, 1) }} kom.</div>
                                @endif
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if($p->cogs > 0)
                                <div class="font-medium text-gray-900">{{ number_format($p->cogs, 2, ',', '.') }} ₺</div>
                                @if($p->packaging_cost > 0)
                                    <div class="text-xs text-gray-400">+{{ number_format($p->packaging_cost, 2, ',', '.') }} ambalaj</div>
                                @endif
                            @else
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-amber-50 text-amber-600">Eksik</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $p->stock_level_color }}">
                                {{ number_format($p->stock_quantity) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $p->vat_rate == 10 ? 'bg-blue-100 text-blue-800' : ($p->vat_rate == 1 ? 'bg-purple-100 text-purple-800' : 'bg-orange-100 text-orange-800') }}">
                                %{{ number_format($p->vat_rate, 0) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $p->status_color }}">
                                {{ $p->status_label }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <button wire:click="editProduct({{ $p->id }})" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium mr-2">Düzenle</button>
                            <button wire:click="deleteProduct({{ $p->id }})" wire:confirm="Bu ürünü silmek istediğinize emin misiniz?" class="text-red-600 hover:text-red-900 text-sm font-medium">Sil</button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center text-gray-400">
                            <div class="mx-auto w-12 h-12 mb-3 text-gray-300">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                            </div>
                            <p>Henüz ürün bulunmuyor.</p>
                            <p class="text-xs mt-1">Excel dosyası import edin veya manuel ekleyin.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ═══ KART GÖRÜNÜMÜ ═══ --}}
    <div x-show="viewMode === 'card'" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3 lg:gap-4 mb-4">
        @forelse($this->products as $p)
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-4 hover:shadow-md transition-shadow">
            <div class="flex items-start gap-3">
                @if($p->main_image)
                    <img src="{{ $p->main_image }}" alt="" class="w-14 h-14 rounded-lg object-cover border border-gray-200 flex-shrink-0">
                @else
                    <div class="w-14 h-14 rounded-lg bg-gray-100 flex items-center justify-center flex-shrink-0">
                        <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                @endif
                <div class="flex-1 min-w-0">
                    <h3 class="font-medium text-gray-900 text-sm truncate">{{ $p->product_name ?? 'İsimsiz' }}</h3>
                    <p class="text-xs text-gray-500 mt-0.5">{{ $p->barcode }}</p>
                    <div class="flex items-center gap-1.5 mt-1">
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium {{ $p->status_color }}">{{ $p->status_label }}</span>
                        @if($p->category_name)
                            <span class="text-xs text-indigo-600">{{ $p->category_name }}</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="grid grid-cols-3 gap-2 mt-3 pt-3 border-t border-gray-100">
                <div>
                    <p class="text-xs text-gray-500">Fiyat</p>
                    <p class="text-sm font-medium text-gray-900">{{ $p->sale_price > 0 ? number_format($p->sale_price, 0, ',', '.') . ' ₺' : '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">COGS</p>
                    <p class="text-sm font-medium {{ $p->cogs > 0 ? 'text-gray-900' : 'text-amber-600' }}">{{ $p->cogs > 0 ? number_format($p->cogs, 0, ',', '.') . ' ₺' : 'Eksik' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Stok</p>
                    <p class="text-sm font-medium"><span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs {{ $p->stock_level_color }}">{{ number_format($p->stock_quantity) }}</span></p>
                </div>
            </div>
            <div class="flex justify-end gap-2 mt-3">
                <button wire:click="editProduct({{ $p->id }})" class="text-xs text-indigo-600 hover:text-indigo-900 font-medium">Düzenle</button>
                <button wire:click="deleteProduct({{ $p->id }})" wire:confirm="Bu ürünü silmek istediğinize emin misiniz?" class="text-xs text-red-600 hover:text-red-900 font-medium">Sil</button>
            </div>
        </div>
        @empty
        <div class="col-span-full text-center py-12 text-gray-400">
            <p>Henüz ürün bulunmuyor.</p>
        </div>
        @endforelse
    </div>

    {{-- ═══ SAYFALAMA ═══ --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="flex items-center gap-2">
            <label class="text-xs sm:text-sm text-gray-600">Sayfa başı:</label>
            <select wire:model.live="perPage" class="text-base sm:text-sm border border-gray-300 rounded-lg px-2 py-1 focus:ring-2 focus:ring-indigo-500">
                <option value="10">10</option>
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
        </div>
        {{ $this->products->links() }}
    </div>

    @include('livewire.partials.mp-products-edit-modal')
    @include('livewire.partials.mp-products-import-modal')
</div>
