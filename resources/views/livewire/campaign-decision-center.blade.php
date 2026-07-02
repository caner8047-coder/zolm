@php
    $summary = $dashboard['summary'];
    $modules = $dashboard['modules'];
    $queue = $dashboard['queue'];
    $breakdown = $dashboard['decision_breakdown'];
    $recentReports = $dashboard['recent_reports'];
    $trend = collect($dashboard['trend']);
    $formatMoney = function ($value) {
        $amount = (float) $value;

        return ($amount < 0 ? '-₺' : '₺') . number_format(abs($amount), 2, ',', '.');
    };
    $formatCompactMoney = function ($value) {
        $amount = (float) $value;
        $prefix = $amount < 0 ? '-₺' : '₺';
        $absolute = abs($amount);

        if ($absolute >= 1000000) {
            return $prefix . number_format($absolute / 1000000, 2, ',', '.') . ' Mn';
        }

        if ($absolute >= 1000) {
            return $prefix . number_format($absolute / 1000, 1, ',', '.') . ' Bin';
        }

        return $prefix . number_format($absolute, 0, ',', '.');
    };
    $formatCount = fn ($value) => number_format((float) $value, 0, ',', '.');
    $formatPercent = fn ($value) => '%' . number_format((float) $value, 1, ',', '.');
    $toneClasses = [
        'slate' => ['soft' => 'bg-slate-50', 'border' => 'border-slate-200', 'text' => 'text-slate-700', 'bar' => 'bg-slate-600'],
        'indigo' => ['soft' => 'bg-indigo-50', 'border' => 'border-indigo-200', 'text' => 'text-indigo-700', 'bar' => 'bg-indigo-500'],
        'amber' => ['soft' => 'bg-amber-50', 'border' => 'border-amber-200', 'text' => 'text-amber-700', 'bar' => 'bg-amber-500'],
        'sky' => ['soft' => 'bg-sky-50', 'border' => 'border-sky-200', 'text' => 'text-sky-700', 'bar' => 'bg-sky-500'],
        'emerald' => ['soft' => 'bg-emerald-50', 'border' => 'border-emerald-200', 'text' => 'text-emerald-700', 'bar' => 'bg-emerald-500'],
        'rose' => ['soft' => 'bg-rose-50', 'border' => 'border-rose-200', 'text' => 'text-rose-700', 'bar' => 'bg-rose-500'],
    ];
    $scoreTone = $summary['decision_score'] >= 85 ? 'emerald' : ($summary['decision_score'] >= 65 ? 'slate' : ($summary['decision_score'] >= 45 ? 'amber' : 'rose'));
    $scoreClasses = $toneClasses[$scoreTone];
    $trendMax = max(1, (float) $trend->max(fn (array $row) => abs((float) $row['value'])));
    $maxDecisionCount = max(1, (int) collect($breakdown)->max('count'));
    $activeFilterCount = collect([$search, $campaignTypeFilter, $decisionFilter])->filter(fn ($value) => $value !== '')->count();
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
        <div class="grid grid-cols-1 gap-4 xl:grid-cols-12">
            <div class="rounded-[10px] border border-slate-200 bg-slate-50/60 p-4 lg:p-5 xl:col-span-4">
                <div class="inline-flex rounded-[6px] border border-slate-200 bg-white px-2.5 py-1 text-xs font-mono text-slate-500">
                    ZOLM Kampanya Yönetimi
                </div>
                <h1 class="mt-3 text-xl font-bold text-slate-900 lg:text-2xl">Kampanya Karar Merkezi</h1>
                <p class="mt-2 text-sm leading-6 text-slate-500">
                    Komisyon, etiket, flaş ve sepet kampanyalarını aynı kâr standardıyla karşılaştırın; yalnız güvenli fırsatları onaylayın.
                </p>

                <div class="mt-5 rounded-[8px] border border-slate-200 bg-white p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-mono text-slate-500">Karar skoru</p>
                            <p class="mt-2 text-3xl font-bold {{ $scoreClasses['text'] }}">{{ $formatPercent($summary['decision_score']) }}</p>
                        </div>
                        <span class="rounded-[6px] border px-2 py-1 text-xs font-medium {{ $scoreClasses['border'] }} {{ $scoreClasses['soft'] }} {{ $scoreClasses['text'] }}">
                            {{ $summary['score_label'] }}
                        </span>
                    </div>
                    <div class="mt-3 h-2 overflow-hidden rounded-[6px] bg-slate-100">
                        <div class="h-full rounded-[6px] {{ $scoreClasses['bar'] }}" style="width: {{ $summary['decision_score'] }}%"></div>
                    </div>
                    <p class="mt-3 text-xs leading-5 text-slate-500">
                        Son {{ $summary['report_count'] }} raporun maliyet, eşleşme ve kâr güveni birlikte değerlendirilir.
                    </p>
                </div>

                <div class="mt-3 flex flex-col gap-2 sm:flex-row xl:flex-col">
                    <a href="{{ route('campaigns.index') }}"
                       class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 sm:w-auto sm:flex-1 sm:py-2 xl:w-full">
                        Rapor arşivi
                    </a>
                    <button type="button"
                            wire:click="exportDecisionReport"
                            wire:loading.attr="disabled"
                            wire:target="exportDecisionReport"
                            class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 disabled:cursor-wait disabled:opacity-60 sm:w-auto sm:flex-1 sm:py-2 xl:w-full">
                        <span wire:loading.remove wire:target="exportDecisionReport">Karar raporunu indir</span>
                        <span wire:loading wire:target="exportDecisionReport">Hazırlanıyor...</span>
                    </button>
                </div>
            </div>

            <div class="xl:col-span-8">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                        <p class="text-xs font-mono text-slate-500">Analiz edilen ürün</p>
                        <p class="mt-2 text-2xl font-bold text-slate-900">{{ $formatCount($summary['product_count']) }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $summary['campaign_type_count'] }} kampanya türü</p>
                    </div>
                    <div class="min-w-0 rounded-[8px] border border-emerald-200 bg-emerald-50/70 p-4">
                        <p class="text-xs font-mono text-emerald-700">Onaylanabilir ek kâr</p>
                        <p class="mt-2 truncate text-2xl font-bold text-emerald-700">{{ $formatCompactMoney($summary['potential_profit']) }}</p>
                        <p class="mt-1 text-xs text-emerald-700/75">{{ $formatCount($summary['approve_count']) }} güvenli ürün</p>
                    </div>
                    <div class="min-w-0 rounded-[8px] border border-rose-200 bg-rose-50/70 p-4">
                        <p class="text-xs font-mono text-rose-700">Risk altındaki kâr</p>
                        <p class="mt-2 truncate text-2xl font-bold text-rose-700">{{ $formatCompactMoney($summary['risk_exposure']) }}</p>
                        <p class="mt-1 text-xs text-rose-700/75">{{ $formatCount($summary['risk_count']) }} ürün incelenmeli</p>
                    </div>
                    <div class="min-w-0 rounded-[8px] border border-amber-200 bg-amber-50/70 p-4">
                        <p class="text-xs font-mono text-amber-700">Seçili karar etkisi</p>
                        <p class="mt-2 truncate text-2xl font-bold {{ $summary['selected_impact'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $formatCompactMoney($summary['selected_impact']) }}</p>
                        <p class="mt-1 text-xs text-amber-700/75">{{ $formatCount($summary['selected_count']) }} seçili ürün</p>
                    </div>
                </div>

                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div class="rounded-[8px] border border-slate-200 bg-white p-4 sm:col-span-2">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-mono text-slate-500">Karar sonrası kâr tabanı</p>
                                <p class="mt-2 text-2xl font-bold text-slate-900">{{ $formatCompactMoney($summary['decision_profit']) }}</p>
                            </div>
                            <p class="text-right text-xs text-slate-500">
                                Mevcut {{ $formatCompactMoney($summary['current_profit']) }}<br>
                                <span class="{{ $summary['raw_impact'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">Kaynak öneri {{ $formatCompactMoney($summary['raw_impact']) }}</span>
                            </p>
                        </div>
                        <p class="mt-3 text-xs leading-5 text-slate-500">
                            Karar sonrası değer yalnız pozitif, maliyeti hazır ve zarar üretmeyen önerileri ekler. Modüller arası aynı ürün tekrar edebilir.
                        </p>
                    </div>
                    <div class="rounded-[8px] border border-slate-200 bg-white p-4">
                        <p class="text-xs font-mono text-slate-500">Maliyet kapsamı</p>
                        <p class="mt-2 text-2xl font-bold {{ $summary['cost_coverage'] >= 90 ? 'text-emerald-700' : 'text-amber-700' }}">{{ $formatPercent($summary['cost_coverage']) }}</p>
                        <div class="mt-3 h-2 overflow-hidden rounded-[6px] bg-slate-100">
                            <div class="h-full rounded-[6px] {{ $summary['cost_coverage'] >= 90 ? 'bg-emerald-500' : 'bg-amber-500' }}" style="width: {{ $summary['cost_coverage'] }}%"></div>
                        </div>
                        <p class="mt-2 text-xs text-slate-500">{{ $formatCount($summary['unmatched_count']) }} eşleşmeyen kayıt</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-base font-semibold text-slate-900">Kampanya modülleri</h2>
                <p class="mt-1 text-sm text-slate-500">Her modülün en güncel raporu aynı karar kriterleriyle karşılaştırılır.</p>
            </div>
            <span class="text-xs font-mono text-slate-400">Son analiz: {{ $summary['latest_at'] ?: 'Henüz yok' }}</span>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
            @foreach($modules as $module)
                @php
                    $tone = $toneClasses[$module['tone']] ?? $toneClasses['slate'];
                    $isActive = $campaignTypeFilter === $module['campaign_type'];
                @endphp
                <article class="min-w-0 rounded-[8px] border p-4 transition {{ $isActive ? 'border-slate-900 bg-slate-50 shadow-sm' : 'border-slate-200 bg-white hover:bg-slate-50/60' }}">
                    <button type="button" wire:click="focusCampaignType('{{ $module['campaign_type'] }}')" class="block w-full text-left">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-900" title="{{ $module['label'] }}">{{ $module['short_label'] }}</p>
                                <p class="mt-1 truncate text-xs text-slate-500">{{ $module['report_date'] ?: 'Rapor yok' }}</p>
                            </div>
                            <span class="rounded-[6px] border px-2 py-0.5 text-xs font-mono {{ $tone['border'] }} {{ $tone['soft'] }} {{ $tone['text'] }}">{{ $module['product_count'] }}</span>
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-2 text-xs">
                            <div class="rounded-[6px] bg-emerald-50 p-2">
                                <p class="text-emerald-700/75">Onay</p>
                                <p class="mt-1 font-bold text-emerald-700">{{ $module['approve_count'] }}</p>
                            </div>
                            <div class="rounded-[6px] bg-rose-50 p-2">
                                <p class="text-rose-700/75">Risk</p>
                                <p class="mt-1 font-bold text-rose-700">{{ $module['risk_count'] }}</p>
                            </div>
                        </div>

                        <div class="mt-3">
                            <div class="flex items-center justify-between gap-3 text-xs">
                                <span class="text-slate-500">Ek kâr</span>
                                <span class="font-semibold text-emerald-700">{{ $formatCompactMoney($module['potential_profit']) }}</span>
                            </div>
                            <div class="mt-1.5 flex items-center justify-between gap-3 text-xs">
                                <span class="text-slate-500">Risk tutarı</span>
                                <span class="font-semibold {{ $module['risk_exposure'] > 0 ? 'text-rose-700' : 'text-slate-600' }}">{{ $formatCompactMoney($module['risk_exposure']) }}</span>
                            </div>
                        </div>
                    </button>

                    <a href="{{ $this->moduleUrl($module) }}"
                       class="mt-4 inline-flex min-h-[40px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-400 hover:bg-slate-50">
                        {{ $module['has_report'] ? 'Son raporu aç' : 'Analiz oluştur' }}
                    </a>
                </article>
            @endforeach
        </div>
    </section>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-12">
        <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6 xl:col-span-8">
            <div>
                <h2 class="text-base font-semibold text-slate-900">Rapor etki trendi</h2>
                <p class="mt-1 text-sm text-slate-500">Son analizlerin kaynak dosyada hesaplanan ek kâr etkisini karşılaştırın.</p>
            </div>

            @if($trend->isEmpty())
                <div class="mt-5 rounded-[8px] border border-dashed border-slate-300 bg-slate-50/60 px-4 py-12 text-center text-sm text-slate-500">
                    Trend oluşturmak için kampanya analizi yükleyin.
                </div>
            @else
                <div class="mt-5 overflow-x-auto pb-2">
                    <div class="flex min-w-[680px] items-stretch gap-2">
                        @foreach($trend as $row)
                            @php
                                $height = max(5, round((abs((float) $row['value']) / $trendMax) * 82, 1));
                                $tone = $toneClasses[$row['tone']] ?? $toneClasses['slate'];
                            @endphp
                            <a href="{{ route($row['route'], ['report' => $row['id']]) }}"
                               class="group flex min-w-[54px] flex-1 flex-col">
                                <div class="flex h-24 items-end justify-center rounded-t-[6px] bg-slate-50 px-2">
                                    <div class="w-full max-w-8 rounded-t-[4px] {{ $row['value'] >= 0 ? $tone['bar'] : 'bg-rose-500' }}" style="height: {{ $height }}%"></div>
                                </div>
                                <div class="border-t border-slate-200 pt-2 text-center">
                                    <p class="truncate text-[10px] font-medium text-slate-600">{{ $row['label'] }}</p>
                                    <p class="mt-1 truncate text-[10px] font-semibold {{ $row['value'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $formatCompactMoney($row['value']) }}</p>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </section>

        <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6 xl:col-span-4">
            <div>
                <h2 class="text-base font-semibold text-slate-900">Karar dağılımı</h2>
                <p class="mt-1 text-sm text-slate-500">Ürünleri aksiyon biçimine göre odaklayın.</p>
            </div>

            <div class="mt-5 space-y-3">
                @foreach($breakdown as $decision)
                    @php
                        $tone = $toneClasses[$decision['tone']] ?? $toneClasses['slate'];
                        $width = max(3, round(($decision['count'] / $maxDecisionCount) * 100, 1));
                        $isActive = $decisionFilter === $decision['key'];
                    @endphp
                    <button type="button"
                            wire:click="focusDecision('{{ $decision['key'] }}')"
                            class="block w-full rounded-[8px] border p-3 text-left transition {{ $isActive ? 'border-slate-900 bg-slate-50' : 'border-slate-200 bg-white hover:bg-slate-50' }}">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium text-slate-800">{{ $decision['label'] }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">{{ $formatCount($decision['count']) }} ürün</p>
                            </div>
                            <p class="text-sm font-semibold {{ $tone['text'] }}">{{ $formatCompactMoney($decision['amount']) }}</p>
                        </div>
                        <div class="mt-2 h-2 overflow-hidden rounded-[6px] bg-slate-100">
                            <div class="h-full rounded-[6px] {{ $tone['bar'] }}" style="width: {{ $width }}%"></div>
                        </div>
                    </button>
                @endforeach
            </div>
        </section>
    </div>

    <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 p-4 lg:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Kampanya karar kuyruğu</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $dashboard['queue_total'] }} ürün seçili odakta. Karar, kâr ve veri hazırlığı birlikte değerlendirilir.</p>
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

            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-[2fr_1fr_1fr_auto]">
                <div>
                    <label class="mb-1.5 block text-xs font-medium text-slate-500">Ürün, stok kodu, barkod veya rapor ara</label>
                    <input type="search"
                           wire:model.live.debounce.350ms="search"
                           placeholder="Ürün veya stok kodu..."
                           class="min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 outline-none transition focus:border-slate-400 sm:text-sm">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium text-slate-500">Kampanya türü</label>
                    <select wire:model.live="campaignTypeFilter" class="min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 sm:text-sm">
                        <option value="">Tümü</option>
                        @foreach($typeDefinitions as $key => $definition)
                            <option value="{{ $key }}">{{ $definition['short_label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium text-slate-500">Karar</label>
                    <select wire:model.live="decisionFilter" class="min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 sm:text-sm">
                        <option value="">Tümü</option>
                        @foreach($decisionDefinitions as $key => $definition)
                            <option value="{{ $key }}">{{ $definition['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="button"
                        wire:click="resetFilters"
                        class="min-h-[44px] w-full self-end rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 sm:w-auto sm:py-2">
                    Sıfırla
                </button>
            </div>

            <div class="mt-3 flex flex-col gap-2 rounded-[8px] border border-slate-200 bg-slate-50/60 px-3 py-2 text-xs text-slate-600 sm:flex-row sm:items-center sm:justify-between">
                <span>{{ $activeFilterCount > 0 ? $activeFilterCount . ' aktif filtre uygulanıyor' : 'En güncel kampanya raporları gösteriliyor' }}</span>
                <span>{{ count($dashboard['latest_report_ids']) }} rapor · {{ $formatCount($dashboard['queue_total']) }} ürün</span>
            </div>
        </div>

        <div wire:loading.flex
             wire:target="search,campaignTypeFilter,decisionFilter,resetFilters,focusCampaignType,focusDecision,sortTable"
             class="items-center justify-center border-b border-slate-200 bg-slate-50/60 px-4 py-3 text-sm text-slate-500">
            Karar kuyruğu güncelleniyor...
        </div>

        <div class="p-4 lg:p-6">
            <div class="space-y-3 md:hidden">
                @forelse($queue as $row)
                    @php
                        $decisionTone = $toneClasses[$row['decision_tone']] ?? $toneClasses['slate'];
                    @endphp
                    <article wire:key="campaign-card-{{ $row['id'] }}" class="rounded-[8px] border border-slate-200 bg-white p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-900">{{ $row['product_name'] }}</p>
                                <p class="mt-1 truncate text-xs text-slate-500">{{ $row['stock_code'] }} · {{ $row['campaign_label'] }}</p>
                            </div>
                            <span class="shrink-0 rounded-[6px] border px-2 py-1 text-xs font-medium {{ $decisionTone['border'] }} {{ $decisionTone['soft'] }} {{ $decisionTone['text'] }}">{{ $row['decision_label'] }}</span>
                        </div>

                        <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                            <div class="rounded-[6px] bg-slate-50 p-2">
                                <p class="text-slate-500">Mevcut kâr</p>
                                <p class="mt-1 font-semibold {{ $row['current_profit'] >= 0 ? 'text-slate-900' : 'text-rose-700' }}">{{ $formatMoney($row['current_profit']) }}</p>
                            </div>
                            <div class="rounded-[6px] bg-slate-50 p-2">
                                <p class="text-slate-500">Önerilen kâr</p>
                                <p class="mt-1 font-semibold {{ $row['suggested_profit'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $formatMoney($row['suggested_profit']) }}</p>
                            </div>
                            <div class="rounded-[6px] bg-slate-50 p-2">
                                <p class="text-slate-500">Ek kâr etkisi</p>
                                <p class="mt-1 font-semibold {{ $row['extra_profit'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $formatMoney($row['extra_profit']) }}</p>
                            </div>
                            <div class="rounded-[6px] bg-slate-50 p-2">
                                <p class="text-slate-500">Toplam maliyet</p>
                                <p class="mt-1 font-semibold {{ $row['total_cost'] > 0 ? 'text-slate-900' : 'text-amber-700' }}">{{ $formatMoney($row['total_cost']) }}</p>
                            </div>
                        </div>

                        @if($row['risk_reasons'] !== [])
                            <div class="mt-3 flex flex-wrap gap-1.5">
                                @foreach($row['risk_reasons'] as $reason)
                                    <span class="rounded-[6px] border border-rose-200 bg-rose-50 px-2 py-1 text-xs text-rose-700">{{ $reason }}</span>
                                @endforeach
                            </div>
                        @endif

                        <p class="mt-3 text-xs leading-5 text-slate-600">{{ $row['action_hint'] }}</p>
                        <a href="{{ $this->rowUrl($row) }}" class="mt-3 inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] bg-slate-900 px-3 py-2 text-sm font-medium text-white">Kaynak raporda aç</a>
                    </article>
                @empty
                    <div class="rounded-[8px] border border-dashed border-slate-300 bg-slate-50/60 px-4 py-10 text-center text-sm text-slate-500">
                        Seçilen filtrelere uygun kampanya kararı bulunamadı.
                    </div>
                @endforelse
            </div>

            <div class="hidden rounded-lg border border-slate-200 bg-white md:block" x-data="columnResize()">
                <div class="overflow-x-auto [scrollbar-gutter:stable]">
                    <table class="w-full min-w-[1040px] table-fixed">
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
                                @php
                                    $decisionTone = $toneClasses[$row['decision_tone']] ?? $toneClasses['slate'];
                                @endphp
                                <tr wire:key="campaign-row-{{ $row['id'] }}" class="transition hover:bg-slate-50/80">
                                    @if(in_array('product', $visibleColumns, true))
                                        <td class="overflow-hidden px-3 py-3 align-top">
                                            <p class="truncate font-semibold text-slate-900" title="{{ $row['product_name'] }}">{{ $row['product_name'] }}</p>
                                            <p class="mt-1 truncate text-xs text-slate-500">{{ $row['stock_code'] ?: 'Stok kodu yok' }}</p>
                                        </td>
                                    @endif
                                    @if(in_array('campaign', $visibleColumns, true))
                                        <td class="overflow-hidden px-3 py-3 align-top">
                                            <p class="truncate font-medium text-slate-900">{{ $row['campaign_label'] }}</p>
                                            <p class="mt-1 truncate text-xs text-slate-500" title="{{ $row['report_name'] }}">{{ $row['report_name'] }}</p>
                                            <p class="mt-1 text-xs text-slate-400">{{ $row['report_date'] }}</p>
                                        </td>
                                    @endif
                                    @if(in_array('decision', $visibleColumns, true))
                                        <td class="overflow-hidden px-3 py-3 align-top">
                                            <span class="inline-flex rounded-[6px] border px-2 py-1 text-xs font-medium {{ $decisionTone['border'] }} {{ $decisionTone['soft'] }} {{ $decisionTone['text'] }}">{{ $row['decision_label'] }}</span>
                                            <p class="mt-2 truncate text-xs text-slate-500">{{ $row['risk_reasons'][0] ?? 'Kâr koşulları uygun' }}</p>
                                        </td>
                                    @endif
                                    @if(in_array('current', $visibleColumns, true))
                                        <td class="overflow-hidden px-3 py-3 text-right align-top">
                                            <p class="font-semibold {{ $row['current_profit'] >= 0 ? 'text-slate-900' : 'text-rose-700' }}">{{ $formatMoney($row['current_profit']) }}</p>
                                            <p class="mt-1 truncate text-xs text-slate-500">{{ $formatMoney($row['current_price']) }} · %{{ number_format($row['current_commission'], 1, ',', '.') }}</p>
                                        </td>
                                    @endif
                                    @if(in_array('suggested', $visibleColumns, true))
                                        <td class="overflow-hidden px-3 py-3 text-right align-top">
                                            <p class="font-semibold {{ $row['suggested_profit'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $formatMoney($row['suggested_profit']) }}</p>
                                            <p class="mt-1 truncate text-xs text-slate-500">{{ $formatMoney($row['suggested_price']) }} · %{{ number_format($row['suggested_commission'], 1, ',', '.') }}</p>
                                        </td>
                                    @endif
                                    @if(in_array('impact', $visibleColumns, true))
                                        <td class="overflow-hidden px-3 py-3 text-right align-top">
                                            <p class="font-semibold {{ $row['extra_profit'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $formatMoney($row['extra_profit']) }}</p>
                                            <p class="mt-1 text-xs text-slate-500">{{ $row['selected'] ? 'Seçili' : 'Seçili değil' }}</p>
                                        </td>
                                    @endif
                                    @if(in_array('cost', $visibleColumns, true))
                                        <td class="overflow-hidden px-3 py-3 text-right align-top">
                                            <p class="font-semibold {{ $row['total_cost'] > 0 ? 'text-slate-900' : 'text-amber-700' }}">{{ $formatMoney($row['total_cost']) }}</p>
                                            <p class="mt-1 text-xs text-slate-500">{{ $row['total_cost'] > 0 ? 'Hazır' : 'Eksik' }}</p>
                                        </td>
                                    @endif
                                    @if(in_array('action', $visibleColumns, true))
                                        <td class="px-3 py-3 text-right align-top">
                                            <a href="{{ $this->rowUrl($row) }}" class="inline-flex min-h-[36px] items-center justify-center rounded-[6px] bg-slate-900 px-3 py-2 text-xs font-medium text-white">Raporda aç</a>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ max(1, count($visibleColumns)) }}" class="px-4 py-12 text-center text-sm text-slate-500">
                                        Seçilen filtrelere uygun kampanya kararı bulunamadı.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-base font-semibold text-slate-900">Son analizler</h2>
                <p class="mt-1 text-sm text-slate-500">Geçmiş raporlara kaynak modülü üzerinden dönün.</p>
            </div>
            <a href="{{ route('campaigns.index') }}" class="text-sm font-medium text-slate-700 hover:text-slate-900">Tüm raporları aç</a>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @forelse($recentReports as $report)
                @php
                    $tone = $toneClasses[$report['tone']] ?? $toneClasses['slate'];
                @endphp
                <a href="{{ $this->reportUrl($report) }}" class="min-w-0 rounded-[8px] border border-slate-200 bg-white p-4 transition hover:border-slate-400 hover:bg-slate-50/60">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-slate-900" title="{{ $report['name'] }}">{{ $report['name'] }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $report['created_at'] }}</p>
                        </div>
                        <span class="shrink-0 rounded-[6px] border px-2 py-0.5 text-xs font-mono {{ $tone['border'] }} {{ $tone['soft'] }} {{ $tone['text'] }}">{{ $report['campaign_label'] }}</span>
                    </div>
                    <div class="mt-4 flex items-end justify-between gap-3">
                        <div>
                            <p class="text-xs text-slate-500">{{ $formatCount($report['total_products']) }} ürün</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $formatCount($report['opportunity_count']) }} kaynak fırsat</p>
                        </div>
                        <p class="text-sm font-bold {{ $report['total_extra_profit'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $formatCompactMoney($report['total_extra_profit']) }}</p>
                    </div>
                </a>
            @empty
                <div class="sm:col-span-2 xl:col-span-4 rounded-[8px] border border-dashed border-slate-300 bg-slate-50/60 px-4 py-10 text-center text-sm text-slate-500">
                    Henüz kampanya analizi bulunmuyor.
                </div>
            @endforelse
        </div>
    </section>
</div>
