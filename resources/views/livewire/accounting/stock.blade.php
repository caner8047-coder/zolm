@php
    $formatCost = fn ($value) => $value ? '₺' . number_format((float) $value, 2, ',', '.') : '-';
@endphp

<div class="w-full space-y-4 lg:space-y-6">
    {{-- Mesaj Paneli --}}
    @if($message !== '')
        <div class="rounded-[8px] border p-4 text-sm flex items-center justify-between {{ $messageType === 'error' ? 'border-red-200 bg-red-50 text-red-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800' }}">
            <div class="flex items-center gap-2">
                @if($messageType === 'error')
                    <svg class="h-5 w-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                    </svg>
                @else
                    <svg class="h-5 w-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                @endif
                <span class="font-medium">{{ $message }}</span>
            </div>
            <button wire:click="$set('message', '')" class="text-slate-400 hover:text-slate-600">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
    @endif

    {{-- Üst Workspace / Özet Kartı --}}
    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <div class="inline-flex items-center rounded-[6px] border border-slate-200 bg-slate-50/70 px-2.5 py-0.5 text-xs font-mono uppercase tracking-wider text-slate-500">
                    Depo & Stok
                </div>
                <h1 class="mt-3 text-xl font-semibold tracking-tight text-slate-900 lg:text-2xl">Depo & Stok Envanteri</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Fiziksel depolarınızı yönetin, envanter düzeylerini izleyin ve giriş/çıkış stok fişlerini kaydedin.
                </p>
            </div>
            <div class="flex flex-col sm:flex-row gap-2 shrink-0">
                <button wire:click="$toggle('showWarehouseForm')" class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-[6px] hover:bg-slate-50 transition-colors min-h-[44px]">
                    <svg class="mr-2 h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                    Yeni Depo Tanımla
                </button>
                <button wire:click="$toggle('showMovementForm')" class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 transition-colors min-h-[44px]">
                    <svg class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                    Stok Fişi (Giriş/Çıkış) Gir
                </button>
            </div>
        </div>
    </section>

    {{-- Yeni Depo Tanımlama Formu --}}
    @if($showWarehouseForm)
        <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
            <h3 class="text-base font-semibold text-slate-900">Yeni Depo Tanımla</h3>
            <form wire:submit.prevent="createWarehouse" class="mt-4 grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Depo Adı</label>
                    <input type="text" wire:model="warehouseName" placeholder="Tuzla Depo" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
                    @error('warehouseName') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Depo Kodu</label>
                    <input type="text" wire:model="warehouseCode" placeholder="depo-tuzla" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
                    @error('warehouseCode') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Yasal Birlik (Şirket)</label>
                    <select wire:model="warehouseLegalEntityId" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                        <option value="">Seçiniz (Opsiyonel)...</option>
                        @foreach($this->legalEntities as $le)
                            <option value="{{ $le->id }}">{{ $le->name }}</option>
                        @endforeach
                    </select>
                    @error('warehouseLegalEntityId') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div class="flex items-center sm:pt-6">
                    <label class="inline-flex items-center cursor-pointer">
                        <input type="checkbox" wire:model="warehouseIsDefault" class="rounded border-slate-300 text-slate-900 focus:ring-slate-900 w-5 h-5" />
                        <span class="ml-2 text-sm text-slate-600 font-medium">Varsayılan Depo Yap</span>
                    </label>
                </div>
                <div class="sm:col-span-4 flex justify-end gap-2 mt-2">
                    <button type="button" wire:click="$set('showWarehouseForm', false)" class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-[6px] hover:bg-slate-50 min-h-[44px]">İptal</button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 min-h-[44px]">Depoyu Oluştur</button>
                </div>
            </form>
        </section>
    @endif

    {{-- Stok Fişi Giriş Formu --}}
    @if($showMovementForm)
        <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
            <h3 class="text-base font-semibold text-slate-900">Stok Hareketi (Giriş / Çıkış) Fişi</h3>
            <form wire:submit.prevent="recordStockMovement" class="mt-4 grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">İşlem Yönü</label>
                    <select wire:model.live="movDirection" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                        <option value="in">Giriş (Inflow)</option>
                        <option value="out">Çıkış (Outflow)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Hareket Tipi</label>
                    <select wire:model="movType" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                        @if($movDirection === 'in')
                            <option value="in_purchase">Alış Faturası</option>
                            <option value="in_return">Müşteri İadesi</option>
                            <option value="in_adjustment">Düzeltme Girişi</option>
                        @else
                            <option value="out_sale">Satış Faturası</option>
                            <option value="out_loss">Fire / Zayiat</option>
                            <option value="out_adjustment">Düzeltme Çıkışı</option>
                        @endif
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Depo</label>
                    <select wire:model="movWarehouseId" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                        <option value="">Seçiniz...</option>
                        @foreach($this->warehouses as $wh)
                            <option value="{{ $wh->id }}">{{ $wh->name }} {{ $wh->is_default ? '(Varsayılan)' : '' }}</option>
                        @endforeach
                    </select>
                    @error('movWarehouseId') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Ürün</label>
                    <select wire:model="movStockCode" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                        <option value="">Seçiniz...</option>
                        @foreach($this->products as $p)
                            <option value="{{ $p->stock_code }}">{{ $p->product_name }} ({{ $p->stock_code }})</option>
                        @endforeach
                    </select>
                    @error('movStockCode') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Miktar</label>
                    <input type="number" wire:model="movQuantity" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm text-right font-mono focus:border-slate-500 focus:outline-none min-h-[44px]" />
                    @error('movQuantity') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Birim Maliyet / Fiyat (Opsiyonel)</label>
                    <input type="number" step="0.01" wire:model="movUnitCost" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm text-right font-mono focus:border-slate-500 focus:outline-none min-h-[44px]" />
                    @error('movUnitCost') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Belge / Fiş No (Opsiyonel)</label>
                    <input type="text" wire:model="movRef" placeholder="Örn: SB-12345" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Yasal Birlik (Opsiyonel)</label>
                    <select wire:model="movLegalEntityId" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                        <option value="">Seçiniz...</option>
                        @foreach($this->legalEntities as $le)
                            <option value="{{ $le->id }}">{{ $le->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-4">
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Açıklama</label>
                    <input type="text" wire:model="movDescription" placeholder="Harekete dair açıklama..." class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
                </div>
                <div class="sm:col-span-4 flex justify-end gap-2 mt-2">
                    <button type="button" wire:click="$set('showMovementForm', false)" class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-[6px] hover:bg-slate-50 min-h-[44px]">İptal</button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 min-h-[44px]">Hareketi Kaydet</button>
                </div>
            </form>
        </section>
    @endif

    {{-- KPI Metrik Kartları --}}
    <section class="grid grid-cols-2 md:grid-cols-6 gap-3 lg:gap-4">
        @php $summary = $this->stockSummary; @endphp
        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block">Toplam SKU</span>
            <span class="mt-2 text-xl font-bold font-mono text-slate-900 block">{{ number_format($summary['total_sku']) }}</span>
        </div>
        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block">Toplam Miktar</span>
            <span class="mt-2 text-xl font-bold font-mono text-slate-900 block">{{ number_format($summary['total_quantity']) }}</span>
        </div>
        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block">Kritik Stok</span>
            <span class="mt-2 text-xl font-bold font-mono {{ $summary['critical_count'] > 0 ? 'text-amber-600' : 'text-slate-900' }} block">
                {{ number_format($summary['critical_count']) }}
            </span>
        </div>
        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block">Sıfır Stok</span>
            <span class="mt-2 text-xl font-bold font-mono {{ $summary['out_of_stock_count'] > 0 ? 'text-red-600' : 'text-slate-900' }} block">
                {{ number_format($summary['out_of_stock_count']) }}
            </span>
        </div>
        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block">Aktif Depo</span>
            <span class="mt-2 text-xl font-bold font-mono text-slate-900 block">{{ number_format($summary['warehouse_count']) }}</span>
        </div>
        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block">Envanter Değeri</span>
            <span class="mt-2 text-xl font-bold font-mono text-slate-900 block">{{ $formatCost($summary['inventory_value']) }}</span>
        </div>
    </section>

    {{-- Aktif Depolar Listesi --}}
    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6 space-y-3">
        <h3 class="text-base font-semibold text-slate-900">Aktif Depolar</h3>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            @forelse($this->warehouses as $wh)
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4 flex justify-between items-start relative">
                    <div>
                        <div class="font-semibold text-slate-900">{{ $wh->name }}</div>
                        <div class="text-xs font-mono text-slate-500 mt-1">Kod: {{ $wh->code }}</div>
                        @if($wh->legalEntity)
                            <div class="text-xs text-slate-400 mt-0.5">Şirket: {{ $wh->legalEntity->name }}</div>
                        @endif
                    </div>
                    @if($wh->is_default)
                        <span class="px-2 py-0.5 text-[10px] font-semibold rounded bg-indigo-100 text-indigo-800">Varsayılan</span>
                    @endif
                </div>
            @empty
                <div class="sm:col-span-3 text-sm text-slate-400 text-center py-4">Kayıtlı aktif depo bulunamadı.</div>
            @endforelse
        </div>
    </section>

    {{-- Ana Layout Grid: Sol Envanter Tablosu, Sağ Hareketler --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 lg:gap-6">
        {{-- Sol Kısım: Stok Envanteri (2/3) --}}
        <section class="xl:col-span-2 rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <h3 class="text-base font-semibold text-slate-900">Mevcut Stok Envanteri</h3>

                {{-- Kolon Seçici ve Diğer Kontroller --}}
                <div class="flex items-center gap-2 self-end sm:self-auto">
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" class="inline-flex items-center justify-center px-3 py-1.5 text-xs font-medium text-slate-700 bg-white border border-slate-200 rounded-[6px] hover:bg-slate-50 min-h-[36px]">
                            Kolonlar
                            <svg class="ml-1.5 h-3.5 w-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                        </button>
                        <div x-show="open" @click.away="open = false" class="absolute right-0 mt-1.5 w-40 rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 z-20 p-2 space-y-1">
                            @foreach($visibleColumns as $col => $visible)
                                <label class="flex items-center px-2 py-1 text-xs hover:bg-slate-50 rounded cursor-pointer">
                                    <input type="checkbox" wire:click="toggleColumn('{{ $col }}')" @if($visible) checked @endif class="rounded border-slate-300 text-slate-900 focus:ring-slate-900 w-4 h-4 mr-2" />
                                    <span>
                                        @switch($col)
                                            @case('stock_code') Stok Kodu @break
                                            @case('product_name') Ürün Adı @break
                                            @case('warehouse_name') Depo Adı @break
                                            @case('quantity') Miktar @break
                                            @case('status') Durum @break
                                        @endswitch
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            {{-- Arama ve Durum/Depo Filtreleri --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <input type="text" wire:model.live="search" placeholder="Stok kodu veya ürün adı ile arayın..." class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />

                <select wire:model.live="filterWarehouse" class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">

                    <option value="">Tüm Depolar</option>
                    @foreach($this->warehouses as $wh)
                        <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                    @endforeach
                </select>

                <select wire:model.live="filterStatus" class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                    <option value="all">Tüm Durumlar</option>
                    <option value="critical">Kritik Stok</option>
                    <option value="out_of_stock">Sıfır Stok (Tükenen)</option>
                    <option value="positive">Stokta Olanlar</option>
                </select>
            </div>

            @if($search || $filterWarehouse || $filterStatus !== 'all')
                <div class="text-xs text-slate-500 bg-slate-50 p-2 rounded flex items-center gap-2">
                    <span class="font-medium">Aktif Filtreleme:</span>
                    @if($search) <span>Arama: "{{ $search }}"</span> @endif
                    @if($filterWarehouse) <span>Depo ID: {{ $filterWarehouse }}</span> @endif
                    @if($filterStatus !== 'all') <span>Durum: {{ $filterStatus === 'critical' ? 'Kritik' : ($filterStatus === 'out_of_stock' ? 'Sıfır Stok' : 'Pozitif Stok') }}</span> @endif
                    <button wire:click="$set('search', ''); $set('filterWarehouse', ''); $set('filterStatus', 'all');" class="text-indigo-600 hover:underline ml-auto font-mono">Temizle</button>
                </div>
            @endif

            {{-- Masaüstü Tablo Görünümü --}}
            <div class="hidden md:block overflow-x-auto rounded-lg border border-slate-200">
                <table class="w-full border-collapse text-left text-sm text-slate-600 table-layout-fixed">
                    <thead>
                        <tr class="border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase bg-slate-50/70">
                            @if($visibleColumns['stock_code'])
                                <th class="p-3 cursor-pointer select-none" wire:click="sortTable('stock_code')">
                                    <div class="flex items-center gap-1">
                                        Stok Kodu
                                        @if($sortColumn === 'stock_code')
                                            <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                        @endif
                                    </div>
                                </th>
                            @endif
                            @if($visibleColumns['product_name'])
                                <th class="p-3">Ürün Adı</th>
                            @endif
                            @if($visibleColumns['warehouse_name'])
                                <th class="p-3">Depo</th>
                            @endif
                            @if($visibleColumns['quantity'])
                                <th class="p-3 text-right cursor-pointer select-none" wire:click="sortTable('quantity')">
                                    <div class="flex items-center justify-end gap-1">
                                        Mevcut Miktar
                                        @if($sortColumn === 'quantity')
                                            <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                        @endif
                                    </div>
                                </th>
                            @endif
                            @if($visibleColumns['status'])
                                <th class="p-3 text-center">Durum</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse($this->stockBalances as $bal)
                            @php
                                $isCritical = app(App\Services\Accounting\StockService::class)->isCriticalStock(auth()->id(), $bal->stock_code, $bal->warehouse_id);
                                $isOutOfStock = $bal->quantity <= 0;
                            @endphp
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                @if($visibleColumns['stock_code'])
                                    <td class="p-3 font-mono font-semibold text-slate-900 truncate">{{ $bal->stock_code }}</td>
                                @endif
                                @if($visibleColumns['product_name'])
                                    <td class="p-3 font-medium text-slate-900 truncate">{{ $bal->product ? $bal->product->product_name : 'Bilinmeyen Ürün' }}</td>
                                @endif
                                @if($visibleColumns['warehouse_name'])
                                    <td class="p-3 text-slate-500 text-xs truncate">{{ $bal->warehouse->name }}</td>
                                @endif
                                @if($visibleColumns['quantity'])
                                    <td class="p-3 text-right font-mono font-bold text-slate-900">{{ number_format($bal->quantity) }}</td>
                                @endif
                                @if($visibleColumns['status'])
                                    <td class="p-3 text-center">
                                        @if($isOutOfStock)
                                            <span class="inline-flex items-center rounded bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-700 ring-1 ring-inset ring-red-600/10">Tükendi</span>
                                        @elseif($isCritical)
                                            <span class="inline-flex items-center rounded bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700 ring-1 ring-inset ring-amber-600/10">Kritik</span>
                                        @else
                                            <span class="inline-flex items-center rounded bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/10">Sağlıklı</span>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-8 text-center text-slate-400">
                                    Envanter kaydı bulunamadı.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Mobil Kart Görünümü --}}
            <div class="md:hidden space-y-3">
                @forelse($this->stockBalances as $bal)
                    @php
                        $isCritical = app(App\Services\Accounting\StockService::class)->isCriticalStock(auth()->id(), $bal->stock_code, $bal->warehouse_id);
                        $isOutOfStock = $bal->quantity <= 0;
                    @endphp
                    <div class="rounded-lg border border-slate-200 p-3 space-y-2 bg-white text-xs">
                        <div class="flex justify-between items-start">
                            <span class="font-mono font-bold text-slate-900">{{ $bal->stock_code }}</span>
                            @if($isOutOfStock)
                                <span class="rounded bg-red-50 px-1.5 py-0.5 font-semibold text-red-700 ring-1 ring-red-600/10">Tükendi</span>
                            @elseif($isCritical)
                                <span class="rounded bg-amber-50 px-1.5 py-0.5 font-semibold text-amber-700 ring-1 ring-amber-600/10">Kritik</span>
                            @else
                                <span class="rounded bg-emerald-50 px-1.5 py-0.5 font-semibold text-emerald-700 ring-1 ring-emerald-600/10">Sağlıklı</span>
                            @endif
                        </div>
                        <div>
                            <div class="font-medium text-slate-900">{{ $bal->product ? $bal->product->product_name : 'Bilinmeyen Ürün' }}</div>
                            <div class="text-slate-400 mt-0.5">Depo: {{ $bal->warehouse->name }}</div>
                        </div>
                        <div class="flex justify-between items-center pt-1 border-t border-slate-100">
                            <span class="text-slate-500 font-medium">Mevcut Stok:</span>
                            <span class="font-mono font-bold text-slate-900 text-sm">{{ number_format($bal->quantity) }}</span>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-slate-400 py-6">Envanter kaydı bulunamadı.</div>
                @endforelse
            </div>

            <div class="mt-4">
                {{ $this->stockBalances->links() }}
            </div>
        </section>

        {{-- Sağ Kısım: Stok Fiş Geçmişi & Log (1/3) --}}
        <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6 space-y-4">
            <h3 class="text-base font-semibold text-slate-900 font-mono">Stok Hareket Logları</h3>

            {{-- Fiş Filtreleri --}}
            <div class="space-y-3">
                <div class="grid grid-cols-2 gap-2">
                    <select wire:model.live="filterDirection" class="rounded-[6px] border border-slate-200 bg-white px-2.5 py-1.5 text-xs focus:border-slate-500 focus:outline-none min-h-[36px]">
                        <option value="all">Yön: Tümü</option>
                        <option value="in">Giriş (In)</option>
                        <option value="out">Çıkış (Out)</option>
                    </select>
                    <select wire:model.live="filterMovementType" class="rounded-[6px] border border-slate-200 bg-white px-2.5 py-1.5 text-xs focus:border-slate-500 focus:outline-none min-h-[36px]">
                        <option value="">Tip: Tümü</option>
                        <option value="in_purchase">Alış Faturası</option>
                        <option value="in_return">Müşteri İadesi</option>
                        <option value="in_adjustment">Düzeltme Girişi</option>
                        <option value="out_sale">Satış Faturası</option>
                        <option value="out_loss">Fire / Zayiat</option>
                        <option value="out_adjustment">Düzeltme Çıkışı</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <input type="date" wire:model.live="filterDateFrom" class="rounded-[6px] border border-slate-200 bg-white px-2 py-1.5 text-xs focus:border-slate-500 focus:outline-none min-h-[36px]" />
                    <input type="date" wire:model.live="filterDateTo" class="rounded-[6px] border border-slate-200 bg-white px-2 py-1.5 text-xs focus:border-slate-500 focus:outline-none min-h-[36px]" />
                </div>
            </div>

            {{-- Hareket Listesi --}}
            <div class="space-y-3 max-h-[600px] overflow-y-auto pr-1">
                @forelse($this->stockMovements as $mov)
                    <div class="rounded-lg border p-3 text-xs space-y-1.5 bg-white relative transition-colors {{ $mov->isVoid() ? 'border-slate-100 opacity-60 bg-slate-50/50' : 'border-slate-200 hover:border-slate-300' }}">
                        <div class="flex justify-between items-center">
                            <span class="font-mono font-semibold {{ $mov->isVoid() ? 'line-through text-slate-400' : 'text-slate-900' }}">{{ $mov->stock_code }}</span>
                            <span class="font-mono text-slate-400">{{ date('d.m.Y', strtotime($mov->movement_date)) }}</span>
                        </div>
                        <div class="text-slate-600 text-[11px] truncate">{{ $mov->product ? $mov->product->product_name : 'Bilinmeyen Ürün' }}</div>
                        <div class="flex justify-between items-center">
                            <span class="text-[10px] uppercase font-mono bg-slate-100 px-1 py-0.5 rounded text-slate-500">
                                {{ str_replace('_', ' ', $mov->movement_type) }}
                            </span>
                            <span class="font-mono font-bold {{ $mov->isVoid() ? 'text-slate-400 line-through' : ($mov->direction === 'in' ? 'text-emerald-700' : 'text-rose-700') }}">
                                {{ $mov->direction === 'in' ? '+' : '-' }}{{ number_format($mov->quantity) }} @if($mov->unit_cost) ({{ $formatCost($mov->unit_cost) }}) @endif
                            </span>
                        </div>
                        @if($mov->reference_number)
                            <div class="text-[10px] text-slate-500 font-mono">Belge: {{ $mov->reference_number }}</div>
                        @endif
                        @if($mov->description)
                            <div class="text-[10px] text-slate-400 italic">Not: {{ $mov->description }}</div>
                        @endif
                        @if($mov->isVoid())
                            <div class="text-[10px] text-red-500 font-semibold mt-1">İPTAL EDİLDİ @if($mov->void_reason) (Neden: {{ $mov->void_reason }}) @endif</div>
                        @else
                            {{-- Void Aksiyonu --}}
                            <div class="flex justify-end pt-1.5 border-t border-slate-100">
                                <button type="button" wire:click="voidMovement({{ $mov->id }})" wire:confirm="Bu stok hareketini iptal etmek istediğinize emin misiniz? Bakiye geri alınacaktır." class="text-red-600 hover:text-red-800 font-semibold text-[11px] hover:underline">
                                    Hareketi İptal Et (Void)
                                </button>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="text-sm text-slate-400 py-8 text-center">Stok hareketi bulunamadı.</div>
                @endforelse
            </div>

            <div class="mt-4">
                {{ $this->stockMovements->links() }}
            </div>
        </section>
    </div>
</div>
