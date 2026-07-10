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
                    Kasa & Banka
                </div>
                <h1 class="mt-3 text-xl font-semibold tracking-tight text-slate-950 lg:text-2xl">Kasa & Banka Hesapları</h1>
                <p class="mt-2 text-sm text-slate-500">
                    Nakit kasalarınızı, banka mevduat hesaplarınızı yönetin, virman transferleri yapın ve anlık hesap ekstrelerini inceleyin.
                </p>
            </div>
            <div class="flex flex-col sm:flex-row gap-2 shrink-0">
                <button wire:click="$toggle('showCashForm')" class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-[6px] hover:bg-slate-50 transition-colors min-h-[44px]">
                    Yeni Kasa Ekle
                </button>
                <button wire:click="$toggle('showBankForm')" class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-[6px] hover:bg-slate-50 transition-colors min-h-[44px]">
                    Yeni Banka Ekle
                </button>
                <button wire:click="$toggle('showTransferForm')" class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 transition-colors min-h-[44px]">
                    Virman Yap (Transfer)
                </button>
            </div>
        </div>
    </section>

    {{-- Kasa Ekleme Formu --}}
    @if($showCashForm)
        <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
            <h3 class="text-base font-semibold text-slate-900">Yeni Kasa Hesabı Tanımla</h3>
            <form wire:submit.prevent="createCashAccount" class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Kasa Adı</label>
                    <input type="text" wire:model="cashName" placeholder="Örn: Merkez Kasa, TL Kasası" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
                    @error('cashName') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div class="sm:col-span-2 flex justify-end gap-2 mt-2">
                    <button type="button" wire:click="$set('showCashForm', false)" class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-[6px] hover:bg-slate-50 min-h-[44px]">İptal</button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 min-h-[44px]">Kasa Hesabı Aç</button>
                </div>
            </form>
        </section>
    @endif

    {{-- Banka Ekleme Formu --}}
    @if($showBankForm)
        <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
            <h3 class="text-base font-semibold text-slate-900">Yeni Banka Hesabı Tanımla</h3>
            <form wire:submit.prevent="createBankAccount" class="mt-4 grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Banka Adı</label>
                    <input type="text" wire:model="bankName" placeholder="Örn: Akbank, Garanti" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
                    @error('bankName') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Şube Adı</label>
                    <input type="text" wire:model="branchName" placeholder="Örn: Kadıköy" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Hesap Numarası</label>
                    <input type="text" wire:model="accountNumber" placeholder="Örn: 12345678" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">IBAN</label>
                    <input type="text" wire:model="iban" placeholder="TR00..." class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
                </div>
                <div class="sm:col-span-4 flex justify-end gap-2 mt-2">
                    <button type="button" wire:click="$set('showBankForm', false)" class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-[6px] hover:bg-slate-50 min-h-[44px]">İptal</button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 min-h-[44px]">Banka Hesabı Aç</button>
                </div>
            </form>
        </section>
    @endif

    {{-- Virman (Para Transferi) Formu --}}
    @if($showTransferForm)
        <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
            <h3 class="text-base font-semibold text-slate-900">Virman Transferi (Hesaplar Arası Para Gönderimi)</h3>
            <form wire:submit.prevent="executeTransfer" class="mt-4 grid grid-cols-1 sm:grid-cols-4 gap-4">
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
                <div class="sm:col-span-4">
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Açıklama</label>
                    <input type="text" wire:model="transferDescription" placeholder="Örn: Kasadan bankaya yatırılan..." class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
                </div>
                <div class="sm:col-span-4 flex justify-end gap-2 mt-2">
                    <button type="button" wire:click="$set('showTransferForm', false)" class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-[6px] hover:bg-slate-50 min-h-[44px]">İptal</button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 min-h-[44px]">Transferi Tamamla</button>
                </div>
            </form>
        </section>
    @endif

    {{-- Kasa & Banka Listeleri Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 lg:gap-6">
        {{-- Kasa Hesapları --}}
        <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6 space-y-4">
            <h3 class="text-base font-semibold text-slate-900 flex items-center gap-2">
                <span class="p-1.5 bg-emerald-50 text-emerald-700 rounded">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </span>
                Kasa Hesapları
            </h3>
            <div class="divide-y divide-slate-100">
                @forelse($this->cashAccounts as $cash)
                    <div wire:click="selectAccount({{ $cash->account->id }})" class="py-3 flex justify-between items-center cursor-pointer hover:bg-slate-50 rounded-lg px-2 transition-colors {{ $selectedAccountId === $cash->account->id ? 'bg-slate-50 border border-slate-200' : '' }}">
                        <div>
                            <div class="font-medium text-slate-900">{{ $cash->name }}</div>
                            <div class="text-xs text-slate-400 font-mono mt-0.5">{{ $cash->account->code }}</div>
                        </div>
                        <div class="text-right font-mono font-bold text-slate-900">
                            {{ $formatMoney($cash->balance()) }}
                        </div>
                    </div>
                @empty
                    <div class="text-sm text-slate-400 py-6 text-center">Tanımlı kasa bulunamadı.</div>
                @endforelse
            </div>
        </section>

        {{-- Banka Hesapları --}}
        <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6 space-y-4">
            <h3 class="text-base font-semibold text-slate-900 flex items-center gap-2">
                <span class="p-1.5 bg-blue-50 text-blue-700 rounded">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                    </svg>
                </span>
                Banka Hesapları
            </h3>
            <div class="divide-y divide-slate-100">
                @forelse($this->bankAccounts as $bank)
                    <div wire:click="selectAccount({{ $bank->account->id }})" class="py-3 flex justify-between items-center cursor-pointer hover:bg-slate-50 rounded-lg px-2 transition-colors {{ $selectedAccountId === $bank->account->id ? 'bg-slate-50 border border-slate-200' : '' }}">
                        <div>
                            <div class="font-medium text-slate-900">{{ $bank->bank_name }} - {{ $bank->account_number }}</div>
                            @if($bank->iban)
                                <div class="text-[10px] text-slate-400 font-mono mt-0.5">{{ $bank->iban }}</div>
                            @endif
                            <div class="text-xs text-slate-400 font-mono">{{ $bank->account->code }}</div>
                        </div>
                        <div class="text-right font-mono font-bold text-slate-900">
                            {{ $formatMoney($bank->balance()) }}
                        </div>
                    </div>
                @empty
                    <div class="text-sm text-slate-400 py-6 text-center">Tanımlı banka hesabı bulunamadı.</div>
                @endforelse
            </div>
        </section>
    </div>

    {{-- Hesap Ekstresi (Transaction Statement) --}}
    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6 space-y-4">
        <h3 class="text-base font-semibold text-slate-900 border-b border-slate-100 pb-3">Hesap Ekstresi (Statement)</h3>

        @if($this->activeAccount)
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h4 class="font-semibold text-slate-900">{{ $this->activeAccount->name }}</h4>
                    <p class="text-xs text-slate-400 font-mono mt-0.5">Hesap Kodu: {{ $this->activeAccount->code }}</p>
                </div>
                <div class="flex gap-2 w-full sm:w-auto">
                    <input type="date" wire:model.live="statementDateFrom" class="rounded-[6px] border border-slate-200 bg-white px-2 py-1 text-xs focus:border-slate-500 focus:outline-none min-h-[40px]" />
                    <input type="date" wire:model.live="statementDateTo" class="rounded-[6px] border border-slate-200 bg-white px-2 py-1 text-xs focus:border-slate-500 focus:outline-none min-h-[40px]" />
                </div>
            </div>

            <div class="overflow-x-auto mt-4">
                <table class="w-full border-collapse text-left text-sm text-slate-600 min-w-[600px]">
                    <thead>
                        <tr class="border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider bg-slate-50/50">
                            <th class="p-3 w-28">Tarih</th>
                            <th class="p-3 w-32">İşlem Tipi</th>
                            <th class="p-3">Açıklama</th>
                            <th class="p-3 text-right w-28">Borç (Giriş)</th>
                            <th class="p-3 text-right w-28">Alacak (Çıkış)</th>
                            <th class="p-3 text-right w-32">Kümülatif Bakiye</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($this->accountStatement as $row)
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="p-3 font-mono text-xs text-slate-500">{{ date('d.m.Y', strtotime($row['date'])) }}</td>
                                <td class="p-3 text-slate-500 text-xs uppercase">{{ $row['type'] }}</td>
                                <td class="p-3 font-medium text-slate-900">{{ $row['description'] }}</td>
                                <td class="p-3 text-right font-mono text-emerald-700">
                                    {{ $row['debit'] > 0 ? $formatMoney($row['debit']) : '-' }}
                                </td>
                                <td class="p-3 text-right font-mono text-rose-700">
                                    {{ $row['credit'] > 0 ? $formatMoney($row['credit']) : '-' }}
                                </td>
                                <td class="p-3 text-right font-mono font-bold {{ $row['balance'] >= 0 ? 'text-slate-900' : 'text-rose-600' }}">
                                    {{ $formatMoney($row['balance']) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-8 text-center text-slate-400">
                                    Seçilen tarih aralığında hareket bulunamadı.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @else
            <div class="p-8 text-center text-slate-400">
                <div class="flex flex-col items-center justify-center space-y-2">
                    <svg class="w-8 h-8 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="text-sm">Detaylı hesap ekstresi için yukarıdan bir Kasa veya Banka hesabı seçin.</span>
                </div>
            </div>
        @endif
    </section>
</div>
