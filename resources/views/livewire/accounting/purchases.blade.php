@php
    $formatMoney = fn ($value) => '₺' . number_format((float) $value, 2, ',', '.');
    $statusLabel = fn ($s) => match ($s) {
        'draft'     => ['Taslak',  'bg-slate-100 text-slate-700'],
        'approved'  => ['Onaylı',  'bg-emerald-50 text-emerald-700'],
        'cancelled' => ['İptal',   'bg-rose-50 text-rose-700'],
        default     => [$s,        'bg-slate-100 text-slate-700'],
    };
@endphp

<div class="w-full space-y-4 lg:space-y-6">
    {{-- Mesaj Paneli --}}
    @if($message !== '')
        <div class="rounded-[8px] border p-4 text-sm {{ $messageType === 'error' ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800' }}">
            {{ $message }}
        </div>
    @endif

    {{-- Üst Workspace & Özet Kartı --}}
    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <div class="inline-flex items-center rounded-[6px] border border-slate-200 bg-slate-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">
                    Satın Alma Yönetimi
                </div>
                <h1 class="mt-3 text-xl font-semibold tracking-tight text-slate-950 lg:text-2xl">Satın Alma Siparişleri</h1>
                <p class="mt-2 text-sm text-slate-500">
                    Tedarikçi satın alma siparişlerini taslak olarak hazırlayın, KDV ve iskonto hesaplamalarını yapın; onaylayarak depoya stok girişi ve cari borca yansıtın.
                </p>
            </div>
            <div class="shrink-0">
                <button
                    wire:click="$toggle('showCreateForm')"
                    id="btn-new-purchase-order"
                    class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 transition-colors min-h-[44px]"
                >
                    {{ $showCreateForm ? 'Formu Kapat' : '+ Yeni Satın Alma Siparişi' }}
                </button>
            </div>
        </div>
    </section>

    {{-- KPI Kartları --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 lg:gap-4">
        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
            <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider block">Taslak Siparişler</span>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-bold text-slate-900 font-mono">{{ $this->kpis['draftCount'] }}</span>
                <span class="text-xs text-slate-500">adet</span>
            </div>
        </div>
        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
            <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider block">Onaylı Alım Toplamı</span>
            <div class="mt-2">
                <span class="text-2xl font-bold text-slate-900 font-mono">{{ $formatMoney($this->kpis['approvedTotal']) }}</span>
            </div>
        </div>
        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
            <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider block">İptal Alım Toplamı</span>
            <div class="mt-2">
                <span class="text-2xl font-bold text-slate-900 font-mono">{{ $formatMoney($this->kpis['cancelledTotal']) }}</span>
            </div>
        </div>
        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
            <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider block">Açık Borç Toplamı</span>
            <div class="mt-2">
                <span class="text-2xl font-bold text-slate-900 font-mono">{{ $formatMoney($this->kpis['openPayableTotal']) }}</span>
            </div>
        </div>
        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4 col-span-2 lg:col-span-1">
            <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider block">Bu Ay Alınan Ürün</span>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-bold text-slate-900 font-mono">{{ $this->kpis['itemCount'] }}</span>
                <span class="text-xs text-slate-500">adet</span>
            </div>
        </div>
    </div>

    {{-- Satın Alma Siparişi Oluşturma Formu --}}
    @if($showCreateForm)
        <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6 space-y-6">
            <h3 class="text-base font-semibold text-slate-900 border-b border-slate-100 pb-3">Satın Alma Siparişi (Taslak) Oluştur</h3>

            {{-- Başlık Bilgileri --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Tedarikçi (Cari) <span class="text-rose-500">*</span></label>
                    <select wire:model="partyId" id="select-purchase-party" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                        <option value="">Tedarikçi Seçin...</option>
                        @foreach($this->parties as $p)
                            <option value="{{ $p->id }}">{{ $p->display_name }}</option>
                        @endforeach
                    </select>
                    @error('partyId') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Belge Numarası <span class="text-rose-500">*</span></label>
                    <input wire:model="documentNumber" id="input-purchase-doc-number" type="text" placeholder="örn. ALI-001" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                    @error('documentNumber') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Sipariş Tarihi <span class="text-rose-500">*</span></label>
                    <input wire:model="orderDate" id="input-purchase-order-date" type="date" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                    @error('orderDate') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Depo</label>
                    <select wire:model="warehouseId" id="select-purchase-warehouse" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                        <option value="">Depo Seçin...</option>
                        @foreach($this->warehouses as $wh)
                            <option value="{{ $wh->id }}">{{ $wh->name }}{{ $wh->is_default ? ' (Varsayılan)' : '' }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Şirket (Legal Entity)</label>
                    <select wire:model="legalEntityId" id="select-purchase-legal-entity" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                        <option value="">Şirket Seçin...</option>
                        @foreach($this->legalEntities as $le)
                            <option value="{{ $le->id }}">{{ $le->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Genel İskonto Tutarı (Header)</label>
                    <input wire:model.live="discountAmount" id="input-purchase-discount-amount" type="number" min="0" step="0.01" placeholder="0.00" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                    @error('discountAmount') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Açıklama</label>
                    <input wire:model="description" id="input-purchase-description" type="text" placeholder="İsteğe bağlı açıklama" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                </div>
            </div>

            {{-- Kalem Tablosu (Desktop) --}}
            <div>
                <h4 class="text-sm font-semibold text-slate-700 mb-3">Sipariş Kalemleri</h4>

                {{-- Desktop Tablo --}}
                <div class="hidden md:block overflow-x-auto rounded-[8px] border border-slate-200">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Ürün</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider w-20">Miktar</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider w-32">Birim Fiyat</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider w-24">KDV %</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider w-24">İskonto %</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider w-32">Satır Toplam</th>
                                <th class="px-4 py-3 w-12"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @foreach($items as $i => $item)
                                <tr>
                                    <td class="px-4 py-2">
                                        <select wire:model="items.{{ $i }}.stock_code" class="w-full rounded-[6px] border border-slate-200 bg-white px-2 py-2 text-sm focus:border-slate-500 focus:outline-none">
                                            <option value="">Ürün Seçin...</option>
                                            @foreach($this->products as $prod)
                                                <option value="{{ $prod->stock_code }}">{{ $prod->product_name }} ({{ $prod->stock_code }})</option>
                                            @endforeach
                                        </select>
                                        @error('items.'.$i.'.stock_code') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                                    </td>
                                    <td class="px-4 py-2">
                                        <input wire:model="items.{{ $i }}.quantity" type="number" min="1" class="w-full rounded-[6px] border border-slate-200 bg-white px-2 py-2 text-sm text-right focus:border-slate-500 focus:outline-none">
                                        @error('items.'.$i.'.quantity') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                                    </td>
                                    <td class="px-4 py-2">
                                        <input wire:model="items.{{ $i }}.unit_price" type="number" min="0" step="0.01" class="w-full rounded-[6px] border border-slate-200 bg-white px-2 py-2 text-sm text-right focus:border-slate-500 focus:outline-none">
                                        @error('items.'.$i.'.unit_price') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                                    </td>
                                    <td class="px-4 py-2">
                                        <input wire:model="items.{{ $i }}.vat_rate" type="number" min="0" max="100" step="0.01" class="w-full rounded-[6px] border border-slate-200 bg-white px-2 py-2 text-sm text-right focus:border-slate-500 focus:outline-none">
                                    </td>
                                    <td class="px-4 py-2">
                                        <input wire:model="items.{{ $i }}.discount_rate" type="number" min="0" max="100" step="0.01" class="w-full rounded-[6px] border border-slate-200 bg-white px-2 py-2 text-sm text-right focus:border-slate-500 focus:outline-none">
                                    </td>
                                    <td class="px-4 py-2 text-right font-mono text-sm text-slate-700">
                                        @php
                                            $base     = (int)($item['quantity'] ?? 0) * (float)($item['unit_price'] ?? 0);
                                            $disc     = $base * ((float)($item['discount_rate'] ?? 0) / 100);
                                            $vat      = ($base - $disc) * ((float)($item['vat_rate'] ?? 20) / 100);
                                            $lineTotal = $base - $disc + $vat;
                                        @endphp
                                        {{ $formatMoney($lineTotal) }}
                                    </td>
                                    <td class="px-4 py-2 text-center">
                                        <button wire:click="removeItem({{ $i }})" class="text-slate-400 hover:text-rose-600 text-xs font-medium transition-colors" title="Satırı Kaldır">
                                            ✕
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Mobil Kalem Kartları --}}
                <div class="md:hidden space-y-3">
                    @foreach($items as $i => $item)
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-4 space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Kalem {{ $i + 1 }}</span>
                                <button wire:click="removeItem({{ $i }})" class="text-rose-500 text-xs font-medium">Kaldır</button>
                            </div>
                            <div>
                                <label class="block text-xs text-slate-500 mb-1">Ürün</label>
                                <select wire:model="items.{{ $i }}.stock_code" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base focus:border-slate-500 focus:outline-none">
                                    <option value="">Ürün Seçin...</option>
                                    @foreach($this->products as $prod)
                                        <option value="{{ $prod->stock_code }}">{{ $prod->product_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs text-slate-500 mb-1">Miktar</label>
                                    <input wire:model="items.{{ $i }}.quantity" type="number" min="1" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-right focus:outline-none">
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-500 mb-1">Birim Fiyat</label>
                                    <input wire:model="items.{{ $i }}.unit_price" type="number" min="0" step="0.01" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-right focus:outline-none">
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-500 mb-1">KDV %</label>
                                    <input wire:model="items.{{ $i }}.vat_rate" type="number" min="0" max="100" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-right focus:outline-none">
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-500 mb-1">İskonto %</label>
                                    <input wire:model="items.{{ $i }}.discount_rate" type="number" min="0" max="100" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-right focus:outline-none">
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Kalem Ekle & Özet --}}
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mt-4 gap-4">
                    <button wire:click="addItem" class="inline-flex items-center text-sm text-slate-600 hover:text-slate-900 font-medium transition-colors">
                        + Kalem Ekle
                    </button>
                    <div class="flex flex-col items-end gap-1 text-sm text-slate-600 font-mono">
                        <div class="flex gap-6">
                            <span class="text-slate-400">Ara Toplam:</span>
                            <span>{{ $formatMoney($this->subtotal) }}</span>
                        </div>
                        <div class="flex gap-6 text-rose-600">
                            <span class="text-slate-400">İskonto (Satır + Genel):</span>
                            <span>- {{ $formatMoney($this->discountTotal) }}</span>
                        </div>
                        <div class="flex gap-6">
                            <span class="text-slate-400">KDV:</span>
                            <span>{{ $formatMoney($this->vatTotal) }}</span>
                        </div>
                        <div class="flex gap-6 border-t border-slate-200 pt-1 mt-1 font-bold text-slate-900 text-base">
                            <span>Genel Toplam:</span>
                            <span>{{ $formatMoney($this->total) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Form Aksiyonları --}}
            <div class="flex flex-col sm:flex-row gap-3 pt-4 border-t border-slate-100">
                <button
                    wire:click="createPurchaseOrder"
                    wire:loading.attr="disabled"
                    id="btn-submit-purchase-order"
                    class="inline-flex items-center justify-center px-5 py-2 text-sm font-medium text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 transition-colors min-h-[44px]"
                >
                    <span wire:loading.remove wire:target="createPurchaseOrder">Taslak Oluştur</span>
                    <span wire:loading wire:target="createPurchaseOrder">Kaydediliyor...</span>
                </button>
                <button wire:click="$set('showCreateForm', false)" class="inline-flex items-center justify-center px-5 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-[6px] hover:bg-slate-50 transition-colors min-h-[44px]">
                    İptal
                </button>
            </div>
        </section>
    @endif

    {{-- Sipariş Listesi Bölümü --}}
    <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm">
        {{-- Command Bar --}}
        <div class="border-b border-slate-200 p-4 lg:px-6">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div class="flex flex-col sm:flex-row gap-3 flex-1">
                    {{-- Arama --}}
                    <div class="flex-1">
                        <input
                            wire:model.live.debounce.350ms="search"
                            id="input-purchase-search"
                            type="text"
                            placeholder="Belge no veya tedarikçi adı ile ara..."
                            class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]"
                        >
                    </div>
                    {{-- Durum Filtresi --}}
                    <select wire:model.live="filterStatus" id="select-filter-status" class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                        <option value="">Tüm Durumlar</option>
                        <option value="draft">Taslak</option>
                        <option value="approved">Onaylı</option>
                        <option value="cancelled">İptal</option>
                    </select>
                    {{-- Cari Filtresi --}}
                    <select wire:model.live="filterPartyId" id="select-filter-party" class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                        <option value="">Tüm Tedarikçiler</option>
                        @foreach($this->partiesForFilter as $p)
                            <option value="{{ $p->id }}">{{ $p->display_name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Kolon Seçici Dropdown --}}
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" id="btn-column-selector" class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-[6px] hover:bg-slate-50 min-h-[44px]">
                        Kolonlar ({{ count($visibleColumns) }})
                    </button>
                    <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-20 p-2 space-y-1">
                        @foreach($this->columnDefs as $colKey => $colLabel)
                            <label class="flex items-center px-2 py-1.5 hover:bg-slate-50 rounded cursor-pointer text-sm">
                                <input type="checkbox" wire:click="toggleColumn('{{ $colKey }}')" {{ in_array($colKey, $visibleColumns, true) ? 'checked' : '' }} class="rounded border-slate-300 text-slate-900 focus:ring-slate-900 mr-2">
                                <span class="text-slate-700">{{ $colLabel }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Detaylı Filtreler --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mt-3 border-t border-slate-100 pt-3">
                {{-- Şirket Filtresi --}}
                <div>
                    <label class="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Şirket (Legal Entity)</label>
                    <select wire:model.live="filterLegalEntityId" id="select-filter-legal-entity" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                        <option value="">Tüm Şirketler</option>
                        @foreach($this->legalEntities as $le)
                            <option value="{{ $le->id }}">{{ $le->name }}</option>
                        @endforeach
                    </select>
                </div>
                {{-- Depo Filtresi --}}
                <div>
                    <label class="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Depo</label>
                    <select wire:model.live="filterWarehouseId" id="select-filter-warehouse" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                        <option value="">Tüm Depolar</option>
                        @foreach($this->warehouses as $wh)
                            <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                        @endforeach
                    </select>
                </div>
                {{-- Tarih Başlangıç --}}
                <div>
                    <label class="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Başlangıç Tarihi</label>
                    <input wire:model.live="filterDateFrom" type="date" id="filter-date-from" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                </div>
                {{-- Tarih Bitiş --}}
                <div>
                    <label class="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Bitiş Tarihi</label>
                    <input wire:model.live="filterDateTo" type="date" id="filter-date-to" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                </div>
            </div>

            @if($search !== '' || $filterStatus !== '' || $filterPartyId || $filterLegalEntityId || $filterWarehouseId || $filterDateFrom !== '' || $filterDateTo !== '')
                <div class="mt-3 flex justify-end">
                    <button wire:click="$set('search', ''); $set('filterStatus', ''); $set('filterPartyId', null); $set('filterLegalEntityId', null); $set('filterWarehouseId', null); $set('filterDateFrom', ''); $set('filterDateTo', '')"
                        class="text-xs text-slate-500 hover:text-slate-900 underline underline-offset-2 whitespace-nowrap">
                        Filtreleri Temizle
                    </button>
                </div>
            @endif
        </div>

        {{-- Tablo (Desktop) --}}
        <div class="hidden md:block overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        @if(in_array('id', $visibleColumns, true))
                            <th wire:click="sortTable('id')" class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider cursor-pointer select-none">
                                No @if($sortColumn === 'id') {!! $sortDirection === 'asc' ? '↑' : '↓' !!} @endif
                            </th>
                        @endif
                        @if(in_array('document_number', $visibleColumns, true))
                            <th wire:click="sortTable('document_number')" class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider cursor-pointer select-none">
                                Belge No @if($sortColumn === 'document_number') {!! $sortDirection === 'asc' ? '↑' : '↓' !!} @endif
                            </th>
                        @endif
                        @if(in_array('order_date', $visibleColumns, true))
                            <th wire:click="sortTable('order_date')" class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider cursor-pointer select-none">
                                Tarih @if($sortColumn === 'order_date') {!! $sortDirection === 'asc' ? '↑' : '↓' !!} @endif
                            </th>
                        @endif
                        @if(in_array('party', $visibleColumns, true))
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider select-none">Tedarikçi</th>
                        @endif
                        @if(in_array('description', $visibleColumns, true))
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider select-none">Açıklama</th>
                        @endif
                        @if(in_array('total_amount', $visibleColumns, true))
                            <th wire:click="sortTable('total_amount')" class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider cursor-pointer select-none">
                                Toplam @if($sortColumn === 'total_amount') {!! $sortDirection === 'asc' ? '↑' : '↓' !!} @endif
                            </th>
                        @endif
                        @if(in_array('status', $visibleColumns, true))
                            <th wire:click="sortTable('status')" class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider cursor-pointer select-none">
                                Durum @if($sortColumn === 'status') {!! $sortDirection === 'asc' ? '↑' : '↓' !!} @endif
                            </th>
                        @endif
                        @if(in_array('action', $visibleColumns, true))
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider select-none">İşlem</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse($this->orders as $order)
                        @php [$statusText, $statusClass] = $statusLabel($order->status); @endphp
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            @if(in_array('id', $visibleColumns, true))
                                <td class="px-4 py-3 text-slate-500 font-mono text-xs">{{ $order->id }}</td>
                            @endif
                            @if(in_array('document_number', $visibleColumns, true))
                                <td class="px-4 py-3 font-mono text-sm text-slate-900 whitespace-nowrap">{{ $order->document_number }}</td>
                            @endif
                            @if(in_array('order_date', $visibleColumns, true))
                                <td class="px-4 py-3 text-slate-500 whitespace-nowrap">{{ $order->order_date->format('d.m.Y') }}</td>
                            @endif
                            @if(in_array('party', $visibleColumns, true))
                                <td class="px-4 py-3 text-slate-700 whitespace-nowrap">
                                    {{ $order->party?->display_name ?? '—' }}
                                    <div class="text-[10px] text-slate-400 mt-0.5">Depo: {{ $order->warehouse?->name ?? '—' }}</div>
                                </td>
                            @endif
                            @if(in_array('description', $visibleColumns, true))
                                <td class="px-4 py-3 text-slate-500 max-w-xs truncate">{{ $order->description ?? '—' }}</td>
                            @endif
                            @if(in_array('total_amount', $visibleColumns, true))
                                <td class="px-4 py-3 text-right font-mono text-slate-900 font-medium whitespace-nowrap">
                                    {{ $formatMoney($order->total_amount) }}
                                </td>
                            @endif
                            @if(in_array('status', $visibleColumns, true))
                                <td class="px-4 py-3 text-center">
                                    <span class="px-2 py-0.5 text-xs font-semibold rounded font-mono {{ $statusClass }}">{{ $statusText }}</span>
                                </td>
                            @endif
                            @if(in_array('action', $visibleColumns, true))
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    @if($order->status === 'draft')
                                        <button
                                            wire:click="approveOrder({{ $order->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="approveOrder({{ $order->id }})"
                                            id="btn-approve-{{ $order->id }}"
                                            class="inline-flex items-center px-3 py-1 text-xs font-medium text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 rounded-[6px] transition-colors"
                                        >
                                            <span wire:loading.remove wire:target="approveOrder({{ $order->id }})">Onayla</span>
                                            <span wire:loading wire:target="approveOrder({{ $order->id }})">...</span>
                                        </button>
                                    @elseif($order->status === 'approved')
                                        <button
                                            wire:click="confirmCancel({{ $order->id }})"
                                            id="btn-cancel-{{ $order->id }}"
                                            class="inline-flex items-center px-3 py-1 text-xs font-medium text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 rounded-[6px] transition-colors"
                                        >
                                            İptal Et
                                        </button>
                                    @else
                                        <span class="text-xs text-slate-400">—</span>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($visibleColumns) }}" class="px-4 py-12 text-center text-sm text-slate-400">
                                Satın alma siparişi bulunamadı.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Mobil Kart Görünümü --}}
        <div class="md:hidden divide-y divide-slate-100">
            @forelse($this->orders as $order)
                @php [$statusText, $statusClass] = $statusLabel($order->status); @endphp
                <div class="p-4 space-y-3">
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-mono text-sm font-semibold text-slate-900">{{ $order->document_number }}</span>
                        <span class="px-2 py-0.5 text-xs font-semibold rounded font-mono {{ $statusClass }}">{{ $statusText }}</span>
                    </div>
                    <div class="text-sm text-slate-600">{{ $order->party?->display_name ?? '—' }}</div>
                    <div class="flex items-center justify-between text-xs text-slate-500">
                        <span>{{ $order->order_date->format('d.m.Y') }}</span>
                        <span>{{ $order->warehouse?->name ?? '—' }}</span>
                        <span class="font-mono font-semibold text-slate-900">{{ $formatMoney($order->total_amount) }}</span>
                    </div>
                    <div class="flex gap-2">
                        @if($order->status === 'draft')
                            <button wire:click="approveOrder({{ $order->id }})" id="btn-mob-approve-{{ $order->id }}" class="flex-1 py-2 text-sm font-medium text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 rounded-[6px] transition-colors text-center">
                                Onayla
                            </button>
                        @elseif($order->status === 'approved')
                            <button wire:click="confirmCancel({{ $order->id }})" id="btn-mob-cancel-{{ $order->id }}" class="flex-1 py-2 text-sm font-medium text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 rounded-[6px] transition-colors text-center">
                                İptal Et
                            </button>
                        @endif
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-sm text-slate-400">Satın alma siparişi bulunamadı.</div>
            @endforelse
        </div>

        {{-- Sayfalama --}}
        @if($this->orders->hasPages())
            <div class="border-t border-slate-200 px-4 py-3 lg:px-6">
                {{ $this->orders->links() }}
            </div>
        @endif
    </section>

    {{-- İptal Nedeni Modalı --}}
    @if($showCancelModal)
        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4 z-50">
            <div class="bg-white rounded-[10px] border border-slate-200 shadow-xl max-w-md w-full p-6 space-y-4">
                <h3 class="text-base font-semibold text-slate-900">Satın Alma Siparişini İptal Et</h3>
                <p class="text-xs text-slate-500">
                    Siparişi iptal ettiğinizde depodaki stok miktarları azaltılacak ve cari borç kaydı silinecektir. Lütfen iptal nedenini belirtin:
                </p>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">İptal Nedeni</label>
                    <input wire:model="cancelReason" id="input-cancel-reason" type="text" placeholder="İptal edilme gerekçesi..." class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                </div>
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button wire:click="cancelOrder" id="btn-submit-cancel" class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-rose-600 hover:bg-rose-700 rounded-[6px] transition-colors min-h-[44px]">
                        İptal İşlemini Onayla
                    </button>
                    <button wire:click="$set('showCancelModal', false)" id="btn-close-cancel-modal" class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-[6px] hover:bg-slate-50 transition-colors min-h-[44px]">
                        Kapat
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
