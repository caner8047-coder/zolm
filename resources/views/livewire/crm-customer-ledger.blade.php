@php
    $formatMoney = fn ($value) => '₺' . number_format((float) $value, 2, ',', '.');
    $formatSignedMoney = fn ($value) => ((float) $value >= 0 ? '+' : '-') . '₺' . number_format(abs((float) $value), 2, ',', '.');
    $formatCount = fn ($value) => number_format((float) $value, 0, ',', '.');
    $statusTone = fn (string $tone) => match ($tone) {
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-700',
        'danger' => 'border-rose-200 bg-rose-50 text-rose-700',
        default => 'border-slate-200 bg-slate-50 text-slate-600',
    };
    $profitTone = fn (string $tone) => match ($tone) {
        'success' => 'text-emerald-700',
        'warning' => 'text-amber-700',
        'danger' => 'text-rose-700',
        default => 'text-slate-700',
    };
    $sortIcon = function (string $columnKey) use ($sortableColumns, $sortField, $sortDirection) {
        $dbColumn = $sortableColumns[$columnKey] ?? null;
        if (!$dbColumn) {
            return '';
        }

        return $sortField === $dbColumn
            ? ($sortDirection === 'asc' ? '▲' : '▼')
            : '⇅';
    };
    $formQuantity = max(0, (float) ($entryForm['quantity'] ?? 0));
    $formUnitPrice = max(0, (float) ($entryForm['unit_price'] ?? 0));
    $formDiscount = max(0, (float) ($entryForm['discount_amount'] ?? 0));
    $formGross = round($formQuantity * $formUnitPrice, 2);
    $formCommission = filled($entryForm['commission_amount'] ?? null)
        ? (float) $entryForm['commission_amount']
        : round(max(0, $formGross - $formDiscount) * max(0, (float) ($entryForm['commission_rate'] ?? 0)) / 100, 2);
    $formCargo = max(0, (float) ($entryForm['cargo_amount'] ?? 0));
    $formCost = max(0, (float) ($entryForm['cost_amount'] ?? 0));
    $formNet = round(max(0, $formGross - $formDiscount) - $formCommission - $formCargo, 2);
    $formProfit = round($formNet - $formCost, 2);
@endphp

<div class="w-full space-y-4 lg:space-y-6">
    @once
        <style>
            .crm-ledger-table {
                table-layout: fixed;
                width: 100%;
                min-width: 1180px;
            }

            .crm-ledger-table th,
            .crm-ledger-table td {
                overflow: hidden;
                text-overflow: ellipsis;
                vertical-align: top;
            }

            .crm-ledger-sortable {
                cursor: pointer;
                user-select: none;
            }

            .crm-ledger-sortable:hover {
                background: rgba(248, 250, 252, 0.95);
            }

            .crm-ledger-resize-handle {
                position: absolute;
                inset-block: 0;
                right: 0;
                width: 4px;
                cursor: col-resize;
                transition: background 0.15s;
            }

            .crm-ledger-resize-handle:hover,
            .crm-ledger-resize-handle.active {
                background: #0f172a;
            }
        </style>
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('crmLedgerColumnResize', () => ({
                    resizing: false,
                    startX: 0,
                    startWidth: 0,
                    currentTh: null,
                    handle: null,
                    startResize(event, th) {
                        this.resizing = true;
                        this.startX = event.pageX;
                        this.startWidth = th.offsetWidth;
                        this.currentTh = th;
                        this.handle = event.target;
                        this.handle.classList.add('active');

                        const onMouseMove = (moveEvent) => {
                            if (!this.resizing) return;

                            const newWidth = Math.max(96, this.startWidth + (moveEvent.pageX - this.startX));
                            this.currentTh.style.width = newWidth + 'px';
                            this.currentTh.style.minWidth = newWidth + 'px';
                        };

                        const onMouseUp = () => {
                            this.resizing = false;
                            if (this.handle) this.handle.classList.remove('active');
                            document.removeEventListener('mousemove', onMouseMove);
                            document.removeEventListener('mouseup', onMouseUp);
                        };

                        document.addEventListener('mousemove', onMouseMove);
                        document.addEventListener('mouseup', onMouseUp);
                    },
                }));
            });
        </script>
    @endonce

    @if($ledgerMessage !== '')
        <div class="rounded-[8px] border p-4 text-sm {{ $ledgerMessageTone === 'warning' ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800' }}">
            {{ $ledgerMessage }}
        </div>
    @endif

    @unless($ledgerReady)
        <section class="rounded-[10px] border border-amber-200 bg-amber-50 p-4 shadow-sm lg:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-amber-700">CRM Cari Kurulum</p>
                    <h1 class="mt-2 text-xl font-semibold text-amber-950 lg:text-2xl">Müşteri cari tabloları hazır değil</h1>
                    <p class="mt-2 text-sm text-amber-800">Migration çalıştıktan sonra müşteri alışverişleri, tarife ve komisyon bilgileri CRM defterinde izlenecek.</p>
                </div>
                <div class="w-full rounded-[8px] border border-amber-200 bg-white px-4 py-3 text-sm text-amber-900 sm:w-auto">
                    <p class="font-mono">php artisan migrate</p>
                    <p class="mt-1 font-mono">php artisan crm:project --source=orders</p>
                </div>
            </div>
        </section>
    @else
        <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
            <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1.05fr)_minmax(360px,0.95fr)]">
                <div class="min-w-0">
                    <div class="inline-flex items-center rounded-[6px] border border-slate-200 bg-slate-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">
                        CRM Alt Modül
                    </div>
                    <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div class="min-w-0">
                            <h1 class="text-xl font-semibold tracking-tight text-slate-950 lg:text-2xl">Müşteri Cari Defteri</h1>
                            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">
                                Müşteri bazlı alışveriş, platform, reçete, tarife, komisyon ve kârlılık hareketleri tek cari akışta tutulur.
                            </p>
                        </div>
                        <div class="flex flex-col gap-2 sm:flex-row">
                            <button type="button"
                                    wire:click="syncFromOrders"
                                    wire:loading.attr="disabled"
                                    wire:target="syncFromOrders"
                                    class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 disabled:opacity-60 sm:w-auto sm:py-2">
                                <svg class="h-4 w-4 text-slate-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0A8.003 8.003 0 018.064 13m11.355 2H15" />
                                </svg>
                                <span wire:loading.remove wire:target="syncFromOrders">Siparişlerden Güncelle</span>
                                <span wire:loading wire:target="syncFromOrders">İşleniyor...</span>
                            </button>
                            <button type="button"
                                    wire:click="exportLedger"
                                    wire:loading.attr="disabled"
                                    wire:target="exportLedger"
                                    class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-60 sm:w-auto sm:py-2">
                                <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9" d="M12 10v6m0 0l-3-3m3 3l3-3M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 9l5-5 5 5" />
                                </svg>
                                <span wire:loading.remove wire:target="exportLedger">Excel</span>
                                <span wire:loading wire:target="exportLedger">Hazırlanıyor...</span>
                            </button>
                            <a href="{{ route('crm.workspace') }}"
                               class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 sm:w-auto sm:py-2">
                                CRM 360
                            </a>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 lg:grid-cols-4 xl:grid-cols-2">
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Hareket</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-950">{{ $formatCount($stats['entries']) }}</p>
                    </div>
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Müşteri</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-950">{{ $formatCount($stats['customers']) }}</p>
                    </div>
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Komisyon</p>
                        <p class="mt-2 truncate text-xl font-semibold text-slate-950">{{ $formatMoney($stats['commission']) }}</p>
                        <p class="mt-1 text-[11px] text-slate-500">%{{ number_format((float) $stats['average_commission_rate'], 2, ',', '.') }} ort.</p>
                    </div>
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Net Kâr</p>
                        <p class="mt-2 truncate text-xl font-semibold {{ (float) $stats['profit'] < 0 ? 'text-rose-600' : 'text-emerald-700' }}">{{ $formatSignedMoney($stats['profit']) }}</p>
                        <p class="mt-1 text-[11px] text-slate-500">{{ $formatCount($stats['negative']) }} zararlı hareket</p>
                    </div>
                </div>
            </div>
        </section>

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_400px]">
            <section class="min-w-0 rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Cari Ledger</p>
                        <h2 class="mt-1 text-lg font-semibold text-slate-950">Müşteri alışveriş hareketleri</h2>
                        @if(!empty($activeFilters))
                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach($activeFilters as $filter)
                                    <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs text-slate-600">{{ $filter }}</span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                        @include('livewire.partials.mp-column-toggle', ['columnDefs' => $columnDefs, 'visibleColumns' => $visibleColumns])
                        <button type="button"
                                wire:click="clearFilters"
                                class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 sm:w-auto sm:py-2">
                            Filtreleri Temizle
                        </button>
                    </div>
                </div>

                <div class="mt-4 rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-6">
                        <div class="xl:col-span-2">
                            <label class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Arama</label>
                            <input type="search"
                                   wire:model.live.debounce.350ms="search"
                                   placeholder="Müşteri, ürün, sipariş, stok kodu..."
                                   class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none sm:py-2 sm:text-sm">
                        </div>
                        <div>
                            <label class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Platform</label>
                            <select wire:model.live="platformFilter"
                                    class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none sm:py-2 sm:text-sm">
                                <option value="">Tümü</option>
                                @foreach($platforms as $platform)
                                    <option value="{{ $platform }}">{{ $platform }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Mağaza</label>
                            <select wire:model.live="storeFilter"
                                    class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none sm:py-2 sm:text-sm">
                                <option value="">Tümü</option>
                                @foreach($stores as $store)
                                    <option value="{{ $store->id }}">{{ $store->store_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Durum</label>
                            <select wire:model.live="statusFilter"
                                    class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none sm:py-2 sm:text-sm">
                                <option value="">Tümü</option>
                                @foreach($this->statusOptions() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Tarih</label>
                            <select wire:model.live="dateFilter"
                                    class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none sm:py-2 sm:text-sm">
                                @foreach($this->dateOptions() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-3">
                    <button type="button" wire:click="$set('marginFilter', 'negative')" class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-left text-xs text-slate-600 transition hover:bg-slate-50">
                        <span class="block font-semibold text-rose-600">{{ $formatCount($stats['negative']) }}</span>
                        Zararlı hareket
                    </button>
                    <button type="button" wire:click="$set('marginFilter', 'low')" class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-left text-xs text-slate-600 transition hover:bg-slate-50">
                        <span class="block font-semibold text-slate-950">{{ $formatMoney($stats['net']) }}</span>
                        Net tahsilat
                    </button>
                    <button type="button" wire:click="$set('marginFilter', 'healthy')" class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-left text-xs text-slate-600 transition hover:bg-slate-50">
                        <span class="block font-semibold text-emerald-700">{{ $formatMoney($stats['gross']) }}</span>
                        Toplam ciro
                    </button>
                </div>

                <div class="mt-4 space-y-3 md:hidden">
                    @if($entries->count() > 0)
                        @foreach($entries as $entry)
                            <article wire:key="crm-ledger-mobile-{{ $entry->id }}"
                                     class="rounded-[8px] border border-slate-200 bg-white p-3 shadow-sm">
                                <button type="button" wire:click="selectContact({{ $entry->contact_id }})" class="w-full text-left">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold text-slate-950">{{ $entry->contact?->display_name ?: 'Müşteri yok' }}</p>
                                            <p class="mt-1 truncate text-xs text-slate-500">{{ $entry->product_name }}</p>
                                        </div>
                                        <span class="rounded-[6px] border px-2 py-0.5 text-[10px] font-medium {{ $statusTone($entry->statusTone()) }}">
                                            {{ $entry->statusLabel() }}
                                        </span>
                                    </div>
                                    <div class="mt-3 grid grid-cols-3 gap-2 text-xs">
                                        <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-2 py-1.5">
                                            <p class="text-slate-400">Tutar</p>
                                            <p class="font-semibold text-slate-950">{{ $formatMoney($entry->gross_amount) }}</p>
                                        </div>
                                        <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-2 py-1.5">
                                            <p class="text-slate-400">Kom.</p>
                                            <p class="font-semibold text-slate-950">%{{ number_format((float) $entry->commission_rate, 2, ',', '.') }}</p>
                                        </div>
                                        <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-2 py-1.5">
                                            <p class="text-slate-400">Kâr</p>
                                            <p class="font-semibold {{ $profitTone($entry->profitTone()) }}">{{ $formatSignedMoney($entry->profit_amount) }}</p>
                                        </div>
                                    </div>
                                    <p class="mt-3 truncate text-xs text-slate-500">{{ $entry->platform ?: 'Platform yok' }} · {{ $entry->tariff_name ?: 'Tarife yok' }}</p>
                                </button>
                                @php($entryAction = $this->sourceActionForEntry($entry))
                                @if($entry->source_type === 'manual' || $entryAction)
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @if($entry->source_type === 'manual')
                                            <button type="button"
                                                    wire:click="editEntry({{ $entry->id }})"
                                                    class="inline-flex min-h-[36px] items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50">
                                                Düzenle
                                            </button>
                                            @if($entry->status !== 'cancelled')
                                                <button type="button"
                                                        wire:click="voidManualEntry({{ $entry->id }})"
                                                        wire:confirm="Bu manuel cari hareketi iptal edilsin mi?"
                                                        class="inline-flex min-h-[36px] items-center justify-center rounded-[6px] border border-rose-200 bg-white px-3 py-2 text-xs font-medium text-rose-700 transition hover:bg-rose-50">
                                                    İptal
                                                </button>
                                            @endif
                                        @endif
                                        @if($entryAction)
                                            <a href="{{ $entryAction['url'] }}"
                                               class="inline-flex min-h-[36px] items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50">
                                                {{ $entry->source_type === 'manual' ? '360' : $entryAction['label'] }}
                                            </a>
                                        @endif
                                    </div>
                                @endif
                            </article>
                        @endforeach
                    @else
                        <div class="rounded-[8px] border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                            Cari hareket bulunamadı.
                        </div>
                    @endif
                </div>

                <div class="mt-4 hidden overflow-x-auto rounded-lg border border-slate-200 bg-white md:block" x-data="crmLedgerColumnResize()">
                    <table class="crm-ledger-table divide-y divide-slate-200">
                        <thead class="bg-slate-50/80">
                            <tr>
                                @foreach($columnDefs as $columnKey => $columnLabel)
                                    @if(in_array($columnKey, $visibleColumns, true))
                                        <th class="relative px-3 py-3 text-left text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 {{ isset($sortableColumns[$columnKey]) ? 'crm-ledger-sortable' : '' }}"
                                            @if(isset($sortableColumns[$columnKey])) wire:click="sortTable('{{ $columnKey }}')" @endif>
                                            <span>{{ $columnLabel }}</span>
                                            @if(isset($sortableColumns[$columnKey]))
                                                <span class="ml-1 text-slate-300">{{ $sortIcon($columnKey) }}</span>
                                            @endif
                                            <span class="crm-ledger-resize-handle" @mousedown.prevent.stop="startResize($event, $el.closest('th'))"></span>
                                        </th>
                                    @endif
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @if($entries->count() > 0)
                                @foreach($entries as $entry)
                                    <tr wire:key="crm-ledger-entry-{{ $entry->id }}" class="hover:bg-slate-50/60">
                                        @if(in_array('musteri', $visibleColumns, true))
                                            <td class="px-3 py-4">
                                                <button type="button" wire:click="selectContact({{ $entry->contact_id }})" class="block max-w-full text-left">
                                                    <span class="block truncate text-sm font-semibold text-slate-950">{{ $entry->contact?->display_name ?: 'Müşteri yok' }}</span>
                                                    <span class="mt-1 block truncate text-xs text-slate-500">{{ $entry->contact?->primary_phone ?: $entry->contact?->primary_email ?: 'İletişim yok' }}</span>
                                                </button>
                                            </td>
                                        @endif
                                        @if(in_array('urun', $visibleColumns, true))
                                            <td class="px-3 py-4">
                                                <p class="truncate text-sm font-semibold text-slate-950">{{ $entry->product_name }}</p>
                                                <p class="mt-1 truncate text-xs text-slate-500">{{ $entry->stock_code ?: $entry->barcode ?: 'Kod yok' }}</p>
                                            </td>
                                        @endif
                                        @if(in_array('platform', $visibleColumns, true))
                                            <td class="px-3 py-4">
                                                <span class="inline-flex rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-0.5 text-[10px] font-medium text-slate-600">{{ $entry->platform ?: 'Manuel' }}</span>
                                                <p class="mt-2 truncate text-xs text-slate-500">{{ $entry->store?->store_name ?: $entry->sourceLabel() }}</p>
                                            </td>
                                        @endif
                                        @if(in_array('tarife', $visibleColumns, true))
                                            <td class="px-3 py-4">
                                                <p class="truncate text-sm font-medium text-slate-800">{{ $entry->tariff_name ?: 'Tarife yok' }}</p>
                                                <p class="mt-1 truncate text-xs text-slate-500">{{ $entry->recipe_name ? $entry->recipe_name . ' ' . $entry->recipe_version : 'Reçete yok' }}</p>
                                            </td>
                                        @endif
                                        @if(in_array('tarih', $visibleColumns, true))
                                            <td class="px-3 py-4 text-sm text-slate-700">
                                                <p class="font-medium text-slate-950">{{ $entry->purchased_at?->format('d.m.Y') ?: '-' }}</p>
                                                <p class="mt-1 text-xs text-slate-500">{{ $entry->marketplace_order_number ?: 'Sipariş no yok' }}</p>
                                            </td>
                                        @endif
                                        @if(in_array('tutar', $visibleColumns, true))
                                            <td class="px-3 py-4 text-sm">
                                                <p class="font-semibold text-slate-950">{{ $formatMoney($entry->gross_amount) }}</p>
                                                <p class="mt-1 text-xs text-slate-500">{{ number_format((float) $entry->quantity, 2, ',', '.') }} adet</p>
                                            </td>
                                        @endif
                                        @if(in_array('komisyon', $visibleColumns, true))
                                            <td class="px-3 py-4 text-sm">
                                                <p class="font-semibold text-slate-950">{{ $formatMoney($entry->commission_amount) }}</p>
                                                <p class="mt-1 text-xs text-slate-500">%{{ number_format((float) $entry->commission_rate, 2, ',', '.') }}</p>
                                            </td>
                                        @endif
                                        @if(in_array('net', $visibleColumns, true))
                                            <td class="px-3 py-4 text-sm">
                                                <p class="font-semibold text-slate-950">{{ $formatMoney($entry->net_amount) }}</p>
                                                <p class="mt-1 text-xs text-slate-500">Maliyet {{ $formatMoney($entry->cost_amount) }}</p>
                                            </td>
                                        @endif
                                        @if(in_array('kar', $visibleColumns, true))
                                            <td class="px-3 py-4 text-sm">
                                                <p class="font-semibold {{ $profitTone($entry->profitTone()) }}">{{ $formatSignedMoney($entry->profit_amount) }}</p>
                                                <p class="mt-1 text-xs text-slate-500">{{ $entry->marginPercent() !== null ? '%' . number_format($entry->marginPercent(), 1, ',', '.') : 'Marj yok' }}</p>
                                            </td>
                                        @endif
                                        @if(in_array('durum', $visibleColumns, true))
                                            <td class="px-3 py-4">
                                                <span class="inline-flex rounded-[6px] border px-2 py-0.5 text-[10px] font-medium {{ $statusTone($entry->statusTone()) }}">{{ $entry->statusLabel() }}</span>
                                            </td>
                                        @endif
                                        @if(in_array('aksiyon', $visibleColumns, true))
                                            <td class="px-3 py-4">
                                                @php($entryAction = $this->sourceActionForEntry($entry))
                                                @if($entry->source_type === 'manual')
                                                    <div class="flex flex-wrap gap-1.5">
                                                        <button type="button"
                                                                wire:click="editEntry({{ $entry->id }})"
                                                                class="inline-flex min-h-[34px] items-center justify-center rounded-[6px] border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50">
                                                            Düzenle
                                                        </button>
                                                        @if($entry->status !== 'cancelled')
                                                            <button type="button"
                                                                    wire:click="voidManualEntry({{ $entry->id }})"
                                                                    wire:confirm="Bu manuel cari hareketi iptal edilsin mi?"
                                                                    class="inline-flex min-h-[34px] items-center justify-center rounded-[6px] border border-rose-200 bg-white px-2.5 py-1.5 text-xs font-medium text-rose-700 transition hover:bg-rose-50">
                                                                İptal
                                                            </button>
                                                        @endif
                                                        @if($entryAction)
                                                            <a href="{{ $entryAction['url'] }}"
                                                               class="inline-flex min-h-[34px] items-center justify-center rounded-[6px] border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50">
                                                                360
                                                            </a>
                                                        @endif
                                                    </div>
                                                @elseif($entryAction)
                                                    <a href="{{ $entryAction['url'] }}"
                                                       class="inline-flex min-h-[36px] items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50">
                                                        {{ $entryAction['label'] }}
                                                    </a>
                                                @else
                                                    <span class="text-xs text-slate-400">Aksiyon yok</span>
                                                @endif
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="{{ count($visibleColumns) }}" class="px-4 py-12 text-center text-sm text-slate-500">
                                        Müşteri cari hareketi bulunamadı.
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $entries->links() }}
                </div>
            </section>

            <aside class="min-w-0 space-y-4">
                <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">{{ $editingEntryId ? 'Hareket Düzenle' : 'Yeni Hareket' }}</p>
                            <h2 class="mt-1 text-lg font-semibold text-slate-950">{{ $editingEntryId ? 'Cari kayıt düzenle' : 'Cari kayıt' }}</h2>
                        </div>
                        <span class="rounded-[6px] border {{ $editingEntryId ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-slate-200 bg-slate-50 text-slate-600' }} px-2 py-1 text-xs font-medium">
                            {{ $editingEntryId ? 'Düzenleme' : 'Manuel' }}
                        </span>
                    </div>

                    <div class="mt-4 space-y-3">
                        <div>
                            <label class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Kayıtlı müşteri</label>
                            <select wire:model.defer="entryForm.contact_id"
                                    class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none sm:py-2 sm:text-sm">
                                <option value="">Yeni müşteri</option>
                                @foreach($contacts as $contact)
                                    <option value="{{ $contact->id }}">{{ $contact->display_name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Müşteri adı</label>
                                <input type="text" wire:model.defer="entryForm.customer_name"
                                       class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none sm:py-2 sm:text-sm">
                                @error('entryForm.customer_name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Telefon</label>
                                <input type="text" wire:model.defer="entryForm.customer_phone"
                                       class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none sm:py-2 sm:text-sm">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Mağaza</label>
                                <select wire:model.defer="entryForm.store_id"
                                        class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none sm:py-2 sm:text-sm">
                                    <option value="">Mağaza yok</option>
                                    @foreach($stores as $store)
                                        <option value="{{ $store->id }}">{{ $store->store_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Platform</label>
                                <input type="text" wire:model.defer="entryForm.platform" placeholder="Trendyol, Hepsiburada..."
                                       class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none sm:py-2 sm:text-sm">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Ürün</label>
                                <input type="text" wire:model.defer="entryForm.product_name"
                                       class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none sm:py-2 sm:text-sm">
                                @error('entryForm.product_name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Sipariş no</label>
                                <input type="text" wire:model.defer="entryForm.marketplace_order_number"
                                       class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none sm:py-2 sm:text-sm">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Reçete</label>
                                <select wire:model.defer="entryForm.recipe_id"
                                        class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none sm:py-2 sm:text-sm">
                                    <option value="">Reçete yok</option>
                                    @foreach($recipes as $recipe)
                                        <option value="{{ $recipe->id }}">{{ $recipe->name }} {{ $recipe->version }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Tarife</label>
                                <input type="text" wire:model.defer="entryForm.tariff_name" placeholder="Tarife 2, Komisyon %..."
                                       class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none sm:py-2 sm:text-sm">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div>
                                <label class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Adet</label>
                                <input type="number" min="0.01" step="0.01" wire:model.live.debounce.300ms="entryForm.quantity"
                                       class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none sm:py-2 sm:text-sm">
                            </div>
                            <div>
                                <label class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Birim fiyat</label>
                                <input type="number" min="0" step="0.01" wire:model.live.debounce.300ms="entryForm.unit_price"
                                       class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none sm:py-2 sm:text-sm">
                            </div>
                            <div>
                                <label class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Kom. %</label>
                                <input type="number" min="0" max="100" step="0.01" wire:model.live.debounce.300ms="entryForm.commission_rate"
                                       class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none sm:py-2 sm:text-sm">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div>
                                <label class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Kargo</label>
                                <input type="number" min="0" step="0.01" wire:model.live.debounce.300ms="entryForm.cargo_amount"
                                       class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none sm:py-2 sm:text-sm">
                            </div>
                            <div>
                                <label class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Maliyet</label>
                                <input type="number" min="0" step="0.01" wire:model.live.debounce.300ms="entryForm.cost_amount"
                                       class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none sm:py-2 sm:text-sm">
                            </div>
                            <div>
                                <label class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Durum</label>
                                <select wire:model.defer="entryForm.status"
                                        class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none sm:py-2 sm:text-sm">
                                    @foreach($this->statusOptions() as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Satış tarihi</label>
                            <input type="datetime-local" wire:model.defer="entryForm.purchased_at"
                                   class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none sm:py-2 sm:text-sm">
                        </div>

                        <textarea wire:model.defer="entryForm.notes" rows="2" placeholder="Not"
                                  class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none sm:text-sm"></textarea>

                        <div class="grid grid-cols-2 gap-2">
                            <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-2 py-2">
                                <p class="text-[10px] text-slate-500">Brüt</p>
                                <p class="mt-1 text-sm font-semibold text-slate-950">{{ $formatMoney($formGross) }}</p>
                            </div>
                            <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-2 py-2">
                                <p class="text-[10px] text-slate-500">Komisyon</p>
                                <p class="mt-1 text-sm font-semibold text-slate-950">{{ $formatMoney($formCommission) }}</p>
                            </div>
                            <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-2 py-2">
                                <p class="text-[10px] text-slate-500">Net</p>
                                <p class="mt-1 text-sm font-semibold text-slate-950">{{ $formatMoney($formNet) }}</p>
                            </div>
                            <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-2 py-2">
                                <p class="text-[10px] text-slate-500">Kâr</p>
                                <p class="mt-1 text-sm font-semibold {{ $formProfit < 0 ? 'text-rose-600' : 'text-emerald-700' }}">{{ $formatSignedMoney($formProfit) }}</p>
                            </div>
                        </div>

                        <div class="flex flex-col gap-2 sm:flex-row">
                            <button type="button"
                                    wire:click="saveManualEntry"
                                    wire:loading.attr="disabled"
                                    wire:target="saveManualEntry"
                                    class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 disabled:opacity-60 sm:py-2">
                                <span wire:loading.remove wire:target="saveManualEntry">{{ $editingEntryId ? 'Cari Hareketi Güncelle' : 'Cari Hareketi Kaydet' }}</span>
                                <span wire:loading wire:target="saveManualEntry">Kaydediliyor...</span>
                            </button>
                            @if($editingEntryId)
                                <button type="button"
                                        wire:click="cancelEdit"
                                        class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 sm:w-auto sm:py-2">
                                    Vazgeç
                                </button>
                            @endif
                        </div>
                    </div>
                </section>

                <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-5">
                    @if($selectedContact)
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Seçili Müşteri</p>
                                <h2 class="mt-1 truncate text-lg font-semibold text-slate-950">{{ $selectedContact->display_name }}</h2>
                                <p class="mt-1 truncate text-sm text-slate-500">{{ $selectedContact->primary_phone ?: $selectedContact->primary_email ?: 'İletişim bilgisi yok' }}</p>
                            </div>
                            <div class="flex shrink-0 gap-2">
                                <button type="button"
                                        wire:click="clearContactFilter"
                                        class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50">
                                    Tümü
                                </button>
                                <a href="{{ route('crm.workspace', ['contact' => $selectedContact->id]) }}"
                                   class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50">
                                    360
                                </a>
                            </div>
                        </div>

                        @if(config('marketplace.features.party_core_enabled', false) && config('marketplace.features.accounting_enabled', false) && !empty($partySummary))
                            <div class="mt-4 rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                                @if($partySummary['has_party'])
                                    <div class="flex items-center justify-between gap-3">
                                        <p class="text-xs font-semibold text-slate-900">Cari Açık Hesap Özeti</p>
                                        <a href="{{ route('accounting.party-ledger', ['party' => $partySummary['party_id']]) }}"
                                           class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-600 transition hover:bg-slate-50">
                                            Cari Aç
                                        </a>
                                    </div>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <span class="inline-flex items-center rounded-full border border-slate-200 bg-white px-2 py-0.5 text-[10px] font-medium text-slate-700">
                                            {{ $partySummary['display_name'] }}
                                        </span>
                                        @foreach($partySummary['roles'] as $role)
                                            <span class="inline-flex items-center rounded-full border border-slate-200 bg-white px-2 py-0.5 text-[10px] font-medium text-slate-600">
                                                {{ $role }}
                                            </span>
                                        @endforeach
                                    </div>
                                    <div class="mt-3 grid grid-cols-3 gap-2">
                                        <div class="rounded-[6px] border border-slate-200 bg-white px-2 py-2">
                                            <p class="text-[10px] text-slate-500">Borç</p>
                                            <p class="mt-1 text-xs font-semibold text-rose-700">₺{{ number_format($partySummary['debit'], 2, ',', '.') }}</p>
                                        </div>
                                        <div class="rounded-[6px] border border-slate-200 bg-white px-2 py-2">
                                            <p class="text-[10px] text-slate-500">Alacak</p>
                                            <p class="mt-1 text-xs font-semibold text-emerald-700">₺{{ number_format($partySummary['credit'], 2, ',', '.') }}</p>
                                        </div>
                                        <div class="rounded-[6px] border border-slate-200 bg-white px-2 py-2">
                                            <p class="text-[10px] text-slate-500">Bakiye</p>
                                            <p class="mt-1 text-xs font-semibold {{ $partySummary['balance'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                                                {{ $partySummary['balance'] >= 0 ? '+' : '-' }}₺{{ number_format(abs($partySummary['balance']), 2, ',', '.') }}
                                            </p>
                                        </div>
                                    </div>
                                    <p class="mt-2 text-[10px] text-slate-400">Kayıt: {{ $partySummary['created_at'] }}</p>
                                @else
                                    <p class="text-xs font-semibold text-slate-900">Cari Açık Hesap Özeti</p>
                                    <p class="mt-2 text-xs text-slate-500">Party bağlı değil</p>
                                    <p class="mt-1 text-[10px] text-slate-400">Backfill çalıştırın veya manuel party bağlantısı kurun.</p>
                                @endif
                            </div>
                        @endif

                        <div class="mt-4 grid grid-cols-3 gap-2">
                            <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-2 py-2">
                                <p class="text-[10px] text-slate-500">Hareket</p>
                                <p class="mt-1 text-sm font-semibold text-slate-950">{{ $formatCount($selectedContactStats['entries']) }}</p>
                            </div>
                            <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-2 py-2">
                                <p class="text-[10px] text-slate-500">Ciro</p>
                                <p class="mt-1 truncate text-sm font-semibold text-slate-950">{{ $formatMoney($selectedContactStats['gross']) }}</p>
                            </div>
                            <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-2 py-2">
                                <p class="text-[10px] text-slate-500">Kâr</p>
                                <p class="mt-1 truncate text-sm font-semibold {{ (float) $selectedContactStats['profit'] < 0 ? 'text-rose-600' : 'text-emerald-700' }}">{{ $formatSignedMoney($selectedContactStats['profit']) }}</p>
                            </div>
                        </div>

                        <div class="mt-4 space-y-2">
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-xs font-semibold text-slate-900">Son cari hareketler</p>
                                <span class="text-xs text-slate-500">{{ $selectedEntries->count() }} kayıt</span>
                            </div>
                            @if($selectedEntries->isNotEmpty())
                                @foreach($selectedEntries as $entry)
                                    <div class="rounded-[8px] border border-slate-200 bg-white p-3">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-semibold text-slate-950">{{ $entry->product_name }}</p>
                                                <p class="mt-1 truncate text-xs text-slate-500">{{ $entry->platform ?: 'Platform yok' }} · {{ $entry->purchased_at?->format('d.m.Y') ?: '-' }}</p>
                                            </div>
                                            <p class="shrink-0 text-sm font-semibold {{ $profitTone($entry->profitTone()) }}">{{ $formatSignedMoney($entry->profit_amount) }}</p>
                                        </div>
                                        <div class="mt-2 flex flex-wrap gap-1.5 text-[11px] text-slate-500">
                                            <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-0.5">{{ $formatMoney($entry->gross_amount) }}</span>
                                            <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-0.5">%{{ number_format((float) $entry->commission_rate, 2, ',', '.') }}</span>
                                            <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-0.5">{{ $entry->tariff_name ?: 'Tarife yok' }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            @else
                                <div class="rounded-[8px] border border-dashed border-slate-300 bg-slate-50 px-3 py-4 text-xs text-slate-500">
                                    Seçili müşteri için cari hareket yok.
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="rounded-[8px] border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                            Bir müşteri seçildiğinde cari özeti burada görünür.
                        </div>
                    @endif
                </section>
            </aside>
        </div>
    @endunless
</div>
