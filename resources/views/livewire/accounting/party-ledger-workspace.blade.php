@php
    $formatMoney = fn ($value) => '₺' . number_format((float) $value, 2, ',', '.');
    $formatSignedMoney = fn ($value) => ((float) $value >= 0 ? '+' : '-') . '₺' . number_format(abs((float) $value), 2, ',', '.');
    $typeLabel = fn (string $type) => match ($type) {
        'receivable' => 'Alacak',
        'collection' => 'Tahsilat',
        'payable' => 'Borç',
        'payment' => 'Ödeme',
        default => $type,
    };
    $typeTone = fn (string $type) => match ($type) {
        'receivable' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'collection' => 'bg-blue-50 text-blue-700 border-blue-200',
        'payable' => 'bg-amber-50 text-amber-700 border-amber-200',
        'payment' => 'bg-rose-50 text-rose-700 border-rose-200',
        default => 'bg-slate-50 text-slate-700 border-slate-200',
    };
    $sortIcon = fn (string $field) => $sortField === $field ? ($sortDirection === 'asc' ? ' ▲' : ' ▼') : ' ⇅';
@endphp

<div class="w-full space-y-4 lg:space-y-6">
    {{-- Mesaj --}}
    @if($message !== '')
        <div class="rounded-[8px] border p-4 text-sm {{ $messageType === 'error' ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800' }}">
            {{ $message }}
        </div>
    @endif

    {{-- KPI Kartları --}}
    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
        <div class="inline-flex items-center rounded-[6px] border border-slate-200 bg-slate-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">
            Cari Açık Hesap
        </div>
        <h1 class="mt-3 text-xl font-semibold tracking-tight text-slate-950 lg:text-2xl">Cari Açık Hesap</h1>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">
            Party bazlı cari hareketleri görüntüleyin, manuel alacak/borç/tahsilat/ödeme girin ve kayıt iptal edin.
        </p>

        <div class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-5">
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <p class="text-[10px] uppercase tracking-[0.2em] text-slate-500">Toplam Borç</p>
                <p class="mt-2 text-xl font-bold text-rose-700">{{ $formatMoney($this->kpi['debit']) }}</p>
            </div>
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <p class="text-[10px] uppercase tracking-[0.2em] text-slate-500">Toplam Alacak</p>
                <p class="mt-2 text-xl font-bold text-emerald-700">{{ $formatMoney($this->kpi['credit']) }}</p>
            </div>
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <p class="text-[10px] uppercase tracking-[0.2em] text-slate-500">Net Bakiye</p>
                <p class="mt-2 text-xl font-bold {{ $this->kpi['balance'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                    {{ $formatSignedMoney($this->kpi['balance']) }}
                </p>
            </div>
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <p class="text-[10px] uppercase tracking-[0.2em] text-slate-500">Açık Party</p>
                <p class="mt-2 text-xl font-bold text-slate-900">{{ $this->kpi['active_party_count'] }}</p>
            </div>
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <p class="text-[10px] uppercase tracking-[0.2em] text-slate-500">İptal Kayıt</p>
                <p class="mt-2 text-xl font-bold text-slate-500">{{ $this->kpi['voided_count'] }}</p>
            </div>
        </div>
    </section>

    {{-- Filtre + Yeni Kayıt --}}
    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Party ara..."
                       class="w-full sm:w-64 rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 placeholder-slate-400 focus:border-slate-400 focus:outline-none">

                <select wire:model.live="documentType" class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
                    <option value="">Tüm Tipler</option>
                    <option value="receivable">Alacak</option>
                    <option value="collection">Tahsilat</option>
                    <option value="payable">Borç</option>
                    <option value="payment">Ödeme</option>
                </select>

                <select wire:model.live="status" class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
                    <option value="posted">Aktif</option>
                    <option value="voided">İptal</option>
                    <option value="all">Tümü</option>
                </select>

                <input type="date" wire:model.live="dateFrom" class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700" placeholder="Başlangıç">
                <input type="date" wire:model.live="dateTo" class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700" placeholder="Bitiş">
            </div>

            <button wire:click="$set('showForm', true)" class="w-full sm:w-auto px-4 py-2 text-sm font-medium bg-slate-900 text-white rounded-[6px] hover:bg-slate-800 transition-colors">
                + Yeni Kayıt
            </button>
        </div>

        {{-- Giriş Formu --}}
        @if($showForm)
            <div class="mt-4 rounded-[8px] border border-slate-200 bg-slate-50/60 p-4">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-semibold text-slate-900">Yeni Cari Hareket</h3>
                    <button wire:click="resetForm" class="text-slate-400 hover:text-slate-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form wire:submit="submitEntry">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Party *</label>
                            <select wire:model="formPartyId" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm" required>
                                <option value="">Seçin...</option>
                                @foreach($this->parties as $party)
                                    <option value="{{ $party['id'] }}">{{ $party['label'] }}</option>
                                @endforeach
                            </select>
                            @error('formPartyId') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Hareket Tipi *</label>
                            <select wire:model="formEntryType" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm" required>
                                <option value="receivable">Alacak (Debit)</option>
                                <option value="collection">Tahsilat (Credit)</option>
                                <option value="payable">Borç (Credit)</option>
                                <option value="payment">Ödeme (Debit)</option>
                            </select>
                            @error('formEntryType') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Tutar *</label>
                            <input type="number" wire:model="formAmount" step="0.01" min="0.01" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm" required>
                            @error('formAmount') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Belge Tarihi *</label>
                            <input type="date" wire:model="formDocumentDate" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm" required>
                            @error('formDocumentDate') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Vade Tarihi</label>
                            <input type="date" wire:model="formDueDate" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm">
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Belge No</label>
                            <input type="text" wire:model="formDocumentNumber" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm">
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Açıklama</label>
                            <input type="text" wire:model="formDescription" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm">
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Para Birimi</label>
                            <input type="text" wire:model="formCurrencyCode" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm" value="TRY">
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Kur</label>
                            <input type="number" wire:model="formExchangeRate" step="0.000001" min="0.01" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm" value="1">
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Legal Entity</label>
                            <select wire:model="formLegalEntityId" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm">
                                <option value="">Yok</option>
                                @foreach($this->legalEntities as $entity)
                                    <option value="{{ $entity['id'] }}">{{ $entity['label'] }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">CRM Contact</label>
                            <select wire:model="formCrmContactId" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm">
                                <option value="">Yok</option>
                                @foreach($this->crmContacts as $contact)
                                    <option value="{{ $contact['id'] }}">{{ $contact['label'] }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Source Key</label>
                            <input type="text" wire:model="formSourceKey" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="Opsiyonel">
                        </div>
                    </div>

                    <div class="mt-4 flex justify-end gap-3">
                        <button type="button" wire:click="resetForm" class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-[6px] hover:bg-slate-50 transition-colors">
                            İptal
                        </button>
                        <button type="submit" wire:loading.attr="disabled" class="px-4 py-2 text-sm font-medium bg-slate-900 text-white rounded-[6px] hover:bg-slate-800 disabled:opacity-60 transition-colors">
                            <span wire:loading.remove wire:target="submitEntry">Kaydet</span>
                            <span wire:loading wire:target="submitEntry">Kaydediliyor...</span>
                        </button>
                    </div>
                </form>
            </div>
        @endif
    </section>

    {{-- Tablo --}}
    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
        {{-- Tablo Üst Araç Çubuğu --}}
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-slate-900">İşlem Listesi</h2>
            <div x-data="{ open: false }" class="relative">
                <button type="button" @click="open = !open"
                        class="inline-flex min-h-[38px] items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50">
                    Kolonlar · {{ count($visibleColumns) }}
                </button>
                <div x-show="open" x-cloak x-transition @click.outside="open = false"
                     class="absolute right-0 top-full z-30 mt-2 w-48 rounded-[8px] border border-slate-200 bg-white p-2 shadow-xl">
                    @foreach(\App\Livewire\Accounting\PartyLedgerWorkspace::$allColumnDefs as $key => $label)
                        <label class="flex cursor-pointer items-center gap-3 rounded-[6px] px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">
                            <input type="checkbox"
                                   wire:click="toggleColumn('{{ $key }}')"
                                   @checked(in_array($key, $visibleColumns, true))
                                   class="h-4 w-4 rounded border-slate-300 text-slate-950 focus:ring-slate-950">
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Mobil Kart Görünümü --}}
        <div class="md:hidden space-y-3">
            @forelse($this->entries as $entry)
                <div class="rounded-[8px] border border-slate-200 bg-white p-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs text-slate-500">{{ $entry->document_date->format('d.m.Y') }}</span>
                        <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-semibold {{ $typeTone($entry->document_type) }}">
                            {{ $typeLabel($entry->document_type) }}
                        </span>
                    </div>
                    <p class="text-sm font-medium text-slate-900">{{ $entry->party->display_name ?? '-' }}</p>
                    @if($entry->document_number)
                        <p class="mt-1 text-xs text-slate-500">{{ $entry->document_number }}</p>
                    @endif
                    @if($entry->description)
                        <p class="mt-1 text-xs text-slate-500">{{ $entry->description }}</p>
                    @endif
                    <div class="mt-3 flex items-center justify-between">
                        <div class="text-sm">
                            @if($entry->debit_amount > 0)
                                <span class="font-semibold text-rose-700">₺{{ number_format($entry->debit_amount, 2, ',', '.') }}</span>
                            @else
                                <span class="font-semibold text-emerald-700">₺{{ number_format($entry->credit_amount, 2, ',', '.') }}</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs {{ $entry->status === 'voided' ? 'text-slate-400 line-through' : 'text-slate-600' }}">
                                {{ $entry->status === 'voided' ? 'İptal' : 'Aktif' }}
                            </span>
                            @if($entry->status === 'posted')
                                <button wire:click="confirmVoid({{ $entry->id }})" class="text-xs text-rose-600 hover:text-rose-800">İptal Et</button>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center py-8 text-sm text-slate-500">Kayıt bulunamadı.</div>
            @endforelse
        </div>

        {{-- Masaüstü Tablo --}}
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-sm" style="table-layout: fixed;">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-[0.15em] text-slate-500">
                        @if(in_array('tarih', $visibleColumns, true))
                            <th class="py-3 px-3 w-[100px] cursor-pointer hover:bg-slate-50 select-none" wire:click="sortTable('tarih')">
                                Tarih{{ $sortIcon('tarih') }}
                            </th>
                        @endif
                        @if(in_array('party', $visibleColumns, true))
                            <th class="py-3 px-3 w-[160px] cursor-pointer hover:bg-slate-50 select-none" wire:click="sortTable('party')">
                                Party{{ $sortIcon('party') }}
                            </th>
                        @endif
                        @if(in_array('tip', $visibleColumns, true))
                            <th class="py-3 px-3 w-[100px] cursor-pointer hover:bg-slate-50 select-none" wire:click="sortTable('tip')">
                                Tip{{ $sortIcon('tip') }}
                            </th>
                        @endif
                        @if(in_array('belge_no', $visibleColumns, true))
                            <th class="py-3 px-3 w-[100px] cursor-pointer hover:bg-slate-50 select-none" wire:click="sortTable('belge_no')">
                                Belge No{{ $sortIcon('belge_no') }}
                            </th>
                        @endif
                        @if(in_array('aciklama', $visibleColumns, true))
                            <th class="py-3 px-3">Açıklama</th>
                        @endif
                        @if(in_array('borc', $visibleColumns, true))
                            <th class="py-3 px-3 w-[110px] text-right cursor-pointer hover:bg-slate-50 select-none" wire:click="sortTable('borc')">
                                Borç{{ $sortIcon('borc') }}
                            </th>
                        @endif
                        @if(in_array('alacak', $visibleColumns, true))
                            <th class="py-3 px-3 w-[110px] text-right cursor-pointer hover:bg-slate-50 select-none" wire:click="sortTable('alacak')">
                                Alacak{{ $sortIcon('alacak') }}
                            </th>
                        @endif
                        @if(in_array('bakiye_etkisi', $visibleColumns, true))
                            <th class="py-3 px-3 w-[120px] text-right select-none">Bakiye Etkisi</th>
                        @endif
                        @if(in_array('durum', $visibleColumns, true))
                            <th class="py-3 px-3 w-[90px] text-right select-none">Durum</th>
                        @endif
                        @if(in_array('aksiyon', $visibleColumns, true))
                            <th class="py-3 px-3 w-[80px] text-center select-none">İşlem</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($this->entries as $entry)
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            @if(in_array('tarih', $visibleColumns, true))
                                <td class="py-3 px-3 text-slate-600">{{ $entry->document_date->format('d.m.Y') }}</td>
                            @endif
                            @if(in_array('party', $visibleColumns, true))
                                <td class="py-3 px-3 font-medium text-slate-900 truncate">{{ $entry->party->display_name ?? '-' }}</td>
                            @endif
                            @if(in_array('tip', $visibleColumns, true))
                                <td class="py-3 px-3">
                                    <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-semibold {{ $typeTone($entry->document_type) }}">
                                        {{ $typeLabel($entry->document_type) }}
                                    </span>
                                </td>
                            @endif
                            @if(in_array('belge_no', $visibleColumns, true))
                                <td class="py-3 px-3 text-slate-600 truncate">{{ $entry->document_number ?? '-' }}</td>
                            @endif
                            @if(in_array('aciklama', $visibleColumns, true))
                                <td class="py-3 px-3 text-slate-600 truncate">{{ $entry->description ?? '-' }}</td>
                            @endif
                            @if(in_array('borc', $visibleColumns, true))
                                <td class="py-3 px-3 text-right font-semibold {{ $entry->debit_amount > 0 ? 'text-rose-700' : 'text-slate-400' }}">
                                    {{ $entry->debit_amount > 0 ? '₺' . number_format($entry->debit_amount, 2, ',', '.') : '-' }}
                                </td>
                            @endif
                            @if(in_array('alacak', $visibleColumns, true))
                                <td class="py-3 px-3 text-right font-semibold {{ $entry->credit_amount > 0 ? 'text-emerald-700' : 'text-slate-400' }}">
                                    {{ $entry->credit_amount > 0 ? '₺' . number_format($entry->credit_amount, 2, ',', '.') : '-' }}
                                </td>
                            @endif
                            @if(in_array('bakiye_etkisi', $visibleColumns, true))
                                <td class="py-3 px-3 text-right font-semibold">
                                    @if($entry->debit_amount > 0)
                                        <span class="text-rose-700 font-semibold">+₺{{ number_format($entry->debit_amount, 2, ',', '.') }}</span>
                                    @elseif($entry->credit_amount > 0)
                                        <span class="text-emerald-700 font-semibold">-₺{{ number_format($entry->credit_amount, 2, ',', '.') }}</span>
                                    @else
                                        <span class="text-slate-400 font-semibold">-</span>
                                    @endif
                                </td>
                            @endif
                            @if(in_array('durum', $visibleColumns, true))
                                <td class="py-3 px-3 text-right">
                                    <span class="text-xs {{ $entry->status === 'voided' ? 'text-slate-400 line-through' : 'text-emerald-600' }}">
                                        {{ $entry->status === 'voided' ? 'İptal' : 'Aktif' }}
                                    </span>
                                </td>
                            @endif
                            @if(in_array('aksiyon', $visibleColumns, true))
                                <td class="py-3 px-3 text-center">
                                    @if($entry->status === 'posted')
                                        <button wire:click="confirmVoid({{ $entry->id }})" class="text-xs text-rose-600 hover:text-rose-800 font-medium">İptal</button>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($visibleColumns) }}" class="py-8 text-center text-sm text-slate-500">Kayıt bulunamadı.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Sayfalama --}}
        <div class="mt-4">
            {{ $this->entries->links() }}
        </div>
    </section>

    {{-- Void Modal --}}
    @if($voidEntryId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50" wire:click.self="cancelVoid">
            <div class="w-full max-w-md rounded-[10px] border border-slate-200 bg-white p-6 shadow-lg">
                <h3 class="text-lg font-semibold text-slate-900">Kayıt İptal</h3>
                <p class="mt-2 text-sm text-slate-600">Bu kaydı iptal etmek istediğinize emin misiniz? Bu işlem geri alınamaz.</p>

                <div class="mt-4">
                    <label class="block text-xs font-medium text-slate-600 mb-1">İptal Nedeni</label>
                    <textarea wire:model="voidReason" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm" rows="3" placeholder="Opsiyonel..."></textarea>
                </div>

                <div class="mt-4 flex justify-end gap-3">
                    <button wire:click="cancelVoid" class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-[6px] hover:bg-slate-50 transition-colors">
                        Vazgeç
                    </button>
                    <button wire:click="voidEntry" class="px-4 py-2 text-sm font-medium bg-rose-600 text-white rounded-[6px] hover:bg-rose-700 transition-colors">
                        İptal Et
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
