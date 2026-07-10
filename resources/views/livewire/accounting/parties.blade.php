@php
    $formatMoney = fn ($value) => '₺' . number_format((float) $value, 2, ',', '.');
    $roleLabel = fn ($role) => match ($role) {
        'customer' => 'Müşteri',
        'supplier' => 'Tedarikçi',
        default => $role,
    };
    $typeLabel = fn ($type) => match ($type) {
        'person' => 'Şahıs',
        'organization' => 'Şirket',
        default => 'Belirsiz',
    };
@endphp

<div class="w-full space-y-4 lg:space-y-6">
    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <div class="inline-flex items-center rounded-[6px] border border-slate-200 bg-slate-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">
                    Cari Yönetimi
                </div>
                <h1 class="mt-3 text-xl font-semibold tracking-tight text-slate-950 lg:text-2xl">Cari Kartları</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">
                    Müşteri ve tedarikçi kayıtlarını tek listede yönetin; rol, iletişim, vergi bilgileri ve açık bakiye etkisini aynı panelden izleyin.
                </p>
            </div>
            <button wire:click="openCreateForm" class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] bg-slate-900 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-slate-800 sm:w-auto">
                + Yeni Cari
            </button>
        </div>

        <div class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-5">
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <p class="text-[10px] uppercase tracking-[0.2em] text-slate-500">Aktif Cari</p>
                <p class="mt-2 text-xl font-bold text-slate-900">{{ $this->kpis['active'] }}</p>
            </div>
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <p class="text-[10px] uppercase tracking-[0.2em] text-slate-500">Müşteri</p>
                <p class="mt-2 text-xl font-bold text-emerald-700">{{ $this->kpis['customers'] }}</p>
            </div>
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <p class="text-[10px] uppercase tracking-[0.2em] text-slate-500">Tedarikçi</p>
                <p class="mt-2 text-xl font-bold text-blue-700">{{ $this->kpis['suppliers'] }}</p>
            </div>
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <p class="text-[10px] uppercase tracking-[0.2em] text-slate-500">Kara Liste</p>
                <p class="mt-2 text-xl font-bold text-rose-700">{{ $this->kpis['blacklisted'] }}</p>
            </div>
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <p class="text-[10px] uppercase tracking-[0.2em] text-slate-500">Net Bakiye</p>
                <p class="mt-2 text-xl font-bold {{ $this->kpis['net_balance'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $formatMoney($this->kpis['net_balance']) }}</p>
            </div>
        </div>
    </section>

    @if($message !== '')
        <div class="rounded-[8px] border px-4 py-3 text-sm {{ $messageType === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-800' }}">
            {{ $message }}
        </div>
    @endif

    <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 p-4 lg:p-5">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="grid flex-1 grid-cols-1 gap-3 sm:grid-cols-3">
                    <input wire:model.live.debounce.300ms="search" type="search" placeholder="Cari, telefon, e-posta, vergi no ara..." class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 outline-none transition focus:border-slate-400 sm:col-span-1 sm:text-sm">
                    <select wire:model.live="filterRole" class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 outline-none transition focus:border-slate-400 sm:text-sm">
                        <option value="">Tüm Roller</option>
                        <option value="customer">Müşteri</option>
                        <option value="supplier">Tedarikçi</option>
                    </select>
                    <select wire:model.live="filterStatus" class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 outline-none transition focus:border-slate-400 sm:text-sm">
                        <option value="">Tüm Durumlar</option>
                        <option value="active">Aktif</option>
                        <option value="passive">Pasif</option>
                    </select>
                </div>
            </div>
        </div>

        @if($showForm)
            <div class="border-b border-slate-100 bg-slate-50/60 p-4 lg:p-5">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">{{ $isEditing ? 'Cari Kartını Düzenle' : 'Yeni Cari Kartı' }}</h2>
                        <p class="mt-1 text-xs text-slate-500">Cariyi müşteri, tedarikçi veya iki rol birden olacak şekilde kaydedebilirsiniz.</p>
                    </div>
                    <button wire:click="resetForm" class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Kapat</button>
                </div>

                <form wire:submit.prevent="saveParty" class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="sm:col-span-2">
                        <label class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Cari Adı</label>
                        <input wire:model.defer="displayName" type="text" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 sm:text-sm">
                        @error('displayName') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Tip</label>
                        <select wire:model.defer="partyType" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 sm:text-sm">
                            <option value="unknown">Belirsiz</option>
                            <option value="person">Şahıs</option>
                            <option value="organization">Şirket</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Durum</label>
                        <select wire:model.defer="status" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 sm:text-sm">
                            <option value="active">Aktif</option>
                            <option value="passive">Pasif</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">E-posta</label>
                        <input wire:model.defer="primaryEmail" type="email" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 sm:text-sm">
                        @error('primaryEmail') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Telefon</label>
                        <input wire:model.defer="primaryPhone" type="text" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 sm:text-sm">
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Vergi No / TCKN</label>
                        <input wire:model.defer="taxNumber" type="text" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 sm:text-sm">
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Vergi Dairesi</label>
                        <input wire:model.defer="taxOffice" type="text" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 sm:text-sm">
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Şehir</label>
                        <input wire:model.defer="city" type="text" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 sm:text-sm">
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">İlçe</label>
                        <input wire:model.defer="district" type="text" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 sm:text-sm">
                    </div>
                    <div class="sm:col-span-2 xl:col-span-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex flex-wrap gap-3">
                                <label class="inline-flex items-center gap-2 rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
                                    <input wire:model.defer="roles" type="checkbox" value="customer" class="rounded border-slate-300 text-slate-900">
                                    Müşteri
                                </label>
                                <label class="inline-flex items-center gap-2 rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
                                    <input wire:model.defer="roles" type="checkbox" value="supplier" class="rounded border-slate-300 text-slate-900">
                                    Tedarikçi
                                </label>
                                <label class="inline-flex items-center gap-2 rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
                                    <input wire:model.defer="isBlacklisted" type="checkbox" class="rounded border-slate-300 text-slate-900">
                                    Kara liste
                                </label>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" wire:click="resetForm" class="min-h-[44px] rounded-[6px] border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Vazgeç</button>
                                <button type="submit" class="min-h-[44px] rounded-[6px] bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Kaydet</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        @endif

        <div class="hidden overflow-x-auto md:block">
            <table class="w-full table-fixed text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50/80 text-[11px] uppercase tracking-[0.16em] text-slate-500">
                    <tr>
                        <th class="w-[240px] cursor-pointer px-4 py-3" wire:click="sortTable('display_name')">Cari</th>
                        <th class="w-[130px] cursor-pointer px-4 py-3" wire:click="sortTable('party_type')">Tip</th>
                        <th class="w-[150px] px-4 py-3">Rol</th>
                        <th class="w-[220px] px-4 py-3">İletişim</th>
                        <th class="w-[150px] cursor-pointer px-4 py-3" wire:click="sortTable('city')">Konum</th>
                        <th class="w-[140px] px-4 py-3 text-right">Bakiye</th>
                        <th class="w-[110px] cursor-pointer px-4 py-3" wire:click="sortTable('status')">Durum</th>
                        <th class="w-[150px] px-4 py-3 text-right">İşlem</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($this->parties as $party)
                        @php
                            $balance = (float) ($party->posted_debit_sum ?? 0) - (float) ($party->posted_credit_sum ?? 0);
                        @endphp
                        <tr class="align-top hover:bg-slate-50/70">
                            <td class="px-4 py-3">
                                <div class="truncate font-semibold text-slate-900">{{ $party->display_name }}</div>
                                <div class="mt-1 truncate text-xs text-slate-500">{{ $party->tax_number ?: 'Vergi no yok' }}</div>
                                @if($party->is_blacklisted)
                                    <span class="mt-2 inline-flex rounded bg-rose-50 px-2 py-0.5 text-[11px] font-semibold text-rose-700">Kara liste</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-700">{{ $typeLabel($party->party_type) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1">
                                    @forelse($party->roles as $role)
                                        <span class="rounded bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-700">{{ $roleLabel($role->role) }}</span>
                                    @empty
                                        <span class="text-xs text-slate-400">Rol yok</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-600">
                                <div class="truncate">{{ $party->primary_email ?: 'E-posta yok' }}</div>
                                <div class="mt-1 truncate">{{ $party->primary_phone ?: 'Telefon yok' }}</div>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-600">{{ trim(($party->city ?? '') . ' ' . ($party->district ?? '')) ?: '-' }}</td>
                            <td class="px-4 py-3 text-right font-semibold {{ $balance >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $formatMoney($balance) }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded px-2 py-0.5 text-[11px] font-semibold {{ $party->status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $party->status === 'active' ? 'Aktif' : 'Pasif' }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-2">
                                    <button wire:click="editParty({{ $party->id }})" class="rounded-[6px] border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Düzenle</button>
                                    @if($party->status === 'active')
                                        <button wire:click="markPassive({{ $party->id }})" class="rounded-[6px] bg-slate-100 px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-200">Pasifle</button>
                                    @else
                                        <button wire:click="markActive({{ $party->id }})" class="rounded-[6px] bg-slate-900 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-slate-800">Aktifle</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-10 text-center text-sm text-slate-500">Cari kaydı bulunamadı.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="space-y-3 p-4 md:hidden">
            @forelse($this->parties as $party)
                @php
                    $balance = (float) ($party->posted_debit_sum ?? 0) - (float) ($party->posted_credit_sum ?? 0);
                @endphp
                <div class="rounded-[8px] border border-slate-200 bg-white p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="truncate font-semibold text-slate-900">{{ $party->display_name }}</div>
                            <div class="mt-1 text-xs text-slate-500">{{ $typeLabel($party->party_type) }} · {{ $party->status === 'active' ? 'Aktif' : 'Pasif' }}</div>
                        </div>
                        <div class="text-right text-sm font-semibold {{ $balance >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $formatMoney($balance) }}</div>
                    </div>
                    <div class="mt-3 text-xs text-slate-600">{{ $party->primary_phone ?: 'Telefon yok' }} · {{ $party->primary_email ?: 'E-posta yok' }}</div>
                    <div class="mt-3 flex gap-2">
                        <button wire:click="editParty({{ $party->id }})" class="min-h-[40px] flex-1 rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700">Düzenle</button>
                        @if($party->status === 'active')
                            <button wire:click="markPassive({{ $party->id }})" class="min-h-[40px] flex-1 rounded-[6px] bg-slate-100 px-3 py-2 text-xs font-medium text-slate-600">Pasifle</button>
                        @else
                            <button wire:click="markActive({{ $party->id }})" class="min-h-[40px] flex-1 rounded-[6px] bg-slate-900 px-3 py-2 text-xs font-medium text-white">Aktifle</button>
                        @endif
                    </div>
                </div>
            @empty
                <div class="rounded-[8px] border border-dashed border-slate-200 p-6 text-center text-sm text-slate-500">Cari kaydı bulunamadı.</div>
            @endforelse
        </div>

        <div class="border-t border-slate-100 px-4 py-3">
            {{ $this->parties->links() }}
        </div>
    </section>
</div>
