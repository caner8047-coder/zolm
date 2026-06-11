@php
    $totalStats = $this->totalStats;
    $cargoCompanies = $this->cargoCompanies;
    $marketplaces = $this->marketplaces;
    $stores = $this->stores;
    $columnDefs = \App\Livewire\Cargo\ReportList::$allColumnDefs;
    $sortableColumns = \App\Livewire\Cargo\ReportList::$sortableColumns;

    $messageClasses = match ($messageType) {
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
        'error' => 'border-rose-200 bg-rose-50 text-rose-800',
        default => 'border-sky-200 bg-sky-50 text-sky-800',
    };

    $formatCount = fn ($value) => number_format((float) $value, 0, ',', '.');
    $formatMoney = fn ($value) => '₺' . number_format((float) $value, 0, ',', '.');
    $formatSignedMoney = fn ($value) => (($value > 0) ? '+₺' : (($value < 0) ? '-₺' : '₺')) . number_format(abs((float) $value), 0, ',', '.');
    $sortIcon = function (string $columnKey) use ($sortableColumns, $sortField, $sortDirection) {
        $dbColumn = $sortableColumns[$columnKey] ?? null;
        if (!$dbColumn) {
            return '';
        }

        return $sortField === $dbColumn
            ? ($sortDirection === 'asc' ? '▲' : '▼')
            : '⇅';
    };
    $recordTypeLabels = [
        'all' => 'Tümü',
        'siparis' => 'Sipariş',
        'iade' => 'İade / değişim',
        'parca' => 'Parça gönderisi',
    ];
    $activeFilters = array_values(array_filter([
        $filterCompany !== '' ? 'Firma: ' . $filterCompany : null,
        $filterMarketplace !== '' ? 'Pazaryeri: ' . $filterMarketplace : null,
        $filterStore !== '' ? 'Mağaza: ' . $filterStore : null,
        ($filterRecordType ?? 'all') !== 'all' ? 'Kayıt: ' . ($recordTypeLabels[$filterRecordType] ?? $filterRecordType) : null,
    ]));
    $showAdvancedFilters = $filterMarketplace !== '' || $filterStore !== '' || ($filterRecordType ?? 'all') !== 'all';
@endphp

<div class="w-full space-y-6 overflow-hidden">
    @if($message)
        <div class="rounded-2xl border p-4 text-sm shadow-sm {{ $messageClasses }}">
            {{ $message }}
        </div>
    @endif

    <div class="flex flex-col xl:flex-row xl:items-start xl:justify-between gap-4 lg:gap-6">
        <div class="min-w-0 max-w-3xl">
            <h2 class="text-xl lg:text-2xl font-bold text-slate-900">Geçmiş karşılaştırmalar</h2>
            <p class="mt-1 text-sm lg:text-base text-slate-700">
                Raporları tarih ve kanal bazında daraltın; detay, indirme ve silme işlemlerini tek akıştan yönetin.
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4">
        <x-zolm.stat-card variant="orders" label="Toplam rapor" :value="$formatCount($totalStats['total_reports'])" description="Arşivdeki kayıtlı karşılaştırmalar" tone="info">
            <x-slot:icon>
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6M7 4h10a2 2 0 012 2v12a2 2 0 01-2 2H7a2 2 0 01-2-2V6a2 2 0 012-2z" />
                </svg>
            </x-slot:icon>
        </x-zolm.stat-card>
        <x-zolm.stat-card variant="orders" label="Toplam sipariş" :value="$formatCount($totalStats['total_orders'])" description="Arşivde işlenen toplam sipariş" tone="success">
            <x-slot:icon>
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8 5-8-5m16 0l-8-5-8 5m16 0v10l-8 5-8-5V7" />
                </svg>
            </x-slot:icon>
        </x-zolm.stat-card>
        <x-zolm.stat-card variant="orders" label="Toplam hata" :value="$formatCount($totalStats['total_errors'])" description="Arşivden çıkan toplam hata" tone="danger">
            <x-slot:icon>
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M4.93 19h14.14A2 2 0 0020.8 16L13.73 4a2 2 0 00-3.46 0L3.2 16A2 2 0 004.93 19z" />
                </svg>
            </x-slot:icon>
        </x-zolm.stat-card>
        <x-zolm.stat-card variant="orders" label="Net tutar farkı" :value="$formatSignedMoney($totalStats['total_tutar_diff'])" description="Arşiv toplam finansal etki" :tone="$totalStats['total_tutar_diff'] > 0 ? 'danger' : ($totalStats['total_tutar_diff'] < 0 ? 'success' : 'default')">
            <x-slot:icon>
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 17l6-6 4 4 8-8" />
                </svg>
            </x-slot:icon>
        </x-zolm.stat-card>
    </div>

    <x-zolm.section-card variant="orders" padding="p-4 lg:p-5">
        <div class="space-y-4">
            <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-3 lg:gap-4">
                <div>
                    <h3 class="text-base lg:text-lg font-semibold text-slate-900">Arşiv filtreleri</h3>
                    <p class="mt-1 text-sm text-slate-500">
                        Tarih, firma ve kayıt tipine göre arşivi daraltın.
                    </p>
                </div>
                <p class="text-sm text-slate-500">
                    {{ count($activeFilters) > 0 ? implode(' · ', $activeFilters) : 'Tüm firmalar, mağazalar ve kayıt tipleri gösteriliyor.' }}
                    · {{ $filterDate ? \Carbon\Carbon::parse($filterDate)->format('d.m.Y') : '-' }} - {{ $filterDateEnd ? \Carbon\Carbon::parse($filterDateEnd)->format('d.m.Y') : '-' }}
                </p>
            </div>

            <div x-data="{ showAdvanced: @js($showAdvancedFilters) }" class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 lg:gap-4">
                    <div>
                        <label class="block text-xs sm:text-sm font-medium text-slate-500">Başlangıç</label>
                        <input type="date" wire:model.live="filterDate" class="mt-1 min-h-[44px] w-full rounded-md border border-slate-300 px-3 py-2 text-base sm:text-sm text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                    </div>
                    <div>
                        <label class="block text-xs sm:text-sm font-medium text-slate-500">Bitiş</label>
                        <input type="date" wire:model.live="filterDateEnd" class="mt-1 min-h-[44px] w-full rounded-md border border-slate-300 px-3 py-2 text-base sm:text-sm text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                    </div>
                    <div>
                        <label class="block text-xs sm:text-sm font-medium text-slate-500">Kargo firması</label>
                        <select wire:model.live="filterCompany" class="mt-1 min-h-[44px] w-full rounded-md border border-slate-300 px-3 py-2 text-base sm:text-sm text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                            <option value="">Tümü</option>
                            @foreach($cargoCompanies as $company)
                                <option value="{{ $company }}">{{ $company }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="button" @click="showAdvanced = !showAdvanced" class="min-h-[44px] w-full rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                            <span x-text="showAdvanced ? 'Gelişmiş filtreleri gizle' : 'Gelişmiş filtreler'"></span>
                        </button>
                    </div>
                </div>

                <div x-show="showAdvanced" x-cloak x-transition.opacity.duration.150ms class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3 lg:gap-4">
                        <div>
                            <label class="block text-xs sm:text-sm font-medium text-slate-500">Pazaryeri</label>
                            <select wire:model.live="filterMarketplace" class="mt-1 min-h-[44px] w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-base sm:text-sm text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                <option value="">Tümü</option>
                                @foreach($marketplaces as $marketplace)
                                    <option value="{{ $marketplace }}">{{ $marketplace }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs sm:text-sm font-medium text-slate-500">Mağaza</label>
                            <select wire:model.live="filterStore" class="mt-1 min-h-[44px] w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-base sm:text-sm text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                <option value="">Tümü</option>
                                @foreach($stores as $store)
                                    <option value="{{ $store }}">{{ $store }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs sm:text-sm font-medium text-slate-500">Kayıt tipi</label>
                            <select wire:model.live="filterRecordType" class="mt-1 min-h-[44px] w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-base sm:text-sm text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                <option value="all">Tümü</option>
                                <option value="siparis">Sipariş</option>
                                <option value="iade">İade / değişim</option>
                                <option value="parca">Parça gönderisi</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-zolm.section-card>

    <x-zolm.section-card
        variant="orders"
        eyebrow="Rapor Listesi"
        title="Kayıtlı raporlar"
        description="Detay inceleme, Excel indirme ve silme işlemleri bu listeden yapılır."
        headerPadding="px-4 pt-4 pb-2 lg:px-6 lg:pt-6 lg:pb-2"
        bodyPadding="px-4 pb-4 lg:px-6 lg:pb-6"
    >
        @once
            <style>
                .col-resize-handle { position: absolute; right: 0; top: 0; bottom: 0; width: 4px; cursor: col-resize; background: transparent; z-index: 10; transition: background 0.15s; }
                .col-resize-handle:hover, .col-resize-handle.active { background: #6366f1; }
                .sortable-th { cursor: pointer; user-select: none; position: relative; }
                .sortable-th:hover { background: #f8fafc; }
                #cargoReportsTable .text-xs { font-size: 10px !important; }
                #cargoReportsTable .text-sm { font-size: 12px !important; }
                #cargoReportsTable .text-\[10px\] { font-size: 8px !important; }
                #cargoReportsTable { table-layout: fixed; width: 100%; }
                #cargoReportsTable th, #cargoReportsTable td { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
            </style>
            <script>
                document.addEventListener('alpine:init', () => {
                    Alpine.data('columnResize', () => ({
                        resizing: false,
                        startX: 0,
                        startWidth: 0,
                        currentTh: null,
                        handle: null,
                        startResize(e, th) {
                            this.resizing = true;
                            this.startX = e.pageX;
                            this.currentTh = th;
                            this.startWidth = th.offsetWidth;
                            this.handle = e.target;
                            this.handle.classList.add('active');

                            const onMouseMove = (ev) => {
                                if (!this.resizing) return;
                                const newWidth = Math.max(40, this.startWidth + (ev.pageX - this.startX));
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
                        }
                    }));
                });
            </script>
        @endonce

        @php
            $columnMeta = [
                'date' => ['width' => '92px', 'align' => 'text-left'],
                'name' => ['width' => '220px', 'align' => 'text-left'],
                'company' => ['width' => '96px', 'align' => 'text-left'],
                'orders' => ['width' => '72px', 'align' => 'text-center'],
                'errors' => ['width' => '82px', 'align' => 'text-center'],
                'desi_diff' => ['width' => '100px', 'align' => 'text-right'],
                'amount_diff' => ['width' => '116px', 'align' => 'text-right'],
                'actions' => ['width' => '112px', 'align' => 'text-right'],
            ];
        @endphp

        <div class="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 lg:gap-4">
            <div class="text-xs text-slate-500">{{ count($visibleColumns) }} / {{ count($columnDefs) }} kolon gösteriliyor</div>
            <div x-data="{ open: false }" class="relative">
                <button @click="open = !open" type="button" class="w-full sm:w-auto rounded-lg border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                    Kolonlar
                </button>
                <div x-show="open" @click.outside="open = false" x-transition class="absolute right-0 top-full z-30 mt-2 w-60 rounded-[10px] border border-slate-200 bg-white p-3 shadow-xl">
                    <p class="text-xs font-medium uppercase tracking-[0.16em] text-slate-500">Görünür Kolonlar</p>
                    <div class="mt-3 space-y-1.5">
                        @foreach($columnDefs as $colKey => $colLabel)
                            <label class="flex items-center gap-2 rounded-lg px-2 py-2 text-sm text-slate-700 transition hover:bg-slate-50">
                                <input type="checkbox" wire:click="toggleColumn('{{ $colKey }}')" {{ in_array($colKey, $visibleColumns, true) ? 'checked' : '' }} class="rounded border-slate-300 text-slate-900 shadow-sm focus:ring-slate-200">
                                <span>{{ $colLabel }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white overflow-hidden">
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full" x-data="columnResize()" id="cargoReportsTable">
                        <thead class="bg-slate-50 text-slate-500">
                            <tr>
                            @foreach($columnDefs as $colKey => $colLabel)
                                @if(in_array($colKey, $visibleColumns, true))
                                    @php
                                        $meta = $columnMeta[$colKey];
                                        $isSortable = isset($sortableColumns[$colKey]);
                                        $align = $meta['align'];
                                    @endphp
                                    <th
                                        class="px-2 py-2 {{ $align }} text-xs font-medium uppercase tracking-[0.16em] {{ $isSortable ? 'sortable-th' : '' }}"
                                        style="position: relative; width: {{ $meta['width'] }}; min-width: 40px;"
                                        @if($isSortable) wire:click="sortTable('{{ $colKey }}')" @endif
                                    >
                                        <div class="flex items-center gap-1 {{ $align === 'text-right' ? 'justify-end' : ($align === 'text-center' ? 'justify-center' : '') }}">
                                            <span>{{ $colLabel }}</span>
                                            @if($isSortable)
                                                <span class="text-[10px] {{ ($sortableColumns[$colKey] ?? null) === $sortField ? 'text-slate-700' : 'text-slate-300' }}">{{ $sortIcon($colKey) }}</span>
                                            @endif
                                        </div>
                                        <div class="col-resize-handle" @mousedown.stop.prevent="startResize($event, $el.parentElement)"></div>
                                    </th>
                                @endif
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($this->reports as $report)
                            <tr class="bg-white hover:bg-slate-50 transition">
                                @foreach($columnDefs as $colKey => $colLabel)
                                    @if(in_array($colKey, $visibleColumns, true))
                                        @switch($colKey)
                                            @case('date')
                                                <td class="px-2 py-2 text-sm text-slate-900">{{ $report->report_date->format('d.m.Y') }}</td>
                                                @break
                                            @case('name')
                                                <td class="px-2 py-2 align-top">
                                                    <p class="truncate text-sm font-semibold text-slate-900" title="{{ $report->name }}">{{ $report->name }}</p>
                                                    <p class="mt-1 truncate text-xs text-slate-500">{{ $report->user?->name }}</p>
                                                </td>
                                                @break
                                            @case('company')
                                                <td class="px-2 py-2">
                                                    <x-zolm.status-badge>{{ $report->cargo_company ?? '-' }}</x-zolm.status-badge>
                                                </td>
                                                @break
                                            @case('orders')
                                                <td class="px-1 py-2 text-center text-sm text-slate-900">{{ $formatCount($report->total_orders) }}</td>
                                                @break
                                            @case('errors')
                                                <td class="px-1 py-2 text-center">
                                                    @if($report->error_count > 0)
                                                        <x-zolm.status-badge tone="danger">{{ $formatCount($report->error_count) }}</x-zolm.status-badge>
                                                    @else
                                                        <x-zolm.status-badge tone="success">OK</x-zolm.status-badge>
                                                    @endif
                                                </td>
                                                @break
                                            @case('desi_diff')
                                                <td class="px-1 py-2 text-right text-sm {{ $report->total_desi_diff > 0 ? 'text-rose-600' : ($report->total_desi_diff < 0 ? 'text-emerald-600' : 'text-slate-900') }}">
                                                    {{ $report->total_desi_diff > 0 ? '+' : '' }}{{ number_format($report->total_desi_diff, 0, ',', '.') }}
                                                </td>
                                                @break
                                            @case('amount_diff')
                                                <td class="px-1 py-2 text-right text-sm {{ $report->total_tutar_diff > 0 ? 'text-rose-600' : ($report->total_tutar_diff < 0 ? 'text-emerald-600' : 'text-slate-900') }}">
                                                    {{ $formatSignedMoney($report->total_tutar_diff) }}
                                                </td>
                                                @break
                                            @case('actions')
                                                <td class="px-2 py-2">
                                                    <div class="flex items-center justify-end gap-1.5">
                                                        <button wire:click="viewReport({{ $report->id }})" class="inline-flex h-8 items-center justify-center rounded-lg border border-slate-200 bg-white px-2.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50">İncele</button>
                                                        <button wire:click="downloadReport({{ $report->id }})" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-700 transition hover:bg-emerald-100">
                                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                                        </button>
                                                        <button wire:click="confirmDelete({{ $report->id }})" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-rose-200 bg-rose-50 text-rose-700 transition hover:bg-rose-100">
                                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                        </button>
                                                    </div>
                                                </td>
                                                @break
                                        @endswitch
                                    @endif
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ max(count($visibleColumns), 1) }}" class="px-6 py-12 text-center text-sm text-slate-500">Seçili filtrelerde kayıtlı rapor bulunamadı.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="md:hidden space-y-3 p-4">
                @forelse($this->reports as $report)
                    <div class="rounded-xl border border-slate-200 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                    @if(in_array('date', $visibleColumns, true))
                                        <span>{{ $report->report_date->format('d.m.Y') }}</span>
                                    @endif
                                    @if(in_array('company', $visibleColumns, true))
                                        <x-zolm.status-badge>{{ $report->cargo_company ?? '-' }}</x-zolm.status-badge>
                                    @endif
                                    @if(in_array('errors', $visibleColumns, true))
                                        @if($report->error_count > 0)
                                            <x-zolm.status-badge tone="danger">{{ $formatCount($report->error_count) }} hata</x-zolm.status-badge>
                                        @else
                                            <x-zolm.status-badge tone="success">OK</x-zolm.status-badge>
                                        @endif
                                    @endif
                                </div>
                                @if(in_array('name', $visibleColumns, true))
                                    <p class="mt-2 truncate text-sm font-semibold text-slate-900">{{ $report->name }}</p>
                                @endif
                                <p class="mt-1 text-xs text-slate-500">{{ $report->user?->name }}</p>
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-2">
                            @if(in_array('orders', $visibleColumns, true))
                                <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                                    <span class="text-slate-500">Sipariş</span>
                                    <span class="font-semibold text-slate-900">{{ $formatCount($report->total_orders) }}</span>
                                </div>
                            @endif
                            @if(in_array('desi_diff', $visibleColumns, true))
                                <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                                    <span class="text-slate-500">Desi farkı</span>
                                    <span class="font-semibold {{ $report->total_desi_diff > 0 ? 'text-rose-600' : ($report->total_desi_diff < 0 ? 'text-emerald-600' : 'text-slate-900') }}">{{ $report->total_desi_diff > 0 ? '+' : '' }}{{ number_format($report->total_desi_diff, 0, ',', '.') }}</span>
                                </div>
                            @endif
                            @if(in_array('amount_diff', $visibleColumns, true))
                                <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm sm:col-span-2">
                                    <span class="text-slate-500">Tutar farkı</span>
                                    <span class="font-semibold {{ $report->total_tutar_diff > 0 ? 'text-rose-600' : ($report->total_tutar_diff < 0 ? 'text-emerald-600' : 'text-slate-900') }}">{{ $formatSignedMoney($report->total_tutar_diff) }}</span>
                                </div>
                            @endif
                        </div>

                        @if(in_array('actions', $visibleColumns, true))
                            <div class="mt-4 flex flex-col sm:flex-row gap-2">
                                <button wire:click="viewReport({{ $report->id }})" class="w-full sm:w-auto rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50">İncele</button>
                                <button wire:click="downloadReport({{ $report->id }})" class="w-full sm:w-auto rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 transition hover:bg-emerald-100">Excel İndir</button>
                                <button wire:click="confirmDelete({{ $report->id }})" class="w-full sm:w-auto rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700 transition hover:bg-rose-100">Sil</button>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-8 text-center text-sm text-slate-500">Seçili filtrelerde kayıtlı rapor bulunamadı.</div>
                @endforelse
            </div>

            @if($this->reports->hasPages())
                <div class="border-t border-slate-200 px-4 py-3">
                    {{ $this->reports->links() }}
                </div>
            @endif
        </div>
    </x-zolm.section-card>

    @if($showDetailModal && $viewingReportId)
        @php
            $report = $this->viewingReport;
            $detailSummary = $this->detailSummary;
        @endphp
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
            <div class="flex min-h-screen items-center justify-center px-4 py-6 sm:py-8">
                <button type="button" class="fixed inset-0 bg-slate-900/40" wire:click="closeDetailModal"></button>

                <div class="relative flex w-full max-h-[100dvh] min-h-0 flex-col overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-xl sm:max-h-[90vh] sm:max-w-6xl">
                    <div class="shrink-0 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 border-b border-slate-200 px-4 py-4 lg:px-6">
                        <div>
                            <x-zolm.eyebrow variant="classic">Rapor Detayı</x-zolm.eyebrow>
                            <h3 class="mt-3 text-xl font-bold text-slate-900">{{ $report?->name }}</h3>
                            <p class="mt-2 text-sm text-slate-500">{{ $report?->report_date?->format('d.m.Y') }} · {{ $report?->cargo_company }}</p>
                        </div>
                        <button wire:click="closeDetailModal" class="rounded-lg border border-slate-200 bg-white p-2 text-slate-400 transition hover:text-slate-700">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <div class="shrink-0 border-b border-slate-200 bg-slate-50 px-4 py-4 lg:px-6">
                        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 lg:gap-4">
                            <input
                                wire:model.live.debounce.300ms="itemSearch"
                                type="text"
                                placeholder="Müşteri, takip, stok veya ürün ara..."
                                class="w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200"
                            >
                            <select wire:model.live="itemFilterErrorType" class="w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                <option value="all">Tüm kayıtlar</option>
                                <option value="errors">Sadece hatalar</option>
                                <option value="referans_eksik">Referans eksik</option>
                                <option value="desi_fazla">Desi fazla</option>
                                <option value="tutar_fazla">Tutar fazla</option>
                                <option value="parca_eksik">Parça eksik</option>
                                <option value="parca_fazla">Parça fazla</option>
                                <option value="eslesmedi">Eşleşmedi</option>
                            </select>
                            <select wire:model.live="itemFilterType" class="w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                <option value="all">Tümü</option>
                                <option value="siparis">Siparişler</option>
                                <option value="iade">İadeler</option>
                                <option value="parca">Parça gönderileri</option>
                            </select>
                            <select wire:model.live="itemFilterClaim" class="w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                                <option value="all">Tüm aksiyonlar</option>
                                <option value="claimable">Sadece tazmine uygunlar</option>
                                <option value="with_compensation">Talep açılmışlar</option>
                                <option value="without_compensation">Talep açılmamışlar</option>
                            </select>
                        </div>

                        <div class="mt-4 grid grid-cols-2 xl:grid-cols-4 gap-3 lg:gap-4">
                            <div class="rounded-[10px] border border-slate-200 bg-white px-4 py-3">
                                <p class="text-[10px] uppercase tracking-[0.18em] text-slate-500">Filtreli toplam</p>
                                <p class="mt-2 text-lg font-semibold text-slate-900">{{ number_format($detailSummary['total']) }}</p>
                            </div>
                            <div class="rounded-[10px] border border-slate-200 bg-white px-4 py-3">
                                <p class="text-[10px] uppercase tracking-[0.18em] text-slate-500">Hatalı satır</p>
                                <p class="mt-2 text-lg font-semibold text-rose-600">{{ number_format($detailSummary['errors']) }}</p>
                            </div>
                            <div class="rounded-[10px] border border-slate-200 bg-white px-4 py-3">
                                <p class="text-[10px] uppercase tracking-[0.18em] text-slate-500">Tazmine uygun</p>
                                <p class="mt-2 text-lg font-semibold text-slate-900">{{ number_format($detailSummary['claimable']) }}</p>
                            </div>
                            <div class="rounded-[10px] border border-slate-200 bg-white px-4 py-3">
                                <p class="text-[10px] uppercase tracking-[0.18em] text-slate-500">Talep açılmış</p>
                                <p class="mt-2 text-lg font-semibold text-emerald-600">{{ number_format($detailSummary['with_compensation']) }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 py-4 lg:px-6">
                        <div class="hidden md:block overflow-x-auto rounded-2xl border border-slate-200 bg-white">
                            <table class="w-full table-fixed text-sm">
                                <thead class="sticky top-0 bg-slate-50 text-slate-500">
                                    <tr>
                                        <th class="w-20 px-2 py-2 text-left text-xs font-medium uppercase tracking-[0.14em]">Tarih</th>
                                        <th class="w-32 px-2 py-2 text-left text-xs font-medium uppercase tracking-[0.14em]">Müşteri</th>
                                        <th class="w-24 px-2 py-2 text-left text-xs font-medium uppercase tracking-[0.14em]">Stok</th>
                                        <th class="w-24 px-2 py-2 text-left text-xs font-medium uppercase tracking-[0.14em]">Takip</th>
                                        <th class="px-2 py-2 text-left text-xs font-medium uppercase tracking-[0.14em]">Ürün</th>
                                        <th class="w-16 px-1 py-2 text-center text-xs font-medium uppercase tracking-[0.14em]">B.Desi</th>
                                        <th class="w-16 px-1 py-2 text-center text-xs font-medium uppercase tracking-[0.14em]">G.Desi</th>
                                        <th class="w-20 px-1 py-2 text-center text-xs font-medium uppercase tracking-[0.14em]">B.Tutar</th>
                                        <th class="w-20 px-1 py-2 text-center text-xs font-medium uppercase tracking-[0.14em]">G.Tutar</th>
                                        <th class="w-20 px-2 py-2 text-center text-xs font-medium uppercase tracking-[0.14em]">Talep</th>
                                        <th class="w-20 px-2 py-2 text-center text-xs font-medium uppercase tracking-[0.14em]">Durum</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200">
                                    @forelse($this->viewingItems as $item)
                                        @php
                                            $rowClass = $item->is_iade
                                                ? 'bg-amber-50'
                                                : ($item->is_parca_gonderi
                                                    ? 'bg-sky-50'
                                                    : ($item->has_error ? 'bg-rose-50' : 'bg-white'));

                                            if ($item->is_iade) {
                                                $status = ['label' => 'İade', 'tone' => 'warning'];
                                            } elseif ($item->is_parca_gonderi) {
                                                $status = ['label' => 'Parça', 'tone' => 'info'];
                                            } else {
                                                $status = match ($item->error_type ?? 'none') {
                                                    'referans_eksik' => ['label' => 'Referans', 'tone' => 'warning'],
                                                    'desi_fazla', 'tutar_fazla', 'parca_eksik' => ['label' => 'Hata', 'tone' => 'danger'],
                                                    'desi_eksik', 'tutar_eksik' => ['label' => 'Kontrol', 'tone' => 'warning'],
                                                    'eslesmedi' => ['label' => 'Eşleşmedi', 'tone' => 'default'],
                                                    default => ['label' => 'OK', 'tone' => 'success'],
                                                };
                                            }
                                        @endphp
                                        <tr class="{{ $rowClass }}">
                                            <td class="px-2 py-2 text-sm text-slate-500">{{ $item->tarih?->format('d.m.Y') ?? '-' }}</td>
                                            <td class="px-2 py-2 truncate text-sm text-slate-900" title="{{ $item->musteri_adi ?? '-' }}">{{ $item->musteri_adi ?? '-' }}</td>
                                            <td class="px-2 py-2 truncate font-mono text-xs text-slate-500">{{ Str::limit($item->stok_kodu ?? '-', 12) }}</td>
                                            <td class="px-2 py-2 truncate font-mono text-xs text-slate-500">{{ Str::limit($item->takip_kodu ?? '-', 12) }}</td>
                                            <td class="px-2 py-2 truncate text-sm text-slate-900" title="{{ $item->urun_adi ?? '-' }}">{{ $item->urun_adi ?? '-' }}</td>
                                            <td class="px-1 py-2 text-center text-sm text-slate-500">{{ number_format($item->beklenen_desi ?? 0, 1, ',', '.') }}</td>
                                            <td class="px-1 py-2 text-center text-sm {{ ($item->desi_fark ?? 0) > 2 ? 'text-rose-600' : 'text-slate-900' }}">{{ number_format($item->gercek_desi ?? 0, 1, ',', '.') }}</td>
                                            <td class="px-1 py-2 text-center text-sm text-slate-500">{{ number_format($item->beklenen_tutar ?? 0, 0, ',', '.') }}</td>
                                            <td class="px-1 py-2 text-center text-sm {{ ($item->tutar_fark ?? 0) > 5 ? 'text-rose-600' : 'text-slate-900' }}">{{ number_format($item->gercek_tutar ?? 0, 0, ',', '.') }}</td>
                                            <td class="px-2 py-2 text-center">
                                                @if($item->compensation_exists)
                                                    <x-zolm.status-badge tone="success">Açıldı</x-zolm.status-badge>
                                                @else
                                                    <x-zolm.status-badge tone="default">Yok</x-zolm.status-badge>
                                                @endif
                                            </td>
                                            <td class="px-2 py-2 text-center"><x-zolm.status-badge :tone="$status['tone']">{{ $status['label'] }}</x-zolm.status-badge></td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="11" class="px-4 py-10 text-center text-sm text-slate-500">Seçili filtrelerde rapor satırı bulunamadı.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="md:hidden space-y-3">
                            @forelse($this->viewingItems as $item)
                                @php
                                    $cardClass = $item->is_iade
                                        ? 'border-amber-200 bg-amber-50'
                                        : ($item->is_parca_gonderi
                                            ? 'border-sky-200 bg-sky-50'
                                            : ($item->has_error ? 'border-rose-200 bg-rose-50' : 'border-slate-200 bg-white'));

                                    if ($item->is_iade) {
                                        $status = ['label' => 'İade', 'tone' => 'warning'];
                                    } elseif ($item->is_parca_gonderi) {
                                        $status = ['label' => 'Parça', 'tone' => 'info'];
                                    } else {
                                        $status = match ($item->error_type ?? 'none') {
                                            'referans_eksik' => ['label' => 'Referans', 'tone' => 'warning'],
                                            'desi_fazla', 'tutar_fazla', 'parca_eksik' => ['label' => 'Hata', 'tone' => 'danger'],
                                            'desi_eksik', 'tutar_eksik' => ['label' => 'Kontrol', 'tone' => 'warning'],
                                            'eslesmedi' => ['label' => 'Eşleşmedi', 'tone' => 'default'],
                                            default => ['label' => 'OK', 'tone' => 'success'],
                                        };
                                    }
                                @endphp
                                <div class="rounded-xl border p-4 {{ $cardClass }}">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2 text-xs text-slate-500">
                                                <span>{{ $item->tarih?->format('d.m.Y') ?? '-' }}</span>
                                                <span class="font-mono">{{ Str::limit($item->takip_kodu ?? '-', 12) }}</span>
                                            </div>
                                            <p class="mt-2 truncate text-sm font-semibold text-slate-900">{{ $item->musteri_adi ?? '-' }}</p>
                                        </div>
                                        <x-zolm.status-badge :tone="$status['tone']">{{ $status['label'] }}</x-zolm.status-badge>
                                    </div>
                                    <p class="mt-3 line-clamp-2 text-sm text-slate-700">{{ $item->urun_adi ?? '-' }}</p>
                                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-2">
                                        <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"><span class="text-slate-500">Beklenen desi</span><span class="font-semibold text-slate-900">{{ number_format($item->beklenen_desi ?? 0, 1, ',', '.') }}</span></div>
                                        <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"><span class="text-slate-500">Gerçek desi</span><span class="font-semibold text-slate-900">{{ number_format($item->gercek_desi ?? 0, 1, ',', '.') }}</span></div>
                                        <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"><span class="text-slate-500">Beklenen tutar</span><span class="font-semibold text-slate-900">{{ number_format($item->beklenen_tutar ?? 0, 0, ',', '.') }}</span></div>
                                        <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"><span class="text-slate-500">Gerçek tutar</span><span class="font-semibold text-slate-900">{{ number_format($item->gercek_tutar ?? 0, 0, ',', '.') }}</span></div>
                                        <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm sm:col-span-2"><span class="text-slate-500">Talep</span><span class="font-semibold {{ $item->compensation_exists ? 'text-emerald-600' : 'text-slate-900' }}">{{ $item->compensation_exists ? 'Açıldı' : 'Yok' }}</span></div>
                                    </div>
                                </div>
                            @empty
                                <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-8 text-center text-sm text-slate-500">Seçili filtrelerde rapor satırı bulunamadı.</div>
                            @endforelse
                        </div>
                    </div>

                    <div class="shrink-0 flex flex-col gap-3 border-t border-slate-200 bg-white px-4 py-4 lg:px-6">
                        <div>
                            {{ $this->viewingItems->links() }}
                        </div>
                        <div class="flex flex-col sm:flex-row justify-end gap-3">
                            <button wire:click="downloadFilteredReportItems" class="w-full sm:w-auto rounded-lg border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">Filtreli Excel</button>
                            <x-zolm.primary-button color="emerald" compact wire:click="downloadReport({{ $viewingReportId }})">Tam Rapor Excel</x-zolm.primary-button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($showDeleteModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
            <div class="flex min-h-screen items-center justify-center px-4 py-6 sm:py-8">
                <button type="button" class="fixed inset-0 bg-slate-900/40" wire:click="$set('showDeleteModal', false)"></button>

                <div class="relative w-full max-w-md rounded-[28px] border border-slate-200 bg-white p-4 lg:p-6 shadow-xl">
                    <x-zolm.eyebrow variant="classic">Silme Onayı</x-zolm.eyebrow>
                    <h3 class="mt-3 text-xl font-bold text-slate-900">Raporu kaldır</h3>
                    <p class="mt-2 text-sm text-slate-500">Silinen rapor ve ilişkili satırlar geri alınamaz.</p>

                    <div class="rounded-[8px] border border-rose-200 bg-rose-50 p-4 text-center">
                        <p class="text-sm font-semibold text-rose-800">Rapor silinecek</p>
                        <p class="mt-2 text-sm text-rose-700">Bu işlem seçili raporu ve tüm detay satırlarını kalıcı olarak kaldırır.</p>
                    </div>

                    <div class="mt-6 flex flex-col sm:flex-row justify-center gap-3">
                        <button wire:click="$set('showDeleteModal', false)" class="w-full sm:w-auto rounded-lg border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">İptal</button>
                        <button wire:click="deleteReport" class="w-full sm:w-auto rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 sm:py-2 text-sm font-medium text-rose-700 transition hover:bg-rose-100">Sil</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
