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
                    Yevmiye Defteri
                </div>
                <h1 class="mt-3 text-xl font-semibold tracking-tight text-slate-950 lg:text-2xl">Yevmiye Fişleri (General Journal)</h1>
                <p class="mt-2 text-sm text-slate-500">
                    Çift taraflı kayıt yevmiye fişlerini görüntüleyin, manuel fiş girin ve iptal (void) işlemlerini yönetin.
                </p>
            </div>
            <div class="shrink-0">
                <button wire:click="$toggle('showCreateForm')" class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 transition-colors min-h-[44px]">
                    Yeni Manuel Fiş Girişi
                </button>
            </div>
        </div>
    </section>

    {{-- Manuel Fiş Ekleme Formu --}}
    @if($showCreateForm)
        <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6 space-y-6">
            <h3 class="text-base font-semibold text-slate-900 border-b border-slate-100 pb-3">Manuel Yevmiye Fişi Oluştur</h3>

            {{-- Fiş Başlığı --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Fiş Tarihi</label>
                    <input type="date" wire:model="entryDate" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
                    @error('entryDate') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Fiş Tipi</label>
                    <select wire:model="entryType" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                        <option value="manual">Manuel Yevmiye</option>
                        <option value="opening">Açılış Fişi</option>
                        <option value="closing">Kapanış Fişi</option>
                        <option value="adjustment">Düzeltme Fişi</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Belge No / Referans</label>
                    <input type="text" wire:model="referenceNumber" placeholder="Örn: REF-1004" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
                </div>
                <div class="sm:col-span-3">
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Açıklama</label>
                    <input type="text" wire:model="description" placeholder="Yevmiye fişi açıklaması..." class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Şirket (Legal Entity)</label>
                    <select wire:model="legalEntityId" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                        <option value="">Genel / Yok</option>
                        @foreach($this->legalEntities as $le)
                            <option value="{{ $le->id }}">{{ $le->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">İlişkili Cari (Party)</label>
                    <select wire:model="partyId" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                        <option value="">Yok</option>
                        @foreach($this->parties as $p)
                            <option value="{{ $p->id }}">{{ $p->display_name }} ({{ $p->typeLabel() }})</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Fiş Satırları --}}
            <div class="space-y-3">
                <h4 class="text-sm font-semibold text-slate-700">Fiş Satırları</h4>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-left text-sm text-slate-600 min-w-[700px]">
                        <thead>
                            <tr class="border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider bg-slate-50/50">
                                <th class="p-3 w-1/3">Hesap</th>
                                <th class="p-3 w-28">Borç (Debit)</th>
                                <th class="p-3 w-28">Alacak (Credit)</th>
                                <th class="p-3 w-1/4">Satır Açıklaması</th>
                                <th class="p-3">Satır Carisi</th>
                                <th class="p-3 text-center w-12">Aksiyon</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($lines as $index => $line)
                                <tr>
                                    <td class="p-2">
                                        <select wire:model="lines.{{ $index }}.account_id" class="w-full rounded-[6px] border border-slate-200 bg-white px-2 py-1.5 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                                            <option value="">Hesap Seçin...</option>
                                            @foreach($this->accounts as $acc)
                                                <option value="{{ $acc->id }}">{{ $acc->code }} - {{ $acc->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="p-2">
                                        <input type="number" step="0.01" wire:model.live="lines.{{ $index }}.debit_amount" class="w-full rounded-[6px] border border-slate-200 bg-white px-2 py-1.5 text-base sm:text-sm text-right font-mono focus:border-slate-500 focus:outline-none min-h-[44px]" />
                                    </td>
                                    <td class="p-2">
                                        <input type="number" step="0.01" wire:model.live="lines.{{ $index }}.credit_amount" class="w-full rounded-[6px] border border-slate-200 bg-white px-2 py-1.5 text-base sm:text-sm text-right font-mono focus:border-slate-500 focus:outline-none min-h-[44px]" />
                                    </td>
                                    <td class="p-2">
                                        <input type="text" wire:model="lines.{{ $index }}.description" placeholder="Açıklama" class="w-full rounded-[6px] border border-slate-200 bg-white px-2 py-1.5 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
                                    </td>
                                    <td class="p-2">
                                        <select wire:model="lines.{{ $index }}.party_id" class="w-full rounded-[6px] border border-slate-200 bg-white px-2 py-1.5 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                                            <option value="">Yok</option>
                                            @foreach($this->parties as $p)
                                                <option value="{{ $p->id }}">{{ $p->display_name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="p-2 text-center">
                                        <button type="button" wire:click="removeLine({{ $index }})" class="p-2 text-rose-600 hover:text-rose-900 rounded hover:bg-rose-50 flex items-center justify-center min-w-[40px] min-h-[40px]">
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
                    <button type="button" wire:click="addLine" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-[6px] transition-colors min-h-[44px]">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Satır Ekle
                    </button>

                    <div class="flex items-center gap-6 font-mono text-sm text-slate-700 bg-slate-50 border border-slate-200 rounded-[6px] px-4 py-2">
                        <div>Toplam Borç: <span class="font-bold text-emerald-700">{{ $formatMoney($this->debitTotal) }}</span></div>
                        <div>Toplam Alacak: <span class="font-bold text-rose-700">{{ $formatMoney($this->creditTotal) }}</span></div>
                        <div>Fark: <span class="font-bold {{ abs($this->diff) < 0.005 ? 'text-slate-700' : 'text-rose-600' }}">{{ $formatMoney($this->diff) }}</span></div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2 border-t border-slate-100 pt-4 mt-6">
                <button type="button" wire:click="$set('showCreateForm', false)" class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-[6px] hover:bg-slate-50 min-h-[44px]">
                    İptal
                </button>
                <button type="button" wire:click="postJournalEntry" class="px-4 py-2 text-sm font-medium text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 min-h-[44px]">
                    Fişi Kaydet & Gönder
                </button>
            </div>
        </section>
    @endif

    {{-- Filtreler & Fişler Listesi --}}
    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6 space-y-4">
        {{-- Filtreler Barı --}}
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 bg-slate-50/50 p-4 rounded-[8px] border border-slate-200">
            <div class="sm:col-span-2">
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Detaylı Arama</label>
                <input type="text" wire:model.live="search" placeholder="Belge no veya açıklama ile arayın..." class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Fiş Tipi</label>
                <select wire:model.live="filterType" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                    <option value="">Tüm Fiş Tipleri</option>
                    <option value="manual">Manuel Yevmiye</option>
                    <option value="opening">Açılış</option>
                    <option value="closing">Kapanış</option>
                    <option value="adjustment">Düzeltme</option>
                    <option value="sales_invoice">Satış Faturası</option>
                    <option value="purchase_invoice">Alış Faturası</option>
                    <option value="collection">Tahsilat</option>
                    <option value="payment">Ödeme</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Fiş Durumu</label>
                <select wire:model.live="filterStatus" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                    <option value="">Tüm Durumlar</option>
                    <option value="posted">Onaylandı</option>
                    <option value="voided">İptal Edildi</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Başlangıç Tarihi</label>
                <input type="date" wire:model.live="dateFrom" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Bitiş Tarihi</label>
                <input type="date" wire:model.live="dateTo" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Min Tutar (TRY)</label>
                <input type="number" step="0.01" wire:model.live="filterMinAmount" placeholder="0.00" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Max Tutar (TRY)</label>
                <input type="number" step="0.01" wire:model.live="filterMaxAmount" placeholder="Giriş yapın" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
            </div>

        {{-- Fiş Listesi Tablosu --}}
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left text-sm text-slate-600 min-w-[800px]">
                <thead>
                    <tr class="border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider bg-slate-50/50">
                        <th class="p-4 w-20">Yevmiye No</th>
                        <th class="p-4 w-28">Tarih</th>
                        <th class="p-4 w-28">Fiş Tipi</th>
                        <th class="p-4 w-32">Belge No</th>
                        <th class="p-4">Açıklama</th>
                        <th class="p-4 text-right w-32">Tutar (TRY)</th>
                        <th class="p-4 text-center w-24">Durum</th>
                        <th class="p-4 text-center w-24">Aksiyon</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($this->entries as $entry)
                        @php
                            // Calculate total amount (Sum of debit side)
                            $totalAmount = $entry->lines->sum('debit_amount');
                        @endphp
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="p-4 font-mono font-bold text-slate-900">
                                <div class="flex items-center gap-1.5">
                                    <button type="button" wire:click="toggleEntry({{ $entry->id }})" class="text-slate-400 hover:text-slate-800 transition-colors focus:outline-none">
                                        @if(in_array($entry->id, $expandedEntries))
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                                            </svg>
                                        @else
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                                            </svg>
                                        @endif
                                    </button>
                                    #{{ $entry->id }}
                                </div>
                            </td>
                            <td class="p-4 text-slate-700 font-mono text-xs">{{ $entry->entry_date->format('d.m.Y') }}</td>
                            <td class="p-4 text-slate-500 text-xs uppercase">{{ $entry->entry_type }}</td>
                            <td class="p-4 font-mono text-xs">{{ $entry->reference_number ?: '-' }}</td>
                            <td class="p-4">
                                <div class="font-medium text-slate-900">{{ $entry->description ?: 'Manuel Muhasebe Kaydı' }}</div>
                                @if($entry->party)
                                    <div class="text-[10px] text-slate-400 mt-0.5">Cari: {{ $entry->party->display_name }}</div>
                                @endif
                                @if($entry->legalEntity)
                                    <div class="text-[10px] text-slate-400">Şirket: {{ $entry->legalEntity->name }}</div>
                                @endif
                            </td>
                            <td class="p-4 text-right font-mono font-bold text-slate-900">{{ $formatMoney($totalAmount) }}</td>
                            <td class="p-4 text-center">
                                @if($entry->isVoid())
                                    <span class="inline-flex items-center rounded-full bg-slate-50 px-2 py-1 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-600/10" title="İptal Nedeni: {{ $entry->void_reason }}">İptal</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/10">Onaylı</span>
                                @endif
                            </td>
                            <td class="p-4 text-center">
                                @if(!$entry->isVoid())
                                    <button type="button" wire:click="confirmVoid({{ $entry->id }})" class="px-2.5 py-1 text-xs font-medium text-rose-600 bg-rose-50 border border-rose-200 rounded-[6px] hover:bg-rose-100 transition-colors min-h-[40px] w-full">
                                        İptal Et
                                    </button>
                                @else
                                    <span class="text-xs text-slate-400">-</span>
                                @endif
                            </td>
                        </tr>
                        {{-- Collapse line details --}}
                        @if(in_array($entry->id, $expandedEntries))
                            <tr class="bg-slate-50/20 text-xs">
                                <td colspan="8" class="p-0">
                                    <div class="px-6 py-3 bg-slate-50/40 border-t border-b border-slate-100">
                                        <div class="font-semibold text-slate-500 uppercase tracking-wider mb-2">Fiş Detayı</div>
                                        <div class="space-y-1.5">
                                            @foreach($entry->lines as $line)
                                                <div class="flex justify-between items-center font-mono">
                                                    <div class="flex gap-4">
                                                        <span class="font-bold text-slate-700 w-16">{{ $line->account->code }}</span>
                                                        <span class="text-slate-600 w-48 truncate">{{ $line->account->name }}</span>
                                                        @if($line->description)
                                                            <span class="text-slate-400">| {{ $line->description }}</span>
                                                        @endif
                                                    </div>
                                                <div class="flex gap-8">
                                                    <span class="w-24 text-right {{ $line->debit_amount > 0 ? 'text-emerald-700 font-bold' : 'text-slate-300' }}">{{ $line->debit_amount > 0 ? $formatMoney($line->debit_amount) : '-' }}</span>
                                                    <span class="w-24 text-right {{ $line->credit_amount > 0 ? 'text-rose-700 font-bold' : 'text-slate-300' }}">{{ $line->credit_amount > 0 ? $formatMoney($line->credit_amount) : '-' }}</span>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="8" class="p-8 text-center text-slate-400">
                                <div class="flex flex-col items-center justify-center space-y-2">
                                    <svg class="w-8 h-8 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                    <span class="text-sm">Filtrelere uygun yevmiye fişi bulunamadı.</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="mt-4">
            {{ $this->entries->links() }}
        </div>
    </section>

    {{-- Void Confirmation Modal --}}
    @if($showVoidModal)
        <div class="fixed inset-0 bg-slate-900/50 z-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-[10px] border border-slate-200 max-w-md w-full p-6 shadow-xl space-y-4">
                <h3 class="text-base font-semibold text-slate-900">Yevmiye Fişini İptal Et</h3>
                <p class="text-sm text-slate-500">
                    Bu yevmiye fişini (#{{ $voidingEntryId }}) iptal etmek istediğinize emin misiniz? Bu işlem geri alınamaz ve bakiyeleri sıfırlayacaktır.
                </p>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">İptal Edilme Sebebi</label>
                    <input type="text" wire:model="voidReason" placeholder="Hatalı kayıt girişi..." class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
                    @error('voidReason') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-100 pt-3">
                    <button type="button" wire:click="$set('showVoidModal', false)" class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-[6px] hover:bg-slate-50 min-h-[44px]">
                        Vazgeç
                    </button>
                    <button type="button" wire:click="voidEntry" class="px-4 py-2 text-sm font-medium text-white bg-rose-600 rounded-[6px] hover:bg-rose-700 min-h-[44px]">
                        Fişi İptal Et
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
