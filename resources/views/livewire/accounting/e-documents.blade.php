@php
    $formatMoney = fn ($value) => '₺' . number_format((float) $value, 2, ',', '.');
    $statusLabel = fn ($s) => match ($s) {
        'draft'     => ['Taslak',         'bg-slate-100 text-slate-700'],
        'accepted'  => ['Kabul Edildi',   'bg-emerald-50 text-emerald-700 ring-emerald-600/20'],
        'cancelled' => ['İptal Edildi',   'bg-rose-50 text-rose-700 ring-rose-600/20'],
        default     => [$s,               'bg-slate-100 text-slate-700'],
    };
@endphp

<div class="w-full space-y-4 lg:space-y-6">
    {{-- Mesaj Bildirim Alanı --}}
    @if($message !== '')
        <div class="rounded-[8px] border p-4 text-sm {{ $messageType === 'error' ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800' }}">
            {{ $message }}
        </div>
    @endif

    {{-- Üst Workspace / Guidance Section --}}
    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <div class="inline-flex items-center rounded-[6px] border border-slate-200 bg-slate-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">
                    e-Belge Portal Entegrasyonu
                </div>
                <h1 class="mt-3 text-xl font-semibold tracking-tight text-slate-950 lg:text-2xl">e-Fatura & e-Arşiv Yönetimi</h1>
                <p class="mt-2 text-sm text-slate-500">
                    Onaylanan siparişlerden otomatik taslak fatura üretin, simüle entegratör üzerinden onay numarası atayın ve audit geçmişini izleyin.
                </p>
            </div>
            <div class="shrink-0 flex gap-2">
                <button
                    wire:click="$toggle('showCreateForm')"
                    id="btn-toggle-create-form"
                    class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 text-sm font-semibold text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 transition-colors min-h-[44px]"
                >
                    {{ $showCreateForm ? 'Taslak Formunu Kapat' : 'Yeni e-Belge Taslağı' }}
                </button>
            </div>
        </div>
    </section>

    {{-- KPI Metrik Kartları --}}
    <section class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Taslak Belgeler</span>
            <span class="text-xl font-bold text-slate-900 font-mono mt-1 block">{{ $this->kpis['drafts'] }} Adet</span>
        </div>
        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Kabul Edilenler</span>
            <span class="text-xl font-bold text-emerald-700 font-mono mt-1 block">{{ $this->kpis['accepted'] }} Adet</span>
        </div>
        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">İptal Edilenler</span>
            <span class="text-xl font-bold text-rose-700 font-mono mt-1 block">{{ $this->kpis['cancelled'] }} Adet</span>
        </div>
        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Toplam Ciro (Kabul)</span>
            <span class="text-xl font-bold text-slate-950 font-mono mt-1 block">{{ $formatMoney($this->kpis['acceptedTotal']) }}</span>
        </div>
    </section>

    {{-- Taslak Oluşturma Formu --}}
    @if($showCreateForm)
        <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6 space-y-4">
            <h3 class="text-base font-semibold text-slate-900 border-b border-slate-100 pb-2">e-Belge Taslağı Oluştur</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Satış Siparişi <span class="text-rose-500">*</span></label>
                    <select wire:model.live="selectedSalesOrderId" id="select-sales-order-id" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                        <option value="">Sipariş Seçin...</option>
                        @foreach($this->availableSalesOrders as $order)
                            <option value="{{ $order->id }}">{{ $order->document_number }} - {{ $order->party?->display_name }} ({{ $formatMoney($order->total_amount) }})</option>
                        @endforeach
                    </select>
                    @error('selectedSalesOrderId') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Belge Türü <span class="text-rose-500">*</span></label>
                    <select wire:model="documentType" id="select-document-type" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                        <option value="e_archive">e-Arşiv Fatura</option>
                        <option value="e_invoice">e-Fatura (VKN Zorunlu)</option>
                    </select>
                    @error('documentType') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>

            @if($selectedSalesOrderId)
                <div class="border-t border-slate-100 pt-4 space-y-4">
                    <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wide">Alıcı (Buyer) Snapshot Önizleme ve Hardening</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-[10px] font-semibold text-slate-400 uppercase">VKN / TCKN</label>
                            <input wire:model="buyerTaxNumber" id="input-buyer-tax-number" type="text" placeholder="11 haneli TCKN veya 10 haneli VKN" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                        </div>
                        <div>
                            <label class="block text-[10px] font-semibold text-slate-400 uppercase">Vergi Dairesi</label>
                            <input wire:model="buyerTaxOffice" id="input-buyer-tax-office" type="text" placeholder="Kadıköy V.D." class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                        </div>
                        <div>
                            <label class="block text-[10px] font-semibold text-slate-400 uppercase">E-posta</label>
                            <input wire:model="buyerEmail" id="input-buyer-email" type="email" placeholder="muhasebe@firma.com" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                        </div>
                        <div>
                            <label class="block text-[10px] font-semibold text-slate-400 uppercase">Telefon</label>
                            <input wire:model="buyerPhone" id="input-buyer-phone" type="text" placeholder="0532..." class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-[10px] font-semibold text-slate-400 uppercase">Adres</label>
                            <input wire:model="buyerAddress" id="input-buyer-address" type="text" placeholder="Firma Adres Satırı..." class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                        </div>
                    </div>
                </div>
            @endif

            <div class="flex justify-end gap-2 border-t border-slate-100 pt-3">
                <button type="button" wire:click="$set('showCreateForm', false)" class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-[6px] hover:bg-slate-50 min-h-[44px]">İptal</button>
                <button type="button" wire:click="createEDocument" id="btn-submit-create-draft" class="px-4 py-2 text-sm font-semibold text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 min-h-[44px]">Taslak Oluştur</button>
            </div>
        </section>
    @endif

    {{-- Ana Tablo & Command Bar Bölümü --}}
    <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm">
        {{-- Command Bar ve Filtre Yüzeyleri --}}
        <div class="p-4 lg:p-6 border-b border-slate-200 space-y-4">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div class="flex-1">
                    <input
                        type="text"
                        wire:model.live.debounce.250ms="search"
                        id="input-e-docs-search"
                        placeholder="Fatura No, UUID, alıcı adı veya vergi no ile arayın..."
                        class="block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]"
                    />
                </div>
                <div class="flex flex-wrap gap-2 shrink-0">
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" id="btn-columns-selector" class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-[6px] hover:bg-slate-50 min-h-[44px]">
                            Kolonlar ({{ count($visibleColumns) }})
                        </button>
                        <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-20 p-2 space-y-1" style="display: none;">
                            @foreach($this->columnDefs as $colKey => $colLabel)
                                <label class="flex items-center px-2 py-1.5 hover:bg-slate-50 rounded cursor-pointer text-sm">
                                    <input type="checkbox" wire:click="toggleColumn('{{ $colKey }}')" {{ in_array($colKey, $visibleColumns, true) ? 'checked' : '' }} class="rounded border-slate-300 text-slate-900 focus:ring-slate-900 mr-2">
                                    <span class="text-slate-700">{{ $colLabel }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            {{-- Detaylı Filtreler --}}
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 pt-2">
                <div>
                    <label class="block text-[10px] font-semibold text-slate-400 uppercase">Statü</label>
                    <select wire:model.live="filterStatus" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-2 py-1 text-xs focus:outline-none min-h-[38px]">
                        <option value="">Tüm Statüler</option>
                        <option value="draft">Taslak</option>
                        <option value="accepted">Kabul Edildi</option>
                        <option value="cancelled">İptal Edildi</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-semibold text-slate-400 uppercase">Belge Türü</label>
                    <select wire:model.live="filterDocumentType" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-2 py-1 text-xs focus:outline-none min-h-[38px]">
                        <option value="">Tüm Türler</option>
                        <option value="e_invoice">e-Fatura</option>
                        <option value="e_archive">e-Arşiv Fatura</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-semibold text-slate-400 uppercase">Cari / Müşteri</label>
                    <select wire:model.live="filterPartyId" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-2 py-1 text-xs focus:outline-none min-h-[38px]">
                        <option value="">Tüm Müşteriler</option>
                        @foreach($this->parties as $party)
                            <option value="{{ $party->id }}">{{ $party->display_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-semibold text-slate-400 uppercase">Şirket / Entity</label>
                    <select wire:model.live="filterLegalEntityId" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-2 py-1 text-xs focus:outline-none min-h-[38px]">
                        <option value="">Tüm Şirketler</option>
                        @foreach($this->legalEntities as $le)
                            <option value="{{ $le->id }}">{{ $le->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-semibold text-slate-400 uppercase">Başlangıç Tarihi</label>
                    <input type="date" wire:model.live="filterDateFrom" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-2 py-1 text-xs focus:outline-none min-h-[38px]">
                </div>
                <div>
                    <label class="block text-[10px] font-semibold text-slate-400 uppercase">Bitiş Tarihi</label>
                    <input type="date" wire:model.live="filterDateTo" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-2 py-1 text-xs focus:outline-none min-h-[38px]">
                </div>
            </div>
        </div>

        {{-- Desktop Tablo --}}
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                    <tr>
                        @if(in_array('id', $visibleColumns, true))
                            <th wire:click="sortTable('id')" class="p-4 cursor-pointer select-none">No @if($sortColumn === 'id') {!! $sortDirection === 'asc' ? '↑' : '↓' !!} @endif</th>
                        @endif
                        @if(in_array('issue_date', $visibleColumns, true))
                            <th wire:click="sortTable('issue_date')" class="p-4 cursor-pointer select-none">Tarih @if($sortColumn === 'issue_date') {!! $sortDirection === 'asc' ? '↑' : '↓' !!} @endif</th>
                        @endif
                        @if(in_array('invoice_number', $visibleColumns, true))
                            <th wire:click="sortTable('invoice_number')" class="p-4 cursor-pointer select-none">Belge No / UUID @if($sortColumn === 'invoice_number') {!! $sortDirection === 'asc' ? '↑' : '↓' !!} @endif</th>
                        @endif
                        @if(in_array('document_type', $visibleColumns, true))
                            <th wire:click="sortTable('document_type')" class="p-4 cursor-pointer select-none">Belge Türü @if($sortColumn === 'document_type') {!! $sortDirection === 'asc' ? '↑' : '↓' !!} @endif</th>
                        @endif
                        @if(in_array('buyer', $visibleColumns, true))
                            <th class="p-4">Alıcı</th>
                        @endif
                        @if(in_array('total_amount', $visibleColumns, true))
                            <th wire:click="sortTable('total_amount')" class="p-4 text-right cursor-pointer select-none">Tutar @if($sortColumn === 'total_amount') {!! $sortDirection === 'asc' ? '↑' : '↓' !!} @endif</th>
                        @endif
                        @if(in_array('status', $visibleColumns, true))
                            <th wire:click="sortTable('status')" class="p-4 text-center cursor-pointer select-none">Durum @if($sortColumn === 'status') {!! $sortDirection === 'asc' ? '↑' : '↓' !!} @endif</th>
                        @endif
                        @if(in_array('action', $visibleColumns, true))
                            <th class="p-4 text-right">İşlemler</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse($this->documents as $doc)
                        @php [$statusText, $statusClass] = $statusLabel($doc->status); @endphp
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            @if(in_array('id', $visibleColumns, true))
                                <td class="p-4 text-slate-500 font-mono text-xs">{{ $doc->id }}</td>
                            @endif
                            @if(in_array('issue_date', $visibleColumns, true))
                                <td class="p-4 whitespace-nowrap text-slate-700 text-xs">{{ $doc->issue_date ? $doc->issue_date->format('d.m.Y') : '—' }}</td>
                            @endif
                            @if(in_array('invoice_number', $visibleColumns, true))
                                <td class="p-4 whitespace-nowrap">
                                    @if($doc->invoice_number)
                                        <span class="font-mono font-bold text-slate-900 block">{{ $doc->invoice_number }}</span>
                                    @else
                                        <span class="text-xs px-2 py-0.5 rounded bg-slate-100 text-slate-500 uppercase font-mono font-semibold">TASLAK</span>
                                    @endif
                                    <span class="font-mono text-[10px] text-slate-400 block mt-0.5">{{ $doc->uuid }}</span>
                                </td>
                            @endif
                            @if(in_array('document_type', $visibleColumns, true))
                                <td class="p-4 whitespace-nowrap">
                                    <span class="px-2 py-0.5 text-xs font-semibold rounded font-mono {{ $doc->document_type === 'e_invoice' ? 'bg-indigo-50 text-indigo-700' : 'bg-slate-100 text-slate-700' }}">
                                        {{ $doc->document_type === 'e_invoice' ? 'e-Fatura' : 'e-Arşiv' }}
                                    </span>
                                </td>
                            @endif
                            @if(in_array('buyer', $visibleColumns, true))
                                <td class="p-4">
                                    <div class="font-semibold text-slate-900 text-xs">{{ $doc->buyer_name }}</div>
                                    <div class="font-mono text-[10px] text-slate-400 mt-0.5">{{ $doc->buyer_tax_number ?: 'VKN BELİRTİLMEDİ' }}</div>
                                </td>
                            @endif
                            @if(in_array('total_amount', $visibleColumns, true))
                                <td class="p-4 text-right font-mono font-bold text-slate-900 whitespace-nowrap">{{ $formatMoney($doc->total_amount) }}</td>
                            @endif
                            @if(in_array('status', $visibleColumns, true))
                                <td class="p-4 text-center whitespace-nowrap">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold font-mono {{ $statusClass }}">{{ $statusText }}</span>
                                </td>
                            @endif
                            @if(in_array('action', $visibleColumns, true))
                                <td class="p-4 text-right whitespace-nowrap">
                                    <div class="inline-flex gap-1">
                                        @if($doc->status === 'draft')
                                            <button
                                                wire:click="sendToGib({{ $doc->id }})"
                                                id="btn-send-gib-{{ $doc->id }}"
                                                class="px-2.5 py-1 text-xs font-semibold text-white bg-slate-900 hover:bg-slate-800 rounded-[6px] transition-colors"
                                            >
                                                GİB Gönder
                                            </button>
                                        @elseif($doc->status === 'accepted')
                                            <button
                                                wire:click="openCancelModal({{ $doc->id }})"
                                                id="btn-cancel-doc-{{ $doc->id }}"
                                                class="px-2.5 py-1 text-xs font-semibold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 rounded-[6px] transition-colors"
                                            >
                                                İptal Et
                                            </button>
                                        @endif
                                        <button
                                            wire:click="openEventsModal({{ $doc->id }})"
                                            id="btn-log-doc-{{ $doc->id }}"
                                            class="px-2.5 py-1 text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-[6px] transition-colors"
                                        >
                                            Log
                                        </button>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($visibleColumns) }}" class="p-8 text-center text-slate-400">
                                Filtrelerle eşleşen kayıtlı e-Belge bulunamadı.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Mobil Kart Görünümü --}}
        <div class="md:hidden divide-y divide-slate-100 bg-white">
            @forelse($this->documents as $doc)
                @php [$statusText, $statusClass] = $statusLabel($doc->status); @endphp
                <div class="p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="font-mono text-sm font-semibold text-slate-900">{{ $doc->invoice_number ?: 'TASLAK BELGE' }}</span>
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold font-mono {{ $statusClass }}">{{ $statusText }}</span>
                    </div>
                    <div class="text-xs text-slate-500 font-semibold">{{ $doc->buyer_name }}</div>
                    <div class="flex items-center justify-between text-xs text-slate-500 font-mono">
                        <span>{{ $doc->document_type === 'e_invoice' ? 'e-Fatura' : 'e-Arşiv' }}</span>
                        <span class="font-bold text-slate-900">{{ $formatMoney($doc->total_amount) }}</span>
                    </div>
                    <div class="pt-2 flex justify-end gap-1.5">
                        @if($doc->status === 'draft')
                            <button wire:click="sendToGib({{ $doc->id }})" id="btn-mob-send-{{ $doc->id }}" class="flex-1 py-2 text-xs font-semibold text-white bg-slate-900 rounded-[6px] text-center">GİB Gönder</button>
                        @elseif($doc->status === 'accepted')
                            <button wire:click="openCancelModal({{ $doc->id }})" id="btn-mob-cancel-{{ $doc->id }}" class="flex-1 py-2 text-xs font-semibold text-rose-700 bg-rose-50 border border-rose-200 rounded-[6px] text-center">İptal Et</button>
                        @endif
                        <button wire:click="openEventsModal({{ $doc->id }})" id="btn-mob-log-{{ $doc->id }}" class="py-2 px-3 text-xs font-semibold text-slate-700 bg-slate-100 rounded-[6px] text-center">Log</button>
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-slate-400 text-sm">Filtrelerle eşleşen kayıtlı e-Belge bulunamadı.</div>
            @endforelse
        </div>

        <div class="p-4 border-t border-slate-100">
            {{ $this->documents->links() }}
        </div>
    </section>

    {{-- İptal Gerekçesi Modali --}}
    @if($showCancelModal)
        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4 z-50">
            <div class="bg-white rounded-[10px] border border-slate-200 shadow-xl max-w-md w-full p-6 space-y-4">
                <h3 class="text-base font-semibold text-slate-900">Belgeyi İptal Et</h3>
                <p class="text-xs text-slate-500 font-mono">
                    Bu belgeyi simüle entegratör üzerinden iptal etmek üzeresiniz. Lütfen bir gerekçe girin.
                </p>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">İptal Gerekçesi</label>
                    <textarea wire:model="cancelReason" id="textarea-cancel-reason" placeholder="Yanlış sipariş kesildi / İade alındı..." class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[80px]"></textarea>
                </div>
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button wire:click="cancelDocument" id="btn-submit-cancel-document" class="inline-flex items-center justify-center px-4 py-2 text-sm font-semibold text-white bg-rose-600 hover:bg-rose-700 rounded-[6px] transition-colors min-h-[44px]">Belgeyi İptal Et</button>
                    <button wire:click="$set('showCancelModal', false)" id="btn-close-cancel-modal" class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-[6px] hover:bg-slate-50 transition-colors min-h-[44px]">Vazgeç</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Olay Günlüğü Log Modali --}}
    @if($showEventsModal && $this->viewDoc)
        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4 z-50">
            <div class="bg-white rounded-[10px] border border-slate-200 shadow-xl max-w-lg w-full p-6 space-y-4">
                <h3 class="text-base font-semibold text-slate-900 border-b border-slate-100 pb-2">Belge Durum Logu (#{{ $this->viewDoc->invoice_number ?: 'Taslak' }})</h3>
                <div class="space-y-3 max-h-[300px] overflow-y-auto pr-1">
                    @forelse($this->viewDoc->events as $event)
                        <div class="p-3 bg-slate-50 border border-slate-100 rounded-lg text-xs space-y-1.5">
                            <div class="flex justify-between items-center text-slate-400 font-mono">
                                <span>{{ $event->created_at->format('d.m.Y H:i:s') }}</span>
                                <span class="font-semibold uppercase tracking-wider text-[10px] px-1.5 py-0.5 rounded {{ $event->event_type === 'created' ? 'bg-indigo-100 text-indigo-800' : ($event->status_to === 'accepted' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800') }}">{{ $event->event_type === 'created' ? 'Oluşturuldu' : ($event->status_to === 'accepted' ? 'Kabul Edildi' : 'İptal') }}</span>
                            </div>
                            <p class="text-slate-700 font-medium">{{ $event->message }}</p>
                            @if($event->actor)
                                <div class="text-[10px] text-slate-400 font-mono">İşlem Yapan: {{ $event->actor->name }}</div>
                            @endif
                        </div>
                    @empty
                        <p class="text-xs text-slate-400 text-center py-4">Bu belgeye ait olay günlüğü bulunamadı.</p>
                    @endforelse
                </div>
                <div class="flex justify-end border-t border-slate-100 pt-3">
                    <button type="button" wire:click="$set('showEventsModal', false)" id="btn-close-events-modal" class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-[6px] hover:bg-slate-50 transition-colors min-h-[44px]">Kapat</button>
                </div>
            </div>
        </div>
    @endif
</div>
