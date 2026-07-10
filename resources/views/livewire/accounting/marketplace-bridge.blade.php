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

    {{-- Üst Section --}}
    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <div class="inline-flex items-center rounded-[6px] border border-slate-200 bg-slate-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">
                    Entegrasyon Köprüsü
                </div>
                <h1 class="mt-3 text-xl font-semibold tracking-tight text-slate-950 lg:text-2xl">Pazaryeri Muhasebe & Finans Köprüsü</h1>
                <p class="mt-2 text-sm text-slate-500">
                    Pazaryerlerindeki satış siparişlerini ön muhasebeye, komisyon/hakediş ödemelerini ise yevmiye defterine otomatik köprüleyin.
                </p>
            </div>
            <div class="shrink-0 flex gap-2">
                @if($activeTab === 'orders')
                    <button wire:click="bridgeAllOrders" class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 transition-colors min-h-[44px]">
                        Tüm Siparişleri Köprüle
                    </button>
                @else
                    <button wire:click="bridgeAllEvents" class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 transition-colors min-h-[44px]">
                        Tüm Olayları Muhasebeleştir
                    </button>
                @endif
            </div>
        </div>
    </section>

    {{-- Filtreler & Tab Navigasyonu --}}
    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6 space-y-4">
        {{-- Tab Nav --}}
        <div class="flex border-b border-slate-200 gap-6">
            <button wire:click="$set('activeTab', 'orders')" class="pb-3 text-sm font-semibold transition-all relative {{ $activeTab === 'orders' ? 'text-slate-950 border-b-2 border-slate-900' : 'text-slate-500 hover:text-slate-800' }}">
                Pazaryeri Siparişleri
            </button>
            <button wire:click="$set('activeTab', 'events')" class="pb-3 text-sm font-semibold transition-all relative {{ $activeTab === 'events' ? 'text-slate-950 border-b-2 border-slate-900' : 'text-slate-500 hover:text-slate-800' }}">
                Hakediş & Finans Olayları
            </button>
        </div>

        {{-- Filtre Inputs --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="sm:col-span-2">
                <input type="text" wire:model.live="search" placeholder="{{ $activeTab === 'orders' ? 'Sipariş no veya müşteri ile arayın...' : 'Olay tipi veya sipariş no ile arayın...' }}" class="block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
            </div>
            <div>
                <select wire:model.live="filterStore" class="block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                    <option value="">Tüm Pazaryeri Mağazaları</option>
                    @foreach($this->stores as $st)
                        <option value="{{ $st->id }}">{{ $st->store_name }} ({{ strtoupper($st->marketplace) }})</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Tab İçeriği --}}
        @if($activeTab === 'orders')
            {{-- SİPARİŞ KÖPRÜ TABLOSU --}}
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left text-sm text-slate-600 min-w-[900px]">
                    <thead>
                        <tr class="border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider bg-slate-50/50">
                            <th class="p-4 w-32">Sipariş No</th>
                            <th class="p-4">Mağaza</th>
                            <th class="p-4">Müşteri</th>
                            <th class="p-4 w-28">Tarih</th>
                            <th class="p-4 w-24">Statü</th>
                            <th class="p-4 text-right w-32">Tutar</th>
                            <th class="p-4 text-center w-36">Köprü Durumu</th>
                            <th class="p-4 text-center w-36">Aksiyon</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($this->orders as $order)
                            @php $isBridged = $this->isBridgedOrder($order->order_number); @endphp
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="p-4 font-mono font-bold text-slate-900">{{ $order->order_number }}</td>
                                <td class="p-4">
                                    <div class="font-semibold text-slate-900">{{ $order->store->store_name }}</div>
                                    <div class="text-[10px] text-slate-400 uppercase">{{ $order->store->marketplace }}</div>
                                </td>
                                <td class="p-4 text-slate-700 font-medium">{{ $order->customer_name }}</td>
                                <td class="p-4 text-slate-500 font-mono text-xs">{{ $order->ordered_at ? $order->ordered_at->format('d.m.Y') : '-' }}</td>
                                <td class="p-4">
                                    <span class="inline-flex items-center rounded-full bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-600/10 uppercase">{{ $order->order_status }}</span>
                                </td>
                                <td class="p-4 text-right font-mono font-bold text-slate-900">
                                    @php
                                        $tot = $order->items->sum(fn($i) => ($i->unit_price ?? $i->gross_amount) * $i->quantity);
                                    @endphp
                                    {{ $formatMoney($tot) }}
                                </td>
                                <td class="p-4 text-center">
                                    @if($isBridged)
                                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/10">Köprülendi</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-600/10">Köprülenmedi</span>
                                    @endif
                                </td>
                                <td class="p-4 text-center">
                                    @if($isBridged)
                                        <span class="text-xs text-slate-400">Ön Muhasebede</span>
                                    @else
                                        <button type="button" wire:click="bridgeSingleOrder({{ $order->id }})" class="px-3 py-1.5 text-xs font-medium text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 transition-colors min-h-[36px] w-full">
                                            Köprüle (Tetikle)
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="p-8 text-center text-slate-400">
                                    Pazaryeri siparişi bulunamadı.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $this->orders->links() }}
            </div>

        @else
            {{-- KÖPRÜ Olayları TABLOSU --}}
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left text-sm text-slate-600 min-w-[900px]">
                    <thead>
                        <tr class="border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider bg-slate-50/50">
                            <th class="p-4 w-40">Finansal Olay Tipi</th>
                            <th class="p-4 w-32">İlişkili Sipariş</th>
                            <th class="p-4">Mağaza</th>
                            <th class="p-4 w-28">Olay Tarihi</th>
                            <th class="p-4 text-right w-36">Tutar</th>
                            <th class="p-4 text-center w-36">Muhasebe Durumu</th>
                            <th class="p-4 text-center w-36">Aksiyon</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($this->events as $event)
                            @php $isBridged = $this->isBridgedEvent($event->id); @endphp
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="p-4 font-semibold text-slate-900 uppercase tracking-wider text-xs">
                                    @if($event->event_type === 'commission')
                                        Komisyon Masrafı
                                    @elseif($event->event_type === 'shipping_fee' || $event->event_type === 'cargo')
                                        Kargo Masrafı
                                    @elseif($event->event_type === 'payout' || $event->event_type === 'settlement')
                                        Banka Hakediş (Payout)
                                    @else
                                        {{ $event->event_type }}
                                    @endif
                                </td>
                                <td class="p-4 font-mono text-xs">
                                    {{ $event->order ? $event->order->order_number : '-' }}
                                </td>
                                <td class="p-4">
                                    <div class="font-semibold text-slate-900">{{ $event->store->store_name }}</div>
                                    <div class="text-[10px] text-slate-400 uppercase">{{ $event->store->marketplace }}</div>
                                </td>
                                <td class="p-4 text-slate-500 font-mono text-xs">{{ $event->event_date->format('d.m.Y') }}</td>
                                <td class="p-4 text-right font-mono font-bold text-slate-900">
                                    {{ $formatMoney($event->amount) }}
                                </td>
                                <td class="p-4 text-center">
                                    @if($isBridged)
                                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/10">Muhasebeleşti</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-600/10">Beklemede</span>
                                    @endif
                                </td>
                                <td class="p-4 text-center">
                                    @if($isBridged)
                                        <span class="text-xs text-slate-400">Yevmiyeye İşlendi</span>
                                    @else
                                        <button type="button" wire:click="bridgeSingleEvent({{ $event->id }})" class="px-3 py-1.5 text-xs font-medium text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 transition-colors min-h-[36px] w-full">
                                            Muhasebeleştir
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="p-8 text-center text-slate-400">
                                    Finansal olay bulunamadı.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $this->events->links() }}
            </div>
        @endif
    </section>
</div>
