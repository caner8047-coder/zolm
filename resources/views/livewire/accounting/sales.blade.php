@php
    $formatMoney = fn ($value) => '₺' . number_format((float) $value, 2, ',', '.');
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
                    Satış Yönetimi
                </div>
                <h1 class="mt-3 text-xl font-semibold tracking-tight text-slate-950 lg:text-2xl">Satış Siparişleri & Satış Faturaları</h1>
                <p class="mt-2 text-sm text-slate-500">
                    Müşteri satış siparişlerini taslak olarak hazırlayın, KDV ve iskonto oranlarını hesaplayın ve onaylayarak faturalandırın.
                </p>
            </div>
            <div class="shrink-0">
                <button wire:click="$toggle('showCreateForm')" class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 transition-colors min-h-[44px]">
                    Yeni Satış Siparişi
                </button>
            </div>
        </div>
    </section>

    {{-- KPI Kartları --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 lg:gap-4">
        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
            <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider block">Taslak Siparişler</span>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-bold text-slate-900 font-mono">{{ $this->kpis['draftCount'] }}</span>
                <span class="text-xs text-slate-500">adet</span>
            </div>
        </div>
        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
            <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider block">Onaylı Satış Toplamı</span>
            <div class="mt-2">
                <span class="text-2xl font-bold text-slate-900 font-mono">{{ $formatMoney($this->kpis['approvedTotal']) }}</span>
            </div>
        </div>
        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
            <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider block">İptal Satış Toplamı</span>
            <div class="mt-2">
                <span class="text-2xl font-bold text-slate-900 font-mono">{{ $formatMoney($this->kpis['cancelledTotal']) }}</span>
            </div>
        </div>
        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
            <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider block">Açık Alacak Toplamı</span>
            <div class="mt-2">
                <span class="text-2xl font-bold text-slate-900 font-mono">{{ $formatMoney($this->kpis['openReceivableTotal']) }}</span>
            </div>
        </div>
        <div class="rounded-[8px] border border-slate-200 p-4 {{ $this->kpis['stockRiskDraftCount'] > 0 ? 'bg-amber-50/50 border-amber-200' : 'bg-slate-50/70' }}">
            <span class="text-[11px] font-semibold uppercase tracking-wider block {{ $this->kpis['stockRiskDraftCount'] > 0 ? 'text-amber-700' : 'text-slate-500' }}">Stok Riskli Taslaklar</span>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-bold font-mono {{ $this->kpis['stockRiskDraftCount'] > 0 ? 'text-amber-800' : 'text-slate-900' }}">{{ $this->kpis['stockRiskDraftCount'] }}</span>
                <span class="text-xs text-slate-500">adet</span>
            </div>
        </div>
    </div>

    {{-- Satış Siparişi Oluşturma Formu --}}
    @if($showCreateForm)
        <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6 space-y-6">
            <h3 class="text-base font-semibold text-slate-900 border-b border-slate-100 pb-3">Satış Siparişi (Taslak) Oluştur</h3>

            {{-- Başlık Bilgileri --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Müşteri (Cari)</label>
                    <select wire:model="partyId" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                        <option value="">Cari Seçin...</option>
                        @foreach($this->parties as $p)
                            <option value="{{ $p->id }}">{{ $p->display_name }} ({{ $p->typeLabel() }})</option>
                        @endforeach
                    </select>
                    @error('partyId') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Şirket (Legal Entity)</label>
                    <select wire:model="legalEntityId" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                        <option value="">Seçin (Opsiyonel)</option>
                        @foreach($this->legalEntities as $le)
                            <option value="{{ $le->id }}">{{ $le->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Depo <span class="text-rose-500">*</span></label>
                    <select wire:model="warehouseId" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                        <option value="">Depo Seçin...</option>
                        @foreach($this->warehouses as $wh)
                            <option value="{{ $wh->id }}">{{ $wh->name }}{{ $wh->is_default ? ' (Varsayılan)' : '' }}</option>
                        @endforeach
                    </select>
                    @if($this->warehouses->isEmpty())
                        <p class="mt-1 text-xs text-amber-600">Aktif depo bulunamadı. Önce depo oluşturun.</p>
                    @endif
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Fatura / Belge No</label>
                    <input type="text" wire:model="documentNumber" placeholder="Örn: FAT-2026-0001" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
                    @error('documentNumber') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Belge Tarihi</label>
                    <input type="date" wire:model="orderDate" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
                    @error('orderDate') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Ekstra İndirim Tutarı</label>
                    <input type="number" step="0.01" wire:model.live="discountAmount" placeholder="0.00" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm text-right font-mono focus:border-slate-500 focus:outline-none min-h-[44px]" />
                    @error('discountAmount') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div class="sm:col-span-3">
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Açıklama</label>
                    <input type="text" wire:model="description" placeholder="Sipariş / Sevkiyat notları..." class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
                </div>
            </div>

            {{-- Kalem Detayları --}}
            <div class="space-y-3">
                <h4 class="text-sm font-semibold text-slate-700">Sipariş Kalemleri</h4>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-left text-sm text-slate-600 min-w-[700px]">
                        <thead>
                            <tr class="border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider bg-slate-50/50">
                                <th class="p-3 w-1/3">Ürün</th>
                                <th class="p-3 w-20">Miktar</th>
                                <th class="p-3 w-28">Birim Fiyat</th>
                                <th class="p-3 w-24">KDV Oranı</th>
                                <th class="p-3 w-24">İskonto (%)</th>
                                <th class="p-3 text-right w-32">Satır Toplamı (KDV Dahil)</th>
                                <th class="p-3 text-center w-12">Aksiyon</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($items as $index => $item)
                                @php
                                    $lineQty = (int) ($item['quantity'] ?? 0);
                                    $linePrice = (float) ($item['unit_price'] ?? 0);
                                    $lineVat = (float) ($item['vat_rate'] ?? 20.00);
                                    $lineDisc = (float) ($item['discount_rate'] ?? 0.00);

                                    $baseTotal = $lineQty * $linePrice;
                                    $lineDiscount = round($baseTotal * ($lineDisc / 100), 2);
                                    $totalBeforeVat = $baseTotal - $lineDiscount;
                                    $lineVatAmount = round($totalBeforeVat * ($lineVat / 100), 2);
                                    $lineTotal = round($totalBeforeVat + $lineVatAmount, 2);
                                @endphp
                                <tr>
                                    <td class="p-2">
                                        <select wire:model="items.{{ $index }}.stock_code" class="w-full rounded-[6px] border border-slate-200 bg-white px-2 py-1.5 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                                            <option value="">Ürün Seçin...</option>
                                            @foreach($this->products as $p)
                                                <option value="{{ $p->stock_code }}">{{ $p->product_name }} ({{ $p->stock_code }})</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="p-2">
                                        <input type="number" wire:model.live="items.{{ $index }}.quantity" class="w-full rounded-[6px] border border-slate-200 bg-white px-2 py-1.5 text-base sm:text-sm text-right font-mono focus:border-slate-500 focus:outline-none min-h-[44px]" />
                                    </td>
                                    <td class="p-2">
                                        <input type="number" step="0.01" wire:model.live="items.{{ $index }}.unit_price" class="w-full rounded-[6px] border border-slate-200 bg-white px-2 py-1.5 text-base sm:text-sm text-right font-mono focus:border-slate-500 focus:outline-none min-h-[44px]" />
                                    </td>
                                    <td class="p-2">
                                        <select wire:model.live="items.{{ $index }}.vat_rate" class="w-full rounded-[6px] border border-slate-200 bg-white px-2 py-1.5 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                                            <option value="20.00">%20</option>
                                            <option value="10.00">%10</option>
                                            <option value="1.00">%1</option>
                                            <option value="0.00">%0</option>
                                        </select>
                                    </td>
                                    <td class="p-2">
                                        <input type="number" step="0.01" wire:model.live="items.{{ $index }}.discount_rate" class="w-full rounded-[6px] border border-slate-200 bg-white px-2 py-1.5 text-base sm:text-sm text-right font-mono focus:border-slate-500 focus:outline-none min-h-[44px]" />
                                    </td>
                                    <td class="p-2 text-right font-mono font-semibold text-slate-900">
                                        {{ $formatMoney($lineTotal) }}
                                    </td>
                                    <td class="p-2 text-center">
                                        <button type="button" wire:click="removeItem({{ $index }})" class="p-2 text-rose-600 hover:text-rose-900 rounded hover:bg-rose-50 flex items-center justify-center min-w-[40px] min-h-[40px]">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-between items-center mt-3">
                    <button type="button" wire:click="addItem" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-[6px] transition-colors min-h-[44px]">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Satır Ekle
                    </button>

                    <div class="flex items-center gap-6 font-mono text-sm text-slate-700 bg-slate-50 border border-slate-200 rounded-[6px] px-4 py-2">
                        <div>Ara Toplam: <span class="font-bold text-slate-900">{{ $formatMoney($this->subtotal) }}</span></div>
                        <div>İndirim: <span class="font-bold text-rose-600">-{{ $formatMoney($this->discountTotal) }}</span></div>
                        <div>KDV Toplam: <span class="font-bold text-slate-600">{{ $formatMoney($this->vatTotal) }}</span></div>
                        <div>Genel Toplam: <span class="font-bold text-slate-950">{{ $formatMoney($this->total) }}</span></div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2 border-t border-slate-100 pt-4 mt-6">
                <button type="button" wire:click="$set('showCreateForm', false)" class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-[6px] hover:bg-slate-50 min-h-[44px]">
                    İptal
                </button>
                <button type="button" wire:click="createSalesOrder" class="px-4 py-2 text-sm font-medium text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 min-h-[44px]">
                    Taslağı Kaydet
                </button>
            </div>
        </section>
    @endif

    {{-- Siparişler Listesi ve Tablo --}}
    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6 space-y-4">
        {{-- Filtreler ve Tablo Araçları --}}
        <div class="space-y-3">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-slate-100 pb-4">
                <div class="flex-1 grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                    <div class="sm:col-span-2 lg:col-span-1">
                        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Arama</label>
                        <input type="text" wire:model.live="search" placeholder="Belge no veya açıklama..." class="block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Durum</label>
                        <select wire:model.live="filterStatus" class="block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                            <option value="">Tüm Durumlar</option>
                            <option value="draft">Taslak (Draft)</option>
                            <option value="approved">Onaylandı (Approved)</option>
                            <option value="cancelled">İptal Edildi (Cancelled)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Müşteri (Cari)</label>
                        <select wire:model.live="filterPartyId" class="block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                            <option value="">Tüm Müşteriler</option>
                            @foreach($this->parties as $p)
                                <option value="{{ $p->id }}">{{ $p->display_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Şirket (Legal Entity)</label>
                        <select wire:model.live="filterLegalEntityId" class="block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                            <option value="">Tüm Şirketler</option>
                            @foreach($this->legalEntities as $le)
                                <option value="{{ $le->id }}">{{ $le->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="shrink-0 flex items-center justify-end gap-2 self-end">
                    @include('livewire.partials.mp-column-toggle', ['columnDefs' => $this->columnDefs, 'visibleColumns' => $visibleColumns])
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-center">
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Başlangıç Tarihi</label>
                    <input type="date" wire:model.live="filterDateFrom" class="block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Bitiş Tarihi</label>
                    <input type="date" wire:model.live="filterDateTo" class="block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
                </div>
                <div class="flex items-center pt-5">
                    <label class="flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                        <input type="checkbox" wire:model.live="filterStockRisk" class="rounded border-slate-300 text-slate-900 focus:ring-indigo-200" />
                        <span class="font-medium">Sadece Yetersiz Stok Riski Taşıyanları Göster</span>
                    </label>
                </div>
            </div>
        </div>

        {{-- Desktop Tablo Görünümü (md ve üstü) --}}
        <div class="hidden md:block overflow-x-auto bg-white rounded-lg border border-slate-200">
            <style>
                .col-resize-handle {
                    position: absolute; right: 0; top: 0; bottom: 0; width: 4px;
                    cursor: col-resize; background: transparent; z-index: 10;
                    transition: background 0.15s;
                }
                .col-resize-handle:hover, .col-resize-handle.active { background: #6366f1; }
                .sortable-th { cursor: pointer; user-select: none; position: relative; }
                .sortable-th:hover { background: #f8fafc; }
            </style>
            <table class="w-full border-collapse text-left text-sm text-slate-600 table-fixed" x-data="columnResize()" id="salesOrdersTable">
                <thead>
                    <tr class="border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider bg-slate-50/50">
                        @if(in_array('id', $visibleColumns, true))
                            <th class="p-4 w-20 sortable-th" wire:click="sortTable('id')">
                                <div class="flex items-center gap-1">
                                    No
                                    @if($sortColumn === 'id')
                                        <span class="text-[10px] text-slate-400">{!! $sortDirection === 'asc' ? '▲' : '▼' !!}</span>
                                    @endif
                                </div>
                                <div class="col-resize-handle" @mousedown.stop.prevent="startResize($event, $el.parentElement)"></div>
                            </th>
                        @endif
                        @if(in_array('document_number', $visibleColumns, true))
                            <th class="p-4 w-32 sortable-th" wire:click="sortTable('document_number')">
                                <div class="flex items-center gap-1">
                                    Belge No
                                    @if($sortColumn === 'document_number')
                                        <span class="text-[10px] text-slate-400">{!! $sortDirection === 'asc' ? '▲' : '▼' !!}</span>
                                    @endif
                                </div>
                                <div class="col-resize-handle" @mousedown.stop.prevent="startResize($event, $el.parentElement)"></div>
                            </th>
                        @endif
                        @if(in_array('order_date', $visibleColumns, true))
                            <th class="p-4 w-28 sortable-th" wire:click="sortTable('order_date')">
                                <div class="flex items-center gap-1">
                                    Tarih
                                    @if($sortColumn === 'order_date')
                                        <span class="text-[10px] text-slate-400">{!! $sortDirection === 'asc' ? '▲' : '▼' !!}</span>
                                    @endif
                                </div>
                                <div class="col-resize-handle" @mousedown.stop.prevent="startResize($event, $el.parentElement)"></div>
                            </th>
                        @endif
                        @if(in_array('party', $visibleColumns, true))
                            <th class="p-4 w-48 relative">
                                <div class="flex items-center gap-1">
                                    Müşteri / Cari
                                </div>
                                <div class="col-resize-handle" @mousedown.stop.prevent="startResize($event, $el.parentElement)"></div>
                            </th>
                        @endif
                        @if(in_array('description', $visibleColumns, true))
                            <th class="p-4 w-48 relative">
                                <div class="flex items-center gap-1">
                                    Açıklama
                                </div>
                                <div class="col-resize-handle" @mousedown.stop.prevent="startResize($event, $el.parentElement)"></div>
                            </th>
                        @endif
                        @if(in_array('total_amount', $visibleColumns, true))
                            <th class="p-4 text-right w-36 sortable-th" wire:click="sortTable('total_amount')">
                                <div class="flex items-center justify-end gap-1">
                                    Toplam Tutar
                                    @if($sortColumn === 'total_amount')
                                        <span class="text-[10px] text-slate-400">{!! $sortDirection === 'asc' ? '▲' : '▼' !!}</span>
                                    @endif
                                </div>
                                <div class="col-resize-handle" @mousedown.stop.prevent="startResize($event, $el.parentElement)"></div>
                            </th>
                        @endif
                        @if(in_array('status', $visibleColumns, true))
                            <th class="p-4 text-center w-28 sortable-th" wire:click="sortTable('status')">
                                <div class="flex items-center justify-center gap-1">
                                    Durum
                                    @if($sortColumn === 'status')
                                        <span class="text-[10px] text-slate-400">{!! $sortDirection === 'asc' ? '▲' : '▼' !!}</span>
                                    @endif
                                </div>
                                <div class="col-resize-handle" @mousedown.stop.prevent="startResize($event, $el.parentElement)"></div>
                            </th>
                        @endif
                        @if(in_array('action', $visibleColumns, true))
                            <th class="p-4 text-center w-32 relative">
                                Aksiyon
                                <div class="col-resize-handle" @mousedown.stop.prevent="startResize($event, $el.parentElement)"></div>
                            </th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($this->orders as $order)
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            @if(in_array('id', $visibleColumns, true))
                                <td class="p-4 font-mono font-bold text-slate-900 truncate">#{{ $order->id }}</td>
                            @endif
                            @if(in_array('document_number', $visibleColumns, true))
                                <td class="p-4 font-mono text-xs font-semibold truncate">{{ $order->document_number }}</td>
                            @endif
                            @if(in_array('order_date', $visibleColumns, true))
                                <td class="p-4 text-slate-500 font-mono text-xs truncate">{{ $order->order_date->format('d.m.Y') }}</td>
                            @endif
                            @if(in_array('party', $visibleColumns, true))
                                <td class="p-4 truncate">
                                    <div class="font-semibold text-slate-900 truncate">{{ $order->party->display_name }}</div>
                                    @if($order->legalEntity)
                                        <div class="text-[10px] text-slate-400 truncate">Şirket: {{ $order->legalEntity->name }}</div>
                                    @endif
                                </td>
                            @endif
                            @if(in_array('description', $visibleColumns, true))
                                <td class="p-4 text-slate-600 truncate">{{ $order->description ?: '-' }}</td>
                            @endif
                            @if(in_array('total_amount', $visibleColumns, true))
                                <td class="p-4 text-right font-mono font-bold text-slate-900 truncate">{{ $formatMoney($order->total_amount) }}</td>
                            @endif
                            @if(in_array('status', $visibleColumns, true))
                                <td class="p-4 text-center">
                                    @if($order->status === 'draft')
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-600/10">Taslak</span>
                                    @elseif($order->status === 'approved')
                                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/10">Onaylı</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-rose-50 px-2 py-1 text-xs font-medium text-rose-700 ring-1 ring-inset ring-rose-600/10">İptal Edildi</span>
                                    @endif
                                </td>
                            @endif
                            @if(in_array('action', $visibleColumns, true))
                                <td class="p-4 text-center">
                                    @if($order->status === 'draft')
                                        <button type="button" wire:click="approveOrder({{ $order->id }})" class="px-3 py-1.5 text-xs font-medium text-white bg-emerald-600 rounded-[6px] hover:bg-emerald-700 transition-colors min-h-[40px] w-full">
                                            Onayla
                                        </button>
                                    @elseif($order->status === 'approved')
                                        <button type="button" wire:click="confirmCancel({{ $order->id }})" class="px-3 py-1.5 text-xs font-medium text-white bg-rose-600 rounded-[6px] hover:bg-rose-700 transition-colors min-h-[40px] w-full">
                                            İptal Et
                                        </button>
                                    @else
                                        <span class="text-xs text-slate-400">-</span>
                                    @endif
                                </td>
                            @endif
                        </tr>
                        {{-- Collapse sipariş detay satırları --}}
                        <tr class="bg-slate-50/20 text-xs">
                            <td colspan="{{ count($visibleColumns) }}" class="p-0">
                                <div class="px-8 py-3 bg-slate-50/40 border-t border-b border-slate-100 space-y-2">
                                    <div class="font-semibold text-slate-500 uppercase tracking-wider flex justify-between items-center">
                                        <span>Sipariş İçeriği</span>
                                        @if($order->status === 'cancelled' && $order->cancel_reason)
                                            <span class="text-rose-600 lowercase normal-case">İptal Nedeni: {{ $order->cancel_reason }}</span>
                                        @endif
                                    </div>
                                    <div class="space-y-1">
                                        @foreach($order->items as $item)
                                            <div class="flex justify-between items-center font-mono">
                                                <div class="flex gap-4">
                                                    <span class="font-bold text-slate-700 w-24">{{ $item->stock_code }}</span>
                                                    <span class="text-slate-600">{{ $item->product ? $item->product->product_name : 'MpProduct' }}</span>
                                                </div>
                                                <div class="flex gap-8">
                                                    <span class="text-slate-500">{{ $item->quantity }} adet</span>
                                                    <span class="text-slate-500">x {{ $formatMoney($item->unit_price) }}</span>
                                                    @if($item->discount_rate > 0)
                                                        <span class="text-rose-600">İsk. %{{ (float) $item->discount_rate }} (-{{ $formatMoney($item->discount_amount) }})</span>
                                                    @endif
                                                    <span class="text-slate-400">KDV %{{ (int) $item->vat_rate }}</span>
                                                    <span class="w-28 text-right font-bold text-slate-800">{{ $formatMoney($item->total_amount) }}</span>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($visibleColumns) }}" class="p-8 text-center text-slate-400">
                                Kayıtlı satış siparişi bulunamadı.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Mobil Kart Görünümü (md ve altı) --}}
        <div class="space-y-3 md:hidden">
            @forelse($this->orders as $order)
                <article class="overflow-hidden rounded-[8px] border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-slate-200 px-4 pt-4 pb-3">
                        <span class="font-mono font-bold text-slate-900">#{{ $order->id }}</span>
                        @if($order->status === 'draft')
                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600 ring-1 ring-inset ring-slate-600/10">Taslak</span>
                        @elseif($order->status === 'approved')
                            <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/10">Onaylı</span>
                        @else
                            <span class="inline-flex items-center rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-semibold text-rose-700 ring-1 ring-inset ring-rose-600/10">İptal Edildi</span>
                        @endif
                    </div>

                    <div class="p-4 space-y-2 text-sm text-slate-600">
                        <div class="flex justify-between">
                            <span class="text-slate-400">Belge No:</span>
                            <span class="font-mono font-semibold">{{ $order->document_number }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">Tarih:</span>
                            <span class="font-mono">{{ $order->order_date->format('d.m.Y') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">Müşteri:</span>
                            <span class="font-semibold text-slate-900">{{ $order->party->display_name }}</span>
                        </div>
                        @if($order->description)
                            <div class="flex justify-between">
                                <span class="text-slate-400">Açıklama:</span>
                                <span>{{ $order->description }}</span>
                            </div>
                        @endif
                        @if($order->status === 'cancelled' && $order->cancel_reason)
                            <div class="flex justify-between text-rose-600">
                                <span>İptal Nedeni:</span>
                                <span>{{ $order->cancel_reason }}</span>
                            </div>
                        @endif
                        <div class="flex justify-between border-t border-slate-100 pt-2 font-semibold">
                            <span class="text-slate-900">Toplam Tutar:</span>
                            <span class="font-mono text-slate-900">{{ $formatMoney($order->total_amount) }}</span>
                        </div>

                        {{-- Collapse Details inside mobile card --}}
                        <div x-data="{ expanded: false }" class="border-t border-slate-100 pt-2 mt-2">
                            <button @click="expanded = !expanded" type="button" class="w-full flex items-center justify-between text-xs font-semibold text-slate-500 hover:text-slate-800 py-1 min-h-[44px]">
                                <span>Sipariş İçeriği</span>
                                <svg class="w-4 h-4 transform transition-transform" :class="expanded ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <div x-show="expanded" x-transition class="mt-2 space-y-2 pl-2 border-l-2 border-slate-100">
                                @foreach($order->items as $item)
                                    <div class="text-xs font-mono py-1 border-b border-slate-50 last:border-b-0 space-y-1">
                                        <div class="flex justify-between font-bold text-slate-700">
                                            <span>{{ $item->stock_code }}</span>
                                            <span>{{ $formatMoney($item->total_amount) }}</span>
                                        </div>
                                        <div class="text-slate-500">{{ $item->product ? $item->product->product_name : 'MpProduct' }}</div>
                                        <div class="flex justify-between text-slate-400">
                                            <span>{{ $item->quantity }} adet x {{ $formatMoney($item->unit_price) }}</span>
                                            <span>KDV %{{ (int) $item->vat_rate }} @if($item->discount_rate > 0) İsk. %{{ (float) $item->discount_rate }} @endif</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    @if($order->status !== 'cancelled')
                        <div class="bg-slate-50 border-t border-slate-150 p-2 flex gap-2">
                            @if($order->status === 'draft')
                                <button type="button" wire:click="approveOrder({{ $order->id }})" class="flex-1 inline-flex items-center justify-center px-4 py-3 text-sm font-semibold text-white bg-emerald-600 rounded-[6px] hover:bg-emerald-700 min-h-[44px]">
                                    Onayla
                                </button>
                            @elseif($order->status === 'approved')
                                <button type="button" wire:click="confirmCancel({{ $order->id }})" class="flex-1 inline-flex items-center justify-center px-4 py-3 text-sm font-semibold text-white bg-rose-600 rounded-[6px] hover:bg-rose-700 min-h-[44px]">
                                    İptal Et
                                </button>
                            @endif
                        </div>
                    @endif
                </article>
            @empty
                <div class="rounded-[8px] border border-slate-200 bg-white p-6 text-center text-slate-400">
                    Kayıtlı satış siparişi bulunamadı.
                </div>
            @endforelse
        </div>

        <div class="mt-4">
            {{ $this->orders->links() }}
        </div>
    </section>

    {{-- İptal Reason Modalı --}}
    @if($showCancelModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm">
            <div class="w-full max-w-md rounded-[10px] border border-slate-200 bg-white p-6 shadow-xl space-y-4">
                <h3 class="text-base font-semibold text-slate-900">Satış Siparişini İptal Et</h3>
                <p class="text-sm text-slate-500">Bu satış siparişi iptal edilecek, cari alacağı geri çekilecek ve stoklar iade edilecektir. Lütfen iptal nedenini giriniz:</p>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">İptal Nedeni</label>
                    <input type="text" wire:model="cancelReason" placeholder="Örn: Müşteri talebi, Hatalı sipariş..." class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" wire:click="$set('showCancelModal', false)" class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-[6px] hover:bg-slate-50 min-h-[44px]">
                        Vazgeç
                    </button>
                    <button type="button" wire:click="cancelOrder" class="px-4 py-2 text-sm font-medium text-white bg-rose-600 rounded-[6px] hover:bg-rose-700 min-h-[44px]">
                        Siparişi İptal Et
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

{{-- Column Resize Alpine.js Component --}}
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('columnResize', () => ({
            resizing: false,
            startX: 0,
            startWidth: 0,
            currentTh: null,
            handle: null,

            startResize(e, th) {
                this.resizing = true;
                this.startX = e.pageX;
                this.currentTh = th;
                this.startWidth = th.offsetWidth;
                this.handle = e.target;
                this.handle.classList.add('active');

                const onMouseMove = (ev) => {
                    if (!this.resizing) return;
                    const diff = ev.pageX - this.startX;
                    const newWidth = Math.max(40, this.startWidth + diff);
                    this.currentTh.style.width = newWidth + 'px';
                    this.currentTh.style.minWidth = newWidth + 'px';
                };

                const onMouseUp = () => {
                    this.resizing = false;
                    if (this.handle) this.handle.classList.remove('active');
                    this.currentTh = null;
                    document.removeEventListener('mousemove', onMouseMove);
                    document.removeEventListener('mouseup', onMouseUp);
                };

                document.addEventListener('mousemove', onMouseMove);
                document.addEventListener('mouseup', onMouseUp);
            }
        }));
    });
</script>
