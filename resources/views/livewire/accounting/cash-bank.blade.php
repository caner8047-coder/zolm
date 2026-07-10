@php
    $formatMoney = fn ($value) => '₺' . number_format((float) $value, 2, ',', '.');
@endphp

<div class="w-full space-y-4 lg:space-y-6">
    {{-- Mesaj Paneli --}}
    @if($message !== '')
        <div class="rounded-[8px] border p-4 text-sm {{ $messageType === 'error' ? 'border-rose-200 bg-rose-50/70 text-rose-800' : 'border-emerald-200 bg-emerald-50/70 text-emerald-800' }} flex justify-between items-center">
            <span>{{ $message }}</span>
            <button wire:click="$set('message', '')" class="text-xs font-semibold hover:underline">Kapat</button>
        </div>
    @endif

    {{-- Üst Section / Command Surface --}}
    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <div class="inline-flex items-center rounded-[6px] border border-slate-200 bg-slate-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">
                    Kasa & Banka
                </div>
                <h1 class="mt-3 text-xl font-semibold tracking-tight text-slate-950 lg:text-2xl">Kasa & Banka Hesapları</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Nakit kasalarınızı, banka mevduat hesaplarınızı yönetin, virman transferleri yapın ve anlık hesap ekstrelerini inceleyin.
                </p>
            </div>
            <div class="flex flex-wrap gap-2 shrink-0">
                <button wire:click="$toggle('showCashForm')" class="w-full sm:w-auto px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-[6px] hover:bg-slate-50 transition-colors min-h-[44px]">
                    Yeni Kasa Ekle
                </button>
                <button wire:click="$toggle('showBankForm')" class="w-full sm:w-auto px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-[6px] hover:bg-slate-50 transition-colors min-h-[44px]">
                    Yeni Banka Ekle
                </button>
                <button wire:click="$toggle('showTransferForm')" class="w-full sm:w-auto px-4 py-2 text-sm font-medium text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 transition-colors min-h-[44px]">
                    Virman Yap (Transfer)
                </button>
            </div>
        </div>
    </section>

    {{-- KPI Kartları --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 lg:gap-4">
        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Toplam Kasa</div>
            <div class="mt-2 text-lg font-mono font-bold text-slate-900">{{ $formatMoney($this->kpiMetrics['total_cash']) }}</div>
        </div>
        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Toplam Banka</div>
            <div class="mt-2 text-lg font-mono font-bold text-slate-900">{{ $formatMoney($this->kpiMetrics['total_bank']) }}</div>
        </div>
        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4 col-span-2 sm:col-span-1">
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Toplam Likidite</div>
            <div class="mt-2 text-lg font-mono font-bold text-slate-950">{{ $formatMoney($this->kpiMetrics['total_liquidity']) }}</div>
        </div>
        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Bugünkü Virman</div>
            <div class="mt-2 text-lg font-mono font-bold text-slate-900">{{ $formatMoney($this->kpiMetrics['today_transfers_sum']) }}</div>
        </div>
        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">İptal Transfer</div>
            <div class="mt-2 text-lg font-mono font-bold text-slate-900">{{ $this->kpiMetrics['voided_transfers_count'] }} Adet</div>
        </div>
    </div>

    {{-- Kasa Ekleme Formu --}}
    @if($showCashForm)
        <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6 space-y-4">
            <h3 class="text-base font-semibold text-slate-900">Yeni Kasa Hesabı Tanımla</h3>
            <form wire:submit.prevent="createCashAccount" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Kasa Adı</label>
                    <input type="text" wire:model="cashName" placeholder="Örn: TL Kasası, Şube Kasası" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
                    @error('cashName') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Yasal Birlik (Legal Entity)</label>
                    <select wire:model="cashLegalEntityId" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                        <option value="">Seçin (Opsiyonel)...</option>
                        @foreach($this->legalEntities as $le)
                            <option value="{{ $le->id }}">{{ $le->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2 flex justify-end gap-2 mt-2">
                    <button type="button" wire:click="$set('showCashForm', false)" class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-[6px] hover:bg-slate-50 min-h-[44px]">İptal</button>
                    <button type="submit" wire:loading.attr="disabled" class="px-4 py-2 text-sm font-medium text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 min-h-[44px]">Kasa Hesabı Aç</button>
                </div>
            </form>
        </section>
    @endif

    {{-- Banka Ekleme Formu --}}
    @if($showBankForm)
        <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6 space-y-4">
            <h3 class="text-base font-semibold text-slate-900">Yeni Banka Hesabı Tanımla</h3>
            <form wire:submit.prevent="createBankAccount" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Banka Adı</label>
                    <input type="text" wire:model="bankName" placeholder="Örn: Garanti BBVA" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
                    @error('bankName') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Şube Adı</label>
                    <input type="text" wire:model="branchName" placeholder="Örn: Kadıköy" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Hesap Numarası</label>
                    <input type="text" wire:model="accountNumber" placeholder="Örn: 98765432" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">IBAN</label>
                    <input type="text" wire:model="iban" placeholder="TR00..." class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Döviz Cinsi</label>
                    <select wire:model="currencyCode" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                        <option value="TRY">TRY (Türk Lirası)</option>
                        <option value="USD">USD (Amerikan Doları)</option>
                        <option value="EUR">EUR (Euro)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Yasal Birlik (Legal Entity)</label>
                    <select wire:model="bankLegalEntityId" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                        <option value="">Seçin (Opsiyonel)...</option>
                        @foreach($this->legalEntities as $le)
                            <option value="{{ $le->id }}">{{ $le->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-3 flex justify-end gap-2 mt-2">
                    <button type="button" wire:click="$set('showBankForm', false)" class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-[6px] hover:bg-slate-50 min-h-[44px]">İptal</button>
                    <button type="submit" wire:loading.attr="disabled" class="px-4 py-2 text-sm font-medium text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 min-h-[44px]">Banka Hesabı Aç</button>
                </div>
            </form>
        </section>
    @endif

    {{-- Virman (Para Transferi) Formu --}}
    @if($showTransferForm)
        <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6 space-y-4">
            <h3 class="text-base font-semibold text-slate-900">Virman Transferi (Hesaplar Arası Para Gönderimi)</h3>
            <form wire:submit.prevent="executeTransfer" class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Kaynak Hesap</label>
                    <select wire:model="fromAccountId" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                        <option value="">Seçin...</option>
                        @foreach($this->transferableAccounts as $acc)
                            <option value="{{ $acc->id }}">{{ $acc->code }} - {{ $acc->name }} ({{ $formatMoney($acc->balance()) }})</option>
                        @endforeach
                    </select>
                    @error('fromAccountId') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Hedef Hesap</label>
                    <select wire:model="toAccountId" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                        <option value="">Seçin...</option>
                        @foreach($this->transferableAccounts as $acc)
                            <option value="{{ $acc->id }}">{{ $acc->code }} - {{ $acc->name }} ({{ $formatMoney($acc->balance()) }})</option>
                        @endforeach
                    </select>
                    @error('toAccountId') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Tutar (TRY)</label>
                    <input type="number" step="0.01" wire:model="transferAmount" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm text-right font-mono focus:border-slate-500 focus:outline-none min-h-[44px]" />
                    @error('transferAmount') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Transfer Tarihi</label>
                    <input type="date" wire:model="transferDate" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
                    @error('transferDate') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Yasal Birlik (Legal Entity)</label>
                    <select wire:model="transferLegalEntityId" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                        <option value="">Seçin (Opsiyonel)...</option>
                        @foreach($this->legalEntities as $le)
                            <option value="{{ $le->id }}">{{ $le->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Açıklama</label>
                    <input type="text" wire:model="transferDescription" placeholder="Örn: Kasadan bankaya aktarım..." class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
                </div>
                <div class="sm:col-span-4 flex justify-end gap-2 mt-2">
                    <button type="button" wire:click="$set('showTransferForm', false)" class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-[6px] hover:bg-slate-50 min-h-[44px]">İptal</button>
                    <button type="submit" wire:loading.attr="disabled" class="px-4 py-2 text-sm font-medium text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 min-h-[44px]">Transferi Tamamla</button>
                </div>
            </form>
        </section>
    @endif

    {{-- Kasa & Banka Listeleri Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 lg:gap-6">
        {{-- Kasa Hesapları ve Banka Hesapları listesi --}}
        <div class="lg:col-span-1 space-y-4">
            {{-- Arama Çubuğu --}}
            <div class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm space-y-2">
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Hesap Ara</label>
                <input type="text" wire:model.live="search" placeholder="Hesap adı, kodu, IBAN veya banka..." class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
            </div>

            {{-- Kasa Hesapları --}}
            <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm space-y-3">
                <h3 class="text-sm font-semibold text-slate-900 flex items-center gap-2 border-b border-slate-100 pb-2">
                    <span class="p-1 bg-emerald-50 text-emerald-700 rounded">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </span>
                    Kasa Hesapları
                </h3>
                <div class="divide-y divide-slate-100 max-h-[300px] overflow-y-auto">
                    @forelse($this->cashAccounts as $cash)
                        <div wire:click="selectAccount({{ $cash->account->id }})" class="py-2.5 flex justify-between items-center cursor-pointer hover:bg-slate-50 rounded-lg px-2 transition-colors {{ $selectedAccountId === $cash->account->id ? 'bg-slate-50 border border-slate-200' : '' }}">
                            <div class="min-w-0">
                                <div class="font-medium text-sm text-slate-900 truncate">{{ $cash->name }}</div>
                                <div class="text-[10px] text-slate-400 font-mono mt-0.5">{{ $cash->account->code }}</div>
                            </div>
                            <div class="text-right font-mono text-sm font-bold text-slate-900 shrink-0 pl-2">
                                {{ $formatMoney($cash->balance()) }}
                            </div>
                        </div>
                    @empty
                        <div class="text-xs text-slate-400 py-6 text-center">Tanımlı kasa bulunamadı.</div>
                    @endforelse
                </div>
            </section>

            {{-- Banka Hesapları --}}
            <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm space-y-3">
                <h3 class="text-sm font-semibold text-slate-900 flex items-center gap-2 border-b border-slate-100 pb-2">
                    <span class="p-1 bg-blue-50 text-blue-700 rounded">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                        </svg>
                    </span>
                    Banka Hesapları
                </h3>
                <div class="divide-y divide-slate-100 max-h-[300px] overflow-y-auto">
                    @forelse($this->bankAccounts as $bank)
                        <div wire:click="selectAccount({{ $bank->account->id }})" class="py-2.5 flex justify-between items-center cursor-pointer hover:bg-slate-50 rounded-lg px-2 transition-colors {{ $selectedAccountId === $bank->account->id ? 'bg-slate-50 border border-slate-200' : '' }}">
                            <div class="min-w-0">
                                <div class="font-medium text-sm text-slate-900 truncate">{{ $bank->bank_name }} - {{ $bank->account_number }}</div>
                                @if($bank->iban)
                                    <div class="text-[9px] text-slate-400 font-mono truncate mt-0.5">{{ $bank->iban }}</div>
                                @endif
                                <div class="text-[10px] text-slate-400 font-mono">{{ $bank->account->code }}</div>
                            </div>
                            <div class="text-right font-mono text-sm font-bold text-slate-900 shrink-0 pl-2">
                                {{ $formatMoney($bank->balance()) }}
                            </div>
                        </div>
                    @empty
                        <div class="text-xs text-slate-400 py-6 text-center">Tanımlı banka hesabı bulunamadı.</div>
                    @endforelse
                </div>
            </section>
        </div>

        {{-- Ekstre / Statement Tablosu --}}
        <div class="lg:col-span-2 space-y-4">
            <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6 space-y-4">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 border-b border-slate-100 pb-3">
                    <div>
                        <h3 class="text-base font-semibold text-slate-900">Hesap Ekstresi (Statement)</h3>
                        @if($this->activeAccount)
                            <p class="text-xs text-slate-400 font-mono mt-0.5">Seçili: {{ $this->activeAccount->name }} ({{ $this->activeAccount->code }})</p>
                        @endif
                    </div>
                    @if($this->activeAccount)
                        <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                            <input type="date" wire:model.live="statementDateFrom" class="rounded-[6px] border border-slate-200 bg-white px-2 py-1 text-xs focus:border-slate-500 focus:outline-none min-h-[38px]" />
                            <input type="date" wire:model.live="statementDateTo" class="rounded-[6px] border border-slate-200 bg-white px-2 py-1 text-xs focus:border-slate-500 focus:outline-none min-h-[38px]" />

                            {{-- Kolon Özelleştirme --}}
                            <div class="relative" x-data="{ open: false }">
                                <button @click="open = !open" class="inline-flex items-center justify-center px-3 py-1.5 text-xs font-medium text-slate-700 bg-white border border-slate-200 rounded-[6px] hover:bg-slate-50 min-h-[38px] transition-colors">
                                    Kolonlar
                                </button>
                                <div x-show="open" @click.outside="open = false" x-cloak class="absolute right-0 mt-1 w-44 bg-white border border-slate-200 rounded-[6px] shadow-lg p-2 z-10 space-y-1">
                                    @foreach(['date' => 'Tarih', 'type' => 'İşlem Tipi', 'description' => 'Açıklama', 'debit' => 'Borç (Giriş)', 'credit' => 'Alacak (Çıkış)', 'balance' => 'Bakiye'] as $col => $label)
                                        <label class="flex items-center gap-2 px-2 py-1 text-xs text-slate-700 hover:bg-slate-50 rounded cursor-pointer select-none">
                                            <input type="checkbox" wire:click="toggleColumn('{{ $col }}')" {{ in_array($col, $visibleColumns) ? 'checked' : '' }} class="rounded border-slate-300 text-slate-900 focus:ring-slate-500" />
                                            {{ $label }}
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                @if($this->activeAccount)
                    {{-- Tablo --}}
                    <div class="hidden md:block overflow-x-auto">
                        <table class="w-full border-collapse text-left text-sm text-slate-600 min-w-[500px]">
                            <thead>
                                <tr class="border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider bg-slate-50/50">
                                    @if(in_array('date', $visibleColumns))
                                        <th class="p-3 cursor-pointer select-none" wire:click="sortTable('date')">
                                            Tarih @if($sortBy === 'date') {{ $sortDir === 'asc' ? '↑' : '↓' }} @endif
                                        </th>
                                    @endif
                                    @if(in_array('type', $visibleColumns))
                                        <th class="p-3 cursor-pointer select-none" wire:click="sortTable('type')">
                                            İşlem Tipi @if($sortBy === 'type') {{ $sortDir === 'asc' ? '↑' : '↓' }} @endif
                                        </th>
                                    @endif
                                    @if(in_array('description', $visibleColumns))
                                        <th class="p-3 cursor-pointer select-none" wire:click="sortTable('description')">
                                            Açıklama @if($sortBy === 'description') {{ $sortDir === 'asc' ? '↑' : '↓' }} @endif
                                        </th>
                                    @endif
                                    @if(in_array('debit', $visibleColumns))
                                        <th class="p-3 text-right cursor-pointer select-none" wire:click="sortTable('debit')">
                                            Giriş (Borç) @if($sortBy === 'debit') {{ $sortDir === 'asc' ? '↑' : '↓' }} @endif
                                        </th>
                                    @endif
                                    @if(in_array('credit', $visibleColumns))
                                        <th class="p-3 text-right cursor-pointer select-none" wire:click="sortTable('credit')">
                                            Çıkış (Alacak) @if($sortBy === 'credit') {{ $sortDir === 'asc' ? '↑' : '↓' }} @endif
                                        </th>
                                    @endif
                                    @if(in_array('balance', $visibleColumns))
                                        <th class="p-3 text-right cursor-pointer select-none" wire:click="sortTable('balance')">
                                            Bakiye @if($sortBy === 'balance') {{ $sortDir === 'asc' ? '↑' : '↓' }} @endif
                                        </th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($this->accountStatement as $row)
                                    <tr class="hover:bg-slate-50/50 transition-colors">
                                        @if(in_array('date', $visibleColumns))
                                            <td class="p-3 font-mono text-xs text-slate-500">{{ date('d.m.Y', strtotime($row['date'])) }}</td>
                                        @endif
                                        @if(in_array('type', $visibleColumns))
                                            <td class="p-3 text-slate-500 text-xs uppercase">{{ $row['type'] }}</td>
                                        @endif
                                        @if(in_array('description', $visibleColumns))
                                            <td class="p-3 font-medium text-slate-900">{{ $row['description'] }}</td>
                                        @endif
                                        @if(in_array('debit', $visibleColumns))
                                            <td class="p-3 text-right font-mono text-emerald-700">
                                                {{ $row['debit'] > 0 ? $formatMoney($row['debit']) : '-' }}
                                            </td>
                                        @endif
                                        @if(in_array('credit', $visibleColumns))
                                            <td class="p-3 text-right font-mono text-rose-700">
                                                {{ $row['credit'] > 0 ? $formatMoney($row['credit']) : '-' }}
                                            </td>
                                        @endif
                                        @if(in_array('balance', $visibleColumns))
                                            <td class="p-3 text-right font-mono font-bold {{ $row['balance'] >= 0 ? 'text-slate-900' : 'text-rose-600' }}">
                                                {{ $formatMoney($row['balance']) }}
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ count($visibleColumns) }}" class="p-8 text-center text-slate-400">
                                            Hareket bulunamadı.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- Mobil Kart Görünümü --}}
                    <div class="md:hidden space-y-3">
                        @forelse($this->accountStatement as $row)
                            <div class="p-3 border border-slate-100 bg-slate-50/30 rounded-lg space-y-2 text-xs">
                                <div class="flex justify-between items-center border-b border-slate-100 pb-1.5">
                                    <span class="font-mono text-slate-500">{{ date('d.m.Y', strtotime($row['date'])) }}</span>
                                    <span class="uppercase tracking-wider font-semibold text-[10px] text-slate-400">{{ $row['type'] }}</span>
                                </div>
                                <div class="font-medium text-slate-900 text-sm">{{ $row['description'] }}</div>
                                <div class="grid grid-cols-3 gap-1 pt-1.5 font-mono text-right">
                                    <div>
                                        <span class="block text-[9px] text-slate-400 text-left uppercase">Borç (Giriş)</span>
                                        <span class="text-emerald-700 block mt-0.5">{{ $row['debit'] > 0 ? $formatMoney($row['debit']) : '-' }}</span>
                                    </div>
                                    <div>
                                        <span class="block text-[9px] text-slate-400 text-left uppercase">Alacak (Çıkış)</span>
                                        <span class="text-rose-700 block mt-0.5">{{ $row['credit'] > 0 ? $formatMoney($row['credit']) : '-' }}</span>
                                    </div>
                                    <div>
                                        <span class="block text-[9px] text-slate-400 text-left uppercase">Bakiye</span>
                                        <span class="font-bold block mt-0.5 {{ $row['balance'] >= 0 ? 'text-slate-950' : 'text-rose-600' }}">{{ $formatMoney($row['balance']) }}</span>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="p-6 text-center text-slate-400 text-xs">Hareket bulunamadı.</div>
                        @endforelse
                    </div>
                @else
                    <div class="p-12 text-center text-slate-400">
                        <div class="flex flex-col items-center justify-center space-y-2">
                            <svg class="w-12 h-12 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                            <span class="text-sm font-medium">Lütfen hareketlerini görmek istediğiniz kasa veya banka hesabını seçin.</span>
                        </div>
                    </div>
                @endif
            </section>
        </div>
    </div>

    {{-- Son Transferler (Virman Listesi) --}}
    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6 space-y-4">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 border-b border-slate-100 pb-3">
            <div>
                <h3 class="text-base font-semibold text-slate-900">Virman Transfer Geçmişi (Money Transfers)</h3>
                <p class="text-xs text-slate-400 mt-0.5">Hesaplar arasında yapılan para aktarım geçmişi.</p>
            </div>
            {{-- Virman Filtreleri --}}
            <div class="flex flex-wrap gap-2 w-full sm:w-auto">
                <select wire:model.live="filterStatus" class="rounded-[6px] border border-slate-200 bg-white px-2 py-1 text-xs focus:border-slate-500 focus:outline-none min-h-[38px]">
                    <option value="">Tüm Durumlar</option>
                    <option value="posted">Aktif</option>
                    <option value="voided">İptal Edilmiş</option>
                </select>
                <input type="date" wire:model.live="filterDateFrom" class="rounded-[6px] border border-slate-200 bg-white px-2 py-1 text-xs focus:border-slate-500 focus:outline-none min-h-[38px]" />
                <input type="date" wire:model.live="filterDateTo" class="rounded-[6px] border border-slate-200 bg-white px-2 py-1 text-xs focus:border-slate-500 focus:outline-none min-h-[38px]" />
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left text-sm text-slate-600 min-w-[700px]">
                <thead>
                    <tr class="border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider bg-slate-50/50">
                        <th class="p-3 w-28">Tarih</th>
                        <th class="p-3">Gönderen (Kaynak)</th>
                        <th class="p-3">Alıcı (Hedef)</th>
                        <th class="p-3">Açıklama / Referans</th>
                        <th class="p-3 text-right w-32">Tutar</th>
                        <th class="p-3 w-24">Durum</th>
                        <th class="p-3 text-right w-24">İşlem</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($this->recentTransfers as $tr)
                        <tr class="hover:bg-slate-50/50 transition-colors {{ $tr->status === 'voided' ? 'bg-slate-50/40 opacity-70' : '' }}">
                            <td class="p-3 font-mono text-xs text-slate-500">
                                {{ $tr->transfer_date->format('d.m.Y') }}
                            </td>
                            <td class="p-3 font-medium text-slate-900">
                                {{ $tr->fromAccount->name }}
                                <span class="block text-[10px] text-slate-400 font-mono">{{ $tr->fromAccount->code }}</span>
                            </td>
                            <td class="p-3 font-medium text-slate-900">
                                {{ $tr->toAccount->name }}
                                <span class="block text-[10px] text-slate-400 font-mono">{{ $tr->toAccount->code }}</span>
                            </td>
                            <td class="p-3 text-xs">
                                <div class="font-medium text-slate-900">{{ $tr->description ?: 'Virman Transferi' }}</div>
                                @if($tr->reference_number)
                                    <div class="text-[10px] text-slate-400 font-mono mt-0.5">Ref: {{ $tr->reference_number }}</div>
                                @endif
                                @if($tr->source_key)
                                    <div class="text-[9px] text-slate-400 font-mono">Key: {{ $tr->source_key }}</div>
                                @endif
                            </td>
                            <td class="p-3 text-right font-mono font-bold text-slate-900">
                                {{ $formatMoney($tr->amount) }}
                            </td>
                            <td class="p-3">
                                @if($tr->status === 'voided')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-rose-100 text-rose-800" title="İptal Nedeni: {{ $tr->void_reason }}">
                                        İPTAL
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-emerald-100 text-emerald-800">
                                        AKTİF
                                    </span>
                                @endif
                            </td>
                            <td class="p-3 text-right">
                                @if($tr->status !== 'voided')
                                    <button wire:click="voidTransfer({{ $tr->id }})" wire:confirm="Bu virman transferini iptal etmek istediğinize emin misiniz? Fiş ve bakiyeler ters yönde güncellenecektir." class="px-2.5 py-1 text-xs text-rose-600 bg-rose-50 border border-rose-100 rounded-[4px] hover:bg-rose-100 transition-colors">
                                        İptal Et
                                    </button>
                                @else
                                    <span class="text-slate-400 text-xs">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-8 text-center text-slate-400">
                                Virman kaydı bulunamadı.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
