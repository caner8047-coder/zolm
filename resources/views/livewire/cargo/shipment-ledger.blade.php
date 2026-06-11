@php
    $stats = $this->stats;
    $shipments = $this->shipments;
    $formatCount = fn ($value) => number_format((float) $value, 0, ',', '.');
    $formatMoney = fn ($value) => '₺' . number_format((float) $value, 2, ',', '.');
    $formatSignedMoney = fn ($value) => (($value > 0) ? '+₺' : (($value < 0) ? '-₺' : '₺')) . number_format(abs((float) $value), 2, ',', '.');
    $statusLabels = [
        'draft' => 'Taslak',
        'ready' => 'Hazır',
        'label_created' => 'Barkodlu',
        'shipped' => 'Kargoda',
        'in_transit' => 'Yolda',
        'out_for_delivery' => 'Dağıtımda',
        'delivered' => 'Teslim',
        'returned' => 'İade',
        'cancelled' => 'İptal',
        'exception' => 'Sorunlu',
        'failed' => 'Hata',
    ];
    $flowLabels = [
        'order' => 'Sipariş',
        'return' => 'İade',
        'exchange' => 'Değişim',
        'supply' => 'Tedarik',
        'part' => 'Parça',
    ];
@endphp

<div class="space-y-4 lg:space-y-6">
    @if(!$this->tableReady)
        <div class="rounded-[10px] border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            Gönderi defteri tabloları henüz oluşturulmamış. Migration çalıştıktan sonra Sürat gönderi kayıtları burada görünecek.
        </div>
    @endif

    @if($message)
        <div class="rounded-[8px] border px-4 py-3 text-sm {{ $messageTone === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : ($messageTone === 'warning' ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-slate-200 bg-slate-50 text-slate-700') }}">
            {{ $message }}
        </div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-3 lg:gap-4">
        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
            <p class="text-xs font-semibold uppercase text-slate-500">Toplam gönderi</p>
            <p class="mt-2 text-2xl font-bold text-slate-900">{{ $formatCount($stats['total']) }}</p>
        </div>
        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
            <p class="text-xs font-semibold uppercase text-slate-500">Aktif</p>
            <p class="mt-2 text-2xl font-bold text-slate-900">{{ $formatCount($stats['active']) }}</p>
        </div>
        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
            <p class="text-xs font-semibold uppercase text-slate-500">Teslim</p>
            <p class="mt-2 text-2xl font-bold text-emerald-600">{{ $formatCount($stats['delivered']) }}</p>
        </div>
        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
            <p class="text-xs font-semibold uppercase text-slate-500">Sorunlu</p>
            <p class="mt-2 text-2xl font-bold text-rose-600">{{ $formatCount($stats['exceptions']) }}</p>
        </div>
        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
            <p class="text-xs font-semibold uppercase text-slate-500">Fatura farkı</p>
            <p class="mt-2 text-2xl font-bold {{ $stats['invoice_delta'] > 0 ? 'text-rose-600' : ($stats['invoice_delta'] < 0 ? 'text-emerald-600' : 'text-slate-900') }}">{{ $formatSignedMoney($stats['invoice_delta']) }}</p>
        </div>
    </div>

    <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 p-4 lg:p-5">
            <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-3 lg:gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Gönderi defteri</h2>
                    <p class="mt-1 text-sm text-slate-500">Sipariş, iade, değişim ve tedarik gönderilerini Sürat takip ve maliyet bilgisiyle yönetin.</p>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                    <button
                        type="button"
                        wire:click="createDraftsFromMarketplacePackages"
                        wire:loading.attr="disabled"
                        wire:target="createDraftsFromMarketplacePackages"
                        class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 disabled:opacity-60 sm:w-auto sm:py-2"
                    >
                        Siparişleri Hazırla
                    </button>
                    <button
                        type="button"
                        wire:click="createDraftsFromMarketplaceClaims"
                        wire:loading.attr="disabled"
                        wire:target="createDraftsFromMarketplaceClaims"
                        class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-60 sm:w-auto sm:py-2"
                    >
                        İade/Değişim Hazırla
                    </button>
                    <button
                        type="button"
                        wire:click="createDraftsFromSupplyOrders"
                        wire:loading.attr="disabled"
                        wire:target="createDraftsFromSupplyOrders"
                        class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-60 sm:w-auto sm:py-2"
                    >
                        Tedarik Hazırla
                    </button>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-3 lg:gap-4">
                <input
                    type="search"
                    wire:model.live.debounce.350ms="search"
                    placeholder="Takip, sipariş, müşteri ara"
                    class="rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 placeholder:text-slate-400 focus:border-slate-900 focus:outline-none sm:text-sm"
                >
                <select wire:model.live="statusFilter" class="rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 sm:text-sm">
                    <option value="">Tüm durumlar</option>
                    @foreach($statusLabels as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
                <select wire:model.live="flowFilter" class="rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 sm:text-sm">
                    <option value="">Tüm akışlar</option>
                    @foreach($flowLabels as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
                <select wire:model.live="directionFilter" class="rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 sm:text-sm">
                    <option value="">Tüm yönler</option>
                    <option value="outgoing">Giden</option>
                    <option value="incoming">Gelen</option>
                </select>
                <div class="flex items-center justify-end">
                    <div x-data="{ open: false }" class="relative w-full sm:w-auto">
                        <button type="button" @click="open = !open" class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 sm:w-auto sm:py-2">
                            Kolonlar
                        </button>
                        <div x-show="open" x-cloak @click.outside="open = false" class="absolute right-0 z-20 mt-2 w-52 rounded-[8px] border border-slate-200 bg-white p-2 shadow-xl">
                            @foreach(static::$columnDefs as $column => $label)
                                <label class="flex cursor-pointer items-center gap-2 rounded-[6px] px-2 py-2 text-sm text-slate-700 hover:bg-slate-50">
                                    <input type="checkbox" wire:click="toggleColumn('{{ $column }}')" @checked(in_array($column, $visibleColumns, true)) class="rounded border-slate-300 text-slate-900">
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <form wire:submit.prevent="importSuratInvoice" class="mt-4 rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-3">
                    <input
                        type="file"
                        wire:model="invoiceFile"
                        accept=".xlsx,.xls,.csv,.txt"
                        class="rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-700 file:mr-3 file:rounded-[6px] file:border-0 file:bg-slate-900 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white sm:text-sm"
                    >
                    <input
                        type="text"
                        wire:model.defer="invoiceNumber"
                        placeholder="Fatura no"
                        class="rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 placeholder:text-slate-400 focus:border-slate-900 focus:outline-none sm:text-sm"
                    >
                    <input
                        type="date"
                        wire:model.defer="invoiceDate"
                        class="rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 focus:border-slate-900 focus:outline-none sm:text-sm"
                    >
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="importSuratInvoice,invoiceFile"
                        class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-100 disabled:opacity-60 sm:w-auto sm:py-2"
                    >
                        Sürat Faturasını Mutabıklaştır
                    </button>
                    <div class="flex min-w-0 items-center text-xs text-slate-500">
                        @error('invoiceFile') <span class="text-rose-600">{{ $message }}</span> @else <span>XLSX / CSV</span> @enderror
                    </div>
                </div>
            </form>
        </div>

        <div class="p-4 lg:p-5">
            <div class="space-y-3 md:hidden">
                @forelse($shipments as $shipment)
                    <article class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-slate-900">{{ $shipment->shipment_no }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $flowLabels[$shipment->flow_type] ?? $shipment->flow_type }} · {{ $shipment->direction === 'incoming' ? 'Gelen' : 'Giden' }}</p>
                            </div>
                            <span class="rounded-[6px] border border-slate-200 bg-white px-2 py-0.5 text-xs font-medium text-slate-700">{{ $statusLabels[$shipment->status] ?? $shipment->status }}</span>
                        </div>
                        <p class="mt-3 text-sm text-slate-700">{{ $shipment->customer_name ?: 'Müşteri yok' }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $shipment->destination_city ?: '-' }}{{ $shipment->destination_district ? ' / ' . $shipment->destination_district : '' }}</p>
                        <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                            <div class="rounded-[6px] border border-slate-200 bg-white px-3 py-2">
                                <span class="block text-slate-500">Takip</span>
                                <span class="mt-1 block truncate font-medium text-slate-900">{{ $shipment->tracking_number ?: '-' }}</span>
                            </div>
                            <div class="rounded-[6px] border border-slate-200 bg-white px-3 py-2">
                                <span class="block text-slate-500">Maliyet</span>
                                <span class="mt-1 block font-medium text-slate-900">{{ $formatMoney($shipment->actual_cost ?: $shipment->expected_cost) }}</span>
                            </div>
                        </div>
                        <div class="mt-3 flex flex-col sm:flex-row gap-2">
                            <button type="button" wire:click="pushShipment({{ $shipment->id }})" class="inline-flex min-h-[44px] flex-1 items-center justify-center rounded-[6px] bg-slate-900 px-3 py-2 text-sm font-medium text-white">Sürat'e Gönder</button>
                            <button type="button" wire:click="refreshTracking({{ $shipment->id }})" class="inline-flex min-h-[44px] flex-1 items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700">Takip Yenile</button>
                            @unless($shipment->is_terminal)
                                <button type="button" wire:click="cancelShipment({{ $shipment->id }})" wire:confirm="Bu Sürat gönderisini iptal etmek istediğinize emin misiniz?" class="inline-flex min-h-[44px] flex-1 items-center justify-center rounded-[6px] border border-rose-200 bg-white px-3 py-2 text-sm font-medium text-rose-700">İptal</button>
                            @endunless
                        </div>
                    </article>
                @empty
                    <div class="rounded-[8px] border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                        Gönderi kaydı bulunamadı.
                    </div>
                @endforelse
            </div>

            <div class="hidden md:block overflow-x-auto rounded-lg border border-slate-200">
                <table class="min-w-full table-fixed divide-y divide-slate-200">
                    <thead class="bg-slate-50/80">
                        <tr>
                            @foreach(static::$columnDefs as $column => $label)
                                @if(in_array($column, $visibleColumns, true))
                                    <th class="px-3 py-3 text-left text-[11px] font-semibold uppercase text-slate-500">{{ $label }}</th>
                                @endif
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse($shipments as $shipment)
                            <tr class="hover:bg-slate-50/70">
                                @if(in_array('shipment', $visibleColumns, true))
                                    <td class="px-3 py-3 align-top">
                                        <p class="truncate text-sm font-semibold text-slate-900">{{ $shipment->shipment_no }}</p>
                                        <p class="mt-1 truncate text-xs text-slate-500">{{ $shipment->order_number ?: $shipment->reference_number ?: '-' }}</p>
                                        <p class="mt-1 text-[11px] text-slate-400">{{ $flowLabels[$shipment->flow_type] ?? $shipment->flow_type }} · {{ $shipment->direction === 'incoming' ? 'Gelen' : 'Giden' }}</p>
                                    </td>
                                @endif
                                @if(in_array('customer', $visibleColumns, true))
                                    <td class="px-3 py-3 align-top">
                                        <p class="truncate text-sm font-medium text-slate-900">{{ $shipment->customer_name ?: '-' }}</p>
                                        <p class="mt-1 truncate text-xs text-slate-500">{{ $shipment->destination_city ?: '-' }}{{ $shipment->destination_district ? ' / ' . $shipment->destination_district : '' }}</p>
                                        <p class="mt-1 truncate text-[11px] text-slate-400">{{ $shipment->store?->store_name ?: $shipment->source_type }}</p>
                                    </td>
                                @endif
                                @if(in_array('carrier', $visibleColumns, true))
                                    <td class="px-3 py-3 align-top">
                                        <p class="text-sm font-medium text-slate-900">{{ $shipment->carrier_name }}</p>
                                        <p class="mt-1 truncate text-xs text-slate-500">{{ $shipment->tracking_number ?: 'Takip no yok' }}</p>
                                        <p class="mt-1 truncate text-[11px] text-slate-400">{{ $shipment->carrierAccount?->customer_code ?: 'Hesap seçilmedi' }}</p>
                                    </td>
                                @endif
                                @if(in_array('cost', $visibleColumns, true))
                                    <td class="px-3 py-3 align-top">
                                        <p class="text-sm font-semibold text-slate-900">{{ $formatMoney($shipment->actual_cost ?: $shipment->expected_cost) }}</p>
                                        <p class="mt-1 text-xs {{ $shipment->cost_delta > 0 ? 'text-rose-600' : ($shipment->cost_delta < 0 ? 'text-emerald-600' : 'text-slate-500') }}">{{ $formatSignedMoney($shipment->cost_delta) }} fark</p>
                                        <p class="mt-1 text-[11px] text-slate-400">{{ number_format((float) $shipment->total_desi, 2, ',', '.') }} desi · {{ $shipment->parcel_count }} koli</p>
                                    </td>
                                @endif
                                @if(in_array('status', $visibleColumns, true))
                                    <td class="px-3 py-3 align-top">
                                        <span class="inline-flex rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-medium text-slate-700">{{ $statusLabels[$shipment->status] ?? $shipment->status }}</span>
                                        <p class="mt-2 line-clamp-2 text-xs text-slate-500">{{ $shipment->status_label ?: $shipment->last_error ?: 'Durum bekleniyor' }}</p>
                                    </td>
                                @endif
                                @if(in_array('actions', $visibleColumns, true))
                                    <td class="px-3 py-3 align-top text-right">
                                        <div class="flex flex-col gap-2">
                                            <button type="button" wire:click="pushShipment({{ $shipment->id }})" wire:loading.attr="disabled" wire:target="pushShipment({{ $shipment->id }})" class="inline-flex min-h-[36px] items-center justify-center rounded-[6px] bg-slate-900 px-3 py-2 text-xs font-medium text-white transition hover:bg-slate-800 disabled:opacity-60">Sürat'e Gönder</button>
                                            <button type="button" wire:click="refreshTracking({{ $shipment->id }})" wire:loading.attr="disabled" wire:target="refreshTracking({{ $shipment->id }})" class="inline-flex min-h-[36px] items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-60">Takip Yenile</button>
                                            @unless($shipment->is_terminal)
                                                <button type="button" wire:click="cancelShipment({{ $shipment->id }})" wire:confirm="Bu Sürat gönderisini iptal etmek istediğinize emin misiniz?" wire:loading.attr="disabled" wire:target="cancelShipment({{ $shipment->id }})" class="inline-flex min-h-[36px] items-center justify-center rounded-[6px] border border-rose-200 bg-white px-3 py-2 text-xs font-medium text-rose-700 transition hover:bg-rose-50 disabled:opacity-60">İptal</button>
                                            @endunless
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($visibleColumns) }}" class="px-4 py-10 text-center text-sm text-slate-500">Gönderi kaydı bulunamadı.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if(method_exists($shipments, 'links'))
                <div class="mt-4">
                    {{ $shipments->links() }}
                </div>
            @endif
        </div>
    </section>
</div>
