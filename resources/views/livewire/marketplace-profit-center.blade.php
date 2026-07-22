@php
    $formatMoney = fn ($value) => '₺' . number_format((float) $value, 2, ',', '.');
    $formatCount = fn ($value) => number_format((float) $value, 0, ',', '.');
    $formatPercent = fn ($value) => '%' . number_format((float) $value, 1, ',', '.');
    $formatCompactMoney = function ($value) {
        $amount = (float) $value;
        $prefix = $amount < 0 ? '-₺' : '₺';
        $abs = abs($amount);

        if ($abs >= 1000000) {
            return $prefix . number_format($abs / 1000000, 2, ',', '.') . ' Mn';
        }

        if ($abs >= 1000) {
            return $prefix . number_format($abs / 1000, 1, ',', '.') . ' Bin';
        }

        return $prefix . number_format($abs, 0, ',', '.');
    };

    $trendRows = collect($dailyTrend ?? [])->values();
    $chartWidth = 720;
    $chartHeight = 230;
    $chartPaddingX = 28;
    $chartPaddingY = 24;
    $plotWidth = $chartWidth - ($chartPaddingX * 2);
    $plotHeight = $chartHeight - ($chartPaddingY * 2);
    $plotBottom = $chartHeight - $chartPaddingY;
    $chartMax = max(
        1,
        (float) ($trendRows->max('gross_revenue') ?? 0),
        (float) ($trendRows->max('profit_value') ?? 0),
        abs((float) ($trendRows->min('profit_value') ?? 0)),
        (float) ($trendRows->max('total_deductions') ?? 0)
    );
    $trendCount = max(1, $trendRows->count());
    $labelInterval = max(1, (int) ceil($trendCount / 6));
    $barWidth = max(4, min(18, ($plotWidth / $trendCount) * 0.52));
    $trendPoints = $trendRows->map(function (array $row, int $index) use ($trendCount, $chartPaddingX, $plotWidth, $plotBottom, $plotHeight, $chartMax, $barWidth) {
        $x = $trendCount === 1
            ? $chartPaddingX + ($plotWidth / 2)
            : $chartPaddingX + (($plotWidth / ($trendCount - 1)) * $index);
        $gross = max(0, (float) ($row['gross_revenue'] ?? 0));
        $profit = (float) ($row['profit_value'] ?? 0);
        $deductions = max(0, (float) ($row['total_deductions'] ?? 0));

        return [
            'x' => round($x, 2),
            'bar_x' => round($x - ($barWidth / 2), 2),
            'bar_width' => round($barWidth, 2),
            'gross_height' => round(($gross / $chartMax) * $plotHeight, 2),
            'profit_y' => round($plotBottom - ((max(0, $profit) / $chartMax) * $plotHeight), 2),
            'deduction_y' => round($plotBottom - (($deductions / $chartMax) * $plotHeight), 2),
            'profit_positive' => $profit >= 0,
            'date_label' => !empty($row['date']) ? \Illuminate\Support\Carbon::parse($row['date'])->format('d.m') : '',
            'gross_revenue' => $gross,
            'profit_value' => $profit,
            'total_deductions' => $deductions,
        ];
    });
    $profitPolyline = $trendPoints->map(fn ($point) => $point['x'] . ',' . $point['profit_y'])->implode(' ');
    $deductionPolyline = $trendPoints->map(fn ($point) => $point['x'] . ',' . $point['deduction_y'])->implode(' ');
    $deductionColorMap = [
        'amber' => 'bg-amber-500',
        'sky' => 'bg-sky-500',
        'slate' => 'bg-slate-500',
        'rose' => 'bg-rose-500',
    ];
    $actionToneBarClass = [
        'danger' => 'bg-rose-500',
        'warning' => 'bg-amber-500',
        'info' => 'bg-sky-500',
        'success' => 'bg-emerald-500',
        'default' => 'bg-slate-500',
    ];
    $actionToneSoftClass = [
        'danger' => 'border-rose-200 bg-rose-50 text-rose-700',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-700',
        'info' => 'border-sky-200 bg-sky-50 text-sky-700',
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
        'default' => 'border-slate-200 bg-slate-50 text-slate-700',
    ];
    $healthPercent = max(0, min(100, (float) $summary['profit_margin_percent']));
    $financeCoveragePercent = $summary['total_orders'] > 0 ? round(($summary['finance_ready_order_count'] / max(1, $summary['total_orders'])) * 100, 1) : 0.0;
    $lossPressurePercent = $summary['total_orders'] > 0 ? round(($summary['loss_order_count'] / max(1, $summary['total_orders'])) * 100, 1) : 0.0;
    $sparkline = function (string $metric, int $width = 148, int $height = 38) use ($trendRows) {
        $rows = $trendRows->values();

        if ($rows->isEmpty()) {
            return '';
        }

        $values = $rows->map(fn (array $row) => (float) ($row[$metric] ?? 0))->values();
        $min = min(0, (float) $values->min());
        $max = max(1, (float) $values->max());
        $range = max(1, $max - $min);
        $count = max(1, $values->count());

        return $values->map(function (float $value, int $index) use ($count, $width, $height, $min, $range) {
            $x = $count === 1 ? $width / 2 : ($width / ($count - 1)) * $index;
            $y = $height - ((($value - $min) / $range) * ($height - 6)) - 3;

            return round($x, 1) . ',' . round($y, 1);
        })->implode(' ');
    };
    $kpiCards = [
        [
            'label' => 'Ciro',
            'value' => $formatCompactMoney($summary['gross_revenue']),
            'detail' => $formatCount($summary['total_orders']) . ' sipariş',
            'metric' => 'gross_revenue',
            'tone' => 'slate',
            'tooltip' => 'Tüm siparişlerinizin vergiler dahil toplam satış tutarıdır.',
        ],
        [
            'label' => 'Net alacak',
            'value' => $formatCompactMoney($summary['net_receivable']),
            'detail' => $formatCount($summary['finance_ready_order_count']) . ' hazır',
            'metric' => 'gross_revenue',
            'tone' => 'emerald',
            'tooltip' => 'Komisyon ve kargo kesintileri yapıldıktan sonra bankanıza yatması beklenen para.',
        ],
        [
            'label' => 'Kâr',
            'value' => $formatCompactMoney($summary['profit_value']),
            'detail' => $formatPercent($summary['profit_margin_percent']) . ' ciro marjı',
            'metric' => 'profit_value',
            'tone' => $summary['profit_value'] >= 0 ? 'emerald' : 'rose',
            'tooltip' => 'Pazar yeri kesintileri ve ürün maliyetleriniz (alış fiyatı) düşüldükten sonra cebinize kalan net tutar.',
        ],
        [
            'label' => 'Kesinti',
            'value' => $formatCompactMoney($summary['total_deductions']),
            'detail' => $formatCompactMoney($summary['commission_total']) . ' komisyon',
            'metric' => 'total_deductions',
            'tone' => 'amber',
            'tooltip' => 'Pazar yerine ödediğiniz komisyon, kargo, ceza ve hizmet bedellerinin toplamı.',
        ],
        [
            'label' => 'Ödeme Kontrolü',
            'value' => $formatCount($summary['material_variance_order_count']),
            'detail' => $formatCount($summary['aligned_order_count']) . ' uyumlu',
            'metric' => 'loss_order_count',
            'tone' => $summary['material_variance_order_count'] > 0 ? 'rose' : 'slate',
        ],
        [
            'label' => 'Maliyet hazır',
            'value' => $formatPercent($costReadiness['ready_percent']),
            'detail' => $formatCount($costReadiness['missing_cost_products']) . ' ürün eksik',
            'metric' => 'order_count',
            'tone' => 'slate',
        ],
    ];
    $kpiToneClasses = [
        'slate' => ['text' => 'text-slate-900', 'line' => '#334155', 'soft' => 'bg-slate-50'],
        'emerald' => ['text' => 'text-emerald-700', 'line' => '#059669', 'soft' => 'bg-emerald-50'],
        'amber' => ['text' => 'text-amber-700', 'line' => '#d97706', 'soft' => 'bg-amber-50'],
        'rose' => ['text' => 'text-rose-700', 'line' => '#e11d48', 'soft' => 'bg-rose-50'],
    ];
    $panelTabs = [
        'finance' => ['label' => 'Finans', 'description' => 'Ciro, kesinti ve kâr akışı'],
        'orders' => ['label' => 'Siparişler', 'description' => 'Görev listesi ve ödeme kontrolü'],
        'products' => ['label' => 'Ürünler', 'description' => 'Maliyet ve ürün kârlılığı'],
    ];
    $funnelToneClasses = [
        'slate' => ['bar' => 'bg-slate-900', 'soft' => 'bg-slate-50', 'text' => 'text-slate-900'],
        'emerald' => ['bar' => 'bg-emerald-500', 'soft' => 'bg-emerald-50', 'text' => 'text-emerald-700'],
        'amber' => ['bar' => 'bg-amber-500', 'soft' => 'bg-amber-50', 'text' => 'text-amber-700'],
        'rose' => ['bar' => 'bg-rose-500', 'soft' => 'bg-rose-50', 'text' => 'text-rose-700'],
    ];
    $commandTone = $funnelToneClasses[$executiveCommandSummary['score_tone']] ?? $funnelToneClasses['slate'];
    $activeActionCount = ($actionSummary['open'] ?? 0) + ($actionSummary['in_progress'] ?? 0) + ($actionSummary['snoozed'] ?? 0);
@endphp

<div class="w-full space-y-4 lg:space-y-6">
    <x-zolm.risk-guidance :guidance="$this->riskGuidance" context-label="Kâr Merkezi" />

    {{-- ═══════════════════════════════════════════
         1. WORKSPACE HEADER — minimal başlık + filtre bandı
    ═══════════════════════════════════════════ --}}
    <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-4 p-4 lg:flex-row lg:items-center lg:justify-between lg:px-6 lg:py-5">
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <h1 class="text-lg font-semibold text-slate-900 lg:text-xl">Kâr Kokpiti</h1>
                </div>
                <p class="mt-0.5 text-sm text-slate-500">Siparişlerinizden ne kazandığınızı, pazar yeri kesintilerini ve tahsilatları tek ekrandan görün.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @foreach($panelTabs as $panelKey => $tab)
                    <button type="button"
                            wire:click="setPanel('{{ $panelKey }}')"
                            class="min-h-[36px] rounded-[6px] border px-4 py-2 text-sm font-medium transition {{ $activePanel === $panelKey ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}">
                        {{ $tab['label'] }}
                    </button>
                @endforeach
            </div>
        </div>

        {{-- KPI Band — 4 ana metrik, kompakt --}}
        <div class="border-t border-slate-100 px-4 py-3 lg:px-6">
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                @foreach(array_slice($kpiCards, 0, 4) as $card)
                    @php
                        $tone = $kpiToneClasses[$card['tone']] ?? $kpiToneClasses['slate'];
                        $points = $sparkline($card['metric']);
                    @endphp
                    <div class="min-w-0 overflow-hidden rounded-[8px] border border-slate-200 bg-slate-50/70 px-3 py-3">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="flex items-center gap-1">
                                    <p class="text-xs font-medium text-slate-500">{{ $card['label'] }}</p>
                                    @if(isset($card['tooltip']))
                                        <x-zolm.help-tip :summary="$card['tooltip']" position="top" />
                                    @endif
                                </div>
                                <p class="mt-1 whitespace-nowrap text-xl font-bold leading-none {{ $tone['text'] }}">{{ $card['value'] }}</p>
                                <p class="mt-1 truncate text-xs text-slate-500">{{ $card['detail'] }}</p>
                            </div>
                            <div class="h-8 w-20 shrink-0 rounded-[4px] {{ $tone['soft'] }} px-1 py-0.5">
                                <svg viewBox="0 0 148 38" class="h-full w-full" aria-hidden="true">
                                    @if($points !== '')
                                        <polyline points="{{ $points }}" fill="none" stroke="{{ $tone['line'] }}" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                                    @else
                                        <line x1="0" y1="28" x2="148" y2="28" stroke="{{ $tone['line'] }}" stroke-width="2.5" stroke-linecap="round" opacity="0.4" />
                                    @endif
                                </svg>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════
         2. KARAR SKORU — kompakt inline band
    ═══════════════════════════════════════════ --}}
        <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-4 p-4 lg:flex-row lg:items-center lg:justify-between lg:px-6 lg:py-4">
                <div class="flex items-center gap-4 min-w-0">
                    <span class="hidden text-xs font-semibold uppercase tracking-[0.14em] text-slate-400 lg:inline">Yönetici komuta özeti</span>
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-medium text-slate-500">Karar skoru</span>
                        <span class="rounded-[6px] border px-2 py-0.5 text-xs font-mono {{ $commandTone['soft'] }} {{ $commandTone['text'] }}">{{ $executiveCommandSummary['score_label'] }}</span>
                        <span class="text-sm font-bold text-slate-900">{{ $formatPercent($executiveCommandSummary['score']) }}</span>
                    </div>
                    <p class="hidden text-sm text-slate-500 lg:block truncate">{{ $executiveCommandSummary['headline'] }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex items-center gap-3 text-xs text-slate-500">
                        <span>Birincil odak: <strong class="text-slate-900">{{ $executiveCommandSummary['primary_focus'] }}</strong></span>
                        <span>Açık aksiyon: <strong class="text-slate-900">{{ $formatCount($activeActionCount) }}</strong></span>
                    </div>
                    <a href="{{ $this->riskUrl($executiveCommandSummary['primary_action']) }}"
                       class="min-h-[36px] rounded-[6px] bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800">
                    {{ $executiveCommandSummary['primary_action']['label'] }}
                </a>
            </div>
        </div>

        {{-- Radar kartları — accordion --}}
        <div x-data="{ radarOpen: false }">
            <button type="button" @click="radarOpen = !radarOpen" class="flex w-full items-center justify-between border-t border-slate-100 px-4 py-2.5 text-left lg:px-6">
                <span class="text-xs font-medium text-slate-500">Yönetici karar radarı</span>
                <svg class="h-3.5 w-3.5 text-slate-400 transition-transform" :class="radarOpen && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>

            <div x-show="radarOpen" x-collapse>
                <div class="border-t border-slate-100 p-4 lg:px-6 lg:pb-5">
                    {{-- Komuta metrikleri --}}
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        @foreach($executiveCommandSummary['metrics'] as $metric)
                            @php $metricTone = $funnelToneClasses[$metric['tone']] ?? $funnelToneClasses['slate']; @endphp
                            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 px-3 py-2">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="truncate text-xs text-slate-500">{{ $metric['label'] }}</p>
                                    <p class="font-mono text-xs font-bold {{ $metricTone['text'] }}">{{ $formatPercent($metric['value']) }}</p>
                                </div>
                                <div class="mt-1.5 h-1 overflow-hidden rounded-full bg-slate-200">
                                    <div class="h-full rounded-full {{ $metricTone['bar'] }}" style="width: {{ min(100, max(0, abs((float) $metric['value']))) }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Radar odak seçimi --}}
                    <div class="mt-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <p class="text-xs font-medium text-slate-500">Otomatik odak</p>
                        <div class="grid grid-cols-3 gap-1 rounded-[6px] border border-slate-200 bg-slate-50 p-0.5 sm:grid-cols-5">
                            @foreach($executiveRadarFocusOptions as $radarFocusKey => $radarFocusLabel)
                                <button type="button"
                                        wire:click="setExecutiveRadarFocus('{{ $radarFocusKey }}')"
                                        class="min-h-[30px] rounded-[5px] px-2 py-1 text-[11px] font-semibold transition {{ $executiveRadarFocus === $radarFocusKey ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:bg-white' }}">
                                    {{ $radarFocusLabel }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    {{-- Radar kartları --}}
                    <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-4">
                        @foreach($executiveDecisionRadar['cards'] as $radarCard)
                            @php
                                $radarTone = $funnelToneClasses[$radarCard['tone']] ?? $funnelToneClasses['slate'];
                                $radarValue = ($radarCard['format'] ?? 'count') === 'percent'
                                    ? $formatPercent($radarCard['value'])
                                    : $formatCount($radarCard['value']);
                                $isRadarSelected = ($executiveDecisionRadar['selected_key'] ?? '') === $radarCard['key'];
                            @endphp
                            <button type="button"
                                    wire:click="setExecutiveRadarFocus('{{ $radarCard['key'] }}')"
                                    class="rounded-[8px] border p-3 text-left transition {{ $isRadarSelected ? 'border-slate-900 bg-slate-50 shadow-sm' : 'border-slate-200 bg-white hover:bg-slate-50' }}">
                                <span class="flex items-center justify-between gap-2">
                                    <span class="text-xs text-slate-500">{{ $radarCard['label'] }}</span>
                                    <span class="rounded-[6px] border px-1.5 py-0.5 text-[10px] font-mono {{ $radarTone['soft'] }} {{ $radarTone['text'] }}">{{ $radarCard['metric_label'] }}</span>
                                </span>
                                <span class="mt-2 block truncate text-sm font-semibold text-slate-900" title="{{ $radarCard['title'] ?? $radarCard['label'] }}">{{ $radarCard['title'] ?? $radarCard['label'] }}</span>
                                <span class="mt-1 block text-lg font-bold {{ $radarTone['text'] }}">{{ $radarValue }}</span>
                                <span class="mt-1 block truncate text-xs text-slate-500" title="{{ $radarCard['headline'] }}">{{ $radarCard['headline'] }}</span>
                            </button>
                        @endforeach
                    </div>

                    {{-- Önerilen hamle --}}
                    @php
                        $radarPrimary = $executiveDecisionRadar['primary'];
                        $radarPrimaryTone = $funnelToneClasses[$radarPrimary['tone']] ?? $funnelToneClasses['slate'];
                    @endphp
                    <div class="mt-3 flex flex-col gap-3 rounded-[8px] border border-slate-200 bg-slate-50/60 p-3 lg:flex-row lg:items-center lg:justify-between">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-semibold text-slate-900">Önerilen hamle</span>
                                <span class="rounded-[6px] border px-1.5 py-0.5 text-[10px] font-mono {{ $radarPrimaryTone['soft'] }} {{ $radarPrimaryTone['text'] }}">{{ $radarPrimary['label'] }}</span>
                            </div>
                            <p class="mt-1 text-xs leading-5 text-slate-500">{{ $radarPrimary['description'] }}</p>
                        </div>
                        @if(($radarPrimary['action_type'] ?? '') === 'focus')
                            <button type="button" wire:click="applyActionQuickFocus('{{ $radarPrimary['action_target'] }}')"
                                    class="shrink-0 min-h-[36px] rounded-[6px] bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800">
                                {{ $radarPrimary['action_label'] }}
                            </button>
                        @else
                            <button type="button" wire:click="setPanel('{{ $radarPrimary['action_target'] }}')"
                                    class="shrink-0 min-h-[36px] rounded-[6px] bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800">
                                {{ $radarPrimary['action_label'] }}
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Kampanya etkisi — kompakt band --}}
        @if(config('marketplace.features.campaign_decision_center_enabled', false))
            <div class="border-t border-slate-100 px-4 py-3 lg:px-6">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex flex-wrap items-center gap-3 min-w-0">
                        <span class="text-xs font-medium text-slate-500">Kampanya etkisi</span>
                        @if($campaignImpact['has_reports'])
                            <span class="rounded-[6px] border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs font-mono text-emerald-700">+{{ $formatCompactMoney($campaignImpact['potential_profit']) }}</span>
                            <span class="rounded-[6px] border border-rose-200 bg-rose-50 px-2 py-0.5 text-xs font-mono text-rose-700">-{{ $formatCompactMoney($campaignImpact['risk_exposure']) }}</span>
                        @else
                            <span class="text-xs text-slate-500">İlk analiz dosyasını yükleyin</span>
                        @endif
                    </div>
                    <a href="{{ route('campaigns.decision-center') }}" class="min-h-[32px] rounded-[6px] border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50">Karar merkezi</a>
                </div>
            </div>
        @endif
    </section>
    @if(!$hasConfiguredStores)
        <section class="rounded-[10px] border border-dashed border-slate-300 bg-white px-5 py-12 text-center shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">Henüz bir pazaryeri mağazanız bağlı değil</h2>
            <p class="mt-2 text-sm text-slate-500 max-w-md mx-auto">ZOLM'un sizin için sipariş kârlılığını hesaplayabilmesi için mağazalarınızı sisteme tanıtmanız gerekir. Bu işlem sadece 2 dakikanızı alır.</p>
            
            <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-4 text-left max-w-3xl mx-auto">
                <div class="rounded-[8px] border border-slate-200 bg-slate-50 p-4">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-900 text-white font-bold text-sm">1</div>
                    <h3 class="mt-3 font-semibold text-slate-900 text-sm">Mağazanı Bağla</h3>
                    <p class="mt-1 text-xs text-slate-500">API entegrasyonu ile Trendyol, Hepsiburada gibi mağazalarınızı saniyeler içinde ZOLM'a bağlayın.</p>
                </div>
                <div class="rounded-[8px] border border-slate-200 bg-slate-50 p-4">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-900 text-white font-bold text-sm">2</div>
                    <h3 class="mt-3 font-semibold text-slate-900 text-sm">Siparişler Çekilsin</h3>
                    <p class="mt-1 text-xs text-slate-500">ZOLM geçmişe dönük tüm siparişlerinizi, iadelerinizi ve kesintilerinizi otomatik olarak indirir.</p>
                </div>
                <div class="rounded-[8px] border border-slate-200 bg-slate-50 p-4">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-900 text-white font-bold text-sm">3</div>
                    <h3 class="mt-3 font-semibold text-slate-900 text-sm">Kârını Gör</h3>
                    <p class="mt-1 text-xs text-slate-500">Hangi üründen ne kadar kâr ettiğinizi, pazar yeri kesintilerini net olarak görmeye başlayın.</p>
                </div>
            </div>

            <a href="{{ route('mp.integrations') }}" class="mt-8 inline-flex min-h-[44px] items-center justify-center rounded-[6px] bg-slate-900 px-6 py-3 text-base font-medium text-white sm:py-2 sm:text-sm hover:bg-slate-800 transition">
                Hemen İlk Mağazanızı Ekleyin
            </a>
        </section>
    @else
        @php
            $calculationTone = $funnelToneClasses[$calculationHealth['score_tone']] ?? $funnelToneClasses['slate'];
        @endphp
        <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm" x-data="{ detailOpen: false }">
            {{-- Güven skoru header --}}
            <div class="flex flex-col gap-3 p-4 lg:flex-row lg:items-center lg:justify-between lg:px-6 lg:py-4">
                <div class="flex flex-wrap items-center gap-3 min-w-0">
                    <span class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Kâr hesabı güveni</span>
                    <span class="rounded-[6px] border px-2 py-0.5 text-xs font-mono {{ $calculationTone['soft'] }} {{ $calculationTone['text'] }}">{{ $calculationHealth['score_label'] }}</span>
                    <span class="text-sm font-semibold text-slate-900">Hesaplama güveni {{ $formatPercent($calculationHealth['score']) }}</span>
                    <span class="text-xs text-slate-500">Kâr rakamı güven skoru</span>
                    <span class="hidden text-sm text-slate-500 lg:inline truncate">{{ $calculationHealth['headline'] }}</span>
                </div>
                <div class="flex items-center gap-2">
                    @if(count($calculationHealth['gap_actions']) > 0)
                        <span class="rounded-[6px] border border-amber-200 bg-amber-50 px-2 py-0.5 text-xs font-mono text-amber-700">{{ $formatCount(count($calculationHealth['gap_actions'])) }} açık</span>
                    @endif
                    <button type="button" @click="detailOpen = !detailOpen" class="rounded-[6px] border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50 transition inline-flex items-center gap-1">
                        Detay
                        <svg class="h-3 w-3 text-slate-400 transition-transform" :class="detailOpen && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                </div>
            </div>

            {{-- Güven kartları — kompakt band --}}
            <div class="border-t border-slate-100 px-4 py-3 lg:px-6">
                <div class="grid grid-cols-2 gap-2 md:grid-cols-4">
                    @foreach($calculationHealth['cards'] as $healthCard)
                        @php $healthCardTone = $funnelToneClasses[$healthCard['tone']] ?? $funnelToneClasses['slate']; @endphp
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 px-3 py-2">
                            <p class="text-xs text-slate-500">{{ $healthCard['label'] }}</p>
                            <div class="mt-1 flex items-center justify-between gap-2">
                                <span class="text-lg font-bold {{ $healthCardTone['text'] }}">{{ $formatPercent($healthCard['value']) }}</span>
                            </div>
                            <div class="mt-1 h-1 overflow-hidden rounded-full bg-slate-200">
                                <div class="h-full rounded-full {{ $healthCardTone['bar'] }}" style="width: {{ min(100, max(0, (float) $healthCard['percent'])) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Açıklar ve Öncelik önerileri — accordion --}}
            <div x-show="detailOpen" x-collapse>
                <div class="border-t border-slate-100 p-4 lg:px-6 lg:pb-5 space-y-4">
                    @if(count($calculationHealth['gap_actions']) > 0)
                        <div>
                            <p class="text-xs font-semibold text-slate-900 mb-2">Hesaplama açıkları</p>
                            <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                                @foreach($calculationHealth['gap_actions'] as $gapAction)
                                    @php $gapTone = $funnelToneClasses[$gapAction['tone']] ?? $funnelToneClasses['slate']; @endphp
                                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 px-3 py-2">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-medium text-slate-900">{{ $gapAction['label'] }}</p>
                                                <p class="mt-0.5 text-xs text-slate-500">{{ $gapAction['description'] }}</p>
                                            </div>
                                            <span class="shrink-0 rounded-[6px] border px-1.5 py-0.5 text-[10px] font-mono {{ $gapTone['soft'] }} {{ $gapTone['text'] }}">{{ $formatCount($gapAction['value']) }}</span>
                                        </div>
                                        <div class="mt-2 flex gap-2">
                                            <a href="{{ $this->riskUrl($gapAction) }}" class="min-h-[30px] flex-1 rounded-[6px] border border-slate-200 bg-white px-2 py-1 text-center text-xs font-medium text-slate-700 transition hover:bg-slate-50">{{ $gapAction['action_label'] }}</a>
                                            <button type="button" wire:click="trackCalculationGap('{{ $gapAction['key'] }}')" wire:loading.attr="disabled" wire:target="trackCalculationGap('{{ $gapAction['key'] }}')" class="min-h-[30px] flex-1 rounded-[6px] bg-slate-900 px-2 py-1 text-xs font-medium text-white transition hover:bg-slate-800 disabled:opacity-60">Takibe al</button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if(count($calculationHealth['assumptions']) > 0)
                        <div>
                            <p class="text-xs font-semibold text-slate-900 mb-2">Varsayımlar</p>
                            <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                                @foreach($calculationHealth['assumptions'] as $assumption)
                                    @php $assumptionTone = $funnelToneClasses[$assumption['tone']] ?? $funnelToneClasses['slate']; @endphp
                                    <div class="flex items-center justify-between gap-3 rounded-[6px] border border-slate-200 bg-white px-3 py-2">
                                        <div class="min-w-0">
                                            <p class="text-xs text-slate-500">{{ $assumption['label'] }}</p>
                                            <p class="mt-0.5 text-xs text-slate-500">{{ $assumption['description'] }}</p>
                                        </div>
                                        <span class="shrink-0 rounded-[6px] border px-1.5 py-0.5 text-[10px] font-mono {{ $assumptionTone['soft'] }} {{ $assumptionTone['text'] }}">{{ $assumption['value'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Öncelik önerileri --}}
                    @if(count($priorityRecommendations) > 0)
                        <div>
                            <div class="flex items-center justify-between gap-3 mb-2">
                                <p class="text-xs font-semibold text-slate-900">Öncelik önerileri</p>
                                <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-0.5 text-[10px] font-mono text-slate-500">{{ $formatCount(count($priorityRecommendations)) }}</span>
                            </div>
                            <div class="grid grid-cols-1 gap-2 lg:grid-cols-2">
                                @foreach($priorityRecommendations as $recommendation)
                                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="text-sm font-medium text-slate-900">{{ $recommendation['label'] }}</p>
                                                <p class="mt-1 text-xs text-slate-500 line-clamp-2">{{ $recommendation['description'] }}</p>
                                            </div>
                                            <span class="shrink-0 rounded-[6px] border px-1.5 py-0.5 text-[10px] font-mono {{ ($funnelToneClasses[$recommendation['tone']] ?? $funnelToneClasses['slate'])['soft'] }} {{ ($funnelToneClasses[$recommendation['tone']] ?? $funnelToneClasses['slate'])['text'] }}">{{ $formatCount($recommendation['value']) }}</span>
                                        </div>
                                        <div class="mt-2 flex items-center justify-between gap-3">
                                            <span class="text-xs text-slate-500">Etki: <strong class="text-slate-900">{{ $formatCompactMoney($recommendation['impact']) }}</strong></span>
                                            <div class="flex gap-1.5">
                                                <a href="{{ $this->riskUrl($recommendation) }}" class="rounded-[6px] border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 transition hover:bg-slate-50">{{ $recommendation['action_label'] }}</a>
                                                <button type="button" wire:click="trackRecommendation('{{ $recommendation['key'] }}')" wire:loading.attr="disabled" wire:target="trackRecommendation('{{ $recommendation['key'] }}')" class="rounded-[6px] bg-slate-900 px-2.5 py-1 text-xs font-medium text-white transition hover:bg-slate-800 disabled:opacity-60">Aksiyona al</button>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </section>
        <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm" x-data="{ actionOpen: false }">
            <button type="button" @click="actionOpen = !actionOpen" class="flex w-full items-center justify-between gap-4 p-4 text-left lg:px-6 lg:py-4">
                <div class="flex flex-wrap items-center gap-3 min-w-0">
                    <h2 class="text-sm font-semibold text-slate-900">Günlük Görevler</h2>
                    <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-mono text-slate-500">{{ $formatCount($actionSummary['open'] + $actionSummary['in_progress']) }} aktif</span>
                    @if(($actionSummary['overdue'] ?? 0) > 0)
                        <span class="rounded-[6px] border border-rose-200 bg-rose-50 px-2 py-0.5 text-xs font-mono text-rose-700">{{ $formatCount($actionSummary['overdue']) }} geciken</span>
                    @endif
                    <span class="rounded-[6px] border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs font-mono text-emerald-700">{{ $formatCompactMoney($actionSummary['resolved_impact']) }} çözülen</span>
                </div>
                <svg class="h-4 w-4 shrink-0 text-slate-400 transition-transform" :class="actionOpen && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>

            <div x-show="actionOpen" x-collapse>
                <div class="border-t border-slate-100 p-4 lg:px-6 lg:pb-5">
                    <div class="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
                        <div class="inline-grid w-full grid-cols-[auto_1fr] items-center gap-2 rounded-[6px] border border-slate-200 bg-slate-50 p-1 sm:w-auto">
                            <span class="px-2 text-xs font-mono text-slate-500">Liste modu</span>
                            <div class="grid grid-cols-2 gap-1">
                                @foreach($actionListDensityOptions as $densityValue => $densityLabel)
                                    <button type="button"
                                            wire:click="$set('actionListDensity', '{{ $densityValue }}')"
                                            class="min-h-[34px] rounded-[6px] px-3 py-1.5 text-xs font-semibold transition {{ $actionListDensity === $densityValue ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:bg-white' }}">
                                        {{ $densityLabel }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 px-3 py-2">
                                <p class="text-xs text-slate-500">Yeni</p>
                                <p class="mt-0.5 text-lg font-bold text-slate-900">{{ $formatCount($actionSummary['open']) }}</p>
                            </div>
                            <div class="rounded-[8px] border border-sky-200 bg-sky-50 px-3 py-2">
                                <p class="text-xs text-sky-700">İnceleniyor</p>
                                <p class="mt-0.5 text-lg font-bold text-sky-700">{{ $formatCount($actionSummary['in_progress']) }}</p>
                            </div>
                            <div class="rounded-[8px] border border-amber-200 bg-amber-50 px-3 py-2">
                                <p class="text-xs text-amber-700">Ertelendi</p>
                                <p class="mt-0.5 text-lg font-bold text-amber-700">{{ $formatCount($actionSummary['snoozed']) }}</p>
                            </div>
                            <div class="rounded-[8px] border border-emerald-200 bg-emerald-50 px-3 py-2">
                                <p class="text-xs text-emerald-700">Çözülen etki</p>
                                <p class="mt-0.5 text-lg font-bold text-emerald-700">{{ $formatCompactMoney($actionSummary['resolved_impact']) }}</p>
                            </div>
                        </div>
                    </div>

            @php
                $actionDeskTabs = [
                    'active' => ['label' => 'Aktif', 'count' => ($actionSummary['open'] + $actionSummary['in_progress'] + $actionSummary['snoozed'])],
                    'resolved' => ['label' => 'Çözülen', 'count' => $actionSummary['resolved']],
                    'all' => ['label' => 'Tümü', 'count' => ($actionSummary['open'] + $actionSummary['in_progress'] + $actionSummary['snoozed'] + $actionSummary['resolved'])],
                ];
            @endphp

            <div class="mt-4 grid grid-cols-2 gap-2 lg:grid-cols-4">
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                    <p class="text-xs text-slate-500">Açık etki</p>
                    <p class="mt-1 text-lg font-bold text-slate-900">{{ $formatCompactMoney($actionSummary['active_impact']) }}</p>
                </div>
                <div class="rounded-[8px] border border-rose-200 bg-rose-50 p-3">
                    <p class="text-xs text-rose-700">Yüksek öncelik</p>
                    <p class="mt-1 text-lg font-bold text-rose-700">{{ $formatCount($actionSummary['high_priority']) }}</p>
                </div>
                <div class="rounded-[8px] border border-amber-200 bg-amber-50 p-3">
                    <p class="text-xs text-amber-700">Geciken</p>
                    <p class="mt-1 text-lg font-bold text-amber-700">{{ $formatCount($actionSummary['overdue']) }}</p>
                </div>
                <div class="rounded-[8px] border border-sky-200 bg-sky-50 p-3">
                    <p class="text-xs text-sky-700">3 gün içinde</p>
                    <p class="mt-1 text-lg font-bold text-sky-700">{{ $formatCount($actionSummary['due_soon']) }}</p>
                </div>
            </div>

            @php
                $actionDistribution = $actionReport['action_distribution'] ?? [
                    'total_count' => 0,
                    'active_count' => 0,
                    'status_rows' => [],
                    'priority_rows' => [],
                    'aging_rows' => [],
                ];
                $actionHealth = $actionReport['action_health'] ?? [];
                $actionHealthTone = $actionHealth['tone'] ?? 'default';
                $actionHealthRingClass = match ($actionHealthTone) {
                    'success' => 'border-emerald-500 text-emerald-700',
                    'warning' => 'border-amber-500 text-amber-700',
                    'danger' => 'border-rose-500 text-rose-700',
                    default => 'border-slate-400 text-slate-700',
                };
                $nextMove = $actionNextMoveRecommendations['primary'] ?? null;
            @endphp

            @if(($actionHealth['total_count'] ?? 0) > 0)
                <div class="mt-4 rounded-[8px] border border-slate-200 bg-white p-3 shadow-sm lg:p-4">
                    <div class="grid grid-cols-1 gap-3 xl:grid-cols-[minmax(0,1fr)_380px]">
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                            <div class="grid grid-cols-1 gap-3 lg:grid-cols-[168px_minmax(0,1fr)] lg:items-center">
                                <div class="flex items-center gap-3 lg:block">
                                    <div class="flex h-[92px] w-[92px] shrink-0 items-center justify-center rounded-full border-[10px] bg-white {{ $actionHealthRingClass }}">
                                        <div class="text-center">
                                            <p class="text-lg font-bold">{{ $formatPercent($actionHealth['score'] ?? 0) }}</p>
                                            <p class="mt-0.5 text-[11px] text-slate-500">{{ $actionHealth['label'] ?? 'Sağlık' }}</p>
                                        </div>
                                    </div>
                                    <div class="min-w-0 lg:mt-3">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="text-sm font-semibold text-slate-900">Aksiyon sağlığı</p>
                                            <span class="rounded-[6px] border px-2 py-0.5 text-xs font-mono {{ $actionToneSoftClass[$actionHealthTone] ?? $actionToneSoftClass['default'] }}">
                                                {{ $actionHealth['label'] ?? 'Veri yok' }}
                                            </span>
                                        </div>
                                        <p class="mt-1 text-xs leading-5 text-slate-500">{{ $actionHealth['headline'] ?? 'Aksiyon akışı izleniyor.' }}</p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-4">
                                    @foreach($actionHealth['cards'] ?? [] as $healthCard)
                                        @php
                                            $healthCardTone = $healthCard['tone'] ?? 'default';
                                            $healthCardValue = match ($healthCard['format'] ?? 'count') {
                                                'percent' => $formatPercent($healthCard['value'] ?? 0),
                                                'money' => $formatCompactMoney($healthCard['value'] ?? 0),
                                                default => $formatCount($healthCard['value'] ?? 0),
                                            };
                                        @endphp
                                        <div class="min-w-0 rounded-[6px] border bg-white px-3 py-2 {{ $actionToneSoftClass[$healthCardTone] ?? $actionToneSoftClass['default'] }}">
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="min-w-0">
                                                    <p class="truncate text-xs font-semibold">{{ $healthCard['label'] }}</p>
                                                    <p class="mt-1 text-lg font-bold">{{ $healthCardValue }}</p>
                                                </div>
                                                <span class="mt-1 h-2 w-2 shrink-0 rounded-full {{ $actionToneBarClass[$healthCardTone] ?? $actionToneBarClass['default'] }}"></span>
                                            </div>
                                            <p class="mt-1 truncate text-xs" title="{{ $healthCard['detail'] ?? '' }}">{{ $healthCard['detail'] ?? '' }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <div class="rounded-[8px] border border-slate-200 bg-white p-3">
                            <div class="flex min-h-full flex-col justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="text-sm font-semibold text-slate-900">Önerilen sonraki hamle</p>
                                        @if($nextMove)
                                            <span class="rounded-[6px] border px-2 py-0.5 text-xs font-mono {{ $actionToneSoftClass[$nextMove['tone'] ?? 'default'] ?? $actionToneSoftClass['default'] }}">
                                                {{ $formatCount($nextMove['count'] ?? 0) }} iş
                                            </span>
                                        @endif
                                    </div>
                                    @if($nextMove)
                                        <p class="mt-2 text-sm font-semibold text-slate-900">{{ $nextMove['title'] }}</p>
                                        <p class="mt-1 text-xs leading-5 text-slate-500">{{ $nextMove['description'] }}</p>
                                    @else
                                        <p class="mt-2 text-sm font-semibold text-slate-900">{{ $actionNextMoveRecommendations['empty_label'] ?? 'Aksiyon sağlığı temiz' }}</p>
                                        <p class="mt-1 text-xs leading-5 text-slate-500">{{ $actionNextMoveRecommendations['empty_description'] ?? '' }}</p>
                                    @endif
                                </div>

                                @if($nextMove)
                                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                        <button type="button"
                                                wire:click="applyActionQuickFocus('{{ $nextMove['focus_key'] }}')"
                                                class="min-h-[40px] rounded-[6px] border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-white">
                                            Odakla
                                        </button>
                                        @if(!empty($nextMove['recommendation_key']))
                                            <button type="button"
                                                    wire:click="bulkApplyActionRecommendation('{{ $nextMove['recommendation_key'] }}')"
                                                    @disabled($selectedActionCount < 1)
                                                    class="min-h-[40px] rounded-[6px] border border-slate-900 bg-slate-900 px-3 py-2 text-sm font-medium text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50">
                                                {{ $nextMove['apply_label'] }}
                                            </button>
                                        @elseif(!empty($nextMove['bulk_status']))
                                            <button type="button"
                                                    wire:click="bulkUpdateActions('{{ $nextMove['bulk_status'] }}')"
                                                    @disabled($selectedActionCount < 1)
                                                    class="min-h-[40px] rounded-[6px] border border-slate-900 bg-slate-900 px-3 py-2 text-sm font-medium text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50">
                                                {{ $nextMove['apply_label'] }}
                                            </button>
                                        @endif
                                    </div>
                                @endif

                                @if(!empty($actionNextMoveRecommendations['alternatives']))
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach($actionNextMoveRecommendations['alternatives'] as $alternativeMove)
                                            <button type="button"
                                                    wire:click="applyActionQuickFocus('{{ $alternativeMove['focus_key'] }}')"
                                                    class="min-h-[30px] rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-1 text-xs font-medium text-slate-600 transition hover:bg-white">
                                                {{ $alternativeMove['title'] }} · {{ $formatCount($alternativeMove['count'] ?? 0) }}
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-3 xl:grid-cols-[minmax(0,1fr)_360px]">
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">Risk sürücüleri</p>
                                    <p class="mt-1 text-xs text-slate-500">Skoru aşağı çeken operasyonel baskılar.</p>
                                </div>
                            </div>
                            <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-4">
                                @foreach($actionHealth['drivers'] ?? [] as $healthDriver)
                                    @php
                                        $driverTone = $healthDriver['tone'] ?? 'default';
                                    @endphp
                                    <div class="min-w-0 rounded-[6px] border bg-white px-3 py-2 {{ $actionToneSoftClass[$driverTone] ?? $actionToneSoftClass['default'] }}">
                                        <div class="flex items-start justify-between gap-2">
                                            <p class="truncate text-xs font-semibold">{{ $healthDriver['label'] }}</p>
                                            <span class="font-mono text-xs">{{ $formatPercent($healthDriver['percent'] ?? 0) }}</span>
                                        </div>
                                        <p class="mt-1 text-lg font-bold">{{ $formatCount($healthDriver['count'] ?? 0) }}</p>
                                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-white/80">
                                            <div class="h-full rounded-full {{ $actionToneBarClass[$driverTone] ?? $actionToneBarClass['default'] }}"
                                                 style="width: {{ min(100, max(0, (float) ($healthDriver['percent'] ?? 0))) }}%"></div>
                                        </div>
                                        <p class="mt-2 line-clamp-2 text-xs leading-4" title="{{ $healthDriver['detail'] ?? '' }}">{{ $healthDriver['detail'] ?? '' }}</p>
                                        @if(in_array(($healthDriver['key'] ?? ''), ['overdue', 'high_priority', 'unowned', 'plan_gap'], true))
                                            <button type="button"
                                                    wire:click="applyActionQuickFocus('{{ $healthDriver['key'] }}')"
                                                    @disabled(($healthDriver['count'] ?? 0) < 1)
                                                    class="mt-3 min-h-[34px] w-full rounded-[6px] border border-white/70 bg-white px-2 py-1.5 text-xs font-medium transition hover:border-slate-300 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50">
                                                Odakla
                                            </button>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">Haftalık tempo</p>
                                    <p class="mt-1 text-xs text-slate-500">Açılan ve kapanan aksiyon dengesi.</p>
                                </div>
                                <span class="rounded-[6px] border border-slate-200 bg-white px-2 py-0.5 text-xs font-mono text-slate-500">
                                    {{ $formatPercent($actionHealth['tempo']['closure_rate_percent'] ?? 0) }}
                                </span>
                            </div>
                            <div class="mt-3 space-y-2">
                                @foreach($actionHealth['tempo']['weeks'] ?? [] as $healthWeek)
                                    @php
                                        $tempoMax = max(1, (int) ($healthWeek['created'] ?? 0), (int) ($healthWeek['resolved'] ?? 0));
                                    @endphp
                                    <div class="grid grid-cols-[64px_minmax(0,1fr)_42px] items-center gap-2 text-xs">
                                        <span class="font-medium text-slate-500">{{ $healthWeek['label'] }}</span>
                                        <div class="space-y-1">
                                            <div class="h-1.5 overflow-hidden rounded-full bg-slate-200">
                                                <div class="h-full rounded-full bg-slate-400" style="width: {{ min(100, max(0, ((int) ($healthWeek['created'] ?? 0) / $tempoMax) * 100)) }}%"></div>
                                            </div>
                                            <div class="h-1.5 overflow-hidden rounded-full bg-slate-200">
                                                <div class="h-full rounded-full bg-emerald-500" style="width: {{ min(100, max(0, ((int) ($healthWeek['resolved'] ?? 0) / $tempoMax) * 100)) }}%"></div>
                                            </div>
                                        </div>
                                        <span class="text-right font-mono {{ ($healthWeek['net'] ?? 0) >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                                            {{ ($healthWeek['net'] ?? 0) >= 0 ? '+' : '' }}{{ $formatCount($healthWeek['net'] ?? 0) }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @if(($actionDistribution['total_count'] ?? 0) > 0)
                <div class="mt-4 rounded-[8px] border border-slate-200 bg-white p-4">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-sm font-semibold text-slate-900">Aksiyon kompozisyonu</p>
                                <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-mono text-slate-500">
                                    {{ $formatCount($actionDistribution['total_count'] ?? 0) }} kayıt
                                </span>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">Durum, öncelik ve hedef baskısını tek bakışta okuyun.</p>
                        </div>
                        <div class="grid grid-cols-2 gap-2 sm:min-w-[360px]">
                            <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-3 py-2">
                                <p class="text-xs text-slate-500">Aktif iş</p>
                                <p class="mt-1 font-bold text-slate-900">{{ $formatCount($actionDistribution['active_count'] ?? 0) }}</p>
                            </div>
                            <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-3 py-2">
                                <p class="text-xs text-slate-500">Baskın odak</p>
                                <p class="mt-1 truncate font-bold text-slate-900">{{ $actionDistribution['top_aging']['label'] ?? 'Yok' }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <div class="flex h-2.5 overflow-hidden rounded-full bg-slate-100">
                            @foreach($actionDistribution['status_rows'] ?? [] as $statusRow)
                                @if(($statusRow['count'] ?? 0) > 0)
                                    <div class="{{ $actionToneBarClass[$statusRow['tone'] ?? 'default'] ?? $actionToneBarClass['default'] }}"
                                         style="width: {{ min(100, max(0, (float) ($statusRow['percent'] ?? 0))) }}%"
                                         title="{{ $statusRow['label'] }} · {{ $formatPercent($statusRow['percent'] ?? 0) }}"></div>
                                @endif
                            @endforeach
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-2 lg:grid-cols-4">
                            @foreach($actionDistribution['status_rows'] ?? [] as $statusRow)
                                <div class="rounded-[6px] border px-3 py-2 {{ $actionToneSoftClass[$statusRow['tone'] ?? 'default'] ?? $actionToneSoftClass['default'] }}">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="truncate text-xs font-semibold">{{ $statusRow['label'] }}</p>
                                        <span class="font-mono text-xs">{{ $formatPercent($statusRow['percent'] ?? 0) }}</span>
                                    </div>
                                    <p class="mt-1 text-lg font-bold">{{ $formatCount($statusRow['count'] ?? 0) }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-2">
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">Öncelik baskısı</p>
                                    <p class="mt-1 text-xs text-slate-500">Aktif aksiyonların risk yoğunluğu.</p>
                                </div>
                                <span class="rounded-[6px] border border-slate-200 bg-white px-2 py-0.5 text-xs font-mono text-slate-500">
                                    {{ $actionDistribution['top_priority']['label'] ?? 'Yok' }}
                                </span>
                            </div>
                            <div class="mt-3 space-y-2">
                                @foreach($actionDistribution['priority_rows'] ?? [] as $priorityRow)
                                    <div>
                                        <div class="flex items-center justify-between gap-2 text-xs">
                                            <span class="font-medium text-slate-600">{{ $priorityRow['label'] }}</span>
                                            <span class="font-mono text-slate-500">{{ $formatCount($priorityRow['count'] ?? 0) }} · {{ $formatPercent($priorityRow['percent'] ?? 0) }}</span>
                                        </div>
                                        <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-200">
                                            <div class="h-full rounded-full {{ $actionToneBarClass[$priorityRow['tone'] ?? 'default'] ?? $actionToneBarClass['default'] }}"
                                                 style="width: {{ min(100, max(0, (float) ($priorityRow['percent'] ?? 0))) }}%"></div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">Hedef yaşlanması</p>
                                    <p class="mt-1 text-xs text-slate-500">Açık aksiyonların hedef tarih baskısı.</p>
                                </div>
                                <span class="rounded-[6px] border border-slate-200 bg-white px-2 py-0.5 text-xs font-mono text-slate-500">
                                    {{ $actionDistribution['top_aging']['label'] ?? 'Yok' }}
                                </span>
                            </div>
                            <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-5">
                                @foreach($actionDistribution['aging_rows'] ?? [] as $agingRow)
                                    <div class="min-w-0 rounded-[6px] border bg-white px-3 py-2 {{ $actionToneSoftClass[$agingRow['tone'] ?? 'default'] ?? $actionToneSoftClass['default'] }}">
                                        <p class="truncate text-xs font-semibold">{{ $agingRow['label'] }}</p>
                                        <p class="mt-1 text-lg font-bold">{{ $formatCount($agingRow['count'] ?? 0) }}</p>
                                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-white/70">
                                            <div class="h-full rounded-full {{ $actionToneBarClass[$agingRow['tone'] ?? 'default'] ?? $actionToneBarClass['default'] }}"
                                                 style="width: {{ min(100, max(0, (float) ($agingRow['percent'] ?? 0))) }}%"></div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="mt-4 grid grid-cols-1 gap-3 xl:grid-cols-12">
                <div class="rounded-[8px] border border-slate-200 bg-white p-4 xl:col-span-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">Yönetici özeti</p>
                            <p class="mt-1 text-xs text-slate-500">Son 7 gün kapanışları ve açık iş baskısı.</p>
                        </div>
                        <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-mono text-slate-500">
                            7 gün
                        </span>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-2">
                        <div class="rounded-[6px] border border-emerald-200 bg-emerald-50 px-3 py-2">
                            <p class="text-xs text-emerald-700">Kapanan etki</p>
                            <p class="mt-1 font-bold text-emerald-700">{{ $formatCompactMoney($actionReport['resolved_impact_7d']) }}</p>
                            <p class="mt-1 text-xs text-emerald-700">{{ $formatCount($actionReport['resolved_7d']) }} aksiyon</p>
                        </div>
                        <div class="rounded-[6px] border border-rose-200 bg-rose-50 px-3 py-2">
                            <p class="text-xs text-rose-700">Geciken etki</p>
                            <p class="mt-1 font-bold text-rose-700">{{ $formatCompactMoney($actionReport['overdue_impact']) }}</p>
                            <p class="mt-1 text-xs text-rose-700">{{ $formatCount($actionSummary['overdue']) }} aksiyon</p>
                        </div>
                        <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-3 py-2">
                            <p class="text-xs text-slate-500">Sahipsiz iş</p>
                            <p class="mt-1 font-bold text-slate-900">{{ $formatCount($actionReport['unowned']) }}</p>
                        </div>
                        <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-3 py-2">
                            <p class="text-xs text-slate-500">Yoğun sorumlu</p>
                            <p class="mt-1 truncate font-bold text-slate-900">{{ $actionReport['top_owner'] }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-[8px] border border-slate-200 bg-white p-4 xl:col-span-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">Sorumlu yükü</p>
                            <p class="mt-1 text-xs text-slate-500">Açık işlerin ekip veya kişi bazında dağılımı.</p>
                        </div>
                    </div>
                    <div class="mt-4 space-y-2">
                        @forelse($actionReport['owner_workload'] as $ownerRow)
                            <div class="rounded-[6px] border border-slate-200 bg-slate-50/60 px-3 py-2">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-900">{{ $ownerRow['owner'] }}</p>
                                        <p class="mt-1 text-xs text-slate-500">
                                            {{ $formatCount($ownerRow['count']) }} açık iş · {{ $formatCount($ownerRow['in_progress']) }} inceleniyor
                                        </p>
                                    </div>
                                    <p class="shrink-0 text-sm font-bold text-slate-900">{{ $formatCompactMoney($ownerRow['impact']) }}</p>
                                </div>
                                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-200">
                                    <div class="h-full rounded-full bg-slate-900" style="width: {{ min(100, max(8, $ownerRow['count'] * 20)) }}%"></div>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-[6px] border border-dashed border-slate-300 bg-slate-50/60 px-3 py-3 text-sm text-slate-500">
                                Açık aksiyon yükü yok.
                            </div>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-[8px] border border-slate-200 bg-white p-4 xl:col-span-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">Son hareketler</p>
                            <p class="mt-1 text-xs text-slate-500">Aksiyon geçmişinden en güncel kayıtlar.</p>
                        </div>
                    </div>
                    <div class="mt-4 space-y-2">
                        @forelse($actionReport['recent_history'] as $historyItem)
                            <div class="rounded-[6px] border border-slate-200 bg-slate-50/60 px-3 py-2">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-900">{{ $historyItem['event_label'] }}</p>
                                        <p class="mt-1 text-xs text-slate-500">
                                            {{ $historyItem['title'] }} · {{ $historyItem['owner'] }} · {{ $historyItem['updated_at'] ?? 'Yeni' }}
                                        </p>
                                        @if($historyItem['from_status_label'] && $historyItem['to_status_label'] && $historyItem['from_status_label'] !== $historyItem['to_status_label'])
                                            <p class="mt-1 text-xs text-slate-400">
                                                {{ $historyItem['from_status_label'] }} → {{ $historyItem['to_status_label'] }}
                                            </p>
                                        @endif
                                        @if(!empty($historyItem['closure_note']))
                                            <p class="mt-2 rounded-[6px] border border-emerald-200 bg-white px-2 py-1 text-xs leading-5 text-emerald-800">
                                                <span class="font-semibold">Kapanış notu:</span> {{ $historyItem['closure_note'] }}
                                            </p>
                                        @endif
                                    </div>
                                    <div class="flex shrink-0 flex-col items-end gap-1">
                                        <span class="rounded-[6px] border px-2 py-0.5 text-xs font-mono {{ $this->actionStatusClass($historyItem['status']) }}">
                                            {{ $historyItem['status_label'] }}
                                        </span>
                                        @if(!empty($historyItem['closure_quality']))
                                            <span class="rounded-[6px] border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs font-mono text-emerald-700">
                                                {{ $historyItem['closure_quality']['quality_label'] ?? 'Kapanış' }}
                                            </span>
                                            @if(($historyItem['closure_quality']['plan_total_steps'] ?? 0) > 0)
                                                <span class="rounded-[6px] border px-2 py-0.5 text-xs font-mono {{ ($historyItem['closure_quality']['plan_complete'] ?? false) ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700' }}">
                                                    Plan {{ $formatPercent($historyItem['closure_quality']['plan_percent'] ?? 0) }}
                                                </span>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-[6px] border border-dashed border-slate-300 bg-slate-50/60 px-3 py-3 text-sm text-slate-500">
                                Aksiyon geçmişi henüz oluşmadı.
                            </div>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-[8px] border border-slate-200 bg-white p-4 xl:col-span-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">Kapanış kalitesi</p>
                            <p class="mt-1 text-xs text-slate-500">Çözülen aksiyonlarda not, sorumlu, hedef ve zamanında kapanış disiplinini izleyin.</p>
                        </div>
                        <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-mono text-slate-500">
                            {{ $actionReport['closure_quality']['quality_label'] }}
                        </span>
                    </div>
                    <div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-[120px_minmax(0,1fr)] lg:items-center">
                        <div class="mx-auto flex h-[112px] w-[112px] items-center justify-center rounded-full border-[12px] {{ ($actionReport['closure_quality']['quality_percent'] ?? 0) >= 80 ? 'border-emerald-500 text-emerald-700' : (($actionReport['closure_quality']['quality_percent'] ?? 0) >= 60 ? 'border-amber-500 text-amber-700' : 'border-rose-500 text-rose-700') }}">
                            <div class="text-center">
                                <p class="text-xl font-bold">{{ $formatPercent($actionReport['closure_quality']['quality_percent']) }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $formatCount($actionReport['closure_quality']['resolved_count']) }} kapanış</p>
                            </div>
                        </div>
                        <div class="space-y-2">
                            @foreach([
                                ['label' => 'Notlu kapanış', 'value' => $actionReport['closure_quality']['with_note_percent']],
                                ['label' => 'Sorumlu atanmış', 'value' => $actionReport['closure_quality']['with_owner_percent']],
                                ['label' => 'Hedef tarihli', 'value' => $actionReport['closure_quality']['with_due_date_percent']],
                                ['label' => 'Zamanında kapanış', 'value' => $actionReport['closure_quality']['on_time_percent']],
                                ['label' => 'Plan tamamlandı', 'value' => $actionReport['closure_quality']['with_plan_complete_percent'] ?? 0],
                            ] as $qualityRow)
                                <div>
                                    <div class="flex items-center justify-between gap-3 text-xs">
                                        <span class="text-slate-500">{{ $qualityRow['label'] }}</span>
                                        <span class="font-mono font-semibold text-slate-700">{{ $formatPercent($qualityRow['value']) }}</span>
                                    </div>
                                    <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-200">
                                        <div class="h-full rounded-full bg-slate-900" style="width: {{ min(100, max(0, (float) $qualityRow['value'])) }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                            <div class="rounded-[6px] border border-slate-200 bg-slate-50/60 px-3 py-2">
                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                    <div>
                                        <p class="text-xs text-slate-500">Ortalama kapanış</p>
                                        <p class="mt-1 text-sm font-bold text-slate-900">{{ number_format((float) $actionReport['closure_quality']['average_resolution_days'], 1, ',', '.') }} gün</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-slate-500">Plan ilerleme</p>
                                        <p class="mt-1 text-sm font-bold text-slate-900">{{ $formatPercent($actionReport['closure_quality']['average_plan_percent'] ?? 0) }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-[8px] border border-slate-200 bg-white p-4 xl:col-span-7">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">Haftalık trend</p>
                            <p class="mt-1 text-xs text-slate-500">Son 6 haftada açılan, kapanan aksiyon ve kapanan finansal etki.</p>
                        </div>
                    </div>
                    <div class="mt-4 space-y-3">
                        @foreach($actionReport['weekly_trend'] as $trendWeek)
                            @php
                                $weekMax = max(1, (int) $trendWeek['created'], (int) $trendWeek['resolved']);
                            @endphp
                            <div class="rounded-[6px] border border-slate-200 bg-slate-50/60 px-3 py-2">
                                <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                    <p class="text-sm font-semibold text-slate-900">{{ $trendWeek['label'] }}</p>
                                    <p class="text-xs text-slate-500">
                                        {{ $formatCount($trendWeek['created']) }} açılan · {{ $formatCount($trendWeek['resolved']) }} kapanan · {{ $formatCompactMoney($trendWeek['resolved_impact']) }}
                                    </p>
                                </div>
                                <div class="mt-2 grid grid-cols-[72px_minmax(0,1fr)] items-center gap-2 text-xs">
                                    <span class="text-slate-500">Açılan</span>
                                    <div class="h-1.5 overflow-hidden rounded-full bg-slate-200">
                                        <div class="h-full rounded-full bg-slate-400" style="width: {{ min(100, max(0, ((int) $trendWeek['created'] / $weekMax) * 100)) }}%"></div>
                                    </div>
                                    <span class="text-slate-500">Kapanan</span>
                                    <div class="h-1.5 overflow-hidden rounded-full bg-slate-200">
                                        <div class="h-full rounded-full bg-emerald-500" style="width: {{ min(100, max(0, ((int) $trendWeek['resolved'] / $weekMax) * 100)) }}%"></div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="mt-4 flex flex-col gap-3 border-t border-slate-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="inline-grid grid-cols-3 rounded-[8px] border border-slate-200 bg-slate-50/60 p-1">
                    @foreach($actionDeskTabs as $filterKey => $tab)
                        <button type="button"
                                wire:click="setActionDeskFilter('{{ $filterKey }}')"
                                class="min-h-[38px] rounded-[6px] px-3 py-2 text-sm font-semibold transition {{ $actionDeskFilter === $filterKey ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-900' }}">
                            {{ $tab['label'] }}
                            <span class="ml-1 font-mono text-xs text-slate-400">{{ $formatCount($tab['count']) }}</span>
                        </button>
                    @endforeach
                </div>
                <p class="text-sm text-slate-500">
                    @if($actionDeskFilter === 'resolved')
                        Kapanan aksiyonlar ve gerekiyorsa yeniden açılacak işler.
                    @elseif($actionDeskFilter === 'all')
                        Aktif ve kapanmış aksiyonların tam görünümü.
                    @else
                        Halen takip gerektiren açık, incelenen ve ertelenen aksiyonlar.
                    @endif
                </p>
            </div>

            <div class="mt-4 rounded-[8px] border border-slate-200 bg-white p-3">
                @php
                    $reportReadiness = $managerReportPreview['readiness'] ?? ['tone' => 'default', 'label' => 'Kontrol bekliyor', 'warnings' => []];
                    $reportReadinessClass = $actionToneSoftClass[$reportReadiness['tone'] ?? 'default'] ?? $actionToneSoftClass['default'];
                @endphp
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-sm font-semibold text-slate-900">Yönetici raporu kapsamı</p>
                            <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-mono text-slate-500">
                                {{ $formatCount($managerReportPreview['sheet_count'] ?? 0) }} Excel sayfası
                            </span>
                            <span class="rounded-[6px] border px-2 py-0.5 text-xs font-mono {{ $reportReadinessClass }}">
                                {{ $reportReadiness['label'] }}
                            </span>
                        </div>
                        <p class="mt-1 text-xs leading-5 text-slate-500">
                            {{ $managerReportPreview['file_label'] }} dosyası seçili filtreleri, kritik özeti, aksiyon kalitesini ve karar kuyruklarını birlikte indirir.
                        </p>
                    </div>
                    <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-3 py-2 text-xs text-slate-500 lg:min-w-[220px]">
                        <span class="font-semibold text-slate-900">Kapsam:</span>
                        {{ collect($managerReportPreview['scope'] ?? [])->map(fn ($scopeItem) => ($scopeItem['label'] ?? '') . ': ' . ($scopeItem['value'] ?? ''))->implode(' · ') }}
                    </div>
                </div>

                <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach($managerReportPreview['highlights'] as $reportHighlight)
                        @php
                            $reportTone = $funnelToneClasses[$reportHighlight['tone'] ?? 'slate'] ?? $funnelToneClasses['slate'];
                            $reportValue = match ($reportHighlight['format'] ?? 'count') {
                                'percent' => $formatPercent($reportHighlight['value'] ?? 0),
                                'money' => $formatCompactMoney($reportHighlight['value'] ?? 0),
                                default => $formatCount($reportHighlight['value'] ?? 0),
                            };
                        @endphp
                        <div class="min-w-0 rounded-[6px] border border-slate-200 bg-slate-50/60 px-3 py-2">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-xs text-slate-500">{{ $reportHighlight['label'] }}</p>
                                    <p class="mt-1 truncate text-sm font-bold {{ $reportTone['text'] }}">{{ $reportValue }}</p>
                                </div>
                                <span class="mt-0.5 h-2 w-2 shrink-0 rounded-full {{ $reportTone['bar'] }}"></span>
                            </div>
                            <p class="mt-1 truncate text-xs text-slate-500" title="{{ $reportHighlight['detail'] }}">{{ $reportHighlight['detail'] }}</p>
                        </div>
                    @endforeach
                </div>

                <div class="mt-3 rounded-[6px] border border-slate-200 bg-slate-50/60 p-3">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-xs font-semibold uppercase tracking-[0.08em] text-slate-500">İndirme öncesi kontrol</p>
                                <span class="rounded-[6px] border px-2 py-0.5 text-xs font-mono {{ $reportReadinessClass }}">
                                    {{ $formatCount($reportReadiness['warning_count'] ?? 0) }} başlık
                                </span>
                            </div>
                            <p class="mt-1 text-xs leading-5 text-slate-500">{{ $reportReadiness['description'] ?? '' }}</p>
                        </div>
                        <div class="grid grid-cols-1 gap-1.5 sm:grid-cols-2 lg:min-w-[520px]">
                            @forelse($reportReadiness['warnings'] as $reportWarning)
                                @php
                                    $reportWarningClass = $actionToneSoftClass[$reportWarning['tone'] ?? 'default'] ?? $actionToneSoftClass['default'];
                                @endphp
                                <div class="min-w-0 rounded-[6px] border bg-white px-2.5 py-2 {{ $reportWarningClass }}">
                                    <div class="flex items-start justify-between gap-2">
                                        <p class="truncate text-xs font-semibold">{{ $reportWarning['label'] }}</p>
                                        <span class="shrink-0 font-mono text-xs font-bold">{{ $reportWarning['value'] }}</span>
                                    </div>
                                    <p class="mt-1 line-clamp-2 text-xs leading-4" title="{{ $reportWarning['description'] }}">{{ $reportWarning['description'] }}</p>
                                </div>
                            @empty
                                <div class="rounded-[6px] border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs leading-5 text-emerald-700 sm:col-span-2">
                                    Kritik uyarı yok. Rapor seçili kapsamla indirilmeye hazır.
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="mt-3 rounded-[6px] border border-slate-200 bg-slate-50/60 p-3">
                    <div class="flex flex-col gap-2 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-[0.08em] text-slate-500">Rapor sayfaları</p>
                            <p class="mt-1 text-xs text-slate-500">İndirme dosyasında yönetici indeksi, aksiyon planı ve analitik kırılımlar bulunur.</p>
                        </div>
                        <div class="grid grid-cols-1 gap-1.5 sm:grid-cols-2 xl:grid-cols-3 lg:min-w-[560px]">
                            @foreach($managerReportPreview['sheets'] as $reportSheet)
                                <div class="min-w-0 rounded-[6px] border border-slate-200 bg-white px-2.5 py-1.5">
                                    <p class="truncate text-xs font-semibold text-slate-900" title="{{ $reportSheet['description'] }}">{{ $reportSheet['label'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4 rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-sm font-semibold text-slate-900">Aksiyon filtreleri</p>
                            <span class="rounded-[6px] border border-slate-200 bg-white px-2 py-0.5 text-xs font-mono text-slate-500">
                                {{ $formatCount($actionFilterCount) }} aktif
                            </span>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">Açık işleri öncelik, sorumlu, odak ve sıralamaya göre karar kuyruğuna alın.</p>
                    </div>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:min-w-[320px]">
                        <button type="button"
                                wire:click="exportActionReport"
                                wire:loading.attr="disabled"
                                wire:target="exportActionReport"
                                class="min-h-[40px] rounded-[6px] border border-slate-900 bg-slate-900 px-3 py-2 text-sm font-medium text-white transition hover:bg-slate-800 disabled:opacity-60">
                            <span wire:loading.remove wire:target="exportActionReport">Yönetici raporu indir</span>
                            <span wire:loading wire:target="exportActionReport">Rapor hazırlanıyor</span>
                        </button>
                        <button type="button"
                                wire:click="resetActionListFilters"
                                class="min-h-[40px] rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                            Filtreleri sıfırla
                        </button>
                    </div>
                </div>

                <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <label for="profit-action-priority-filter" class="text-xs font-medium text-slate-500">Öncelik</label>
                        <select id="profit-action-priority-filter"
                                wire:model.live="actionPriorityFilter"
                                class="mt-1 min-h-[40px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-700 outline-none transition focus:border-slate-400 focus:ring-2 focus:ring-slate-100 sm:text-sm">
                            <option value="">Tüm öncelikler</option>
                            @foreach($actionPriorityOptions as $priorityValue => $priorityLabel)
                                <option value="{{ $priorityValue }}">{{ $priorityLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="profit-action-owner-filter" class="text-xs font-medium text-slate-500">Sorumlu</label>
                        <select id="profit-action-owner-filter"
                                wire:model.live="actionOwnerFilter"
                                class="mt-1 min-h-[40px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-700 outline-none transition focus:border-slate-400 focus:ring-2 focus:ring-slate-100 sm:text-sm">
                            <option value="">Tüm sorumlular</option>
                            <option value="__unowned">Sahipsiz işler</option>
                            @foreach($actionOwnerOptions as $ownerLabel)
                                <option value="{{ $ownerLabel }}">{{ $ownerLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="profit-action-focus-filter" class="text-xs font-medium text-slate-500">Odak</label>
                        <select id="profit-action-focus-filter"
                                wire:model.live="actionFocusFilter"
                                class="mt-1 min-h-[40px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-700 outline-none transition focus:border-slate-400 focus:ring-2 focus:ring-slate-100 sm:text-sm">
                            <option value="">Tüm odaklar</option>
                            @foreach($actionFocusOptions as $focusValue => $focusLabel)
                                <option value="{{ $focusValue }}">{{ $focusLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="profit-action-sort" class="text-xs font-medium text-slate-500">Sıralama</label>
                        <select id="profit-action-sort"
                                wire:model.live="actionSort"
                                class="mt-1 min-h-[40px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-700 outline-none transition focus:border-slate-400 focus:ring-2 focus:ring-slate-100 sm:text-sm">
                            @foreach($actionSortOptions as $sortValue => $sortLabel)
                                <option value="{{ $sortValue }}">{{ $sortLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mt-3 rounded-[6px] border border-slate-200 bg-white p-3">
                    <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-900">Hızlı odak</p>
                            <p class="mt-1 text-xs text-slate-500">Aksiyon listesini en kritik operasyon başlığına göre tek dokunuşla süzün.</p>
                        </div>
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-5 lg:min-w-[760px]">
                            @foreach($actionQuickFocusControls as $quickFocus)
                                @php
                                    $quickFocusClass = $actionToneSoftClass[$quickFocus['tone'] ?? 'default'] ?? $actionToneSoftClass['default'];
                                @endphp
                                <button type="button"
                                        wire:click="applyActionQuickFocus('{{ $quickFocus['key'] }}')"
                                        class="min-h-[54px] rounded-[6px] border px-3 py-2 text-left transition {{ $quickFocus['active'] ? 'border-slate-900 bg-slate-900 text-white shadow-sm' : $quickFocusClass . ' hover:border-slate-300' }}">
                                    <span class="flex items-start justify-between gap-3">
                                        <span class="min-w-0">
                                            <span class="block truncate text-xs font-semibold">{{ $quickFocus['label'] }}</span>
                                            <span class="mt-1 block truncate text-[11px] leading-4 {{ $quickFocus['active'] ? 'text-slate-300' : '' }}">{{ $quickFocus['description'] }}</span>
                                        </span>
                                        <span class="shrink-0 font-mono text-sm font-bold">{{ $formatCount($quickFocus['count']) }}</span>
                                    </span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            @if(count($actionCommandQueue) > 0)
                <div class="mt-4 rounded-[8px] border border-slate-200 bg-white p-3">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-sm font-semibold text-slate-900">Komuta kuyruğu</p>
                                <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-mono text-slate-500">En kritik {{ $formatCount(count($actionCommandQueue)) }} iş</span>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">Gecikme, öncelik, finansal etki ve sorumluluk durumuna göre ilk bakılacak aksiyonlar.</p>
                        </div>
                        <button type="button"
                                wire:click="applyActionQuickFocus('high_priority')"
                                class="min-h-[40px] rounded-[6px] border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-white">
                            Yüksek önceliğe odaklan
                        </button>
                    </div>

                    <div class="mt-3 grid grid-cols-1 gap-2 xl:grid-cols-3">
                        @foreach($actionCommandQueue as $commandAction)
                            <div class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <span class="rounded-[6px] border px-2 py-0.5 text-xs font-mono {{ $this->badgeToneClass($commandAction['priority_tone']) }}">
                                                {{ $commandAction['priority_label'] }}
                                            </span>
                                            <span class="rounded-[6px] border border-slate-200 bg-white px-2 py-0.5 text-xs font-mono text-slate-500">
                                                {{ $commandAction['command_reason'] }}
                                            </span>
                                        </div>
                                        <p class="mt-2 line-clamp-2 text-sm font-semibold leading-5 text-slate-900">{{ $commandAction['title'] }}</p>
                                        <p class="mt-1 line-clamp-2 text-xs leading-5 text-slate-500">{{ $commandAction['description'] }}</p>
                                    </div>
                                    <p class="shrink-0 text-sm font-bold text-slate-900" title="{{ $formatMoney($commandAction['impact']) }}">{{ $formatCompactMoney($commandAction['impact']) }}</p>
                                </div>

                                <div class="mt-3 grid grid-cols-2 gap-2">
                                    <div class="rounded-[6px] border border-slate-200 bg-white px-2.5 py-2">
                                        <p class="text-xs text-slate-500">Sorumlu</p>
                                        <p class="mt-1 truncate text-xs font-semibold text-slate-900">{{ $commandAction['command_owner'] }}</p>
                                    </div>
                                    <div class="rounded-[6px] border border-slate-200 bg-white px-2.5 py-2">
                                        <p class="text-xs text-slate-500">Hedef</p>
                                        <p class="mt-1 truncate text-xs font-semibold text-slate-900">{{ $commandAction['due_date'] ?: 'Tarih yok' }}</p>
                                    </div>
                                </div>

                                <div class="mt-3 rounded-[6px] border border-slate-200 bg-white px-3 py-2">
                                    <div class="flex items-center justify-between gap-3 text-xs">
                                        <span class="text-slate-500">Plan ilerleme</span>
                                        <span class="font-mono font-semibold text-slate-700">{{ $formatPercent($commandAction['playbook_progress']['percent'] ?? 0) }}</span>
                                    </div>
                                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-200">
                                        <div class="h-full rounded-full {{ ($commandAction['playbook_progress']['is_complete'] ?? false) ? 'bg-emerald-500' : 'bg-slate-900' }}"
                                             style="width: {{ min(100, max(0, (float) ($commandAction['playbook_progress']['percent'] ?? 0))) }}%"></div>
                                    </div>
                                </div>

                                <div class="mt-3 grid grid-cols-1 gap-2">
                                    <div class="rounded-[6px] border border-slate-200 bg-white px-3 py-2">
                                        <p class="text-xs font-semibold text-slate-900">Neden bu sırada?</p>
                                        <p class="mt-1 line-clamp-2 text-xs leading-5 text-slate-500" title="{{ $commandAction['command_rank_explanation'] }}">{{ $commandAction['command_rank_explanation'] }}</p>
                                    </div>
                                    <div class="rounded-[6px] border border-sky-200 bg-sky-50 px-3 py-2">
                                        <p class="text-xs font-semibold text-sky-800">Önerilen ilk adım</p>
                                        <p class="mt-1 line-clamp-2 text-xs leading-5 text-sky-700" title="{{ $commandAction['command_next_step'] }}">{{ $commandAction['command_next_step'] }}</p>
                                    </div>
                                </div>

                                <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-3">
                                    <button type="button"
                                            wire:click="startAction({{ $commandAction['id'] }})"
                                            class="min-h-[38px] rounded-[6px] border border-sky-200 bg-white px-2 py-2 text-xs font-medium text-sky-700 transition hover:bg-sky-50">
                                        İncelemeye al
                                    </button>
                                    <button type="button"
                                            wire:click="openActionTimeline({{ $commandAction['id'] }})"
                                            class="min-h-[38px] rounded-[6px] border border-slate-200 bg-white px-2 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50">
                                        Geçmiş
                                    </button>
                                    <a href="{{ $this->actionItemUrl($commandAction) }}"
                                       class="min-h-[38px] rounded-[6px] border border-slate-900 bg-slate-900 px-2 py-2 text-center text-xs font-medium text-white transition hover:bg-slate-800">
                                        Detaya git
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="mt-4 rounded-[8px] border border-slate-200 bg-white p-3 shadow-sm">
                <div class="grid grid-cols-1 gap-3 xl:grid-cols-[minmax(0,1fr)_340px]">
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="text-sm font-semibold text-slate-900">Toplu işlem</p>
                                    <span class="rounded-[6px] border border-slate-200 bg-white px-2 py-0.5 text-xs font-mono text-slate-500">
                                        {{ $formatCount($selectedActionCount) }} seçili
                                    </span>
                                </div>
                                <p class="mt-1 text-xs leading-5 text-slate-500">Seçili aksiyonlara standart durum komutu veya önerilen düzeltme seti uygulayın.</p>
                            </div>
                            @if(($selectedActionClosureReadiness['selected_count'] ?? 0) > 0)
                                <div class="min-w-0 rounded-[6px] border border-slate-200 bg-white px-3 py-2 lg:max-w-[360px]">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-[6px] border px-2 py-0.5 text-xs font-mono {{ $this->badgeToneClass($selectedActionClosureReadiness['tone'] ?? 'default') }}">
                                            {{ $selectedActionClosureReadiness['label'] }}
                                        </span>
                                    </div>
                                    <p class="mt-1 text-xs leading-5 text-slate-500">{{ $selectedActionClosureReadiness['description'] }}</p>
                                </div>
                            @endif
                        </div>

                        <div class="mt-3 border-t border-slate-200 pt-3">
                            <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold uppercase tracking-[0.08em] text-slate-500">Akıllı öneriler</p>
                                    <p class="mt-1 text-xs text-slate-500">Eksik sorumlu, hedef tarihi ve plan açığı için hızlı onarım.</p>
                                </div>
                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-3 lg:min-w-[560px]">
                                    @foreach($bulkActionRecommendationOptions as $recommendationKey => $recommendationOption)
                                        @php
                                            $recommendationClass = $actionToneSoftClass[$recommendationOption['tone'] ?? 'default'] ?? $actionToneSoftClass['default'];
                                        @endphp
                                        <button type="button"
                                                wire:click="bulkApplyActionRecommendation('{{ $recommendationKey }}')"
                                                @disabled($selectedActionCount < 1)
                                                class="min-h-[48px] rounded-[6px] border px-3 py-2 text-left transition {{ $recommendationClass }} hover:border-slate-300 disabled:cursor-not-allowed disabled:opacity-50">
                                            <span class="block text-xs font-semibold">{{ $recommendationOption['label'] }}</span>
                                            <span class="mt-1 block truncate text-[11px] leading-4" title="{{ $recommendationOption['description'] }}">{{ $recommendationOption['description'] }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-slate-900">Durum komutları</p>
                                <p class="mt-1 text-xs text-slate-500">Seçili işleri operasyon akışında ilerletin.</p>
                            </div>
                            <span class="rounded-[6px] border border-slate-200 bg-white px-2 py-0.5 text-xs font-mono text-slate-500">
                                {{ $formatCount($selectedActionCount) }}
                            </span>
                        </div>
                        <div class="mt-3 grid grid-cols-1 gap-2">
                            <button type="button"
                                    wire:click="bulkUpdateActions('{{ \App\Models\MpProfitActionItem::STATUS_IN_PROGRESS }}')"
                                    @disabled($selectedActionCount < 1)
                                    class="min-h-[40px] rounded-[6px] border border-sky-200 bg-white px-3 py-2 text-sm font-medium text-sky-700 transition hover:bg-sky-50 disabled:cursor-not-allowed disabled:opacity-50">
                                İncelemeye al
                            </button>
                            <button type="button"
                                    wire:click="bulkUpdateActions('{{ \App\Models\MpProfitActionItem::STATUS_SNOOZED }}')"
                                    @disabled($selectedActionCount < 1)
                                    class="min-h-[40px] rounded-[6px] border border-amber-200 bg-white px-3 py-2 text-sm font-medium text-amber-700 transition hover:bg-amber-50 disabled:cursor-not-allowed disabled:opacity-50">
                                Ertele
                            </button>
                            <button type="button"
                                    wire:click="bulkUpdateActions('{{ \App\Models\MpProfitActionItem::STATUS_RESOLVED }}')"
                                    @disabled($selectedActionCount < 1 || (($selectedActionClosureReadiness['ready_count'] ?? 0) < 1))
                                    class="min-h-[40px] rounded-[6px] border border-emerald-200 bg-white px-3 py-2 text-sm font-medium text-emerald-700 transition hover:bg-emerald-50 disabled:cursor-not-allowed disabled:opacity-50">
                                Çözüldü
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            @if($actionTimeline)
                <div class="mt-4 rounded-[8px] border border-slate-200 bg-white p-4">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-sm font-semibold text-slate-900">Aksiyon geçmişi</p>
                                <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-mono text-slate-500">
                                    {{ $formatCount($actionTimeline['event_count']) }} olay
                                </span>
                            </div>
                            <p class="mt-1 truncate text-lg font-bold text-slate-900">{{ $actionTimeline['action']['title'] }}</p>
                            <p class="mt-1 text-sm text-slate-500">{{ $actionTimeline['action']['description'] }}</p>
                        </div>
                        <div class="grid grid-cols-2 gap-2 sm:min-w-[320px]">
                            <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-3 py-2">
                                <p class="text-xs text-slate-500">Durum</p>
                                <p class="mt-1 font-semibold text-slate-900">{{ $this->actionStatusLabel($actionTimeline['action']['status']) }}</p>
                            </div>
                            <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-3 py-2">
                                <p class="text-xs text-slate-500">Etki</p>
                                <p class="mt-1 font-semibold text-slate-900">{{ $formatCompactMoney($actionTimeline['action']['impact']) }}</p>
                            </div>
                            <button type="button"
                                    wire:click="closeActionTimeline"
                                    class="col-span-2 min-h-[40px] rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                Geçmişi kapat
                            </button>
                        </div>
                    </div>

                    <div class="mt-4 space-y-3">
                        @forelse($actionTimeline['events'] as $timelineEvent)
                            <div class="grid grid-cols-[18px_minmax(0,1fr)] gap-3">
                                <div class="flex flex-col items-center">
                                    <span class="mt-1 h-2.5 w-2.5 rounded-full bg-slate-900"></span>
                                    <span class="mt-1 min-h-[44px] w-px flex-1 bg-slate-200"></span>
                                </div>
                                <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold text-slate-900">{{ $timelineEvent['event_label'] }}</p>
                                            <p class="mt-1 text-xs text-slate-500">{{ $timelineEvent['created_at'] ?? $timelineEvent['updated_at'] ?? 'Yeni' }}</p>
                                            @if($timelineEvent['from_status_label'] && $timelineEvent['to_status_label'] && $timelineEvent['from_status_label'] !== $timelineEvent['to_status_label'])
                                                <p class="mt-2 text-xs text-slate-500">
                                                    {{ $timelineEvent['from_status_label'] }} → {{ $timelineEvent['to_status_label'] }}
                                                </p>
                                            @endif
                                            @if($timelineEvent['meta_summary'])
                                                <p class="mt-2 text-xs leading-5 text-slate-500">{{ $timelineEvent['meta_summary'] }}</p>
                                            @endif
                                            @if(!empty($timelineEvent['closure_note']))
                                                <div class="mt-2 rounded-[6px] border border-emerald-200 bg-white px-3 py-2">
                                                    <p class="text-xs font-semibold text-emerald-800">Kapanış notu</p>
                                                    <p class="mt-1 text-xs leading-5 text-emerald-700">{{ $timelineEvent['closure_note'] }}</p>
                                                </div>
                                            @endif
                                            @if(!empty($timelineEvent['closure_quality']))
                                                <div class="mt-2 flex flex-wrap gap-1.5">
                                                    <span class="rounded-[6px] border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs font-mono text-emerald-700">
                                                        {{ $timelineEvent['closure_quality']['quality_label'] ?? 'Kapanış' }} · {{ $formatPercent($timelineEvent['closure_quality']['quality_percent'] ?? 0) }}
                                                    </span>
                                                    <span class="rounded-[6px] border px-2 py-0.5 text-xs font-mono {{ ($timelineEvent['closure_quality']['has_note'] ?? false) ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700' }}">
                                                        {{ ($timelineEvent['closure_quality']['has_note'] ?? false) ? 'Not var' : 'Not eksik' }}
                                                    </span>
                                                    <span class="rounded-[6px] border px-2 py-0.5 text-xs font-mono {{ ($timelineEvent['closure_quality']['has_owner'] ?? false) ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700' }}">
                                                        {{ ($timelineEvent['closure_quality']['has_owner'] ?? false) ? 'Sorumlu var' : 'Sorumlu eksik' }}
                                                    </span>
                                                    <span class="rounded-[6px] border px-2 py-0.5 text-xs font-mono {{ ($timelineEvent['closure_quality']['has_due_date'] ?? false) ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700' }}">
                                                        {{ ($timelineEvent['closure_quality']['has_due_date'] ?? false) ? 'Hedef var' : 'Hedef eksik' }}
                                                    </span>
                                                    <span class="rounded-[6px] border px-2 py-0.5 text-xs font-mono {{ ($timelineEvent['closure_quality']['on_time'] ?? false) ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-white text-slate-500' }}">
                                                        {{ ($timelineEvent['closure_quality']['on_time'] ?? false) ? 'Zamanında' : 'Zaman kontrolü' }}
                                                    </span>
                                                    @if(($timelineEvent['closure_quality']['plan_total_steps'] ?? 0) > 0)
                                                        <span class="rounded-[6px] border px-2 py-0.5 text-xs font-mono {{ ($timelineEvent['closure_quality']['plan_complete'] ?? false) ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700' }}">
                                                            {{ ($timelineEvent['closure_quality']['plan_complete'] ?? false) ? 'Plan tamam' : 'Plan eksik' }} · {{ $formatPercent($timelineEvent['closure_quality']['plan_percent'] ?? 0) }}
                                                        </span>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                        <span class="shrink-0 rounded-[6px] border px-2 py-0.5 text-xs font-mono {{ $this->actionStatusClass($timelineEvent['status']) }}">
                                            {{ $timelineEvent['status_label'] }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-[8px] border border-dashed border-slate-300 bg-slate-50/60 p-5 text-sm text-slate-500">
                                Bu aksiyon için henüz olay kaydı yok.
                            </div>
                        @endforelse
                    </div>
                </div>
            @endif

            <div class="mt-4 rounded-[8px] border border-slate-200 bg-white p-3 shadow-sm">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-sm font-semibold text-slate-900">Aksiyon görünümü</p>
                            <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-mono text-slate-500">
                                {{ $formatCount(count($trackedActions)) }} kayıt
                            </span>
                        </div>
                        <p class="mt-1 text-xs leading-5 text-slate-500">Detaylı plan takibi veya kompakt yönetici kuyruğu arasında geçiş yapın.</p>
                    </div>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-[minmax(0,240px)_auto] lg:min-w-[520px]">
                        <div class="grid grid-cols-2 gap-1 rounded-[6px] border border-slate-200 bg-slate-50 p-1">
                            @foreach($actionListDensityOptions as $densityValue => $densityLabel)
                                <button type="button"
                                        wire:click="$set('actionListDensity', '{{ $densityValue }}')"
                                        class="min-h-[36px] rounded-[6px] px-3 py-1.5 text-sm font-medium transition {{ $actionListDensity === $densityValue ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:bg-white' }}">
                                    {{ $densityLabel }}
                                </button>
                            @endforeach
                        </div>
                        <button type="button"
                                wire:click="applyActionQuickFocus('high_priority')"
                                class="min-h-[40px] rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                            Kritik aksiyonları göster
                        </button>
                    </div>
                </div>
            </div>

            <div class="mt-3 space-y-3">
                @forelse($trackedActions as $action)
                    @php
                        $isActionSelected = in_array((int) $action['id'], array_map('intval', (array) $selectedActionIds), true);
                    @endphp
                    <div class="overflow-hidden rounded-[8px] border bg-white shadow-sm transition {{ $isActionSelected ? 'border-slate-900' : 'border-slate-200' }}">
                        <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_340px]">
                            <div class="min-w-0 p-4">
                                <div class="flex min-w-0 gap-3">
                                    <input type="checkbox"
                                           wire:model.live="selectedActionIds"
                                           value="{{ $action['id'] }}"
                                           aria-label="{{ $action['title'] }} seç"
                                           class="mt-1 h-5 w-5 shrink-0 rounded border-slate-300 text-slate-900 focus:ring-slate-300">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-col gap-2 lg:flex-row lg:items-start lg:justify-between">
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <p class="font-semibold leading-5 text-slate-900">{{ $action['title'] }}</p>
                                                    <span class="rounded-[6px] border px-2 py-0.5 text-xs font-mono {{ $this->actionStatusClass($action['status']) }}">
                                                        {{ $this->actionStatusLabel($action['status']) }}
                                                    </span>
                                                    <span class="rounded-[6px] border px-2 py-0.5 text-xs font-mono {{ $this->badgeToneClass($action['priority_tone']) }}">
                                                        {{ $action['priority_label'] }}
                                                    </span>
                                                    @if($action['is_overdue'])
                                                        <span class="rounded-[6px] border border-rose-200 bg-rose-50 px-2 py-0.5 text-xs font-mono text-rose-700">Gecikti</span>
                                                    @elseif($action['is_due_soon'])
                                                        <span class="rounded-[6px] border border-sky-200 bg-sky-50 px-2 py-0.5 text-xs font-mono text-sky-700">Yakın</span>
                                                    @endif
                                                </div>
                                                <p class="mt-1 text-sm leading-5 text-slate-500">{{ $action['description'] }}</p>
                                            </div>
                                            <div class="grid grid-cols-2 gap-2 sm:min-w-[260px]">
                                                <div class="min-w-0 rounded-[6px] border border-slate-200 bg-slate-50/70 px-3 py-2">
                                                    <p class="text-xs text-slate-500">Etki</p>
                                                    <p class="mt-1 truncate font-semibold text-slate-900" title="{{ $formatMoney($action['impact']) }}">{{ $formatCompactMoney($action['impact']) }}</p>
                                                </div>
                                                <div class="min-w-0 rounded-[6px] border border-slate-200 bg-slate-50/70 px-3 py-2">
                                                    <p class="text-xs text-slate-500">Skor</p>
                                                    <p class="mt-1 font-semibold text-slate-900">{{ $formatCount($action['score']) }}</p>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-3">
                                            <div class="rounded-[6px] border border-slate-200 bg-slate-50/60 px-3 py-2">
                                                <p class="text-xs text-slate-500">Sorumlu</p>
                                                <p class="mt-1 truncate text-xs font-semibold text-slate-900">
                                                    {{ $action['owner_label'] ?: ($action['default_owner'] ? 'Öneri: ' . $action['default_owner'] : 'Atanmadı') }}
                                                </p>
                                            </div>
                                            <div class="rounded-[6px] border border-slate-200 bg-slate-50/60 px-3 py-2">
                                                <p class="text-xs text-slate-500">Hedef</p>
                                                <p class="mt-1 truncate text-xs font-semibold text-slate-900">{{ $action['due_date'] ?: 'Tarih yok' }}</p>
                                            </div>
                                            <div class="rounded-[6px] border border-slate-200 bg-slate-50/60 px-3 py-2">
                                                <p class="text-xs text-slate-500">Güncelleme</p>
                                                <p class="mt-1 truncate text-xs font-semibold text-slate-900">{{ $action['updated_at'] ?: 'Yeni' }}</p>
                                            </div>
                                        </div>

                                        @if($actionListDensity === 'detailed')
                                @if(!empty($action['playbook_steps']))
                                    <div class="mt-3 rounded-[8px] border border-slate-200 bg-white p-3">
                                        <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                                            <div class="min-w-0">
                                                <p class="text-sm font-semibold text-slate-900">Önerilen işlem planı</p>
                                                @if($action['plan_summary'])
                                                    <p class="mt-1 text-xs leading-5 text-slate-500">{{ $action['plan_summary'] }}</p>
                                                @endif
                                            </div>
                                            @if($action['success_metric'])
                                                <span class="shrink-0 rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-mono text-slate-500">
                                                    Başarı ölçütü
                                                </span>
                                            @endif
                                        </div>
                                        <div class="mt-3 rounded-[6px] border border-slate-200 bg-slate-50/60 px-3 py-2">
                                            <div class="flex items-center justify-between gap-3 text-xs">
                                                <span class="font-semibold text-slate-700">Plan ilerleme</span>
                                                <span class="font-mono text-slate-500">
                                                    {{ $formatCount($action['playbook_progress']['completed_count'] ?? 0) }}/{{ $formatCount($action['playbook_progress']['total_steps'] ?? 0) }} · {{ $formatPercent($action['playbook_progress']['percent'] ?? 0) }}
                                                </span>
                                            </div>
                                            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-200">
                                                <div class="h-full rounded-full {{ ($action['playbook_progress']['is_complete'] ?? false) ? 'bg-emerald-500' : 'bg-slate-900' }}"
                                                     style="width: {{ min(100, max(0, (float) ($action['playbook_progress']['percent'] ?? 0))) }}%"></div>
                                            </div>
                                            @if(!empty($action['playbook_progress']['next_step_label']))
                                                <p class="mt-2 text-xs leading-5 text-slate-500">Sıradaki adım: {{ $action['playbook_progress']['next_step_label'] }}</p>
                                            @else
                                                <p class="mt-2 text-xs leading-5 text-emerald-700">Plan adımları tamamlandı.</p>
                                            @endif
                                        </div>
                                        <div class="mt-3 grid grid-cols-1 gap-2 lg:grid-cols-3">
                                            @foreach($action['playbook_progress']['rows'] as $playbookStep)
                                                <button type="button"
                                                        wire:click="toggleActionStep({{ $action['id'] }}, {{ $playbookStep['index'] }})"
                                                        class="min-h-[74px] rounded-[6px] border px-3 py-2 text-left transition {{ $playbookStep['completed'] ? 'border-emerald-200 bg-emerald-50 text-emerald-800 hover:bg-emerald-100' : 'border-slate-200 bg-slate-50/60 text-slate-600 hover:bg-white' }}">
                                                    <span class="flex items-start justify-between gap-2">
                                                        <span class="font-mono text-xs {{ $playbookStep['completed'] ? 'text-emerald-700' : 'text-slate-400' }}">{{ $playbookStep['number'] }}. adım</span>
                                                        <span class="rounded-[6px] border px-2 py-0.5 text-[11px] font-mono {{ $playbookStep['completed'] ? 'border-emerald-200 bg-white text-emerald-700' : 'border-slate-200 bg-white text-slate-500' }}">
                                                            {{ $playbookStep['completed'] ? 'Tamamlandı' : 'Bekliyor' }}
                                                        </span>
                                                    </span>
                                                    <span class="mt-1 block text-xs leading-5">{{ $playbookStep['label'] }}</span>
                                                </button>
                                            @endforeach
                                        </div>
                                        @if($action['success_metric'])
                                            <p class="mt-2 text-xs leading-5 text-slate-500">Başarı: {{ $action['success_metric'] }}</p>
                                        @endif
                                    </div>
                                @endif
                                @if(($action['signal_progress']['tracked'] ?? false))
                                    <div class="mt-3 rounded-[8px] border border-slate-200 bg-white p-3">
                                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                            <div>
                                                <p class="text-sm font-semibold text-slate-900">Sinyal ilerlemesi</p>
                                                <p class="mt-1 text-xs leading-5 text-slate-500">Aksiyon takibe alındığındaki sinyal ile güncel durum karşılaştırması.</p>
                                            </div>
                                            <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-mono text-slate-500">
                                                {{ $formatPercent($action['signal_progress']['improvement_percent']) }} iyileşme
                                            </span>
                                        </div>
                                        <div class="mt-3 grid grid-cols-3 gap-2">
                                            <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-3 py-2">
                                                <p class="text-xs text-slate-500">İlk</p>
                                                <p class="mt-1 font-bold text-slate-900">{{ $formatCount($action['signal_progress']['initial_value']) }}</p>
                                            </div>
                                            <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-3 py-2">
                                                <p class="text-xs text-slate-500">Güncel</p>
                                                <p class="mt-1 font-bold text-slate-900">{{ $formatCount($action['signal_progress']['current_value']) }}</p>
                                            </div>
                                            <div class="rounded-[6px] border border-emerald-200 bg-emerald-50 px-3 py-2">
                                                <p class="text-xs text-emerald-700">Kapanan</p>
                                                <p class="mt-1 font-bold text-emerald-700">{{ $formatCount($action['signal_progress']['closed_value']) }}</p>
                                            </div>
                                        </div>
                                        @if($action['signal_progress']['health_score_before'] !== null && $action['signal_progress']['health_score_now'] !== null)
                                            <div class="mt-2 rounded-[6px] border border-slate-200 bg-slate-50/60 px-3 py-2 text-xs text-slate-500">
                                                Güven skoru:
                                                <span class="font-mono font-semibold text-slate-900">{{ $formatPercent($action['signal_progress']['health_score_before']) }}</span>
                                                →
                                                <span class="font-mono font-semibold text-slate-900">{{ $formatPercent($action['signal_progress']['health_score_now']) }}</span>
                                                <span class="ml-1 font-mono {{ ($action['signal_progress']['health_score_delta'] ?? 0) >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                                                    {{ ($action['signal_progress']['health_score_delta'] ?? 0) >= 0 ? '+' : '' }}{{ $formatPercent($action['signal_progress']['health_score_delta'] ?? 0) }}
                                                </span>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                                @if(($action['closure_summary']['is_resolved'] ?? false))
                                    <div class="mt-3 rounded-[8px] border border-emerald-200 bg-white p-3">
                                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                            <div>
                                                <p class="text-sm font-semibold text-slate-900">Kapanış özeti</p>
                                                <p class="mt-1 text-xs leading-5 text-slate-500">Aksiyon kapatılırken kaydedilen not ve kanıt kalitesi.</p>
                                            </div>
                                            <span class="rounded-[6px] border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs font-mono text-emerald-700">
                                                {{ $action['closure_summary']['quality_label'] ?? 'Kapanış' }} · {{ $formatPercent($action['closure_summary']['quality_percent'] ?? 0) }}
                                            </span>
                                        </div>
                                        @if(!empty($action['closure_summary']['note_excerpt']))
                                            <p class="mt-3 rounded-[6px] border border-slate-200 bg-slate-50/60 px-3 py-2 text-xs leading-5 text-slate-600">
                                                {{ $action['closure_summary']['note_excerpt'] }}
                                            </p>
                                        @endif
                                        <div class="mt-3 grid grid-cols-2 gap-2 lg:grid-cols-5">
                                            <div class="rounded-[6px] border px-3 py-2 {{ ($action['closure_summary']['has_note'] ?? false) ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700' }}">
                                                <p class="text-xs font-mono">{{ ($action['closure_summary']['has_note'] ?? false) ? 'Not var' : 'Not eksik' }}</p>
                                            </div>
                                            <div class="rounded-[6px] border px-3 py-2 {{ ($action['closure_summary']['has_owner'] ?? false) ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700' }}">
                                                <p class="text-xs font-mono">{{ ($action['closure_summary']['has_owner'] ?? false) ? 'Sorumlu var' : 'Sorumlu eksik' }}</p>
                                            </div>
                                            <div class="rounded-[6px] border px-3 py-2 {{ ($action['closure_summary']['has_due_date'] ?? false) ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700' }}">
                                                <p class="text-xs font-mono">{{ ($action['closure_summary']['has_due_date'] ?? false) ? 'Hedef var' : 'Hedef eksik' }}</p>
                                            </div>
                                            <div class="rounded-[6px] border px-3 py-2 {{ ($action['closure_summary']['on_time'] ?? false) ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-50/70 text-slate-500' }}">
                                                <p class="text-xs font-mono">{{ ($action['closure_summary']['on_time'] ?? false) ? 'Zamanında' : 'Zaman kontrolü' }}</p>
                                            </div>
                                            @if(($action['closure_summary']['plan_total_steps'] ?? 0) > 0)
                                                <div class="rounded-[6px] border px-3 py-2 {{ ($action['closure_summary']['plan_complete'] ?? false) ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700' }}">
                                                    <p class="text-xs font-mono">{{ ($action['closure_summary']['plan_complete'] ?? false) ? 'Plan tamam' : 'Plan eksik' }}</p>
                                                    <p class="mt-1 text-[11px] font-mono">{{ $formatPercent($action['closure_summary']['plan_percent'] ?? 0) }}</p>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                                        @else
                                            <div class="mt-3 grid grid-cols-1 gap-2 lg:grid-cols-3">
                                                @if(!empty($action['playbook_steps']))
                                                    <div class="rounded-[6px] border border-slate-200 bg-slate-50/60 px-3 py-2">
                                                        <div class="flex items-center justify-between gap-3 text-xs">
                                                            <span class="font-semibold text-slate-700">Plan</span>
                                                            <span class="font-mono text-slate-500">{{ $formatPercent($action['playbook_progress']['percent'] ?? 0) }}</span>
                                                        </div>
                                                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-200">
                                                            <div class="h-full rounded-full {{ ($action['playbook_progress']['is_complete'] ?? false) ? 'bg-emerald-500' : 'bg-slate-900' }}"
                                                                 style="width: {{ min(100, max(0, (float) ($action['playbook_progress']['percent'] ?? 0))) }}%"></div>
                                                        </div>
                                                        <p class="mt-2 truncate text-xs text-slate-500" title="{{ $action['playbook_progress']['next_step_label'] ?? 'Plan adımları tamamlandı.' }}">
                                                            {{ $action['playbook_progress']['next_step_label'] ?? 'Plan adımları tamamlandı.' }}
                                                        </p>
                                                    </div>
                                                @endif
                                                @if(($action['signal_progress']['tracked'] ?? false))
                                                    <div class="rounded-[6px] border border-slate-200 bg-slate-50/60 px-3 py-2">
                                                        <div class="flex items-center justify-between gap-3">
                                                            <p class="text-xs font-semibold text-slate-700">Sinyal</p>
                                                            <span class="font-mono text-xs text-emerald-700">{{ $formatPercent($action['signal_progress']['improvement_percent']) }}</span>
                                                        </div>
                                                        <p class="mt-2 truncate text-xs text-slate-500">
                                                            {{ $formatCount($action['signal_progress']['initial_value']) }} → {{ $formatCount($action['signal_progress']['current_value']) }}
                                                        </p>
                                                    </div>
                                                @endif
                                                @if(($action['closure_summary']['is_resolved'] ?? false))
                                                    <div class="rounded-[6px] border border-emerald-200 bg-emerald-50 px-3 py-2">
                                                        <div class="flex items-center justify-between gap-3">
                                                            <p class="text-xs font-semibold text-emerald-800">Kapanış</p>
                                                            <span class="font-mono text-xs text-emerald-700">{{ $formatPercent($action['closure_summary']['quality_percent'] ?? 0) }}</span>
                                                        </div>
                                                        <p class="mt-2 truncate text-xs text-emerald-700">
                                                            {{ $action['closure_summary']['quality_label'] ?? 'Kapanış' }} · {{ ($action['closure_summary']['plan_complete'] ?? false) ? 'Plan tamam' : 'Plan kontrol' }}
                                                        </p>
                                                    </div>
                                                @endif
                                            </div>
                                        @endif
                                @if($action['resolved_at'] || $action['snoozed_until'])
                                    <div class="mt-3 flex flex-wrap gap-2 text-xs text-slate-400">
                                        @if($action['resolved_at'])
                                            <span>Çözüm: {{ $action['resolved_at'] }}</span>
                                        @endif
                                        @if($action['snoozed_until'])
                                            <span>Erteleme: {{ $action['snoozed_until'] }}</span>
                                        @endif
                                    </div>
                                @endif
                                </div>
                            </div>
                            </div>

                            <aside class="border-t border-slate-200 bg-slate-50/60 p-3 xl:border-l xl:border-t-0">
                                <div class="grid grid-cols-1 gap-2">
                                    <div>
                                        <label for="profit-action-priority-{{ $action['id'] }}" class="text-xs font-medium text-slate-500">Öncelik</label>
                                        <select id="profit-action-priority-{{ $action['id'] }}"
                                                wire:model.defer="actionPriorities.{{ $action['id'] }}"
                                                class="mt-1 min-h-[40px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-700 outline-none transition focus:border-slate-400 focus:ring-2 focus:ring-slate-100 sm:text-sm">
                                            @foreach($actionPriorityOptions as $priorityValue => $priorityLabel)
                                                <option value="{{ $priorityValue }}">{{ $priorityLabel }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label for="profit-action-due-{{ $action['id'] }}" class="text-xs font-medium text-slate-500">Hedef tarih</label>
                                        <input id="profit-action-due-{{ $action['id'] }}"
                                               type="date"
                                               wire:model.defer="actionDueDates.{{ $action['id'] }}"
                                               class="mt-1 min-h-[40px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-700 outline-none transition focus:border-slate-400 focus:ring-2 focus:ring-slate-100 sm:text-sm">
                                    </div>
                                    <div>
                                        <label for="profit-action-owner-{{ $action['id'] }}" class="text-xs font-medium text-slate-500">Sorumlu</label>
                                        <input id="profit-action-owner-{{ $action['id'] }}"
                                               type="text"
                                               wire:model.defer="actionOwners.{{ $action['id'] }}"
                                               class="mt-1 min-h-[40px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-slate-400 focus:ring-2 focus:ring-slate-100 sm:text-sm"
                                               placeholder="Ekip veya kişi">
                                    </div>
                                    <button type="button"
                                            wire:click="saveActionMeta({{ $action['id'] }})"
                                            wire:loading.attr="disabled"
                                            wire:target="saveActionMeta({{ $action['id'] }})"
                                            class="min-h-[40px] rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-60">
                                        Planı kaydet
                                    </button>
                                </div>

                                <div class="mt-3 border-t border-slate-200 pt-3">
                                    <label for="profit-action-note-{{ $action['id'] }}" class="text-xs font-medium text-slate-500">Karar notu</label>
                                    <textarea id="profit-action-note-{{ $action['id'] }}"
                                              wire:model.defer="actionNotes.{{ $action['id'] }}"
                                              rows="3"
                                              class="mt-1 min-h-[84px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-slate-400 focus:ring-2 focus:ring-slate-100 sm:text-sm"
                                              placeholder="Kısa not, kontrol sonucu veya sonraki adım"></textarea>
                                    <p class="mt-1 text-xs text-slate-400">Çözüldü olarak kapatmak için kısa karar notu gerekir.</p>
                                    <button type="button"
                                            wire:click="saveActionNote({{ $action['id'] }})"
                                            wire:loading.attr="disabled"
                                            wire:target="saveActionNote({{ $action['id'] }})"
                                            class="mt-2 min-h-[40px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-60">
                                        Notu kaydet
                                    </button>
                                </div>

                                <div class="mt-3 grid grid-cols-1 gap-2 border-t border-slate-200 pt-3">
                                <a href="{{ $this->actionItemUrl($action) }}"
                                   class="min-h-[40px] rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-center text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                    {{ $action['action_label'] ?: 'Aksiyonu aç' }}
                                </a>
                                <button type="button"
                                        wire:click="openActionTimeline({{ $action['id'] }})"
                                        class="min-h-[40px] rounded-[6px] border px-3 py-2 text-sm font-medium transition {{ $timelineActionId === $action['id'] ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50' }}">
                                    Geçmiş
                                </button>
                            </div>

                                <div class="mt-2 grid grid-cols-1 gap-2">
                                @if($action['status'] === \App\Models\MpProfitActionItem::STATUS_RESOLVED)
                                    <button type="button"
                                            wire:click="reopenAction({{ $action['id'] }})"
                                            class="min-h-[40px] rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                        Yeniden aç
                                    </button>
                                @else
                                    @if($action['status'] !== \App\Models\MpProfitActionItem::STATUS_IN_PROGRESS)
                                        <button type="button"
                                                wire:click="startAction({{ $action['id'] }})"
                                                class="min-h-[40px] rounded-[6px] border border-sky-200 bg-sky-50 px-3 py-2 text-sm font-medium text-sky-700 transition hover:bg-sky-100">
                                            İncelemeye al
                                        </button>
                                    @endif
                                    <button type="button"
                                            wire:click="snoozeAction({{ $action['id'] }})"
                                            class="min-h-[40px] rounded-[6px] border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-medium text-amber-700 transition hover:bg-amber-100">
                                        Ertele
                                    </button>
                                    <button type="button"
                                            wire:click="resolveAction({{ $action['id'] }})"
                                            class="min-h-[40px] rounded-[6px] border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700 transition hover:bg-emerald-100">
                                        Çözüldü
                                    </button>
                                @endif
                                </div>
                            </aside>
                        </div>
                    </div>
                @empty
                    <div class="rounded-[8px] border border-dashed border-slate-300 bg-slate-50/60 p-5 text-sm text-slate-500">
                        @if($actionFilterCount > 0)
                            Seçili aksiyon filtrelerinde sonuç yok. Filtreleri sıfırlayıp kuyruğu yeniden genişletebilirsiniz.
                        @elseif($actionDeskFilter === 'resolved')
                            Seçili aralıkta çözülen aksiyon yok.
                        @elseif($actionDeskFilter === 'all')
                            Bu filtre setinde aksiyon geçmişi bulunmuyor.
                        @else
                            Henüz listeye eklenen bir görev yok. Öncelik önerilerinden birini görev listesine ekleyin.
                        @endif
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</section>


        @if($activePanel === 'finance')
        <section class="grid grid-cols-1 items-start gap-4 lg:gap-6 xl:grid-cols-12">
            <div class="self-start rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6 xl:col-span-8">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">Kâr akışı</h2>
                        <p class="mt-1 text-sm text-slate-500">Seçili aralıkta ciro, kâr ve kesinti hareketini gün bazında izleyin.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
                        <span class="inline-flex items-center gap-1 rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-1"><span class="h-2 w-2 rounded-full bg-slate-300"></span>Ciro</span>
                        <span class="inline-flex items-center gap-1 rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-1"><span class="h-2 w-2 rounded-full bg-emerald-500"></span>Kâr</span>
                        <span class="inline-flex items-center gap-1 rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-1"><span class="h-2 w-2 rounded-full bg-amber-500"></span>Kesinti</span>
                    </div>
                </div>

                <div class="mt-5 overflow-hidden rounded-[8px] border border-slate-200 bg-slate-50/50">
                    @if($trendRows->isNotEmpty())
                        <svg viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}" class="h-[260px] w-full" role="img" aria-label="Kâr akışı grafiği">
                            @foreach([0, 1, 2, 3] as $grid)
                                @php
                                    $gridY = $chartPaddingY + (($plotHeight / 3) * $grid);
                                @endphp
                                <line x1="{{ $chartPaddingX }}" y1="{{ $gridY }}" x2="{{ $chartWidth - $chartPaddingX }}" y2="{{ $gridY }}" stroke="#e2e8f0" stroke-width="1" />
                            @endforeach

                            @foreach($trendPoints as $point)
                                <rect
                                    x="{{ $point['bar_x'] }}"
                                    y="{{ $plotBottom - $point['gross_height'] }}"
                                    width="{{ $point['bar_width'] }}"
                                    height="{{ max(2, $point['gross_height']) }}"
                                    rx="4"
                                    fill="#cbd5e1"
                                    opacity="0.72"
                                />
                            @endforeach

                            @if($deductionPolyline !== '')
                                <polyline points="{{ $deductionPolyline }}" fill="none" stroke="#d97706" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" opacity="0.78" />
                            @endif

                            @if($profitPolyline !== '')
                                <polyline points="{{ $profitPolyline }}" fill="none" stroke="#059669" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" />
                            @endif

                            @foreach($trendPoints as $point)
                                <circle cx="{{ $point['x'] }}" cy="{{ $point['profit_y'] }}" r="{{ $point['profit_positive'] ? 4 : 5 }}" fill="{{ $point['profit_positive'] ? '#059669' : '#e11d48' }}" stroke="#ffffff" stroke-width="2" />
                                @if($loop->first || $loop->last || $loop->index % $labelInterval === 0)
                                    <text x="{{ $point['x'] }}" y="{{ $chartHeight - 6 }}" text-anchor="middle" font-size="11" fill="#64748b">{{ $point['date_label'] }}</text>
                                @endif
                            @endforeach
                        </svg>
                    @else
                        <div class="flex h-[260px] items-center justify-center px-4 text-center text-sm text-slate-500">
                            Seçili aralıkta grafik oluşturacak sipariş verisi yok.
                        </div>
                    @endif
                </div>
            </div>

            <div class="self-start space-y-4 lg:space-y-6 xl:col-span-4">
                <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-bold text-slate-900">Kâr sağlığı</h2>
                            <p class="mt-1 text-sm text-slate-500">Ciro marjı, finans kapsama ve zarar baskısı birlikte okunur.</p>
                        </div>
                        <div class="grid h-24 w-24 shrink-0 place-items-center rounded-full"
                             style="background: conic-gradient(#059669 0 {{ $healthPercent }}%, #e2e8f0 {{ $healthPercent }}% 100%);">
                            <div class="grid h-16 w-16 place-items-center rounded-full bg-white text-center">
                                <span class="text-sm font-bold text-slate-900">{{ $formatPercent($summary['profit_margin_percent']) }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-5 space-y-3">
                        <div>
                            <div class="flex items-center justify-between text-xs text-slate-500">
                                <span>Finans kapsama</span>
                                <span class="font-mono">{{ $formatPercent($financeCoveragePercent) }}</span>
                            </div>
                            <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-slate-900" style="width: {{ min(100, $financeCoveragePercent) }}%"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex items-center justify-between text-xs text-slate-500">
                                <span>Zarar baskısı</span>
                                <span class="font-mono">{{ $formatPercent($lossPressurePercent) }}</span>
                            </div>
                            <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-rose-500" style="width: {{ min(100, $lossPressurePercent) }}%"></div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-bold text-slate-900">Kesinti kompozisyonu</h2>
                            <p class="mt-1 text-sm text-slate-500">Komisyon, kargo, hizmet ve stopaj baskısını ayırın.</p>
                        </div>
                        <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-mono text-slate-500">{{ $formatMoney($summary['total_deductions']) }}</span>
                    </div>

                    <div class="mt-5 h-3 overflow-hidden rounded-full bg-slate-100">
                        <div class="flex h-full w-full">
                            @foreach($deductionBreakdown as $row)
                                <div class="{{ $deductionColorMap[$row['tone']] ?? 'bg-slate-500' }}" style="width: {{ max(0, min(100, $row['percent'])) }}%"></div>
                            @endforeach
                        </div>
                    </div>

                    <div class="mt-4 space-y-2">
                        @foreach($deductionBreakdown as $row)
                            <div class="flex items-center justify-between gap-3 rounded-[6px] border border-slate-200 bg-slate-50/60 px-3 py-2">
                                <div class="flex min-w-0 items-center gap-2">
                                    <span class="h-2.5 w-2.5 rounded-full {{ $deductionColorMap[$row['tone']] ?? 'bg-slate-500' }}"></span>
                                    <span class="text-sm font-medium text-slate-700">{{ $row['label'] }}</span>
                                </div>
                                <div class="shrink-0 text-right">
                                    <p class="text-sm font-semibold text-slate-900">{{ $formatMoney($row['value']) }}</p>
                                    <p class="text-xs font-mono text-slate-500">{{ $formatPercent($row['percent']) }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            </div>
        </section>
        @elseif($activePanel === 'orders')
        <section class="grid grid-cols-1 items-start gap-4 lg:gap-6 xl:grid-cols-12">
            <div class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6 xl:col-span-8">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">Sipariş risk hunisi</h2>
                        <p class="mt-1 text-sm text-slate-500">Ödeme bekleyen, zararda olan ve ödeme farkı yüksek siparişleri aynı akışta okuyun.</p>
                    </div>
                    <a href="{{ $this->ordersUrl() }}"
                       class="w-full min-h-[44px] rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-center text-base font-medium text-slate-700 transition hover:bg-slate-50 sm:w-auto sm:py-2 sm:text-sm">
                        Sipariş listesinde aç
                    </a>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                        <p class="text-xs font-mono text-slate-500">Karar kuyruğu</p>
                        <p class="mt-1 text-xl font-bold text-slate-900">{{ $formatCount($orderDecisionInsights['queue_count']) }}</p>
                        <p class="mt-1 text-xs text-slate-500">öncelikli sipariş</p>
                    </div>
                    <div class="rounded-[8px] border border-rose-200 bg-rose-50 p-3">
                        <p class="text-xs font-mono text-rose-700">En yüksek skor</p>
                        <p class="mt-1 text-xl font-bold text-rose-700">{{ $formatCount($orderDecisionInsights['highest_score']) }}</p>
                        <p class="mt-1 text-xs text-rose-700">{{ $formatCount($orderDecisionInsights['critical_order_count']) }} kritik</p>
                    </div>
                    <div class="rounded-[8px] border border-amber-200 bg-amber-50 p-3">
                        <p class="text-xs font-mono text-amber-700">Ana neden</p>
                        <p class="mt-1 truncate text-sm font-bold text-amber-800">{{ $orderDecisionInsights['top_reason'] }}</p>
                        <p class="mt-1 text-xs text-amber-700">{{ $formatCount($orderDecisionInsights['top_reason_count']) }} sipariş</p>
                    </div>
                    <div class="rounded-[8px] border border-slate-200 bg-white p-3">
                        <p class="text-xs font-mono text-slate-500">Ortalama skor</p>
                        <p class="mt-1 text-xl font-bold text-slate-900">{{ $formatCount($orderDecisionInsights['average_score']) }}</p>
                        <p class="mt-1 text-xs text-slate-500">risk seviyesi</p>
                    </div>
                </div>

                <div class="mt-4 rounded-[8px] border border-slate-200 bg-slate-50/60 p-4">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-900">Öncelik rehberi</p>
                            <p class="mt-1 text-sm leading-5 text-slate-500">{{ $orderDecisionInsights['decision_hint'] }}</p>
                        </div>
                        <div class="grid grid-cols-3 gap-2 lg:min-w-[320px]">
                            <div class="rounded-[6px] border border-white bg-white px-2 py-2 text-center">
                                <p class="text-xs text-slate-500">Finans</p>
                                <p class="mt-1 font-mono text-sm font-bold text-amber-700">{{ $formatPercent($orderDecisionInsights['finance_waiting_percent']) }}</p>
                            </div>
                            <div class="rounded-[6px] border border-white bg-white px-2 py-2 text-center">
                                <p class="text-xs text-slate-500">Zarar</p>
                                <p class="mt-1 font-mono text-sm font-bold text-rose-700">{{ $formatPercent($orderDecisionInsights['loss_pressure_percent']) }}</p>
                            </div>
                            <div class="rounded-[6px] border border-white bg-white px-2 py-2 text-center">
                                <p class="text-xs text-slate-500">Fark</p>
                                <p class="mt-1 font-mono text-sm font-bold text-slate-900">{{ $formatPercent($orderDecisionInsights['material_variance_percent']) }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 space-y-2">
                        @forelse($orderDecisionInsights['reason_distribution'] as $reasonRow)
                            @php
                                $reasonTone = $funnelToneClasses[$reasonRow['tone']] ?? $funnelToneClasses['slate'];
                            @endphp
                            <div>
                                <div class="flex items-center justify-between gap-3 text-xs">
                                    <span class="truncate font-medium text-slate-600">{{ $reasonRow['label'] }}</span>
                                    <span class="font-mono text-slate-500">{{ $formatCount($reasonRow['count']) }} · {{ $formatPercent($reasonRow['percent']) }}</span>
                                </div>
                                <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-white">
                                    <div class="h-full rounded-full {{ $reasonTone['bar'] }}" style="width: {{ min(100, max(0, $reasonRow['percent'])) }}%"></div>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-[6px] border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                                Seçili aralıkta kök neden dağılımı oluşturacak kritik sipariş yok.
                            </div>
                        @endforelse
                    </div>
                </div>

                <div class="mt-4 rounded-[8px] border border-slate-200 bg-white p-4">
                    <div class="flex flex-col gap-2 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-900">Karar akış planı</p>
                            <p class="mt-1 text-xs leading-5 text-slate-500">Sipariş risk kuyruğunu operasyon sırasına göre kapatın.</p>
                        </div>
                        <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-mono text-slate-500">
                            4 kontrol adımı
                        </span>
                    </div>

                    <div class="mt-3 grid grid-cols-1 gap-2 md:grid-cols-2 2xl:grid-cols-4">
                        <a href="{{ $this->financeUrl(['financialStateFilter' => 'waiting']) }}"
                           class="rounded-[8px] border border-amber-200 bg-amber-50 px-3 py-2 transition hover:bg-amber-100">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-xs font-mono text-amber-700">1. Finans</p>
                                    <p class="mt-1 text-sm font-semibold text-amber-900">Hakedişi kesinleştir</p>
                                </div>
                                <span class="font-mono text-sm font-bold text-amber-800">{{ $formatCount($summary['finance_waiting_order_count']) }}</span>
                            </div>
                            <p class="mt-2 text-xs leading-4 text-amber-800">{{ $formatPercent($orderDecisionInsights['finance_waiting_percent']) }} finans bekleyen sipariş.</p>
                        </a>
                        <a href="{{ $this->financeUrl(['sortField' => 'profit_value_metric', 'sortDirection' => 'asc']) }}"
                           class="rounded-[8px] border border-rose-200 bg-rose-50 px-3 py-2 transition hover:bg-rose-100">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-xs font-mono text-rose-700">2. Zarar</p>
                                    <p class="mt-1 text-sm font-semibold text-rose-900">Negatif kârı doğrula</p>
                                </div>
                                <span class="font-mono text-sm font-bold text-rose-800">{{ $formatCount($summary['loss_order_count']) }}</span>
                            </div>
                            <p class="mt-2 text-xs leading-4 text-rose-800">{{ $formatPercent($orderDecisionInsights['loss_pressure_percent']) }} zarar baskısı.</p>
                        </a>
                        <a href="{{ $this->financeUrl(['deltaStateFilter' => 'material', 'sortField' => 'reconciliation_delta_abs_metric', 'sortDirection' => 'desc']) }}"
                           class="rounded-[8px] border border-slate-200 bg-slate-50 px-3 py-2 transition hover:bg-white">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-xs font-mono text-slate-500">3. Ödeme Kontrolü</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-900">Farkı kapat</p>
                                </div>
                                <span class="font-mono text-sm font-bold text-slate-900">{{ $formatCount($summary['material_variance_order_count']) }}</span>
                            </div>
                            <p class="mt-2 text-xs leading-4 text-slate-500">{{ $formatPercent($orderDecisionInsights['material_variance_percent']) }} yüksek fark oranı.</p>
                        </a>
                        <a href="{{ $this->matchingUrl(['statusFilter' => 'pending']) }}"
                           class="rounded-[8px] border border-sky-200 bg-sky-50 px-3 py-2 transition hover:bg-sky-100">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-xs font-mono text-sky-700">4. Eşleşme</p>
                                    <p class="mt-1 text-sm font-semibold text-sky-900">Ürün bağını tamamla</p>
                                </div>
                                <span class="font-mono text-sm font-bold text-sky-800">{{ $formatCount($costReadiness['unmatched_lines']) }}</span>
                            </div>
                            <p class="mt-2 text-xs leading-4 text-sky-800">Eşleşmeyen satırlar kâr güvenini düşürür.</p>
                        </a>
                    </div>
                </div>

                <div class="mt-4 rounded-[8px] border border-slate-200 bg-white p-4">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-sm font-semibold text-slate-900">Risk yoğunluğu</p>
                                <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-mono text-slate-500">
                                    {{ $formatCompactMoney($orderDecisionInsights['risk_exposure'] ?? 0) }} baskı
                                </span>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">Karar kuyruğundaki siparişleri skor bandı ve finansal baskı ile ayırın.</p>
                        </div>
                        <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-3 py-2 sm:min-w-[180px]">
                            <p class="text-xs text-slate-500">Baskın bant</p>
                            <p class="mt-1 truncate font-bold text-slate-900">{{ $orderDecisionInsights['dominant_risk_lane']['label'] ?? 'Yok' }}</p>
                        </div>
                    </div>
                    <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-3">
                        @foreach($orderDecisionInsights['risk_lane_rows'] ?? [] as $riskLane)
                            @php
                                $laneTone = $funnelToneClasses[$riskLane['tone'] ?? 'slate'] ?? $funnelToneClasses['slate'];
                            @endphp
                            <div class="rounded-[6px] border border-slate-200 {{ $laneTone['soft'] }} px-3 py-2">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="truncate text-xs font-semibold text-slate-700">{{ $riskLane['label'] }}</p>
                                        <p class="mt-1 text-lg font-bold {{ $laneTone['text'] }}">{{ $formatCount($riskLane['count'] ?? 0) }}</p>
                                    </div>
                                    <span class="rounded-[6px] border border-white/70 bg-white/80 px-2 py-0.5 text-xs font-mono text-slate-500">{{ $formatPercent($riskLane['percent'] ?? 0) }}</span>
                                </div>
                                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-white/80">
                                    <div class="h-full rounded-full {{ $laneTone['bar'] }}" style="width: {{ min(100, max(0, $riskLane['percent'] ?? 0)) }}%"></div>
                                </div>
                                <p class="mt-2 truncate text-xs text-slate-500" title="{{ $formatMoney($riskLane['exposure'] ?? 0) }}">
                                    Baskı {{ $formatCompactMoney($riskLane['exposure'] ?? 0) }} · Ort. skor {{ $formatCount($riskLane['average_score'] ?? 0) }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach($orderRiskFunnel as $step)
                        @php
                            $tone = $funnelToneClasses[$step['tone']] ?? $funnelToneClasses['slate'];
                        @endphp
                        <div class="rounded-[8px] border border-slate-200 {{ $tone['soft'] }} p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-xs font-mono text-slate-500">{{ $step['label'] }}</p>
                                    <p class="mt-2 text-2xl font-bold {{ $tone['text'] }}">{{ $formatCount($step['value']) }}</p>
                                </div>
                                <span class="rounded-[6px] border border-white/70 bg-white/80 px-2 py-0.5 text-xs font-mono text-slate-500">{{ $formatPercent($step['percent']) }}</span>
                            </div>
                            <div class="mt-4 h-2 overflow-hidden rounded-full bg-white/80">
                                <div class="h-full rounded-full {{ $tone['bar'] }}" style="width: {{ min(100, max(0, $step['percent'])) }}%"></div>
                            </div>
                            <p class="mt-3 text-sm leading-5 text-slate-600">{{ $step['description'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            <aside class="space-y-4 lg:space-y-6 xl:col-span-4">
                <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-5">
                    <h2 class="text-lg font-bold text-slate-900">Sipariş sağlığı</h2>
                    <p class="mt-1 text-sm text-slate-500">Öncelik: zarar, eksik/hatalı ödeme, ödeme bekleme ve eşleşme eksikleri.</p>
                    <div class="mt-5 space-y-3">
                        <div class="flex items-center justify-between rounded-[8px] border border-rose-200 bg-rose-50 px-3 py-2">
                            <span class="text-sm font-medium text-rose-800">Zarar baskısı</span>
                            <span class="font-mono text-sm font-semibold text-rose-800">{{ $formatCount($summary['loss_order_count']) }}</span>
                        </div>
                        <div class="flex items-center justify-between rounded-[8px] border border-amber-200 bg-amber-50 px-3 py-2">
                            <span class="text-sm font-medium text-amber-800">Finans bekleyen</span>
                            <span class="font-mono text-sm font-semibold text-amber-800">{{ $formatCount($summary['finance_waiting_order_count']) }}</span>
                        </div>
                        <div class="flex items-center justify-between rounded-[8px] border border-slate-200 bg-slate-50 px-3 py-2">
                            <span class="text-sm font-medium text-slate-700">Ödeme kontrolü uyumlu</span>
                            <span class="font-mono text-sm font-semibold text-slate-900">{{ $formatCount($summary['aligned_order_count']) }}</span>
                        </div>
                    </div>
                </section>

                <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-5">
                    <h2 class="text-lg font-bold text-slate-900">Hızlı aksiyonlar</h2>
                    <div class="mt-4 grid grid-cols-1 gap-2">
                        <a href="{{ $this->financeUrl(['financialStateFilter' => 'waiting']) }}"
                           class="rounded-[8px] border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-medium text-amber-800 transition hover:bg-amber-100">
                            Finans bekleyenleri aç
                        </a>
                        <a href="{{ $this->financeUrl(['deltaStateFilter' => 'material', 'sortField' => 'reconciliation_delta_abs_metric', 'sortDirection' => 'desc']) }}"
                           class="rounded-[8px] border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-medium text-rose-800 transition hover:bg-rose-100">
                            Mutabakat farklarını incele
                        </a>
                        <a href="{{ $this->ordersUrl(['financialStateFilter' => 'waiting']) }}"
                           class="rounded-[8px] border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-white">
                            Sipariş operasyon listesine git
                        </a>
                    </div>
                </section>
            </aside>
        </section>
        @elseif($activePanel === 'products')
        <section class="grid grid-cols-1 items-start gap-4 lg:gap-6 xl:grid-cols-12">
            <div class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6 xl:col-span-8">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">Ürün kârlılık matrisi</h2>
                        <p class="mt-1 text-sm text-slate-500">Ciro, tahmini maliyet, komisyon ve hazırlık eksiklerini ürün bazında karşılaştırın.</p>
                    </div>
                    <a href="{{ $this->productsUrl() }}"
                       class="w-full min-h-[44px] rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-center text-base font-medium text-slate-700 transition hover:bg-slate-50 sm:w-auto sm:py-2 sm:text-sm">
                        Ürün listesinde aç
                    </a>
                </div>

                <div class="mt-4 rounded-[8px] border border-slate-200 bg-slate-50/60 p-4">
                    <div class="flex flex-col gap-3 2xl:flex-row 2xl:items-start 2xl:justify-between">
                        <div class="min-w-0 2xl:flex-1">
                            <p class="text-sm font-semibold text-slate-900">Ürün karar özeti</p>
                            <p class="mt-1 text-sm leading-5 text-slate-500">{{ $productReadinessInsights['decision_hint'] }}</p>
                        </div>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4 2xl:w-[460px] 2xl:shrink-0">
                            <div class="min-w-0 rounded-[6px] border border-white bg-white px-2 py-2">
                                <p class="text-xs text-slate-500">Riskli ürün</p>
                                <p class="mt-1 truncate font-mono text-sm font-bold text-amber-700">{{ $formatCount($productReadinessInsights['risk_product_count']) }}</p>
                            </div>
                            <div class="min-w-0 rounded-[6px] border border-white bg-white px-2 py-2">
                                <p class="text-xs text-slate-500">Etkilenen ciro</p>
                                <p class="mt-1 truncate font-mono text-sm font-bold text-slate-900" title="{{ $formatMoney($productReadinessInsights['affected_revenue']) }}">{{ $formatCompactMoney($productReadinessInsights['affected_revenue']) }}</p>
                            </div>
                            <div class="min-w-0 rounded-[6px] border border-white bg-white px-2 py-2">
                                <p class="text-xs text-slate-500">Negatif ürün</p>
                                <p class="mt-1 truncate font-mono text-sm font-bold text-rose-700">{{ $formatCount($productReadinessInsights['negative_product_count']) }}</p>
                            </div>
                            <div class="min-w-0 rounded-[6px] border border-white bg-white px-2 py-2">
                                <p class="text-xs text-slate-500">Kayıp baskısı</p>
                                <p class="mt-1 truncate font-mono text-sm font-bold text-rose-700" title="{{ $formatMoney($productReadinessInsights['negative_pressure']) }}">{{ $formatCompactMoney($productReadinessInsights['negative_pressure']) }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 space-y-2">
                        @foreach($productReadinessInsights['segments'] as $segment)
                            @php
                                $segmentTone = $funnelToneClasses[$segment['tone']] ?? $funnelToneClasses['slate'];
                            @endphp
                            <div>
                                <div class="flex items-center justify-between gap-3 text-xs">
                                    <span class="truncate font-medium text-slate-600">{{ $segment['label'] }}</span>
                                    <span class="font-mono text-slate-500">{{ $formatCount($segment['value']) }} · {{ $formatPercent($segment['percent']) }}</span>
                                </div>
                                <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-white">
                                    <div class="h-full rounded-full {{ $segmentTone['bar'] }}" style="width: {{ min(100, max(0, $segment['percent'])) }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-3 xl:grid-cols-2">
                        <div class="rounded-[8px] border border-slate-200 bg-white p-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-slate-900">Ciro lideri</p>
                                    <p class="mt-1 text-xs text-slate-500">En yüksek ciro taşıyan ürün odağı.</p>
                                </div>
                                <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-mono text-slate-500">Odak</span>
                            </div>
                            @if(!empty($productReadinessInsights['top_revenue_product']))
                                <div class="mt-3 rounded-[6px] border border-slate-200 bg-slate-50/60 px-3 py-2">
                                    <p class="truncate text-sm font-semibold text-slate-900">{{ $productReadinessInsights['top_revenue_product']['product_name'] }}</p>
                                    <p class="mt-1 truncate text-xs text-slate-500">{{ $productReadinessInsights['top_revenue_product']['stock_code'] ?: 'Stok kodu yok' }}</p>
                                    <div class="mt-3 grid grid-cols-2 gap-2">
                                        <div>
                                            <p class="text-xs text-slate-500">Ciro</p>
                                            <p class="mt-1 truncate text-sm font-bold text-slate-900" title="{{ $formatMoney($productReadinessInsights['top_revenue_product']['gross_revenue'] ?? 0) }}">{{ $formatCompactMoney($productReadinessInsights['top_revenue_product']['gross_revenue'] ?? 0) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-slate-500">Marj</p>
                                            <p class="mt-1 text-sm font-bold text-slate-900">{{ $formatPercent($productReadinessInsights['top_revenue_product']['profit_margin_percent'] ?? 0) }}</p>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <p class="mt-3 rounded-[6px] border border-dashed border-slate-300 bg-slate-50/60 px-3 py-2 text-xs text-slate-500">Ciro lideri oluşturacak ürün verisi yok.</p>
                            @endif
                        </div>

                        <div class="rounded-[8px] border border-slate-200 bg-white p-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-slate-900">Kayıp odağı</p>
                                    <p class="mt-1 text-xs text-slate-500">Önce maliyet ve fiyatı kontrol edilecek ürün.</p>
                                </div>
                                <span class="rounded-[6px] border border-rose-200 bg-rose-50 px-2 py-0.5 text-xs font-mono text-rose-700">Risk</span>
                            </div>
                            @if(!empty($productReadinessInsights['top_loss_product']))
                                <div class="mt-3 rounded-[6px] border border-rose-200 bg-rose-50 px-3 py-2">
                                    <p class="truncate text-sm font-semibold text-rose-900">{{ $productReadinessInsights['top_loss_product']['product_name'] }}</p>
                                    <p class="mt-1 truncate text-xs text-rose-700">{{ $productReadinessInsights['top_loss_product']['decision_hint'] ?? 'Maliyet ve fiyat kontrolü' }}</p>
                                    <div class="mt-3 grid grid-cols-2 gap-2">
                                        <div>
                                            <p class="text-xs text-rose-700">Kayıp</p>
                                            <p class="mt-1 truncate text-sm font-bold text-rose-800" title="{{ $formatMoney($productReadinessInsights['top_loss_product']['profit_value'] ?? 0) }}">{{ $formatCompactMoney($productReadinessInsights['top_loss_product']['profit_value'] ?? 0) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-rose-700">Hazırlık</p>
                                            <p class="mt-1 text-sm font-bold text-rose-800">{{ $formatPercent($productReadinessInsights['top_loss_product']['readiness_score'] ?? 0) }}</p>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <p class="mt-3 rounded-[6px] border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-700">Negatif kâr üreten ürün görünmüyor.</p>
                            @endif
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-3 xl:grid-cols-2">
                        <div class="rounded-[8px] border border-slate-200 bg-white p-3">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">Marj dağılımı</p>
                                    <p class="mt-1 text-xs text-slate-500">Listelenen ürünlerin kâr marjı bantları.</p>
                                </div>
                                @if(!empty($productReadinessInsights['top_loss_product']))
                                    <span class="rounded-[6px] border border-rose-200 bg-rose-50 px-2 py-0.5 text-xs font-mono text-rose-700">
                                        Kayıp {{ $formatCompactMoney(abs($productReadinessInsights['top_loss_product']['profit_value'] ?? 0)) }}
                                    </span>
                                @endif
                            </div>
                            <div class="mt-3 space-y-2">
                                @foreach($productReadinessInsights['margin_rows'] ?? [] as $marginRow)
                                    @php
                                        $marginTone = $funnelToneClasses[$marginRow['tone'] ?? 'slate'] ?? $funnelToneClasses['slate'];
                                    @endphp
                                    <div>
                                        <div class="flex items-center justify-between gap-2 text-xs">
                                            <span class="font-medium text-slate-600">{{ $marginRow['label'] }}</span>
                                            <span class="font-mono text-slate-500">{{ $formatCount($marginRow['count'] ?? 0) }} · {{ $formatPercent($marginRow['percent'] ?? 0) }}</span>
                                        </div>
                                        <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-100">
                                            <div class="h-full rounded-full {{ $marginTone['bar'] }}" style="width: {{ min(100, max(0, $marginRow['percent'] ?? 0)) }}%"></div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="rounded-[8px] border border-slate-200 bg-white p-3">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">Maliyet bileşimi</p>
                                    <p class="mt-1 text-xs text-slate-500">COGS, ambalaj ve komisyon yükü.</p>
                                </div>
                                @if(!empty($productReadinessInsights['top_revenue_product']))
                                    <span class="max-w-[160px] truncate rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-mono text-slate-500">
                                        {{ $formatCompactMoney($productReadinessInsights['top_revenue_product']['gross_revenue'] ?? 0) }} lider
                                    </span>
                                @endif
                            </div>
                            <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100">
                                <div class="flex h-full w-full">
                                    @foreach($productReadinessInsights['cost_composition_rows'] ?? [] as $costRow)
                                        @php
                                            $costTone = $funnelToneClasses[$costRow['tone'] ?? 'slate'] ?? $funnelToneClasses['slate'];
                                        @endphp
                                        <div class="{{ $costTone['bar'] }}" style="width: {{ min(100, max(0, $costRow['percent'] ?? 0)) }}%"></div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-3">
                                @foreach($productReadinessInsights['cost_composition_rows'] ?? [] as $costRow)
                                    @php
                                        $costTone = $funnelToneClasses[$costRow['tone'] ?? 'slate'] ?? $funnelToneClasses['slate'];
                                    @endphp
                                    <div class="min-w-0 rounded-[6px] border border-slate-200 bg-slate-50/70 px-3 py-2">
                                        <p class="truncate text-xs text-slate-500">{{ $costRow['label'] }}</p>
                                        <p class="mt-1 truncate text-sm font-bold {{ $costTone['text'] }}" title="{{ $formatMoney($costRow['value'] ?? 0) }}">{{ $formatCompactMoney($costRow['value'] ?? 0) }}</p>
                                        <p class="mt-1 font-mono text-xs text-slate-400">{{ $formatPercent($costRow['percent'] ?? 0) }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                        <p class="text-xs font-mono text-slate-500">Hazırlık oranı</p>
                        <p class="mt-2 text-2xl font-bold text-slate-900">{{ $formatPercent($costReadiness['ready_percent']) }}</p>
                        <div class="mt-3 h-2 overflow-hidden rounded-full bg-white">
                            <div class="h-full rounded-full bg-slate-900" style="width: {{ min(100, $costReadiness['ready_percent']) }}%"></div>
                        </div>
                    </div>
                    <div class="rounded-[8px] border border-amber-200 bg-amber-50 p-4">
                        <p class="text-xs font-mono text-amber-700">Eksik maliyet satırı</p>
                        <p class="mt-2 text-2xl font-bold text-amber-700">{{ $formatCount($costReadiness['missing_cost_lines']) }}</p>
                        <p class="mt-2 text-sm text-amber-800">COGS veya ambalaj maliyeti bekliyor.</p>
                    </div>
                    <div class="rounded-[8px] border border-rose-200 bg-rose-50 p-4">
                        <p class="text-xs font-mono text-rose-700">Eşleşmeyen satır</p>
                        <p class="mt-2 text-2xl font-bold text-rose-700">{{ $formatCount($costReadiness['unmatched_lines']) }}</p>
                        <p class="mt-2 text-sm text-rose-800">Ürün kartına bağlanmamış satırlar.</p>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-3">
                    <a href="{{ $this->productsUrl(['filterCostDefined' => 'no', 'sortField' => 'cogs', 'sortDirection' => 'asc']) }}"
                       class="rounded-[8px] border border-amber-200 bg-amber-50 px-3 py-2 text-center text-sm font-medium text-amber-800 transition hover:bg-amber-100">
                        Maliyet eksiklerini aç
                    </a>
                    <a href="{{ $this->matchingUrl(['statusFilter' => 'pending']) }}"
                       class="rounded-[8px] border border-rose-200 bg-rose-50 px-3 py-2 text-center text-sm font-medium text-rose-800 transition hover:bg-rose-100">
                        Eşleşme merkezini aç
                    </a>
                    <a href="{{ $this->productsUrl(['tab' => 'pricing']) }}"
                       class="rounded-[8px] border border-slate-200 bg-slate-50 px-3 py-2 text-center text-sm font-medium text-slate-700 transition hover:bg-white">
                        Fiyat/maliyet sekmesine git
                    </a>
                </div>
            </div>

            <aside class="space-y-4 lg:space-y-6 xl:col-span-4">
                <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-5">
                    <h2 class="text-lg font-bold text-slate-900">Maliyet hazırlığı</h2>
                    <p class="mt-1 text-sm text-slate-500">Kâr hesabını güvenilir yapan ürün veri kalitesi.</p>
                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                            <p class="text-xs text-slate-500">Satır</p>
                            <p class="mt-1 text-lg font-bold text-slate-900">{{ $formatCount($costReadiness['total_lines']) }}</p>
                        </div>
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                            <p class="text-xs text-slate-500">Ürün</p>
                            <p class="mt-1 text-lg font-bold text-slate-900">{{ $formatCount($costReadiness['distinct_products']) }}</p>
                        </div>
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                            <p class="text-xs text-slate-500">Eşleşen</p>
                            <p class="mt-1 text-lg font-bold text-emerald-700">{{ $formatCount($costReadiness['matched_lines']) }}</p>
                        </div>
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                            <p class="text-xs text-slate-500">Eksik ürün</p>
                            <p class="mt-1 text-lg font-bold text-amber-700">{{ $formatCount($costReadiness['missing_cost_products']) }}</p>
                        </div>
                    </div>
                </section>
            </aside>
        </section>
        @endif

        <section class="rounded-[10px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0">
                    <h2 class="text-lg font-bold text-slate-900">Kontrol yüzeyi</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ count($activeFilters) > 0 ? implode(' · ', $activeFilters) : 'Henüz aktif filtre yok.' }}
                    </p>
                </div>
                <button type="button"
                        wire:click="resetFilters"
                        class="w-full min-h-[44px] rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-base font-medium text-slate-700 transition hover:bg-slate-50 sm:w-auto sm:py-2 sm:text-sm">
                    Filtreleri sıfırla
                </button>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <label class="block">
                    <span class="text-xs font-medium text-slate-500">Pazaryeri</span>
                    <select wire:model.live="marketplaceFilter" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 focus:border-slate-900 focus:outline-none sm:py-2 sm:text-sm">
                        <option value="">Tümü</option>
                        @foreach($marketplaceOptions as $marketplace)
                            <option value="{{ $marketplace }}">{{ $this->humanMarketplace($marketplace) }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-xs font-medium text-slate-500">Mağaza</span>
                    <select wire:model.live="storeFilter" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 focus:border-slate-900 focus:outline-none sm:py-2 sm:text-sm">
                        <option value="">Tümü</option>
                        @foreach($storeOptions as $store)
                            <option value="{{ $store->id }}">{{ $store->store_name }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-xs font-medium text-slate-500">Firma</span>
                    <select wire:model.live="legalEntityFilter" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 focus:border-slate-900 focus:outline-none sm:py-2 sm:text-sm">
                        <option value="">Tümü</option>
                        @foreach($legalEntities as $entity)
                            <option value="{{ $entity->id }}">{{ $entity->name }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-xs font-medium text-slate-500">Başlangıç</span>
                    <input type="date" wire:model.live="dateFrom" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 focus:border-slate-900 focus:outline-none sm:py-2 sm:text-sm">
                </label>

                <label class="block">
                    <span class="text-xs font-medium text-slate-500">Bitiş</span>
                    <input type="date" wire:model.live="dateTo" class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 focus:border-slate-900 focus:outline-none sm:py-2 sm:text-sm">
                </label>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-3 lg:grid-cols-5">
            @foreach($riskSignals as $signal)
                <a href="{{ $this->riskUrl($signal) }}"
                   class="rounded-[8px] border p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md {{ $this->signalToneClass($signal['tone']) }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-xs font-mono opacity-80">{{ $signal['label'] }}</p>
                            <p class="mt-2 text-2xl font-bold">{{ $formatCount($signal['value']) }}</p>
                        </div>
                        <span class="rounded-[6px] border border-current/20 px-2 py-0.5 text-xs font-mono opacity-80">Odak</span>
                    </div>
                    <p class="mt-3 text-sm leading-5 opacity-90">{{ $signal['description'] }}</p>
                </a>
            @endforeach
        </section>

        @if($activePanel === 'finance')
        <div class="grid grid-cols-1 gap-4 lg:gap-6 xl:grid-cols-12">
            <section class="xl:col-span-8 rounded-[10px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">Pazaryeri kırılımı</h2>
                        <p class="mt-1 text-sm text-slate-500">Ciro, kâr, kesinti ve risk baskısını kanal bazında karşılaştırın.</p>
                    </div>
                    <span class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-2 py-0.5 text-xs font-mono text-slate-500">
                        {{ $formatCount(count($marketplaceBreakdown)) }} kanal
                    </span>
                </div>

                <div class="mt-4 space-y-3">
                    @forelse($marketplaceBreakdown as $row)
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <p class="font-semibold text-slate-900">{{ $this->humanMarketplace($row['marketplace']) }}</p>
                                    <p class="mt-1 truncate text-sm text-slate-500" title="{{ $formatCount($row['order_count']) }} sipariş · {{ $formatMoney($row['gross_revenue']) }} ciro">
                                        {{ $formatCount($row['order_count']) }} sipariş · {{ $formatCompactMoney($row['gross_revenue']) }} ciro
                                    </p>
                                </div>
                                <div class="grid grid-cols-2 gap-2 sm:min-w-[360px]">
                                    <div class="min-w-0 rounded-[6px] border border-slate-200 bg-white px-3 py-2">
                                        <p class="text-xs text-slate-500">Kâr</p>
                                        <p class="mt-1 truncate font-semibold {{ $row['profit_value'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}" title="{{ $formatMoney($row['profit_value']) }}">{{ $formatCompactMoney($row['profit_value']) }}</p>
                                    </div>
                                    <div class="min-w-0 rounded-[6px] border border-slate-200 bg-white px-3 py-2">
                                        <p class="text-xs text-slate-500">Marj</p>
                                        <p class="mt-1 font-semibold text-slate-900">{{ $formatPercent($row['profit_margin_percent']) }}</p>
                                    </div>
                                    <div class="min-w-0 rounded-[6px] border border-slate-200 bg-white px-3 py-2">
                                        <p class="text-xs text-slate-500">Kesinti</p>
                                        <p class="mt-1 truncate font-semibold text-amber-700" title="{{ $formatMoney($row['total_deductions']) }}">{{ $formatCompactMoney($row['total_deductions']) }}</p>
                                    </div>
                                    <div class="min-w-0 rounded-[6px] border border-slate-200 bg-white px-3 py-2">
                                        <p class="text-xs text-slate-500">Risk</p>
                                        <p class="mt-1 font-semibold text-slate-900">{{ $formatCount($row['loss_order_count'] + $row['material_variance_order_count']) }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-[8px] border border-dashed border-slate-300 bg-slate-50/60 p-5 text-sm text-slate-500">
                            Seçili filtrelerde pazaryeri kâr verisi bulunamadı.
                        </div>
                    @endforelse
                </div>
            </section>

            <aside class="xl:col-span-4 space-y-4 lg:space-y-6">
                <section class="rounded-[10px] border border-slate-200 bg-white p-4 lg:p-5 shadow-sm">
                    <h2 class="text-lg font-bold text-slate-900">Maliyet hazırlığı</h2>
                    <p class="mt-1 text-sm text-slate-500">Ürün eşleşmesi ve maliyet alanlarının kâr hesabına hazır olma oranı.</p>

                    <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full bg-slate-900" style="width: {{ min(100, $costReadiness['ready_percent']) }}%"></div>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                            <p class="text-xs text-slate-500">Satır</p>
                            <p class="mt-1 text-lg font-bold text-slate-900">{{ $formatCount($costReadiness['total_lines']) }}</p>
                        </div>
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                            <p class="text-xs text-slate-500">Eşleşmeyen</p>
                            <p class="mt-1 text-lg font-bold text-amber-700">{{ $formatCount($costReadiness['unmatched_lines']) }}</p>
                        </div>
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                            <p class="text-xs text-slate-500">Eksik maliyet</p>
                            <p class="mt-1 text-lg font-bold text-amber-700">{{ $formatCount($costReadiness['missing_cost_lines']) }}</p>
                        </div>
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                            <p class="text-xs text-slate-500">Ürün</p>
                            <p class="mt-1 text-lg font-bold text-slate-900">{{ $formatCount($costReadiness['distinct_products']) }}</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-[10px] border border-slate-200 bg-white p-4 lg:p-5 shadow-sm">
                    <h2 class="text-lg font-bold text-slate-900">Mağaza odağı</h2>
                    <div class="mt-4 space-y-3">
                        @forelse($storeBreakdown as $store)
                            <a href="{{ $this->financeUrl(['storeFilter' => $store['store_id']]) }}"
                               class="block rounded-[8px] border border-slate-200 bg-slate-50/60 p-3 transition hover:bg-white">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-900">{{ $store['store_name'] }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ $this->humanMarketplace($store['marketplace']) }} · {{ $formatCount($store['order_count']) }} sipariş</p>
                                    </div>
                                    <span class="shrink-0 rounded-[6px] border px-2 py-0.5 text-xs font-mono {{ $store['loss_order_count'] > 0 ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700' }}">
                                        {{ $formatCount($store['loss_order_count']) }} risk
                                    </span>
                                </div>
                                <div class="mt-3 flex items-center justify-between gap-3 text-sm">
                                    <span class="text-slate-500">Kâr</span>
                                    <span class="font-semibold {{ $store['profit_value'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $formatMoney($store['profit_value']) }}</span>
                                </div>
                            </a>
                        @empty
                            <p class="rounded-[8px] border border-dashed border-slate-300 bg-slate-50/60 p-4 text-sm text-slate-500">Mağaza kırılımı yok.</p>
                        @endforelse
                    </div>
                </section>
            </aside>
        </div>

        <section class="rounded-[10px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Zarar baskısı en yüksek siparişler</h2>
                    <p class="mt-1 text-sm text-slate-500">Önce bu siparişlerde maliyet, kesinti, finans ve iade etkisini kontrol edin.</p>
                </div>
                <a href="{{ $this->financeUrl(['sortField' => 'profit_value_metric', 'sortDirection' => 'asc']) }}"
                   class="w-full min-h-[44px] rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-center text-base font-medium text-slate-700 transition hover:bg-slate-50 sm:w-auto sm:py-2 sm:text-sm">
                    Finans listesinde aç
                </a>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-2">
                @forelse($topLossOrders as $order)
                    <a href="{{ $this->financeOrderUrl($order) }}"
                       class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-4 transition hover:bg-white hover:shadow-sm">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-900">{{ $order['order_number'] }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $this->humanMarketplace($order['marketplace']) }} · {{ $order['store_name'] }}</p>
                            </div>
                            <span class="shrink-0 rounded-[6px] border px-2 py-0.5 text-xs font-mono {{ $this->badgeToneClass($this->reconciliationStateTone($order['reconciliation_state'])) }}">
                                {{ $this->reconciliationStateLabel($order['reconciliation_state']) }}
                            </span>
                        </div>
                        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <div>
                                <p class="text-xs text-slate-500">Ciro</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900">{{ $formatMoney($order['gross_revenue']) }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-500">Kâr</p>
                                <p class="mt-1 text-sm font-semibold text-rose-700">{{ $formatMoney($order['profit_value']) }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-500">Marj</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900">{{ $formatPercent($order['profit_margin_percent']) }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-500">Kesinti</p>
                                <p class="mt-1 text-sm font-semibold text-amber-700">{{ $formatMoney($order['deduction_total']) }}</p>
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="rounded-[8px] border border-emerald-200 bg-emerald-50 p-5 text-sm text-emerald-800 lg:col-span-2">
                        Seçili aralıkta zararda görünen sipariş yok.
                    </div>
                @endforelse
            </div>
        </section>
        @elseif($activePanel === 'orders')
        <section class="rounded-[10px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Sipariş karar kuyruğu</h2>
                    <p class="mt-1 text-sm text-slate-500">En yüksek risk skoruna sahip siparişleri önce kontrol edin.</p>
                </div>
                <a href="{{ $this->financeUrl(['sortField' => 'profit_value_metric', 'sortDirection' => 'asc']) }}"
                   class="w-full min-h-[44px] rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-center text-base font-medium text-slate-700 transition hover:bg-slate-50 sm:w-auto sm:py-2 sm:text-sm">
                    Finans kuyruğunda aç
                </a>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-2">
                @forelse($orderDecisionQueue as $order)
                    <a href="{{ $this->financeOrderUrl($order) }}"
                       class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-4 transition hover:bg-white hover:shadow-sm">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-900">{{ $order['order_number'] }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $this->humanMarketplace($order['marketplace']) }} · {{ $order['store_name'] }}</p>
                            </div>
                            <span class="shrink-0 rounded-[6px] border border-rose-200 bg-rose-50 px-2 py-0.5 text-xs font-mono text-rose-700">
                                Skor {{ $formatCount($order['risk_score']) }}
                            </span>
                        </div>

                        <div class="mt-3">
                            <div class="flex items-center justify-between gap-3 text-xs">
                                <span class="font-medium text-slate-600">{{ $order['action_hint'] }}</span>
                                <span class="font-mono text-slate-400">{{ $order['primary_reason'] }}</span>
                            </div>
                            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-white">
                                <div class="h-full rounded-full {{ $order['risk_tone'] === 'critical' ? 'bg-rose-500' : ($order['risk_tone'] === 'high' ? 'bg-amber-500' : 'bg-slate-400') }}" style="width: {{ min(100, max(6, $order['risk_score'])) }}%"></div>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            @foreach($order['reasons'] as $reason)
                                <span class="rounded-[6px] border border-slate-200 bg-white px-2 py-1 text-xs text-slate-600">{{ $reason }}</span>
                            @endforeach
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <div class="min-w-0">
                                <p class="text-xs text-slate-500">Ciro</p>
                                <p class="mt-1 truncate text-sm font-semibold text-slate-900" title="{{ $formatMoney($order['gross_revenue']) }}">{{ $formatCompactMoney($order['gross_revenue']) }}</p>
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs text-slate-500">Kâr</p>
                                <p class="mt-1 truncate text-sm font-semibold {{ $order['profit_value'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}" title="{{ $formatMoney($order['profit_value']) }}">{{ $formatCompactMoney($order['profit_value']) }}</p>
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs text-slate-500">Kesinti</p>
                                <p class="mt-1 truncate text-sm font-semibold text-amber-700" title="{{ $formatMoney($order['deduction_total']) }}">{{ $formatCompactMoney($order['deduction_total']) }}</p>
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs text-slate-500">Fark</p>
                                <p class="mt-1 truncate text-sm font-semibold text-slate-900" title="{{ $formatMoney($order['reconciliation_delta_abs']) }}">{{ $formatCompactMoney($order['reconciliation_delta_abs']) }}</p>
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="rounded-[8px] border border-emerald-200 bg-emerald-50 p-5 text-sm text-emerald-800 lg:col-span-2">
                        Seçili aralıkta aksiyon gerektiren sipariş bulunamadı.
                    </div>
                @endforelse
            </div>
        </section>
        @elseif($activePanel === 'products')
        @include('livewire.partials.marketplace-capital-optimization')

        <section class="rounded-[10px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Ürün performans listesi</h2>
                    <p class="mt-1 text-sm text-slate-500">Ciro, tahmini kâr, komisyon ve maliyet eksiklerini ürün bazında takip edin.</p>
                </div>
                <span class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-2 py-0.5 text-xs font-mono text-slate-500">
                    {{ $formatCount(count($productProfitability)) }} ürün
                </span>
            </div>

            <div class="mt-4 space-y-3">
                @forelse($productProfitability as $product)
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-4">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="truncate font-semibold text-slate-900">{{ $product['product_name'] }}</p>
                                    <span class="rounded-[6px] border px-2 py-0.5 text-xs font-mono {{ $product['risk_count'] > 0 ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700' }}">
                                        {{ $product['readiness_label'] }}
                                    </span>
                                    <span class="rounded-[6px] border border-slate-200 bg-white px-2 py-0.5 text-xs font-mono text-slate-500">
                                        {{ $product['decision_hint'] }}
                                    </span>
                                </div>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{ $product['stock_code'] !== '' ? $product['stock_code'] : 'Stok kodu yok' }} · {{ $formatCount($product['order_count']) }} sipariş · {{ $formatCount($product['quantity']) }} adet
                                </p>
                                <div class="mt-3 max-w-md">
                                    <div class="flex items-center justify-between gap-3 text-xs">
                                        <span class="text-slate-500">Hazırlık skoru</span>
                                        <span class="font-mono font-semibold text-slate-700">{{ $formatPercent($product['readiness_score']) }}</span>
                                    </div>
                                    <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-white">
                                        <div class="h-full rounded-full {{ $product['readiness_score'] >= 80 ? 'bg-emerald-500' : ($product['readiness_score'] >= 50 ? 'bg-amber-500' : 'bg-rose-500') }}" style="width: {{ min(100, max(0, $product['readiness_score'])) }}%"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:min-w-[520px]">
                                <div class="min-w-0 rounded-[6px] border border-slate-200 bg-white px-3 py-2">
                                    <p class="text-xs text-slate-500">Ciro</p>
                                    <p class="mt-1 truncate font-semibold text-slate-900" title="{{ $formatMoney($product['gross_revenue']) }}">{{ $formatCompactMoney($product['gross_revenue']) }}</p>
                                </div>
                                <div class="min-w-0 rounded-[6px] border border-slate-200 bg-white px-3 py-2">
                                    <p class="text-xs text-slate-500">Tahmini kâr</p>
                                    <p class="mt-1 truncate font-semibold {{ $product['profit_value'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}" title="{{ $formatMoney($product['profit_value']) }}">{{ $formatCompactMoney($product['profit_value']) }}</p>
                                </div>
                                <div class="min-w-0 rounded-[6px] border border-slate-200 bg-white px-3 py-2">
                                    <p class="text-xs text-slate-500">Marj</p>
                                    <p class="mt-1 font-semibold text-slate-900">{{ $formatPercent($product['profit_margin_percent']) }}</p>
                                </div>
                                <div class="min-w-0 rounded-[6px] border border-slate-200 bg-white px-3 py-2">
                                    <p class="text-xs text-slate-500">Eksik</p>
                                    <p class="mt-1 font-semibold text-amber-700">{{ $formatCount($product['risk_count']) }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-1 gap-2 text-sm text-slate-600 sm:grid-cols-3">
                            <div class="min-w-0 rounded-[6px] border border-slate-200 bg-white px-3 py-2">Maliyet: <span class="font-semibold text-slate-900" title="{{ $formatMoney($product['cogs_cost'] + $product['packaging_cost']) }}">{{ $formatCompactMoney($product['cogs_cost'] + $product['packaging_cost']) }}</span></div>
                            <div class="min-w-0 rounded-[6px] border border-slate-200 bg-white px-3 py-2">Komisyon: <span class="font-semibold text-amber-700" title="{{ $formatMoney($product['estimated_commission']) }}">{{ $formatCompactMoney($product['estimated_commission']) }}</span></div>
                            <div class="rounded-[6px] border border-slate-200 bg-white px-3 py-2">Eşleşmeyen: <span class="font-semibold text-rose-700">{{ $formatCount($product['unmatched_lines']) }}</span></div>
                        </div>

                        <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-end">
                            <a href="{{ $this->productFocusUrl($product) }}"
                               class="min-h-[40px] rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-center text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                {{ $product['product_id'] > 0 ? 'Ürünü düzenle' : 'Eşleşme aç' }}
                            </a>
                            @if($product['missing_cost_lines'] > 0 && $product['product_id'] > 0)
                                <a href="{{ $this->productsUrl(['search' => $product['stock_code'] ?: $product['barcode'] ?: $product['product_name'], 'edit' => $product['product_id'], 'tab' => 'pricing']) }}"
                                   class="min-h-[40px] rounded-[6px] border border-amber-200 bg-amber-50 px-3 py-2 text-center text-sm font-medium text-amber-800 transition hover:bg-amber-100">
                                    Maliyet tamamla
                                </a>
                            @endif
                            @if($product['unmatched_lines'] > 0)
                                <a href="{{ $this->matchingUrl(['statusFilter' => 'pending']) }}"
                                   class="min-h-[40px] rounded-[6px] border border-rose-200 bg-rose-50 px-3 py-2 text-center text-sm font-medium text-rose-800 transition hover:bg-rose-100">
                                    Eşleşmeleri kontrol et
                                </a>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="rounded-[8px] border border-dashed border-slate-300 bg-slate-50/60 p-5 text-sm text-slate-500">
                        Seçili filtrelerde ürün kârlılık verisi bulunamadı.
                    </div>
                @endforelse
            </div>
        </section>
        @endif
    @endif
</div>
