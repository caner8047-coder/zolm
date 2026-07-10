@php
    $formatMoney = fn ($value) => '₺' . number_format((float) $value, 2, ',', '.');
    $typeLabel = fn (string $type) => match ($type) {
        'asset'     => 'Varlık',
        'liability' => 'Kaynak',
        'equity'    => 'Öz Kaynak',
        'revenue'   => 'Gelir',
        'expense'   => 'Gider',
        default     => $type,
    };
    $typeTone = fn (string $type) => match ($type) {
        'asset'     => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'revenue'   => 'bg-blue-50 text-blue-700 border-blue-200',
        'liability' => 'bg-amber-50 text-amber-700 border-amber-200',
        'expense'   => 'bg-rose-50 text-rose-700 border-rose-200',
        default     => 'bg-slate-50 text-slate-700 border-slate-200',
    };
    $sortIcon = fn (string $field) => $sortField === $field ? ($sortDirection === 'asc' ? ' ▲' : ' ▼') : ' ⇅';
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
                    Hesap Planı
                </div>
                <h1 class="mt-3 text-xl font-semibold tracking-tight text-slate-950 lg:text-2xl">Hesap Planı (Chart of Accounts)</h1>
                <p class="mt-2 text-sm text-slate-500">
                    Türkiye Tek Düzen Hesap Planına uygun hesap sınıflarını listeyin, yeni hesap tanımlayın ve güncel bakiyelerini inceleyin.
                </p>
            </div>
            <div class="flex flex-col sm:flex-row gap-2 shrink-0">
                <button wire:click="seedDefaultAccounts" class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-[6px] hover:bg-slate-50 transition-colors min-h-[44px]">
                    Varsayılan Planı Yükle
                </button>
                <button wire:click="$toggle('showCreateForm')" class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 transition-colors min-h-[44px]">
                    Yeni Hesap Tanımla
                </button>
            </div>
        </div>
    </section>

    {{-- Yeni Hesap Tanımlama Form Drawer/Modal --}}
    @if($showCreateForm)
        <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
            <h3 class="text-base font-semibold text-slate-900">Yeni Hesap Tanımla</h3>
            <form wire:submit.prevent="createAccount" class="mt-4 grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Hesap Kodu</label>
                    <input type="text" wire:model="newCode" placeholder="Örn: 100.01" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
                    @error('newCode') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Hesap Adı</label>
                    <input type="text" wire:model="newName" placeholder="Örn: Merkez Kasa" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
                    @error('newName') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Hesap Tipi</label>
                    <select wire:model="newType" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                        <option value="asset">Varlık (Asset)</option>
                        <option value="liability">Kaynak (Liability)</option>
                        <option value="equity">Öz Kaynak (Equity)</option>
                        <option value="revenue">Gelir (Revenue)</option>
                        <option value="expense">Gider (Expense)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Bakiye Yönü</label>
                    <select wire:model="newNormalBalance" class="mt-1 block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                        <option value="debit">Borç (Debit)</option>
                        <option value="credit">Alacak (Credit)</option>
                    </select>
                </div>
                <div class="sm:col-span-4 flex justify-end gap-2 mt-2">
                    <button type="button" wire:click="$set('showCreateForm', false)" class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-[6px] hover:bg-slate-50 min-h-[44px]">
                        İptal
                    </button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-slate-900 rounded-[6px] hover:bg-slate-800 min-h-[44px]">
                        Kaydet
                    </button>
                </div>
            </form>
        </section>
    @endif

    {{-- Filtreler & Tablo Section --}}
    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6 space-y-4">
        {{-- Filtreler --}}
        <div class="flex flex-col sm:flex-row gap-3">
            <div class="flex-1">
                <input type="text" wire:model.live="search" placeholder="Hesap kodu veya adı ile arayın..." class="block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]" />
            </div>
            <div class="w-full sm:w-48">
                <select wire:model.live="filterType" class="block w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base sm:text-sm focus:border-slate-500 focus:outline-none min-h-[44px]">
                    <option value="">Tüm Tipler</option>
                    <option value="asset">Varlıklar</option>
                    <option value="liability">Kaynaklar</option>
                    <option value="equity">Öz Kaynaklar</option>
                    <option value="revenue">Gelirler</option>
                    <option value="expense">Giderler</option>
                </select>
            </div>
        </div>

        {{-- Tablo --}}
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left text-sm text-slate-600">
                <thead>
                    <tr class="border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider bg-slate-50/50">
                        <th class="p-4 cursor-pointer select-none" wire:click="sortTable('code')">
                            Hesap Kodu {!! $sortIcon('code') !!}
                        </th>
                        <th class="p-4 cursor-pointer select-none" wire:click="sortTable('name')">
                            Hesap Adı {!! $sortIcon('name') !!}
                        </th>
                        <th class="p-4 cursor-pointer select-none" wire:click="sortTable('type')">
                            Hesap Sınıfı {!! $sortIcon('type') !!}
                        </th>
                        <th class="p-4 cursor-pointer select-none" wire:click="sortTable('normal_balance')">
                            Bakiye Yönü {!! $sortIcon('normal_balance') !!}
                        </th>
                        <th class="p-4 text-right">Bakiye (TRY)</th>
                        <th class="p-4 text-center">Özellik</th>
                        <th class="p-4 text-center">Durum</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($this->accounts as $account)
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="p-4 font-mono font-semibold text-slate-900">{{ $account->code }}</td>
                            <td class="p-4 font-medium text-slate-900">{{ $account->name }}</td>
                            <td class="p-4">
                                <span class="px-2 py-0.5 text-xs font-medium rounded-full border {{ $typeTone($account->type) }}">
                                    {{ $typeLabel($account->type) }}
                                </span>
                            </td>
                            <td class="p-4 text-slate-500 font-mono text-xs">
                                {{ $account->normal_balance === 'debit' ? 'Borç (Debit)' : 'Alacak (Credit)' }}
                            </td>
                            <td class="p-4 text-right font-mono font-bold {{ $account->balance() >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                                {{ $formatMoney($account->balance()) }}
                            </td>
                            <td class="p-4 text-center">
                                @if($account->is_system)
                                    <span class="px-2 py-0.5 text-[10px] font-semibold rounded bg-slate-100 text-slate-500">Sistem</span>
                                @else
                                    <span class="px-2 py-0.5 text-[10px] font-semibold rounded bg-indigo-50 text-indigo-700">Özel</span>
                                @endif
                            </td>
                            <td class="p-4 text-center">
                                @if($account->is_active)
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/10">Aktif</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-slate-50 px-2 py-1 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-600/10">Pasif</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-8 text-center text-slate-400">
                                <div class="flex flex-col items-center justify-center space-y-2">
                                    <svg class="w-8 h-8 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                    <span class="text-sm">Kayıtlı hesap bulunamadı. "Varsayılan Planı Yükle" butonu ile TDHP hesap setini yükleyebilirsiniz.</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="mt-4">
            {{ $this->accounts->links() }}
        </div>
    </section>
</div>
