@php
    $formatMoney = fn ($value) => '₺' . number_format((float) $value, 2, ',', '.');
    $formatMoneyShort = fn ($value) => '₺' . number_format((float) $value, 0, ',', '.');
    $formatSignedMoney = fn ($value) => ((float) $value >= 0 ? '+' : '-') . '₺' . number_format(abs((float) $value), 2, ',', '.');
    $formatPercent = fn ($value) => '%' . number_format((float) $value, 1, ',', '.');
    $formatMultiplier = fn ($value) => '%' . number_format((((float) $value) - 1) * 100, 1, ',', '.');
    $statusFilters = [
        'all' => 'Tümü',
        'opportunity' => 'Hedefi geçen',
        'risk' => 'Risk',
        'selected' => 'Seçili',
        'negative_delta' => 'Kâr azalır',
        'missing_cost' => 'Maliyet eksik',
        'unmatched' => 'Eşleşmeyen',
        'kept' => 'Korunan',
    ];
    $visibleOptionalColumns = collect($visibleColumns)->filter()->count();
    $tableColspan = 3 + $visibleOptionalColumns;
    $sortLabel = fn ($field) => $sortField === $field ? ($sortDirection === 'asc' ? 'Artan' : 'Azalan') : 'Sırala';
@endphp

<div class="w-full space-y-4 overflow-x-hidden lg:space-y-6" x-data>
    @once
        <style>
            [x-cloak] {
                display: none !important;
            }

            .basket-scrollbar::-webkit-scrollbar {
                height: 8px;
                width: 8px;
            }

            .basket-scrollbar::-webkit-scrollbar-thumb {
                background: rgba(148, 163, 184, 0.55);
                border-radius: 999px;
            }

            .basket-ledger-table {
                table-layout: fixed;
                min-width: 1180px;
            }

            .basket-ledger-table th,
            .basket-ledger-table td {
                vertical-align: top;
            }

            .basket-resize-handle {
                position: absolute;
                inset-block: 0;
                right: 0;
                width: 4px;
                cursor: col-resize;
            }

            .basket-resize-handle:hover,
            .basket-resize-handle.active {
                background: #0f172a;
            }
        </style>
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('basketColumnResize', () => ({
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

                            const newWidth = Math.max(104, this.startWidth + (moveEvent.pageX - this.startX));
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

    @if($message)
        <div class="rounded-[8px] border p-4 text-sm {{ $messageType === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : ($messageType === 'error' ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-sky-200 bg-sky-50 text-sky-800') }}">
            {{ $message }}
        </div>
    @endif

    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
        <div class="grid grid-cols-1 gap-4 xl:grid-cols-12 xl:gap-6">
            <div class="min-w-0 xl:col-span-7">
                <div class="inline-flex rounded-[6px] border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-500">
                    Kampanya karar paneli
                </div>
                <h1 class="mt-3 text-xl font-bold text-slate-900 lg:text-2xl">Sepet İndirimi Kampanyaları</h1>
                <p class="mt-1 max-w-3xl text-sm text-slate-500">
                    Maksimum tutar, satıcı indirim payı, komisyon ve ürün maliyetini birlikte hesaplayarak kampanyaya kârlı katılım listesini çıkarır.
                </p>

                <div class="mt-4 inline-flex w-full rounded-[8px] border border-slate-200 bg-slate-50/70 p-1 sm:w-auto">
                    <button
                        type="button"
                        wire:click="switchTab('analyze')"
                        class="flex-1 rounded-[6px] px-4 py-2 text-sm font-medium transition sm:flex-none {{ $activeTab === 'analyze' ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:bg-white hover:text-slate-900' }}"
                    >
                        Analiz
                    </button>
                    <button
                        type="button"
                        wire:click="switchTab('history')"
                        class="flex-1 rounded-[6px] px-4 py-2 text-sm font-medium transition sm:flex-none {{ $activeTab === 'history' ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:bg-white hover:text-slate-900' }}"
                    >
                        Geçmiş
                        @if($this->reports->count() > 0)
                            <span class="ml-1 rounded-[6px] bg-white/80 px-1.5 py-0.5 text-[11px] text-slate-700">{{ $this->reports->count() }}</span>
                        @endif
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 xl:col-span-5">
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                    <p class="text-xs font-medium text-slate-500">Ürün havuzu</p>
                    <p class="mt-2 text-xl font-bold text-slate-900">{{ number_format($this->productCount, 0, ',', '.') }}</p>
                    <p class="mt-1 text-xs text-slate-500">MpProduct kaydı</p>
                </div>
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                    <p class="text-xs font-medium text-slate-500">Maliyet hazır</p>
                    <p class="mt-2 text-xl font-bold text-emerald-700">{{ number_format($this->costCount, 0, ',', '.') }}</p>
                    <p class="mt-1 text-xs text-slate-500">COGS tanımlı</p>
                </div>
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                    <p class="text-xs font-medium text-slate-500">AI durumu</p>
                    <p class="mt-2 text-xl font-bold text-slate-900">{{ $this->activeReport?->ai_analysis ? 'Hazır' : 'Bekliyor' }}</p>
                    <p class="mt-1 text-xs text-slate-500">Kampanya bağlamlı</p>
                </div>
            </div>
        </div>
    </section>

    @if($activeTab === 'analyze')
        @if($step === 1)
            <div class="grid grid-cols-1 gap-4 lg:gap-6 xl:grid-cols-12">
                <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6 xl:col-span-8">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0">
                            <h2 class="text-lg font-semibold text-slate-900">Yeni sepet indirimi analizi</h2>
                            <p class="mt-1 text-sm text-slate-500">Trendyol kampanya Excelindeki maksimum tutar kolonuna göre ürün bazında kârlılık hesaplanır.</p>
                        </div>
                        <div class="rounded-[6px] border border-slate-200 bg-slate-50/80 px-3 py-2 text-xs font-medium text-slate-600">
                            XLSX/XLS · Max 10 MB
                        </div>
                    </div>

                    <div class="mt-5 grid grid-cols-1 gap-4">
                        <label class="group cursor-pointer rounded-[8px] border border-dashed border-slate-300 bg-slate-50/60 p-4 transition hover:border-slate-400 hover:bg-white">
                            <input type="file" wire:model="excelFile" accept=".xlsx,.xls" class="hidden">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex min-w-0 items-center gap-3">
                                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-[8px] bg-slate-900 text-white">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 16V4m0 12-4-4m4 4 4-4M4 20h16" />
                                        </svg>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-900">{{ $excelFile ? $excelFile->getClientOriginalName() : 'Sepet indirimi dosyası seçin' }}</p>
                                        <p class="mt-1 text-xs text-slate-500">Barkod, stok kodu, mevcut fiyat, maksimum tutar ve ListingId kolonları korunur.</p>
                                    </div>
                                </div>
                                <span class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition group-hover:border-slate-300">
                                    Dosya seç
                                </span>
                            </div>
                        </label>
                        @error('excelFile')
                            <p class="text-sm text-rose-600">{{ $message }}</p>
                        @enderror

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            <div class="sm:col-span-2">
                                <label class="mb-1.5 block text-sm font-medium text-slate-700">Kampanya adı</label>
                                <input
                                    type="text"
                                    wire:model.defer="campaignTitle"
                                    class="min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none transition focus:border-slate-900 sm:py-2 sm:text-sm"
                                >
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-slate-700">Sepet baremi</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    wire:model.live="thresholdAmount"
                                    class="min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none transition focus:border-slate-900 sm:py-2 sm:text-sm"
                                >
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-slate-700">İndirim tutarı</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    wire:model.live="discountAmount"
                                    class="min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none transition focus:border-slate-900 sm:py-2 sm:text-sm"
                                >
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-slate-700">Satıcı payı %</label>
                                <input
                                    type="number"
                                    step="0.1"
                                    wire:model.live="sellerSharePercent"
                                    class="min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none transition focus:border-slate-900 sm:py-2 sm:text-sm"
                                >
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-slate-700">Hedef net kâr %</label>
                                <input
                                    type="number"
                                    step="0.1"
                                    wire:model.live="targetMarginPercent"
                                    class="min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none transition focus:border-slate-900 sm:py-2 sm:text-sm"
                                >
                            </div>
                            <div class="sm:col-span-2">
                                <label class="mb-1.5 block text-sm font-medium text-slate-700">Rapor adı</label>
                                <input
                                    type="text"
                                    wire:model.defer="reportName"
                                    placeholder="Örn: Trendyol Büyük İndirim Günleri"
                                    class="min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-slate-900 sm:py-2 sm:text-sm"
                                >
                            </div>
                        </div>
                    </div>

                    @if($excelFile)
                        <div class="mt-5 flex flex-col gap-3 rounded-[8px] border border-emerald-200 bg-emerald-50 p-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0 text-sm text-emerald-800">
                                <span class="font-semibold">Dosya hazır.</span>
                                Satıcı payı {{ $formatMoney($discountAmount * ($sellerSharePercent / 100)) }} üst sınırla hesaplanacak.
                            </div>
                            <div class="flex w-full gap-2 sm:w-auto">
                                <button
                                    type="button"
                                    wire:click="$set('excelFile', null)"
                                    class="inline-flex min-h-[44px] flex-1 items-center justify-center rounded-[6px] border border-emerald-200 bg-white px-4 py-3 text-sm font-medium text-emerald-800 transition hover:bg-emerald-50 sm:flex-none sm:py-2"
                                >
                                    Temizle
                                </button>
                                <button
                                    type="button"
                                    wire:click="analyze"
                                    wire:loading.attr="disabled"
                                    wire:target="analyze"
                                    class="inline-flex min-h-[44px] flex-1 items-center justify-center rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 disabled:opacity-60 sm:flex-none sm:py-2"
                                >
                                    <span wire:loading.remove wire:target="analyze">Analizi başlat</span>
                                    <span wire:loading wire:target="analyze">İşleniyor...</span>
                                </button>
                            </div>
                        </div>
                    @endif
                </section>

                <aside class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6 xl:col-span-4">
                    <h3 class="text-sm font-semibold text-slate-900">Kampanya parametreleri</h3>
                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                            <p class="text-xs font-medium text-slate-500">Barem</p>
                            <p class="mt-1 text-lg font-bold text-slate-900">{{ $formatMoneyShort($thresholdAmount) }}</p>
                        </div>
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                            <p class="text-xs font-medium text-slate-500">İndirim</p>
                            <p class="mt-1 text-lg font-bold text-slate-900">{{ $formatMoneyShort($discountAmount) }}</p>
                        </div>
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                            <p class="text-xs font-medium text-slate-500">Satıcı payı</p>
                            <p class="mt-1 text-lg font-bold text-rose-700">{{ $formatMoney($discountAmount * ($sellerSharePercent / 100)) }}</p>
                        </div>
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                            <p class="text-xs font-medium text-slate-500">Hedef</p>
                            <p class="mt-1 text-lg font-bold text-emerald-700">{{ $formatMultiplier(1 + ((float) $targetMarginPercent / 100)) }}</p>
                        </div>
                    </div>
                </aside>
            </div>
        @elseif($step === 2)
            <section class="rounded-[10px] border border-slate-200 bg-white p-10 text-center shadow-sm">
                <div class="mx-auto h-12 w-12 animate-spin rounded-full border-2 border-slate-200 border-t-slate-900"></div>
                <h2 class="mt-4 text-lg font-semibold text-slate-900">Kampanya dosyası analiz ediliyor</h2>
                <p class="mt-1 text-sm text-slate-500">Maksimum tutar ve satıcı payı satır satır hesaplanıyor.</p>
            </section>
        @elseif($step === 3 && $this->activeReport)
            @php
                $report = $this->activeReport;
                $metrics = $this->reportMetrics;
            @endphp

            <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
                <div class="grid grid-cols-1 gap-4 xl:grid-cols-12 xl:gap-6">
                    <div class="min-w-0 xl:col-span-5">
                        <div class="inline-flex rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-1 text-xs font-semibold text-slate-500">
                            {{ $campaignTitle }}
                        </div>
                        <h2 class="mt-3 truncate text-lg font-semibold text-slate-900" title="{{ $report->name }}">{{ $report->name }}</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ $report->created_at->format('d.m.Y H:i') }} · {{ $report->total_products }} ürün · {{ $report->original_filename }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 xl:col-span-7">
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                            <p class="text-xs font-medium text-slate-500">Hedefi geçen</p>
                            <p class="mt-1 text-xl font-bold text-emerald-700">{{ number_format($report->opportunity_count, 0, ',', '.') }}</p>
                        </div>
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                            <p class="text-xs font-medium text-slate-500">Seçili</p>
                            <p class="mt-1 text-xl font-bold text-slate-900">{{ number_format($metrics['selected_count'], 0, ',', '.') }}</p>
                        </div>
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                            <p class="text-xs font-medium text-slate-500">Seçili kampanya kârı</p>
                            <p class="mt-1 text-xl font-bold {{ $metrics['selected_campaign_profit'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $formatMoneyShort($metrics['selected_campaign_profit']) }}</p>
                        </div>
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                            <p class="text-xs font-medium text-slate-500">Satıcı indirim payı</p>
                            <p class="mt-1 text-xl font-bold text-rose-700">{{ $formatMoneyShort($metrics['selected_seller_discount']) }}</p>
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex flex-col gap-3 rounded-[8px] border border-slate-200 bg-slate-50/60 p-3 lg:flex-row lg:items-center lg:justify-between">
                    <div class="grid min-w-0 grid-cols-2 gap-2 text-xs sm:grid-cols-4">
                        <span class="rounded-[6px] border border-slate-200 bg-white px-2 py-1.5 text-slate-600">Maliyet kapsamı: <strong class="text-slate-900">{{ $formatPercent($metrics['cost_coverage']) }}</strong></span>
                        <span class="rounded-[6px] border border-slate-200 bg-white px-2 py-1.5 text-slate-600">Risk: <strong class="text-slate-900">{{ $metrics['risk_count'] }}</strong></span>
                        <span class="rounded-[6px] border border-slate-200 bg-white px-2 py-1.5 text-slate-600">Eşleşmeyen: <strong class="text-slate-900">{{ $metrics['unmatched_count'] }}</strong></span>
                        <span class="rounded-[6px] border border-slate-200 bg-white px-2 py-1.5 text-slate-600">Kâr azalır: <strong class="text-slate-900">{{ $metrics['negative_delta_count'] }}</strong></span>
                    </div>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <button
                            type="button"
                            wire:click="autoSelectProfitable"
                            class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800"
                        >
                            Kârlıları seç
                        </button>
                        <button
                            type="button"
                            x-on:click="window.dispatchEvent(new CustomEvent('openAiPanel', { detail: { tab: 'campaign' } }))"
                            class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                        >
                            AI karar merkezi
                        </button>
                    </div>
                </div>
            </section>

            <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 p-4 lg:p-5">
                    <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                        <div class="min-w-0">
                            <h3 class="text-sm font-semibold text-slate-900">Ürün ledger</h3>
                            <p class="mt-1 text-xs text-slate-500">{{ $metrics['filtered_count'] }} satır görüntüleniyor · hedef {{ $formatMultiplier(1 + ((float) $targetMarginPercent / 100)) }}</p>
                        </div>

                        <div class="flex flex-col gap-2 md:flex-row md:items-center">
                            <input
                                type="search"
                                wire:model.live.debounce.300ms="searchQuery"
                                placeholder="Ürün, barkod, stok veya ListingId ara"
                                class="min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-slate-900 md:w-72 sm:py-2 sm:text-sm"
                            >
                            <details class="relative">
                                <summary class="flex min-h-[44px] cursor-pointer list-none items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                                    Kolonlar
                                </summary>
                                <div class="absolute right-0 z-20 mt-2 w-56 rounded-[8px] border border-slate-200 bg-white p-2 shadow-lg">
                                    @foreach($visibleColumns as $column => $enabled)
                                        <label class="flex cursor-pointer items-center gap-2 rounded-[6px] px-2 py-2 text-sm text-slate-700 hover:bg-slate-50">
                                            <input type="checkbox" wire:click="toggleColumn('{{ $column }}')" @checked($enabled) class="rounded border-slate-300 text-slate-900">
                                            <span>{{ match($column) {
                                                'costs' => 'Maliyet',
                                                'current' => 'Mevcut',
                                                'campaign' => 'Kampanya',
                                                'discount' => 'Satıcı indirim payı',
                                                'price_action' => 'Fiyat aksiyonu',
                                                default => $column,
                                            } }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </details>
                            <button
                                type="button"
                                wire:click="exportSelected"
                                class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800"
                            >
                                Export
                            </button>
                        </div>
                    </div>

                    <div class="mt-3 flex gap-2 overflow-x-auto pb-1">
                        @foreach($statusFilters as $key => $label)
                            <button
                                type="button"
                                wire:click="setStatusFilter('{{ $key }}')"
                                class="whitespace-nowrap rounded-[6px] px-3 py-2 text-xs font-semibold transition {{ $statusFilter === $key ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}"
                            >
                                {{ $label }}
                            </button>
                        @endforeach
                        <button
                            type="button"
                            wire:click="clearTableFilters"
                            class="whitespace-nowrap rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-500 transition hover:bg-slate-50"
                        >
                            Temizle
                        </button>
                    </div>

                    <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div class="text-xs text-slate-500">
                            Seçili etki: <span class="font-semibold {{ $metrics['selected_impact'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $formatSignedMoney($metrics['selected_impact']) }}</span>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" wire:click="selectFilteredOpportunities" class="inline-flex min-h-[40px] items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                                Görünen kârlıları seç
                            </button>
                            <button type="button" wire:click="deselectAll" class="inline-flex min-h-[40px] items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                                Seçimi temizle
                            </button>
                        </div>
                    </div>
                </div>

                <div class="hidden md:block">
                    <div class="basket-scrollbar overflow-x-auto">
                        <table class="basket-ledger-table w-full border-collapse text-left text-sm" x-data="basketColumnResize">
                            <thead class="bg-slate-50 text-xs uppercase tracking-[0.04em] text-slate-500">
                                <tr class="border-b border-slate-200">
                                    <th class="w-12 px-3 py-3"></th>
                                    <th class="relative w-[290px] px-3 py-3">
                                        <button type="button" wire:click="sortTable('product_name')" class="flex items-center gap-2 font-semibold">
                                            Ürün
                                            <span class="text-[10px] text-slate-400">{{ $sortLabel('product_name') }}</span>
                                        </button>
                                        <span class="basket-resize-handle" x-on:mousedown.prevent="startResize($event, $el.parentElement)"></span>
                                    </th>
                                    @if($this->showColumn('costs'))
                                        <th class="relative w-[150px] px-3 py-3">
                                            <button type="button" wire:click="sortTable('total_cost')" class="flex items-center gap-2 font-semibold">
                                                Maliyet
                                                <span class="text-[10px] text-slate-400">{{ $sortLabel('total_cost') }}</span>
                                            </button>
                                            <span class="basket-resize-handle" x-on:mousedown.prevent="startResize($event, $el.parentElement)"></span>
                                        </th>
                                    @endif
                                    @if($this->showColumn('current'))
                                        <th class="relative w-[155px] px-3 py-3">
                                            <button type="button" wire:click="sortTable('current_net_profit')" class="flex items-center gap-2 font-semibold">
                                                Mevcut
                                                <span class="text-[10px] text-slate-400">{{ $sortLabel('current_net_profit') }}</span>
                                            </button>
                                            <span class="basket-resize-handle" x-on:mousedown.prevent="startResize($event, $el.parentElement)"></span>
                                        </th>
                                    @endif
                                    @if($this->showColumn('campaign'))
                                        <th class="relative w-[170px] px-3 py-3">
                                            <button type="button" wire:click="sortTable('suggested_net_profit')" class="flex items-center gap-2 font-semibold">
                                                Kampanya
                                                <span class="text-[10px] text-slate-400">{{ $sortLabel('suggested_net_profit') }}</span>
                                            </button>
                                            <span class="basket-resize-handle" x-on:mousedown.prevent="startResize($event, $el.parentElement)"></span>
                                        </th>
                                    @endif
                                    @if($this->showColumn('discount'))
                                        <th class="relative w-[145px] px-3 py-3">
                                            Satıcı indirim payı
                                            <span class="basket-resize-handle" x-on:mousedown.prevent="startResize($event, $el.parentElement)"></span>
                                        </th>
                                    @endif
                                    @if($this->showColumn('price_action'))
                                        <th class="relative w-[230px] px-3 py-3">
                                            Fiyat aksiyonu
                                            <span class="basket-resize-handle" x-on:mousedown.prevent="startResize($event, $el.parentElement)"></span>
                                        </th>
                                    @endif
                                    <th class="w-[130px] px-3 py-3">Durum</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($this->filteredItems as $item)
                                    @php
                                        $projected = $this->projectedNetProfit($item);
                                        $margin = $this->projectedMargin($item);
                                        $sellerDiscount = $this->campaignSellerDiscount($item);
                                        $targetMargin = (float) data_get($item->campaign_data, 'target_profitability_ratio', 1 + ((float) $targetMarginPercent / 100));
                                        $isSelected = in_array($item->id, $selectedItems, true) || (bool) $item->is_selected;
                                        $maxPrice = (float) data_get($item->campaign_data, 'max_price', $item->suggested_price);
                                        $status = match (true) {
                                            $item->totalCost() <= 0 => ['label' => 'Maliyet eksik', 'class' => 'border-amber-200 bg-amber-50 text-amber-700'],
                                            $projected < 0 => ['label' => 'Zarar riski', 'class' => 'border-rose-200 bg-rose-50 text-rose-700'],
                                            $item->action === 'update' && (float) $item->extra_profit < 0 => ['label' => 'Katılabilir', 'class' => 'border-sky-200 bg-sky-50 text-sky-700'],
                                            $item->action === 'update' => ['label' => 'Hedef üstü', 'class' => 'border-emerald-200 bg-emerald-50 text-emerald-700'],
                                            default => ['label' => 'Kontrol', 'class' => 'border-slate-200 bg-slate-50 text-slate-600'],
                                        };
                                    @endphp
                                    <tr wire:key="basket-row-{{ $item->id }}" class="{{ $isSelected ? 'bg-emerald-50/40' : 'bg-white hover:bg-slate-50/60' }}">
                                        <td class="px-3 py-4">
                                            <input type="checkbox" wire:click="toggleItem({{ $item->id }})" @checked($isSelected) class="h-4 w-4 rounded border-slate-300 text-slate-900">
                                        </td>
                                        <td class="px-3 py-4">
                                            <div class="min-w-0">
                                                <p class="truncate font-semibold text-slate-900" title="{{ $item->product_name }}">{{ $item->product_name ?: 'Ürün adı yok' }}</p>
                                                <p class="mt-1 truncate text-xs text-slate-500">{{ $item->stock_code ?: 'Stok kodu yok' }} · {{ $item->barcode ?: 'Barkod yok' }}</p>
                                                <p class="mt-1 truncate text-xs text-slate-400">{{ data_get($item->campaign_data, 'category') }} {{ data_get($item->campaign_data, 'brand') ? '· ' . data_get($item->campaign_data, 'brand') : '' }}</p>
                                            </div>
                                        </td>
                                        @if($this->showColumn('costs'))
                                            <td class="px-3 py-4">
                                                <p class="font-semibold text-slate-900">{{ $formatMoney($item->totalCost()) }}</p>
                                                <p class="mt-1 text-xs text-slate-500">Ürün {{ $formatMoney($item->production_cost) }}</p>
                                                <p class="text-xs text-slate-500">Lojistik {{ $formatMoney($item->shipping_cost) }}</p>
                                            </td>
                                        @endif
                                        @if($this->showColumn('current'))
                                            <td class="px-3 py-4">
                                                <p class="font-semibold text-slate-900">{{ $formatMoney($item->current_price) }}</p>
                                                <p class="mt-1 text-xs text-slate-500">Komisyon {{ $formatPercent($item->current_commission) }}</p>
                                                <p class="mt-1 font-semibold {{ (float) $item->current_net_profit >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $formatMoney($item->current_net_profit) }}</p>
                                            </td>
                                        @endif
                                        @if($this->showColumn('campaign'))
                                            <td class="px-3 py-4">
                                                <p class="font-semibold text-slate-900">{{ $formatMoney($item->custom_price ?: $maxPrice) }}</p>
                                                <p class="mt-1 text-xs text-slate-500">Hedef {{ $formatMultiplier($targetMargin) }}</p>
                                                <p class="mt-1 font-semibold {{ $projected >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $formatMoney($projected) }}</p>
                                                <p class="text-xs {{ $margin >= $targetMargin ? 'text-emerald-700' : 'text-amber-700' }}">Kârlılık {{ $formatMultiplier($margin) }}</p>
                                            </td>
                                        @endif
                                        @if($this->showColumn('discount'))
                                            <td class="px-3 py-4">
                                                <p class="font-semibold text-rose-700">{{ $formatMoney($sellerDiscount) }}</p>
                                                <p class="mt-1 text-xs text-slate-500">{{ $formatPercent(data_get($item->campaign_data, 'seller_share_percent', 60)) }} satıcı payı</p>
                                                <p class="mt-1 text-xs {{ (float) $item->extra_profit >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">Fark {{ $formatSignedMoney($item->extra_profit) }}</p>
                                            </td>
                                        @endif
                                        @if($this->showColumn('price_action'))
                                            <td class="px-3 py-4">
                                                <div class="flex gap-2">
                                                    <input
                                                        type="text"
                                                        value="{{ number_format((float) ($item->custom_price ?: $maxPrice), 2, ',', '.') }}"
                                                        wire:change="updateCustomPrice({{ $item->id }}, $event.target.value)"
                                                        class="min-h-[40px] w-28 rounded-[6px] border border-slate-200 bg-white px-2 py-2 text-sm text-slate-900 outline-none transition focus:border-slate-900"
                                                    >
                                                    <button type="button" wire:click="selectCampaignPrice({{ $item->id }})" class="inline-flex min-h-[40px] items-center justify-center rounded-[6px] bg-slate-900 px-3 py-2 text-xs font-semibold text-white transition hover:bg-slate-800">
                                                        Maks.
                                                    </button>
                                                </div>
                                                <div class="mt-2 flex flex-wrap gap-1.5">
                                                    <button type="button" wire:click="keepCurrentPrice({{ $item->id }})" class="rounded-[6px] border border-slate-200 bg-white px-2 py-1 text-[11px] font-semibold text-slate-600 transition hover:bg-slate-50">
                                                        Koruyorum
                                                    </button>
                                                    <button type="button" wire:click="getAiPriceSuggestion({{ $item->id }})" class="rounded-[6px] border border-slate-200 bg-white px-2 py-1 text-[11px] font-semibold text-slate-600 transition hover:bg-slate-50">
                                                        AI fiyat
                                                    </button>
                                                </div>
                                                @if(isset($suggestedPrices[$item->id]))
                                                    <div class="mt-2 rounded-[6px] border border-slate-200 bg-slate-50 p-2 text-xs text-slate-600">
                                                        @if($suggestedPrices[$item->id]['loading'] ?? false)
                                                            Öneri hazırlanıyor...
                                                        @elseif(isset($suggestedPrices[$item->id]['error']))
                                                            <span class="text-rose-600">{{ $suggestedPrices[$item->id]['error'] }}</span>
                                                        @else
                                                            <div class="font-semibold text-slate-900">{{ $formatMoney($suggestedPrices[$item->id]['price']) }}</div>
                                                            <p class="mt-1 line-clamp-2">{{ $suggestedPrices[$item->id]['reason'] }}</p>
                                                            <div class="mt-2 flex gap-1.5">
                                                                <button type="button" wire:click="applySuggestedPrice({{ $item->id }})" class="rounded-[6px] bg-slate-900 px-2 py-1 text-[11px] font-semibold text-white">Uygula</button>
                                                                <button type="button" wire:click="clearAiSuggestion({{ $item->id }})" class="rounded-[6px] border border-slate-200 bg-white px-2 py-1 text-[11px] font-semibold text-slate-600">Kapat</button>
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endif
                                            </td>
                                        @endif
                                        <td class="px-3 py-4">
                                            <span class="inline-flex rounded-[6px] border px-2 py-1 text-xs font-semibold {{ $status['class'] }}">{{ $status['label'] }}</span>
                                            @if(!$isSelected && $item->action === 'update')
                                                <p class="mt-2 text-xs text-slate-500">Seçilebilir</p>
                                            @elseif($isSelected)
                                                <p class="mt-2 text-xs font-semibold text-emerald-700">Exportta</p>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ $tableColspan }}" class="px-4 py-10 text-center text-sm text-slate-500">Bu filtrelerde ürün bulunamadı.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="space-y-3 p-4 md:hidden">
                    @forelse($this->filteredItems as $item)
                        @php
                            $projected = $this->projectedNetProfit($item);
                            $margin = $this->projectedMargin($item);
                            $sellerDiscount = $this->campaignSellerDiscount($item);
                            $targetMargin = (float) data_get($item->campaign_data, 'target_profitability_ratio', 1 + ((float) $targetMarginPercent / 100));
                            $isSelected = in_array($item->id, $selectedItems, true) || (bool) $item->is_selected;
                            $maxPrice = (float) data_get($item->campaign_data, 'max_price', $item->suggested_price);
                        @endphp
                        <article wire:key="basket-card-{{ $item->id }}" class="rounded-[8px] border border-slate-200 bg-white p-3 shadow-sm">
                            <div class="flex items-start gap-3">
                                <input type="checkbox" wire:click="toggleItem({{ $item->id }})" @checked($isSelected) class="mt-1 h-4 w-4 rounded border-slate-300 text-slate-900">
                                <div class="min-w-0 flex-1">
                                    <h4 class="text-sm font-semibold text-slate-900">{{ $item->product_name ?: 'Ürün adı yok' }}</h4>
                                    <p class="mt-1 text-xs text-slate-500">{{ $item->stock_code }} · {{ $item->barcode }}</p>
                                </div>
                            </div>
                            <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                                <div class="rounded-[6px] bg-slate-50 p-2">
                                    <p class="text-slate-500">Mevcut kâr</p>
                                    <p class="mt-1 font-semibold {{ (float) $item->current_net_profit >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $formatMoney($item->current_net_profit) }}</p>
                                </div>
                                <div class="rounded-[6px] bg-slate-50 p-2">
                                    <p class="text-slate-500">Kampanya kârı</p>
                                    <p class="mt-1 font-semibold {{ $projected >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $formatMoney($projected) }}</p>
                                </div>
                                <div class="rounded-[6px] bg-slate-50 p-2">
                                    <p class="text-slate-500">Maksimum tutar</p>
                                    <p class="mt-1 font-semibold text-slate-900">{{ $formatMoney($item->custom_price ?: $maxPrice) }}</p>
                                </div>
                                <div class="rounded-[6px] bg-slate-50 p-2">
                                    <p class="text-slate-500">Satıcı yükü</p>
                                    <p class="mt-1 font-semibold text-rose-700">{{ $formatMoney($sellerDiscount) }}</p>
                                </div>
                            </div>
                            <div class="mt-3 flex flex-col gap-2">
                                <input
                                    type="text"
                                    value="{{ number_format((float) ($item->custom_price ?: $maxPrice), 2, ',', '.') }}"
                                    wire:change="updateCustomPrice({{ $item->id }}, $event.target.value)"
                                    class="min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none transition focus:border-slate-900"
                                >
                                <div class="grid grid-cols-2 gap-2">
                                    <button type="button" wire:click="selectCampaignPrice({{ $item->id }})" class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] bg-slate-900 px-3 py-2 text-xs font-semibold text-white">Maksimumu seç</button>
                                    <button type="button" wire:click="keepCurrentPrice({{ $item->id }})" class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700">Koru</button>
                                </div>
                            </div>
                            <p class="mt-3 text-xs {{ $margin >= $targetMargin ? 'text-emerald-700' : 'text-amber-700' }}">Kârlılık {{ $formatMultiplier($margin) }} · Hedef {{ $formatMultiplier($targetMargin) }}</p>
                        </article>
                    @empty
                        <div class="rounded-[8px] border border-dashed border-slate-300 bg-slate-50 p-6 text-center text-sm text-slate-500">Bu filtrelerde ürün bulunamadı.</div>
                    @endforelse
                </div>
            </section>
        @endif
    @else
        <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Geçmiş sepet indirimi raporları</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $this->reports->count() }} rapor kayıtlı</p>
                </div>
                <button type="button" wire:click="resetAnalysis" class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800">
                    Yeni analiz
                </button>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @forelse($this->reports as $report)
                    <article class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="truncate text-sm font-semibold text-slate-900" title="{{ $report->name }}">{{ $report->name }}</h3>
                                <p class="mt-1 text-xs text-slate-500">{{ $report->created_at->format('d.m.Y H:i') }}</p>
                            </div>
                            <span class="rounded-[6px] border border-slate-200 bg-white px-2 py-1 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">Sepet</span>
                        </div>
                        <div class="mt-3 grid grid-cols-3 gap-2 rounded-[8px] border border-slate-200 bg-white p-3 text-center">
                            <div>
                                <p class="text-[10px] font-semibold uppercase tracking-[0.06em] text-slate-400">Ürün</p>
                                <p class="text-lg font-bold text-slate-900">{{ $report->total_products }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-semibold uppercase tracking-[0.06em] text-emerald-600">Fırsat</p>
                                <p class="text-lg font-bold text-emerald-700">{{ $report->opportunity_count }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-semibold uppercase tracking-[0.06em] text-slate-400">Etki</p>
                                <p class="text-sm font-bold {{ (float) $report->total_extra_profit >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $formatSignedMoney($report->total_extra_profit) }}</p>
                            </div>
                        </div>
                        @if($report->original_filename)
                            <p class="mt-3 truncate text-xs text-slate-400">{{ $report->original_filename }}</p>
                        @endif
                        <div class="mt-3 flex gap-2">
                            <button type="button" wire:click="viewReport({{ $report->id }})" class="inline-flex min-h-[40px] flex-1 items-center justify-center rounded-[6px] bg-slate-900 px-3 py-2 text-sm font-semibold text-white transition hover:bg-slate-800">
                                Görüntüle
                            </button>
                            <button type="button" wire:click="deleteReport({{ $report->id }})" wire:confirm="Bu raporu kalıcı olarak silmek istediğinize emin misiniz?" class="inline-flex min-h-[40px] items-center justify-center rounded-[6px] border border-rose-200 bg-white px-3 py-2 text-sm font-semibold text-rose-600 transition hover:bg-rose-50">
                                Sil
                            </button>
                        </div>
                    </article>
                @empty
                    <div class="col-span-full rounded-[8px] border border-dashed border-slate-300 bg-slate-50 p-8 text-center text-sm text-slate-500">
                        Henüz sepet indirimi raporu yok.
                    </div>
                @endforelse
            </div>
        </section>
    @endif

    @include('livewire.partials.campaign-ai-panel')
</div>
