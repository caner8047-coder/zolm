@php
    $crmLinks = app(\App\Services\Crm\CrmSourceLinkService::class);
    $crmSnapshots = app(\App\Services\Crm\CrmCustomerSnapshotService::class);
    $groupedSupplyOrders = $this->groupedOrders;
    $formatCount = fn ($value) => number_format((float) $value, 0, ',', '.');
    $visible = fn (string $column) => in_array($column, $visibleColumns, true);
    $visibleColumnCount = count($visibleColumns) + 1;
    $reasonTotal = array_sum($stats['sebebiyet']);
    $reasonPercent = fn (string $key) => $reasonTotal > 0 ? max(4, round(($stats['sebebiyet'][$key] / $reasonTotal) * 100)) : 0;
    $hasActiveFilters = $search !== ''
        || $durumFiltre !== 'hepsi'
        || $sebebiyetFiltre !== 'hepsi'
        || $gecikmeFiltre !== 'hepsi'
        || filled($baslangicTarihi)
        || filled($bitisTarihi)
        || $tarihAlani !== 'soz_tarihi';

    $statusTone = function (?string $status): string {
        return match ($status) {
            'gonderildi' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
            'kargo' => 'border-orange-200 bg-orange-50 text-orange-700',
            'paketleme' => 'border-amber-200 bg-amber-50 text-amber-700',
            'uretim' => 'border-sky-200 bg-sky-50 text-sky-700',
            default => 'border-slate-200 bg-white text-slate-700',
        };
    };

    $reasonTone = function (?string $reason): string {
        return match ($reason) {
            'kargo' => 'border-orange-200 bg-orange-50 text-orange-700',
            'paketleme' => 'border-amber-200 bg-amber-50 text-amber-700',
            'uretim' => 'border-blue-200 bg-blue-50 text-blue-700',
            default => 'border-slate-200 bg-white text-slate-600',
        };
    };

    $reasonLabel = fn (?string $reason) => ($sebebiyetOptions[$reason]['label'] ?? '-') === '-'
        ? 'Seçilmedi'
        : ($sebebiyetOptions[$reason]['label'] ?? 'Seçilmedi');
@endphp

<div class="w-full space-y-4 lg:space-y-6">
    @once
        <style>
            [x-cloak] { display: none !important; }

            .supply-scrollbar::-webkit-scrollbar {
                height: 8px;
                width: 8px;
            }

            .supply-scrollbar::-webkit-scrollbar-thumb {
                background: rgba(148, 163, 184, 0.45);
                border-radius: 999px;
            }

            .supply-table {
                table-layout: fixed;
                min-width: 1120px;
                width: 100%;
            }

            .supply-table th,
            .supply-table td {
                vertical-align: top;
            }

            .supply-table th {
                position: relative;
                white-space: nowrap;
            }

            .supply-table td {
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .supply-col-resize {
                position: absolute;
                inset: 0 0 0 auto;
                width: 5px;
                cursor: col-resize;
                background: transparent;
                transition: background-color 0.15s ease;
            }

            .supply-col-resize:hover,
            .supply-col-resize.active {
                background: #0f172a;
            }
        </style>
        <script>
            window.supplyColumnResize = window.supplyColumnResize || (() => ({
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
                            if (!this.resizing || !this.currentTh) {
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

            document.addEventListener('alpine:init', () => {
                Alpine.data('supplyColumnResize', window.supplyColumnResize);
            });
        </script>
    @endonce

    @if(session()->has('success'))
        <div class="rounded-[8px] border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    @if(session()->has('error'))
        <div class="rounded-[8px] border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
            {{ session('error') }}
        </div>
    @endif

    <section class="rounded-[10px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
        <div class="grid grid-cols-1 gap-4 lg:gap-6 xl:grid-cols-12">
            <div class="min-w-0 xl:col-span-5">
                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Fabrika Tedarik</p>
                <div class="mt-2 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between xl:block">
                    <div class="min-w-0">
                        <h1 class="text-xl font-bold text-slate-950 lg:text-2xl">Tedarik Raporları</h1>
                        <p class="mt-1 max-w-xl text-sm text-slate-500">Bekleyen, geciken ve gönderilen tedarikleri tek ekranda izleyin.</p>
                    </div>

                    <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row xl:mt-5">
                        <button
                            type="button"
                            wire:click="$set('showImportModal', true)"
                            class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 sm:w-auto sm:py-2"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 16V4m0 0 4 4m-4-4-4 4M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2" />
                            </svg>
                            Excel Yükle
                        </button>
                        <button
                            type="button"
                            wire:click="$refresh"
                            class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 sm:w-auto sm:py-2"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16.5 3.5A7.5 7.5 0 1 1 4.6 7.2M4 3v4.5h4.5M7.5 20.5A7.5 7.5 0 1 0 19.4 16.8M20 21v-4.5h-4.5" />
                            </svg>
                            Güncelle
                        </button>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 xl:col-span-7">
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-xs font-medium text-slate-500">Hazırlanacak</p>
                            <p class="mt-2 text-3xl font-semibold text-slate-950">{{ $formatCount($stats['bekleyen']) }}</p>
                        </div>
                        <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-[8px] border border-amber-200 bg-amber-50 text-amber-700">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6v6l3 2m6-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                        </span>
                    </div>
                    <p class="mt-3 text-xs text-slate-500">Henüz tamamlanmamış sipariş.</p>
                </div>

                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-xs font-medium text-slate-500">Bugün gönderildi</p>
                            <p class="mt-2 text-3xl font-semibold text-emerald-600">{{ $formatCount($stats['bugun_gonderilen']) }}</p>
                        </div>
                        <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-[8px] border border-emerald-200 bg-emerald-50 text-emerald-700">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m5 12 4 4L19 6" />
                            </svg>
                        </span>
                    </div>
                    <p class="mt-3 text-xs text-slate-500">Bugün tamamlanan sipariş.</p>
                </div>

                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-xs font-medium text-slate-500">Geç kaldı</p>
                            <p class="mt-2 text-3xl font-semibold text-rose-600">{{ $formatCount($stats['gecikmis']) }}</p>
                        </div>
                        <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-[8px] border border-rose-200 bg-rose-50 text-rose-700">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v4m0 4h.01M10.3 4.3 2.8 17.2A2 2 0 0 0 4.5 20h15a2 2 0 0 0 1.7-2.8L13.7 4.3a2 2 0 0 0-3.4 0Z" />
                            </svg>
                        </span>
                    </div>
                    <p class="mt-3 text-xs text-slate-500">Söz tarihi geçmiş kayıt.</p>
                </div>
            </div>
        </div>

        <div class="mt-4 rounded-[8px] border border-slate-200 bg-slate-50/60 p-3 lg:p-4">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="min-w-0">
                    <p class="text-xs font-medium text-slate-500">Soruna sebebiyet veren birimler</p>
                    <p class="mt-1 text-sm font-semibold text-slate-900">Üretim, paketleme ve kargo dağılımı</p>
                </div>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-3 lg:min-w-[520px]">
                    <div class="min-w-0 rounded-[6px] border border-blue-200 bg-white px-3 py-2">
                        <div class="flex items-center justify-between gap-2 text-xs">
                            <span class="font-medium text-blue-700">Üretim</span>
                            <span class="font-mono text-slate-700">{{ $formatCount($stats['sebebiyet']['uretim']) }}</span>
                        </div>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-blue-500" style="width: {{ $reasonPercent('uretim') }}%"></div>
                        </div>
                    </div>
                    <div class="min-w-0 rounded-[6px] border border-amber-200 bg-white px-3 py-2">
                        <div class="flex items-center justify-between gap-2 text-xs">
                            <span class="font-medium text-amber-700">Paketleme</span>
                            <span class="font-mono text-slate-700">{{ $formatCount($stats['sebebiyet']['paketleme']) }}</span>
                        </div>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-amber-500" style="width: {{ $reasonPercent('paketleme') }}%"></div>
                        </div>
                    </div>
                    <div class="min-w-0 rounded-[6px] border border-orange-200 bg-white px-3 py-2">
                        <div class="flex items-center justify-between gap-2 text-xs">
                            <span class="font-medium text-orange-700">Kargo</span>
                            <span class="font-mono text-slate-700">{{ $formatCount($stats['sebebiyet']['kargo']) }}</span>
                        </div>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-orange-500" style="width: {{ $reasonPercent('kargo') }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="rounded-[10px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Liste</p>
                <h2 class="mt-1 text-lg font-semibold text-slate-900">Tedarik edilecek siparişler</h2>
                <p class="mt-1 text-sm text-slate-500">Arayın, filtreleyin, durum veya birim bilgisini aynı satırdan güncelleyin.</p>
            </div>

            <div class="hidden items-center gap-2 md:flex">
                @include('livewire.partials.mp-column-toggle', ['columnDefs' => $columnDefs, 'visibleColumns' => $visibleColumns])
            </div>
        </div>

        <div class="mt-4 rounded-[8px] border border-slate-200 bg-slate-50/60 p-3 lg:p-4">
            <div class="grid grid-cols-1 gap-3 xl:grid-cols-12">
                <div class="xl:col-span-4">
                    <label class="sr-only" for="supply-search">Arama</label>
                    <div class="relative">
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m21 21-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" />
                        </svg>
                        <input
                            id="supply-search"
                            type="text"
                            wire:model.live.debounce.300ms="search"
                            placeholder="Müşteri, sipariş no veya ürün ara"
                            class="w-full rounded-[6px] border border-slate-200 bg-white py-3 pl-10 pr-4 text-base text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10 sm:py-2 sm:text-sm"
                        >
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-2 sm:grid-cols-3 xl:col-span-5">
                    <select wire:model.live="durumFiltre" aria-label="Durum filtresi" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10 sm:py-2 sm:text-sm">
                        <option value="hepsi">Tüm durumlar</option>
                        @foreach($durumOptions as $key => $option)
                            <option value="{{ $key }}">{{ $option['label'] }}</option>
                        @endforeach
                    </select>

                    <select wire:model.live="sebebiyetFiltre" aria-label="Birim filtresi" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10 sm:py-2 sm:text-sm">
                        <option value="hepsi">Tüm birimler</option>
                        @foreach($sebebiyetOptions as $key => $option)
                            @if($key !== 'yok')
                                <option value="{{ $key }}">{{ $option['label'] }}</option>
                            @endif
                        @endforeach
                    </select>

                    <select wire:model.live="gecikmeFiltre" aria-label="Gecikme filtresi" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10 sm:py-2 sm:text-sm">
                        <option value="hepsi">Tüm kayıtlar</option>
                        <option value="gecikmis">Geç kalanlar</option>
                        <option value="zamaninda">Zamanında</option>
                    </select>
                </div>

                <div class="flex flex-col gap-2 sm:flex-row sm:justify-end xl:col-span-3">
                    <button
                        type="button"
                        wire:click="resetFilters"
                        class="inline-flex min-h-[44px] w-full items-center justify-center whitespace-nowrap rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 sm:w-auto sm:py-2"
                    >
                        Sıfırla
                    </button>
                    <button
                        type="button"
                        wire:click="exportExcel"
                        class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 whitespace-nowrap rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 sm:w-auto sm:py-2"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3v12m0 0 4-4m-4 4-4-4M5 21h14" />
                        </svg>
                        Rapor İndir
                    </button>
                </div>
            </div>

            <div class="mt-3 grid grid-cols-1 gap-2 lg:grid-cols-12">
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-3 lg:col-span-8">
                    <select wire:model.live="tarihAlani" aria-label="Tarih alanı" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10 sm:py-2 sm:text-sm">
                        <option value="soz_tarihi">Söz tarihine göre</option>
                        <option value="kayit_tarihi">Kayıt tarihine göre</option>
                    </select>
                    <input type="date" wire:model.live="baslangicTarihi" aria-label="Başlangıç tarihi" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10 sm:py-2 sm:text-sm">
                    <input type="date" wire:model.live="bitisTarihi" aria-label="Bitiş tarihi" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10 sm:py-2 sm:text-sm">
                </div>

                <div class="flex items-center rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-xs text-slate-500 lg:col-span-4">
                    <span class="min-w-0 truncate">{{ $formatCount($orders->total()) }} kayıt gösteriliyor</span>
                    <span class="ml-auto shrink-0 font-medium text-slate-700">{{ count($visibleColumns) }}/{{ count($columnDefs) }} kolon</span>
                </div>
            </div>

            @if($hasActiveFilters)
                <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-slate-200 pt-3">
                    <span class="text-xs font-medium text-slate-500">Aktif filtre</span>
                    @if($search !== '')
                        <span class="rounded-[6px] border border-slate-200 bg-white px-2 py-1 text-xs text-slate-700">Arama: {{ Str::limit($search, 24) }}</span>
                    @endif
                    @if($durumFiltre !== 'hepsi')
                        <span class="rounded-[6px] border border-slate-200 bg-white px-2 py-1 text-xs text-slate-700">Durum: {{ $durumOptions[$durumFiltre]['label'] ?? $durumFiltre }}</span>
                    @endif
                    @if($sebebiyetFiltre !== 'hepsi')
                        <span class="rounded-[6px] border border-slate-200 bg-white px-2 py-1 text-xs text-slate-700">Birim: {{ $reasonLabel($sebebiyetFiltre) }}</span>
                    @endif
                    @if($gecikmeFiltre !== 'hepsi')
                        <span class="rounded-[6px] border border-slate-200 bg-white px-2 py-1 text-xs text-slate-700">{{ $gecikmeFiltre === 'gecikmis' ? 'Geç kalanlar' : 'Zamanında' }}</span>
                    @endif
                    @if(filled($baslangicTarihi) || filled($bitisTarihi))
                        <span class="rounded-[6px] border border-slate-200 bg-white px-2 py-1 text-xs text-slate-700">Tarih aralığı seçili</span>
                    @endif
                </div>
            @endif

            @if(count($selectedIds) > 0)
                <div class="mt-3 flex flex-col gap-2 rounded-[8px] border border-slate-200 bg-white p-3 sm:flex-row sm:items-center">
                    <span class="text-sm font-medium text-slate-700">{{ count($selectedIds) }} kayıt seçildi</span>
                    <div class="flex flex-col gap-2 sm:ml-auto sm:flex-row">
                        <button type="button" wire:click="openBulkModal('durum')" class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] border border-sky-200 bg-sky-50 px-3 py-2 text-sm font-medium text-sky-700 transition hover:bg-sky-100">Durum değiştir</button>
                        <button type="button" wire:click="openBulkModal('sebebiyet')" class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-medium text-amber-700 transition hover:bg-amber-100">Birim ata</button>
                        <button type="button" wire:click="openBulkModal('sil')" class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-medium text-rose-700 transition hover:bg-rose-100">Sil</button>
                    </div>
                </div>
            @endif
        </div>

        <div class="mt-4 hidden overflow-hidden rounded-lg border border-slate-200 bg-white md:block">
            <div class="supply-scrollbar overflow-x-auto" x-data="supplyColumnResize()">
                <table class="supply-table">
                    <thead class="border-b border-slate-200 bg-slate-50">
                        <tr>
                            <th scope="col" class="w-[44px] px-4 py-3 text-left">
                                <input type="checkbox" wire:model.live="selectAll" wire:click="toggleSelectAll" class="rounded border-slate-300 text-slate-900 shadow-sm focus:ring-slate-200">
                            </th>

                            @if($visible('kayit'))
                                <th scope="col" class="w-[112px] px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">
                                    <button type="button" wire:click="sortTable('kayit')" class="inline-flex items-center gap-1 transition hover:text-slate-900">
                                        Kayıt
                                        <span>{{ $sortField === 'kayit_tarihi' ? ($sortDirection === 'asc' ? '↑' : '↓') : '↕' }}</span>
                                    </button>
                                    <div class="supply-col-resize" @mousedown.stop.prevent="startResize($event, $event.target.closest('th'))"></div>
                                </th>
                            @endif

                            @if($visible('musteri'))
                                <th scope="col" class="w-[230px] px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">
                                    Müşteri
                                    <div class="supply-col-resize" @mousedown.stop.prevent="startResize($event, $event.target.closest('th'))"></div>
                                </th>
                            @endif

                            @if($visible('urun'))
                                <th scope="col" class="w-[310px] px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">
                                    Ürün
                                    <div class="supply-col-resize" @mousedown.stop.prevent="startResize($event, $event.target.closest('th'))"></div>
                                </th>
                            @endif

                            @if($visible('adet'))
                                <th scope="col" class="w-[82px] px-4 py-3 text-center text-xs font-semibold uppercase text-slate-500">
                                    Adet
                                    <div class="supply-col-resize" @mousedown.stop.prevent="startResize($event, $event.target.closest('th'))"></div>
                                </th>
                            @endif

                            @if($visible('soz'))
                                <th scope="col" class="w-[132px] px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">
                                    <button type="button" wire:click="sortTable('soz')" class="inline-flex items-center gap-1 transition hover:text-slate-900">
                                        Söz
                                        <span>{{ $sortField === 'soz_tarihi' ? ($sortDirection === 'asc' ? '↑' : '↓') : '↕' }}</span>
                                    </button>
                                    <div class="supply-col-resize" @mousedown.stop.prevent="startResize($event, $event.target.closest('th'))"></div>
                                </th>
                            @endif

                            @if($visible('sebep'))
                                <th scope="col" class="w-[150px] px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">
                                    Birim
                                    <div class="supply-col-resize" @mousedown.stop.prevent="startResize($event, $event.target.closest('th'))"></div>
                                </th>
                            @endif

                            @if($visible('durum'))
                                <th scope="col" class="w-[150px] px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">
                                    Durum
                                    <div class="supply-col-resize" @mousedown.stop.prevent="startResize($event, $event.target.closest('th'))"></div>
                                </th>
                            @endif

                            @if($visible('islem'))
                                <th scope="col" class="w-[152px] px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">
                                    İşlem
                                    <div class="supply-col-resize" @mousedown.stop.prevent="startResize($event, $event.target.closest('th'))"></div>
                                </th>
                            @endif
                        </tr>
                    </thead>

                    @if($groupedSupplyOrders->isNotEmpty())
                        @foreach($groupedSupplyOrders as $group)
                            @php
                                $hasMultipleItems = $group['item_count'] > 1;
                                $firstItem = $group['items']->first();
                                $firstItemCrmSnapshot = $crmSnapshots->forSubject(auth()->user(), 'supply', $firstItem);
                            @endphp

                            <tbody x-data="{ expanded: false }" class="divide-y divide-slate-100 border-b border-slate-200 last:border-b-0">
                                <tr class="transition hover:bg-slate-50/80 {{ $group['is_gecikmis'] ? 'bg-rose-50/50' : '' }}">
                                    <td class="px-4 py-4" @click.stop>
                                        <input type="checkbox" wire:model.live="selectedIds" value="{{ $firstItem->id }}" class="rounded border-slate-300 text-slate-900 shadow-sm focus:ring-slate-200">
                                    </td>

                                    @if($visible('kayit'))
                                        <td class="px-4 py-4 text-sm text-slate-600">
                                            {{ $group['kayit_tarihi']?->format('d.m.Y') ?? '-' }}
                                        </td>
                                    @endif

                                    @if($visible('musteri'))
                                        <td class="px-4 py-4">
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-semibold text-slate-900" title="{{ $group['musteri_adi'] }}">{{ $group['musteri_adi'] }}</p>
                                                <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                                    <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-0.5 font-mono text-[11px] text-slate-500">{{ $group['siparis_no'] ?: 'Sipariş yok' }}</span>
                                                    @if($firstItemCrmSnapshot)
                                                        <a href="{{ $firstItemCrmSnapshot['url'] }}" class="rounded-[6px] border border-slate-200 bg-white px-2 py-0.5 text-[11px] font-medium text-slate-600 transition hover:bg-slate-50">
                                                            CRM {{ $firstItemCrmSnapshot['risk_score'] ?? 0 }} risk
                                                        </a>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                    @endif

                                    @if($visible('urun'))
                                        <td class="px-4 py-4">
                                            @if($hasMultipleItems)
                                                <button type="button" @click="expanded = !expanded" class="group flex w-full min-w-0 items-start gap-2 text-left">
                                                    <span class="mt-0.5 inline-flex h-6 min-w-6 items-center justify-center rounded-[6px] bg-slate-900 px-2 text-xs font-semibold text-white">{{ $group['item_count'] }}</span>
                                                    <span class="min-w-0">
                                                        <span class="block truncate text-sm font-medium text-slate-900">{{ Str::limit($firstItem->urun_adi, 54) }}</span>
                                                        <span class="mt-1 block text-xs text-slate-500">{{ $group['item_count'] - 1 }} ürün daha var</span>
                                                    </span>
                                                    <svg :class="{ 'rotate-180': expanded }" class="ml-auto mt-1 h-4 w-4 shrink-0 text-slate-400 transition group-hover:text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m6 9 6 6 6-6" />
                                                    </svg>
                                                </button>
                                            @else
                                                <p class="line-clamp-2 text-sm text-slate-700" title="{{ $firstItem->urun_adi }}">{{ $firstItem->urun_adi }}</p>
                                            @endif
                                        </td>
                                    @endif

                                    @if($visible('adet'))
                                        <td class="px-4 py-4 text-center">
                                            <span class="inline-flex h-8 min-w-8 items-center justify-center rounded-[6px] border border-slate-200 bg-slate-50 px-2 text-sm font-semibold text-slate-900">{{ $group['total_adet'] }}</span>
                                        </td>
                                    @endif

                                    @if($visible('soz'))
                                        <td class="px-4 py-4">
                                            <p class="text-sm font-medium {{ $group['is_gecikmis'] ? 'text-rose-600' : 'text-slate-700' }}">{{ $group['soz_tarihi']?->format('d.m.Y') ?? '-' }}</p>
                                            @if($group['is_gecikmis'])
                                                <span class="mt-1 inline-flex rounded-[6px] border border-rose-200 bg-rose-50 px-2 py-0.5 text-[11px] font-medium text-rose-700">Geç kaldı</span>
                                            @endif
                                        </td>
                                    @endif

                                    @if($visible('sebep'))
                                        <td class="px-4 py-4" @click.stop>
                                            <select wire:change="updateSebebiyet({{ $firstItem->id }}, $event.target.value)" class="w-full rounded-[6px] border px-2.5 py-2 text-xs font-medium shadow-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10 {{ $reasonTone($firstItem->sebebiyet) }}">
                                                @foreach($sebebiyetOptions as $key => $option)
                                                    <option value="{{ $key }}" {{ $firstItem->sebebiyet === $key ? 'selected' : '' }}>{{ $option['label'] === '-' ? 'Seçilmedi' : $option['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                    @endif

                                    @if($visible('durum'))
                                        <td class="px-4 py-4" @click.stop>
                                            <select wire:change="updateDurum({{ $firstItem->id }}, $event.target.value)" class="w-full rounded-[6px] border px-2.5 py-2 text-xs font-medium shadow-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10 {{ $statusTone($firstItem->durum) }}">
                                                @foreach($durumOptions as $key => $option)
                                                    <option value="{{ $key }}" {{ $firstItem->durum === $key ? 'selected' : '' }}>{{ $option['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                    @endif

                                    @if($visible('islem'))
                                        <td class="px-4 py-4" @click.stop>
                                            <div class="flex flex-wrap items-center gap-1.5">
                                                <a href="{{ $crmLinks->urlFor('supply', $firstItem) }}" class="inline-flex min-h-[32px] items-center justify-center rounded-[6px] border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 transition hover:bg-slate-50">CRM</a>
                                                <a href="{{ route('supply.label', $firstItem->id) }}" target="_blank" class="inline-flex min-h-[32px] items-center justify-center gap-1 rounded-[6px] border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-700 transition hover:bg-white">
                                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 17H5a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2M7 9V5a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v4M7 14h10v7H7z" />
                                                    </svg>
                                                    Etiket
                                                </a>
                                            </div>
                                        </td>
                                    @endif
                                </tr>

                                @if($hasMultipleItems)
                                    <tr x-show="expanded" x-cloak x-transition class="bg-slate-50/70">
                                        <td colspan="{{ $visibleColumnCount }}" class="p-0">
                                            <div class="space-y-2 p-3">
                                                @foreach($group['items'] as $item)
                                                    <div class="grid grid-cols-12 gap-2 rounded-[8px] border border-slate-200 bg-white p-3">
                                                        <div class="col-span-12 flex items-start gap-2 lg:col-span-6">
                                                            <input type="checkbox" wire:model.live="selectedIds" value="{{ $item->id }}" class="mt-1 rounded border-slate-300 text-slate-900 shadow-sm focus:ring-slate-200">
                                                            <div class="min-w-0">
                                                                <p class="text-xs font-medium text-slate-900">{{ $item->urun_adi }}</p>
                                                                <p class="mt-1 text-[11px] text-slate-500">{{ $item->adet }} adet · Söz: {{ $item->soz_tarihi?->format('d.m.Y') ?? '-' }}</p>
                                                            </div>
                                                        </div>
                                                        <div class="col-span-12 grid grid-cols-1 gap-2 sm:grid-cols-3 lg:col-span-6">
                                                            <select wire:change="updateSebebiyet({{ $item->id }}, $event.target.value)" class="rounded-[6px] border px-2.5 py-2 text-xs font-medium shadow-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10 {{ $reasonTone($item->sebebiyet) }}">
                                                                @foreach($sebebiyetOptions as $key => $option)
                                                                    <option value="{{ $key }}" {{ $item->sebebiyet === $key ? 'selected' : '' }}>{{ $option['label'] === '-' ? 'Seçilmedi' : $option['label'] }}</option>
                                                                @endforeach
                                                            </select>
                                                            <select wire:change="updateDurum({{ $item->id }}, $event.target.value)" class="rounded-[6px] border px-2.5 py-2 text-xs font-medium shadow-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10 {{ $statusTone($item->durum) }}">
                                                                @foreach($durumOptions as $key => $option)
                                                                    <option value="{{ $key }}" {{ $item->durum === $key ? 'selected' : '' }}>{{ $option['label'] }}</option>
                                                                @endforeach
                                                            </select>
                                                            <div class="flex items-center gap-1.5">
                                                                <a href="{{ $crmLinks->urlFor('supply', $item) }}" class="inline-flex min-h-[36px] flex-1 items-center justify-center rounded-[6px] border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 transition hover:bg-slate-50">CRM</a>
                                                                <a href="{{ route('supply.label', $item->id) }}" target="_blank" class="inline-flex min-h-[36px] flex-1 items-center justify-center rounded-[6px] border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-700 transition hover:bg-white">Etiket</a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        @endforeach
                    @else
                        <tbody>
                            <tr>
                                <td colspan="{{ $visibleColumnCount }}" class="px-4 py-14 text-center">
                                    <div class="mx-auto flex max-w-sm flex-col items-center">
                                        <span class="inline-flex h-12 w-12 items-center justify-center rounded-[8px] border border-slate-200 bg-slate-50 text-slate-400">
                                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 13V7a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v6m16 0v4a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-4m16 0h-4.5l-2 2h-3l-2-2H4" />
                                            </svg>
                                        </span>
                                        <p class="mt-3 text-sm font-semibold text-slate-900">Kayıt bulunamadı</p>
                                        <p class="mt-1 text-sm text-slate-500">Aramayı veya filtreleri değiştirerek tekrar deneyin.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    @endif
                </table>
            </div>

            @if($orders->hasPages())
                <div class="border-t border-slate-200 px-4 py-3">
                    {{ $orders->links() }}
                </div>
            @endif
        </div>

        <div class="mt-4 space-y-3 md:hidden">
            @if($groupedSupplyOrders->isNotEmpty())
                @foreach($groupedSupplyOrders as $group)
                    @php
                        $hasMultipleItems = $group['item_count'] > 1;
                        $firstItem = $group['items']->first();
                        $firstItemCrmSnapshot = $crmSnapshots->forSubject(auth()->user(), 'supply', $firstItem);
                    @endphp

                    <article x-data="{ expanded: false }" class="rounded-[10px] border bg-white p-4 shadow-sm {{ $group['is_gecikmis'] ? 'border-rose-200' : 'border-slate-200' }}">
                        <div class="flex items-start gap-3">
                            <input type="checkbox" wire:model.live="selectedIds" value="{{ $firstItem->id }}" class="mt-1 rounded border-slate-300 text-slate-900 shadow-sm focus:ring-slate-200">
                            <div class="min-w-0 flex-1">
                                <div class="flex min-w-0 items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-900">{{ $group['musteri_adi'] }}</p>
                                        <p class="mt-1 font-mono text-[11px] text-slate-500">{{ $group['siparis_no'] ?: 'Sipariş yok' }}</p>
                                    </div>
                                    <span class="shrink-0 rounded-[6px] border px-2 py-1 text-[11px] font-medium {{ $group['is_gecikmis'] ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-slate-200 bg-slate-50 text-slate-600' }}">
                                        {{ $group['is_gecikmis'] ? 'Geç kaldı' : 'Takipte' }}
                                    </span>
                                </div>

                                <button type="button" @if($hasMultipleItems) @click="expanded = !expanded" @endif class="mt-3 flex w-full min-w-0 items-start gap-2 text-left">
                                    <span class="inline-flex h-6 min-w-6 items-center justify-center rounded-[6px] bg-slate-900 px-2 text-xs font-semibold text-white">{{ $group['item_count'] }}</span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-sm text-slate-700">{{ $hasMultipleItems ? Str::limit($firstItem->urun_adi, 44) . ' ve ' . ($group['item_count'] - 1) . ' ürün daha' : $firstItem->urun_adi }}</span>
                                    </span>
                                    @if($hasMultipleItems)
                                        <svg :class="{ 'rotate-180': expanded }" class="mt-1 h-4 w-4 shrink-0 text-slate-400 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m6 9 6 6 6-6" />
                                        </svg>
                                    @endif
                                </button>
                            </div>
                        </div>

                        <div class="mt-3 grid grid-cols-3 gap-2">
                            <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-2 py-2">
                                <p class="text-[11px] text-slate-500">Adet</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900">{{ $group['total_adet'] }}</p>
                            </div>
                            <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-2 py-2">
                                <p class="text-[11px] text-slate-500">Söz</p>
                                <p class="mt-1 text-sm font-semibold {{ $group['is_gecikmis'] ? 'text-rose-600' : 'text-slate-900' }}">{{ $group['soz_tarihi']?->format('d.m') ?? '-' }}</p>
                            </div>
                            <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-2 py-2">
                                <p class="text-[11px] text-slate-500">Kayıt</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900">{{ $group['kayit_tarihi']?->format('d.m') ?? '-' }}</p>
                            </div>
                        </div>

                        @if($firstItemCrmSnapshot)
                            <a href="{{ $firstItemCrmSnapshot['url'] }}" class="mt-3 flex items-center justify-between rounded-[6px] border border-slate-200 bg-slate-50/70 px-3 py-2 text-xs text-slate-600">
                                <span class="font-medium text-slate-700">CRM sinyali</span>
                                <span>{{ $firstItemCrmSnapshot['risk_score'] ?? 0 }} risk · {{ $firstItemCrmSnapshot['open_case_count'] ?? 0 }} vaka</span>
                            </a>
                        @endif

                        <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <select wire:change="updateSebebiyet({{ $firstItem->id }}, $event.target.value)" class="w-full rounded-[6px] border px-3 py-3 text-base font-medium shadow-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10 sm:text-sm {{ $reasonTone($firstItem->sebebiyet) }}">
                                @foreach($sebebiyetOptions as $key => $option)
                                    <option value="{{ $key }}" {{ $firstItem->sebebiyet === $key ? 'selected' : '' }}>{{ $option['label'] === '-' ? 'Seçilmedi' : $option['label'] }}</option>
                                @endforeach
                            </select>

                            <select wire:change="updateDurum({{ $firstItem->id }}, $event.target.value)" class="w-full rounded-[6px] border px-3 py-3 text-base font-medium shadow-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10 sm:text-sm {{ $statusTone($firstItem->durum) }}">
                                @foreach($durumOptions as $key => $option)
                                    <option value="{{ $key }}" {{ $firstItem->durum === $key ? 'selected' : '' }}>{{ $option['label'] }}</option>
                                @endforeach
                            </select>

                            <a href="{{ $crmLinks->urlFor('supply', $firstItem) }}" class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50">CRM</a>
                            <a href="{{ route('supply.label', $firstItem->id) }}" target="_blank" class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800">Etiket</a>
                        </div>

                        @if($hasMultipleItems)
                            <div x-show="expanded" x-cloak x-transition class="mt-3 space-y-2 border-t border-slate-200 pt-3">
                                @foreach($group['items'] as $item)
                                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                                        <div class="flex items-start gap-2">
                                            <input type="checkbox" wire:model.live="selectedIds" value="{{ $item->id }}" class="mt-1 rounded border-slate-300 text-slate-900 shadow-sm focus:ring-slate-200">
                                            <div class="min-w-0 flex-1">
                                                <p class="text-xs font-medium text-slate-900">{{ $item->urun_adi }}</p>
                                                <p class="mt-1 text-[11px] text-slate-500">{{ $item->adet }} adet · Söz: {{ $item->soz_tarihi?->format('d.m.Y') ?? '-' }}</p>
                                            </div>
                                        </div>
                                        <div class="mt-2 grid grid-cols-1 gap-2">
                                            <select wire:change="updateDurum({{ $item->id }}, $event.target.value)" class="w-full rounded-[6px] border px-3 py-2.5 text-sm font-medium shadow-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10 {{ $statusTone($item->durum) }}">
                                                @foreach($durumOptions as $key => $option)
                                                    <option value="{{ $key }}" {{ $item->durum === $key ? 'selected' : '' }}>{{ $option['label'] }}</option>
                                                @endforeach
                                            </select>
                                            <div class="grid grid-cols-2 gap-2">
                                                <a href="{{ $crmLinks->urlFor('supply', $item) }}" class="inline-flex min-h-[40px] items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700">CRM</a>
                                                <a href="{{ route('supply.label', $item->id) }}" target="_blank" class="inline-flex min-h-[40px] items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700">Etiket</a>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </article>
                @endforeach
            @else
                <div class="rounded-[10px] border border-slate-200 bg-white p-8 text-center shadow-sm">
                    <p class="text-sm font-semibold text-slate-900">Kayıt bulunamadı</p>
                    <p class="mt-1 text-sm text-slate-500">Aramayı veya filtreleri değiştirerek tekrar deneyin.</p>
                </div>
            @endif

            @if($orders->hasPages())
                <div class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm">
                    {{ $orders->links() }}
                </div>
            @endif
        </div>
    </section>

    @if($showImportModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
            <div class="flex min-h-screen items-end justify-center p-0 sm:items-center sm:p-4">
                <div class="fixed inset-0 bg-slate-950/40" wire:click="$set('showImportModal', false)"></div>

                <div class="relative w-full rounded-t-[10px] border border-slate-200 bg-white p-4 shadow-xl sm:max-w-lg sm:rounded-[10px] sm:p-6">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Excel</p>
                            <h3 class="mt-1 text-lg font-semibold text-slate-900">Tedarik dosyası yükle</h3>
                            <p class="mt-1 text-sm text-slate-500">Excel'de TEDARİK EDİLECEKLER işaretli satırlar alınır.</p>
                        </div>
                        <button type="button" wire:click="$set('showImportModal', false)" class="inline-flex h-9 w-9 items-center justify-center rounded-[6px] border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 6l12 12M18 6 6 18" />
                            </svg>
                        </button>
                    </div>

                    <form wire:submit="importExcel" class="mt-5 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Excel dosyası</label>
                            <input type="file" wire:model="excelFile" accept=".xlsx,.xls" class="mt-2 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm file:mr-3 file:rounded-[6px] file:border-0 file:bg-slate-900 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10 sm:text-sm">
                            @error('excelFile')
                                <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="rounded-[8px] border border-sky-200 bg-sky-50 p-3 text-sm text-sky-800">
                            Sipariş no, müşteri ve ürün alanı boş olan satırlar atlanır.
                        </div>

                        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <button type="button" wire:click="$set('showImportModal', false)" class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 sm:w-auto sm:py-2">İptal</button>
                            <button type="submit" wire:loading.attr="disabled" class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto sm:py-2">
                                <span wire:loading.remove wire:target="importExcel">İçeri Aktar</span>
                                <span wire:loading wire:target="importExcel">Yükleniyor...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if($showBulkModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
            <div class="flex min-h-screen items-end justify-center p-0 sm:items-center sm:p-4">
                <div class="fixed inset-0 bg-slate-950/40" wire:click="$set('showBulkModal', false)"></div>

                <div class="relative w-full rounded-t-[10px] border border-slate-200 bg-white p-4 shadow-xl sm:max-w-md sm:rounded-[10px] sm:p-6">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Toplu işlem</p>
                    <h3 class="mt-1 text-lg font-semibold text-slate-900">
                        @if($bulkAction === 'durum')
                            Durum değiştir
                        @elseif($bulkAction === 'sebebiyet')
                            Birim ata
                        @else
                            Kayıtları sil
                        @endif
                    </h3>
                    <p class="mt-2 text-sm text-slate-500">{{ count($selectedIds) }} kayıt için işlem yapılacak.</p>

                    <div class="mt-5 space-y-4">
                        @if($bulkAction === 'durum')
                            <div>
                                <label class="block text-sm font-medium text-slate-700">Yeni durum</label>
                                <select wire:model="bulkDurum" class="mt-2 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10 sm:text-sm">
                                    @foreach($durumOptions as $key => $option)
                                        <option value="{{ $key }}">{{ $option['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @elseif($bulkAction === 'sebebiyet')
                            <div>
                                <label class="block text-sm font-medium text-slate-700">Soruna sebebiyet veren birim</label>
                                <select wire:model="bulkSebebiyet" class="mt-2 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10 sm:text-sm">
                                    @foreach($sebebiyetOptions as $key => $option)
                                        <option value="{{ $key }}">{{ $option['label'] === '-' ? 'Seçilmedi' : $option['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @else
                            <div class="rounded-[8px] border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800">
                                Bu işlem seçili kayıtları kalıcı olarak siler.
                            </div>
                        @endif

                        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <button type="button" wire:click="$set('showBulkModal', false)" class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 sm:w-auto sm:py-2">İptal</button>
                            @if($bulkAction === 'sil')
                                <button type="button" wire:click="bulkDelete" class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] bg-rose-600 px-4 py-3 text-sm font-medium text-white transition hover:bg-rose-700 sm:w-auto sm:py-2">Sil</button>
                            @else
                                <button type="button" wire:click="{{ $bulkAction === 'durum' ? 'bulkUpdateDurum' : 'bulkUpdateSebebiyet' }}" class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 sm:w-auto sm:py-2">Uygula</button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
