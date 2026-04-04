@php
    $formatMoney = fn ($value) => '₺' . number_format((float) $value, 2, ',', '.');
    $formatSignedMoney = fn ($value) => ((float) $value >= 0 ? '+' : '-') . '₺' . number_format(abs((float) $value), 2, ',', '.');
    $formatCount = fn ($value) => number_format((float) $value, 0, ',', '.');
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

<div class="orders-page-shell -mt-1 lg:-mt-4 w-full space-y-5 lg:space-y-6"
     x-data="{
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

            .orders-hero-card {
                background:
                    radial-gradient(circle at top left, rgba(59, 130, 246, 0.12), transparent 28%),
                    radial-gradient(circle at top right, rgba(15, 23, 42, 0.08), transparent 30%),
                    linear-gradient(180deg, rgba(248, 250, 252, 0.92), rgba(255, 255, 255, 1));
            }

            .orders-hero-lead {
                background:
                    radial-gradient(circle at top right, rgba(96, 165, 250, 0.12), transparent 26%),
                    linear-gradient(180deg, rgba(255, 255, 255, 1), rgba(248, 250, 252, 0.92));
            }

            .orders-control-surface {
                background:
                    linear-gradient(180deg, rgba(248, 250, 252, 0.95), rgba(255, 255, 255, 1));
            }

            .orders-tool-rail {
                background:
                    radial-gradient(circle at top right, rgba(96, 165, 250, 0.12), transparent 28%),
                    linear-gradient(180deg, rgba(248, 250, 252, 0.96), rgba(255, 255, 255, 1));
            }

            .orders-stat-card {
                background: linear-gradient(180deg, #ffffff, #f8fafc);
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

    {{-- ═══════════════════════════════════════════════ --}}
    {{-- BLOK 1: HERO + KPI --}}
    {{-- ═══════════════════════════════════════════════ --}}
    @php
        $readyOrders = max(0, (int) ($stats['total_orders'] ?? 0) - (int) ($stats['finance_waiting_orders'] ?? 0));
        $averageBasket = (float) (($stats['total_orders'] ?? 0) > 0 ? $stats['total_revenue'] / $stats['total_orders'] : 0);
        $readyRatio = (int) round(($stats['total_orders'] ?? 0) > 0 ? ($readyOrders / max(1, (int) $stats['total_orders'])) * 100 : 0);
    @endphp
    <div class="orders-collapse-panel space-y-4 overflow-hidden"
         x-bind:style="workspaceCollapsed ? 'max-height: 0px; opacity: 0; transform: translateY(-10px); margin-bottom: 0; pointer-events: none;' : 'max-height: 4200px; opacity: 1; transform: translateY(0); pointer-events: auto;'">
    <section class="orders-hero-card rounded-[10px] border border-slate-200/90 p-4 shadow-sm lg:p-5">
        <div class="grid grid-cols-1 gap-3 xl:grid-cols-[minmax(420px,1.08fr)_minmax(0,0.92fr)]">
            <div class="orders-hero-lead rounded-[10px] border border-slate-200 p-4 shadow-sm lg:p-6">
                <div class="grid gap-3 xl:grid-cols-[minmax(0,1fr)_220px]">
                    <div class="min-w-0">
                        <div class="inline-flex items-center rounded-[6px] border border-slate-200 bg-slate-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">
                            Sipariş Çalışma Alanı
                        </div>
                        <h1 class="mt-4 text-3xl font-bold leading-none tracking-tight text-slate-950 lg:mt-5 lg:text-[40px]">Sipariş Yönetimi</h1>
                        <p class="mt-3 max-w-xl text-sm leading-6 text-slate-500">
                            Operasyon, muhasebe ve kârlılık akışını aynı workspace içinde yönetin. Venture’daki temiz SaaS kabuğunu korurken tablo yoğunluğunu düşürmeden çalışıyoruz.
                        </p>

                        <div class="mt-5 flex flex-col gap-3 sm:flex-row">
                            <button type="button"
                                    wire:click="exportCsv"
                                    wire:loading.attr="disabled"
                                    class="inline-flex min-h-[48px] items-center justify-center gap-2 rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">
                                <svg class="h-4 w-4 text-slate-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                Dışa Aktar
                            </button>
                            <a href="{{ route('mp.integrations') }}"
                               class="inline-flex min-h-[48px] items-center justify-center gap-2 rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                Finansal Senkronizasyon
                            </a>
                        </div>

                        <div class="mt-4 grid grid-cols-1 gap-2 text-xs sm:grid-cols-3 lg:mt-5">
                            <div class="rounded-[8px] border border-slate-200 bg-slate-50/80 px-3 py-3">
                                <p class="text-slate-500">Görünüm</p>
                                <p class="mt-1 font-semibold text-slate-900">Yoğun operasyon tablosu</p>
                            </div>
                            <div class="rounded-[8px] border border-slate-200 bg-slate-50/80 px-3 py-3">
                                <p class="text-slate-500">Kontrol yüzeyi</p>
                                <p class="mt-1 font-semibold text-slate-900">Filtre + araçlar birleşik</p>
                            </div>
                            <div class="rounded-[8px] border border-slate-200 bg-slate-50/80 px-3 py-3">
                                <p class="text-slate-500">Detay paneli</p>
                                <p class="mt-1 font-semibold text-slate-900">Eski veri korunuyor</p>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-3">
                        <div class="rounded-[10px] border border-slate-200 bg-white px-4 py-4">
                            <div class="flex items-center gap-1.5">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Hazır olma oranı</p>
                                <x-zolm.help-tip title="Hazır olma oranı" summary="Finans ve operasyon akışı tamamlanmış siparişlerin toplam siparişe oranını gösterir." source="Sipariş statüleri, finans bekleyen durumu ve operasyon tamamlama işaretleri." refresh="Sipariş veya finans durumu değiştiğinde." impact="Tabloya inmeden önce genel hazır seviyesini anlamanızı sağlar." />
                            </div>
                            <p class="mt-3 text-3xl font-bold tracking-tight text-slate-950">%{{ $readyRatio }}</p>
                            <div class="mt-3 h-2 rounded-full bg-slate-100">
                                <div class="h-2 rounded-full bg-slate-900 transition-all" style="width: {{ min(100, max(0, $readyRatio)) }}%"></div>
                            </div>
                        </div>
                        <div class="rounded-[10px] border border-slate-200 bg-white px-4 py-4">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Hazır</p>
                            <p class="mt-2 text-2xl font-bold tracking-tight text-slate-950">{{ $formatCount($readyOrders) }}</p>
                            <p class="mt-2 text-xs text-emerald-600">Finans ve operasyon akışı tamamlanan siparişler</p>
                        </div>
                        <div class="rounded-[10px] border border-slate-200 bg-white px-4 py-4">
                            <div class="flex items-center gap-1.5">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Eşleşme riski</p>
                                <x-zolm.help-tip title="Eşleşme riski" summary="Sipariş satırında ürün eşleşmesi net olmayan veya kontrol isteyen kayıt sayısını verir." source="Matching issue kayıtları ve sipariş satırı eşleşme kontrolleri." refresh="Eşleştirme çözüldüğünde veya yeni issue oluştuğunda." impact="Kârlılık ve ürün detayının güvenilirliğini doğrudan etkiler." />
                            </div>
                            <p class="mt-2 text-2xl font-bold tracking-tight text-slate-950">{{ $formatCount($stats['match_issue_orders']) }}</p>
                            <p class="mt-2 text-xs text-rose-600">Ürün satırı kontrolü isteyen siparişler</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-2">
                <div class="orders-stat-card rounded-[10px] border border-slate-200 p-5 shadow-sm lg:col-span-2">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="inline-flex items-center rounded-[6px] border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">
                                Çalışma Alanı Özeti
                            </div>
                            <p class="mt-4 text-3xl font-bold tracking-tight text-slate-950 lg:text-[36px]">{{ $formatCount($stats['total_orders']) }}</p>
                            <p class="mt-2 text-sm font-medium text-slate-700">toplam operasyon siparişi</p>
                        </div>
                        <span class="rounded-[6px] bg-emerald-50 px-2.5 py-1 text-[10px] font-semibold text-emerald-600">Canlı</span>
                    </div>
                    <div class="mt-4 grid gap-2 sm:grid-cols-2">
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/80 px-4 py-3">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">Toplam Ciro</p>
                            <p class="mt-2 text-lg font-semibold tracking-tight text-slate-950" title="{{ $formatMoney($stats['total_revenue']) }}">{{ $formatMoney($stats['total_revenue']) }}</p>
                        </div>
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/80 px-4 py-3">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">Ort. Sepet</p>
                            <p class="mt-2 text-lg font-semibold tracking-tight text-slate-950">{{ $formatMoney($averageBasket) }}</p>
                        </div>
                    </div>
                    <div class="mt-4 flex flex-wrap items-center gap-2 text-xs">
                        <span class="rounded-[6px] border border-slate-200 bg-white px-2.5 py-1 text-slate-600">Eski veri destekli detay paneli</span>
                        <span class="rounded-[6px] border border-slate-200 bg-white px-2.5 py-1 text-slate-600">Yoğun tablo görünümü</span>
                    </div>
                </div>

                <div class="orders-stat-card rounded-[10px] border border-slate-200 p-4 shadow-sm">
                    <div class="flex items-center gap-1.5">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Finans Bekliyor</p>
                        <x-zolm.help-tip title="Finans bekliyor" summary="Sipariş gelmiş ama kesin hakediş, kesinti veya finans akışı henüz tamamlanmamış kayıtları gösterir." source="Channel order ve financial event eşleşme durumu." refresh="Yeni finans olayı düştüğünde veya tekrar hesaplandığında." impact="Muhasebe ve finans modülünde önce hangi siparişlere bakılacağını belirler." />
                    </div>
                    <p class="mt-3 text-2xl font-bold tracking-tight text-slate-950">{{ $formatCount($stats['finance_waiting_orders']) }}</p>
                    <p class="mt-2 text-xs text-amber-600">Henüz net muhasebe akışı oluşmayanlar</p>
                </div>
                <div class="orders-stat-card rounded-[10px] border border-slate-200 p-4 shadow-sm">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Sipariş Ritim</p>
                    <p class="mt-3 text-2xl font-bold tracking-tight text-slate-950">{{ $formatCount($readyOrders) }}/{{ $formatCount($stats['total_orders']) }}</p>
                    <p class="mt-2 text-xs text-slate-500">Hazır sipariş / toplam sipariş oranı</p>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════════ --}}
    {{-- OPERASYONEL SİPARİŞ İÇE AKTARIM --}}
    {{-- ═══════════════════════════════════════════════ --}}
    <div x-data="{ importOpen: false }" class="overflow-hidden rounded-[10px] border border-slate-200 bg-white shadow-sm">
        <button type="button"
                @click="importOpen = !importOpen"
                class="flex w-full items-center justify-between gap-3 px-4 py-4 text-left">
            <div>
                <p class="text-base font-semibold text-slate-900">Operasyonel Sipariş İçe Aktarım</p>
                <p class="mt-0.5 text-sm text-slate-500">Müşteri detayı, ürün ve varyant içeren geniş kapsamlı "Sipariş Kayıtları" Excelinizi buraya yükleyin.</p>
            </div>
            <svg class="h-5 w-5 shrink-0 text-slate-400 transition" :class="{ 'rotate-180': importOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 9l-7 7-7-7" />
            </svg>
        </button>
        <div x-cloak x-show="importOpen" x-transition class="border-t border-slate-200 px-4 py-4">
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
                            class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] bg-rose-600 px-4 py-3 sm:py-2 text-sm font-medium text-white transition hover:bg-rose-700 disabled:opacity-60">
                        <span wire:loading.remove wire:target="importOrders">Yükle ve İşle</span>
                        <span wire:loading wire:target="importOrders">İşleniyor...</span>
                    </button>
                    <button type="button"
                            wire:click="runSyncEngine"
                            wire:loading.attr="disabled"
                            class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                        Eski veri finans senkronu
                    </button>
                    <button type="button"
                            wire:click="previewLegacyFinancials"
                            wire:loading.attr="disabled"
                            class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                        Önizleme
                    </button>
                    <button type="button"
                            wire:click="projectLegacyFinancials"
                            wire:loading.attr="disabled"
                            @disabled(($legacyFinancialProjectionPreview['projected_rows'] ?? 0) === 0)
                            class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed">
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
    </div>

    {{-- ═══════════════════════════════════════════════ --}}
    {{-- BLOK 2: TABLO VE FİLTRELER --}}
    {{-- ═══════════════════════════════════════════════ --}}
        @php
            $primaryGuidance = $diagnosticsGuidance['items'][0] ?? null;
            $secondaryGuidance = collect($diagnosticsGuidance['items'] ?? [])->skip(1)->take(4)->values();
            $legacyGuidance = $this->getLegacyProjectionGuidanceCard();
            $guidanceTotalItems = (int) ($diagnosticsGuidance['totals']['items'] ?? 0);
            $guidanceTotalCritical = (int) ($diagnosticsGuidance['totals']['critical'] ?? 0);
            $guidanceTotalWarning = (int) ($diagnosticsGuidance['totals']['warning'] ?? 0);
            $alertsSummary = collect([
                $primaryGuidance['title'] ?? null,
                $legacyGuidance['title'] ?? null,
            ])->filter()->implode(' · ');
            $topAlertsOpenDefault = false;
        @endphp
    @if($guidanceTotalItems > 0 || $legacyGuidance)
        <div class="mb-4" x-data="{ topAlertsOpen: @js($topAlertsOpenDefault) }">
            <div class="overflow-hidden rounded-[10px] border border-slate-200 bg-white shadow-sm">
                <button type="button"
                        @click="topAlertsOpen = !topAlertsOpen"
                        class="flex w-full items-center justify-between gap-3 px-4 py-4 text-left">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            @if($guidanceTotalCritical > 0)
                                <span class="rounded-[6px] border border-rose-200 bg-rose-100 px-2.5 py-0.5 text-xs font-medium text-rose-700">
                                    {{ number_format($guidanceTotalCritical, 0, ',', '.') }} kritik
                                </span>
                            @endif
                            @if($guidanceTotalWarning > 0)
                                <span class="rounded-[6px] border border-amber-200 bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-700">
                                    {{ number_format($guidanceTotalWarning, 0, ',', '.') }} uyarı
                                </span>
                            @endif
                            @if($legacyGuidance)
                                <span class="rounded-[6px] border {{ $legacyGuidance['state'] === 'warning' ? 'border-amber-200 bg-amber-100 text-amber-700' : 'border-emerald-200 bg-emerald-100 text-emerald-700' }} px-2.5 py-0.5 text-xs font-medium">
                                    Eski Veri Finansı
                                </span>
                            @endif
                        </div>
                        @if($alertsSummary !== '')
                            <p class="mt-1.5 text-sm font-medium text-slate-800">{{ $alertsSummary }}</p>
                        @endif
                        <div class="mt-2 flex flex-wrap items-center gap-2 text-[11px]">
                            @if($guidanceTotalItems > 0 && $primaryGuidance && method_exists($this, 'guidanceRouteLabel'))
                                <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2.5 py-1 font-medium text-slate-600">
                                    {{ $this->guidanceRouteLabel($primaryGuidance['route'] ?? null) }}
                                </span>
                            @endif
                            @if($legacyGuidance)
                                <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2.5 py-1 font-medium text-slate-600">
                                    Yansıtma kuyruğu
                                </span>
                            @endif
                        </div>
                    </div>
                    <svg class="h-5 w-5 shrink-0 text-slate-400 transition" :class="{ 'rotate-180': topAlertsOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div x-cloak x-show="topAlertsOpen" x-transition class="border-t border-slate-200 bg-slate-50/40 px-4 py-4 space-y-3">
                    @if($guidanceTotalItems > 0)
                        <div class="rounded-[8px] border border-slate-200 bg-white px-4 py-3">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Kritik ve Uyarılar</p>
                                    @if($primaryGuidance)
                                        <p class="mt-2 text-sm font-semibold text-slate-900">{{ $primaryGuidance['title'] }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ $primaryGuidance['store_name'] ?: '-' }} · {{ $this->humanMarketplace($primaryGuidance['marketplace']) }}</p>
                                    @endif
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    @if(method_exists($this, 'focusTopGuidance'))
                                        <button type="button"
                                                wire:click="focusTopGuidance"
                                                class="inline-flex min-h-[36px] items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50">
                                            {{ $this->guidanceFocusLabel() }}
                                        </button>
                                    @endif
                                    @if(method_exists($this, 'syncTopGuidance'))
                                        <button type="button"
                                                wire:click="syncTopGuidance"
                                                class="inline-flex min-h-[36px] items-center justify-center rounded-[6px] bg-slate-900 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-slate-800">
                                            {{ $this->guidanceSyncLabel() }}
                                        </button>
                                    @endif
                                </div>
                            </div>

                            @if($secondaryGuidance->isNotEmpty())
                                <div class="mt-3 space-y-2">
                                    @foreach($secondaryGuidance as $item)
                                        <a href="{{ $this->guidanceRoute($item) }}"
                                           class="block rounded-[6px] border border-slate-200 bg-slate-50/70 px-3 py-2 transition hover:border-slate-300 hover:bg-slate-50">
                                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                                <div class="min-w-0">
                                                    <p class="text-sm font-medium text-slate-800">{{ $item['title'] }}</p>
                                                    <p class="mt-0.5 text-xs text-slate-500">{{ $item['store_name'] ?: '-' }} · {{ $this->humanMarketplace($item['marketplace']) }}</p>
                                                </div>
                                                <span class="rounded-[6px] border border-slate-200 bg-white px-2.5 py-0.5 text-xs font-medium text-slate-600">
                                                    {{ number_format($item['impact_count'], 0, ',', '.') }} kayıt
                                                </span>
                                            </div>
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif

                    @if($legacyGuidance)
                        <div class="rounded-[8px] border border-slate-200 bg-white px-4 py-3">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-[6px] border {{ $legacyGuidance['state'] === 'warning' ? 'border-amber-200 bg-amber-100 text-amber-700' : 'border-emerald-200 bg-emerald-100 text-emerald-700' }} px-2.5 py-0.5 text-xs font-medium">Eski Veri Finansı</span>
                                        <p class="text-sm font-semibold text-slate-900">{{ $legacyGuidance['store_name'] }}</p>
                                        <span class="text-xs text-slate-400">·</span>
                                        <p class="text-xs text-slate-500">{{ $this->humanMarketplace($legacyGuidance['marketplace']) }}</p>
                                    </div>
                                    <p class="mt-2 text-sm font-medium text-slate-800">{{ $legacyGuidance['title'] }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $legacyGuidance['description'] }}</p>
                                    <div class="mt-2 flex flex-wrap items-center gap-3">
                                        <span class="text-xs font-medium text-amber-700">Bekleyen {{ $legacyGuidance['pending_rows'] }}</span>
                                        <span class="text-xs font-medium text-emerald-700">Kesine dönen {{ $legacyGuidance['confirmed_orders'] }}</span>
                                    </div>
                                </div>
                                <div class="flex shrink-0 gap-2">
                                    <button type="button" wire:click="focusLegacyProjectionCard" class="inline-flex min-h-[36px] items-center justify-center rounded-[6px] bg-slate-900 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-slate-800">
                                        Filtrele ve İncele
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
    </div>

    <div class="pb-1">
        <div class="flex items-center gap-3">
            <div class="orders-collapse-divider-line flex-1"></div>
            <button type="button"
                    @click="toggleWorkspace"
                    class="orders-collapse-button inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-500 transition hover:border-slate-300 hover:text-slate-900">
                <svg class="h-4 w-4 transition duration-200"
                     :class="{ 'rotate-180': workspaceCollapsed }"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div class="orders-collapse-divider-line flex-1"></div>
        </div>
    </div>

    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6"
             x-data="{
                advancedFilters: @js(filled($searchProduct) || filled($profitStateFilter) || filled($financialStateFilter) || filled($legalEntityFilter) || filled($matchStateFilter)),
                bulkToolsOpen: @js(count($selectedOrderIds) > 0 || count($selectedPackageIds) > 0)
             }">

        {{-- Filtreler --}}
        <div class="orders-control-surface mb-5 rounded-[10px] border border-slate-200 p-3 lg:p-4">
            <div class="grid gap-3 xl:grid-cols-[minmax(0,1fr)_304px]">
                <div class="rounded-[10px] border border-slate-200 bg-white/90 p-3 sm:p-4">
                    <div class="flex flex-col gap-4 border-b border-slate-200/80 pb-3 xl:flex-row xl:items-start xl:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex items-center rounded-[6px] border border-slate-200 bg-slate-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">
                                    Komut Çubuğu
                                </span>
                                <span class="inline-flex items-center rounded-[6px] bg-slate-900 px-2.5 py-1 text-[11px] font-semibold text-white">
                                    {{ $formatCount($orders->total()) }} sipariş
                                </span>
                                @if(count($activeFilters) > 0)
                                    <span class="inline-flex items-center rounded-[6px] border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-[11px] font-semibold text-indigo-600">
                                        {{ count($activeFilters) }} aktif filtre
                                    </span>
                                @endif
                            </div>
                            <p class="mt-3 text-sm font-semibold text-slate-900">Arama ve filtre akışı tek çalışma panelinde</p>
                        </div>
                        <div class="grid grid-cols-3 gap-1.5 xl:w-[288px] xl:flex-none">
                            <div class="rounded-[6px] border border-slate-200 bg-slate-50/80 px-2 py-1.5">
                                <p class="text-[9px] font-semibold uppercase tracking-[0.16em] text-slate-400">Hazır</p>
                                <p class="mt-0.5 text-[11px] font-semibold text-emerald-600">{{ $formatCount($readyOrders) }}</p>
                            </div>
                            <div class="rounded-[6px] border border-slate-200 bg-slate-50/80 px-2 py-1.5">
                                <p class="text-[9px] font-semibold uppercase tracking-[0.16em] text-slate-400">Finans</p>
                                <p class="mt-0.5 text-[11px] font-semibold text-amber-600">{{ $formatCount($stats['finance_waiting_orders']) }}</p>
                            </div>
                            <div class="rounded-[6px] border border-slate-200 bg-slate-50/80 px-2 py-1.5">
                                <p class="text-[9px] font-semibold uppercase tracking-[0.16em] text-slate-400">Risk</p>
                                <p class="mt-0.5 text-[11px] font-semibold text-rose-600">{{ $formatCount($stats['match_issue_orders']) }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-3 lg:grid-cols-[minmax(0,1fr)_220px_220px]">
                        <div class="relative lg:col-span-1">
                            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <input type="text"
                                   wire:model.live.debounce.400ms="search"
                                   placeholder="Sipariş / Paket No arayın..."
                                   class="w-full rounded-[6px] border border-slate-200 bg-white py-3 pl-10 pr-4 text-sm font-medium text-slate-900 shadow-sm transition focus:border-slate-900 focus:bg-white focus:outline-none">
                        </div>
                        <input type="text"
                               wire:model.live.debounce.400ms="searchProduct"
                               placeholder="Barkod / Ürün / Stok Kodu..."
                               class="w-full rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                        <input type="text"
                               wire:model.live.debounce.400ms="searchCustomer"
                               placeholder="Müşteri / Telefon / E-Posta..."
                               class="w-full rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                    </div>

                    <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-[repeat(4,minmax(0,1fr))_auto]">
                        <select wire:model.live="statusFilter"
                                class="w-full rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                            <option value="">Tüm Durumlar</option>
                            @foreach($statusOptions as $status)
                                <option value="{{ $status }}">{{ $this->humanStatus($status) }}</option>
                            @endforeach
                        </select>
                        <select wire:model.live="marketplaceFilter"
                                class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                            <option value="">Tüm Pazaryerleri</option>
                            @foreach($marketplaceOptions as $marketplace)
                                <option value="{{ $marketplace }}">{{ $this->humanMarketplace($marketplace) }}</option>
                            @endforeach
                        </select>
                        <select wire:model.live="storeFilter"
                                class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                            <option value="">Tüm Mağazalar</option>
                            @foreach($storeOptions as $store)
                                <option value="{{ $store->id }}">{{ $store->store_name }}</option>
                            @endforeach
                        </select>
                        <select wire:model.live="labelFilter"
                                class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                            <option value="">Renk Etiketi</option>
                            @foreach($orderLabelDefinitions as $labelKey => $label)
                                <option value="{{ $labelKey }}">{{ $label['name'] }}</option>
                            @endforeach
                        </select>
                        <button type="button"
                                @click="advancedFilters = !advancedFilters"
                                class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-white xl:w-auto sm:py-2">
                            <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                            </svg>
                            Gelişmiş
                        </button>
                    </div>

                    <div x-cloak x-show="advancedFilters" x-transition class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-6">
                        <input type="date" wire:model.live="dateFrom"
                               class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                        <input type="date" wire:model.live="dateTo"
                               class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                        <select wire:model.live="profitStateFilter"
                                class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                            <option value="">Kâr Durumu</option>
                            <option value="confirmed">Kesin</option>
                            <option value="estimated">Tahmini</option>
                            <option value="missing">Hesaplanmadı</option>
                        </select>
                        <select wire:model.live="financialStateFilter"
                                class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                            <option value="">Finans Akışı</option>
                            <option value="ready">Finans geldi</option>
                            <option value="waiting">Finans bekliyor</option>
                        </select>
                        <select wire:model.live="legalEntityFilter"
                                class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                            <option value="">Tüm Firmalar</option>
                            @foreach($legalEntityOptions as $entity)
                                <option value="{{ $entity->id }}">{{ $entity->name }}</option>
                            @endforeach
                        </select>
                        <select wire:model.live="matchStateFilter"
                                class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm">
                            <option value="">Eşleşme</option>
                            <option value="full_match">Tam eşleşti</option>
                            <option value="needs_match">Kontrol gerekiyor</option>
                        </select>
                    </div>

                    <div class="mt-3 space-y-3 xl:hidden">
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 px-4 py-3">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">Aktif filtreler</p>
                            <div class="mt-3 flex flex-wrap gap-2 text-[11px]">
                                @if(count($activeFilters) > 0)
                                    @foreach($activeFilters as $filter)
                                        <span class="rounded-[6px] border border-slate-200 bg-white px-2.5 py-1 text-slate-600">{{ $filter }}</span>
                                    @endforeach
                                @else
                                    <span class="rounded-[6px] border border-slate-200 bg-white px-2.5 py-1 text-slate-500">Aktif filtre yok</span>
                                @endif
                            </div>
                        </div>

                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 px-4 py-3">
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">Workspace özeti</p>
                                <span class="rounded-[6px] border border-slate-200 bg-white px-2 py-0.5 text-[10px] font-medium text-slate-600">
                                    {{ count($visibleColumns) }}/{{ count($columnDefs) }}
                                </span>
                            </div>
                            <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 text-xs">
                                <div class="rounded-[6px] border border-slate-200 bg-white px-3 py-2">
                                    <p class="text-slate-500">Hazır</p>
                                    <p class="mt-1 font-medium text-emerald-600">{{ $formatCount($readyOrders) }}</p>
                                </div>
                                <div class="rounded-[6px] border border-slate-200 bg-white px-3 py-2">
                                    <p class="text-slate-500">Finans bekliyor</p>
                                    <p class="mt-1 font-medium text-amber-600">{{ $formatCount($stats['finance_waiting_orders']) }}</p>
                                </div>
                                <div class="rounded-[6px] border border-slate-200 bg-white px-3 py-2">
                                    <p class="text-slate-500">Eşleşme riski</p>
                                    <p class="mt-1 font-medium text-rose-600">{{ $formatCount($stats['match_issue_orders']) }}</p>
                                </div>
                                <div class="rounded-[6px] border border-slate-200 bg-white px-3 py-2">
                                    <p class="text-slate-500">Seçili kayıt</p>
                                    <p class="mt-1 font-medium text-slate-900">{{ $formatCount(count($selectedOrderIds) + count($selectedPackageIds)) }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            @if(config('marketplace.features.bulk_order_actions_enabled', true) || config('marketplace.features.bulk_package_actions_enabled', true))
                                <div x-data="{ bulkOpen: false }" class="relative">
                                    <button @click="bulkOpen = !bulkOpen"
                                            type="button"
                                            class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
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
                                         class="absolute left-0 right-0 top-full z-30 mt-2 rounded-[8px] border border-slate-200 bg-white p-4 shadow-xl sm:w-[320px] sm:left-auto">
                                        <p class="text-xs font-medium uppercase tracking-[0.16em] text-slate-500">Toplu İşlemler</p>

                                        <div class="mt-3 space-y-2">
                                            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">Belge çıktıları</p>
                                            @if($this->hasDocumentSelection())
                                                <a href="{{ $this->documentDownloadUrl('label') }}"
                                                   target="_blank"
                                                   class="flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                                    Kargo etiketi PDF indir
                                                </a>
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

                            @if(count($activeFilters) > 0)
                                <button type="button"
                                        wire:click="resetFilters"
                                        class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-white">
                                    Filtreleri Temizle
                                </button>
                            @endif
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <button type="button"
                                    wire:click="openOrderLabelManager"
                                    class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                Renk Etiketleri
                            </button>
                            <a href="{{ route('mp.settings') }}"
                               class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                Çıktı Ayarları
                            </a>
                        </div>
                    </div>
                </div>

                <div class="orders-tool-rail hidden xl:block rounded-[10px] border border-slate-200 p-4 text-slate-900 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Çalışma Alanı Araçları</p>
                            <p class="mt-2 text-lg font-semibold text-slate-900">Görünüm ve toplu aksiyonlar</p>
                        </div>
                        <span class="rounded-[6px] border border-slate-200 bg-white px-2.5 py-1 text-[10px] font-semibold text-slate-500">
                            {{ count($visibleColumns) }}/{{ count($columnDefs) }}
                        </span>
                    </div>

                    <div class="mt-4 space-y-3">
                        <div class="rounded-[8px] border border-slate-200 bg-white/90 px-4 py-3">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">Aktif filtreler</p>
                            <div class="mt-3 flex flex-wrap gap-2 text-[11px]">
                                @if(count($activeFilters) > 0)
                                    @foreach($activeFilters as $filter)
                                        <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2.5 py-1 text-slate-600">{{ $filter }}</span>
                                    @endforeach
                                @else
                                    <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2.5 py-1 text-slate-500">Aktif filtre yok</span>
                                @endif
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <button type="button"
                                    wire:click="openOrderLabelManager"
                                    class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                Renk Etiketleri
                            </button>
                            <a href="{{ route('mp.settings') }}"
                               class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                Çıktı Ayarları
                            </a>
                        </div>

                        @if(config('marketplace.features.bulk_order_actions_enabled', true) || config('marketplace.features.bulk_package_actions_enabled', true))
                            <div x-data="{ bulkOpen: false }" class="relative md:hidden">
                                <button @click="bulkOpen = !bulkOpen"
                                        type="button"
                                        class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
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
                                         class="absolute right-0 top-full z-30 mt-2 w-full rounded-[8px] border border-slate-200 bg-white p-4 shadow-xl">
                                    <p class="text-xs font-medium uppercase tracking-[0.16em] text-slate-500">Toplu İşlemler</p>

                                    <div class="mt-3 space-y-2">
                                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">Belge çıktıları</p>
                                        @if($this->hasDocumentSelection())
                                            <a href="{{ $this->documentDownloadUrl('label') }}"
                                               target="_blank"
                                               class="flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                                Kargo etiketi PDF indir
                                            </a>
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

                        @if(count($activeFilters) > 0)
                            <button type="button"
                                    wire:click="resetFilters"
                                    class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-white">
                                Filtreleri Temizle
                            </button>
                        @endif
                    </div>
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
                            $profitValue = $legacyHasFinancial
                                ? (float) $legacyOrder->total_net_profit
                                : (float) ($order->profit_value_metric ?? ($profitState === 'confirmed' ? $snapshot?->confirmed_profit : $snapshot?->estimated_profit));
                            $grossRevenue = $legacyOrder
                                ? (float) ($legacyOrder->total_gross_amount ?? ($order->gross_revenue_metric ?? $snapshot?->gross_revenue))
                                : (float) ($order->gross_revenue_metric ?? $snapshot?->gross_revenue);
                            $netReceivable = $legacyHasFinancial
                                ? (float) $legacyOrder->total_net_hakedis
                                : (float) ($snapshot?->net_receivable ?? 0);
                            $matchRatio = (int) ($order->matched_lines_count ?? 0) . '/' . (int) ($order->item_lines_count ?? 0);
                        @endphp

                        <article class="overflow-hidden rounded-[8px] border border-slate-200 bg-white shadow-sm">
                            <div class="flex items-center justify-between border-b border-slate-200 px-4 pt-4 pb-3">
                                <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                                    <input type="checkbox"
                                           wire:model.live="selectedOrderIds"
                                           value="{{ $order->id }}"
                                           class="rounded border-slate-300 text-slate-900 shadow-sm focus:ring-indigo-200">
                                    <span>Seç</span>
                                </label>
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
                                        <p class="mt-1 text-xs text-slate-500">{{ $order->ordered_at?->format('d.m.Y H:i') ?: '-' }}</p>
                                    </div>

                                    <div class="text-right">
                                        <x-zolm.status-badge size="sm" :tone="$this->statusTone($order->order_status)">
                                            {{ $this->humanStatus($order->order_status) }}
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
                                    </div>
                                </div>

                                <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                    <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-3 py-2">
                                        <p class="text-[10px] uppercase tracking-[0.16em] text-slate-500">Hakediş</p>
                                        <p class="mt-1 text-sm font-medium text-slate-900">{{ $formatMoney($netReceivable) }}</p>
                                    </div>
                                    <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-3 py-2">
                                        <p class="text-[10px] uppercase tracking-[0.16em] text-slate-500">Eşleşme</p>
                                        <p class="mt-1 text-sm font-medium text-slate-900">{{ $matchRatio }}</p>
                                    </div>
                                </div>

                                @if($package)
                                    <p class="mt-3 text-xs text-slate-500">
                                        {{ $package->cargo_company ?: 'Kargo yok' }}
                                        @if($package->cargo_tracking_number)
                                            · {{ $package->cargo_tracking_number }}
                                        @endif
                                    </p>
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
                            'muhasebe' => ['label' => 'Muhasebe', 'width' => '106px'],
                            'kar' => ['label' => 'Kâr Oranı', 'width' => '100px'],
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
                                'title' => 'Muhasebe',
                                'summary' => 'Hakediş, net alacak ve tahmini görünümün sipariş bazlı muhasebe özetidir.',
                                'source' => 'Finans olayları, anlık kayıt ve eski veri yansıtma etkileri.',
                                'refresh' => 'Yeni finans olayı işlendiğinde veya yeniden hesap sonrası.',
                                'impact' => 'Tahsilat ve mutabakat odağını belirler.',
                            ],
                            'kar' => [
                                'title' => 'Kâr Oranı',
                                'summary' => 'Sipariş bazında maliyet, komisyon, kargo ve gelir birleşiminden çıkan kârlılık oranıdır.',
                                'source' => 'Sipariş kalemleri, maliyet, kargo ve finans kesintileri.',
                                'refresh' => 'Maliyet veya finans verisi değiştiğinde.',
                                'impact' => 'Zararlı siparişleri ve öncelikli inceleme alanını öne çıkarır.',
                            ],
                        ];
                    @endphp

                    <div class="mb-3 flex flex-col gap-3 rounded-[8px] border border-slate-200 bg-white/90 px-4 py-3 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Sipariş Kayıt Defteri</p>
                            <div class="mt-1 flex items-center gap-2">
                                <p class="text-sm font-semibold text-slate-900">Canlı sipariş tablosu</p>
                                <x-zolm.help-tip title="Canlı sipariş tablosu" summary="Siparişleri operasyon, finans ve kârlılık bağlamıyla aynı tabloda gösterir." source="Kanal siparişleri, paketler, ürün satırları ve finans olayı özetleri." refresh="Filtre, senkron ve aksiyonlar sonrası anlık." impact="Günlük operasyon kararını tek yüzeyden vermenizi sağlar." />
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2.5 py-1 text-slate-600">Eski veri + V2 detay</span>
                            <div class="hidden md:block">
                                @include('livewire.partials.mp-column-toggle', ['columnDefs' => $columnDefs, 'visibleColumns' => $visibleColumns])
                            </div>

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
                                                   class="flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                                    Kargo etiketi PDF indir
                                                </a>
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
                                                   class="rounded border-slate-300 text-slate-900 shadow-sm focus:ring-indigo-200">
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
                                        $profitValue = $legacyHasFinancial
                                            ? (float) $legacyOrder->total_net_profit
                                            : (float) ($order->profit_value_metric ?? ($profitState === 'confirmed' ? $snapshot?->confirmed_profit : $snapshot?->estimated_profit));
                                        $grossRevenue = $legacyOrder
                                            ? (float) ($legacyOrder->total_gross_amount ?? ($order->gross_revenue_metric ?? $snapshot?->gross_revenue))
                                            : (float) ($order->gross_revenue_metric ?? $snapshot?->gross_revenue);
                                        $netReceivable = $legacyHasFinancial
                                            ? (float) $legacyOrder->total_net_hakedis
                                            : (float) ($snapshot?->net_receivable ?? 0);
                                        $matchedLines = (int) ($order->matched_lines_count ?? 0);
                                        $itemLines = (int) ($order->item_lines_count ?? 0);
                                        $lineCount = $legacyOrder?->items?->count() ?: $itemLines;
                                        $discountTotal = $legacyOrder
                                            ? (float) ($legacyOrder->total_discount ?? ($order->total_discount_amount ?? 0))
                                            : (float) ($order->total_discount_amount ?? 0);
                                        $locationCity = $legacyOrder?->customer_city ?: $order->shipment_city;
                                        $locationDistrict = $legacyOrder?->customer_district ?: $order->shipment_district;
                                        $cargoCompany = $legacyOrder?->cargo_company ?: ($package?->cargo_company ?: null);
                                        $trackingNumber = $legacyOrder?->tracking_number ?: ($package?->cargo_tracking_number ?: null);
                                        $cargoDate = $legacyOrder?->cargo_delivery_date ?: ($package?->shipped_at ?: null);
                                        $financialAlert = $legacyHasFinancial ? $legacyOrder->financial_alert : ['type' => null, 'label' => null, 'color' => null];
                                        $estimatedCogs = $legacyOrder
                                            ? (float) (($legacyOrder->estimated_cogs ?? 0) + ($legacyOrder->estimated_packaging ?? 0))
                                            : (float) (($snapshot?->cogs_cost ?? 0) + ($snapshot?->packaging_cost ?? 0));
                                        $estimatedCargo = $legacyOrder
                                            ? (float) ($legacyOrder->estimated_cargo ?? 0)
                                            : (float) (($snapshot?->cargo_total ?? 0) + ($snapshot?->own_cargo_cost ?? 0));
                                        $commissionImpact = $legacyHasFinancial
                                            ? (float) ($legacyOrder->total_commission ?? 0)
                                            : (float) (($snapshot?->commission_total ?? 0) + ($snapshot?->service_fee_total ?? 0) + ($snapshot?->withholding_total ?? 0));
                                        $serviceFeeImpact = $legacyHasFinancial
                                            ? max(0, round($grossRevenue - ((float) ($legacyOrder->total_commission ?? 0)) - ((float) ($legacyOrder->total_cargo_amount ?? 0)) - $netReceivable, 2))
                                            : (float) ($snapshot?->service_fee_total ?? ($order->service_fee_total_metric ?? 0));
                                        $withholdingImpact = $legacyHasFinancial
                                            ? 0.0
                                            : (float) ($snapshot?->withholding_total ?? ($order->withholding_total_metric ?? 0));
                                        $receivableBase = $legacyHasFinancial
                                            ? $grossRevenue
                                            : ((float) ($order->seller_revenue_metric ?? 0) > 0 ? (float) $order->seller_revenue_metric : $grossRevenue);
                                        $receivableBaseLabel = $legacyHasFinancial
                                            ? 'Brüt satış'
                                            : ((float) ($order->seller_revenue_metric ?? 0) > 0 ? 'Satıcı geliri' : 'Tahmini satış');
                                        $receivableRows = array_values(array_filter([
                                            ['label' => $receivableBaseLabel, 'value' => $formatMoney($receivableBase), 'tone' => 'default'],
                                            $commissionImpact > 0 ? ['label' => 'Komisyon / temel kesinti', 'value' => $formatSignedMoney(-1 * $commissionImpact), 'tone' => 'danger'] : null,
                                            $estimatedCargo > 0 ? ['label' => 'Kargo kesintisi', 'value' => $formatSignedMoney(-1 * $estimatedCargo), 'tone' => 'warning'] : null,
                                            $serviceFeeImpact > 0 ? ['label' => $legacyHasFinancial ? 'Diğer kesintiler' : 'Hizmet bedeli', 'value' => $formatSignedMoney(-1 * $serviceFeeImpact), 'tone' => 'danger'] : null,
                                            $withholdingImpact > 0 ? ['label' => 'Stopaj', 'value' => $formatSignedMoney(-1 * $withholdingImpact), 'tone' => 'danger'] : null,
                                        ]));
                                        $receivableFormula = $legacyHasFinancial
                                            ? 'Net hakediş = Brüt satış - komisyon - kargo - diğer kesintiler'
                                            : 'Net hakediş = Satıcı geliri - komisyon - kargo - hizmet - stopaj';
                                        $profitRows = array_values(array_filter([
                                            ($legacyHasFinancial || (int) ($order->financial_event_count ?? 0) > 0)
                                                ? ['label' => 'Net hakediş', 'value' => $formatMoney($netReceivable), 'tone' => 'default']
                                                : ['label' => 'Ciro', 'value' => $formatMoney($grossRevenue), 'tone' => 'default'],
                                            (!$legacyHasFinancial && (int) ($order->financial_event_count ?? 0) === 0 && $commissionImpact > 0)
                                                ? ['label' => 'Tahmini komisyon / kesinti', 'value' => $formatSignedMoney(-1 * $commissionImpact), 'tone' => 'danger']
                                                : null,
                                            $estimatedCogs > 0 ? ['label' => 'Maliyet + ambalaj', 'value' => $formatSignedMoney(-1 * $estimatedCogs), 'tone' => 'danger'] : null,
                                            $estimatedCargo > 0 ? ['label' => 'Kargo maliyeti', 'value' => $formatSignedMoney(-1 * $estimatedCargo), 'tone' => 'warning'] : null,
                                        ]));
                                        $profitFormula = ($legacyHasFinancial || (int) ($order->financial_event_count ?? 0) > 0)
                                            ? 'Kâr = Net hakediş - (maliyet + ambalaj) - kargo maliyeti'
                                            : 'Kâr = Ciro - komisyon / kesinti - (maliyet + ambalaj) - kargo maliyeti';
                                        $marginPercent = (float) ($order->margin_percent_metric ?? 0);
                                        $latestActionRun = $order->actionRuns->sortByDesc('created_at')->first();
                                        $orderColorLabel = $orderLabelDefinitions[$order->color_label_key] ?? null;
                                    @endphp

                                    <tr class="transition hover:bg-slate-50/80">
                                        <td class="px-2.5 py-3.5 align-top">
                                            <div class="flex items-center gap-2">
                                                <input type="checkbox"
                                                       wire:model.live="selectedOrderIds"
                                                       value="{{ $order->id }}"
                                                       class="rounded border-slate-300 text-slate-900 shadow-sm focus:ring-indigo-200">
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
                                                <div class="mt-1 text-xs text-slate-500">{{ $order->ordered_at?->format('d.m.Y H:i') ?: '-' }}</div>
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
                                                <div class="mt-1 text-xs text-slate-500 truncate">{{ $trackingNumber ?: 'Takip no yok' }}</div>
                                                @if($cargoDate)
                                                    <div class="mt-1 text-[11px] text-slate-400">Kargoya: {{ $cargoDate->format('d/m H:i') }}</div>
                                                @else
                                                    <div class="mt-1 text-[11px] text-slate-400">{{ $matchedLines }}/{{ $itemLines }} satır eşleşti</div>
                                                @endif
                                            </td>
                                        @endif

                                        @if(in_array('ciro', $visibleColumns, true))
                                            <td class="order-metric-cell px-2.5 py-3.5 align-top text-right">
                                                <div class="font-semibold text-slate-900">{{ $formatMoney($grossRevenue) }}</div>
                                                <div class="mt-1 text-xs text-indigo-600">{{ $formatCount($lineCount) }} Ürün</div>
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
                                                        :subtitle="($legacyHasFinancial || (int) ($order->financial_event_count ?? 0) > 0)
                                                            ? 'Bu tutar finans hareketlerinden ve kesinti kalemlerinden oluşur.'
                                                            : 'Kesin finans akışı gelmediğinde hakediş alanı sınırlı veriyle görünür.'"
                                                        :rows="$receivableRows"
                                                        result-label="Net hakediş"
                                                        :result-value="$formatMoney($netReceivable)"
                                                        result-tone="default"
                                                        :formulas="[
                                                            $receivableFormula,
                                                            $formatMoney($netReceivable) . ' = ' . collect($receivableRows)->pluck('value')->join(' ')
                                                        ]"
                                                        :note="($legacyHasFinancial || (int) ($order->financial_event_count ?? 0) > 0)
                                                            ? 'Kâr değeri ayrı kâr oranı kolonunda gösterilir.'
                                                            : 'Finans olayları tamamlandığında hakediş dökümü netleşir. Kâr değeri ayrı kâr oranı kolonunda gösterilir.'"
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
                                                    title="Kâr / oran hesabı"
                                                    subtitle="Tablodaki oran, sipariş cirosuna göre kâr yüzdesini gösterir."
                                                    :rows="$profitRows"
                                                    result-label="Kâr"
                                                    :result-value="$formatSignedMoney($profitValue)"
                                                    :result-tone="$profitValue >= 0 ? 'success' : 'danger'"
                                                    :formulas="[
                                                        $profitFormula,
                                                        'Oran = Kâr / Ciro × 100',
                                                        '%' . number_format($marginPercent, 1, ',', '.') . ' = ' . $formatSignedMoney($profitValue) . ' / ' . $formatMoney($grossRevenue) . ' × 100'
                                                    ]"
                                                    note="Siparişteki yüzde alanı ciro bazlı kârlılık oranıdır; bu yüzden kâr oranı başlığı altında görünse de hesap ciroyu temel alır."
                                                >
                                                    <div class="text-[10px] text-slate-500">Maliyet {{ $formatMoney($estimatedCogs) }}</div>
                                                    <div class="mt-1 text-[10px] text-rose-500">Kom. {{ $formatMoney($commissionImpact) }}</div>
                                                    @if($estimatedCargo > 0)
                                                        <div class="mt-1 text-[10px] text-amber-600">Kargo {{ $formatMoney($estimatedCargo) }}</div>
                                                    @endif
                                                    <div class="mt-2 font-semibold {{ $profitValue >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ $formatSignedMoney($profitValue) }}</div>
                                                    <div class="mt-1 text-xs text-slate-500">%{{ number_format($marginPercent, 1, ',', '.') }}</div>
                                                </x-zolm.metric-breakdown>
                                            </td>
                                        @endif

                                        @if(in_array('durum', $visibleColumns, true))
                                            <td class="px-2.5 py-3.5 align-top">
                                                <x-zolm.status-badge size="xs" :tone="$this->statusTone($order->order_status)">
                                                    {{ $this->humanStatus($order->order_status) }}
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
