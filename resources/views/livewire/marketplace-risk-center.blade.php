@php
    $dashboard = $this->dashboard;
    $summary = $dashboard['summary'];
    $formatMoney = function ($value) {
        $amount = (float) $value;
        $prefix = $amount < 0 ? '-₺' : '₺';

        return $prefix . number_format(abs($amount), 2, ',', '.');
    };
    $formatCompactMoney = function ($value) {
        $amount = abs((float) $value);
        $prefix = (float) $value < 0 ? '-₺' : '₺';

        if ($amount >= 1000000) {
            return $prefix . number_format($amount / 1000000, 1, ',', '.') . ' Mn';
        }

        if ($amount >= 1000) {
            return $prefix . number_format($amount / 1000, 1, ',', '.') . ' Bin';
        }

        return $prefix . number_format($amount, 0, ',', '.');
    };
    $formatCount = fn ($value) => number_format((float) $value, 0, ',', '.');
    $mutedTypes = collect($dashboard['notification_preferences']['muted_types'] ?? []);
    $activeFilters = array_values(array_filter([
        $search !== '' ? 'Arama: ' . $search : null,
        $categoryFilter !== '' ? 'Kategori: ' . ($categoryDefinitions[$categoryFilter]['label'] ?? $categoryFilter) : null,
        $severityFilter !== '' ? 'Önem: ' . ($severityDefinitions[$severityFilter]['label'] ?? $severityFilter) : null,
        $statusFilter !== 'active' ? 'Durum: ' . ($statusDefinitions[$statusFilter]['label'] ?? $statusFilter) : null,
    ]));
    $sortIcon = function (string $column) use ($sortableColumns, $sortField, $sortDirection) {
        $field = $sortableColumns[$column] ?? null;

        if (!$field) {
            return '';
        }

        return $sortField === $field ? ($sortDirection === 'asc' ? '▲' : '▼') : '⇅';
    };
    $severityClasses = [
        'critical' => 'border-rose-200 bg-rose-50 text-rose-700',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-700',
        'info' => 'border-sky-200 bg-sky-50 text-sky-700',
    ];
    $statusClasses = [
        'open' => 'border-rose-200 bg-rose-50 text-rose-700',
        'snoozed' => 'border-amber-200 bg-amber-50 text-amber-700',
        'resolved' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
    ];
    $barClasses = [
        'rose' => 'bg-rose-500',
        'amber' => 'bg-amber-500',
        'sky' => 'bg-sky-500',
        'indigo' => 'bg-indigo-500',
        'emerald' => 'bg-emerald-500',
        'slate' => 'bg-slate-500',
    ];
@endphp

@once
    <style>
        .risk-center-table {
            table-layout: fixed;
            width: 100%;
            min-width: 1040px;
        }

        .risk-center-table th,
        .risk-center-table td {
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .risk-column-resizer {
            position: absolute;
            right: -2px;
            top: 0;
            width: 5px;
            height: 100%;
            cursor: col-resize;
            z-index: 10;
        }
    </style>
@endonce

<div class="flex w-full flex-col gap-4 sm:flex-row lg:gap-6">
    <div class="min-w-0 w-full space-y-4 lg:space-y-6">
        @if(session()->has('success'))
            <div class="rounded-[8px] border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
            <div class="grid grid-cols-1 gap-4 xl:grid-cols-12">
                <div class="min-w-0 xl:col-span-5">
                    <x-zolm.eyebrow>Pazaryeri Günlük Görevler</x-zolm.eyebrow>
                    <h1 class="mt-3 text-xl font-bold text-slate-900 lg:text-2xl">Bugün ilk neyi düzeltmeliyim?</h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">
                        Sipariş, ödeme, kargo ve kârlılık sorunlarınızı tek bir görev listesinde hızlıca çözün.
                    </p>

                    @if($dashboard['primary_focus'])
                        @php($focusSignal = $dashboard['primary_focus'])
                        <div class="mt-5 rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-[6px] border px-2 py-0.5 text-xs font-mono {{ $severityClasses[$focusSignal['severity']] ?? 'border-slate-200 bg-white text-slate-600' }}">
                                            {{ $focusSignal['severity_label'] }}
                                        </span>
                                        <span class="text-xs text-slate-500">{{ $focusSignal['category_label'] }}</span>
                                    </div>
                                    <h2 class="mt-2 text-base font-semibold text-slate-900">{{ $focusSignal['title'] }}</h2>
                                    <p class="mt-1 text-sm leading-5 text-slate-500">{{ $focusSignal['recommendation'] }}</p>
                                </div>
                                <div class="shrink-0 text-left sm:text-right">
                                    <p class="text-xs text-slate-500">Öncelik</p>
                                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ number_format($focusSignal['priority_score'], 1, ',', '.') }}</p>
                                </div>
                            </div>
                            <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                                <a href="{{ $focusSignal['action_url'] }}"
                                   class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 sm:w-auto sm:py-2">
                                    {{ $focusSignal['action_label'] }}
                                </a>
                                <button type="button"
                                        wire:click="snoozeRisk('{{ $focusSignal['fingerprint'] }}', 1)"
                                        class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 sm:w-auto sm:py-2">
                                    <x-lucide.icon name="clock" />
                                    Yarın hatırlat
                                </button>
                            </div>
                        </div>
                    @else
                        <div class="mt-5 rounded-[8px] border border-emerald-200 bg-emerald-50 p-4">
                            <p class="text-sm font-semibold text-emerald-800">Açık risk kuyruğu temiz.</p>
                            <p class="mt-1 text-sm text-emerald-700">Yeni sinyaller saatlik kontrol ile otomatik olarak buraya düşer.</p>
                        </div>
                    @endif
                </div>

                <div class="xl:col-span-3">
                    <div class="flex h-full min-h-[230px] flex-col items-center justify-center rounded-[8px] border border-slate-200 bg-slate-50/60 p-4 text-center">
                        <div class="relative flex h-32 w-32 items-center justify-center rounded-full"
                             style="background: conic-gradient(#0f172a {{ $summary['risk_score'] }}%, #e2e8f0 0);">
                            <div class="flex h-24 w-24 flex-col items-center justify-center rounded-full bg-white">
                                <span class="text-3xl font-bold text-slate-900">%{{ number_format($summary['risk_score'], 1, ',', '.') }}</span>
                                <span class="mt-1 text-xs text-slate-500">{{ $summary['risk_score_label'] }}</span>
                            </div>
                        </div>
                        <p class="mt-4 text-sm font-semibold text-slate-900">Risk kontrol skoru</p>
                        <p class="mt-1 text-xs leading-5 text-slate-500">Açık sinyallerin önem ve yoğunluğuna göre hesaplanır.</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 xl:col-span-4">
                    <div class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                        <p class="text-xs font-mono text-slate-500">Açık risk</p>
                        <p class="mt-2 text-2xl font-bold text-slate-900">{{ $formatCount($summary['open_count']) }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $formatCount($summary['critical_count']) }} kritik</p>
                    </div>
                    <div class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                        <p class="text-xs font-mono text-slate-500">Finansal etki</p>
                        <p class="mt-2 truncate text-2xl font-bold text-rose-700">{{ $formatCompactMoney($summary['impact_total']) }}</p>
                        <p class="mt-1 text-xs text-slate-500">Açık risk baskısı</p>
                    </div>
                    <div class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                        <p class="text-xs font-mono text-slate-500">Etkilenen kayıt</p>
                        <p class="mt-2 text-2xl font-bold text-slate-900">{{ $formatCount($summary['affected_total']) }}</p>
                        <p class="mt-1 text-xs text-slate-500">Toplam kapsam</p>
                    </div>
                    <div class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                        <p class="text-xs font-mono text-slate-500">Kontrol edilen</p>
                        <p class="mt-2 text-2xl font-bold text-emerald-700">{{ $formatCount($summary['resolved_count']) }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $formatCount($summary['snoozed_count']) }} ertelendi</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-4 xl:grid-cols-12">
            <div class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6 xl:col-span-7">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Kategori baskısı</h2>
                        <p class="mt-1 text-sm text-slate-500">Risk yoğunluğunu iş alanına göre karşılaştırın.</p>
                    </div>
                    <button type="button"
                            wire:click="syncRisks"
                            wire:loading.attr="disabled"
                            class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-50 sm:w-auto sm:py-2">
                        <x-lucide.icon name="activity" wire:loading.class="animate-pulse" wire:target="syncRisks" />
                        Sinyalleri yenile
                    </button>
                </div>

                <div class="mt-5 space-y-4">
                    @forelse($dashboard['category_breakdown'] as $category)
                        <button type="button"
                                wire:click="focusCategory('{{ $category['key'] }}')"
                                class="block w-full rounded-[8px] border p-3 text-left transition {{ $categoryFilter === $category['key'] ? 'border-slate-900 bg-slate-50' : 'border-slate-200 bg-white hover:border-slate-300' }}">
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-slate-900">{{ $category['label'] }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $formatCount($category['count']) }} sinyal · {{ $formatCount($category['affected']) }} kayıt</p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <p class="text-sm font-semibold text-slate-900">{{ $formatCompactMoney($category['impact']) }}</p>
                                    <p class="mt-1 text-xs text-rose-600">{{ $category['critical_count'] }} kritik</p>
                                </div>
                            </div>
                            <div class="mt-3 h-2 overflow-hidden rounded-[6px] bg-slate-100">
                                <div class="h-full {{ $barClasses[$category['tone']] ?? 'bg-slate-500' }}"
                                     style="width: {{ max(4, min(100, $category['max_score'])) }}%"></div>
                            </div>
                        </button>
                    @empty
                        <div class="rounded-[8px] border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500">
                            Açık kategori baskısı bulunmuyor.
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6 xl:col-span-5">
                <h2 class="text-lg font-semibold text-slate-900">Öncelikli aksiyonlar</h2>
                <p class="mt-1 text-sm text-slate-500">Skor, kayıt adedi ve finansal etkiye göre ilk dört adım.</p>

                <div class="mt-4 space-y-3">
                    @forelse($dashboard['priority_actions'] as $index => $signal)
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                            <div class="flex items-start gap-3">
                                <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-[6px] bg-slate-900 text-sm font-semibold text-white">{{ $index + 1 }}</span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start justify-between gap-2">
                                        <p class="text-sm font-semibold text-slate-900">{{ $signal['title'] }}</p>
                                        <span class="shrink-0 text-xs font-semibold text-slate-500">{{ number_format($signal['priority_score'], 1, ',', '.') }}</span>
                                    </div>
                                    <p class="mt-1 line-clamp-2 text-xs leading-5 text-slate-500">{{ $signal['recommendation'] }}</p>
                                    <a href="{{ $signal['action_url'] }}" class="mt-2 inline-flex text-xs font-semibold text-slate-900 hover:text-slate-600">{{ $signal['action_label'] }} →</a>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-[8px] border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500">
                            Öncelikli aksiyon bulunmuyor.
                        </div>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 p-4 lg:p-6">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Risk defteri</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ $formatCount($dashboard['queue_total']) }} sinyal filtrelerle eşleşiyor.</p>
                    </div>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <div x-data="{ open: false }" class="relative">
                            <button type="button"
                                    @click="open = !open"
                                    class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 sm:w-auto sm:py-2">
                                Kolonlar · {{ count($visibleColumns) }}
                            </button>
                            <div x-show="open" x-cloak x-transition @click.outside="open = false"
                                 class="absolute right-0 top-full z-30 mt-2 w-64 rounded-[8px] border border-slate-200 bg-white p-2 shadow-xl">
                                @foreach($columnDefs as $key => $label)
                                    <label class="flex cursor-pointer items-center gap-3 rounded-[6px] px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">
                                        <input type="checkbox"
                                               wire:click="toggleColumn('{{ $key }}')"
                                               @checked(in_array($key, $visibleColumns, true))
                                               class="h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-900">
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <button type="button"
                                wire:click="exportRiskReport"
                                wire:loading.attr="disabled"
                                class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 disabled:opacity-50 sm:w-auto sm:py-2">
                            Excel indir
                        </button>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    <label class="xl:col-span-2">
                        <span class="mb-1 block text-xs font-medium text-slate-500">Arama</span>
                        <input type="search"
                               wire:model.live.debounce.350ms="search"
                               placeholder="Risk, kaynak veya mağaza ara"
                               class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none transition focus:border-slate-900 sm:py-2 sm:text-sm">
                    </label>
                    <label>
                        <span class="mb-1 block text-xs font-medium text-slate-500">Kategori</span>
                        <select wire:model.live="categoryFilter" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none focus:border-slate-900 sm:py-2 sm:text-sm">
                            <option value="">Tümü</option>
                            @foreach($categoryDefinitions as $key => $definition)
                                <option value="{{ $key }}">{{ $definition['label'] }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        <span class="mb-1 block text-xs font-medium text-slate-500">Önem</span>
                        <select wire:model.live="severityFilter" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none focus:border-slate-900 sm:py-2 sm:text-sm">
                            <option value="">Tümü</option>
                            @foreach($severityDefinitions as $key => $definition)
                                <option value="{{ $key }}">{{ $definition['label'] }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        <span class="mb-1 block text-xs font-medium text-slate-500">Durum</span>
                        <select wire:model.live="statusFilter" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none focus:border-slate-900 sm:py-2 sm:text-sm">
                            <option value="active">Aktif kuyruk</option>
                            <option value="snoozed">Ertelenen</option>
                            <option value="resolved">Çözülen</option>
                            <option value="all">Tümü</option>
                        </select>
                    </label>
                </div>

                <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-slate-500">
                        @if($activeFilters)
                            Aktif filtreler: {{ implode(' · ', $activeFilters) }}
                        @else
                            Aktif risk kuyruğu gösteriliyor.
                        @endif
                    </p>
                    <button type="button" wire:click="resetFilters" class="text-left text-xs font-semibold text-slate-600 hover:text-slate-900 sm:text-right">Filtreleri sıfırla</button>
                </div>
            </div>

            <div class="space-y-3 p-4 md:hidden">
                @forelse($dashboard['queue'] as $signal)
                    <article @class([
                        'rounded-[8px] border bg-white p-4',
                        'ring-2 ring-slate-900' => $focus === $signal['fingerprint'],
                        'border-slate-200' => $focus !== $signal['fingerprint'],
                    ])>
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-[6px] border px-2 py-0.5 text-xs font-mono {{ $severityClasses[$signal['severity']] ?? 'border-slate-200 bg-slate-50 text-slate-600' }}">{{ $signal['severity_label'] }}</span>
                                    <span class="text-xs text-slate-500">{{ $signal['category_label'] }}</span>
                                </div>
                                <h3 class="mt-2 text-sm font-semibold text-slate-900">{{ $signal['title'] }}</h3>
                            </div>
                            <span class="shrink-0 text-lg font-bold text-slate-900">{{ number_format($signal['priority_score'], 1, ',', '.') }}</span>
                        </div>
                        <p class="mt-2 text-sm leading-5 text-slate-500">{{ $signal['description'] }}</p>
                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-3 py-2">
                                <p class="text-xs text-slate-500">Finansal etki</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900">{{ $formatMoney($signal['impact']) }}</p>
                            </div>
                            <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-3 py-2">
                                <p class="text-xs text-slate-500">Durum</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900">{{ $signal['status_label'] }}</p>
                            </div>
                        </div>
                        <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                            <a href="{{ $signal['action_url'] }}" class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white sm:w-auto">{{ $signal['action_label'] }}</a>
                            @if(!empty($signal['available_actions']))
                                @foreach($signal['available_actions'] as $action)
                                    <button type="button" wire:click="executeAction('{{ $signal['fingerprint'] }}', '{{ $action['key'] }}')" class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 sm:w-auto">
                                        <x-lucide.icon name="{{ $action['icon'] }}" /> {{ $action['label'] }}
                                    </button>
                                @endforeach
                            @endif
                            @if($signal['status'] === 'open')
                                <button type="button" wire:click="resolveRisk('{{ $signal['fingerprint'] }}')" title="Çözüldü olarak işaretle" class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 sm:w-auto">
                                    <x-lucide.icon name="check-circle" /> Çöz
                                </button>
                                <button type="button" wire:click="snoozeRisk('{{ $signal['fingerprint'] }}', 3)" title="Üç gün ertele" class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 sm:w-auto">
                                    <x-lucide.icon name="clock" /> Ertele
                                </button>
                            @else
                                <button type="button" wire:click="reopenRisk('{{ $signal['fingerprint'] }}')" title="Yeniden aç" class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 sm:w-auto">
                                    <x-lucide.icon name="activity" /> Yeniden aç
                                </button>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="rounded-[8px] border border-dashed border-slate-300 px-4 py-10 text-center text-sm text-slate-500">Filtrelerle eşleşen risk bulunamadı.</div>
                @endforelse
            </div>

            <div class="hidden overflow-x-auto md:block" x-data="columnResize()">
                <table class="risk-center-table text-left text-sm">
                    <thead class="bg-slate-50/80 text-xs text-slate-500">
                        <tr>
                            @foreach($visibleColumns as $column)
                                <th class="relative border-b border-slate-200 px-4 py-3 font-medium"
                                    style="width: {{ in_array($column, ['signal'], true) ? '310px' : (in_array($column, ['actions'], true) ? '170px' : '140px') }}">
                                    @if(isset($sortableColumns[$column]))
                                        <button type="button" wire:click="sortTable('{{ $column }}')" class="inline-flex items-center gap-1 text-left hover:text-slate-900">
                                            {{ $columnDefs[$column] }} <span>{{ $sortIcon($column) }}</span>
                                        </button>
                                    @else
                                        {{ $columnDefs[$column] }}
                                    @endif
                                    <span class="risk-column-resizer" @mousedown="startResize($event)"></span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($dashboard['queue'] as $signal)
                            <tr @class([
                                'transition hover:bg-slate-50/60',
                                'bg-slate-50 ring-1 ring-inset ring-slate-900' => $focus === $signal['fingerprint'],
                            ])>
                                @foreach($visibleColumns as $column)
                                    <td class="px-4 py-4 align-top">
                                        @switch($column)
                                            @case('priority')
                                                <div class="flex items-center gap-2">
                                                    <span class="inline-flex h-9 min-w-9 items-center justify-center rounded-[6px] bg-slate-900 px-2 text-sm font-bold text-white">{{ number_format($signal['priority_score'], 0, ',', '.') }}</span>
                                                    <span class="rounded-[6px] border px-2 py-0.5 text-xs font-mono {{ $severityClasses[$signal['severity']] ?? 'border-slate-200 bg-slate-50 text-slate-600' }}">{{ $signal['severity_label'] }}</span>
                                                </div>
                                                @break
                                            @case('signal')
                                                <p class="truncate font-semibold text-slate-900" title="{{ $signal['title'] }}">{{ $signal['title'] }}</p>
                                                <p class="mt-1 line-clamp-2 text-xs leading-5 text-slate-500" title="{{ $signal['description'] }}">{{ $signal['description'] }}</p>
                                                <p class="mt-2 truncate text-xs font-medium text-slate-700" title="{{ $signal['recommendation'] }}">{{ $signal['recommendation'] }}</p>
                                                @break
                                            @case('category')
                                                <p class="font-medium text-slate-900">{{ $signal['category_label'] }}</p>
                                                <p class="mt-1 text-xs text-slate-500">{{ $formatCount($signal['value']) }} kayıt</p>
                                                @break
                                            @case('impact')
                                                <p class="font-semibold {{ $signal['impact'] > 0 ? 'text-rose-700' : 'text-slate-900' }}">{{ $formatMoney($signal['impact']) }}</p>
                                                <p class="mt-1 text-xs text-slate-500">{{ $formatCount($signal['value']) }} etkilenen</p>
                                                @break
                                            @case('status')
                                                <span class="rounded-[6px] border px-2 py-0.5 text-xs font-mono {{ $statusClasses[$signal['status']] ?? 'border-slate-200 bg-slate-50 text-slate-600' }}">{{ $signal['status_label'] }}</span>
                                                <p class="mt-2 text-xs text-slate-500">{{ $signal['last_seen_label'] }}</p>
                                                @if($signal['snoozed_until_label'])
                                                    <p class="mt-1 text-xs text-amber-700">{{ $signal['snoozed_until_label'] }}</p>
                                                @endif
                                                @break
                                            @case('source')
                                                <p class="truncate font-medium text-slate-900" title="{{ $signal['source_label'] }}">{{ $signal['source_label'] }}</p>
                                                @if($signal['store_name'] ?? null)
                                                    <p class="mt-1 truncate text-xs text-slate-500">{{ $signal['store_name'] }}</p>
                                                @endif
                                                @break
                                            @case('actions')
                                                <div class="flex items-center gap-2">
                                                    <a href="{{ $signal['action_url'] }}" title="{{ $signal['action_label'] }}" class="inline-flex h-9 w-9 items-center justify-center rounded-[6px] bg-slate-900 text-white transition hover:bg-slate-800">
                                                        <x-lucide.icon name="arrow-up-right" />
                                                    </a>
                                                    @if(!empty($signal['available_actions']))
                                                        @foreach($signal['available_actions'] as $action)
                                                            <button type="button" wire:click="executeAction('{{ $signal['fingerprint'] }}', '{{ $action['key'] }}')" title="{{ $action['label'] }}" class="inline-flex h-9 w-9 items-center justify-center rounded-[6px] border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50 hover:text-indigo-700">
                                                                <x-lucide.icon name="{{ $action['icon'] }}" />
                                                            </button>
                                                        @endforeach
                                                    @endif
                                                    @if($signal['status'] === 'open')
                                                        <button type="button" wire:click="resolveRisk('{{ $signal['fingerprint'] }}')" title="Çözüldü olarak işaretle" class="inline-flex h-9 w-9 items-center justify-center rounded-[6px] border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50 hover:text-emerald-700">
                                                            <x-lucide.icon name="check-circle" />
                                                        </button>
                                                        <div x-data="{ open: false }" class="relative">
                                                            <button type="button" @click="open = !open" title="Riski ertele" class="inline-flex h-9 w-9 items-center justify-center rounded-[6px] border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50 hover:text-amber-700">
                                                                <x-lucide.icon name="clock" />
                                                            </button>
                                                            <div x-show="open" x-cloak x-transition @click.outside="open = false" class="absolute right-0 top-full z-30 mt-2 w-36 rounded-[8px] border border-slate-200 bg-white p-1.5 shadow-xl">
                                                                @foreach([1 => '1 gün', 3 => '3 gün', 7 => '7 gün'] as $days => $label)
                                                                    <button type="button" wire:click="snoozeRisk('{{ $signal['fingerprint'] }}', {{ $days }})" @click="open = false" class="block w-full rounded-[6px] px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50">{{ $label }}</button>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    @else
                                                        <button type="button" wire:click="reopenRisk('{{ $signal['fingerprint'] }}')" title="Yeniden açık kuyruğa al" class="inline-flex h-9 w-9 items-center justify-center rounded-[6px] border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50">
                                                            <x-lucide.icon name="activity" />
                                                        </button>
                                                    @endif
                                                </div>
                                                @break
                                        @endswitch
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($visibleColumns) }}" class="px-4 py-12 text-center text-sm text-slate-500">Filtrelerle eşleşen risk bulunamadı.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Risk bildirim tercihleri</h2>
                    <p class="mt-1 text-sm text-slate-500">Kritik ve uyarı sinyallerinin canlı bildirim akışına düşme kapsamını yönetin.</p>
                </div>
                <p class="text-xs text-slate-400">Son yenileme: {{ $dashboard['generated_at'] }}</p>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach([
                    'risk_critical' => ['label' => 'Kritik riskler', 'description' => 'En yüksek finansal ve operasyonel baskı.'],
                    'risk_warning' => ['label' => 'Risk uyarıları', 'description' => 'İzlenmesi ve planlanması gereken sinyaller.'],
                ] as $token => $option)
                    <label class="flex min-w-0 cursor-pointer items-start gap-3 rounded-[8px] border border-slate-200 bg-slate-50/60 p-4">
                        <input type="checkbox"
                               wire:click="toggleNotificationPreference('{{ $token }}')"
                               @checked(!$mutedTypes->contains($token))
                               class="mt-0.5 h-5 w-5 rounded border-slate-300 text-slate-900 focus:ring-slate-900">
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-slate-900">{{ $option['label'] }}</span>
                            <span class="mt-1 block text-xs leading-5 text-slate-500">{{ $option['description'] }}</span>
                        </span>
                    </label>
                @endforeach

                @foreach($categoryDefinitions as $key => $definition)
                    @php($token = 'risk_category:' . $key)
                    <label class="flex min-w-0 cursor-pointer items-start gap-3 rounded-[8px] border border-slate-200 bg-slate-50/60 p-4">
                        <input type="checkbox"
                               wire:click="toggleNotificationPreference('{{ $token }}')"
                               @checked(!$mutedTypes->contains($token))
                               class="mt-0.5 h-5 w-5 rounded border-slate-300 text-slate-900 focus:ring-slate-900">
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-slate-900">{{ $definition['label'] }}</span>
                            <span class="mt-1 block text-xs leading-5 text-slate-500">Bu kategorideki risk bildirimleri.</span>
                        </span>
                    </label>
                @endforeach
            </div>
        </section>
    </div>
</div>
