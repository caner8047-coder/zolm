@php
    $tracking = $this->trackingDashboard;
    $trackingColumns = $this->trackingColumnDefinitions();
    $schedulerState = $this->trackingSchedulerState;
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
    $radarStates = collect($tracking['products'] ?? [])->map(fn ($tracked) => $salesPresentation($tracked, $tracked->latestSnapshot));
    $radarSummary = [
        'observed' => $radarStates->whereIn('status', ['observed', 'observed_with_restock'])->count(),
        'proxy' => $radarStates->where('status', 'proxy')->count(),
        'no_movement' => $radarStates->whereIn('status', ['no_movement', 'no_movement_after_restock'])->count(),
        'waiting' => $radarStates->whereIn('status', ['warming_up', 'unavailable'])->count(),
    ];
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
                    <span class="inline-flex min-h-[40px] items-center justify-center gap-2 rounded-[6px] border px-3 py-2 text-xs font-semibold {{ $schedulerState['healthy'] ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700' }}">
                        <span class="h-2 w-2 rounded-full {{ $schedulerState['healthy'] ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                        {{ $schedulerState['label'] }} · {{ (int) config('marketplace.trendyol_booster.tracking_interval_minutes', 60) }} dk
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

            <div class="mt-4 grid grid-cols-2 gap-2 lg:grid-cols-4 lg:gap-3">
                <div class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/60 p-3"><div class="flex items-center justify-between gap-2"><p class="truncate text-xs font-medium text-slate-500">Stok sinyali</p><span class="h-2 w-2 shrink-0 rounded-full bg-emerald-500"></span></div><p class="mt-1 text-xl font-semibold text-slate-900">{{ $radarSummary['observed'] }}</p><p class="mt-0.5 truncate text-[11px] text-slate-500">Doğrudan stok erimesi</p></div>
                <div class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/60 p-3"><div class="flex items-center justify-between gap-2"><p class="truncate text-xs font-medium text-slate-500">İlgi proxy</p><span class="h-2 w-2 shrink-0 rounded-full bg-sky-500"></span></div><p class="mt-1 text-xl font-semibold text-slate-900">{{ $radarSummary['proxy'] }}</p><p class="mt-0.5 truncate text-[11px] text-slate-500">Düşük güvenli tahmin</p></div>
                <div class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/60 p-3"><div class="flex items-center justify-between gap-2"><p class="truncate text-xs font-medium text-slate-500">Hareket yok</p><span class="h-2 w-2 shrink-0 rounded-full bg-slate-400"></span></div><p class="mt-1 text-xl font-semibold text-slate-900">{{ $radarSummary['no_movement'] }}</p><p class="mt-0.5 truncate text-[11px] text-slate-500">Stok düşüşü gözlenmedi</p></div>
                <div class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/60 p-3"><div class="flex items-center justify-between gap-2"><p class="truncate text-xs font-medium text-slate-500">Veri bekliyor</p><span class="h-2 w-2 shrink-0 rounded-full bg-amber-500"></span></div><p class="mt-1 text-xl font-semibold text-slate-900">{{ $radarSummary['waiting'] }}</p><p class="mt-0.5 truncate text-[11px] text-slate-500">Isınan veya eksik kaynak</p></div>
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
                    @forelse($tracking['products'] as $tracked)
                        @php
                            $snap = $tracked->latestSnapshot;
                            $velocity = (array) data_get($snap?->metrics_json, 'stock_velocity_24h', []);
                            $salesState = $salesPresentation($tracked, $snap);
                        @endphp
                        <tr wire:key="tracking-row-{{ $tracked->id }}" class="text-sm hover:bg-slate-50/50">
                            @foreach($trackingVisibleColumns as $column)
                                @if($column === 'product')
                                    <td class="px-3 py-3"><div class="flex min-w-0 items-center gap-3"><div class="h-10 w-10 shrink-0 overflow-hidden rounded-[6px] border border-slate-200 bg-slate-50">@if($tracked->image_url)<img src="{{ $tracked->image_url }}" alt="" class="h-full w-full object-cover">@endif</div><div class="min-w-0"><p class="truncate font-medium text-slate-900">{{ $tracked->title ?: 'Trendyol ürünü' }}</p><p class="mt-0.5 truncate text-xs text-slate-500">{{ $tracked->brand ?: '-' }} · {{ $tracked->category_name ?: '-' }}</p></div></div></td>
                                @elseif($column === 'price')
                                    <td class="px-3 py-3"><p class="font-medium text-slate-900">{{ $this->formatMoney($tracked->sale_price) }}</p><p class="mt-0.5 text-xs {{ (float) ($snap?->price_delta ?? 0) < 0 ? 'text-emerald-700' : ((float) ($snap?->price_delta ?? 0) > 0 ? 'text-rose-700' : 'text-slate-400') }}">{{ $this->priceDeltaLabel($snap?->price_delta) }}</p></td>
                                @elseif($column === 'stock')
                                    <td class="px-3 py-3"><p class="font-medium text-slate-900">{{ $snap?->stock_quantity !== null ? number_format((int) $snap->stock_quantity, 0, ',', '.') : 'Yayınlanmıyor' }}</p><p class="mt-0.5 text-xs text-slate-500">@if($snap?->stock_quantity === null)Trendyol sayısal stok yayınlamıyor @elseif($snap?->estimated_days_of_stock !== null)~{{ number_format((float) $snap->estimated_days_of_stock, 1, ',', '.') }} günde biter @elseif($snap?->estimated_daily_sales !== null && (float) $snap->estimated_daily_sales === 0.0)Ölçümde düşüş yok @elseif(($velocity['sample_count'] ?? 0) < 2)En az 2 stok ölçümü gerekli @else Hız verisi birikiyor @endif</p></td>
                                @elseif($column === 'sales')
                                    <td class="px-3 py-3"><p class="font-semibold text-slate-900">{{ $salesState['label'] }}</p>@if($salesState['range'])<p class="mt-0.5 text-[11px] text-slate-500">{{ $salesState['range'] }}</p>@endif<div class="mt-1.5 flex flex-wrap items-center gap-1.5"><span class="rounded-[6px] border px-1.5 py-0.5 text-[10px] font-semibold {{ $salesState['badgeClass'] }}">{{ $salesState['badge'] }}</span><span class="text-[10px] font-semibold {{ $salesState['tone'] }}">Tahmin güveni %{{ $salesState['confidence'] }}</span></div><p class="mt-1 text-[10px] text-slate-400">{{ $salesState['detail'] }}</p></td>
                                @elseif($column === 'interest')
                                    <td class="px-3 py-3"><span class="rounded-[6px] border border-sky-200 bg-sky-50 px-2 py-1 font-mono text-xs text-sky-700">{{ (int) $tracked->interest_score }}/100</span></td>
                                @elseif($column === 'risk')
                                    <td class="px-3 py-3"><span class="rounded-[6px] border px-2 py-1 font-mono text-xs {{ (int) $tracked->risk_score >= 60 ? 'border-rose-200 bg-rose-50 text-rose-700' : ((int) $tracked->risk_score >= 35 ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700') }}">{{ (int) $tracked->risk_score }}/100</span></td>
                                @elseif($column === 'quality')
                                    <td class="px-3 py-3"><div class="inline-flex items-center gap-1.5 rounded-[6px] border border-sky-200 bg-sky-50 px-2 py-1"><x-lucide.icon name="shield-check" class="h-3 w-3 text-sky-600" /><span class="text-xs font-semibold text-sky-700">Model %{{ (int) ($snap?->confidence_score ?? 0) }}</span></div><p class="mt-1.5 text-[10px] font-semibold uppercase tracking-wider text-slate-500">Veri kalitesi %{{ (int) $tracked->data_quality_score }}</p></td>
                                @elseif($column === 'updated')
                                    <td class="px-3 py-3"><p class="text-slate-700">{{ $tracked->last_checked_at?->format('d.m.Y H:i') ?? '-' }}</p><p class="mt-0.5 text-xs text-slate-500">{{ $tracked->next_analysis_refresh_at?->format('d.m H:i') ?? 'Plan yok' }}</p></td>
                                @elseif($column === 'actions')
                                    <td class="px-3 py-3"><div x-data="{ open: false }" class="relative"><button type="button" x-on:click="open = !open" class="inline-flex h-10 w-10 items-center justify-center rounded-[6px] border border-slate-200 bg-white text-slate-600"><x-lucide.icon name="more-vertical" class="h-4 w-4" /></button><div x-cloak x-show="open" x-on:click.outside="open = false" class="absolute right-0 z-30 mt-2 w-52 rounded-[8px] border border-slate-200 bg-white p-2 shadow-xl"><button type="button" wire:click="refreshProductAnalysisNow({{ $tracked->id }})" class="min-h-10 w-full rounded-[6px] px-2 text-left text-sm text-slate-700 hover:bg-slate-50">Anlık yenile</button>@if($tracked->tracking_status === 'active')<button type="button" wire:click="pauseTrackedProduct({{ $tracked->id }})" class="min-h-10 w-full rounded-[6px] px-2 text-left text-sm text-slate-700 hover:bg-slate-50">Takibi duraklat</button>@else<button type="button" wire:click="followTrackedProduct({{ $tracked->id }}, 'tracking_center')" class="min-h-10 w-full rounded-[6px] px-2 text-left text-sm text-slate-700 hover:bg-slate-50">Takibi başlat</button>@endif<button type="button" wire:click="toggleProductFavorite({{ $tracked->id }})" class="min-h-10 w-full rounded-[6px] px-2 text-left text-sm text-slate-700 hover:bg-slate-50">{{ $tracked->is_favorite ? 'Favoriden çıkar' : 'Favoriye ekle' }}</button><a href="{{ $tracked->source_url }}" target="_blank" rel="noopener" class="flex min-h-10 items-center rounded-[6px] px-2 text-sm text-slate-700 hover:bg-slate-50">Trendyol'da aç</a><button type="button" wire:click="removeTrackedProduct({{ $tracked->id }})" wire:confirm="Ürün ve tüm takip geçmişi silinsin mi?" class="min-h-10 w-full rounded-[6px] px-2 text-left text-sm text-rose-700 hover:bg-rose-50">Sil</button></div></div></td>
                                @endif
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ max(1, count($trackingVisibleColumns)) }}" class="px-4 py-12 text-center text-sm text-slate-500">Bu filtrelerle eşleşen takip kaydı bulunamadı.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="grid grid-cols-1 gap-3 p-4 md:hidden">
            @forelse($tracking['products'] as $tracked)
                @php
                    $snap = $tracked->latestSnapshot;
                    $velocity = (array) data_get($snap?->metrics_json, 'stock_velocity_24h', []);
                    $salesState = $salesPresentation($tracked, $snap);
                @endphp
                <article class="rounded-[8px] border border-slate-200 bg-white p-3 shadow-sm">
                    <div class="flex items-start gap-3"><div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-slate-900">{{ $tracked->title ?: 'Trendyol ürünü' }}</p><p class="mt-1 truncate text-xs text-slate-500">{{ $tracked->brand ?: '-' }} · {{ $tracked->category_name ?: '-' }}</p></div><div class="flex shrink-0 items-center gap-2"><div class="inline-flex shrink-0 items-center gap-1 rounded-[6px] border border-sky-200 bg-sky-50 px-2 py-1"><x-lucide.icon name="shield-check" class="h-3 w-3 text-sky-600" /><span class="font-mono text-[10px] font-semibold text-sky-700">Model %{{ (int) ($snap?->confidence_score ?? 0) }}</span></div><div x-data="{ open: false }" class="relative"><button type="button" x-on:click="open = !open" class="inline-flex h-7 w-7 items-center justify-center rounded-[6px] border border-slate-200 bg-white text-slate-600 hover:bg-slate-50"><x-lucide.icon name="more-vertical" class="h-4 w-4" /></button><div x-cloak x-show="open" x-on:click.outside="open = false" class="absolute right-0 z-30 mt-2 w-48 rounded-[8px] border border-slate-200 bg-white p-1.5 shadow-xl"><button type="button" wire:click="refreshProductAnalysisNow({{ $tracked->id }})" class="flex min-h-[36px] w-full items-center rounded-[6px] px-2 text-sm text-slate-700 hover:bg-slate-50">Anlık yenile</button>@if($tracked->tracking_status === 'active')<button type="button" wire:click="pauseTrackedProduct({{ $tracked->id }})" class="flex min-h-[36px] w-full items-center rounded-[6px] px-2 text-sm text-slate-700 hover:bg-slate-50">Duraklat</button>@else<button type="button" wire:click="followTrackedProduct({{ $tracked->id }}, 'tracking_center')" class="flex min-h-[36px] w-full items-center rounded-[6px] px-2 text-sm font-medium text-slate-900 hover:bg-slate-50">Takibi başlat</button>@endif<button type="button" wire:click="toggleProductFavorite({{ $tracked->id }})" class="flex min-h-[36px] w-full items-center rounded-[6px] px-2 text-sm text-slate-700 hover:bg-slate-50">{{ $tracked->is_favorite ? 'Favoriden çıkar' : 'Favoriye ekle' }}</button><a href="{{ $tracked->source_url }}" target="_blank" rel="noopener" class="flex min-h-[36px] w-full items-center rounded-[6px] px-2 text-sm text-slate-700 hover:bg-slate-50">Trendyol'da aç</a><button type="button" wire:click="removeTrackedProduct({{ $tracked->id }})" wire:confirm="Ürün ve tüm takip geçmişi silinsin mi?" class="flex min-h-[36px] w-full items-center rounded-[6px] px-2 text-sm text-rose-700 hover:bg-rose-50">Sil</button></div></div></div></div>
                    <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3"><div class="rounded-[6px] bg-slate-50 p-2"><p class="text-[10px] text-slate-400">Fiyat</p><p class="mt-1 text-xs font-semibold text-slate-900">{{ $this->formatMoney($tracked->sale_price) }}</p></div><div class="min-w-0 rounded-[6px] bg-slate-50 p-2"><p class="text-[10px] text-slate-400">Tahmini satış</p><p class="mt-1 text-xs font-semibold text-slate-900">{{ $salesState['label'] }}</p>@if($salesState['range'])<p class="mt-1 truncate text-[10px] text-slate-500">{{ $salesState['range'] }}</p>@endif<span class="mt-1.5 inline-flex rounded-[6px] border px-1.5 py-0.5 text-[10px] font-semibold {{ $salesState['badgeClass'] }}">{{ $salesState['badge'] }} · %{{ $salesState['confidence'] }}</span></div><div class="rounded-[6px] bg-slate-50 p-2"><p class="text-[10px] text-slate-400">Stok bitiş</p><p class="mt-1 text-xs font-semibold text-slate-900">{{ $snap?->stock_quantity === null ? 'Stok yayınlanmıyor' : ($snap?->estimated_days_of_stock !== null ? '~' . number_format((float) $snap->estimated_days_of_stock, 1, ',', '.') . ' gün' : (($velocity['sample_count'] ?? 0) < 2 ? '2 ölçüm gerekli' : 'Düşüş yok')) }}</p></div><div class="rounded-[6px] bg-slate-50 p-2"><p class="text-[10px] text-slate-400">Risk</p><p class="mt-1 text-xs font-semibold text-slate-900">{{ (int) $tracked->risk_score }}/100</p></div></div>
                </article>
            @empty
                <div class="rounded-[8px] border border-dashed border-slate-300 bg-slate-50/70 p-5 text-center text-sm text-slate-500">Takibe alınmış ürün yok. İlk dört araştırma ekranındaki Takibe Al düğmesini kullanın.</div>
            @endforelse
        </div>
    </section>
</div>
