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
                    E-Belge Akışı
                </div>
                <h1 class="mt-3 text-xl font-semibold tracking-tight text-slate-950 lg:text-2xl">e-Fatura & e-Arşiv Belge Yönetimi</h1>
                <p class="mt-2 text-sm text-slate-500">
                    Onaylanan satış siparişlerini e-Fatura veya e-Arşiv Fatura taslağına dönüştürün, GİB entegratörüne iletin ve fatura iptal süreçlerini yönetin.
                </p>
            </div>
            <div class="shrink-0">
                <button wire:click="$toggle('showCreateForm')" class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 transition-colors min-h-[44px]">
                    Yeni e-Belge Taslağı Oluştur
                </button>
            </div>
        </div>
    </section>

    {{-- e-Belge Oluşturma Formu --}}
    @if($showCreateForm)
        <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6 space-y-4">
            <h3 class="text-base font-semibold text-slate-900 border-b border-slate-100 pb-2">Onaylı Siparişten e-Belge Üret</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Satış Siparişi</label>
                    <select wire:model="selectedSalesOrderId" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                        <option value="">Seçin...</option>
                        @foreach($this->availableSalesOrders as $order)
                            <option value="{{ $order->id }}">{{ $order->document_number }} - {{ $order->party->display_name }} ({{ $formatMoney($order->total_amount) }})</option>
                        @endforeach
                    </select>
                    @error('selectedSalesOrderId') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Fatura Belge Tipi</label>
                    <select wire:model="documentType" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                        <option value="e_archive">e-Arşiv Fatura</option>
                        <option value="e_invoice">e-Fatura</option>
                    </select>
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t border-slate-100 pt-3">
                <button type="button" wire:click="$set('showCreateForm', false)" class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-[6px] hover:bg-slate-50 min-h-[44px]">İptal</button>
                <button type="button" wire:click="createEDocument" class="px-4 py-2 text-sm font-medium text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 min-h-[44px]">Taslak Oluştur</button>
            </div>
        </section>
    @endif

    {{-- Belgeler Listesi --}}
    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6 space-y-4">
        {{-- Filtreler --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="sm:col-span-2">
                <input type="text" wire:model.live="search" placeholder="Fatura no, UUID veya sipariş no ile arayın..." class="block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
            </div>
            <div>
                <select wire:model.live="filterStatus" class="block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                    <option value="">Tüm Belge Statüleri</option>
                    <option value="draft">Taslak (Draft)</option>
                    <option value="accepted">Kabul Edildi (Accepted)</option>
                    <option value="cancelled">İptal Edildi (Cancelled)</option>
                </select>
            </div>
        </div>

        {{-- Belge Tablosu --}}
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left text-sm text-slate-600 min-w-[900px]">
                <thead>
                    <tr class="border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider bg-slate-50/50">
                        <th class="p-4 w-40">Fatura / Belge No</th>
                        <th class="p-4 w-48">UUID / Referans</th>
                        <th class="p-4 w-28">Belge Tipi</th>
                        <th class="p-4">Müşteri</th>
                        <th class="p-4 w-28">Sipariş</th>
                        <th class="p-4 text-right w-32">Toplam Tutar</th>
                        <th class="p-4 text-center w-24">Durum</th>
                        <th class="p-4 text-center w-48">Aksiyonlar</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($this->documents as $doc)
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="p-4 font-mono font-bold text-slate-900">
                                {{ $doc->invoice_number ?: 'YENİ BELGE' }}
                            </td>
                            <td class="p-4 font-mono text-xs text-slate-400 select-all" title="{{ $doc->uuid }}">{{ Str::limit($doc->uuid, 8) }}...</td>
                            <td class="p-4 font-semibold text-slate-700">
                                {{ $doc->document_type === 'e_invoice' ? 'e-Fatura' : 'e-Arşiv' }}
                            </td>
                            <td class="p-4">
                                <div class="font-semibold text-slate-900">{{ $doc->salesOrder->party->display_name }}</div>
                            </td>
                            <td class="p-4 font-mono text-xs">{{ $doc->salesOrder->document_number }}</td>
                            <td class="p-4 text-right font-mono font-bold text-slate-900">{{ $formatMoney($doc->salesOrder->total_amount) }}</td>
                            <td class="p-4 text-center">
                                @if($doc->status === 'draft')
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-600/10">Taslak</span>
                                @elseif($doc->status === 'accepted')
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/10">Kabul Edildi</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-rose-50 px-2 py-1 text-xs font-medium text-rose-700 ring-1 ring-inset ring-rose-600/10">İptal Edildi</span>
                                @endif
                            </td>
                            <td class="p-4 text-center space-x-1.5 flex justify-center items-center">
                                @if($doc->status === 'draft')
                                    <button type="button" wire:click="sendToGib({{ $doc->id }})" class="px-2 py-1.5 text-xs font-medium text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 transition-colors min-h-[36px]">
                                        GİB Gönder
                                    </button>
                                @elseif($doc->status === 'accepted')
                                    <button type="button" wire:click="openCancelModal({{ $doc->id }})" class="px-2 py-1.5 text-xs font-medium text-white bg-rose-600 hover:bg-rose-700 rounded-[6px] transition-colors min-h-[36px]">
                                        İptal Et
                                    </button>
                                @endif
                                <button type="button" wire:click="openEventsModal({{ $doc->id }})" class="px-2 py-1.5 text-xs font-medium text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-[6px] transition-colors min-h-[36px]">
                                    Log
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="p-8 text-center text-slate-400">
                                Kayıtlı e-Belge bulunamadı.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $this->documents->links() }}
        </div>
    </section>

    {{-- İptal Gerekçesi Modali --}}
    @if($showCancelModal)
        <div class="fixed inset-0 bg-slate-900/50 z-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-[10px] border border-slate-200 max-w-sm w-full p-6 shadow-xl space-y-4">
                <h3 class="text-base font-semibold text-slate-900">Belgeyi İptal Et</h3>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">İptal Gerekçesi</label>
                    <textarea wire:model="cancelReason" placeholder="Gerekçe giriniz..." class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[80px]"></textarea>
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-100 pt-3">
                    <button type="button" wire:click="$set('showCancelModal', false)" class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-[6px] hover:bg-slate-50 min-h-[44px]">Vazgeç</button>
                    <button type="button" wire:click="cancelDocument" class="px-4 py-2 text-sm font-medium text-white bg-rose-600 rounded-[6px] hover:bg-rose-700 min-h-[44px]">Belgeyi İptal Et</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Olay Günlüğü Log Modali --}}
    @if($showEventsModal && $this->viewDoc)
        <div class="fixed inset-0 bg-slate-900/50 z-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-[10px] border border-slate-200 max-w-lg w-full p-6 shadow-xl space-y-4">
                <h3 class="text-base font-semibold text-slate-900 border-b border-slate-100 pb-2">Belge Durum Logu (#{{ $this->viewDoc->invoice_number ?: 'Taslak' }})</h3>
                <div class="space-y-3 max-h-[300px] overflow-y-auto pr-1">
                    @foreach($this->viewDoc->events as $event)
                        <div class="p-3 bg-slate-50 border border-slate-100 rounded-lg text-xs space-y-1.5">
                            <div class="flex justify-between items-center text-slate-400 font-mono">
                                <span>{{ $event->created_at->format('d.m.Y H:i:s') }}</span>
                                <span class="font-semibold uppercase tracking-wider text-[10px] px-1 py-0.5 rounded {{ $event->to_status === 'accepted' ? 'bg-emerald-100 text-emerald-800' : ($event->to_status === 'cancelled' ? 'bg-rose-100 text-rose-800' : 'bg-slate-100 text-slate-600') }}">{{ $event->to_status }}</span>
                            </div>
                            <p class="text-slate-700">{{ $event->message }}</p>
                        </div>
                    @endforeach
                </div>
                <div class="flex justify-end border-t border-slate-100 pt-3">
                    <button type="button" wire:click="$set('showEventsModal', false)" class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-[6px] hover:bg-slate-50 min-h-[44px]">Kapat</button>
                </div>
            </div>
        </div>
    @endif
</div>
