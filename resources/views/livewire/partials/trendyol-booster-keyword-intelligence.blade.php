@php
    $keywordIntelligence = (array) ($keywordLookupDashboard['intelligence'] ?? []);
    $currentKeywordLookup = $keywordLookupDashboard['current'] ?? null;
    $keywordScores = (array) ($keywordIntelligence['scores'] ?? []);
    $keywordScoreLabels = (array) ($keywordIntelligence['score_labels'] ?? []);
    $keywordClusters = (array) ($keywordIntelligence['clusters'] ?? []);
    $keywordLongTail = (array) ($keywordIntelligence['long_tail'] ?? []);
    $keywordBrands = (array) ($keywordIntelligence['brands'] ?? []);
    $keywordTitlePlan = (array) ($keywordIntelligence['title_plan'] ?? []);
    $keywordQuality = (array) ($keywordIntelligence['quality'] ?? []);
    $keywordMarketOptions = (array) ($keywordTitlePlan['market_options'] ?? []);
    $keywordTitleRules = array_values(array_filter(
        (array) ($keywordTitlePlan['rules'] ?? []),
        fn (mixed $rule): bool => is_string($rule) && trim($rule) !== '',
    ));
    $keywordRisks = (array) ($keywordIntelligence['risks'] ?? []);
    $keywordRecommendations = (array) ($keywordIntelligence['recommendations'] ?? []);
    $keywordScoreTone = fn (int $score, bool $inverse = false) => $inverse
        ? ($score >= 72 ? 'text-rose-700' : ($score >= 45 ? 'text-amber-700' : 'text-emerald-700'))
        : ($score >= 72 ? 'text-emerald-700' : ($score >= 45 ? 'text-amber-700' : 'text-rose-700'));
    $keywordIntentTone = fn (string $intent) => match ($intent) {
        'product' => 'border-slate-300 bg-slate-100 text-slate-700',
        'material' => 'border-amber-200 bg-amber-50 text-amber-700',
        'feature' => 'border-sky-200 bg-sky-50 text-sky-700',
        'form' => 'border-cyan-200 bg-cyan-50 text-cyan-700',
        'style' => 'border-violet-200 bg-violet-50 text-violet-700',
        'use_case' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
        'color' => 'border-rose-200 bg-rose-50 text-rose-700',
        default => 'border-slate-200 bg-white text-slate-600',
    };
    $keywordSortIcon = fn (string $column) => $keywordIntelligenceSortField === $column
        ? ($keywordIntelligenceSortDirection === 'asc' ? '▲' : '▼')
        : '⇅';
@endphp

<div class="flex flex-col sm:flex-row">
    <div
        x-data="{ bridgeBusy: false, columnsOpen: false, copied: false }"
        x-on:booster-keyword-lookup-busy.window="bridgeBusy = true"
        x-on:booster-keyword-lookup-finished.window="bridgeBusy = false"
        class="w-full space-y-4 lg:space-y-6"
    >
        <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0">
                    <div class="inline-flex items-center gap-2 rounded-[6px] border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">
                        <x-lucide.icon name="scan-search" class="h-3.5 w-3.5" />
                        Keyword Intelligence v{{ $keywordIntelligence['version'] ?? \App\Services\Marketplace\TrendyolKeywordIntelligenceService::VERSION }}
                    </div>
                    <h2 class="mt-3 text-xl font-bold tracking-tight text-slate-950 lg:text-2xl">Anahtar Kelime İstihbaratı</h2>
                    <p class="mt-1 max-w-3xl text-sm leading-6 text-slate-500">
                        Trendyol'un üst sıralarındaki ürün başlıklarını; sıra gücü, sorgu yakınlığı, arama niyeti, rekabet ve uzun kuyruk fırsatıyla birlikte analiz eder.
                    </p>
                </div>

                <div class="grid min-w-0 grid-cols-3 gap-2 lg:min-w-[330px]">
                    <div class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/70 px-3 py-2.5">
                        <p class="truncate text-[11px] text-slate-500">Analiz</p>
                        <p class="mt-1 text-lg font-bold text-slate-900">{{ $keywordLookupDashboard['successful_total'] ?? 0 }}</p>
                    </div>
                    <div class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/70 px-3 py-2.5">
                        <p class="truncate text-[11px] text-slate-500">Sorgu</p>
                        <p class="mt-1 text-lg font-bold text-slate-900">{{ $keywordLookupDashboard['unique_keywords'] }}</p>
                    </div>
                    <div class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/70 px-3 py-2.5">
                        <p class="truncate text-[11px] text-slate-500">Örneklem</p>
                        <p class="mt-1 text-lg font-bold text-slate-900">{{ $keywordIntelligence['sample_size'] ?? 0 }}</p>
                    </div>
                </div>
            </div>

            <div class="mt-4 rounded-[8px] border border-slate-200 bg-slate-50/60 p-3 lg:p-4">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
                    <label class="min-w-0 flex-1">
                        <span class="text-xs font-semibold text-slate-700">Satmak istediğiniz ürün veya kategori</span>
                        <div class="relative mt-1.5">
                            <x-lucide.icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input
                                type="search"
                                wire:model.defer="keywordLookupInput"
                                placeholder="Örnek: berjer, sandıklı puf, ahşap tabure"
                                class="w-full rounded-[6px] border border-slate-200 bg-white py-3 pl-10 pr-3 text-base text-slate-900 outline-none transition focus:border-slate-400 sm:py-2 sm:text-sm"
                            >
                        </div>
                        @error('keywordLookupInput') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </label>
                    <button
                        type="button"
                        wire:click="runKeywordLookup"
                        wire:loading.attr="disabled"
                        wire:target="runKeywordLookup,keywordLookupServerFallback,keywordLookupBridgeCompleted"
                        x-bind:disabled="bridgeBusy"
                        @disabled(!$keywordLookupReady)
                        class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] bg-slate-900 px-4 py-3 text-base font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto sm:py-2 sm:text-sm"
                    >
                        <span x-show="!bridgeBusy" class="inline-flex items-center gap-2">
                            <x-lucide.icon name="sparkles" class="h-4 w-4" />
                            Profesyonel Analizi Başlat
                        </span>
                        <span x-show="bridgeBusy" x-cloak class="inline-flex items-center gap-2">
                            <x-lucide.icon name="loader-circle" class="h-4 w-4 animate-spin" />
                            Pazar sinyalleri okunuyor...
                        </span>
                    </button>
                </div>

                @if($currentKeywordLookup)
                    <div class="mt-3 flex flex-col gap-2 border-t border-slate-200 pt-3 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex min-w-0 flex-wrap items-center gap-2">
                            <span class="rounded-[6px] border border-emerald-200 bg-emerald-50 px-2 py-1 font-medium text-emerald-700">Aktif analiz: {{ $currentKeywordLookup->keyword }}</span>
                            <span>{{ $currentKeywordLookup->searched_at?->format('d.m.Y H:i') }}</span>
                        </div>
                        <span class="font-mono text-slate-600">{{ number_format((int) $currentKeywordLookup->result_count, 0, ',', '.') }} pazar sonucu</span>
                    </div>
                @endif
            </div>
        </section>

        <details class="group rounded-[10px] border border-slate-200 bg-white shadow-sm">
            <summary class="flex min-h-[44px] cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 lg:px-6">
                <div class="flex min-w-0 items-center gap-3">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-[6px] border border-slate-200 bg-slate-50 text-slate-600">
                        <x-lucide.icon name="circle-help" class="h-4 w-4" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-900">Skorlar nasıl hesaplanıyor?</p>
                        <p class="truncate text-xs text-slate-500">Rank ağırlığı, kapsama, sorgu yakınlığı, niyet ve zorluk bileşenleri</p>
                    </div>
                </div>
                <x-lucide.icon name="chevron-down" class="h-4 w-4 shrink-0 text-slate-400 transition group-open:rotate-180" />
            </summary>
            <div class="grid grid-cols-1 gap-3 border-t border-slate-200 px-4 py-4 sm:grid-cols-2 xl:grid-cols-4 lg:px-6">
                @foreach((array) ($keywordIntelligence['methodology'] ?? []) as $methodologyItem)
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3 text-xs leading-5 text-slate-600">{{ $methodologyItem }}</div>
                @endforeach
            </div>
        </details>

        @if($currentKeywordLookup && !empty($keywordIntelligence))
            <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Pazar özeti</p>
                        <h3 class="mt-1 text-lg font-semibold text-slate-900">“{{ $currentKeywordLookup->keyword }}” karar skoru</h3>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ $keywordIntelligence['source_sample_size'] ?? $keywordIntelligence['sample_size'] ?? 0 }} kaynaktan
                            {{ $keywordIntelligence['sample_size'] ?? 0 }} kategoriyle uyumlu, tekil başlık analiz edildi;
                            {{ number_format((int) ($keywordIntelligence['result_count'] ?? 0), 0, ',', '.') }} pazar sonucu dikkate alındı.
                        </p>
                        @if(($keywordQuality['off_topic_count'] ?? 0) > 0 || ($keywordQuality['duplicate_count'] ?? 0) > 0 || ($keywordQuality['brand_capped_count'] ?? 0) > 0)
                            <div class="mt-2 flex flex-wrap gap-2 text-[11px] text-slate-500">
                                <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-1">{{ $keywordQuality['off_topic_count'] ?? 0 }} kategori dışı elendi</span>
                                <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-1">{{ $keywordQuality['duplicate_count'] ?? 0 }} yakın kopya çıkarıldı</span>
                                <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-1">{{ $keywordQuality['brand_capped_count'] ?? 0 }} marka tekrarı sınırlandı</span>
                            </div>
                        @endif
                    </div>
                    <span class="shrink-0 rounded-[6px] border border-slate-200 bg-slate-50 px-2.5 py-1 font-mono text-xs text-slate-600">Model v{{ $keywordIntelligence['version'] ?? 1 }}</span>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach([
                        ['key' => 'opportunity', 'label' => 'Fırsat skoru', 'inverse' => false, 'icon' => 'target'],
                        ['key' => 'competition', 'label' => 'Rekabet baskısı', 'inverse' => true, 'icon' => 'bar-chart-3'],
                        ['key' => 'query_coverage', 'label' => 'Sorgu kapsaması', 'inverse' => false, 'icon' => 'scan-text'],
                        ['key' => 'confidence', 'label' => 'Veri güveni', 'inverse' => false, 'icon' => 'shield-check'],
                    ] as $scoreCard)
                        @php($scoreValue = (int) ($keywordScores[$scoreCard['key']] ?? 0))
                        <div class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-xs font-medium text-slate-500">{{ $scoreCard['label'] }}</p>
                                    <p class="mt-2 text-2xl font-bold tracking-tight {{ $keywordScoreTone($scoreValue, $scoreCard['inverse']) }}">{{ $scoreValue }}<span class="text-sm font-medium text-slate-400">/100</span></p>
                                </div>
                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-[6px] border border-slate-200 bg-white text-slate-500">
                                    <x-lucide.icon name="{{ $scoreCard['icon'] }}" class="h-4 w-4" />
                                </div>
                            </div>
                            <div class="mt-3 h-1.5 overflow-hidden rounded bg-slate-200">
                                <div class="h-full rounded bg-slate-900" style="width: {{ $scoreValue }}%"></div>
                            </div>
                            <p class="mt-2 text-[11px] font-medium text-slate-500">{{ $keywordScoreLabels[$scoreCard['key']] ?? '—' }}</p>
                        </div>
                    @endforeach
                </div>
            </section>

            <div class="grid grid-cols-1 gap-4 xl:grid-cols-12 lg:gap-6">
                <section class="min-w-0 rounded-[10px] border border-slate-200 bg-white shadow-sm xl:col-span-8" x-on:click.outside="columnsOpen = false">
                    <div class="border-b border-slate-200 p-4 lg:p-5">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Keyword ledger</p>
                                <h3 class="mt-1 text-lg font-semibold text-slate-900">Öncelikli kelime havuzu</h3>
                                <p class="mt-1 text-sm text-slate-500">Her adayın anlam gücü, kapsaması, fırsatı ve rekabet zorluğu ayrı hesaplanır.</p>
                            </div>
                            <div class="relative flex shrink-0 items-center gap-2">
                                <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2.5 py-2 text-xs text-slate-600">{{ count($this->keywordIntelligenceRows) }} aday</span>
                                <button type="button" x-on:click="columnsOpen = !columnsOpen" class="inline-flex min-h-[40px] items-center justify-center gap-2 rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50">
                                    <x-lucide.icon name="columns-3" class="h-4 w-4" />
                                    Kolonlar
                                </button>
                                <div x-show="columnsOpen" x-cloak class="absolute right-0 top-11 z-30 w-52 rounded-[8px] border border-slate-200 bg-white p-2 shadow-md">
                                    @foreach(\App\Livewire\TrendyolBooster::$keywordIntelligenceColumnDefinitions as $columnKey => $columnLabel)
                                        <label class="flex min-h-[36px] items-center gap-2 rounded-[6px] px-2 text-xs text-slate-700 hover:bg-slate-50">
                                            <input type="checkbox" wire:click="toggleKeywordIntelligenceColumn('{{ $columnKey }}')" @checked(in_array($columnKey, $keywordIntelligenceVisibleColumns, true)) @disabled($columnKey === 'keyword') class="rounded border-slate-300 text-slate-900 focus:ring-slate-500">
                                            <span>{{ $columnLabel }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <div class="mt-3 flex flex-wrap items-center gap-2 text-[11px] text-slate-500">
                            <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-1">Aktif sıralama: {{ \App\Livewire\TrendyolBooster::$keywordIntelligenceColumnDefinitions[$keywordIntelligenceSortField] ?? 'Fırsat' }} {{ $keywordIntelligenceSortDirection === 'asc' ? 'artan' : 'azalan' }}</span>
                            <span>Yüksek fırsat = güçlü anlam uyumu + görece düşük zorluk + özgül ifade.</span>
                        </div>
                    </div>

                    <div class="hidden overflow-x-auto md:block" x-data="columnResize()">
                        <table class="w-full table-fixed text-left text-sm">
                            <thead class="bg-slate-50/80 text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500">
                                <tr>
                                    @foreach(\App\Livewire\TrendyolBooster::$keywordIntelligenceColumnDefinitions as $columnKey => $columnLabel)
                                        @if(in_array($columnKey, $keywordIntelligenceVisibleColumns, true))
                                            <th class="overflow-hidden border-b border-slate-200 px-4 py-3 {{ $columnKey === 'keyword' ? 'w-[29%]' : '' }}">
                                                <button type="button" wire:click="sortKeywordIntelligenceTable('{{ $columnKey }}')" class="flex w-full items-center justify-between gap-2 text-left">
                                                    <span class="truncate">{{ $columnLabel }}</span>
                                                    <span class="text-[10px] text-slate-400">{{ $keywordSortIcon($columnKey) }}</span>
                                                </button>
                                            </th>
                                        @endif
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                @forelse($this->keywordIntelligenceRows as $keywordRow)
                                    <tr class="bg-white transition hover:bg-slate-50/70">
                                        @if(in_array('keyword', $keywordIntelligenceVisibleColumns, true))
                                            <td class="overflow-hidden px-4 py-3">
                                                <div class="flex min-w-0 items-start gap-2">
                                                    <span class="mt-0.5 rounded-[5px] border {{ $keywordRow['priority'] === 'high' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-50 text-slate-500' }} px-1.5 py-0.5 font-mono text-[10px]">{{ $keywordRow['ngram'] }}G</span>
                                                    <div class="min-w-0">
                                                        <p class="truncate font-semibold text-slate-900">{{ $keywordRow['keyword'] }}</p>
                                                        <p class="mt-1 truncate text-[11px] text-slate-500">{{ $keywordRow['document_frequency'] }} başlık · {{ $keywordRow['frequency'] }} tekrar</p>
                                                    </div>
                                                </div>
                                            </td>
                                        @endif
                                        @if(in_array('intent', $keywordIntelligenceVisibleColumns, true))
                                            <td class="overflow-hidden px-4 py-3"><span class="inline-flex max-w-full truncate rounded-[6px] border px-2 py-1 text-xs font-medium {{ $keywordIntentTone($keywordRow['intent']) }}">{{ $keywordRow['intent_label'] }}</span></td>
                                        @endif
                                        @if(in_array('semantic', $keywordIntelligenceVisibleColumns, true))
                                            <td class="overflow-hidden px-4 py-3"><p class="font-mono font-semibold text-slate-900">{{ $keywordRow['semantic_score'] }}</p><p class="mt-1 text-[10px] text-slate-400">yakınlık {{ $keywordRow['query_affinity'] }}</p></td>
                                        @endif
                                        @if(in_array('coverage', $keywordIntelligenceVisibleColumns, true))
                                            <td class="overflow-hidden px-4 py-3"><p class="font-mono font-semibold text-slate-900">%{{ $keywordRow['coverage_percent'] }}</p><div class="mt-1.5 h-1 overflow-hidden rounded bg-slate-100"><div class="h-full rounded bg-slate-500" style="width: {{ $keywordRow['coverage_percent'] }}%"></div></div></td>
                                        @endif
                                        @if(in_array('opportunity', $keywordIntelligenceVisibleColumns, true))
                                            <td class="overflow-hidden px-4 py-3"><span class="font-mono text-base font-bold {{ $keywordScoreTone((int) $keywordRow['opportunity_score']) }}">{{ $keywordRow['opportunity_score'] }}</span><p class="mt-0.5 text-[10px] text-slate-400">fırsat</p></td>
                                        @endif
                                        @if(in_array('difficulty', $keywordIntelligenceVisibleColumns, true))
                                            <td class="overflow-hidden px-4 py-3"><span class="font-mono font-semibold {{ $keywordScoreTone((int) $keywordRow['difficulty_score'], true) }}">{{ $keywordRow['difficulty_score'] }}</span><p class="mt-0.5 text-[10px] text-slate-400">{{ $keywordRow['difficulty_label'] }}</p></td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">Puanlanabilir kelime adayı bulunamadı.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="space-y-3 p-4 md:hidden">
                        @forelse($this->keywordIntelligenceRows as $keywordRow)
                            <article class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-900">{{ $keywordRow['keyword'] }}</p>
                                        <span class="mt-1 inline-flex rounded-[6px] border px-2 py-0.5 text-[11px] {{ $keywordIntentTone($keywordRow['intent']) }}">{{ $keywordRow['intent_label'] }}</span>
                                    </div>
                                    <div class="text-right"><p class="font-mono text-lg font-bold {{ $keywordScoreTone((int) $keywordRow['opportunity_score']) }}">{{ $keywordRow['opportunity_score'] }}</p><p class="text-[10px] text-slate-400">fırsat</p></div>
                                </div>
                                <div class="mt-3 grid grid-cols-3 gap-2 text-center text-xs">
                                    <div class="rounded-[6px] border border-slate-200 bg-white p-2"><p class="text-slate-400">Güç</p><p class="mt-1 font-mono font-semibold text-slate-800">{{ $keywordRow['semantic_score'] }}</p></div>
                                    <div class="rounded-[6px] border border-slate-200 bg-white p-2"><p class="text-slate-400">Kapsama</p><p class="mt-1 font-mono font-semibold text-slate-800">%{{ $keywordRow['coverage_percent'] }}</p></div>
                                    <div class="rounded-[6px] border border-slate-200 bg-white p-2"><p class="text-slate-400">Zorluk</p><p class="mt-1 font-mono font-semibold text-slate-800">{{ $keywordRow['difficulty_score'] }}</p></div>
                                </div>
                            </article>
                        @empty
                            <div class="rounded-[8px] border border-dashed border-slate-300 p-4 text-sm text-slate-500">Kelime adayı bulunamadı.</div>
                        @endforelse
                    </div>
                </section>

                <aside class="min-w-0 space-y-4 xl:col-span-4">
                    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Başlık reçetesi</p>
                                <h3 class="mt-1 text-base font-semibold text-slate-900">Önerilen SEO omurgası</h3>
                            </div>
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-[6px] border border-slate-200 bg-slate-50 text-slate-600"><x-lucide.icon name="wand-sparkles" class="h-4 w-4" /></div>
                        </div>
                        <div class="mt-3 rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                            <p class="text-sm font-semibold leading-6 text-slate-900">{{ $keywordTitlePlan['recommended_title'] ?? 'Yeterli veri bekleniyor.' }}</p>
                            <div class="mt-2 flex items-center justify-between gap-3 text-[11px] text-slate-500">
                                <span>{{ $keywordTitlePlan['character_count'] ?? 0 }} karakter</span>
                                <button type="button" x-on:click="navigator.clipboard.writeText(@js($keywordTitlePlan['recommended_title'] ?? '')); copied = true; setTimeout(() => copied = false, 1400)" class="inline-flex items-center gap-1 font-medium text-slate-700">
                                    <x-lucide.icon name="copy" class="h-3.5 w-3.5" />
                                    <span x-text="copied ? 'Kopyalandı' : 'Kopyala'"></span>
                                </button>
                            </div>
                        </div>
                        <p class="mt-3 rounded-[6px] border border-dashed border-slate-300 px-3 py-2 text-xs leading-5 text-slate-500">{{ $keywordTitlePlan['formula'] ?? '' }}</p>
                        @if($keywordMarketOptions !== [])
                            <div class="mt-3 space-y-2">
                                @foreach($keywordMarketOptions as $option)
                                    <div wire:key="keyword-market-option-{{ $option['key'] ?? $loop->index }}" class="rounded-[6px] border border-slate-200 bg-white px-3 py-2">
                                        <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-400">{{ $option['label'] ?? 'Sinyal' }}</p>
                                        <p class="mt-1 text-xs text-slate-700">{{ implode(' · ', (array) ($option['terms'] ?? [])) }}</p>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        <div class="mt-3 space-y-2">
                            @foreach($keywordTitleRules as $rule)
                                <div wire:key="keyword-title-rule-{{ $loop->index }}" class="flex items-start gap-2 text-xs leading-5 text-slate-600"><x-lucide.icon name="check" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-emerald-600" /><span>{{ $rule }}</span></div>
                            @endforeach
                        </div>
                    </section>

                    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-5">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Aksiyon planı</p>
                        <div class="mt-3 space-y-3">
                            @foreach($keywordRecommendations as $recommendation)
                                <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                                    <div class="flex items-start justify-between gap-3"><p class="text-sm font-semibold text-slate-900">{{ $recommendation['title'] }}</p><span class="shrink-0 rounded-[5px] border border-slate-200 bg-white px-1.5 py-0.5 font-mono text-[10px] text-slate-500">{{ $recommendation['impact'] }}</span></div>
                                    <p class="mt-1.5 text-xs leading-5 text-slate-500">{{ $recommendation['description'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    </section>

                    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-5">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Risk ve kalite</p>
                        <div class="mt-3 space-y-2">
                            @foreach($keywordRisks as $risk)
                                <div class="rounded-[8px] border p-3 {{ $risk['level'] === 'critical' ? 'border-rose-200 bg-rose-50/70' : ($risk['level'] === 'warning' ? 'border-amber-200 bg-amber-50/70' : 'border-sky-200 bg-sky-50/70') }}">
                                    <p class="text-xs font-semibold {{ $risk['level'] === 'critical' ? 'text-rose-800' : ($risk['level'] === 'warning' ? 'text-amber-800' : 'text-sky-800') }}">{{ $risk['label'] }}</p>
                                    <p class="mt-1 text-[11px] leading-5 text-slate-600">{{ $risk['detail'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    </section>
                </aside>
            </div>

            <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Anlam haritası</p>
                        <h3 class="mt-1 text-lg font-semibold text-slate-900">Niyet kümeleri ve uzun kuyruk fırsatları</h3>
                        <p class="mt-1 text-sm text-slate-500">Başlıkta aynı kelimeyi tekrar etmek yerine farklı satın alma niyetlerini dengeli biçimde kapsayın.</p>
                    </div>
                    <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs text-slate-600">{{ count($keywordClusters) }} aktif küme</span>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @forelse($keywordClusters as $cluster)
                        <article class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/60 p-4">
                            <div class="flex items-center justify-between gap-3"><p class="text-sm font-semibold text-slate-900">{{ $cluster['label'] }}</p><span class="font-mono text-xs font-semibold text-slate-500">{{ $cluster['average_score'] }}/100</span></div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach($cluster['terms'] as $term)
                                    <span class="max-w-full truncate rounded-[6px] border bg-white px-2 py-1 text-xs font-medium {{ $keywordIntentTone($term['intent']) }}">{{ $term['keyword'] }} <span class="font-mono opacity-60">{{ $term['opportunity_score'] }}</span></span>
                                @endforeach
                            </div>
                        </article>
                    @empty
                        <div class="rounded-[8px] border border-dashed border-slate-300 bg-slate-50/70 p-4 text-sm text-slate-500 sm:col-span-2 xl:col-span-3">Anlam grubu oluşturmak için yeterli başlık verisi bulunamadı.</div>
                    @endforelse
                </div>

                <div class="mt-4 grid grid-cols-1 gap-4 border-t border-slate-200 pt-4 xl:grid-cols-2">
                    <div>
                        <p class="text-xs font-semibold text-slate-700">En güçlü uzun kuyruk kombinasyonları</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @forelse($keywordLongTail as $longTail)
                                <span class="rounded-[6px] border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-800">{{ $longTail['keyword'] }} <span class="font-mono text-emerald-600">{{ $longTail['opportunity_score'] }}</span></span>
                            @empty
                                <span class="text-xs text-slate-500">Uzun kuyruk adayı oluşmadı.</span>
                            @endforelse
                        </div>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-700">Organik havuzdan ayrılan marka sinyalleri</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @forelse($keywordBrands as $brandSignal)
                                <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs text-slate-600">{{ $brandSignal['label'] }} <span class="font-mono text-slate-400">%{{ $brandSignal['share_percent'] }}</span></span>
                            @empty
                                <span class="text-xs text-slate-500">Belirgin marka yoğunluğu algılanmadı.</span>
                            @endforelse
                        </div>
                    </div>
                </div>
            </section>

            <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div><p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">İzlenebilirlik</p><h3 class="mt-1 text-lg font-semibold text-slate-900">Arama geçmişi ve kaynak örneklemi</h3></div>
                    <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs text-slate-600">Son {{ count($keywordLookupDashboard['latest']) }} kayıt</span>
                </div>
                <div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-2">
                    <div class="space-y-2">
                        @foreach($keywordLookupDashboard['latest'] as $lookup)
                            <div class="flex items-center justify-between gap-3 rounded-[8px] border border-slate-200 bg-slate-50/60 px-3 py-2.5">
                                <div class="min-w-0"><p class="truncate text-sm font-medium text-slate-800">{{ $lookup->keyword }}</p><p class="mt-0.5 text-[11px] text-slate-500">{{ $lookup->searched_at?->format('d.m.Y H:i') }}</p></div>
                                <span class="shrink-0 font-mono text-xs {{ $lookup->result_count > 0 ? 'text-emerald-700' : 'text-slate-400' }}">{{ number_format((int) $lookup->result_count, 0, ',', '.') }}</span>
                            </div>
                        @endforeach
                    </div>
                    <div class="space-y-2">
                        @foreach(array_slice((array) $currentKeywordLookup->top_products, 0, 6) as $product)
                            <a href="{{ $product['source_url'] ?? '#' }}" target="_blank" rel="noopener noreferrer" class="flex min-w-0 items-start gap-3 rounded-[8px] border border-slate-200 bg-white px-3 py-2.5 transition hover:bg-slate-50">
                                <span class="shrink-0 rounded-[5px] border border-slate-200 bg-slate-50 px-1.5 py-0.5 font-mono text-[10px] text-slate-500">#{{ $product['rank'] ?? '-' }}</span>
                                <span class="min-w-0 truncate text-xs text-slate-600">{{ $product['title'] ?? 'Ürün başlığı' }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </section>
        @else
            <section class="rounded-[10px] border border-dashed border-slate-300 bg-white p-6 text-center shadow-sm lg:p-10">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-[8px] border border-slate-200 bg-slate-50 text-slate-500"><x-lucide.icon name="radar" class="h-6 w-6" /></div>
                <h3 class="mt-4 text-lg font-semibold text-slate-900">İlk pazar analizini başlatın</h3>
                <p class="mx-auto mt-2 max-w-2xl text-sm leading-6 text-slate-500">Bir ürün veya kategori yazın. Motor; üst sıra başlıklarını okuyup marka gürültüsünü ayıracak, kelimeleri puanlayacak ve doğrudan uygulanabilir başlık reçetesi oluşturacak.</p>
            </section>
        @endif
    </div>
</div>
