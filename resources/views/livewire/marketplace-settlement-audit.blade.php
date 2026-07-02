@php
    $summary = $audit['summary'];
    $queue = $audit['queue'];
    $breakdown = $audit['risk_breakdown'];
    $trend = $audit['service_fee_trend'];
    $tolerances = $audit['tolerances'];
    $formatMoney = fn ($value) => '₺' . number_format((float) $value, 2, ',', '.');
    $formatNumber = fn ($value, $decimals = 0) => number_format((float) $value, $decimals, ',', '.');
    $maxRiskCount = max(1, (int) collect($breakdown)->max('count'));
    $activeFilterCount = collect([
        $search,
        $marketplaceFilter,
        $storeFilter,
        $legalEntityFilter,
        $riskTypeFilter,
    ])->filter(fn ($value) => $value !== '')->count();
    $riskTone = fn (string $tone) => match ($tone) {
        'rose' => 'bg-rose-500',
        'amber' => 'bg-amber-500',
        'sky' => 'bg-sky-500',
        'emerald' => 'bg-emerald-500',
        default => 'bg-slate-500',
    };
    $sortIcon = function (string $columnKey) use ($sortableColumns, $sortField, $sortDirection) {
        $field = $sortableColumns[$columnKey] ?? null;
        if (! $field) {
            return '';
        }

        return $sortField === $field ? ($sortDirection === 'asc' ? '▲' : '▼') : '⇅';
    };
@endphp

<div class="flex flex-col gap-4 lg:gap-6">
    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
            <div class="min-w-0 max-w-3xl">
                <div class="inline-flex rounded-[6px] border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-mono font-medium text-slate-500">
                    ZOLM Finans
                </div>
                <h1 class="mt-3 text-xl font-bold text-slate-900 lg:text-xl">Hakediş, Desi ve Kesinti Kontrolü</h1>
                <p class="mt-2 text-sm leading-6 text-slate-500">
                    Beklediğiniz kâr ile pazar yerinin size gönderdiği kesinleşen ödemeyi karşılaştırın; itiraz edilmesi gereken eksik ödemeleri tek ekrandan yönetin.
                </p>
            </div>

            <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                <a href="{{ route('mp.finance') }}"
                   class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 sm:w-auto sm:py-2">
                    Finans defteri
                </a>
                <button type="button"
                        wire:click="exportAppealPackage"
                        wire:loading.attr="disabled"
                        wire:target="exportAppealPackage"
                        class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 disabled:cursor-wait disabled:opacity-60 sm:w-auto sm:py-2">
                    <span wire:loading.remove wire:target="exportAppealPackage">İtiraz Excel'ini İndir</span>
                    <span wire:loading wire:target="exportAppealPackage">Hazırlanıyor...</span>
                </button>
            </div>
        </div>

        <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <div class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <p class="text-xs font-mono text-slate-500">İncelenecek sipariş</p>
                <p class="mt-2 text-2xl font-bold text-slate-900">{{ $formatNumber($summary['review_order_count']) }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ $formatNumber($summary['critical_order_count']) }} kritik kayıt</p>
            </div>
            <div class="min-w-0 rounded-[8px] border border-rose-200 bg-rose-50/70 p-4">
                <p class="text-xs font-mono text-rose-700">Potansiyel iade</p>
                <p class="mt-2 truncate text-2xl font-bold text-rose-700">{{ $formatMoney($summary['potential_recovery']) }}</p>
                <p class="mt-1 text-xs text-rose-700/75">Çifte sayım engellenmiştir</p>
            </div>
            <div class="min-w-0 rounded-[8px] border border-amber-200 bg-amber-50/70 p-4">
                <div class="flex items-center gap-1">
                    <p class="text-xs font-mono text-amber-700">Ödeme Bekleyen</p>
                    <x-zolm.help-tip summary="Sipariş teslim edilmiş ancak pazar yeri tarafından ödemesi henüz hesabınıza geçmemiş siparişler." position="top" />
                </div>
                <p class="mt-2 text-2xl font-bold text-amber-700">{{ $formatNumber($summary['waiting_settlement_count']) }}</p>
                <p class="mt-1 text-xs text-amber-700/75">Kesin ödeme onayı yok</p>
            </div>
            <div class="min-w-0 rounded-[8px] border border-sky-200 bg-sky-50/70 p-4">
                <p class="text-xs font-mono text-sky-700">Kargo / desi farkı</p>
                <p class="mt-2 text-2xl font-bold text-sky-700">
                    {{ $formatNumber($summary['cargo_difference_count'] + $summary['desi_difference_count']) }}
                </p>
                <p class="mt-1 text-xs text-sky-700/75">{{ $summary['missing_shipment_count'] }} sevkiyat kaydı eksik</p>
            </div>
            <div class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <div class="flex items-center gap-1">
                    <p class="text-xs font-mono text-slate-500">Komisyon farkı</p>
                    <x-zolm.help-tip summary="Pazar yerinin hesapladığı komisyon ile sizin beklediğiniz komisyon arasındaki farklar." position="top" />
                </div>
                <p class="mt-2 text-2xl font-bold text-slate-900">{{ $formatNumber($summary['commission_difference_count']) }}</p>
                <p class="mt-1 text-xs text-slate-500">Kabul edilebilir fark: {{ $formatMoney($tolerances['commission']) }}</p>
            </div>
        </div>
    </section>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-12">
        <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6 xl:col-span-8">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Risk yoğunluğu</h2>
                    <p class="mt-1 text-sm text-slate-500">İtiraz ve veri tamamlama işlerini kayıt yoğunluğuna göre sıralayın.</p>
                </div>
                <span class="text-xs font-mono text-slate-400">{{ count($breakdown) }} aktif kontrol</span>
            </div>

            <div class="mt-5 space-y-3">
                @forelse($breakdown as $risk)
                    @php
                        $isFilterable = array_key_exists($risk['key'], $riskOptions);
                        $isActive = $riskTypeFilter === $risk['key'];
                        $width = max(3, round(((int) $risk['count'] / $maxRiskCount) * 100, 1));
                    @endphp
                    <button type="button"
                            @if($isFilterable) wire:click="focusRisk('{{ $risk['key'] }}')" @else disabled @endif
                            class="block w-full rounded-[6px] border px-3 py-3 text-left transition {{ $isActive ? 'border-slate-400 bg-slate-50' : 'border-transparent hover:border-slate-200 hover:bg-slate-50/70' }} {{ $isFilterable ? '' : 'cursor-default' }}">
                        <div class="flex min-w-0 items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-slate-800">{{ $risk['label'] }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">{{ $formatNumber($risk['count']) }} kayıt</p>
                            </div>
                            <p class="shrink-0 text-sm font-semibold {{ $risk['amount'] > 0 ? 'text-rose-700' : 'text-slate-600' }}">
                                {{ $risk['amount'] > 0 ? $formatMoney($risk['amount']) : 'Kontrol' }}
                            </p>
                        </div>
                        <div class="mt-2 h-2 overflow-hidden rounded-[6px] bg-slate-100">
                            <div class="h-full rounded-[6px] {{ $riskTone($risk['tone']) }}" style="width: {{ $width }}%"></div>
                        </div>
                    </button>
                @empty
                    <div class="rounded-[8px] border border-dashed border-slate-300 bg-slate-50/60 px-4 py-10 text-center text-sm text-slate-500">
                        Seçili aralıkta limit dışı risk bulunamadı.
                    </div>
                @endforelse
            </div>
        </section>

        <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6 xl:col-span-4">
            <div>
                <h2 class="text-base font-semibold text-slate-900">Kesinti sağlığı</h2>
                <p class="mt-1 text-sm text-slate-500">Hizmet bedeli ve kontrol limitlerinin dönem görünümü.</p>
            </div>

            <div class="mt-5 rounded-[8px] border border-slate-200 bg-slate-50/60 p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-mono text-slate-500">Hizmet bedeli oranı</p>
                        <p class="mt-2 text-2xl font-bold {{ $trend['is_increase'] ? 'text-amber-700' : 'text-slate-900' }}">
                            %{{ $formatNumber($trend['current_rate'], 2) }}
                        </p>
                    </div>
                    <span class="rounded-[6px] px-2 py-1 text-xs font-medium {{ $trend['change_points'] > 0 ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' }}">
                        {{ $trend['change_points'] > 0 ? '+' : '' }}{{ $formatNumber($trend['change_points'], 2) }} puan
                    </span>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-2">
                    <div class="min-w-0">
                        <div class="h-16 rounded-[6px] bg-slate-200 p-2">
                            <div class="flex h-full items-end">
                                <div class="w-full rounded-[4px] bg-slate-500" style="height: {{ min(100, max(5, $trend['previous_rate'] * 10)) }}%"></div>
                            </div>
                        </div>
                        <p class="mt-2 text-center text-xs text-slate-500">Önceki %{{ $formatNumber($trend['previous_rate'], 2) }}</p>
                    </div>
                    <div class="min-w-0">
                        <div class="h-16 rounded-[6px] bg-slate-200 p-2">
                            <div class="flex h-full items-end">
                                <div class="w-full rounded-[4px] {{ $trend['is_increase'] ? 'bg-amber-500' : 'bg-emerald-500' }}" style="height: {{ min(100, max(5, $trend['current_rate'] * 10)) }}%"></div>
                            </div>
                        </div>
                        <p class="mt-2 text-center text-xs text-slate-500">Güncel %{{ $formatNumber($trend['current_rate'], 2) }}</p>
                    </div>
                </div>
            </div>

            <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                <div class="rounded-[8px] border border-slate-200 bg-white p-3">
                    <p class="text-slate-500">Kargo farkı</p>
                    <p class="mt-1 font-semibold text-slate-900">{{ $formatMoney($tolerances['cargo']) }}</p>
                </div>
                <div class="rounded-[8px] border border-slate-200 bg-white p-3">
                    <p class="text-slate-500">Desi farkı</p>
                    <p class="mt-1 font-semibold text-slate-900">±{{ $formatNumber($tolerances['desi'], 1) }}</p>
                </div>
                <div class="rounded-[8px] border border-slate-200 bg-white p-3">
                    <p class="text-slate-500">Eşleşmeyen fatura</p>
                    <p class="mt-1 font-semibold {{ $summary['orphan_invoice_count'] > 0 ? 'text-rose-700' : 'text-slate-900' }}">{{ $summary['orphan_invoice_count'] }}</p>
                </div>
                <div class="rounded-[8px] border border-slate-200 bg-white p-3">
                    <p class="text-slate-500">Ödeme farkı</p>
                    <p class="mt-1 font-semibold text-slate-900">{{ $formatMoney($tolerances['settlement']) }}</p>
                </div>
            </div>
        </section>
    </div>

    <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 p-4 lg:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Kontrol ve itiraz kuyruğu</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ $audit['queue_total'] }} sipariş inceleme bekliyor. Filtre ve tablo aynı çalışma yüzeyinde tutulur.
                    </p>
                </div>

                <div class="relative flex w-full gap-2 sm:w-auto" x-data="{ columnsOpen: false }">
                    <button type="button"
                            @click="columnsOpen = !columnsOpen"
                            class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 sm:w-auto">
                        Kolonlar · {{ count($visibleColumns) }}
                    </button>
                    <div x-show="columnsOpen"
                         x-cloak
                         x-transition
                         @click.outside="columnsOpen = false"
                         class="absolute right-0 top-full z-30 mt-2 w-60 rounded-[8px] border border-slate-200 bg-white p-2 shadow-md">
                        @foreach($columnDefs as $key => $label)
                            <label class="flex min-h-[40px] cursor-pointer items-center gap-3 rounded-[6px] px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">
                                <input type="checkbox"
                                       wire:click="toggleColumn('{{ $key }}')"
                                       @checked(in_array($key, $visibleColumns, true))
                                       class="rounded border-slate-300 text-slate-900 focus:ring-slate-500">
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-6">
                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-xs font-medium text-slate-500">Sipariş, mağaza, takip veya fatura ara</label>
                    <input type="search"
                           wire:model.live.debounce.350ms="search"
                           placeholder="Sipariş no, takip no..."
                           class="min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 outline-none transition focus:border-slate-400 sm:text-sm">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium text-slate-500">Pazaryeri</label>
                    <select wire:model.live="marketplaceFilter" class="min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 sm:text-sm">
                        <option value="">Tümü</option>
                        @foreach($marketplaceOptions as $marketplace)
                            <option value="{{ $marketplace }}">{{ ucfirst($marketplace) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium text-slate-500">Mağaza</label>
                    <select wire:model.live="storeFilter" class="min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 sm:text-sm">
                        <option value="">Tümü</option>
                        @foreach($storeOptions as $store)
                            <option value="{{ $store->id }}">{{ $store->store_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium text-slate-500">Firma</label>
                    <select wire:model.live="legalEntityFilter" class="min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 sm:text-sm">
                        <option value="">Tümü</option>
                        @foreach($legalEntities as $entity)
                            <option value="{{ $entity->id }}">{{ $entity->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium text-slate-500">Risk</label>
                    <select wire:model.live="riskTypeFilter" class="min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 sm:text-sm">
                        <option value="">Tümü</option>
                        @foreach($riskOptions as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-[1fr_1fr_auto]">
                <div>
                    <label class="mb-1.5 block text-xs font-medium text-slate-500">Başlangıç</label>
                    <input type="date" wire:model.live="dateFrom" class="min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 sm:text-sm">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium text-slate-500">Bitiş</label>
                    <input type="date" wire:model.live="dateTo" class="min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 sm:text-sm">
                </div>
                <button type="button"
                        wire:click="resetFilters"
                        class="min-h-[44px] w-full self-end rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 sm:w-auto sm:py-2">
                    Filtreleri sıfırla
                </button>
            </div>

            <div class="mt-3 flex flex-col gap-2 rounded-[8px] border border-slate-200 bg-slate-50/60 px-3 py-2 text-xs text-slate-600 sm:flex-row sm:items-center sm:justify-between">
                <span>{{ $activeFilterCount > 0 ? $activeFilterCount . ' aktif filtre uygulanıyor' : 'Tüm kayıtlar gösteriliyor' }}</span>
                <span>{{ $dateFrom }} · {{ $dateTo }}</span>
            </div>
        </div>

        <div wire:loading.flex
             wire:target="search,marketplaceFilter,storeFilter,legalEntityFilter,riskTypeFilter,dateFrom,dateTo,resetFilters,focusRisk,sortTable"
             class="items-center justify-center border-b border-slate-200 bg-slate-50/60 px-4 py-3 text-sm text-slate-500">
            Kontrol kuyruğu güncelleniyor...
        </div>

        <div class="p-4 lg:p-6">
            <div class="space-y-3 md:hidden">
                @forelse($queue as $row)
                    <article wire:key="settlement-card-{{ $row['id'] }}" class="rounded-[8px] border border-slate-200 bg-white p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-900">{{ $row['order_number'] }}</p>
                                <p class="mt-1 truncate text-xs text-slate-500">{{ $row['store_name'] }} · {{ ucfirst($row['marketplace']) }}</p>
                            </div>
                            <span class="shrink-0 rounded-[6px] px-2 py-1 text-xs font-medium {{ $row['severity'] === 'critical' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800' }}">
                                {{ $row['severity_label'] }}
                            </span>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-1.5">
                            @foreach($row['risks'] as $risk)
                                <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-1 text-xs text-slate-600">{{ $risk['label'] }}</span>
                            @endforeach
                        </div>

                        <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                            <div class="rounded-[6px] bg-slate-50 p-2">
                                <p class="text-slate-500">Komisyon farkı</p>
                                <p class="mt-1 font-semibold {{ $row['commission_delta'] > $tolerances['commission'] ? 'text-rose-700' : 'text-slate-900' }}">{{ $formatMoney($row['commission_delta']) }}</p>
                            </div>
                            <div class="rounded-[6px] bg-slate-50 p-2">
                                <p class="text-slate-500">Kargo farkı</p>
                                <p class="mt-1 font-semibold {{ $row['cargo_delta'] > $tolerances['cargo'] ? 'text-rose-700' : 'text-slate-900' }}">{{ $formatMoney($row['cargo_delta']) }}</p>
                            </div>
                            <div class="rounded-[6px] bg-slate-50 p-2">
                                <p class="text-slate-500">Desi farkı</p>
                                <p class="mt-1 font-semibold text-slate-900">{{ $formatNumber($row['desi_delta'], 1) }}</p>
                            </div>
                            <div class="rounded-[6px] bg-slate-50 p-2">
                                <p class="text-slate-500">Potansiyel iade</p>
                                <p class="mt-1 font-semibold text-rose-700">{{ $formatMoney($row['potential_recovery']) }}</p>
                            </div>
                        </div>

                        <p class="mt-3 text-xs leading-5 text-slate-600">{{ $row['action_hint'] }}</p>
                        <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                            <a href="{{ $this->financeUrl($row) }}" class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] bg-slate-900 px-3 py-2 text-sm font-medium text-white sm:w-auto">Finansta aç</a>
                            <a href="{{ $this->cargoUrl() }}" class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 sm:w-auto">Kargoda aç</a>
                        </div>
                    </article>
                @empty
                    <div class="rounded-[8px] border border-dashed border-slate-300 bg-slate-50/60 px-4 py-10 text-center text-sm text-slate-500">
                        Seçilen filtrelere uygun kontrol kaydı bulunamadı.
                    </div>
                @endforelse
            </div>

            <div class="hidden rounded-lg border border-slate-200 bg-white md:block" x-data="columnResize()">
                <div class="overflow-x-auto [scrollbar-gutter:stable]">
                    <table class="w-full min-w-[980px] table-fixed">
                        <thead class="bg-slate-50 text-slate-500">
                            <tr>
                                @foreach($columnDefs as $key => $label)
                                    @if(in_array($key, $visibleColumns, true))
                                        <th @if(isset($sortableColumns[$key])) wire:click="sortTable('{{ $key }}')" @endif
                                            class="px-3 py-3 text-left text-[11px] font-semibold uppercase tracking-[0.12em] {{ isset($sortableColumns[$key]) ? 'cursor-pointer select-none' : '' }}">
                                            <div class="flex items-center justify-between gap-2">
                                                <span>{{ $label }}</span>
                                                @if(isset($sortableColumns[$key]))
                                                    <span class="{{ $sortField === $sortableColumns[$key] ? 'text-slate-700' : 'text-slate-300' }}">{{ $sortIcon($key) }}</span>
                                                @endif
                                            </div>
                                        </th>
                                    @endif
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 text-sm text-slate-700">
                            @forelse($queue as $row)
                                <tr wire:key="settlement-row-{{ $row['id'] }}" class="transition hover:bg-slate-50/80">
                                    @if(in_array('order', $visibleColumns, true))
                                        <td class="overflow-hidden px-3 py-3 align-top">
                                            <p class="truncate font-semibold text-slate-900">{{ $row['order_number'] }}</p>
                                            <p class="mt-1 truncate text-xs text-slate-500">{{ $row['ordered_at'] }} · {{ $row['order_status'] }}</p>
                                        </td>
                                    @endif
                                    @if(in_array('store', $visibleColumns, true))
                                        <td class="overflow-hidden px-3 py-3 align-top">
                                            <p class="truncate font-medium text-slate-900">{{ $row['store_name'] }}</p>
                                            <p class="mt-1 truncate text-xs text-slate-500">{{ ucfirst($row['marketplace']) }}</p>
                                            <p class="mt-1 truncate text-xs text-slate-400">{{ $row['legal_entity_name'] }}</p>
                                        </td>
                                    @endif
                                    @if(in_array('risk', $visibleColumns, true))
                                        <td class="overflow-hidden px-3 py-3 align-top">
                                            <span class="inline-flex rounded-[6px] px-2 py-1 text-xs font-medium {{ $row['severity'] === 'critical' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800' }}">{{ $row['severity_label'] }}</span>
                                            <p class="mt-2 truncate text-xs text-slate-600">{{ collect($row['risks'])->pluck('label')->implode(', ') }}</p>
                                        </td>
                                    @endif
                                    @if(in_array('commission', $visibleColumns, true))
                                        <td class="overflow-hidden px-3 py-3 text-right align-top">
                                            <p class="font-semibold {{ $row['commission_delta'] > $tolerances['commission'] ? 'text-rose-700' : 'text-slate-900' }}">{{ $formatMoney($row['commission_delta']) }}</p>
                                            <p class="mt-1 truncate text-xs text-slate-500">{{ $formatMoney($row['estimated_commission']) }} → {{ $formatMoney($row['actual_commission']) }}</p>
                                        </td>
                                    @endif
                                    @if(in_array('cargo', $visibleColumns, true))
                                        <td class="overflow-hidden px-3 py-3 text-right align-top">
                                            <p class="font-semibold {{ $row['cargo_delta'] > $tolerances['cargo'] ? 'text-rose-700' : 'text-slate-900' }}">{{ $formatMoney($row['cargo_delta']) }}</p>
                                            <p class="mt-1 truncate text-xs text-slate-500">{{ $formatMoney($row['expected_cargo']) }} → {{ $formatMoney($row['invoice_cargo']) }}</p>
                                        </td>
                                    @endif
                                    @if(in_array('desi', $visibleColumns, true))
                                        <td class="overflow-hidden px-3 py-3 text-right align-top">
                                            <p class="font-semibold {{ abs($row['desi_delta']) > $tolerances['desi'] ? 'text-rose-700' : 'text-slate-900' }}">{{ $formatNumber($row['desi_delta'], 1) }}</p>
                                            <p class="mt-1 truncate text-xs text-slate-500">{{ $formatNumber($row['expected_desi'], 1) }} → {{ $formatNumber($row['invoice_desi'], 1) }}</p>
                                        </td>
                                    @endif
                                    @if(in_array('recovery', $visibleColumns, true))
                                        <td class="overflow-hidden px-3 py-3 text-right align-top">
                                            <p class="font-semibold text-rose-700">{{ $formatMoney($row['potential_recovery']) }}</p>
                                            <p class="mt-1 truncate text-xs text-slate-500">{{ $row['invoice_number'] ?: 'Fatura no yok' }}</p>
                                        </td>
                                    @endif
                                    @if(in_array('action', $visibleColumns, true))
                                        <td class="px-3 py-3 align-top">
                                            <div class="flex items-center justify-end gap-2">
                                                <a href="{{ $this->financeUrl($row) }}" title="Finans kaydını aç" class="inline-flex min-h-[36px] items-center justify-center rounded-[6px] bg-slate-900 px-3 py-2 text-xs font-medium text-white">Finans</a>
                                                <a href="{{ $this->cargoUrl() }}" title="Kargo kaydını aç" class="inline-flex min-h-[36px] items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700">Kargo</a>
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ max(1, count($visibleColumns)) }}" class="px-4 py-12 text-center text-sm text-slate-500">
                                        Seçilen filtrelere uygun kontrol kaydı bulunamadı.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>
