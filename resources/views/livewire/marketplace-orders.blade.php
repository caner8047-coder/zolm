@php
    $formatMoney = fn ($value) => '₺' . number_format((float) $value, 2, ',', '.');
    $formatSignedMoney = fn ($value) => ((float) $value >= 0 ? '+' : '-') . '₺' . number_format(abs((float) $value), 2, ',', '.');
    $formatCount = fn ($value) => number_format((float) $value, 0, ',', '.');
    $formatProfitabilityPercent = fn ($value) => $value !== null ? '%' . number_format((float) $value, 1, ',', '.') : '—';
    $selectedLabelPrintSummary = $this->selectedLabelPrintSummary();
    $selectedLabelConfirmMessage = $selectedLabelPrintSummary['confirm_message'];
    $currentSortPreset = $this->currentSortPreset();
    $sortIcon = function (string $columnKey) use ($sortableColumns, $sortField, $sortDirection) {
        $dbColumn = $sortableColumns[$columnKey] ?? null;
        if (!$dbColumn) {
            return '';
        }

        return $sortField === $dbColumn
            ? ($sortDirection === 'asc' ? '▲' : '▼')
            : '⇅';
    };
@endphp

<div class="orders-page-shell -mt-1 w-full space-y-4 lg:-mt-4 lg:space-y-6"
     x-data="{
        importOpen: false,
        workspaceCollapsed: false,
        init() {
            try {
                this.workspaceCollapsed = JSON.parse(window.localStorage.getItem('orders-workspace-collapsed') ?? 'false');
            } catch (error) {
                this.workspaceCollapsed = false;
            }
        },
        toggleWorkspace() {
            this.workspaceCollapsed = !this.workspaceCollapsed;

            try {
                window.localStorage.setItem('orders-workspace-collapsed', JSON.stringify(this.workspaceCollapsed));
            } catch (error) {}
        }
     }">
    @once
        <style>
            .orders-page-shell {
                --orders-accent: #2563eb;
                --orders-ink: #0f172a;
                --orders-soft: #f8fafc;
                --orders-soft-strong: #f1f5f9;
                --orders-border: #e2e8f0;
            }

            .orders-control-surface {
                background: rgba(248, 250, 252, 0.6);
            }

            .orders-v2-table {
                table-layout: fixed;
                width: 100%;
                min-width: 100%;
            }

            .orders-v2-table th,
            .orders-v2-table td {
                vertical-align: top;
            }

            .orders-v2-table th {
                white-space: nowrap;
            }

            .orders-v2-table tbody > tr:not(.expanded-row) > td {
                overflow: hidden;
                text-overflow: clip;
                white-space: normal;
            }

            .orders-v2-table tbody > tr:not(.expanded-row) > td.order-actions-cell {
                overflow: visible;
                position: relative;
            }

            .orders-v2-table tbody > tr:not(.expanded-row) > td.order-metric-cell {
                overflow: visible;
                position: relative;
            }

            .orders-v2-table tbody > tr:not(.expanded-row) > td.order-metric-cell:hover,
            .orders-v2-table tbody > tr:not(.expanded-row) > td.order-metric-cell:focus-within {
                z-index: 20;
            }

            .orders-v2-table tbody > tr.expanded-row > td {
                white-space: normal;
            }

            .orders-table-shell {
                background:
                    linear-gradient(180deg, rgba(248, 250, 252, 0.88), rgba(255, 255, 255, 1));
            }

            .col-resize-handle {
                position: absolute;
                top: 0;
                right: 0;
                bottom: 0;
                width: 4px;
                cursor: col-resize;
                z-index: 10;
                background: transparent;
                transition: background 0.15s;
            }

            .col-resize-handle:hover,
            .col-resize-handle.active {
                background: #0f172a;
            }

            .sortable-th {
                cursor: pointer;
                user-select: none;
            }

            .sortable-th:hover {
                background-color: rgba(248, 250, 252, 0.95);
            }

            .orders-collapse-panel {
                transition: max-height 0.32s ease, opacity 0.24s ease, transform 0.24s ease, margin 0.24s ease;
                will-change: max-height, opacity, transform;
            }

            .orders-collapse-divider-line {
                height: 1px;
                background: linear-gradient(90deg, rgba(226, 232, 240, 0.15), rgba(203, 213, 225, 0.95), rgba(226, 232, 240, 0.15));
            }

            .orders-collapse-button {
                box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08);
            }

        </style>
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('columnResize', () => ({
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
                            if (!this.resizing) {
                                return;
                            }

                            const newWidth = Math.max(84, this.startWidth + (moveEvent.pageX - this.startX));
                            this.currentTh.style.width = newWidth + 'px';
                            this.currentTh.style.minWidth = newWidth + 'px';
                        };

                        const onMouseUp = () => {
                            this.resizing = false;
                            if (this.handle) {
                                this.handle.classList.remove('active');
                            }

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

    {{-- Flash mesajları --}}
    @if(session()->has('sync_message'))
        <div class="rounded-[8px] border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
            {{ session('sync_message') }}
        </div>
    @endif

    @if($importMessage)
        <div class="rounded-[8px] border border-sky-200 bg-sky-50 p-4 text-sm text-sky-800">
            {{ $importMessage }}
        </div>
    @endif

    @if($actionMessage !== '')
        <div class="rounded-[8px] border p-4 text-sm {{ $actionMessageTone === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-slate-50 text-slate-700' }}">
            {{ $actionMessage }}
        </div>
    @endif

    @php
        $latestOrderSyncAt = $sidebarSummary['latest_sync_at'] ?? null;
        $salesChartMax = max(1, ...array_column($todaySalesTrend['points'], 'products'));
        $salesChartPoints = collect($todaySalesTrend['points'])
            ->map(function (array $point) use ($salesChartMax): array {
                $point['x'] = round(($point['hour'] / 23) * 1000, 2);
                $point['y'] = round(108 - (($point['products'] / $salesChartMax) * 88), 2);

                return $point;
            })
            ->all();
        $salesChartPolyline = collect($salesChartPoints)
            ->map(fn (array $point) => $point['x'] . ',' . $point['y'])
            ->implode(' ');
        $salesChartArea = '0,108 ' . $salesChartPolyline . ' 1000,108';
    @endphp
    <div class="orders-collapse-panel overflow-hidden"
         x-bind:style="workspaceCollapsed ? 'max-height: 0px; opacity: 0; transform: translateY(-10px); margin-bottom: 0; pointer-events: none;' : 'max-height: 2200px; opacity: 1; transform: translateY(0); pointer-events: auto;'">
    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-5">
        <div class="space-y-4">
            <div class="min-w-0">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div class="inline-flex w-fit items-center rounded-[6px] border border-slate-200 bg-slate-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">
                        Sipariş Çalışma Alanı
                    </div>
                    <div class="flex flex-wrap items-center gap-1.5 text-[10px]">
                        <span class="inline-flex items-center gap-1.5 rounded-[5px] border {{ $hasConfiguredStores ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700' }} px-2 py-1 font-semibold">
                            <span class="h-1.5 w-1.5 rounded-full {{ $hasConfiguredStores ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                            {{ $hasConfiguredStores ? 'Canlı' : 'Kurulum gerekli' }}
                        </span>
                        <span class="rounded-[5px] border border-slate-200 bg-white px-2 py-1 text-slate-500">
                            Son senkron {{ $latestOrderSyncAt?->format('d.m H:i') ?: 'yok' }}
                        </span>
                    </div>
                </div>
                <h1 class="mt-3 text-2xl font-bold leading-none tracking-tight text-slate-950 lg:text-[32px]">Sipariş Yönetimi</h1>
                <p class="mt-2 hidden max-w-3xl text-sm leading-5 text-slate-500 sm:block">
                    Pazaryeri siparişlerini, kargo hazırlığını ve finans durumunu tek çalışma alanında yönetin.
                </p>
            </div>

            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-8">
                <button type="button"
                        wire:click="syncOrders"
                        wire:loading.attr="disabled"
                        wire:target="syncOrders"
                        class="col-span-2 inline-flex min-h-[44px] items-center justify-center gap-2 rounded-[6px] bg-slate-900 px-3 py-2 text-xs font-medium text-white transition hover:bg-slate-800 disabled:cursor-wait disabled:opacity-70">
                    <svg wire:loading.remove wire:target="syncOrders" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h5M20 20v-5h-5M19 9A7 7 0 006.4 5.4M5 15a7 7 0 0012.6 3.6" />
                    </svg>
                    <svg wire:loading wire:target="syncOrders" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" />
                        <path class="opacity-75" fill="currentColor" d="M12 3a9 9 0 00-9 9h3a6 6 0 016-6V3z" />
                    </svg>
                    <span wire:loading.remove wire:target="syncOrders">Sipariş senkronunu başlat</span>
                    <span wire:loading wire:target="syncOrders">Senkron başlatılıyor...</span>
                </button>
                <button type="button"
                        @click="importOpen = !importOpen"
                        class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50">
                    <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 16V4m0 0L8 8m4-4 4 4M5 20h14" />
                    </svg>
                    İçe Aktar
                </button>
                <a href="{{ route('mp.matching') }}"
                   class="relative inline-flex min-h-[44px] items-center justify-center gap-2 rounded-[6px] border border-indigo-200 bg-indigo-50 px-3 py-2 text-xs font-medium text-indigo-700 transition hover:bg-indigo-100">
                    <svg class="h-4 w-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    Eşleştir
                    @if((int) $stats['match_issue_orders'] > 0)
                        <span class="absolute right-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-indigo-500 px-1 text-[9px] font-bold text-white">{{ (int) $stats['match_issue_orders'] > 99 ? '99+' : $stats['match_issue_orders'] }}</span>
                    @endif
                </a>
                <a href="{{ route('mp.finance') }}"
                   class="relative inline-flex min-h-[44px] items-center justify-center gap-2 rounded-[6px] border {{ (int) $stats['finance_waiting_orders'] > 0 ? 'border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50' }} px-3 py-2 text-xs font-medium transition">
                    <svg class="h-4 w-4 {{ (int) $stats['finance_waiting_orders'] > 0 ? 'text-amber-500' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m4-9.5C15.2 7.6 13.8 7 12 7c-2.2 0-4 1.1-4 2.5s1.8 2.5 4 2.5 4 1.1 4 2.5S14.2 17 12 17c-1.8 0-3.2-.6-4-1.5" />
                    </svg>
                    Finans
                    @if((int) $stats['finance_waiting_orders'] > 0)
                        <span class="absolute right-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-amber-500 px-1 text-[9px] font-bold text-white">{{ (int) $stats['finance_waiting_orders'] > 99 ? '99+' : $stats['finance_waiting_orders'] }}</span>
                    @endif
                </a>
                <button type="button"
                        wire:click="exportCsv"
                        wire:loading.attr="disabled"
                        class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50">
                    <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5h6l2 2h4v12a2 2 0 01-2 2z" />
                    </svg>
                    Dışa Aktar
                </button>
                <a href="{{ route('mp.integrations') }}"
                   class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50">
                    <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h5M20 20v-5h-5M19 9A7 7 0 006.4 5.4M5 15a7 7 0 0012.6 3.6" />
                    </svg>
                    Entegrasyonlar
                </a>
                <a href="{{ route('mp.settings') }}"
                   class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50">
                    <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15.5A3.5 3.5 0 1012 8a3.5 3.5 0 000 7.5zM19.4 15a1.7 1.7 0 00.34 1.88l.06.06-2.12 2.12-.06-.06A1.7 1.7 0 0015.74 18a1.7 1.7 0 00-1.03 1.55V20h-3v-.45A1.7 1.7 0 0010.68 18a1.7 1.7 0 00-1.88.34l-.06.06-2.12-2.12.06-.06A1.7 1.7 0 007 14.34a1.7 1.7 0 00-1.55-1.03H5v-3h.45A1.7 1.7 0 007 9.28 1.7 1.7 0 006.66 7.4l-.06-.06 2.12-2.12.06.06A1.7 1.7 0 0010.66 5a1.7 1.7 0 001.03-1.55V3h3v.45A1.7 1.7 0 0015.72 5a1.7 1.7 0 001.88-.34l.06-.06 2.12 2.12-.06.06A1.7 1.7 0 0020 8.66a1.7 1.7 0 001.55 1.03H22v3h-.45A1.7 1.7 0 0020 13.72z" />
                    </svg>
                    Çıktı Ayarları
                </a>
            </div>

            <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3 sm:p-4">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-400">Siparişler</p>
                        <div class="mt-1.5 flex items-baseline gap-2">
                            <p class="text-xl font-bold tracking-tight text-slate-950 sm:text-2xl">{{ $formatCount($stats['total_orders']) }}</p>
                            <p class="text-xs text-slate-500">toplam sipariş</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 sm:text-right">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">{{ $formatCount($todaySalesTrend['total_products']) }} ürün</p>
                            <p class="text-[10px] text-slate-500">bugün satıldı · {{ $todaySalesTrend['date_label'] }}</p>
                        </div>
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-[6px] border border-emerald-200 bg-emerald-50 text-emerald-600">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l5-5 4 4 7-8m0 0h-5m5 0v5" />
                            </svg>
                        </span>
                    </div>
                </div>

                <div class="relative mt-3 rounded-[6px] outline-none focus-visible:ring-2 focus-visible:ring-slate-300 focus-visible:ring-offset-2"
                     x-data="{
                        points: @js($salesChartPoints),
                        active: null,
                        selectPoint(event) {
                            const bounds = event.currentTarget.getBoundingClientRect();
                            const ratio = Math.min(1, Math.max(0, (event.clientX - bounds.left) / bounds.width));
                            this.active = this.points[Math.round(ratio * (this.points.length - 1))];
                        },
                        tooltipTransform() {
                            if (!this.active || this.active.x < 125) return 'translateX(0)';
                            if (this.active.x > 875) return 'translateX(-100%)';
                            return 'translateX(-50%)';
                        }
                     }"
                     @pointermove="selectPoint($event)"
                     @pointerdown="selectPoint($event)"
                     @pointerleave="if ($event.pointerType !== 'touch') active = null"
                     @focus="active = points[Math.min(new Date().getHours(), 23)]"
                     @blur="active = null"
                     tabindex="0"
                     role="img"
                     aria-label="Bugün saatlere göre satılan ürün adedi grafiği">
                    <div x-cloak
                         x-show="active"
                         x-bind:style="`left: ${active ? active.x / 10 : 0}%; transform: ${tooltipTransform()}`"
                         class="pointer-events-none absolute -top-2 z-20 min-w-[132px] rounded-[8px] border border-slate-200 bg-white p-2.5 text-left shadow-lg">
                        <p class="text-[10px] font-medium text-slate-500" x-text="active?.label"></p>
                        <p class="mt-1 text-xs font-semibold text-slate-900">
                            <span x-text="active?.products ?? 0"></span> ürün
                        </p>
                        <p class="mt-0.5 text-[10px] text-slate-500">
                            <span x-text="active?.orders ?? 0"></span> sipariş
                        </p>
                    </div>

                    <svg class="h-[112px] w-full overflow-visible sm:h-[124px]"
                         viewBox="0 0 1000 120"
                         preserveAspectRatio="none"
                         aria-hidden="true">
                        <defs>
                            <linearGradient id="orders-sales-trend-fill" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#10b981" stop-opacity="0.18" />
                                <stop offset="100%" stop-color="#10b981" stop-opacity="0" />
                            </linearGradient>
                        </defs>
                        <line x1="0" y1="20" x2="1000" y2="20" stroke="#e2e8f0" stroke-dasharray="4 7" vector-effect="non-scaling-stroke" />
                        <line x1="0" y1="64" x2="1000" y2="64" stroke="#e2e8f0" stroke-dasharray="4 7" vector-effect="non-scaling-stroke" />
                        <line x1="0" y1="108" x2="1000" y2="108" stroke="#e2e8f0" vector-effect="non-scaling-stroke" />
                        <polygon points="{{ $salesChartArea }}" fill="url(#orders-sales-trend-fill)" />
                        <polyline points="{{ $salesChartPolyline }}"
                                  fill="none"
                                  stroke="#0f172a"
                                  stroke-width="2"
                                  stroke-linecap="round"
                                  stroke-linejoin="round"
                                  vector-effect="non-scaling-stroke" />
                        <line x-cloak
                              x-show="active"
                              x-bind:x1="active?.x"
                              x-bind:x2="active?.x"
                              y1="12"
                              y2="108"
                              stroke="#94a3b8"
                              stroke-dasharray="3 4"
                              vector-effect="non-scaling-stroke" />
                        <circle x-cloak
                                x-show="active"
                                x-bind:cx="active?.x"
                                x-bind:cy="active?.y"
                                r="5"
                                fill="#ffffff"
                                stroke="#0f172a"
                                stroke-width="2"
                                vector-effect="non-scaling-stroke" />
                    </svg>

                    @if($todaySalesTrend['total_products'] === 0)
                        <div class="pointer-events-none absolute inset-x-0 top-9 text-center">
                            <span class="rounded-[6px] border border-slate-200 bg-white/90 px-2.5 py-1 text-[10px] text-slate-500">Bugün henüz ürün satışı yok</span>
                        </div>
                    @endif

                    <div class="mt-1 flex justify-between text-[9px] font-medium text-slate-400 sm:text-[10px]">
                        <span>00:00</span>
                        <span>06:00</span>
                        <span>12:00</span>
                        <span>18:00</span>
                        <span>23:00</span>
                    </div>
                </div>
            </div>
        </div>

        <div x-cloak x-show="importOpen" x-transition class="mt-4 border-t border-slate-200 pt-4">
            <div class="mb-4 flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-slate-900">Excel’den sipariş yükle</p>
                    <p class="mt-1 text-xs text-slate-500">Müşteri, ürün ve varyant bilgilerini içeren Sipariş Kayıtları dosyasını kullanın.</p>
                </div>
                <button type="button"
                        @click="importOpen = false"
                        class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-[6px] text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                        aria-label="İçe aktarımı kapat">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <form wire:submit.prevent="importOrders" class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-slate-700">Yansıtma mağazası</span>
                        <select wire:model.live="legacyProjectionStoreId"
                                class="w-full rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-base text-slate-900 shadow-sm outline-none transition focus:border-slate-400 focus:ring-0 sm:py-2 sm:text-sm">
                            <option value="">Yalnızca eski veride bırak</option>
                            @foreach($legacyProjectionStoreOptions as $optionValue => $optionLabel)
                                <option value="{{ $optionValue }}">{{ $optionLabel }}</option>
                            @endforeach
                        </select>
                    </label>
                    <div class="flex items-end gap-3">
                        <label class="block flex-1 cursor-pointer rounded-[6px] border border-dashed border-slate-300 bg-slate-50 px-4 py-3 transition hover:border-slate-400 hover:bg-white">
                            <input type="file" wire:model="file" accept=".xlsx,.xls" class="hidden">
                            <div class="flex items-center gap-2">
                                <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                </svg>
                                <span class="text-sm text-slate-600">{{ $file ? $file->getClientOriginalName() : 'Dosya seçilmedi' }}</span>
                            </div>
                        </label>
                    </div>
                </div>

                @error('file') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
                @error('legacyProjectionStoreId') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror

                <div class="flex flex-wrap gap-2">
                    <button type="submit"
                            wire:loading.attr="disabled"
                            wire:target="importOrders,file"
                            class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 disabled:opacity-60 sm:w-auto sm:py-2">
                        <span wire:loading.remove wire:target="importOrders">Yükle ve İşle</span>
                        <span wire:loading wire:target="importOrders">İşleniyor...</span>
                    </button>
                    <button type="button"
                            wire:click="runSyncEngine"
                            wire:loading.attr="disabled"
                            class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 sm:w-auto sm:py-2">
                        Eski veri finans senkronu
                    </button>
                    <button type="button"
                            wire:click="previewLegacyFinancials"
                            wire:loading.attr="disabled"
                            class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 sm:w-auto sm:py-2">
                        Önizleme
                    </button>
                    <button type="button"
                            wire:click="projectLegacyFinancials"
                            wire:loading.attr="disabled"
                            @disabled(($legacyFinancialProjectionPreview['projected_rows'] ?? 0) === 0)
                            class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto sm:py-2">
                        Eski finansı taşı
                    </button>
                </div>

                @if($legacyProjectionResult)
                    <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 p-3">
                        <div class="flex flex-wrap items-center gap-2 text-xs text-slate-600">
                            <span class="font-semibold text-slate-900">{{ !empty($legacyProjectionResult['executed']) ? 'Aktarım sonucu' : 'Önizleme' }}</span>
                            <span class="rounded-[6px] border border-slate-200 bg-white px-2 py-0.5">Aday {{ $formatCount((int) data_get($legacyProjectionResult, 'projected_rows', 0)) }}</span>
                            <span class="rounded-[6px] border border-slate-200 bg-white px-2 py-0.5">Yeni {{ $formatCount((int) data_get($legacyProjectionResult, 'created', 0)) }}</span>
                            <span class="rounded-[6px] border border-slate-200 bg-white px-2 py-0.5">Güncelleme {{ $formatCount((int) data_get($legacyProjectionResult, 'updated', 0)) }}</span>
                            <span class="rounded-[6px] border border-slate-200 bg-white px-2 py-0.5">Etkilenen {{ $formatCount((int) data_get($legacyProjectionResult, 'impacted_orders', 0)) }}</span>
                        </div>
                        <div class="mt-2 text-xs font-mono text-slate-400">
                            {{ $this->legacyProjectionRunCommand }}
                            <br>
                            {{ $this->legacyProjectionDryRunCommand }}
                        </div>
                    </div>
                @endif
            </form>
        </div>
    </section>
    </div>

    <div class="pb-1">
        <div class="flex items-center gap-3">
            <div class="orders-collapse-divider-line flex-1"></div>
            <button type="button"
                    @click="toggleWorkspace"
                    class="orders-collapse-button inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-500 transition hover:border-slate-300 hover:text-slate-900"
                    aria-label="Sipariş çalışma alanını daralt">
                <svg class="h-4 w-4 transition duration-200"
                     :class="{ 'rotate-180': workspaceCollapsed }"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div class="orders-collapse-divider-line flex-1"></div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════ --}}
    {{-- BLOK 2: TABLO VE FİLTRELER --}}
    {{-- ═══════════════════════════════════════════════ --}}
    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6"
             x-data="{
                advancedFilters: @js(filled($searchProduct) || filled($searchCustomer) || filled($marketplaceFilter) || filled($storeFilter) || filled($labelFilter) || filled($profitStateFilter) || filled($financialStateFilter) || filled($legalEntityFilter) || filled($matchStateFilter) || filled($dateFrom) || filled($dateTo))
             }">

        <div class="orders-control-surface mb-4 rounded-[8px] border border-slate-200 p-3 lg:p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-base font-semibold text-slate-900">Siparişleri filtrele</h2>
                        @if(count($activeFilters) > 0)
                            <span class="rounded-[6px] bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-600">
                                {{ count($activeFilters) }} filtre
                            </span>
                        @endif
                    </div>
                    <p class="mt-0.5 text-xs text-slate-500">{{ $formatCount($orders->total()) }} sipariş gösteriliyor · {{ $this->currentSortLabel() }}</p>
                </div>
                <div class="flex w-full items-center gap-2 sm:w-auto">
                    <div class="hidden md:block">
                        @include('livewire.partials.mp-column-toggle', ['columnDefs' => $columnDefs, 'visibleColumns' => $visibleColumns])
                    </div>
                    <button type="button"
                            @click="advancedFilters = !advancedFilters"
                            class="inline-flex min-h-[44px] flex-1 items-center justify-center gap-2 rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 sm:flex-none sm:py-2">
                        <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M7 12h10m-7 6h4" />
                        </svg>
                        Filtreler
                    </button>
                </div>
            </div>

            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(280px,1fr)_220px_220px_220px]">
                <div class="relative sm:col-span-2 xl:col-span-1">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input type="text"
                           wire:model.live.debounce.400ms="search"
                           placeholder="Sipariş no, müşteri veya kargo kodu ara"
                           class="w-full rounded-[6px] border border-slate-200 bg-white py-3 pl-10 pr-4 text-base font-medium text-slate-900 shadow-sm transition focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                </div>
                <select wire:model.live="statusFilter"
                        class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                    <option value="">Tüm durumlar</option>
                    @foreach($statusOptions as $statusValue => $statusLabel)
                        <option value="{{ $statusValue }}">{{ $statusLabel }}</option>
                    @endforeach
                </select>
                <select wire:model.live="marketplaceFilter"
                        class="hidden w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm lg:block">
                    <option value="">Tüm pazaryerleri</option>
                    @foreach($marketplaceOptions as $marketplace)
                        <option value="{{ $marketplace }}">{{ $this->humanMarketplace($marketplace) }}</option>
                    @endforeach
                </select>
                <select wire:model.live="storeFilter"
                        class="hidden w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm lg:block">
                    <option value="">Tüm mağazalar</option>
                    @foreach($storeOptions as $store)
                        <option value="{{ $store->id }}">{{ $store->store_name }}</option>
                    @endforeach
                </select>
            </div>

            @if(count($activeFilters) > 0)
                <div class="mt-3 flex flex-col gap-2 border-t border-slate-200 pt-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex min-w-0 flex-wrap gap-2">
                        @foreach($activeFilters as $filter)
                            <span class="rounded-[6px] border border-slate-200 bg-white px-2 py-1 text-[11px] text-slate-600">{{ $filter }}</span>
                        @endforeach
                    </div>
                    <button type="button"
                            wire:click="resetFilters"
                            class="inline-flex min-h-[44px] w-full shrink-0 items-center justify-center rounded-[6px] px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-white hover:text-slate-900 sm:w-auto">
                        Temizle
                    </button>
                </div>
            @endif

            <div x-cloak x-show="advancedFilters" x-transition class="mt-3 border-t border-slate-200 pt-3">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <select wire:model.live="marketplaceFilter"
                            class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm lg:hidden">
                        <option value="">Tüm pazaryerleri</option>
                        @foreach($marketplaceOptions as $marketplace)
                            <option value="{{ $marketplace }}">{{ $this->humanMarketplace($marketplace) }}</option>
                        @endforeach
                    </select>
                    <select wire:model.live="storeFilter"
                            class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm lg:hidden">
                        <option value="">Tüm mağazalar</option>
                        @foreach($storeOptions as $store)
                            <option value="{{ $store->id }}">{{ $store->store_name }}</option>
                        @endforeach
                    </select>
                    <input type="text"
                           wire:model.live.debounce.400ms="searchProduct"
                           placeholder="Ürün, barkod veya stok kodu"
                           class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                    <input type="text"
                           wire:model.live.debounce.400ms="searchCustomer"
                           placeholder="Müşteri, telefon veya e-posta"
                           class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                    <select wire:model.live="matchStateFilter"
                            class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                        <option value="">Tüm eşleşmeler</option>
                        <option value="full_match">Tam eşleşenler</option>
                        <option value="needs_match">Kontrol gerekenler</option>
                    </select>
                    <select wire:model.live="financialStateFilter"
                            class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                        <option value="">Tüm finans durumları</option>
                        <option value="ready">Finans verisi gelenler</option>
                        <option value="waiting">Finans bekleyenler</option>
                    </select>
                    <label class="block">
                        <span class="mb-1 block text-xs font-medium text-slate-500">Başlangıç</span>
                        <input type="date" wire:model.live="dateFrom"
                               class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-xs font-medium text-slate-500">Bitiş</span>
                        <input type="date" wire:model.live="dateTo"
                               class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                    </label>
                    <select wire:model.live="profitStateFilter"
                            class="w-full self-end rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                        <option value="">Tüm kâr durumları</option>
                        <option value="confirmed">Kesin kâr</option>
                        <option value="estimated">Tahmini kâr</option>
                        <option value="missing">Hesaplanmayan</option>
                    </select>
                    <select wire:model.live="legalEntityFilter"
                            class="w-full self-end rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                        <option value="">Tüm firmalar</option>
                        @foreach($legalEntityOptions as $entity)
                            <option value="{{ $entity->id }}">{{ $entity->name }}</option>
                        @endforeach
                    </select>
                    <select wire:model.live="labelFilter"
                            class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                        <option value="">Renk Etiketi</option>
                        @foreach($orderLabelDefinitions as $labelKey => $label)
                            <option value="{{ $labelKey }}">{{ $label['name'] }}</option>
                        @endforeach
                    </select>
                    <select wire:change="applySortPreset($event.target.value)"
                            class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm xl:col-span-2">
                        @foreach($sortPresets as $presetKey => $preset)
                            <option value="{{ $presetKey }}" @selected($currentSortPreset === $presetKey)>{{ $preset['label'] }}</option>
                        @endforeach
                        @if($currentSortPreset === 'custom')
                            <option value="custom" selected>Tablo kolonuna göre özel sıralama</option>
                        @endif
                    </select>
                    <button type="button"
                            wire:click="openOrderLabelManager"
                            class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 sm:py-2">
                        Etiketleri Yönet
                    </button>
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════ --}}
        {{-- TABLO --}}
        {{-- ═══════════════════════════════════════════════ --}}
        <div x-data="{ expanded: [] }">
            @if(!$hasConfiguredStores)
                <div class="rounded-[8px] border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-[6px] bg-slate-900 text-white shadow-lg shadow-slate-900/10">
                        <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 7h18M6 10h12M5 13h14M8 17h8" />
                        </svg>
                    </div>
                    <h3 class="mt-4 text-lg font-semibold text-slate-900">Henüz mağaza bağlantısı yok</h3>
                    <p class="mt-2 text-sm text-slate-500">İlk Trendyol mağazanızı eklediğinizde siparişler burada görünecek.</p>
                    <div class="mt-4">
                        <a href="{{ route('mp.integrations') }}" class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800">
                            Entegrasyon kurulumuna git
                        </a>
                    </div>
                </div>
            @elseif(!$hasChannelData)
                <div class="rounded-[8px] border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center">
                    <h3 class="mt-4 text-lg font-semibold text-slate-900">Mağazalar hazır, veri bekleniyor</h3>
                    <p class="mt-2 text-sm text-slate-500">İlk senkron tamamlandığında siparişler burada listelenecek.</p>
                </div>
            @else
                {{-- Mobil kart görünümü --}}
                <div class="space-y-3 md:hidden">
                    @foreach($orders as $order)
                        @php
                            $snapshot = data_get($order, 'order_snapshot');
                            $package = data_get($order, 'package_summary');
                            $orderColorLabel = $orderLabelDefinitions[$order->color_label_key] ?? null;
                            $legacyOrder = data_get($order, 'legacy_operational_order');
                            $legacyHasFinancial = $legacyOrder?->financialOrders?->isNotEmpty() ?? false;
                            $profitState = $order->profit_state_metric ?? ($snapshot?->profit_state ?: 'estimated');
                            $profitValue = (float) ($order->profit_value_metric
                                ?? ($profitState === 'confirmed' ? $snapshot?->confirmed_profit : $snapshot?->estimated_profit));
                            $grossRevenue = (float) ($order->gross_revenue_metric ?? $snapshot?->gross_revenue);
                            $netReceivable = (float) ($order->net_receivable_metric ?? $snapshot?->net_receivable ?? 0);
                            $estimatedCogs = (float) (
                                ($order->display_cogs_cost_metric ?? $snapshot?->cogs_cost ?? 0)
                                + ($order->display_packaging_cost_metric ?? $snapshot?->packaging_cost ?? 0)
                            );
                            $costProfitPercent = $estimatedCogs > 0
                                ? \App\Services\ProfitabilityMetric::profitPercent($profitValue, $estimatedCogs)
                                : null;
                            $labelPrintSummary = $this->orderLabelPrintSummary($order);
                            $matchRatio = (int) ($order->matched_lines_count ?? 0) . '/' . (int) ($order->item_lines_count ?? 0);
                            $statusKey = (string) ($order->display_status_key ?? '');
                            $statusLabel = (string) ($order->display_status_label ?? $this->humanStatus($order->order_status, $order->marketplace_alias, $package?->cargo_tracking_number, $order->delivered_at));
                            $statusTone = (string) ($order->display_status_tone ?? $this->statusTone($order->order_status, $order->marketplace_alias, $package?->cargo_tracking_number, $order->delivered_at));
                            $isCancelled = in_array($statusKey, ['cancelled', 'returned', 'rejected'], true);
                        @endphp

                        <article class="overflow-hidden rounded-[8px] border border-slate-200 bg-white shadow-sm {{ $isCancelled ? 'opacity-50 grayscale hover:opacity-75' : '' }}">
                            <div class="flex items-center justify-end border-b border-slate-200 px-4 pb-3 pt-3">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-slate-400">#{{ $order->id }}</span>
                                    @include('livewire.partials.mp-order-row-actions', ['order' => $order, 'align' => 'right'])
                                </div>
                            </div>
                            <button type="button"
                                    class="w-full px-4 py-3 text-left"
                                    @click="expanded.includes({{ $order->id }}) ? expanded = expanded.filter(i => i !== {{ $order->id }}) : expanded.push({{ $order->id }})">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2">
                                            <svg class="h-4 w-4 shrink-0 text-slate-400 transition"
                                                 :class="{ 'rotate-90 text-slate-900': expanded.includes({{ $order->id }}) }"
                                                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                            </svg>
                                            <p class="truncate text-sm font-semibold text-slate-900">{{ $order->order_number }}</p>
                                        </div>
                                        <p class="mt-1 truncate text-xs text-slate-500">{{ $order->store_name_alias }} · {{ $this->humanMarketplace($order->marketplace_alias) }}</p>
                                        @if($orderColorLabel)
                                            <div class="mt-2 inline-flex max-w-full items-center gap-1.5 rounded-[6px] border px-2 py-0.5 text-[10px] font-semibold"
                                                 style="background-color: {{ $orderColorLabel['bg_color'] }}; border-color: {{ $orderColorLabel['border_color'] }}; color: {{ $orderColorLabel['color'] }};">
                                                <span class="h-1.5 w-1.5 shrink-0 rounded-full" style="background-color: {{ $orderColorLabel['color'] }};"></span>
                                                <span class="truncate">{{ $orderColorLabel['name'] }}</span>
                                            </div>
                                        @endif
                                        <p class="mt-2 text-sm font-medium text-slate-900">{{ $order->customer_name ?: 'Müşteri bilgisi yok' }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ $this->displayOrderDate($order)?->format('d.m.Y H:i') ?: '-' }}</p>
                                    </div>

                                    <div class="text-right">
                                        <x-zolm.status-badge size="sm" :tone="$statusTone">
                                            {{ $statusLabel }}
                                        </x-zolm.status-badge>
                                        @if($orderColorLabel)
                                            <div class="mt-2 inline-flex max-w-full items-center gap-1.5 rounded-[6px] border px-2 py-0.5 text-[10px] font-semibold"
                                                 style="background-color: {{ $orderColorLabel['bg_color'] }}; border-color: {{ $orderColorLabel['border_color'] }}; color: {{ $orderColorLabel['color'] }};">
                                                <span class="h-1.5 w-1.5 shrink-0 rounded-full" style="background-color: {{ $orderColorLabel['color'] }};"></span>
                                                <span class="truncate">{{ $orderColorLabel['name'] }}</span>
                                            </div>
                                        @endif
                                        <p class="mt-3 text-base font-semibold text-slate-900">{{ $formatMoney($grossRevenue) }}</p>
                                        <p class="mt-1 text-sm font-semibold {{ $profitValue >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ $formatMoney($profitValue) }}</p>
                                        @if($costProfitPercent !== null)
                                            <p class="mt-1 text-[11px] font-medium {{ $profitValue >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                                                Maliyet {{ $formatProfitabilityPercent($costProfitPercent) }}
                                            </p>
                                        @endif
                                    </div>
                                </div>

                                <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                    <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-3 py-2">
                                        <p class="text-[10px] uppercase tracking-[0.16em] text-slate-500">Ödeme</p>
                                        <p class="mt-1 text-sm font-medium text-slate-900">{{ $formatMoney($netReceivable) }}</p>
                                    </div>
                                    <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-3 py-2">
                                        <p class="text-[10px] uppercase tracking-[0.16em] text-slate-500">Eşleşme</p>
                                        <p class="mt-1 text-sm font-medium text-slate-900">{{ $matchRatio }}</p>
                                    </div>
                                </div>

                                @if($package)
                                    @php
                                        $mobileCargoCompany = $this->displayCargoCompany(
                                            $package->cargo_company,
                                            $package->shipment_provider,
                                        );
                                        $mobileTrackingUrl = $this->trackingUrl($mobileCargoCompany, $package->cargo_tracking_number);
                                        $mobileCargoDueDate = $this->displayCargoDueDate($order);
                                    @endphp
                                    <p class="mt-3 text-xs text-slate-500">
                                        {{ $mobileCargoCompany ?: 'Kargo yok' }}
                                        @if($package->cargo_tracking_number)
                                            ·
                                            @if($mobileTrackingUrl)
                                                <a href="{{ $mobileTrackingUrl }}"
                                                   target="_blank"
                                                   rel="noopener noreferrer"
                                                   class="font-medium text-sky-600 transition hover:text-sky-700 hover:underline">
                                                    {{ $package->cargo_tracking_number }}
                                                </a>
                                            @else
                                                {{ $package->cargo_tracking_number }}
                                            @endif
                                        @endif
                                    </p>
                                    @if($mobileCargoDueDate)
                                        <p class="mt-1 text-[11px] text-slate-400">
                                            Son teslim: {{ $mobileCargoDueDate->format('d/m H:i') }}
                                        </p>
                                    @endif
                                    @if($labelPrintSummary['has_printed'])
                                        <div class="mt-2 inline-flex items-center gap-1.5 rounded-[6px] border border-emerald-200 bg-emerald-50 px-2 py-1 text-[10px] font-semibold text-emerald-700">
                                            <svg class="h-3 w-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                            <span>{{ $labelPrintSummary['badge_label'] }}</span>
                                        </div>
                                        @if($labelPrintSummary['meta_label'])
                                            <p class="mt-1 text-[10px] text-emerald-700/80">{{ $labelPrintSummary['meta_label'] }}</p>
                                        @endif
                                    @endif
                                @endif

                                @if($legacyOrder?->is_corporate_invoice === 'Evet')
                                    <p class="mt-2 inline-flex rounded-[6px] border border-sky-200 bg-sky-50 px-2 py-0.5 text-[10px] font-semibold text-sky-700">
                                        Kurumsal fatura
                                    </p>
                                @endif
                            </button>

                            <div x-show="expanded.includes({{ $order->id }})"
                                 x-transition
                                 x-cloak
                                 class="border-t border-slate-200 bg-slate-50/60 p-3 sm:p-4">
                                @include('livewire.partials.marketplace-order-v2-detail', ['order' => $order])
                            </div>
                        </article>
                    @endforeach
                </div>

                {{-- Desktop tablo --}}
                <div class="orders-table-shell hidden md:block rounded-[10px] border border-slate-200 p-3 shadow-sm">
                    @php
                        $columnMeta = [
                            'siparis' => ['label' => 'Sipariş No & Tarih', 'width' => '154px'],
                            'magaza' => ['label' => 'Mağaza', 'width' => '124px'],
                            'musteri' => ['label' => 'Müşteri', 'width' => '130px'],
                            'lojistik' => ['label' => 'Lojistik', 'width' => '124px'],
                            'ciro' => ['label' => 'Ciro', 'width' => '90px'],
                            'muhasebe' => ['label' => 'Ödeme', 'width' => '106px'],
                            'kar' => ['label' => 'Kârlılık', 'width' => '112px'],
                            'durum' => ['label' => 'Durum', 'width' => '92px'],
                        ];
                        $columnHelp = [
                            'ciro' => [
                                'title' => 'Ciro',
                                'summary' => 'Siparişin satış tarafındaki brüt gelirini ve satır adedini gösterir.',
                                'source' => 'Sipariş kalemleri ve sipariş toplamı.',
                                'refresh' => 'Sipariş güncellendiğinde veya sipariş kalemi değiştiğinde.',
                                'impact' => 'Kâr ve sepet büyüklüğü analizlerinde temel referanstır.',
                            ],
                            'muhasebe' => [
                                'title' => 'Ödeme Özeti',
                                'summary' => 'Siparişinizden beklediğiniz ödemenin, kesintilerin ve yatacak net tutarın özetidir.',
                                'source' => 'Sipariş, fatura, komisyon ve kargo verileri.',
                                'refresh' => 'Yeni finansal olay işlendiğinde.',
                                'impact' => 'Siparişin hesabınıza eksiksiz yatıp yatmadığını kontrol etmenizi sağlar.',
                            ],
                            'kar' => [
                                'title' => 'Kârlılık',
                                'summary' => 'Tüm kesintiler (kargo vb.) yapıldıktan sonra üründen kazandığınız net kâr oranıdır.',
                                'source' => 'Sipariş kalemleri, maliyet, kargo ve finans kesintileri.',
                                'refresh' => 'Maliyet veya finans verisi değiştiğinde.',
                                'impact' => 'Ürün maliyetine göre net kâr yüzdesini gösterir.',
                            ],
                        ];
                    @endphp

                    <div class="mb-3 flex flex-col gap-3 rounded-[8px] border border-slate-200 bg-white px-4 py-3 lg:flex-row lg:items-center lg:justify-between">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-900">Sipariş listesi</p>
                            <p class="mt-1 text-xs text-slate-500">Pazaryeri, müşteri, kargo ve finans bilgileri</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            @if(config('marketplace.features.bulk_order_actions_enabled', true) || config('marketplace.features.bulk_package_actions_enabled', true))
                                <div x-data="{ bulkOpen: false }" class="relative">
                                    <button @click="bulkOpen = !bulkOpen"
                                            type="button"
                                            class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-[6px] border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                        Toplu İşlem
                                        @if(count($selectedOrderIds) > 0 || count($selectedPackageIds) > 0)
                                            <span class="rounded-[6px] bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-600">
                                                {{ count($selectedOrderIds) + count($selectedPackageIds) }}
                                            </span>
                                        @endif
                                    </button>

                                    <div x-show="bulkOpen"
                                         @click.outside="bulkOpen = false"
                                         x-transition
                                         class="absolute right-0 top-full z-30 mt-2 w-80 rounded-[8px] border border-slate-200 bg-white p-4 shadow-xl">
                                        <p class="text-xs font-medium uppercase tracking-[0.16em] text-slate-500">Toplu İşlemler</p>

                                        <div class="mt-3 space-y-2">
                                            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">Belge çıktıları</p>
                                            @if($this->hasDocumentSelection())
                                                <a href="{{ $this->documentDownloadUrl('label') }}"
                                                   target="_blank"
                                                   @click="{{ $selectedLabelConfirmMessage ? "if (!window.confirm(" . \Illuminate\Support\Js::from($selectedLabelConfirmMessage) . ")) { \$event.preventDefault(); return; }" : '' }}"
                                                   class="flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                                    Kargo etiketi PDF indir
                                                </a>
                                                @if($selectedLabelPrintSummary['has_printed'])
                                                    <p class="text-[11px] text-amber-600">{{ $selectedLabelPrintSummary['badge_label'] }}. Tekrar indirmeden önce kontrol edin.</p>
                                                @endif
                                                <a href="{{ $this->documentDownloadUrl('dispatch') }}"
                                                   target="_blank"
                                                   class="flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                                    İrsaliye PDF indir
                                                </a>
                                            @else
                                                <div class="rounded-[6px] border border-dashed border-slate-200 bg-slate-50 px-3 py-3 text-xs text-slate-500">
                                                    Belge çıktısı almak için en az bir sipariş veya paket seçin.
                                                </div>
                                            @endif
                                        </div>

                                        @if(config('marketplace.features.bulk_order_actions_enabled', true))
                                            <div class="mt-3 space-y-2 border-t border-slate-200 pt-3">
                                                <select wire:model.live="bulkActionType"
                                                        class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm">
                                                    <option value="">Sipariş aksiyonu seçin</option>
                                                    @foreach($this->bulkActionOptions() as $actionValue => $actionLabel)
                                                        <option value="{{ $actionValue }}">{{ $actionLabel }}</option>
                                                    @endforeach
                                                </select>
                                                <button type="button"
                                                        wire:click="runBulkOrderAction"
                                                        wire:loading.attr="disabled"
                                                        class="w-full rounded-[6px] bg-slate-900 px-3 py-2 text-sm font-medium text-white transition hover:bg-slate-800 disabled:opacity-60">
                                                    Kuyruğa al
                                                </button>
                                            </div>
                                        @endif

                                        @if(config('marketplace.features.bulk_package_actions_enabled', true))
                                            <div class="mt-3 space-y-2 border-t border-slate-200 pt-3">
                                                <select wire:model.live="bulkPackageActionType"
                                                        class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm">
                                                    <option value="">Paket aksiyonu seçin</option>
                                                    @foreach($this->bulkPackageActionOptions() as $actionValue => $actionLabel)
                                                        <option value="{{ $actionValue }}">{{ $actionLabel }}</option>
                                                    @endforeach
                                                </select>
                                                <button type="button"
                                                        wire:click="runBulkPackageAction"
                                                        wire:loading.attr="disabled"
                                                        class="w-full rounded-[6px] bg-slate-900 px-3 py-2 text-sm font-medium text-white transition hover:bg-slate-800 disabled:opacity-60">
                                                    Kuyruğa al
                                                </button>
                                            </div>
                                        @endif

                                        <button type="button"
                                                wire:click="clearSelection"
                                                class="mt-3 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                            Seçimleri temizle
                                        </button>
                                    </div>
                                </div>
                            @endif

                        </div>
                    </div>

                    <div class="overflow-x-auto w-full pb-2 [scrollbar-gutter:stable]" x-data="columnResize()">
                        <div class="mb-2 flex items-center justify-between gap-3 rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-xs text-slate-500 xl:hidden">
                            <span>Tablo sığmazsa sağa kaydırabilirsiniz.</span>
                            <span class="font-medium text-slate-600">{{ count($visibleColumns) }}/{{ count($columnDefs) }} kolon açık</span>
                        </div>
                        <table class="orders-v2-table overflow-hidden rounded-[8px] divide-y divide-slate-200 table-fixed bg-white shadow-sm" style="min-width: 860px;">
                            <thead class="bg-slate-50/90 text-slate-500">
                                <tr>
                                    <th class="w-14 px-2.5 py-2.5 text-left text-xs font-semibold uppercase tracking-[0.16em]">
                                        <label class="inline-flex items-center gap-2 text-[11px] text-slate-500">
                                            <input type="checkbox"
                                                   wire:model.live="selectPage"
                                                   class="rounded border-slate-300 text-slate-900 shadow-sm focus:ring-slate-200">
                                            <span>Seç</span>
                                        </label>
                                    </th>
                                    @foreach($columnMeta as $columnKey => $meta)
                                        @if(in_array($columnKey, $visibleColumns, true))
                                            <th class="sortable-th relative px-2.5 py-2.5 text-left text-[11px] font-semibold uppercase tracking-[0.14em]"
                                                style="width: {{ $meta['width'] }}; min-width: {{ $meta['width'] }};"
                                                wire:click="sortTable('{{ $columnKey }}')">
                                                <div class="flex items-center justify-between gap-2">
                                                    <div class="flex items-center gap-1.5 min-w-0">
                                                        <span>{{ $meta['label'] }}</span>
                                                        @if(isset($columnHelp[$columnKey]))
                                                            <x-zolm.help-tip
                                                                :title="$columnHelp[$columnKey]['title']"
                                                                :summary="$columnHelp[$columnKey]['summary']"
                                                                :source="$columnHelp[$columnKey]['source']"
                                                                :refresh="$columnHelp[$columnKey]['refresh']"
                                                                :impact="$columnHelp[$columnKey]['impact']"
                                                            />
                                                        @endif
                                                    </div>
                                                    <span class="text-[10px] {{ ($sortableColumns[$columnKey] ?? null) === $sortField ? 'text-slate-700' : 'text-slate-300' }}">{{ $sortIcon($columnKey) }}</span>
                                                </div>
                                                <span class="col-resize-handle" @mousedown.prevent="startResize($event, $el.parentElement)"></span>
                                            </th>
                                        @endif
                                    @endforeach
                                    <th class="w-[60px] px-1.5 py-2.5 text-right text-[11px] font-semibold uppercase tracking-[0.14em]">
                                        Aksiyon
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white text-sm text-slate-700">
                                @foreach($orders as $order)
                                    @php
                                        $snapshot = data_get($order, 'order_snapshot');
                                        $package = data_get($order, 'package_summary');
                                        $legacyOrder = data_get($order, 'legacy_operational_order');
                                        $legacyHasFinancial = $legacyOrder?->financialOrders?->isNotEmpty() ?? false;
                                        $profitState = $order->profit_state_metric ?? ($snapshot?->profit_state ?: 'estimated');
                                        $profitValue = (float) ($order->profit_value_metric
                                            ?? ($profitState === 'confirmed' ? $snapshot?->confirmed_profit : $snapshot?->estimated_profit));
                                        $grossRevenue = (float) ($order->gross_revenue_metric ?? $snapshot?->gross_revenue);
                                        $netReceivable = (float) ($order->net_receivable_metric ?? $snapshot?->net_receivable ?? 0);
                                        $matchedLines = (int) ($order->matched_lines_count ?? 0);
                                        $itemLines = (int) ($order->item_lines_count ?? 0);
                                        $lineCount = $legacyOrder?->items?->count() ?: $itemLines;
                                        $previewItems = ($order->items ?? collect())->take(3);
                                        $remainingPreviewItemCount = max(0, $itemLines - $previewItems->count());
                                        $matchSummaryLabel = $itemLines > 0
                                            ? "{$matchedLines}/{$itemLines} satır eşleşti"
                                            : 'Satır bilgisi bekleniyor';
                                        $matchSummaryClass = $itemLines > 0 && $matchedLines === $itemLines
                                            ? 'text-emerald-600'
                                            : ($matchedLines > 0 ? 'text-amber-600' : 'text-rose-500');
                                        $discountTotal = $legacyOrder
                                            ? (float) ($legacyOrder->total_discount ?? ($order->total_discount_amount ?? 0))
                                            : (float) ($order->total_discount_amount ?? 0);
                                        $locationCity = $legacyOrder?->customer_city ?: $order->shipment_city;
                                        $locationDistrict = $legacyOrder?->customer_district ?: $order->shipment_district;
                                        $cargoCompany = $this->displayCargoCompany(
                                            $package?->cargo_company,
                                            $package?->shipment_provider,
                                            $legacyOrder?->cargo_company,
                                        );
                                        $trackingNumber = $package?->cargo_tracking_number ?: ($legacyOrder?->tracking_number ?: null);
                                        $cargoTrackingUrl = $this->trackingUrl($cargoCompany, $trackingNumber);
                                        $cargoDate = $this->packageShipmentAt($package, $order->marketplace_alias) ?: ($legacyOrder?->cargo_delivery_date ?: null);
                                        $cargoDueDate = $this->displayCargoDueDate($order);
                                        $labelPrintSummary = $this->orderLabelPrintSummary($order);
                                        $financialAlert = $legacyHasFinancial ? $legacyOrder->financial_alert : ['type' => null, 'label' => null, 'color' => null];
                                        $estimatedCogs = (float) (
                                            ($order->display_cogs_cost_metric ?? $snapshot?->cogs_cost ?? 0)
                                            + ($order->display_packaging_cost_metric ?? $snapshot?->packaging_cost ?? 0)
                                        );
                                        $marketplaceCargoImpact = (float) ($snapshot?->cargo_total ?? $order->cargo_total_metric ?? 0);
                                        $ownCargoImpact = (float) ($order->display_own_cargo_cost_metric ?? $snapshot?->own_cargo_cost ?? 0);
                                        $estimatedCargo = $marketplaceCargoImpact + $ownCargoImpact;
                                        $commissionImpact = (float) ($snapshot?->commission_total ?? $order->commission_total_metric ?? 0);
                                        $serviceFeeImpact = (float) ($snapshot?->service_fee_total ?? $order->service_fee_total_metric ?? 0);
                                        $withholdingImpact = (float) ($snapshot?->withholding_total ?? $order->withholding_total_metric ?? 0);
                                        $vatImpact = (float) ($snapshot?->vat_effect ?? 0);
                                        $isConfirmedProfit = $profitState === 'confirmed';
                                        $receivableBase = $grossRevenue;
                                        $receivableBaseLabel = $isConfirmedProfit ? 'Brüt satış / finans bazı' : 'Tahmini satış';
                                        $receivableRows = array_values(array_filter([
                                            ['label' => $receivableBaseLabel, 'value' => $formatMoney($receivableBase), 'tone' => 'default'],
                                            $commissionImpact > 0 ? ['label' => 'Komisyon', 'value' => $formatSignedMoney(-1 * $commissionImpact), 'tone' => 'danger'] : null,
                                            $marketplaceCargoImpact > 0 ? ['label' => 'Pazaryeri kargo kesintisi', 'value' => $formatSignedMoney(-1 * $marketplaceCargoImpact), 'tone' => 'warning'] : null,
                                            $serviceFeeImpact > 0 ? ['label' => 'Platform hizmet bedeli ve diğer', 'value' => $formatSignedMoney(-1 * $serviceFeeImpact), 'tone' => 'danger'] : null,
                                            $withholdingImpact > 0 ? ['label' => 'Stopaj', 'value' => $formatSignedMoney(-1 * $withholdingImpact), 'tone' => 'danger'] : null,
                                        ]));
                                        $receivableFormula = 'Net ödeme = Brüt satış - komisyon - pazaryeri kargosu - hizmet bedeli - stopaj';
                                        $profitRows = array_values(array_filter([
                                            ['label' => 'Ciro', 'value' => $formatMoney($grossRevenue), 'tone' => 'default'],
                                            $commissionImpact > 0 ? ['label' => $isConfirmedProfit ? 'Komisyon' : 'Tahmini komisyon', 'value' => $formatSignedMoney(-1 * $commissionImpact), 'tone' => 'danger'] : null,
                                            $serviceFeeImpact > 0 ? ['label' => $isConfirmedProfit ? 'Hizmet bedeli ve diğer' : 'Tahmini hizmet bedeli', 'value' => $formatSignedMoney(-1 * $serviceFeeImpact), 'tone' => 'danger'] : null,
                                            $withholdingImpact > 0 ? ['label' => $isConfirmedProfit ? 'E-ticaret stopajı' : 'Tahmini e-ticaret stopajı', 'value' => $formatSignedMoney(-1 * $withholdingImpact), 'tone' => 'danger'] : null,
                                            $marketplaceCargoImpact > 0 ? ['label' => 'Pazaryeri kargo kesintisi', 'value' => $formatSignedMoney(-1 * $marketplaceCargoImpact), 'tone' => 'warning'] : null,
                                            $estimatedCogs > 0 ? ['label' => 'Maliyet + ambalaj', 'value' => $formatSignedMoney(-1 * $estimatedCogs), 'tone' => 'danger'] : null,
                                            $ownCargoImpact > 0 ? ['label' => 'Kendi kargo maliyeti', 'value' => $formatSignedMoney(-1 * $ownCargoImpact), 'tone' => 'warning'] : null,
                                            $vatImpact > 0 ? ['label' => 'Net KDV etkisi', 'value' => $formatSignedMoney(-1 * $vatImpact), 'tone' => 'danger'] : null,
                                        ]));
                                        $profitFormula = 'Nakit net kâr = Ciro - komisyon - hizmet bedeli - stopaj - kargo - maliyet - ambalaj - net KDV';
                                        $costProfitPercent = $estimatedCogs > 0
                                            ? \App\Services\ProfitabilityMetric::profitPercent($profitValue, $estimatedCogs)
                                            : null;
                                        $latestActionRun = $order->actionRuns->sortByDesc('created_at')->first();
                                        $orderColorLabel = $orderLabelDefinitions[$order->color_label_key] ?? null;
                                        $statusKey = (string) ($order->display_status_key ?? '');
                                        $statusLabel = (string) ($order->display_status_label ?? $this->humanStatus($order->order_status, $order->marketplace_alias, $trackingNumber, $order->delivered_at));
                                        $statusTone = (string) ($order->display_status_tone ?? $this->statusTone($order->order_status, $order->marketplace_alias, $trackingNumber, $order->delivered_at));
                                        $isCancelled = in_array($statusKey, ['cancelled', 'returned', 'rejected'], true);
                                    @endphp

                                    <tr class="transition hover:bg-slate-50/80 {{ $isCancelled ? 'opacity-50 grayscale hover:opacity-75' : '' }}">
                                        <td class="px-2.5 py-3.5 align-top">
                                            <div class="flex items-center gap-2">
                                                <input type="checkbox"
                                                       wire:model.live="selectedOrderIds"
                                                       value="{{ $order->id }}"
                                                       class="rounded border-slate-300 text-slate-900 shadow-sm focus:ring-slate-200">
                                                <button type="button"
                                                        class="inline-flex h-8 w-8 items-center justify-center rounded-[6px] border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50 hover:text-slate-900"
                                                        @click="expanded.includes({{ $order->id }}) ? expanded = expanded.filter(i => i !== {{ $order->id }}) : expanded.push({{ $order->id }})">
                                                    <svg class="h-4 w-4 transition"
                                                         :class="{ 'rotate-90': expanded.includes({{ $order->id }}) }"
                                                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </td>

                                        @if(in_array('siparis', $visibleColumns, true))
                                            <td class="px-2.5 py-3.5 align-top">
                                                <div class="font-semibold text-slate-900">{{ $order->order_number }}</div>
                                                <div class="mt-1 text-xs text-slate-500">{{ $this->displayOrderDate($order)?->format('d.m.Y H:i') ?: '-' }}</div>
                                                @if($orderColorLabel)
                                                    <div class="mt-2 inline-flex max-w-full items-center gap-1.5 rounded-[6px] border px-2 py-0.5 text-[10px] font-semibold"
                                                         style="background-color: {{ $orderColorLabel['bg_color'] }}; border-color: {{ $orderColorLabel['border_color'] }}; color: {{ $orderColorLabel['color'] }};">
                                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full" style="background-color: {{ $orderColorLabel['color'] }};"></span>
                                                        <span class="truncate">{{ $orderColorLabel['name'] }}</span>
                                                    </div>
                                                @endif
                                                @if($package?->package_number)
                                                    <div class="mt-1 text-[11px] font-mono text-slate-400">PKT {{ $package->package_number }}</div>
                                                @endif
                                                @if(!in_array('magaza', $visibleColumns, true))
                                                    <div class="mt-2 truncate text-[11px] text-slate-400" title="{{ $order->store_name_alias }} · {{ $this->humanMarketplace($order->marketplace_alias) }}">
                                                        {{ $order->store_name_alias }}
                                                    </div>
                                                @endif
                                            </td>
                                        @endif

                                        @if(in_array('magaza', $visibleColumns, true))
                                            <td class="px-2.5 py-3.5 align-top">
                                                <div class="font-medium text-slate-900">{{ $order->store_name_alias }}</div>
                                                <div class="mt-1 text-xs text-slate-500">{{ $this->humanMarketplace($order->marketplace_alias) }}</div>
                                                <div class="mt-2 text-[11px] text-slate-400">{{ $order->legal_entity_name_alias }}</div>
                                            </td>
                                        @endif

                                        @if(in_array('musteri', $visibleColumns, true))
                                            <td class="px-2.5 py-3.5 align-top">
                                                <div class="font-medium text-slate-900">{{ $order->customer_name ?: 'Müşteri bilgisi yok' }}</div>
                                                <div class="mt-1 text-xs text-slate-500 truncate">{{ $locationCity ?: '-' }}{{ $locationDistrict ? ', ' . $locationDistrict : '' }}</div>
                                                @if($legacyOrder?->is_corporate_invoice === 'Evet')
                                                    <span class="mt-2 inline-flex rounded-[6px] border border-sky-200 bg-sky-50 px-2 py-0.5 text-[10px] font-semibold text-sky-700">
                                                        Kurumsal
                                                    </span>
                                                @endif
                                            </td>
                                        @endif

                                        @if(in_array('lojistik', $visibleColumns, true))
                                            <td class="px-2.5 py-3.5 align-top">
                                                <div class="font-medium text-slate-900">{{ $cargoCompany ?: 'Kargo bilgisi bekleniyor' }}</div>
                                                <div class="mt-1 text-xs text-slate-500 truncate">
                                                    @if($trackingNumber)
                                                        @if($cargoTrackingUrl)
                                                            <a href="{{ $cargoTrackingUrl }}"
                                                               target="_blank"
                                                               rel="noopener noreferrer"
                                                               class="font-medium text-sky-600 transition hover:text-sky-700 hover:underline">
                                                                {{ $trackingNumber }}
                                                            </a>
                                                        @else
                                                            {{ $trackingNumber }}
                                                        @endif
                                                    @else
                                                        Takip no yok
                                                    @endif
                                                </div>
                                                @if($cargoDate)
                                                    <div class="mt-1 text-[11px] text-slate-400">
                                                        {{ $this->shipmentDateShortLabel($order->marketplace_alias, $package?->package_status, $trackingNumber, $package?->delivered_at, $package?->raw_payload) }}:
                                                        {{ $cargoDate->format('d/m H:i') }}
                                                    </div>
                                                @endif
                                                @if($cargoDueDate)
                                                    <div class="mt-1 text-[11px] text-slate-400">
                                                        Son teslim: {{ $cargoDueDate->format('d/m H:i') }}
                                                    </div>
                                                @endif
                                                @if($labelPrintSummary['has_printed'])
                                                    <div class="mt-2 inline-flex max-w-full items-center gap-1.5 rounded-[6px] border border-emerald-200 bg-emerald-50 px-2 py-1 text-[10px] font-semibold text-emerald-700">
                                                        <svg class="h-3 w-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                        </svg>
                                                        <span class="truncate">{{ $labelPrintSummary['badge_label'] }}</span>
                                                    </div>
                                                    @if($labelPrintSummary['meta_label'])
                                                        <div class="mt-1 text-[10px] text-emerald-700/80">{{ $labelPrintSummary['meta_label'] }}</div>
                                                    @endif
                                                @endif
                                            </td>
                                        @endif

                                        @if(in_array('ciro', $visibleColumns, true))
                                            <td class="order-metric-cell px-2.5 py-3.5 align-top text-right">
                                                <div class="font-semibold text-slate-900">{{ $formatMoney($grossRevenue) }}</div>
                                                <div
                                                    x-data="{
                                                        open: false,
                                                        touchMode: window.matchMedia('(hover: none)').matches,
                                                        show() {
                                                            if (!this.touchMode) this.open = true;
                                                        },
                                                        hide() {
                                                            if (!this.touchMode) this.open = false;
                                                        },
                                                        toggle() {
                                                            this.open = !this.open;
                                                        },
                                                    }"
                                                    class="relative mt-1 inline-flex items-center justify-end"
                                                    @mouseenter="show()"
                                                    @mouseleave="hide()"
                                                >
                                                    <button type="button"
                                                            class="inline-flex items-center gap-1 text-xs font-medium text-indigo-600 transition hover:text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                                            @click.prevent.stop="toggle()"
                                                            @focus="open = true"
                                                            @keydown.escape.stop="open = false">
                                                        <span>{{ $formatCount($lineCount) }} Ürün</span>
                                                        <svg class="h-3 w-3 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                        </svg>
                                                    </button>

                                                    <div x-cloak
                                                         x-show="open"
                                                         x-transition.opacity.scale.origin.top.right
                                                         @click.outside="open = false"
                                                         class="absolute right-0 top-full z-[90] mt-2 w-[26rem] max-w-[min(26rem,calc(100vw-2rem))]">
                                                        <div class="rounded-[10px] border border-slate-200 bg-white p-3 text-left shadow-xl shadow-slate-900/10">
                                                            <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-3">
                                                                <div class="min-w-0">
                                                                    <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-400">Hızlı ürün özeti</p>
                                                                    <p class="mt-1 text-sm font-semibold text-slate-900">Detay açmadan satırları hızlıca inceleyin</p>
                                                                    <p class="mt-1 text-[11px] leading-5 {{ $matchSummaryClass }}">
                                                                        {{ $matchedLines === $itemLines && $itemLines > 0
                                                                            ? 'Tüm satırlar master ürüne bağlı.'
                                                                            : ($itemLines > 0
                                                                                ? ($itemLines - $matchedLines) . ' satır kontrol bekliyor.'
                                                                                : 'Satır verisi gelmedi.') }}
                                                                    </p>
                                                                </div>
                                                                <div class="rounded-[8px] border border-slate-200 bg-slate-50 px-2.5 py-2 text-right">
                                                                    <p class="text-[10px] uppercase tracking-[0.16em] text-slate-400">Satır / eşleşme</p>
                                                                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ $matchedLines }}/{{ $itemLines }}</p>
                                                                </div>
                                                            </div>

                                                            <div class="mt-3 space-y-2">
                                                                @forelse($previewItems as $previewItem)
                                                                    @php
                                                                        $previewMatched = (bool) $previewItem->is_matched;
                                                                        $previewBillable = (float) ($previewItem->billable_amount ?: $previewItem->gross_amount);
                                                                        $previewMasterName = $previewItem->product?->product_name ?: $previewItem->product?->stock_code;
                                                                        $previewPublicUrl = $this->marketplacePublicProductUrlForOrderItem($previewItem, $order);
                                                                    @endphp
                                                                    <div class="rounded-[8px] border px-3 py-2.5 {{ $previewMatched ? 'border-emerald-200 bg-emerald-50/60' : 'border-amber-200 bg-amber-50/70' }}">
                                                                        <div class="flex items-start justify-between gap-3">
                                                                            <div class="min-w-0">
                                                                                @if($previewPublicUrl)
                                                                                    <a href="{{ $previewPublicUrl }}"
                                                                                       target="_blank"
                                                                                       rel="noopener noreferrer"
                                                                                       class="block truncate text-sm font-medium text-slate-900 underline-offset-2 transition hover:text-slate-700 hover:underline"
                                                                                       title="Pazaryerinde aç">
                                                                                        {{ \Illuminate\Support\Str::limit($previewItem->product_name ?: 'Ürün adı yok', 54) }}
                                                                                    </a>
                                                                                @else
                                                                                    <p class="truncate text-sm font-medium text-slate-900">
                                                                                        {{ \Illuminate\Support\Str::limit($previewItem->product_name ?: 'Ürün adı yok', 54) }}
                                                                                    </p>
                                                                                @endif
                                                                                <div class="mt-1 flex flex-wrap items-center gap-2 text-[11px] text-slate-500">
                                                                                    <span>{{ (int) ($previewItem->quantity ?? 0) }} adet</span>
                                                                                    <span class="text-slate-300">•</span>
                                                                                    <span class="font-mono">{{ $previewItem->stock_code ?: ($previewItem->barcode ?: '-') }}</span>
                                                                                </div>
                                                                            </div>
                                                                            <div class="shrink-0 text-right">
                                                                                <p class="text-sm font-semibold text-slate-900">{{ $formatMoney($previewBillable) }}</p>
                                                                                <span class="mt-1 inline-flex rounded-[6px] border px-2 py-0.5 text-[10px] font-semibold {{ $previewMatched ? 'border-emerald-200 bg-white text-emerald-700' : 'border-amber-200 bg-white text-amber-700' }}">
                                                                                    {{ $previewMatched ? 'Eşleşti' : 'Kontrol gerekli' }}
                                                                                </span>
                                                                            </div>
                                                                        </div>

                                                                        <div class="mt-2 rounded-[6px] border border-white/80 bg-white/80 px-2.5 py-2 text-[11px] leading-5 text-slate-600">
                                                                            @if($previewMatched)
                                                                                <p class="font-medium text-emerald-700">Master ürün bağlı</p>
                                                                                <p class="mt-0.5">
                                                                                    {{ $previewMasterName ?: 'İç ürün kartı bağlı' }}
                                                                                    @if(filled($previewItem->match_source))
                                                                                        · {{ ucfirst((string) $previewItem->match_source) }}
                                                                                    @endif
                                                                                </p>
                                                                            @else
                                                                                <p class="font-medium text-amber-700">Master ürün bağlantısı bekleniyor</p>
                                                                                <p class="mt-0.5">Bu satır eşleşmeden maliyet ve kârlılık hesabı sınırlı kalır.</p>
                                                                            @endif
                                                                        </div>
                                                                    </div>
                                                                @empty
                                                                    <div class="rounded-[8px] border border-slate-200 bg-slate-50 px-3 py-3 text-[11px] leading-5 text-slate-500">
                                                                        Satır detayları henüz yüklenmedi. Siparişi açınca tam içerik görünecek.
                                                                    </div>
                                                                @endforelse
                                                            </div>

                                                            @if($remainingPreviewItemCount > 0)
                                                                <div class="mt-3 rounded-[8px] border border-slate-200 bg-slate-50 px-3 py-2 text-[11px] text-slate-500">
                                                                    +{{ $remainingPreviewItemCount }} satır daha var. Tam liste için sipariş detayını açabilirsiniz.
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="mt-1 text-[11px] {{ $matchSummaryClass }}">{{ $matchSummaryLabel }}</div>
                                                @if($discountTotal > 0)
                                                    <div class="mt-1 text-[11px] text-rose-500">İnd. {{ $formatMoney($discountTotal) }}</div>
                                                @endif
                                            </td>
                                        @endif

                                        @if(in_array('muhasebe', $visibleColumns, true))
                                            <td class="order-metric-cell px-2.5 py-3.5 align-top text-right">
                                                @if($netReceivable > 0 || $legacyHasFinancial || (int) ($order->financial_event_count ?? 0) > 0 || $snapshot)
                                                    <x-zolm.metric-breakdown
                                                        title="Hakediş hesabı"
                                                        :subtitle="$isConfirmedProfit
                                                            ? 'Kesinleşen finans hareketleri, eksik kalemlerde kanonik tahmin politikasıyla tamamlanır.'
                                                            : 'Finans hareketleri gelene kadar kanonik tahmin sözleşmesi kullanılır.'"
                                                        :rows="$receivableRows"
                                                        result-label="Net Ödeme"
                                                        :result-value="$formatMoney($netReceivable)"
                                                        result-tone="default"
                                                        :formulas="[
                                                            $receivableFormula,
                                                            $formatMoney($netReceivable) . ' = ' . collect($receivableRows)->pluck('value')->join(' ')
                                                        ]"
                                                        :note="$isConfirmedProfit
                                                            ? 'Kârlılık değeri ayrı kârlılık kolonunda gösterilir.'
                                                            : 'Finans olayları tamamlandığında hakediş dökümü netleşir. Kârlılık değeri ayrı kârlılık kolonunda gösterilir.'"
                                                    >
                                                        <div class="text-xs text-slate-500">Hakediş</div>
                                                        <div class="font-semibold text-slate-900">{{ $formatMoney($netReceivable) }}</div>
                                                        @if(filled($financialAlert['label'] ?? null))
                                                            <span class="mt-2 inline-flex rounded-[6px] border px-2 py-0.5 text-[10px] font-semibold {{ $financialAlert['color'] }}">
                                                                {{ $financialAlert['label'] }}
                                                            </span>
                                                        @else
                                                            <div class="mt-1 text-[10px] text-slate-400">
                                                                {{ (int) ($order->financial_event_count ?? 0) > 0 ? 'Finans akışı hazır' : 'Tahmini görünüm' }}
                                                            </div>
                                                        @endif
                                                    </x-zolm.metric-breakdown>
                                                @else
                                                    <span class="text-xs italic text-slate-400">Veri yok</span>
                                                @endif
                                            </td>
                                        @endif

                                        @if(in_array('kar', $visibleColumns, true))
                                            <td class="order-metric-cell px-2.5 py-3.5 align-top text-right">
                                                <x-zolm.metric-breakdown
                                                    title="Kârlılık hesabı"
                                                    subtitle="Tüm ZOLM ekranlarında aynı kanonik nakit net kâr sözleşmesi kullanılır; hizmet bedeli ve e-ticaret stopajı hesaba dahildir."
                                                    :rows="$profitRows"
                                                    result-label="Nakit net kâr"
                                                    :result-value="$formatSignedMoney($profitValue)"
                                                    :result-tone="$profitValue >= 0 ? 'success' : 'danger'"
                                                    :formulas="array_values(array_filter([
                                                        $profitFormula,
                                                        'Maliyet getirisi = Nakit net kâr / (ürün maliyeti + ambalaj)',
                                                        $costProfitPercent !== null ? $formatProfitabilityPercent($costProfitPercent) . ' = ' . $formatMoney($profitValue) . ' / ' . $formatMoney($estimatedCogs) : null,
                                                    ]))"
                                                    note="Gösterilen yüzde satış marjı değil, ürün maliyeti ve ambalaja göre nakit net kâr getirisidir."
                                                >
                                                    <div class="text-[10px] text-slate-500">Maliyet {{ $formatMoney($estimatedCogs) }}</div>
                                                    <div class="mt-1 text-[10px] text-rose-500">Kom. {{ $formatMoney($commissionImpact) }}</div>
                                                    @if($estimatedCargo > 0)
                                                        <div class="mt-1 text-[10px] text-amber-600">Kargo {{ $formatMoney($estimatedCargo) }}</div>
                                                    @endif
                                                    @if($costProfitPercent !== null)
                                                        <div class="mt-2 font-semibold {{ $profitValue >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                                                            {{ $formatProfitabilityPercent($costProfitPercent) }}
                                                        </div>
                                                    @else
                                                        <div class="mt-2 text-xs font-medium text-amber-600">Maliyet yok</div>
                                                    @endif
                                                    <div class="mt-1 text-xs {{ $profitValue >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ $formatSignedMoney($profitValue) }}</div>
                                                </x-zolm.metric-breakdown>
                                            </td>
                                        @endif

                                        @if(in_array('durum', $visibleColumns, true))
                                            <td class="px-2.5 py-3.5 align-top">
                                                <x-zolm.status-badge size="xs" :tone="$statusTone">
                                                    {{ $statusLabel }}
                                                </x-zolm.status-badge>
                                                @if($orderColorLabel && !in_array('siparis', $visibleColumns, true))
                                                    <div class="mt-2 inline-flex max-w-full items-center gap-1.5 rounded-[6px] border px-2 py-0.5 text-[10px] font-semibold"
                                                         style="background-color: {{ $orderColorLabel['bg_color'] }}; border-color: {{ $orderColorLabel['border_color'] }}; color: {{ $orderColorLabel['color'] }};">
                                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full" style="background-color: {{ $orderColorLabel['color'] }};"></span>
                                                        <span class="truncate">{{ $orderColorLabel['name'] }}</span>
                                                    </div>
                                                @endif
                                                <div class="mt-2 text-[10px] text-slate-500">
                                                    {{ (int) ($order->financial_event_count ?? 0) > 0 ? 'Finans geldi' : 'Finans bekleniyor' }}
                                                </div>
                                            </td>
                                        @endif

                                        <td class="order-actions-cell px-1.5 py-3.5 align-top">
                                            <div class="flex justify-end">
                                                @include('livewire.partials.mp-order-row-actions', ['order' => $order, 'latestActionRun' => $latestActionRun, 'align' => 'right'])
                                            </div>
                                        </td>
                                    </tr>

                                    <tr x-show="expanded.includes({{ $order->id }})" x-cloak x-transition class="expanded-row bg-slate-50/60">
                                        <td colspan="{{ count($visibleColumns) + 2 }}" class="p-0">
                                            <div class="px-3 py-4 lg:px-4 w-full">
                                                @include('livewire.partials.marketplace-order-v2-detail', ['order' => $order])
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Pagination --}}
                @include('livewire.partials.mp-pagination-bar', ['paginator' => $orders])
            @endif
        </div>
    </section>

    @include('livewire.partials.mp-order-edit-modal')
    @include('livewire.partials.mp-order-label-manager-modal')
</div>
