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
                {{-- ⚙️ Kolon Özelleştirme --}}
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open" class="px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors flex items-center gap-1.5 shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                        Kolonlar
                    </button>
                    <div x-show="open" @click.outside="open = false" x-transition class="absolute right-0 top-full mt-1 z-50 w-56 bg-white border border-gray-200 rounded-xl shadow-xl p-3 space-y-1.5">
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Görünür Kolonlar</p>
                        @foreach(\App\Livewire\MpProductsManager::$allColumnDefs as $colKey => $colLabel)
                            <label class="flex items-center gap-2 cursor-pointer hover:bg-gray-50 rounded px-2 py-1.5 transition-colors">
                                <input type="checkbox" wire:click="toggleColumn('{{ $colKey }}')" {{ in_array($colKey, $visibleColumns) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-200 w-4 h-4">
                                <span class="text-sm text-gray-700">{{ $colLabel }}</span>
                            </label>
                        @endforeach
                        <p class="text-[10px] text-gray-400 mt-2 pt-2 border-t">{{ count($visibleColumns) }} / {{ count(\App\Livewire\MpProductsManager::$allColumnDefs) }} kolon gösteriliyor</p>
                    </div>
                </div>
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
    <div x-show="viewMode === 'table'" class="hidden md:block bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden mb-4">
        {{-- Resize & Compact CSS --}}
        <style>
            .col-resize-handle { position: absolute; right: 0; top: 0; bottom: 0; width: 4px; cursor: col-resize; background: transparent; z-index: 10; transition: background 0.15s; }
            .col-resize-handle:hover, .col-resize-handle.active { background: #6366f1; }
            .sortable-th { cursor: pointer; user-select: none; position: relative; }
            .sortable-th:hover { background: #f3f4f6; }
            #productsTable .text-xs { font-size: 11px !important; }
            #productsTable .text-sm { font-size: 13px !important; }
            #productsTable .text-\[10px\] { font-size: 9px !important; }
            #productsTable { table-layout: fixed; width: 100%; }
            #productsTable th, #productsTable td { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        </style>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200" x-data="columnResize()" id="productsTable">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-2 py-3 text-center text-xs font-medium text-gray-500 uppercase" style="width:32px; min-width:32px">
                            <input type="checkbox" wire:model.live="selectAll" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        </th>
                        @php
                            $sortableMap = \App\Livewire\MpProductsManager::$sortableColumns;
                            $colAligns = [
                                'urun' => 'text-left', 'fiyat' => 'text-right', 'cogs' => 'text-right',
                                'kargo' => 'text-center', 'stok' => 'text-center', 'kdv' => 'text-center',
                                'karlilik' => 'text-right', 'durum' => 'text-center', 'islem' => 'text-right',
                            ];
                            $colWidths = [
                                'urun' => '35%', 'fiyat' => '10%', 'cogs' => '9%',
                                'kargo' => '8%', 'stok' => '6%', 'kdv' => '5%',
                                'karlilik' => '8%', 'durum' => '7%', 'islem' => '8%',
                            ];
                        @endphp
                        @foreach(\App\Livewire\MpProductsManager::$allColumnDefs as $colKey => $colLabel)
                            @if(in_array($colKey, $visibleColumns))
                                @php
                                    $isSortable = isset($sortableMap[$colKey]);
                                    $dbCol = $sortableMap[$colKey] ?? null;
                                    $isActive = $dbCol && $sortField === $dbCol;
                                    $align = $colAligns[$colKey] ?? 'text-left';
                                    $width = $colWidths[$colKey] ?? 'auto';
                                @endphp
                                <th class="px-3 py-3 {{ $align }} text-xs font-medium text-gray-500 uppercase {{ $isSortable ? 'sortable-th' : '' }}" style="position:relative; width:{{ $width }}; min-width:40px"
                                    @if($isSortable) wire:click="sortTable('{{ $colKey }}')" @endif>
                                    <div class="flex items-center gap-1 {{ $align === 'text-right' ? 'justify-end' : ($align === 'text-center' ? 'justify-center' : '') }}">
                                        <span>{{ $colLabel }}</span>
                                        @if($isSortable)
                                            @if($isActive)
                                                <span class="text-indigo-600 text-[10px]">{{ $sortDirection === 'asc' ? '▲' : '▼' }}</span>
                                            @else
                                                <span class="text-gray-300 text-[10px]">⇅</span>
                                            @endif
                                        @endif
                                    </div>
                                    <div class="col-resize-handle" @mousedown.stop.prevent="startResize($event, $el.parentElement)"></div>
                                </th>
                            @endif
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($this->products as $p)
                    @php
                        $totalCost = (float)$p->cogs + (float)$p->packaging_cost + (float)$p->cargo_cost;
                        $commissionAmt = (float)$p->sale_price * ((float)$p->commission_rate / 100);
                        $profitTL = $totalCost > 0 ? (float)$p->sale_price - $totalCost - $commissionAmt : null;
                        $profitPct = ((float)$p->cogs > 0 && $profitTL !== null) ? round(($profitTL / (float)$p->cogs) * 100, 1) : null;
                    @endphp
                    <tr class="hover:bg-gray-50/50 transition-colors" wire:key="product-row-{{ $p->id }}"
                        x-data="{
                            salePrice: {{ (float)$p->sale_price }},
                            cogs: {{ (float)$p->cogs }},
                            packagingCost: {{ (float)$p->packaging_cost }},
                            cargoCost: {{ (float)$p->cargo_cost }},
                            commissionRate: {{ (float)$p->commission_rate }},
                            get totalCost() { return this.cogs + this.packagingCost + this.cargoCost; },
                            get commissionAmt() { return this.salePrice * (this.commissionRate / 100); },
                            get profitTL() { return this.totalCost > 0 ? this.salePrice - this.totalCost - this.commissionAmt : null; },
                            get profitPct() { return (this.cogs > 0 && this.profitTL !== null) ? Math.round((this.profitTL / this.cogs) * 1000) / 10 : null; },
                            get hasCost() { return this.cogs > 0; }
                        }">
                        <td class="px-2 py-2"><input type="checkbox" value="{{ $p->id }}" wire:model.live="selectedProducts" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"></td>
                        @if(in_array('urun', $visibleColumns))
                        <td class="px-3 py-2">
                            <div class="flex items-center gap-2">
                                @if($p->main_image)
                                    <img src="{{ $p->main_image }}" alt="" class="w-8 h-8 rounded object-cover border border-gray-200 flex-shrink-0">
                                @else
                                    <div class="w-8 h-8 rounded bg-gray-100 flex items-center justify-center flex-shrink-0">
                                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    </div>
                                @endif
                                <div class="min-w-0">
                                    <div class="font-medium text-gray-900 truncate text-sm" title="{{ $p->product_name }}">{{ $p->product_name ?? 'İsimsiz Ürün' }}</div>
                                    <div class="text-xs text-gray-500">{{ $p->barcode }}@if($p->stock_code && $p->stock_code !== $p->barcode) · {{ $p->stock_code }}@endif</div>
                                </div>
                            </div>
                        </td>
                        @endif
                        @if(in_array('fiyat', $visibleColumns))
                        <td class="px-3 py-2 text-right" x-data="{ editing: false, tempPrice: salePrice, cancelled: false }">
                            <template x-if="!editing">
                                <div class="group cursor-pointer" @click="editing = true; cancelled = false; tempPrice = salePrice; $nextTick(() => { let el = $refs.priceInput_{{ $p->id }}; el.focus(); el.select(); })">
                                    <div class="font-medium text-gray-900 group-hover:text-indigo-600 transition-colors inline-flex items-center gap-1 text-sm">
                                        <span x-text="new Intl.NumberFormat('tr-TR', {minimumFractionDigits: 2}).format(salePrice) + ' ₺'"></span>
                                        <svg class="w-3 h-3 text-gray-400 opacity-0 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                    </div>
                                    @if($p->commission_rate > 0)
                                        <div class="text-xs text-gray-400">%{{ number_format($p->commission_rate, 1) }} kom.</div>
                                    @endif
                                </div>
                            </template>
                            <template x-if="editing">
                                <div class="flex items-center gap-1">
                                    <input type="number" step="0.01" min="0" x-ref="priceInput_{{ $p->id }}" x-model.number="tempPrice"
                                           @input="salePrice = tempPrice || 0"
                                           @keydown.enter="salePrice = tempPrice; editing = false; $wire.updateSalePrice({{ $p->id }}, tempPrice)"
                                           @keydown.escape="cancelled = true; tempPrice = salePrice; editing = false"
                                           @blur="if(!cancelled) { salePrice = tempPrice; $wire.updateSalePrice({{ $p->id }}, tempPrice); } editing = false"
                                           class="w-20 text-right text-sm border border-indigo-300 rounded px-2 py-1 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                    <span class="text-xs text-gray-400">₺</span>
                                </div>
                            </template>
                        </td>
                        @endif
                        @if(in_array('cogs', $visibleColumns))
                        <td class="px-3 py-2 text-right">
                            @if($p->cogs > 0)
                                <div class="font-medium text-gray-900 text-sm">{{ number_format($p->cogs, 2, ',', '.') }} ₺</div>
                                @if($p->packaging_cost > 0)
                                    <div class="text-xs text-gray-400">+{{ number_format($p->packaging_cost, 2, ',', '.') }} amb.</div>
                                @endif
                            @else
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-amber-50 text-amber-600">Eksik</span>
                            @endif
                        </td>
                        @endif
                        @if(in_array('kargo', $visibleColumns))
                        <td class="px-3 py-2 text-center">
                            @if((float)$p->cargo_cost > 0 || (float)$p->desi > 0 || (int)$p->pieces > 0)
                                @php
                                    $cargoLabel = '';
                                    if((int)$p->pieces > 0) $cargoLabel .= $p->pieces . 'P ';
                                    if((float)$p->desi > 0) $cargoLabel .= number_format($p->desi, 0) . 'D';
                                @endphp
                                <div class="text-center">
                                    @if(trim($cargoLabel))
                                        <div class="text-xs text-gray-600 font-medium">{{ trim($cargoLabel) }}</div>
                                    @endif
                                    @if((float)$p->cargo_cost > 0)
                                        <div class="text-xs font-medium text-orange-600">{{ number_format($p->cargo_cost, 2, ',', '.') }} ₺</div>
                                    @endif
                                </div>
                            @else
                                <span class="text-gray-400 text-xs">—</span>
                            @endif
                        </td>
                        @endif
                        @if(in_array('stok', $visibleColumns))
                        <td class="px-3 py-2 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $p->stock_level_color }}">{{ number_format($p->stock_quantity) }}</span>
                        </td>
                        @endif
                        @if(in_array('kdv', $visibleColumns))
                        <td class="px-3 py-2 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $p->vat_rate == 10 ? 'bg-blue-100 text-blue-800' : ($p->vat_rate == 1 ? 'bg-purple-100 text-purple-800' : 'bg-orange-100 text-orange-800') }}">%{{ number_format($p->vat_rate, 0) }}</span>
                        </td>
                        @endif
                        @if(in_array('karlilik', $visibleColumns))
                        <td class="px-3 py-2 text-right" style="white-space:nowrap">
                            <template x-if="hasCost">
                                <div>
                                    <div class="text-sm font-bold" :class="profitPct >= 20 ? 'text-emerald-600' : (profitPct >= 0 ? 'text-amber-600' : 'text-red-600')">%<span x-text="profitPct !== null ? profitPct.toFixed(1) : '—'"></span></div>
                                    <div class="text-xs font-medium" :class="profitTL >= 0 ? 'text-emerald-500' : 'text-red-500'"><span x-text="profitTL !== null ? ((profitTL >= 0 ? '+' : '') + profitTL.toFixed(0) + ' ₺') : ''"></span></div>
                                </div>
                            </template>
                            <template x-if="!hasCost">
                                <span class="text-gray-400 text-xs italic">Maliyet yok</span>
                            </template>
                        </td>
                        @endif
                        @if(in_array('durum', $visibleColumns))
                        <td class="px-3 py-2 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $p->status_color }}">{{ $p->status_label }}</span>
                        </td>
                        @endif
                        @if(in_array('islem', $visibleColumns))
                        <td class="px-3 py-2 text-center" style="overflow:visible">
                            <div x-data="{ open: false }" class="relative inline-block">
                                <button @click="open = !open" class="p-1 rounded-md hover:bg-gray-100 text-gray-500 hover:text-gray-700 transition-colors">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zm0 6a2 2 0 110-4 2 2 0 010 4zm0 6a2 2 0 110-4 2 2 0 010 4z"/></svg>
                                </button>
                                <div x-show="open" @click.outside="open = false" x-transition
                                     class="absolute right-0 top-full mt-1 z-50 w-36 bg-white border border-gray-200 rounded-lg shadow-xl py-1">
                                    <button wire:click="editProduct({{ $p->id }})" @click="open = false" class="w-full text-left px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50 flex items-center gap-2">
                                        <svg class="w-3.5 h-3.5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                        Düzenle
                                    </button>
                                    <button wire:click="duplicateProduct({{ $p->id }})" @click="open = false" class="w-full text-left px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50 flex items-center gap-2">
                                        <svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                        Çoğalt
                                    </button>
                                    <div class="border-t border-gray-100 my-1"></div>
                                    <button wire:click="deleteProduct({{ $p->id }})" wire:confirm="Bu ürünü silmek istediğinize emin misiniz?" @click="open = false" class="w-full text-left px-3 py-1.5 text-xs text-red-600 hover:bg-red-50 flex items-center gap-2">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        Sil
                                    </button>
                                </div>
                            </div>
                        </td>
                        @endif
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ count($visibleColumns) + 1 }}" class="px-6 py-12 text-center text-gray-400">
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

    {{-- ═══ MOBİL KART GÖRÜNÜMÜ (md altı) ═══ --}}
    <div class="md:hidden space-y-3 mb-4">
        @forelse($this->products as $p)
        @php
            $cardTotalCost = (float)$p->cogs + (float)$p->packaging_cost + (float)$p->cargo_cost;
            $cardCommission = (float)$p->sale_price * ((float)$p->commission_rate / 100);
            $cardProfit = $cardTotalCost > 0 ? (float)$p->sale_price - $cardTotalCost - $cardCommission : null;
            $cardProfitPct = ((float)$p->cogs > 0 && $cardProfit !== null) ? round(($cardProfit / (float)$p->cogs) * 100, 1) : null;
        @endphp
        <div class="bg-white rounded-xl border border-gray-200 p-4" wire:key="product-card-{{ $p->id }}">
            {{-- Üst: Checkbox + Ürün + Durum --}}
            <div class="flex items-start gap-3">
                <input type="checkbox" value="{{ $p->id }}" wire:model.live="selectedProducts" class="rounded border-gray-300 text-indigo-600 mt-1">
                @if($p->main_image)
                    <img src="{{ $p->main_image }}" alt="" class="w-12 h-12 rounded-lg object-cover border border-gray-200 flex-shrink-0">
                @endif
                <div class="flex-1 min-w-0">
                    <h3 class="font-medium text-gray-900 text-sm truncate">{{ $p->product_name ?? 'İsimsiz' }}</h3>
                    <p class="text-xs text-gray-500 mt-0.5">{{ $p->barcode }}</p>
                    <div class="flex items-center gap-1.5 mt-1">
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium {{ $p->status_color }}">{{ $p->status_label }}</span>
                    </div>
                </div>
            </div>
            {{-- Alt: Veriler 2 sütunlu grid --}}
            <div class="grid grid-cols-2 gap-2 mt-3 pt-3 border-t border-gray-100 text-xs">
                <div class="flex justify-between"><span class="text-gray-500">Fiyat</span><span class="font-medium text-gray-900">{{ $p->sale_price > 0 ? number_format($p->sale_price, 0, ',', '.') . ' ₺' : '—' }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">COGS</span><span class="font-medium {{ $p->cogs > 0 ? 'text-gray-900' : 'text-amber-600' }}">{{ $p->cogs > 0 ? number_format($p->cogs, 0, ',', '.') . ' ₺' : 'Eksik' }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">Stok</span><span class="font-medium"><span class="px-1.5 py-0.5 rounded text-xs {{ $p->stock_level_color }}">{{ number_format($p->stock_quantity) }}</span></span></div>
                <div class="flex justify-between"><span class="text-gray-500">KDV</span><span class="font-medium text-gray-700">%{{ number_format($p->vat_rate, 0) }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">Kargo</span><span class="font-medium {{ (float)$p->cargo_cost > 0 ? 'text-orange-600' : 'text-gray-400' }}">{{ (float)$p->cargo_cost > 0 ? number_format($p->cargo_cost, 0, ',', '.') . ' ₺' : '—' }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">ROI</span>
                    @if($cardProfitPct !== null)
                        <span class="font-bold {{ $cardProfitPct >= 20 ? 'text-emerald-600' : ($cardProfitPct >= 0 ? 'text-amber-600' : 'text-red-600') }}">%{{ number_format($cardProfitPct, 1) }}</span>
                    @else
                        <span class="text-gray-400 italic">—</span>
                    @endif
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-3">
                <button wire:click="editProduct({{ $p->id }})" class="text-xs text-indigo-600 hover:text-indigo-900 font-medium">Düzenle</button>
                <button wire:click="deleteProduct({{ $p->id }})" wire:confirm="Bu ürünü silmek istediğinize emin misiniz?" class="text-xs text-red-600 hover:text-red-900 font-medium">Sil</button>
            </div>
        </div>
        @empty
        <div class="text-center py-12 text-gray-400"><p>Henüz ürün bulunmuyor.</p></div>
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

    {{-- Alpine.js Column Resize Component --}}
    <script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('columnResize', () => ({
            resizing: false, startX: 0, startWidth: 0, currentTh: null, handle: null,
            startResize(e, th) {
                this.resizing = true; this.startX = e.pageX; this.currentTh = th;
                this.startWidth = th.offsetWidth; this.handle = e.target;
                this.handle.classList.add('active');
                const onMouseMove = (ev) => {
                    if (!this.resizing) return;
                    const newWidth = Math.max(40, this.startWidth + (ev.pageX - this.startX));
                    this.currentTh.style.width = newWidth + 'px';
                    this.currentTh.style.minWidth = newWidth + 'px';
                };
                const onMouseUp = () => {
                    this.resizing = false;
                    if (this.handle) this.handle.classList.remove('active');
                    document.removeEventListener('mousemove', onMouseMove);
                    document.removeEventListener('mouseup', onMouseUp);
                };
                document.addEventListener('mousemove', onMouseMove);
                document.addEventListener('mouseup', onMouseUp);
            }
        }));
    });
    </script>
</div>
