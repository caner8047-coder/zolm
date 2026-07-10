@php
    $formatCost = fn ($value) => $value ? '₺' . number_format((float) $value, 2, ',', '.') : '-';
@endphp

<div class="w-full space-y-4 lg:space-y-6">
    {{-- Mesaj Paneli --}}
    @if($message !== '')
        <div class="rounded-[8px] border p-4 text-sm {{ $messageType === 'error' ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800' }}">
            {{ $message }}
        </div>
    @endif

    {{-- Üst Section --}}
    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <div class="inline-flex items-center rounded-[6px] border border-slate-200 bg-slate-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">
                    Depo & Stok
                </div>
                <h1 class="mt-3 text-xl font-semibold tracking-tight text-slate-950 lg:text-2xl">Depolar & Stok Envanteri</h1>
                <p class="mt-2 text-sm text-slate-500">
                    Fiziksel veya dijital depolarınızı yönetin, alış/satış/fire stok hareketlerini kaydedin ve anlık stok envanter seviyelerinizi takip edin.
                </p>
            </div>
            <div class="flex flex-col sm:flex-row gap-2 shrink-0">
                <button wire:click="$toggle('showWarehouseForm')" class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-[6px] hover:bg-slate-50 transition-colors min-h-[44px]">
                    Yeni Depo Tanımla
                </button>
                <button wire:click="$toggle('showMovementForm')" class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 transition-colors min-h-[44px]">
                    Stok Hareketi (Fiş) Gir
                </button>
            </div>
        </div>
    </section>

    {{-- Depo Tanımlama Formu --}}
    @if($showWarehouseForm)
        <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
            <h3 class="text-base font-semibold text-slate-900">Yeni Depo Tanımla</h3>
            <form wire:submit.prevent="createWarehouse" class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Depo Adı</label>
                    <input type="text" wire:model="warehouseName" placeholder="Örn: Tuzla Depo" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
                    @error('warehouseName') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Depo Kodu</label>
                    <input type="text" wire:model="warehouseCode" placeholder="Örn: depo-tuzla" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
                    @error('warehouseCode') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div class="flex items-center sm:pt-6">
                    <label class="inline-flex items-center cursor-pointer">
                        <input type="checkbox" wire:model="warehouseIsDefault" class="rounded border-slate-300 text-slate-900 focus:ring-slate-900 w-5 h-5" />
                        <span class="ml-2 text-sm text-slate-600 font-medium">Varsayılan Depo Yap</span>
                    </label>
                </div>
                <div class="sm:col-span-3 flex justify-end gap-2 mt-2">
                    <button type="button" wire:click="$set('showWarehouseForm', false)" class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-[6px] hover:bg-slate-50 min-h-[44px]">İptal</button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 min-h-[44px]">Depoyu Aç</button>
                </div>
            </form>
        </section>
    @endif

    {{-- Stok Hareketi (Fiş) Giriş Formu --}}
    @if($showMovementForm)
        <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
            <h3 class="text-base font-semibold text-slate-900">Stok Hareketi (Giriş / Çıkış) Kaydet</h3>
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
                        <option value="">Depo Seçin...</option>
                        @foreach($this->warehouses as $wh)
                            <option value="{{ $wh->id }}">{{ $wh->name }} {{ $wh->is_default ? '(Varsayılan)' : '' }}</option>
                        @endforeach
                    </select>
                    @error('movWarehouseId') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Ürün (MpProduct)</label>
                    <select wire:model="movStockCode" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                        <option value="">Ürün Seçin...</option>
                        @foreach($this->products as $p)
                            <option value="{{ $p->stock_code }}">{{ $p->product_name }} ({{ $p->stock_code }})</option>
                        @endforeach
                    </select>
                    @error('movStockCode') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Miktar</label>
                    <input type="number" wire:model="movQuantity" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm text-right font-mono focus:border-slate-500 focus:outline-none min-h-[44px]" />
                    @error('movQuantity') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Birim Maliyet / Fiyat</label>
                    <input type="number" step="0.01" wire:model="movUnitCost" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm text-right font-mono focus:border-slate-500 focus:outline-none min-h-[44px]" />
                    @error('movUnitCost') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Açıklama</label>
                    <input type="text" wire:model="movDescription" placeholder="Harekete dair açıklama notu..." class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
                </div>
                <div class="sm:col-span-4 flex justify-end gap-2 mt-2">
                    <button type="button" wire:click="$set('showMovementForm', false)" class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-[6px] hover:bg-slate-50 min-h-[44px]">İptal</button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 min-h-[44px]">Kaydet</button>
                </div>
            </form>
        </section>
    @endif

    {{-- Depolar Listesi --}}
    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6 space-y-3">
        <h3 class="text-base font-semibold text-slate-900">Aktif Depolar</h3>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            @forelse($this->warehouses as $wh)
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4 relative">
                    <div class="font-semibold text-slate-900">{{ $wh->name }}</div>
                    <div class="text-xs font-mono text-slate-500 mt-1">Kod: {{ $wh->code }}</div>
                    @if($wh->is_default)
                        <span class="absolute top-4 right-4 px-2 py-0.5 text-[9px] font-semibold rounded bg-emerald-100 text-emerald-800">Varsayılan</span>
                    @endif
                </div>
            @empty
                <div class="sm:col-span-3 text-sm text-slate-400 text-center py-4">Kayıtlı aktif depo bulunamadı. Stok fişi kaydedildiğinde varsayılan bir depo otomatik oluşturulacaktır.</div>
            @endforelse
        </div>
    </section>

    {{-- Stok Envanteri Tablosu & Hareketler --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        {{-- Sol Kısım: Stok Envanteri (Genişlik: 2/3) --}}
        <section class="xl:col-span-2 rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6 space-y-4">
            <h3 class="text-base font-semibold text-slate-900">Mevcut Stok Envanteri</h3>

            {{-- Filtreler --}}
            <div class="flex flex-col sm:flex-row gap-3">
                <input type="text" wire:model.live="search" placeholder="Stok kodu veya ürün adı ile arayın..." class="flex-1 rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
                <select wire:model.live="filterWarehouse" class="w-full sm:w-48 rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                    <option value="">Tüm Depolar</option>
                    @foreach($this->warehouses as $wh)
                        <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Envanter Tablosu --}}
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left text-sm text-slate-600">
                    <thead>
                        <tr class="border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider bg-slate-50/50">
                            <th class="p-3">Stok Kodu</th>
                            <th class="p-3">Ürün Adı</th>
                            <th class="p-3">Depo</th>
                            <th class="p-3 text-right">Mevcut Miktar</th>
                            <th class="p-3 text-center">Durum</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($this->stockBalances as $bal)
                            @php
                                $isCritical = app(App\Services\Accounting\StockService::class)->isCriticalStock(auth()->id(), $bal->stock_code, $bal->warehouse_id);
                            @endphp
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="p-3 font-mono font-semibold text-slate-900">{{ $bal->stock_code }}</td>
                                <td class="p-3 font-medium text-slate-900">{{ $bal->product ? $bal->product->product_name : 'Bilinmeyen Ürün' }}</td>
                                <td class="p-3 text-slate-500 text-xs">{{ $bal->warehouse->name }}</td>
                                <td class="p-3 text-right font-mono font-bold text-slate-900">{{ $bal->quantity }}</td>
                                <td class="p-3 text-center">
                                    @if($isCritical)
                                        <span class="inline-flex items-center rounded bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-700 ring-1 ring-inset ring-rose-600/10">Kritik Stok</span>
                                    @else
                                        <span class="inline-flex items-center rounded bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/10">Yeterli</span>
                                    @endif
                                </td>
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

            <div class="mt-4">
                {{ $this->stockBalances->links() }}
            </div>
        </section>

        {{-- Sağ Kısım: Son Stok Hareketleri (Genişlik: 1/3) --}}
        <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6 space-y-4">
            <h3 class="text-base font-semibold text-slate-900">Son Stok Hareketleri (Log)</h3>
            <div class="divide-y divide-slate-100 max-h-[500px] overflow-y-auto pr-1">
                @forelse($this->stockMovements as $mov)
                    <div class="py-3 text-xs space-y-1">
                        <div class="flex justify-between items-center">
                            <span class="font-mono font-semibold text-slate-900">{{ $mov->stock_code }}</span>
                            <span class="font-mono text-slate-400">{{ date('d.m.Y', strtotime($mov->movement_date)) }}</span>
                        </div>
                        <div class="text-slate-600 text-[11px] truncate">{{ $mov->product ? $mov->product->product_name : 'Bilinmeyen Ürün' }}</div>
                        <div class="flex justify-between items-center pt-1">
                            <span class="text-[10px] uppercase text-slate-400 bg-slate-100 px-1 py-0.5 rounded">{{ str_replace('_', ' ', $mov->movement_type) }}</span>
                            <span class="font-mono font-bold {{ $mov->direction === 'in' ? 'text-emerald-700' : 'text-rose-700' }}">
                                {{ $mov->direction === 'in' ? '+' : '-' }}{{ $mov->quantity }} ({{ $formatCost($mov->unit_cost) }})
                            </span>
                        </div>
                        @if($mov->description)
                            <div class="text-[10px] text-slate-400 italic mt-0.5">Not: {{ $mov->description }}</div>
                        @endif
                    </div>
                @empty
                    <div class="text-sm text-slate-400 py-8 text-center">Stok hareketi bulunamadı.</div>
                @endforelse
            </div>
        </section>
    </div>
</div>
