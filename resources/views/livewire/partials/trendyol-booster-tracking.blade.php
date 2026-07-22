@php
    $tracking = $this->trackingDashboard;
    $trackingProducts = collect($tracking['products'] ?? []);
    $trackingColumns = $this->trackingColumnDefinitions();
    $schedulerState = $this->trackingSchedulerState;
    $retentionState = $this->trackingRetentionState;
    $operationalAlert = $this->trackingOperationalAlertState;
    $priorityActionState = $this->trackingPriorityActionState;
    $actionCenter = $this->trackingActionCenter;
    $companionHealth = $this->companionHealth;
    $calibration = $this->forecastCalibration;
    $collectionDashboard = $this->collectionDashboard;
    $boosterCollections = collect($collectionDashboard['collections'] ?? []);
    $selectedCollection = $collectionDashboard['selected'] ?? null;
    $calibrationCategories = collect($calibration['categories'] ?? [])->take(4);
    $priorityActions = collect($priorityActionState['actions'] ?? []);
    $actionCenterItems = collect($actionCenter['items'] ?? []);
    $actionAudit = collect($actionCenter['audit'] ?? []);
    $boosterTeamOptions = $this->boosterTeamOptions;
    $syncHealthAreas = collect($schedulerState['areas'] ?? []);
    $schedulerTone = $schedulerState['tone'] ?? (($schedulerState['healthy'] ?? false) ? 'emerald' : 'amber');
    $retentionTone = $retentionState['tone'] ?? (($retentionState['healthy'] ?? false) ? 'emerald' : 'amber');
    $operationalTone = $operationalAlert['tone'] ?? 'slate';
    $operationalPrimaryIssue = $operationalAlert['primary_issue'] ?? null;
    $schedulerToneClasses = [
        'emerald' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
        'sky' => 'border-sky-200 bg-sky-50 text-sky-700',
        'amber' => 'border-amber-200 bg-amber-50 text-amber-700',
        'slate' => 'border-slate-200 bg-slate-50 text-slate-600',
    ][$schedulerTone] ?? 'border-slate-200 bg-slate-50 text-slate-600';
    $retentionToneClasses = [
        'emerald' => 'border-emerald-200 bg-emerald-50/70 text-emerald-700',
        'amber' => 'border-amber-200 bg-amber-50/80 text-amber-700',
        'slate' => 'border-slate-200 bg-white text-slate-600',
    ][$retentionTone] ?? 'border-slate-200 bg-white text-slate-600';
    $operationalToneClasses = [
        'emerald' => 'border-emerald-200 bg-emerald-50/70 text-emerald-700',
        'amber' => 'border-amber-200 bg-amber-50/80 text-amber-700',
        'rose' => 'border-rose-200 bg-rose-50/80 text-rose-700',
        'sky' => 'border-sky-200 bg-sky-50/70 text-sky-700',
        'slate' => 'border-slate-200 bg-white text-slate-600',
    ][$operationalTone] ?? 'border-slate-200 bg-white text-slate-600';
    $companionToneClasses = [
        'emerald' => 'border-emerald-200 bg-emerald-50/70 text-emerald-700',
        'rose' => 'border-rose-200 bg-rose-50/70 text-rose-700',
        'sky' => 'border-sky-200 bg-sky-50/70 text-sky-700',
        'amber' => 'border-amber-200 bg-amber-50/70 text-amber-700',
    ][$companionHealth['tone'] ?? 'sky'] ?? 'border-slate-200 bg-white text-slate-600';
    $schedulerDotClasses = [
        'emerald' => 'bg-emerald-500',
        'sky' => 'bg-sky-500',
        'amber' => 'bg-amber-500',
        'slate' => 'bg-slate-400',
    ][$schedulerTone] ?? 'bg-slate-400';
    $retentionDotClasses = [
        'emerald' => 'bg-emerald-500',
        'amber' => 'bg-amber-500',
        'slate' => 'bg-slate-400',
    ][$retentionTone] ?? 'bg-slate-400';
    $operationalDotClasses = [
        'emerald' => 'bg-emerald-500',
        'amber' => 'bg-amber-500',
        'rose' => 'bg-rose-500',
        'sky' => 'bg-sky-500',
        'slate' => 'bg-slate-400',
    ][$operationalTone] ?? 'bg-slate-400';
    $syncAreaToneClasses = [
        'emerald' => 'border-emerald-200 bg-emerald-50/70 text-emerald-700',
        'sky' => 'border-sky-200 bg-sky-50/70 text-sky-700',
        'amber' => 'border-amber-200 bg-amber-50/80 text-amber-700',
        'slate' => 'border-slate-200 bg-white text-slate-600',
    ];
    $priorityToneClasses = [
        'rose' => 'border-rose-200 bg-rose-50 text-rose-700',
        'amber' => 'border-amber-200 bg-amber-50 text-amber-700',
        'sky' => 'border-sky-200 bg-sky-50 text-sky-700',
        'emerald' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
        'slate' => 'border-slate-200 bg-slate-50 text-slate-600',
    ];
    $priorityIcons = [
        'loss' => 'alert-triangle',
        'high_risk' => 'alert-circle',
        'critical_stock' => 'package',
        'first_analysis' => 'refresh-cw',
        'low_quality' => 'refresh-cw',
    ];
    $retentionTopCandidate = $retentionState['top_candidate'] ?? null;
    $lastRunAt = $schedulerState['last_run_at'] ?? null;
    $lastRunLabel = $lastRunAt
        ? $lastRunAt->timezone('Europe/Istanbul')->format('d.m H:i')
        : 'Henüz yok';
    $salesPresentation = static function ($tracked, $snapshot): array {
        $estimate = (array) data_get($snapshot?->metrics_json, 'sales_estimate', []);
        $velocity = (array) data_get($snapshot?->metrics_json, 'stock_velocity_24h', []);
        $engagement = (array) data_get($snapshot?->metrics_json, 'engagement_velocity_24h', []);
        $dailySales = $tracked->estimated_daily_sales;
        $method = (string) ($estimate['method'] ?? data_get($snapshot?->metrics_json, 'sales_method', ''));
        $status = (string) ($estimate['status'] ?? match (true) {
            $dailySales !== null && (float) $dailySales > 0 && in_array($method, ['engagement_proxy', 'engagement_velocity_24h'], true) => 'proxy',
            $dailySales !== null && (float) $dailySales > 0 => 'observed',
            $dailySales !== null => 'no_movement',
            $snapshot && $snapshot->stock_quantity === null => 'unavailable',
            default => 'warming_up',
        });
        $confidence = (int) ($estimate['confidence'] ?? 0);
        $low = $estimate['low'] ?? null;
        $high = $estimate['high'] ?? null;
        $observedHours = in_array($status, ['proxy'], true)
            ? ($engagement['observed_hours'] ?? null)
            : ($velocity['observed_hours'] ?? null);
        $coverage = in_array($status, ['proxy'], true)
            ? (int) ($engagement['coverage_percent'] ?? 0)
            : (int) ($velocity['coverage_percent'] ?? 0);

        $range = is_numeric($low) && is_numeric($high)
            ? ((float) $dailySales > 0
                ? 'Bant '.number_format((float) $low, 2, ',', '.').'–'.number_format((float) $high, 2, ',', '.').'/gün'
                : 'Algılama üst sınırı ≤'.number_format((float) $high, 2, ',', '.').'/gün')
            : null;

        return match ($status) {
            'observed', 'observed_with_restock' => [
                'status' => $status,
                'label' => '~'.number_format((float) $dailySales, 2, ',', '.').'/gün',
                'detail' => is_numeric($observedHours) ? number_format((float) $observedHours, 1, ',', '.').'/24 saat stok ölçümü' : 'Stok erimesinden hesaplandı',
                'range' => $range,
                'confidence' => $confidence,
                'coverage' => $coverage,
                'badge' => $status === 'observed_with_restock' ? 'Stok + ikmal' : 'Stok ölçümü',
                'badgeClass' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                'tone' => 'text-emerald-700',
            ],
            'proxy' => [
                'status' => $status,
                'label' => '~'.number_format((float) $dailySales, 2, ',', '.').'/gün',
                'detail' => is_numeric($observedHours) ? number_format((float) $observedHours, 1, ',', '.').'/24 saat ilgi hareketi' : 'İlgi hareketinden hesaplandı',
                'range' => $range,
                'confidence' => $confidence,
                'coverage' => $coverage,
                'badge' => 'İlgi proxy',
                'badgeClass' => 'border-sky-200 bg-sky-50 text-sky-700',
                'tone' => 'text-sky-700',
            ],
            'no_movement', 'no_movement_after_restock' => [
                'status' => $status,
                'label' => '0 gözlenen satış',
                'detail' => is_numeric($observedHours) ? number_format((float) $observedHours, 1, ',', '.').'/24 saat stok gözlemi' : 'Stok düşüşü gözlenmedi',
                'range' => $range,
                'confidence' => $confidence,
                'coverage' => $coverage,
                'badge' => 'Hareket yok',
                'badgeClass' => 'border-slate-200 bg-slate-50 text-slate-600',
                'tone' => 'text-slate-700',
            ],
            'unavailable' => [
                'status' => $status,
                'label' => 'Tahmin üretilemedi',
                'detail' => 'Stok yok · ölçülebilir ilgi hareketi yok',
                'range' => null,
                'confidence' => $confidence,
                'coverage' => $coverage,
                'badge' => 'Kaynak yok',
                'badgeClass' => 'border-rose-200 bg-rose-50 text-rose-700',
                'tone' => 'text-rose-700',
            ],
            default => [
                'status' => 'warming_up',
                'label' => 'Model ısınıyor',
                'detail' => 'İlk karşılaştırılabilir ölçümler bekleniyor',
                'range' => null,
                'confidence' => $confidence,
                'coverage' => $coverage,
                'badge' => 'Veri birikiyor',
                'badgeClass' => 'border-amber-200 bg-amber-50 text-amber-700',
                'tone' => 'text-amber-700',
            ],
        };
    };
    $radarStates = $trackingProducts->map(fn ($tracked) => $salesPresentation($tracked, $tracked->latestSnapshot));
    $radarSummary = [
        'observed' => $radarStates->whereIn('status', ['observed', 'observed_with_restock'])->count(),
        'proxy' => $radarStates->where('status', 'proxy')->count(),
        'no_movement' => $radarStates->whereIn('status', ['no_movement', 'no_movement_after_restock'])->count(),
        'waiting' => $radarStates->whereIn('status', ['warming_up', 'unavailable'])->count(),
    ];
    $calibrationTone = [
        'calibrated' => 'border-emerald-200 bg-emerald-50/70 text-emerald-700',
        'watch' => 'border-sky-200 bg-sky-50/70 text-sky-700',
        'needs_tuning' => 'border-rose-200 bg-rose-50/70 text-rose-700',
        'warming_up' => 'border-amber-200 bg-amber-50/70 text-amber-700',
    ][$calibration['status'] ?? 'warming_up'] ?? 'border-slate-200 bg-slate-50 text-slate-600';
@endphp

<div class="space-y-4 lg:space-y-6">
    <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 p-4 lg:p-6">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase text-slate-500">Booster ledger</p>
                    <h2 class="mt-1 text-lg font-semibold text-slate-900">Takipteki ürünler</h2>
                    <p class="mt-1 text-sm text-slate-500">Stok erimesi birincil sinyal; stok yoksa 24 saatlik doğrulanabilir ilgi hareketi kontrollü proxy olarak kullanılır.</p>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <span class="inline-flex min-h-[40px] items-center justify-center gap-2 rounded-[6px] border px-3 py-2 text-xs font-semibold {{ $schedulerToneClasses }}">
                        <span class="h-2 w-2 rounded-full {{ $schedulerDotClasses }}"></span>
                        {{ $schedulerState['label'] }} · {{ (int) ($schedulerState['due_total'] ?? 0) }} iş bekliyor
                    </span>
                    <div x-data="{ open: false }" class="relative">
                        <button type="button" x-on:click="open = !open" class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 lg:w-auto">
                            <x-lucide.icon name="columns-3" class="h-4 w-4" /> Kolonlar · {{ count($trackingVisibleColumns) }}
                        </button>
                        <div x-cloak x-show="open" x-on:click.outside="open = false" class="absolute right-0 z-40 mt-2 w-56 rounded-[8px] border border-slate-200 bg-white p-2 shadow-xl">
                            @foreach($trackingColumns as $key => $label)
                                <label class="flex min-h-10 items-center gap-2 rounded-[6px] px-2 text-sm text-slate-700 hover:bg-slate-50">
                                    <input type="checkbox" wire:click="toggleTrackingColumn('{{ $key }}')" @checked(in_array($key, $trackingVisibleColumns, true)) @disabled($key === 'product') class="rounded border-slate-300 text-slate-900">
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50/60 p-3">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase text-slate-500">Otomatik takip sağlığı</p>
                        <p class="mt-0.5 text-sm text-slate-600">Son scheduler: <span class="font-semibold text-slate-900">{{ $lastRunLabel }}</span> · {{ (int) ($schedulerState['tracked_total'] ?? 0) }} aktif takip alanı</p>
                    </div>
                    <div class="inline-flex min-h-[36px] items-center gap-2 rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600">
                        <x-lucide.icon name="activity" class="h-4 w-4" />
                        {{ (int) ($schedulerState['never_checked_total'] ?? 0) }} ilk tarama bekliyor
                    </div>
                </div>
                <div class="mt-3 rounded-[8px] border p-3 {{ $operationalToneClasses }}" data-testid="booster-operational-alert">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="h-2 w-2 shrink-0 rounded-full {{ $operationalDotClasses }}"></span>
                                <p class="truncate text-xs font-semibold uppercase">Operasyon alarmı</p>
                            </div>
                            <p class="mt-1 text-sm font-semibold">{{ $operationalAlert['label'] }}</p>
                            <p class="mt-0.5 text-xs opacity-80">{{ $operationalAlert['summary'] }}</p>
                        </div>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:min-w-[300px]">
                            <div class="rounded-[6px] bg-white/75 px-2 py-1.5">
                                <p class="text-[10px] font-semibold uppercase opacity-70">Alarm</p>
                                <p class="mt-0.5 text-sm font-bold">{{ (int) ($operationalAlert['issue_count'] ?? 0) }}</p>
                            </div>
                            <div class="rounded-[6px] bg-white/75 px-2 py-1.5">
                                <p class="text-[10px] font-semibold uppercase opacity-70">Bekleyen</p>
                                <p class="mt-0.5 text-sm font-bold">{{ (int) data_get($operationalAlert, 'sync.due_total', 0) }}</p>
                            </div>
                            <div class="col-span-2 rounded-[6px] bg-white/75 px-2 py-1.5 sm:col-span-1">
                                <p class="text-[10px] font-semibold uppercase opacity-70">Öncelik</p>
                                <p class="mt-0.5 truncate text-xs font-bold">{{ $operationalPrimaryIssue['label'] ?? 'Normal' }}</p>
                            </div>
                        </div>
                    </div>
                    @if($operationalPrimaryIssue)
                        <div class="mt-3 flex flex-col gap-2 rounded-[6px] bg-white/70 px-3 py-2 text-xs sm:flex-row sm:items-center sm:justify-between">
                            <span class="font-medium">{{ $operationalPrimaryIssue['action'] }}</span>
                            @if($operationalPrimaryIssue['metric'] ?? null)
                                <span class="shrink-0 font-mono font-bold">{{ $operationalPrimaryIssue['metric'] }}</span>
                            @endif
                        </div>
                    @endif
                </div>
                <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-5">
                    <?php if ($syncHealthAreas->isNotEmpty()) { ?>
                    <?php foreach ($syncHealthAreas as $area) { ?>
                        <?php $areaTone = $area['tone'] ?? 'slate'; ?>
                        <div class="min-w-0 rounded-[8px] border p-3 {{ $syncAreaToneClasses[$areaTone] ?? $syncAreaToneClasses['slate'] }}">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate text-xs font-semibold">{{ $area['label'] }}</p>
                                    <p class="mt-0.5 truncate text-[11px] opacity-80">{{ $area['detail'] ?? '' }}</p>
                                </div>
                                <span class="shrink-0 rounded-[6px] bg-white/70 px-1.5 py-0.5 text-[10px] font-bold">{{ (int) ($area['due_count'] ?? 0) }}</span>
                            </div>
                            <p class="mt-2 text-xs font-semibold">{{ $area['status_label'] }}</p>
                            <p class="mt-0.5 text-[11px] opacity-75">
                                Son: {{ (($area['last_checked_at'] ?? null) ? $area['last_checked_at']->timezone('Europe/Istanbul')->format('d.m H:i') : 'yok') }}
                            </p>
                        </div>
                    <?php } ?>
                    <?php } else { ?>
                        <div class="rounded-[8px] border border-slate-200 bg-white p-3 text-xs text-slate-500">Takip sağlığı için henüz kayıt yok.</div>
                    <?php } ?>
                </div>
                <div class="mt-3 grid grid-cols-1 gap-2 lg:grid-cols-[minmax(0,1fr)_minmax(260px,360px)]">
                    <div class="min-w-0 rounded-[8px] border p-3 {{ $retentionToneClasses }}" data-testid="booster-retention-health">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="h-2 w-2 shrink-0 rounded-full {{ $retentionDotClasses }}"></span>
                                    <p class="truncate text-xs font-semibold uppercase">Geçmiş veri yükü</p>
                                </div>
                                <p class="mt-1 text-sm font-semibold">{{ $retentionState['label'] }}</p>
                                <p class="mt-0.5 text-xs opacity-80">
                                    {{ number_format((int) ($retentionState['dataset_count'] ?? 0), 0, ',', '.') }} tablo grubu · {{ number_format((int) ($retentionState['total_count'] ?? 0), 0, ',', '.') }} kayıt
                                </p>
                            </div>
                            <div class="rounded-[6px] bg-white/75 px-3 py-2 text-right">
                                <p class="text-lg font-bold">{{ number_format((int) ($retentionState['candidate_count'] ?? 0), 0, ',', '.') }}</p>
                                <p class="text-[11px] font-semibold opacity-75">temizlik adayı</p>
                            </div>
                        </div>
                        <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-white/70">
                            <div class="h-full rounded-full {{ $retentionTone === 'amber' ? 'bg-amber-500' : 'bg-emerald-500' }}" style="width: {{ min(100, max(0, (float) ($retentionState['candidate_ratio'] ?? 0))) }}%"></div>
                        </div>
                    </div>
                    <div class="min-w-0 rounded-[8px] border border-slate-200 bg-white p-3">
                        <div class="flex items-start gap-2">
                            <x-lucide.icon name="database" class="mt-0.5 h-4 w-4 shrink-0 text-slate-500" />
                            <div class="min-w-0">
                                <div class="flex min-w-0 items-center gap-2">
                                    <p class="min-w-0 flex-1 truncate text-xs font-semibold text-slate-900">{{ $retentionTopCandidate['label'] ?? 'Retention dry-run' }}</p>
                                    <span class="shrink-0 rounded-[6px] border px-1.5 py-0.5 text-[10px] font-semibold {{ ($retentionState['cleanup_enabled'] ?? false) ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-slate-200 bg-slate-50 text-slate-500' }}">
                                        Temizlik {{ ($retentionState['cleanup_enabled'] ?? false) ? 'açık' : 'kapalı' }}
                                    </span>
                                </div>
                                <p class="mt-0.5 text-[11px] text-slate-500">
                                    {{ number_format((int) ($retentionTopCandidate['candidate_count'] ?? 0), 0, ',', '.') }} aday · {{ (int) ($retentionTopCandidate['retention_days'] ?? 0) }} gün
                                </p>
                            </div>
                        </div>
                        <div class="mt-2 rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-1.5">
                            <p class="break-all font-mono text-[10px] leading-4 text-slate-600">{{ $retentionState['command'] }}</p>
                        </div>
                    </div>
                </div>
                <div class="mt-3 rounded-[8px] border p-3 {{ $companionToneClasses }}" data-testid="booster-companion-health">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <x-lucide.icon name="radio-tower" class="h-4 w-4 shrink-0" />
                                <p class="text-xs font-semibold uppercase">Companion gözlem yüzeyi · {{ strtoupper((string) ($companionHealth['release_ring'] ?? 'ga')) }}</p>
                            </div>
                            <p class="mt-1 text-sm font-semibold">{{ $companionHealth['label'] }}</p>
                            <p class="mt-0.5 text-xs opacity-80">Son {{ (int) ($companionHealth['window_minutes'] ?? 60) }} dakika; istek gövdesi, ürün verisi ve kişisel veri telemetriye yazılmaz.</p>
                        </div>
                        <div class="grid grid-cols-3 gap-2 text-center sm:min-w-[300px]">
                            <div class="rounded-[6px] bg-white/75 px-2 py-1.5"><p class="text-[10px] font-semibold uppercase opacity-70">İstek</p><p class="font-mono text-sm font-bold">{{ (int) ($companionHealth['request_count'] ?? 0) }}</p></div>
                            <div class="rounded-[6px] bg-white/75 px-2 py-1.5"><p class="text-[10px] font-semibold uppercase opacity-70">Hata</p><p class="font-mono text-sm font-bold">%{{ number_format((float) ($companionHealth['error_rate'] ?? 0), 2, ',', '.') }}</p></div>
                            <div class="rounded-[6px] bg-white/75 px-2 py-1.5"><p class="text-[10px] font-semibold uppercase opacity-70">P95</p><p class="font-mono text-sm font-bold">{{ (int) ($companionHealth['p95_duration_ms'] ?? 0) }} ms</p></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:justify-end" data-testid="booster-report-downloads">
                <button type="button" wire:click="exportBoosterExcel" wire:loading.attr="disabled" wire:target="exportBoosterExcel" class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 sm:w-auto sm:py-2"><x-lucide.icon name="sheet" class="h-4 w-4" /> Excel raporu</button>
                <button type="button" wire:click="exportBoosterPdf" wire:loading.attr="disabled" wire:target="exportBoosterPdf" class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white sm:w-auto sm:py-2"><x-lucide.icon name="file-text" class="h-4 w-4" /> PDF raporu</button>
            </div>

            <div class="mt-4 overflow-hidden rounded-lg border border-slate-200 bg-white" data-testid="booster-action-center">
                <div class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50/60 p-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <x-lucide.icon name="inbox" class="h-4 w-4 text-slate-600" />
                            <p class="text-xs font-semibold uppercase text-slate-500">Aksiyon merkezi</p>
                        </div>
                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ (int) ($actionCenter['open_count'] ?? 0) }} açık iş, {{ (int) ($actionCenter['critical_count'] ?? 0) }} kritik</p>
                        <p class="mt-0.5 text-xs text-slate-500">Operasyon ve ürün sinyalleri tek kuyrukta; kabul edin, erteleyin veya yeniden açın.</p>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-center text-xs sm:shrink-0">
                        <div class="rounded-[6px] border border-slate-200 bg-white px-2.5 py-1.5"><p class="text-slate-400">Açık</p><p class="font-mono font-bold text-slate-900">{{ (int) ($actionCenter['open_count'] ?? 0) }}</p></div>
                        <div class="rounded-[6px] border border-slate-200 bg-white px-2.5 py-1.5"><p class="text-slate-400">Erteli</p><p class="font-mono font-bold text-amber-700">{{ (int) ($actionCenter['snoozed_count'] ?? 0) }}</p></div>
                        <div class="rounded-[6px] border border-slate-200 bg-white px-2.5 py-1.5"><p class="text-slate-400">Kabul</p><p class="font-mono font-bold text-emerald-700">{{ (int) ($actionCenter['acknowledged_count'] ?? 0) }}</p></div>
                    </div>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse($actionCenterItems as $actionItem)
                        @php($actionItemTone = $priorityToneClasses[$actionItem['tone'] ?? 'slate'] ?? $priorityToneClasses['slate'])
                        <article class="flex flex-col gap-3 p-3 sm:flex-row sm:items-center" wire:key="booster-action-{{ md5($actionItem['fingerprint']) }}">
                            <div class="flex min-w-0 flex-1 items-start gap-3">
                                <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-[6px] border {{ $actionItemTone }}"><x-lucide.icon name="{{ ($actionItem['source'] ?? '') === 'product' ? 'package-search' : 'activity' }}" class="h-4 w-4" /></span>
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="text-sm font-semibold text-slate-900">{{ $actionItem['label'] }}</p>
                                        <span class="rounded-[6px] border {{ $actionItemTone }} px-1.5 py-0.5 font-mono text-[10px] font-semibold">{{ $actionItem['metric'] ?: ($actionItem['source'] === 'product' ? 'Ürün' : 'Operasyon') }}</span>
                                        <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-1.5 py-0.5 text-[10px] font-medium text-slate-500">{{ $actionItem['status_label'] }}</span>
                                    </div>
                                    <p class="mt-0.5 truncate text-xs font-medium text-slate-700">{{ $actionItem['title'] }}</p>
                                    <p class="mt-0.5 text-xs text-slate-500">{{ $actionItem['reason'] }}</p>
                                    @if(($actionItem['status'] ?? '') === 'snoozed' && $actionItem['snoozed_until'])
                                        <p class="mt-1 text-[11px] font-medium text-amber-700">{{ $actionItem['snoozed_until']->timezone('Europe/Istanbul')->format('d.m H:i') }} tarihine kadar ertelendi</p>
                                    @endif
                                    <label class="mt-2 flex max-w-xs items-center gap-2 text-[11px] text-slate-500"><span class="shrink-0">Sorumlu</span><select wire:change="assignTrackingAction(@js($actionItem['fingerprint']), $event.target.value)" class="min-h-[36px] min-w-0 flex-1 rounded-[6px] border border-slate-200 bg-white px-2 text-xs text-slate-700"><option value="">Ata</option>@foreach($boosterTeamOptions as $teamUser)<option value="{{ $teamUser->id }}" @selected((int) ($actionItem['assigned_user_id'] ?? 0) === (int) $teamUser->id)>{{ $teamUser->name ?: $teamUser->email }}</option>@endforeach</select></label>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-2 sm:flex sm:shrink-0">
                                @if(($actionItem['status'] ?? 'open') === 'open')
                                    <button type="button" wire:click="updateTrackingAction(@js($actionItem['fingerprint']), 'snoozed', 24)" class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 sm:w-auto">24 sa. ertele</button>
                                    <button type="button" wire:click="updateTrackingAction(@js($actionItem['fingerprint']), 'acknowledged')" class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] bg-slate-900 px-3 py-2 text-xs font-medium text-white sm:w-auto">Kabul et</button>
                                @else
                                    <button type="button" wire:click="updateTrackingAction(@js($actionItem['fingerprint']), 'open')" class="col-span-2 inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 sm:w-auto">Yeniden aç</button>
                                @endif
                            </div>
                        </article>
                    @empty
                        <div class="p-5 text-center text-sm text-slate-500">Açık aksiyon yok. Yeni risk oluştuğunda bu kuyruk otomatik güncellenir.</div>
                    @endforelse
                </div>
                @if($actionAudit->isNotEmpty())
                    <details class="border-t border-slate-200 bg-slate-50/60"><summary class="flex min-h-[44px] cursor-pointer items-center justify-between gap-3 px-3 py-2 text-xs font-semibold text-slate-600"><span>Denetim izi · {{ $actionAudit->count() }} son hareket</span><x-lucide.icon name="chevron-down" class="h-4 w-4" /></summary><div class="grid grid-cols-1 gap-2 border-t border-slate-200 p-3 sm:grid-cols-2 xl:grid-cols-3">@foreach($actionAudit as $audit)<div class="min-w-0 rounded-[6px] border border-slate-200 bg-white px-3 py-2"><p class="truncate text-xs font-semibold text-slate-800">{{ $audit['event_label'] }} · {{ $audit['actor'] }}</p><p class="mt-1 truncate font-mono text-[10px] text-slate-500">{{ $audit['fingerprint'] }}</p><p class="mt-1 text-[11px] text-slate-500">{{ $audit['from'] ?: '—' }} → {{ $audit['to'] ?: '—' }} · {{ $audit['occurred_at']?->timezone('Europe/Istanbul')->format('d.m H:i') }}</p></div>@endforeach</div></details>
                @endif
            </div>

            <div class="mt-4 overflow-hidden rounded-lg border border-slate-200 bg-white" data-testid="booster-decision-collections">
                <div class="border-b border-slate-200 bg-slate-50/60 p-3">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                        <div class="min-w-0"><div class="flex items-center gap-2"><x-lucide.icon name="folders" class="h-4 w-4 text-slate-600" /><p class="text-xs font-semibold uppercase text-slate-500">Karar koleksiyonları</p></div><p class="mt-1 text-sm font-semibold text-slate-900">Ürünleri araştırma listelerinde gruplayın</p><p class="mt-0.5 text-xs text-slate-500">Tedarikçi görüşmesi, numune, reklam testi veya sezon planı için kalıcı kısa listeler.</p></div>
                        <form wire:submit="createCollection" class="flex w-full gap-2 lg:w-auto"><input type="text" wire:model.defer="newCollectionName" placeholder="Yeni koleksiyon" class="min-h-[44px] min-w-0 flex-1 rounded-[6px] border border-slate-200 bg-white px-3 text-base sm:min-w-[220px] sm:text-sm"><button type="submit" class="inline-flex min-h-[44px] shrink-0 items-center justify-center rounded-[6px] bg-slate-900 px-4 py-2 text-sm font-medium text-white">Oluştur</button></form>
                    </div>
                </div>
                @if($collectionDashboard['ready'] ?? false)
                    <div class="p-3">
                        <div class="flex gap-2 overflow-x-auto pb-2">
                            @forelse($boosterCollections as $collection)
                                <button type="button" wire:click="$set('selectedCollectionId', {{ $collection->id }})" class="inline-flex min-h-[40px] shrink-0 items-center gap-2 rounded-[6px] border px-3 py-2 text-xs font-medium {{ (int) ($selectedCollection?->id ?? 0) === (int) $collection->id ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-700' }}"><span>{{ $collection->name }}</span><span class="rounded bg-white/20 px-1.5 py-0.5 font-mono">{{ $collection->products_count }}</span></button>
                            @empty
                                <p class="text-xs text-slate-500">İlk karar koleksiyonunuzu oluşturun.</p>
                            @endforelse
                        </div>
                        @if($selectedCollection)
                            <div class="mt-2 flex flex-col gap-2 sm:flex-row sm:items-end">
                                <label class="min-w-0 flex-1"><span class="text-xs text-slate-500">Koleksiyona ürün ekle / çıkar</span><select wire:model.defer="collectionProductId" class="mt-1 min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-3 text-base sm:text-sm"><option value="">Ürün seçin</option>@foreach(($collectionDashboard['products'] ?? []) as $collectionProduct)<option value="{{ $collectionProduct->id }}">{{ $collectionProduct->title ?: 'Ürün '.$collectionProduct->id }}</option>@endforeach</select></label>
                                <button type="button" wire:click="toggleCollectionProduct" class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 sm:w-auto">Üyeliği değiştir</button>
                            </div>
                            <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-3">
                                @forelse($selectedCollection->products as $collectionProduct)
                                    <article class="flex min-w-0 items-center gap-3 rounded-[8px] border border-slate-200 bg-slate-50/60 p-3"><div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-slate-900">{{ $collectionProduct->title ?: 'Trendyol ürünü' }}</p><p class="mt-0.5 truncate text-xs text-slate-500">{{ $collectionProduct->brand ?: 'Marka yok' }} · {{ $this->formatMoney($collectionProduct->sale_price) }}</p></div><button type="button" wire:click="toggleCollectionProduct({{ $collectionProduct->id }})" title="Koleksiyondan çıkar" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-[6px] border border-slate-200 bg-white text-slate-500"><x-lucide.icon name="x" class="h-4 w-4" /></button></article>
                                @empty
                                    <div class="rounded-[8px] border border-dashed border-slate-300 p-4 text-sm text-slate-500 sm:col-span-2 xl:col-span-3">Bu koleksiyonda henüz ürün yok.</div>
                                @endforelse
                            </div>
                        @endif
                    </div>
                @else
                    <div class="p-4 text-sm text-amber-700">Koleksiyon migration'ı çalıştırıldıktan sonra bu alan açılacak.</div>
                @endif
            </div>

            <div class="mt-4 rounded-lg border border-slate-200 bg-white p-3" data-testid="booster-evidence-assistant">
                <div class="flex items-start gap-3"><span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-[6px] border border-slate-200 bg-slate-50 text-slate-600"><x-lucide.icon name="sparkles" class="h-4 w-4" /></span><div class="min-w-0"><p class="text-xs font-semibold uppercase text-slate-500">Kanıt bağlı AI karar asistanı</p><p class="mt-1 text-sm font-semibold text-slate-900">Ürünlerim içinde neye öncelik vermeliyim?</p><p class="mt-0.5 text-xs text-slate-500">Yanıt yalnız sizin ZOLM ürün defterinizden üretilir ve her iddia [K1] kaynak kimliğiyle gösterilir.</p></div></div>
                <form wire:submit="askBoosterAssistant" class="mt-3 flex flex-col gap-2 sm:flex-row"><input type="text" wire:model.defer="assistantQuestion" placeholder="Örn. En düşük riskle hangi ürünü test etmeliyim?" class="min-h-[44px] min-w-0 flex-1 rounded-[6px] border border-slate-200 bg-white px-3 text-base sm:text-sm"><button type="submit" wire:loading.attr="disabled" wire:target="askBoosterAssistant" class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white disabled:opacity-60 sm:w-auto sm:py-2">Kanıtla yanıtla</button></form>
                @if(!empty($assistantResponse))
                    <div class="mt-3 rounded-[8px] border border-slate-200 bg-slate-50/60 p-3"><div class="whitespace-pre-line text-sm leading-6 text-slate-700">{{ $assistantResponse['answer'] ?? '' }}</div><div class="mt-3 flex flex-wrap gap-2">@foreach(($assistantResponse['sources'] ?? []) as $assistantSource)<button type="button" wire:click="openTrackedProductAnalysis({{ (int) $assistantSource['product_id'] }})" class="inline-flex min-h-[36px] max-w-full items-center gap-1 rounded-[6px] border border-slate-200 bg-white px-2 py-1 text-xs text-slate-600"><span class="font-mono font-semibold">[{{ $assistantSource['source_id'] }}]</span><span class="max-w-[180px] truncate">{{ $assistantSource['title'] }}</span></button>@endforeach</div><p class="mt-3 text-[11px] leading-5 text-slate-500">{{ $assistantResponse['disclaimer'] ?? '' }} · Motor: {{ $assistantResponse['provider'] ?? 'kanıt' }}</p></div>
                @endif
            </div>

            <div class="mt-4 overflow-hidden rounded-lg border border-slate-200 bg-white" data-testid="booster-priority-actions">
                <div class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50/60 p-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <x-lucide.icon name="check-square" class="h-4 w-4 shrink-0 text-slate-600" />
                            <p class="text-xs font-semibold uppercase text-slate-500">Bugün ne yapmalıyım?</p>
                        </div>
                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ $priorityActionState['label'] }}</p>
                        <p class="mt-0.5 text-xs text-slate-500">{{ $priorityActionState['summary'] }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-2 sm:flex sm:shrink-0">
                        <div class="rounded-[6px] border border-rose-200 bg-rose-50 px-2.5 py-1.5 text-rose-700">
                            <p class="text-[10px] font-semibold uppercase">Kritik</p>
                            <p class="mt-0.5 font-mono text-sm font-bold">{{ (int) ($priorityActionState['critical_count'] ?? 0) }}</p>
                        </div>
                        <div class="rounded-[6px] border border-amber-200 bg-amber-50 px-2.5 py-1.5 text-amber-700">
                            <p class="text-[10px] font-semibold uppercase">Uyarı</p>
                            <p class="mt-0.5 font-mono text-sm font-bold">{{ (int) ($priorityActionState['warning_count'] ?? 0) }}</p>
                        </div>
                    </div>
                </div>
                <div class="divide-y divide-slate-100">
                    <?php if ($priorityActions->isNotEmpty()) { ?>
                    <?php foreach ($priorityActions as $priorityAction) { ?>
                        <?php
                            $priorityTone = $priorityAction['tone'] ?? 'slate';
                        $priorityIcon = $priorityIcons[$priorityAction['key'] ?? ''] ?? 'info';
                        ?>
                        <div class="flex flex-col gap-3 p-3 sm:flex-row sm:items-center">
                            <div class="flex min-w-0 flex-1 items-start gap-3">
                                <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-[6px] border {{ $priorityToneClasses[$priorityTone] ?? $priorityToneClasses['slate'] }}">
                                    <x-lucide.icon name="{{ $priorityIcon }}" class="h-4 w-4" />
                                </span>
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="text-sm font-semibold text-slate-900">{{ $priorityAction['label'] }}</p>
                                        <span class="rounded-[6px] border px-1.5 py-0.5 font-mono text-[10px] font-semibold {{ $priorityToneClasses[$priorityTone] ?? $priorityToneClasses['slate'] }}">{{ $priorityAction['metric'] }}</span>
                                    </div>
                                    <p class="mt-0.5 truncate text-xs font-medium text-slate-700">{{ $priorityAction['title'] }}</p>
                                    <p class="mt-0.5 text-xs text-slate-500">{{ $priorityAction['reason'] }}</p>
                                </div>
                            </div>
                            <button type="button" wire:click="openTrackedProductAnalysis({{ (int) $priorityAction['product_id'] }})" wire:loading.attr="disabled" wire:target="openTrackedProductAnalysis({{ (int) $priorityAction['product_id'] }})" class="inline-flex min-h-[44px] w-full shrink-0 items-center justify-center gap-2 rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white disabled:opacity-60 sm:w-auto sm:py-2">
                                <x-lucide.icon name="search" class="h-4 w-4" />
                                {{ $priorityAction['action_label'] }}
                            </button>
                        </div>
                    <?php } ?>
                    <?php } else { ?>
                        <div class="flex items-center gap-3 p-3 text-sm text-slate-600">
                            <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-[6px] border border-emerald-200 bg-emerald-50 text-emerald-700">
                                <x-lucide.icon name="check-circle" class="h-4 w-4" />
                            </span>
                            <span>Takip akışı sakin; yeni bir ticari sinyal oluştuğunda burada görünecek.</span>
                        </div>
                    <?php } ?>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-2 lg:grid-cols-4 lg:gap-3">
                <div class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/60 p-3"><div class="flex items-center justify-between gap-2"><p class="truncate text-xs font-medium text-slate-500">Stok sinyali</p><span class="h-2 w-2 shrink-0 rounded-full bg-emerald-500"></span></div><p class="mt-1 text-xl font-semibold text-slate-900">{{ $radarSummary['observed'] }}</p><p class="mt-0.5 truncate text-[11px] text-slate-500">Doğrudan stok erimesi</p></div>
                <div class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/60 p-3"><div class="flex items-center justify-between gap-2"><p class="truncate text-xs font-medium text-slate-500">İlgi proxy</p><span class="h-2 w-2 shrink-0 rounded-full bg-sky-500"></span></div><p class="mt-1 text-xl font-semibold text-slate-900">{{ $radarSummary['proxy'] }}</p><p class="mt-0.5 truncate text-[11px] text-slate-500">Düşük güvenli tahmin</p></div>
                <div class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/60 p-3"><div class="flex items-center justify-between gap-2"><p class="truncate text-xs font-medium text-slate-500">Hareket yok</p><span class="h-2 w-2 shrink-0 rounded-full bg-slate-400"></span></div><p class="mt-1 text-xl font-semibold text-slate-900">{{ $radarSummary['no_movement'] }}</p><p class="mt-0.5 truncate text-[11px] text-slate-500">Stok düşüşü gözlenmedi</p></div>
                <div class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/60 p-3"><div class="flex items-center justify-between gap-2"><p class="truncate text-xs font-medium text-slate-500">Veri bekliyor</p><span class="h-2 w-2 shrink-0 rounded-full bg-amber-500"></span></div><p class="mt-1 text-xl font-semibold text-slate-900">{{ $radarSummary['waiting'] }}</p><p class="mt-0.5 truncate text-[11px] text-slate-500">Isınan veya eksik kaynak</p></div>
            </div>

            <div class="mt-4 overflow-hidden rounded-lg border border-slate-200 bg-white" data-testid="booster-forecast-calibration">
                <div class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50/60 p-3 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase text-slate-500">Tahmin doğruluk ve kalibrasyon merkezi</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">Tahmin edilen satış ile sonraki gerçek stok erimesini karşılaştırır</p>
                        <p class="mt-0.5 text-xs text-slate-500">{{ $calibration['evidence_note'] }}</p>
                    </div>
                    <span class="inline-flex min-h-[36px] shrink-0 items-center justify-center rounded-[6px] border px-3 py-2 text-xs font-semibold {{ $calibrationTone }}">{{ $calibration['label'] }} · {{ (int) $calibration['sample_count'] }} örnek</span>
                </div>
                <div class="grid grid-cols-1 gap-3 p-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3"><p class="text-xs text-slate-500">Ortalama yüzde hata</p><p class="mt-1 text-lg font-semibold text-slate-900">{{ $calibration['mape'] !== null ? '%'.number_format((float) $calibration['mape'], 1, ',', '.') : 'Veri bekleniyor' }}</p><p class="mt-0.5 text-[11px] text-slate-500">Yalnız sıfırdan büyük gerçek satış</p></div>
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3"><p class="text-xs text-slate-500">±%25 içinde</p><p class="mt-1 text-lg font-semibold text-slate-900">{{ $calibration['within_25_percent'] !== null ? '%'.number_format((float) $calibration['within_25_percent'], 1, ',', '.') : 'Veri bekleniyor' }}</p><p class="mt-0.5 text-[11px] text-slate-500">Doğruluk bandı</p></div>
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3"><p class="text-xs text-slate-500">Ort. adet hatası</p><p class="mt-1 text-lg font-semibold text-slate-900">{{ $calibration['mae'] !== null ? number_format((float) $calibration['mae'], 2, ',', '.').' / gün' : 'Veri bekleniyor' }}</p><p class="mt-0.5 text-[11px] text-slate-500">Mutlak hata</p></div>
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3"><p class="text-xs text-slate-500">Model eğilimi</p><p class="mt-1 truncate text-sm font-semibold text-slate-900">{{ $calibration['bias_label'] }}</p><p class="mt-0.5 text-[11px] text-slate-500">Sapma {{ $calibration['bias'] !== null ? number_format((float) $calibration['bias'], 2, ',', '.').' adet' : '-' }}</p></div>
                </div>
                @if($calibrationCategories->isNotEmpty())
                    <div class="grid grid-cols-1 gap-2 border-t border-slate-100 p-3 sm:grid-cols-2 xl:grid-cols-4">
                        @foreach($calibrationCategories as $categoryCalibration)
                            <div class="min-w-0 rounded-[6px] border border-slate-200 bg-white px-3 py-2"><p class="truncate text-xs font-medium text-slate-900">{{ $categoryCalibration['category'] }}</p><p class="mt-0.5 text-[11px] text-slate-500">{{ $categoryCalibration['sample_count'] }} örnek · Hata {{ $categoryCalibration['mape'] !== null ? '%'.number_format((float) $categoryCalibration['mape'], 1, ',', '.') : '-' }}</p></div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50/60 p-3">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(240px,1fr)_220px_180px_auto_auto] xl:items-end">
                    <label class="min-w-0">
                        <span class="text-xs font-medium text-slate-600">Ürün ara</span>
                        <input type="search" wire:model.live.debounce.300ms="analysisSearch" placeholder="Ürün, marka veya ID"
                               class="mt-1.5 min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 sm:py-2 sm:text-sm">
                    </label>
                    <label>
                        <span class="text-xs font-medium text-slate-600">Kategori</span>
                        <select wire:model.live="analysisCategory" class="mt-1.5 min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 sm:text-sm">
                            <option value="all">Tüm kategoriler</option>
                            @foreach($this->analysisCategories as $category)<option value="{{ $category }}">{{ $category }}</option>@endforeach
                        </select>
                    </label>
                    <label>
                        <span class="text-xs font-medium text-slate-600">Durum</span>
                        <select wire:model.live="trackingStatus" class="mt-1.5 min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 sm:text-sm">
                            <option value="active">Takipte</option>
                            <option value="paused">Duraklatılan</option>
                            <option value="candidate">Tek seferlik analiz</option>
                            <option value="all">Tümü</option>
                        </select>
                    </label>
                    <button type="button" wire:click="toggleFavoritesOnly" class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700"><x-lucide.icon name="heart" class="h-4 w-4 {{ $favoritesOnly ? 'fill-rose-500 text-rose-500' : '' }}" /> Favoriler</button>
                    <button type="button" wire:click="resetAnalysisFilters" class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-600">Temizle</button>
                </div>
                <p class="mt-2 text-xs text-slate-500">{{ $tracking['filtered_count'] ?? 0 }} ürün gösteriliyor · Tahmini metrikler güven puanıyla birlikte sunulur.</p>
            </div>
        </div>

        <div class="hidden overflow-x-auto md:block" x-data="{
            startResize(event, column) {
                const startX = event.clientX, startWidth = column.offsetWidth;
                const move = (moveEvent) => { column.style.width = Math.max(96, startWidth + moveEvent.clientX - startX) + 'px'; };
                const up = () => { window.removeEventListener('mousemove', move); window.removeEventListener('mouseup', up); };
                window.addEventListener('mousemove', move); window.addEventListener('mouseup', up);
            }
        }">
            <table class="w-full table-fixed text-left">
                <thead class="bg-slate-50/70 text-xs font-semibold uppercase text-slate-500">
                    <tr>
                        @foreach($trackingVisibleColumns as $column)
                            <th class="relative px-3 py-3 {{ $column === 'product' ? 'w-72' : ($column === 'sales' ? 'w-52' : ($column === 'actions' ? 'w-28' : 'w-36')) }}">
                                @if(in_array($column, ['price', 'sales', 'interest', 'risk', 'updated'], true))
                                    <button type="button" wire:click="sortTracking('{{ $column }}')" class="font-semibold uppercase">{{ $trackingColumns[$column] }}</button>
                                @else
                                    {{ $trackingColumns[$column] }}
                                @endif
                                <span x-on:mousedown.prevent="startResize($event, $el.parentElement)" class="absolute inset-y-2 right-0 w-1 cursor-col-resize rounded bg-transparent hover:bg-slate-300"></span>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if ($trackingProducts->isNotEmpty()) { ?>
                    <?php foreach ($trackingProducts as $tracked) { ?>
                        <?php
                            $snap = $tracked->latestSnapshot;
                        $velocity = (array) data_get($snap?->metrics_json, 'stock_velocity_24h', []);
                        $salesState = $salesPresentation($tracked, $snap);
                        ?>
                        <tr class="text-sm hover:bg-slate-50/50">
                            @if(in_array('product', $trackingVisibleColumns, true))
                                <td class="px-3 py-3"><div class="flex min-w-0 items-center gap-3"><div class="h-10 w-10 shrink-0 overflow-hidden rounded-[6px] border border-slate-200 bg-slate-50">@if($tracked->image_url)<img src="{{ $tracked->image_url }}" alt="" class="h-full w-full object-cover">@endif</div><div class="min-w-0"><p class="truncate font-medium text-slate-900">{{ $tracked->title ?: 'Trendyol ürünü' }}</p><p class="mt-0.5 truncate text-xs text-slate-500">{{ $tracked->brand ?: '-' }} · {{ $tracked->category_name ?: '-' }}</p></div></div></td>
                            @endif
                            @if(in_array('price', $trackingVisibleColumns, true))
                                <td class="px-3 py-3"><p class="font-medium text-slate-900">{{ $this->formatMoney($tracked->sale_price) }}</p><p class="mt-0.5 text-xs {{ (float) ($snap?->price_delta ?? 0) < 0 ? 'text-emerald-700' : ((float) ($snap?->price_delta ?? 0) > 0 ? 'text-rose-700' : 'text-slate-400') }}">{{ $this->priceDeltaLabel($snap?->price_delta) }}</p></td>
                            @endif
                            @if(in_array('stock', $trackingVisibleColumns, true))
                                <td class="px-3 py-3"><p class="font-medium text-slate-900">{{ $snap?->stock_quantity !== null ? number_format((int) $snap->stock_quantity, 0, ',', '.') : 'Yayınlanmıyor' }}</p><p class="mt-0.5 text-xs text-slate-500">@if($snap?->stock_quantity === null)Trendyol sayısal stok yayınlamıyor @elseif($snap?->estimated_days_of_stock !== null)~{{ number_format((float) $snap->estimated_days_of_stock, 1, ',', '.') }} günde biter @elseif($snap?->estimated_daily_sales !== null && (float) $snap->estimated_daily_sales === 0.0)Ölçümde düşüş yok @elseif(($velocity['sample_count'] ?? 0) < 2)En az 2 stok ölçümü gerekli @else Hız verisi birikiyor @endif</p></td>
                            @endif
                            @if(in_array('sales', $trackingVisibleColumns, true))
                                <td class="px-3 py-3"><p class="font-semibold text-slate-900">{{ $salesState['label'] }}</p>@if($salesState['range'])<p class="mt-0.5 text-[11px] text-slate-500">{{ $salesState['range'] }}</p>@endif<div class="mt-1.5 flex flex-wrap items-center gap-1.5"><span class="rounded-[6px] border px-1.5 py-0.5 text-[10px] font-semibold {{ $salesState['badgeClass'] }}">{{ $salesState['badge'] }}</span><span class="text-[10px] font-semibold {{ $salesState['tone'] }}">Tahmin güveni %{{ $salesState['confidence'] }}</span></div><p class="mt-1 text-[10px] text-slate-400">{{ $salesState['detail'] }}</p></td>
                            @endif
                            @if(in_array('interest', $trackingVisibleColumns, true))
                                <td class="px-3 py-3"><span class="rounded-[6px] border border-sky-200 bg-sky-50 px-2 py-1 font-mono text-xs text-sky-700">{{ (int) $tracked->interest_score }}/100</span></td>
                            @endif
                            @if(in_array('risk', $trackingVisibleColumns, true))
                                <td class="px-3 py-3"><span class="rounded-[6px] border px-2 py-1 font-mono text-xs {{ (int) $tracked->risk_score >= 60 ? 'border-rose-200 bg-rose-50 text-rose-700' : ((int) $tracked->risk_score >= 35 ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700') }}">{{ (int) $tracked->risk_score }}/100</span></td>
                            @endif
                            @if(in_array('quality', $trackingVisibleColumns, true))
                                <td class="px-3 py-3"><div class="inline-flex items-center gap-1.5 rounded-[6px] border border-sky-200 bg-sky-50 px-2 py-1"><x-lucide.icon name="shield-check" class="h-3 w-3 text-sky-600" /><span class="text-xs font-semibold text-sky-700">Model %{{ (int) ($snap?->confidence_score ?? 0) }}</span></div><p class="mt-1.5 text-[10px] font-semibold uppercase tracking-wider text-slate-500">Veri kalitesi %{{ (int) $tracked->data_quality_score }}</p></td>
                            @endif
                            @if(in_array('updated', $trackingVisibleColumns, true))
                                <td class="px-3 py-3"><p class="text-slate-700">{{ $tracked->last_checked_at?->format('d.m.Y H:i') ?? '-' }}</p><p class="mt-0.5 text-xs text-slate-500">{{ $tracked->next_analysis_refresh_at?->format('d.m H:i') ?? 'Plan yok' }}</p></td>
                            @endif
                            @if(in_array('actions', $trackingVisibleColumns, true))
                                <td class="px-3 py-3"><div x-data="{ open: false }" class="relative"><button type="button" x-on:click="open = !open" class="inline-flex h-10 w-10 items-center justify-center rounded-[6px] border border-slate-200 bg-white text-slate-600"><x-lucide.icon name="more-vertical" class="h-4 w-4" /></button><div x-cloak x-show="open" x-on:click.outside="open = false" class="absolute right-0 z-30 mt-2 w-52 rounded-[8px] border border-slate-200 bg-white p-2 shadow-xl"><button type="button" wire:click="refreshProductAnalysisNow({{ $tracked->id }})" class="min-h-10 w-full rounded-[6px] px-2 text-left text-sm text-slate-700 hover:bg-slate-50">Anlık yenile</button>@if($tracked->tracking_status === 'active')<button type="button" wire:click="pauseTrackedProduct({{ $tracked->id }})" class="min-h-10 w-full rounded-[6px] px-2 text-left text-sm text-slate-700 hover:bg-slate-50">Takibi duraklat</button>@else<button type="button" wire:click="followTrackedProduct({{ $tracked->id }}, 'tracking_center')" class="min-h-10 w-full rounded-[6px] px-2 text-left text-sm text-slate-700 hover:bg-slate-50">Takibi başlat</button>@endif<button type="button" wire:click="toggleProductFavorite({{ $tracked->id }})" class="min-h-10 w-full rounded-[6px] px-2 text-left text-sm text-slate-700 hover:bg-slate-50">{{ $tracked->is_favorite ? 'Favoriden çıkar' : 'Favoriye ekle' }}</button><a href="{{ $tracked->source_url }}" target="_blank" rel="noopener" class="flex min-h-10 items-center rounded-[6px] px-2 text-sm text-slate-700 hover:bg-slate-50">Trendyol'da aç</a><button type="button" wire:click="removeTrackedProduct({{ $tracked->id }})" wire:confirm="Ürün ve tüm takip geçmişi silinsin mi?" class="min-h-10 w-full rounded-[6px] px-2 text-left text-sm text-rose-700 hover:bg-rose-50">Sil</button></div></div></td>
                            @endif
                        </tr>
                    <?php } ?>
                    <?php } else { ?>
                        <tr><td colspan="{{ max(1, count($trackingVisibleColumns)) }}" class="px-4 py-12 text-center text-sm text-slate-500">Bu filtrelerle eşleşen takip kaydı bulunamadı.</td></tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <div class="grid grid-cols-1 gap-3 p-4 md:hidden">
            <?php if ($trackingProducts->isNotEmpty()) { ?>
            <?php foreach ($trackingProducts as $tracked) { ?>
                <?php
                    $snap = $tracked->latestSnapshot;
                $velocity = (array) data_get($snap?->metrics_json, 'stock_velocity_24h', []);
                $salesState = $salesPresentation($tracked, $snap);
                ?>
                <article class="rounded-[8px] border border-slate-200 bg-white p-3 shadow-sm">
                    <div class="flex items-start gap-3"><div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-slate-900">{{ $tracked->title ?: 'Trendyol ürünü' }}</p><p class="mt-1 truncate text-xs text-slate-500">{{ $tracked->brand ?: '-' }} · {{ $tracked->category_name ?: '-' }}</p></div><div class="flex shrink-0 items-center gap-2"><div class="inline-flex shrink-0 items-center gap-1 rounded-[6px] border border-sky-200 bg-sky-50 px-2 py-1"><x-lucide.icon name="shield-check" class="h-3 w-3 text-sky-600" /><span class="font-mono text-[10px] font-semibold text-sky-700">Model %{{ (int) ($snap?->confidence_score ?? 0) }}</span></div><div x-data="{ open: false }" class="relative"><button type="button" x-on:click="open = !open" class="inline-flex h-7 w-7 items-center justify-center rounded-[6px] border border-slate-200 bg-white text-slate-600 hover:bg-slate-50"><x-lucide.icon name="more-vertical" class="h-4 w-4" /></button><div x-cloak x-show="open" x-on:click.outside="open = false" class="absolute right-0 z-30 mt-2 w-48 rounded-[8px] border border-slate-200 bg-white p-1.5 shadow-xl"><button type="button" wire:click="refreshProductAnalysisNow({{ $tracked->id }})" class="flex min-h-[36px] w-full items-center rounded-[6px] px-2 text-sm text-slate-700 hover:bg-slate-50">Anlık yenile</button>@if($tracked->tracking_status === 'active')<button type="button" wire:click="pauseTrackedProduct({{ $tracked->id }})" class="flex min-h-[36px] w-full items-center rounded-[6px] px-2 text-sm text-slate-700 hover:bg-slate-50">Duraklat</button>@else<button type="button" wire:click="followTrackedProduct({{ $tracked->id }}, 'tracking_center')" class="flex min-h-[36px] w-full items-center rounded-[6px] px-2 text-sm font-medium text-slate-900 hover:bg-slate-50">Takibi başlat</button>@endif<button type="button" wire:click="toggleProductFavorite({{ $tracked->id }})" class="flex min-h-[36px] w-full items-center rounded-[6px] px-2 text-sm text-slate-700 hover:bg-slate-50">{{ $tracked->is_favorite ? 'Favoriden çıkar' : 'Favoriye ekle' }}</button><a href="{{ $tracked->source_url }}" target="_blank" rel="noopener" class="flex min-h-[36px] w-full items-center rounded-[6px] px-2 text-sm text-slate-700 hover:bg-slate-50">Trendyol'da aç</a><button type="button" wire:click="removeTrackedProduct({{ $tracked->id }})" wire:confirm="Ürün ve tüm takip geçmişi silinsin mi?" class="flex min-h-[36px] w-full items-center rounded-[6px] px-2 text-sm text-rose-700 hover:bg-rose-50">Sil</button></div></div></div></div>
                    <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3"><div class="rounded-[6px] bg-slate-50 p-2"><p class="text-[10px] text-slate-400">Fiyat</p><p class="mt-1 text-xs font-semibold text-slate-900">{{ $this->formatMoney($tracked->sale_price) }}</p></div><div class="min-w-0 rounded-[6px] bg-slate-50 p-2"><p class="text-[10px] text-slate-400">Tahmini satış</p><p class="mt-1 text-xs font-semibold text-slate-900">{{ $salesState['label'] }}</p>@if($salesState['range'])<p class="mt-1 truncate text-[10px] text-slate-500">{{ $salesState['range'] }}</p>@endif<span class="mt-1.5 inline-flex rounded-[6px] border px-1.5 py-0.5 text-[10px] font-semibold {{ $salesState['badgeClass'] }}">{{ $salesState['badge'] }} · %{{ $salesState['confidence'] }}</span></div><div class="rounded-[6px] bg-slate-50 p-2"><p class="text-[10px] text-slate-400">Stok bitiş</p><p class="mt-1 text-xs font-semibold text-slate-900">{{ $snap?->stock_quantity === null ? 'Stok yayınlanmıyor' : ($snap?->estimated_days_of_stock !== null ? '~' . number_format((float) $snap->estimated_days_of_stock, 1, ',', '.') . ' gün' : (($velocity['sample_count'] ?? 0) < 2 ? '2 ölçüm gerekli' : 'Düşüş yok')) }}</p></div><div class="rounded-[6px] bg-slate-50 p-2"><p class="text-[10px] text-slate-400">Risk</p><p class="mt-1 text-xs font-semibold text-slate-900">{{ (int) $tracked->risk_score }}/100</p></div></div>
                </article>
            <?php } ?>
            <?php } else { ?>
                <div class="rounded-[8px] border border-dashed border-slate-300 bg-slate-50/70 p-5 text-center text-sm text-slate-500">Takibe alınmış ürün yok. İlk dört araştırma ekranındaki Takibe Al düğmesini kullanın.</div>
            <?php } ?>
        </div>
    </section>
</div>
