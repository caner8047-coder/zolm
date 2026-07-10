@php
    $formatMoney = fn ($value) => '₺' . number_format((float) $value, 2, ',', '.');
    $statusLabel = function(string $status): array {
        return match($status) {
            'posted' => ['Kayıtlı', 'bg-emerald-100 text-emerald-800'],
            'voided' => ['İptal', 'bg-rose-100 text-rose-800'],
            default  => [$status, 'bg-slate-100 text-slate-600'],
        };
    };
    $methodLabel = function(string $m): string {
        return match($m) {
            'cash'        => 'Nakit',
            'bank'        => 'Havale/EFT',
            'check'       => 'Çek',
            'credit_card' => 'Kredi Kartı',
            default       => $m,
        };
    };
@endphp

<div class="w-full space-y-4 lg:space-y-6">

    {{-- Mesaj Paneli --}}
    @if($message !== '')
        <div wire:key="msg-{{ rand() }}" class="rounded-[8px] border p-4 text-sm {{ $messageType === 'error' ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800' }}">
            {{ $message }}
        </div>
    @endif

    {{-- Header --}}
    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div>
                <div class="inline-flex items-center rounded-[6px] border border-slate-200 bg-slate-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">
                    Ön Muhasebe
                </div>
                <h1 class="mt-3 text-xl font-semibold tracking-tight text-slate-950 lg:text-2xl">Tahsilat & Ödeme Merkezi</h1>
                <p class="mt-2 text-sm text-slate-500">
                    Müşteri tahsilatlarını ve tedarikçi ödemelerini kaydedin, açık faturalara dağıtın.
                </p>
            </div>
            <div class="flex shrink-0 gap-2">
                @if($tab === 'collections')
                    <button wire:click="openCollectionForm" id="btn-yeni-tahsilat"
                        class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 transition-colors min-h-[44px]">
                        + Tahsilat Kaydet
                    </button>
                @else
                    <button wire:click="openPaymentForm" id="btn-yeni-odeme"
                        class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 transition-colors min-h-[44px]">
                        + Ödeme Kaydet
                    </button>
                @endif
            </div>
        </div>

        {{-- KPI Kartları --}}
        <div class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <p class="text-[10px] uppercase tracking-[0.2em] text-slate-500">Toplam Tahsilat</p>
                <p class="mt-2 text-xl font-bold text-emerald-700">{{ $formatMoney($this->kpis['total_collected']) }}</p>
            </div>
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <p class="text-[10px] uppercase tracking-[0.2em] text-slate-500">Toplam Ödeme</p>
                <p class="mt-2 text-xl font-bold text-rose-700">{{ $formatMoney($this->kpis['total_paid']) }}</p>
            </div>
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <p class="text-[10px] uppercase tracking-[0.2em] text-slate-500">Açık Alacak</p>
                <p class="mt-2 text-xl font-bold text-amber-700">{{ $formatMoney($this->kpis['open_receivables']) }}</p>
            </div>
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <p class="text-[10px] uppercase tracking-[0.2em] text-slate-500">Açık Borç</p>
                <p class="mt-2 text-xl font-bold text-slate-700">{{ $formatMoney($this->kpis['open_payables']) }}</p>
            </div>
        </div>
    </section>

    {{-- Ana Bölüm: Sekmeler + Tablo --}}
    <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm">

        {{-- Sekme Navigasyonu --}}
        <div class="flex border-b border-slate-200 px-4 lg:px-6 pt-4">
            <button wire:click="switchTab('collections')" id="tab-tahsilatlar"
                class="mr-6 pb-3 text-sm font-medium border-b-2 transition-colors
                    {{ $tab === 'collections' ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                Tahsilatlar
            </button>
            <button wire:click="switchTab('payments')" id="tab-odemeler"
                class="pb-3 text-sm font-medium border-b-2 transition-colors
                    {{ $tab === 'payments' ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                Ödemeler
            </button>
        </div>

        {{-- Filtre Çubuğu --}}
        <div class="px-4 lg:px-6 py-3 border-b border-slate-100 flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1 min-w-0">
                <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text" wire:model.live.debounce.300ms="search" id="inp-search"
                    placeholder="{{ $tab === 'collections' ? 'Cari, açıklama veya referans...' : 'Cari, açıklama veya referans...' }}"
                    class="w-full rounded-[6px] border border-slate-200 bg-white py-2 pl-9 pr-3 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[40px]" />
            </div>
            <select wire:model.live="filterStatus" id="sel-status"
                class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm focus:border-slate-500 focus:outline-none min-h-[40px]">
                <option value="">Tüm Durumlar</option>
                <option value="posted">Kayıtlı</option>
                <option value="voided">İptal</option>
            </select>
            <input type="date" wire:model.live="filterDateFrom" id="inp-date-from"
                class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm focus:border-slate-500 focus:outline-none min-h-[40px]" />
            <input type="date" wire:model.live="filterDateTo" id="inp-date-to"
                class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm focus:border-slate-500 focus:outline-none min-h-[40px]" />
        </div>

        {{-- TAHSİLATLAR --}}
        @if($tab === 'collections')
            {{-- Desktop Tablo --}}
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600" style="table-layout:fixed">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/50">
                            <th class="px-4 lg:px-6 py-3 text-[11px] uppercase tracking-wider text-slate-500 font-semibold w-[130px]">Tarih</th>
                            <th class="px-4 py-3 text-[11px] uppercase tracking-wider text-slate-500 font-semibold">Cari</th>
                            <th class="px-4 py-3 text-[11px] uppercase tracking-wider text-slate-500 font-semibold w-[120px]">Hesap</th>
                            <th class="px-4 py-3 text-[11px] uppercase tracking-wider text-slate-500 font-semibold w-[110px]">Yöntem</th>
                            <th class="px-4 py-3 text-[11px] uppercase tracking-wider text-slate-500 font-semibold w-[120px]">Referans</th>
                            <th class="px-4 py-3 text-[11px] uppercase tracking-wider text-slate-500 font-semibold text-right w-[120px]">Tutar</th>
                            <th class="px-4 py-3 text-[11px] uppercase tracking-wider text-slate-500 font-semibold w-[90px]">Durum</th>
                            <th class="px-4 lg:px-6 py-3 text-[11px] uppercase tracking-wider text-slate-500 font-semibold w-[160px]">İşlem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($this->collections as $col)
                            @php [$label, $badge] = $statusLabel($col->status); @endphp
                            <tr class="hover:bg-slate-50/60 transition-colors">
                                <td class="px-4 lg:px-6 py-3 text-sm text-slate-700 whitespace-nowrap">
                                    {{ $col->collection_date->format('d.m.Y') }}
                                </td>
                                <td class="px-4 py-3 min-w-0">
                                    <p class="font-medium text-slate-900 truncate">{{ $col->party->display_name ?? '—' }}</p>
                                    @if($col->description)
                                        <p class="text-xs text-slate-400 truncate">{{ $col->description }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-xs text-slate-500 truncate">
                                    {{ $col->account?->name ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-xs text-slate-600">{{ $methodLabel($col->payment_method) }}</td>
                                <td class="px-4 py-3 text-xs text-slate-500 font-mono truncate">{{ $col->reference_number ?? '—' }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-slate-900 whitespace-nowrap">
                                    {{ $formatMoney($col->amount) }}
                                </td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-0.5 text-xs font-semibold rounded {{ $badge }}">{{ $label }}</span>
                                </td>
                                <td class="px-4 lg:px-6 py-3">
                                    <div class="flex items-center gap-2">
                                        @if($col->status === 'posted')
                                            <button wire:click="openAllocateForm('collection', {{ $col->id }})" id="btn-alloc-col-{{ $col->id }}"
                                                class="text-xs text-indigo-700 hover:text-indigo-900 font-medium whitespace-nowrap">
                                                Dağıt
                                            </button>
                                            <button wire:click="openVoidConfirm('collection', {{ $col->id }})" id="btn-void-col-{{ $col->id }}"
                                                class="text-xs text-rose-600 hover:text-rose-800 font-medium whitespace-nowrap">
                                                İptal
                                            </button>
                                        @else
                                            <span class="text-xs text-slate-400">—</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-10 text-center text-sm text-slate-400">
                                    Kayıtlı tahsilat bulunamadı.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Mobil Kart Görünümü --}}
            <div class="md:hidden divide-y divide-slate-100">
                @forelse($this->collections as $col)
                    @php [$label, $badge] = $statusLabel($col->status); @endphp
                    <div class="p-4 space-y-2">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="font-medium text-slate-900 truncate">{{ $col->party->display_name ?? '—' }}</p>
                                <p class="text-xs text-slate-400">{{ $col->collection_date->format('d.m.Y') }} · {{ $methodLabel($col->payment_method) }}</p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="font-bold text-slate-900">{{ $formatMoney($col->amount) }}</p>
                                <span class="px-2 py-0.5 text-xs font-semibold rounded {{ $badge }}">{{ $label }}</span>
                            </div>
                        </div>
                        @if($col->status === 'posted')
                            <div class="flex gap-3 pt-1">
                                <button wire:click="openAllocateForm('collection', {{ $col->id }})"
                                    class="text-xs text-indigo-700 font-medium">Dağıt</button>
                                <button wire:click="openVoidConfirm('collection', {{ $col->id }})"
                                    class="text-xs text-rose-600 font-medium">İptal</button>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="p-8 text-center text-sm text-slate-400">Kayıtlı tahsilat bulunamadı.</div>
                @endforelse
            </div>

            <div class="px-4 lg:px-6 py-4 border-t border-slate-100">
                {{ $this->collections->links() }}
            </div>
        @endif

        {{-- ÖDEMELER --}}
        @if($tab === 'payments')
            {{-- Desktop Tablo --}}
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600" style="table-layout:fixed">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/50">
                            <th class="px-4 lg:px-6 py-3 text-[11px] uppercase tracking-wider text-slate-500 font-semibold w-[130px]">Tarih</th>
                            <th class="px-4 py-3 text-[11px] uppercase tracking-wider text-slate-500 font-semibold">Cari (Tedarikçi)</th>
                            <th class="px-4 py-3 text-[11px] uppercase tracking-wider text-slate-500 font-semibold w-[120px]">Hesap</th>
                            <th class="px-4 py-3 text-[11px] uppercase tracking-wider text-slate-500 font-semibold w-[110px]">Yöntem</th>
                            <th class="px-4 py-3 text-[11px] uppercase tracking-wider text-slate-500 font-semibold w-[120px]">Referans</th>
                            <th class="px-4 py-3 text-[11px] uppercase tracking-wider text-slate-500 font-semibold text-right w-[120px]">Tutar</th>
                            <th class="px-4 py-3 text-[11px] uppercase tracking-wider text-slate-500 font-semibold w-[90px]">Durum</th>
                            <th class="px-4 lg:px-6 py-3 text-[11px] uppercase tracking-wider text-slate-500 font-semibold w-[160px]">İşlem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($this->payments as $pay)
                            @php [$label, $badge] = $statusLabel($pay->status); @endphp
                            <tr class="hover:bg-slate-50/60 transition-colors">
                                <td class="px-4 lg:px-6 py-3 text-sm text-slate-700 whitespace-nowrap">
                                    {{ $pay->payment_date->format('d.m.Y') }}
                                </td>
                                <td class="px-4 py-3 min-w-0">
                                    <p class="font-medium text-slate-900 truncate">{{ $pay->party->display_name ?? '—' }}</p>
                                    @if($pay->description)
                                        <p class="text-xs text-slate-400 truncate">{{ $pay->description }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-xs text-slate-500 truncate">
                                    {{ $pay->account?->name ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-xs text-slate-600">{{ $methodLabel($pay->payment_method) }}</td>
                                <td class="px-4 py-3 text-xs text-slate-500 font-mono truncate">{{ $pay->reference_number ?? '—' }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-slate-900 whitespace-nowrap">
                                    {{ $formatMoney($pay->amount) }}
                                </td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-0.5 text-xs font-semibold rounded {{ $badge }}">{{ $label }}</span>
                                </td>
                                <td class="px-4 lg:px-6 py-3">
                                    <div class="flex items-center gap-2">
                                        @if($pay->status === 'posted')
                                            <button wire:click="openAllocateForm('payment', {{ $pay->id }})" id="btn-alloc-pay-{{ $pay->id }}"
                                                class="text-xs text-indigo-700 hover:text-indigo-900 font-medium whitespace-nowrap">
                                                Dağıt
                                            </button>
                                            <button wire:click="openVoidConfirm('payment', {{ $pay->id }})" id="btn-void-pay-{{ $pay->id }}"
                                                class="text-xs text-rose-600 hover:text-rose-800 font-medium whitespace-nowrap">
                                                İptal
                                            </button>
                                        @else
                                            <span class="text-xs text-slate-400">—</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-10 text-center text-sm text-slate-400">
                                    Kayıtlı ödeme bulunamadı.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Mobil Kart Görünümü --}}
            <div class="md:hidden divide-y divide-slate-100">
                @forelse($this->payments as $pay)
                    @php [$label, $badge] = $statusLabel($pay->status); @endphp
                    <div class="p-4 space-y-2">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="font-medium text-slate-900 truncate">{{ $pay->party->display_name ?? '—' }}</p>
                                <p class="text-xs text-slate-400">{{ $pay->payment_date->format('d.m.Y') }} · {{ $methodLabel($pay->payment_method) }}</p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="font-bold text-slate-900">{{ $formatMoney($pay->amount) }}</p>
                                <span class="px-2 py-0.5 text-xs font-semibold rounded {{ $badge }}">{{ $label }}</span>
                            </div>
                        </div>
                        @if($pay->status === 'posted')
                            <div class="flex gap-3 pt-1">
                                <button wire:click="openAllocateForm('payment', {{ $pay->id }})"
                                    class="text-xs text-indigo-700 font-medium">Dağıt</button>
                                <button wire:click="openVoidConfirm('payment', {{ $pay->id }})"
                                    class="text-xs text-rose-600 font-medium">İptal</button>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="p-8 text-center text-sm text-slate-400">Kayıtlı ödeme bulunamadı.</div>
                @endforelse
            </div>

            <div class="px-4 lg:px-6 py-4 border-t border-slate-100">
                {{ $this->payments->links() }}
            </div>
        @endif

    </section>

    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- MODAL: Tahsilat Oluştur --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    @if($showCollectionForm)
        <div class="fixed inset-0 z-50 flex items-start justify-center bg-slate-900/50 p-4 pt-16">
            <div class="w-full max-w-lg rounded-[10px] bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                    <h3 class="text-base font-semibold text-slate-900">Tahsilat Kaydet</h3>
                    <button wire:click="$set('showCollectionForm', false)" class="text-slate-400 hover:text-slate-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="p-6 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {{-- Cari --}}
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Müşteri (Cari)</label>
                            <select wire:model="colPartyId" id="sel-col-party"
                                class="block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                                <option value="">Müşteri Seçin...</option>
                                @foreach($this->customerParties as $p)
                                    <option value="{{ $p->id }}">{{ $p->display_name }}</option>
                                @endforeach
                            </select>
                            @error('colPartyId') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        {{-- Hesap --}}
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Kasa / Banka Hesabı</label>
                            <select wire:model="colAccountId" id="sel-col-account"
                                class="block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                                <option value="">Hesap Seçin...</option>
                                @foreach($this->cashBankAccounts as $acc)
                                    <option value="{{ $acc->id }}">{{ $acc->code }} – {{ $acc->name }}</option>
                                @endforeach
                            </select>
                            @error('colAccountId') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        {{-- Tutar --}}
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Tutar (₺)</label>
                            <input type="number" step="0.01" min="0.01" wire:model="colAmount" id="inp-col-amount"
                                class="block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
                            @error('colAmount') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        {{-- Tarih --}}
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Tahsilat Tarihi</label>
                            <input type="date" wire:model="colDate" id="inp-col-date"
                                class="block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
                            @error('colDate') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        {{-- Yöntem --}}
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Ödeme Yöntemi</label>
                            <select wire:model="colMethod" id="sel-col-method"
                                class="block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                                <option value="bank">Havale/EFT</option>
                                <option value="cash">Nakit</option>
                                <option value="check">Çek</option>
                                <option value="credit_card">Kredi Kartı</option>
                            </select>
                        </div>
                        {{-- Referans --}}
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Referans No</label>
                            <input type="text" wire:model="colReference" id="inp-col-reference" placeholder="Makbuz / Havale No"
                                class="block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
                        </div>
                        {{-- Açıklama --}}
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Açıklama</label>
                            <input type="text" wire:model="colDescription" id="inp-col-description" placeholder="İsteğe bağlı not..."
                                class="block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-3 border-t border-slate-200 px-6 py-4">
                    <button wire:click="$set('showCollectionForm', false)"
                        class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-[6px] hover:bg-slate-50 transition-colors min-h-[44px]">
                        Vazgeç
                    </button>
                    <button wire:click="saveCollection" id="btn-save-collection"
                        wire:loading.attr="disabled"
                        class="px-4 py-2 text-sm font-medium text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 transition-colors min-h-[44px] disabled:opacity-60">
                        <span wire:loading.remove wire:target="saveCollection">Tahsilatı Kaydet</span>
                        <span wire:loading wire:target="saveCollection">Kaydediliyor...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- MODAL: Ödeme Oluştur --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    @if($showPaymentForm)
        <div class="fixed inset-0 z-50 flex items-start justify-center bg-slate-900/50 p-4 pt-16">
            <div class="w-full max-w-lg rounded-[10px] bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                    <h3 class="text-base font-semibold text-slate-900">Ödeme Kaydet</h3>
                    <button wire:click="$set('showPaymentForm', false)" class="text-slate-400 hover:text-slate-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="p-6 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {{-- Cari --}}
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Tedarikçi (Cari)</label>
                            <select wire:model="payPartyId" id="sel-pay-party"
                                class="block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                                <option value="">Tedarikçi Seçin...</option>
                                @foreach($this->supplierParties as $p)
                                    <option value="{{ $p->id }}">{{ $p->display_name }}</option>
                                @endforeach
                            </select>
                            @error('payPartyId') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        {{-- Hesap --}}
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Kasa / Banka Hesabı</label>
                            <select wire:model="payAccountId" id="sel-pay-account"
                                class="block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                                <option value="">Hesap Seçin...</option>
                                @foreach($this->cashBankAccounts as $acc)
                                    <option value="{{ $acc->id }}">{{ $acc->code }} – {{ $acc->name }}</option>
                                @endforeach
                            </select>
                            @error('payAccountId') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        {{-- Tutar --}}
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Tutar (₺)</label>
                            <input type="number" step="0.01" min="0.01" wire:model="payAmount" id="inp-pay-amount"
                                class="block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
                            @error('payAmount') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        {{-- Tarih --}}
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Ödeme Tarihi</label>
                            <input type="date" wire:model="payDate" id="inp-pay-date"
                                class="block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
                            @error('payDate') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        {{-- Yöntem --}}
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Ödeme Yöntemi</label>
                            <select wire:model="payMethod" id="sel-pay-method"
                                class="block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                                <option value="bank">Havale/EFT</option>
                                <option value="cash">Nakit</option>
                                <option value="check">Çek</option>
                                <option value="credit_card">Kredi Kartı</option>
                            </select>
                        </div>
                        {{-- Referans --}}
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Referans No</label>
                            <input type="text" wire:model="payReference" id="inp-pay-reference" placeholder="Dekont / Havale No"
                                class="block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
                        </div>
                        {{-- Açıklama --}}
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Açıklama</label>
                            <input type="text" wire:model="payDescription" id="inp-pay-description" placeholder="İsteğe bağlı not..."
                                class="block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-3 border-t border-slate-200 px-6 py-4">
                    <button wire:click="$set('showPaymentForm', false)"
                        class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-[6px] hover:bg-slate-50 transition-colors min-h-[44px]">
                        Vazgeç
                    </button>
                    <button wire:click="savePayment" id="btn-save-payment"
                        wire:loading.attr="disabled"
                        class="px-4 py-2 text-sm font-medium text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 transition-colors min-h-[44px] disabled:opacity-60">
                        <span wire:loading.remove wire:target="savePayment">Ödemeyi Kaydet</span>
                        <span wire:loading wire:target="savePayment">Kaydediliyor...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- MODAL: Dağıtım (Allocation) --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    @if($showAllocateForm)
        <div class="fixed inset-0 z-50 flex items-start justify-center bg-slate-900/50 p-4 pt-12">
            <div class="w-full max-w-xl rounded-[10px] bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                    <div>
                        <h3 class="text-base font-semibold text-slate-900">
                            {{ $allocateType === 'collection' ? 'Tahsilatı Faturalara Dağıt' : 'Ödemeyi Faturalara Dağıt' }}
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Kalan Bakiye: <strong class="text-emerald-700">₺{{ number_format($allocateBalance, 2, ',', '.') }}</strong>
                        </p>
                    </div>
                    <button wire:click="$set('showAllocateForm', false)" class="text-slate-400 hover:text-slate-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="p-6">
                    @if(empty($allocateLines))
                        <p class="text-sm text-slate-500 text-center py-6">
                            Bu cari için açık {{ $allocateType === 'collection' ? 'alacak faturası' : 'borç faturası' }} bulunamadı.
                        </p>
                    @else
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-slate-100 text-[11px] uppercase tracking-wider text-slate-500">
                                    <th class="py-2 text-left">Fatura Ref.</th>
                                    <th class="py-2 text-right">Kalan</th>
                                    <th class="py-2 text-right w-32">Dağıt (₺)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($allocateLines as $idx => $line)
                                    <tr>
                                        <td class="py-2 font-mono text-xs text-slate-700">{{ $line['ref'] }}</td>
                                        <td class="py-2 text-right text-slate-600">₺{{ number_format($line['remaining'], 2, ',', '.') }}</td>
                                        <td class="py-2 text-right">
                                            <input type="number" step="0.01" min="0"
                                                wire:model="allocateLines.{{ $idx }}.amount"
                                                id="inp-alloc-{{ $idx }}"
                                                class="w-28 rounded-[6px] border border-slate-200 bg-white px-2 py-1.5 text-sm text-right focus:border-slate-500 focus:outline-none" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>

                <div class="flex justify-end gap-3 border-t border-slate-200 px-6 py-4">
                    <button wire:click="$set('showAllocateForm', false)"
                        class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-[6px] hover:bg-slate-50 transition-colors min-h-[44px]">
                        Vazgeç
                    </button>
                    @if(!empty($allocateLines))
                        <button wire:click="saveAllocation" id="btn-save-alloc"
                            wire:loading.attr="disabled"
                            class="px-4 py-2 text-sm font-medium text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 transition-colors min-h-[44px] disabled:opacity-60">
                            <span wire:loading.remove wire:target="saveAllocation">Dağıtımı Onayla</span>
                            <span wire:loading wire:target="saveAllocation">Kaydediliyor...</span>
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- MODAL: İptal Onayı --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    @if($showVoidConfirm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div class="w-full max-w-sm rounded-[10px] bg-white shadow-xl">
                <div class="p-6 space-y-4">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-rose-100">
                            <svg class="h-5 w-5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                            </svg>
                        </span>
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900">
                                {{ $voidType === 'collection' ? 'Tahsilatı İptal Et' : 'Ödemeyi İptal Et' }}
                            </h3>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Bu işlem tüm dağıtımları geri alacak ve GL kaydını iptal edecektir.
                            </p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">İptal Nedeni (Opsiyonel)</label>
                        <input type="text" wire:model="voidReason" id="inp-void-reason" placeholder="Açıklama..."
                            class="block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm focus:border-slate-500 focus:outline-none" />
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button wire:click="$set('showVoidConfirm', false)"
                            class="flex-1 px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-[6px] hover:bg-slate-50 transition-colors min-h-[44px]">
                            Vazgeç
                        </button>
                        <button wire:click="confirmVoid" id="btn-confirm-void"
                            wire:loading.attr="disabled"
                            class="flex-1 px-4 py-2 text-sm font-medium text-white bg-rose-600 rounded-[6px] hover:bg-rose-700 transition-colors min-h-[44px] disabled:opacity-60">
                            <span wire:loading.remove wire:target="confirmVoid">İptal Et</span>
                            <span wire:loading wire:target="confirmVoid">İşleniyor...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

</div>
