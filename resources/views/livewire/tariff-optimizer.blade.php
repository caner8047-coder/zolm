@php
    $formatMoney = fn ($value) => '₺' . number_format((float) $value, 2, ',', '.');
    $formatMoneyShort = fn ($value) => '₺' . number_format((float) $value, 0, ',', '.');
    $formatSignedMoney = fn ($value) => ((float) $value >= 0 ? '+' : '-') . '₺' . number_format(abs((float) $value), 2, ',', '.');
    $formatPercent = fn ($value) => '%' . number_format((float) $value, 1, ',', '.');
    $formatMultiplier = fn ($value) => '%' . number_format((((float) $value) - 1) * 100, 1, ',', '.');
    $statusFilters = [
        'all' => 'Tümü',
        'opportunity' => 'Fırsat',
        'risk' => 'Risk',
        'selected' => 'Seçili',
        'missing_cost' => 'Maliyet eksik',
        'kept' => 'Korunan',
    ];
    $tariffColumns = [
        0 => ['key' => 'tariff_1', 'label' => 'Mevcut'],
        1 => ['key' => 'tariff_2', 'label' => '2. Tarife'],
        2 => ['key' => 'tariff_3', 'label' => '3. Tarife'],
        3 => ['key' => 'tariff_4', 'label' => '4. Tarife'],
    ];
    $visibleOptionalColumns = collect($visibleColumns)->filter()->count();
    $tableColspan = 3 + $visibleOptionalColumns;
    $sortLabel = fn ($field) => $sortField === $field ? ($sortDirection === 'asc' ? 'Artan' : 'Azalan') : 'Sırala';
@endphp

<div class="w-full space-y-4 overflow-x-hidden lg:space-y-6">
    @once
        <style>
            [x-cloak] {
                display: none !important;
            }

            .tariff-scrollbar::-webkit-scrollbar {
                height: 8px;
                width: 8px;
            }

            .tariff-scrollbar::-webkit-scrollbar-thumb {
                background: rgba(148, 163, 184, 0.55);
                border-radius: 999px;
            }

            .tariff-ledger-table {
                table-layout: fixed;
                min-width: 1160px;
            }

            .tariff-ledger-table th,
            .tariff-ledger-table td {
                vertical-align: top;
            }

            .tariff-ledger-table td {
                overflow: visible;
            }

            .tariff-resize-handle {
                position: absolute;
                inset-block: 0;
                right: 0;
                width: 4px;
                cursor: col-resize;
            }

            .tariff-resize-handle:hover,
            .tariff-resize-handle.active {
                background: #0f172a;
            }
        </style>
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('tariffColumnResize', () => ({
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

                            const newWidth = Math.max(96, this.startWidth + (moveEvent.pageX - this.startX));
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
                <h1 class="mt-3 text-xl font-bold text-slate-900 lg:text-2xl">Ürün Komisyon Tarifeleri</h1>
                <p class="mt-1 max-w-3xl text-sm text-slate-500">
                    Trendyol tarife dosyasını ürün maliyetleriyle eşleştirir, net kârı koruyan fiyat/tarife kararlarını görünür hale getirir.
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
                    <p class="mt-1 text-xs text-slate-500">Rapor bazlı destek</p>
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
                            <h2 class="text-lg font-semibold text-slate-900">Yeni tarife analizi</h2>
                            <p class="mt-1 text-sm text-slate-500">Seller Center komisyon Excel dosyasını yükleyin; motor mevcut formülüyle senaryoları hesaplasın.</p>
                        </div>
                        <div class="rounded-[6px] border border-slate-200 bg-slate-50/80 px-3 py-2 text-xs font-medium text-slate-600">
                            Max 10 MB · XLSX/XLS
                        </div>
                    </div>

                    <div class="mt-5 grid grid-cols-1 gap-3 lg:gap-4">
                        <label class="group cursor-pointer rounded-[8px] border border-dashed border-slate-300 bg-slate-50/60 p-4 transition hover:border-slate-400 hover:bg-white">
                            <input type="file" wire:model="tariffFile" accept=".xlsx,.xls" class="hidden">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex min-w-0 items-center gap-3">
                                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-[8px] bg-slate-900 text-white">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 16V4m0 12-4-4m4 4 4-4M4 20h16" />
                                        </svg>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-900">{{ $tariffFile ? $tariffFile->getClientOriginalName() : 'Trendyol komisyon tarife dosyası seçin' }}</p>
                                        <p class="mt-1 text-xs text-slate-500">Stok kodu, güncel fiyat, komisyon ve tarife kolonları otomatik eşleşir.</p>
                                    </div>
                                </div>
                                <span class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition group-hover:border-slate-300">
                                    Dosya seç
                                </span>
                            </div>
                        </label>
                        @error('tariffFile')
                            <p class="text-sm text-rose-600">{{ $message }}</p>
                        @enderror

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700">Rapor adı</label>
                            <input
                                type="text"
                                wire:model="reportName"
                                placeholder="Örn: Nisan 2026 Komisyon Tarife Kontrolü"
                                class="min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-slate-900 sm:py-2 sm:text-sm"
                            >
                        </div>
                    </div>

                    @if($tariffFile)
                        <div class="mt-5 flex flex-col gap-3 rounded-[8px] border border-emerald-200 bg-emerald-50 p-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0 text-sm text-emerald-800">
                                <span class="font-semibold">Dosya hazır.</span>
                                Analiz çalışınca mevcut tarife, alternatif tarifeler ve net kâr senaryoları kaydedilecek.
                            </div>
                            <div class="flex w-full gap-2 sm:w-auto">
                                <button
                                    type="button"
                                    wire:click="$set('tariffFile', null)"
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
                    <h3 class="text-sm font-semibold text-slate-900">Akıllı çalışma akışı</h3>
                    <div class="mt-4 space-y-3">
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                            <p class="text-sm font-semibold text-slate-900">1. Maliyet kontrolü</p>
                            <p class="mt-1 text-xs text-slate-500">COGS, ambalaj ve kargo maliyetleri Pazaryeri Ürünlerim üzerinden okunur.</p>
                        </div>
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                            <p class="text-sm font-semibold text-slate-900">2. Komisyon senaryosu</p>
                            <p class="mt-1 text-xs text-slate-500">Net kâr formülü korunur: fiyat eksi komisyon, ardından toplam maliyet.</p>
                        </div>
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                            <p class="text-sm font-semibold text-slate-900">3. AI destekli karar</p>
                            <p class="mt-1 text-xs text-slate-500">Rapor sonrasında strateji analizi, zarar denetimi ve satır bazlı fiyat önerisi aynı ekrandan çalışır.</p>
                        </div>
                    </div>
                </aside>
            </div>

            @if($this->costCount === 0)
                <div class="rounded-[10px] border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                    Pazaryeri Ürünlerim modülünde maliyetli ürün bulunamadı. Analiz çalışır, fakat maliyetler 0 kabul edildiği için kârlılık kararları güvenilir olmaz.
                </div>
            @endif
        @endif

        @if($step === 2)
            <section class="rounded-[10px] border border-slate-200 bg-white p-8 text-center shadow-sm lg:p-12">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full border border-slate-200 bg-slate-50">
                    <div class="h-8 w-8 animate-spin rounded-full border-2 border-slate-200 border-t-slate-900"></div>
                </div>
                <h2 class="mt-5 text-xl font-semibold text-slate-900">Komisyon motoru çalışıyor</h2>
                <p class="mt-2 text-sm text-slate-500">Tarife kolonları okunuyor, ürün maliyetleri eşleşiyor ve senaryolar hesaplanıyor.</p>
            </section>
        @endif

        @if($step === 3 && $this->activeReport)
            @php
                $report = $this->activeReport;
                $items = $this->filteredItems;
                $metrics = $this->reportMetrics;
                $topOpportunity = $metrics['top_opportunity'];
                $worstLoss = $metrics['worst_loss'];
            @endphp

            <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-1 text-xs font-medium text-slate-600">Tamamlanan rapor</span>
                            <span class="rounded-[6px] border border-slate-200 bg-white px-2 py-1 text-xs text-slate-500">{{ $report->created_at->format('d.m.Y H:i') }}</span>
                        </div>
                        <h2 class="mt-3 text-xl font-bold text-slate-900 lg:text-2xl">{{ $report->name }}</h2>
                        <p class="mt-1 truncate text-sm text-slate-500">{{ $report->original_filename }}</p>
                    </div>

                    <div class="flex w-full flex-col gap-2 sm:flex-row xl:w-auto">
                        <button type="button" wire:click="resetAnalysis" class="inline-flex min-h-[44px] flex-1 items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 sm:flex-none sm:py-2">
                            Yeni analiz
                        </button>
                        <button type="button" wire:click="generateAIAnalysis" x-on:click="$dispatch('openAiPanel', { tab: 'campaign' })" class="inline-flex min-h-[44px] flex-1 items-center justify-center rounded-[6px] border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm font-medium text-indigo-700 transition hover:bg-indigo-100 sm:flex-none sm:py-2">
                            <span wire:loading.remove wire:target="generateAIAnalysis">AI strateji</span>
                            <span wire:loading wire:target="generateAIAnalysis">AI çalışıyor...</span>
                        </button>
                        <button type="button" wire:click="exportSelected" class="inline-flex min-h-[44px] flex-1 items-center justify-center rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 sm:flex-none sm:py-2">
                            Düzeltme dosyası
                        </button>
                    </div>
                </div>

                <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                        <p class="text-xs font-medium text-slate-500">Analiz edilen</p>
                        <p class="mt-2 text-2xl font-bold text-slate-900">{{ number_format($report->total_products, 0, ',', '.') }}</p>
                    </div>
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                        <p class="text-xs font-medium text-slate-500">Fırsat</p>
                        <p class="mt-2 text-2xl font-bold text-emerald-700">{{ number_format($report->opportunity_count, 0, ',', '.') }}</p>
                    </div>
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                        <p class="text-xs font-medium text-slate-500">Ek kâr</p>
                        <p class="mt-2 text-2xl font-bold text-emerald-700">{{ $formatSignedMoney($report->total_extra_profit) }}</p>
                    </div>
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                        <p class="text-xs font-medium text-slate-500">Riskli satır</p>
                        <p class="mt-2 text-2xl font-bold text-rose-700">{{ number_format($metrics['risk_count'], 0, ',', '.') }}</p>
                    </div>
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                        <p class="text-xs font-medium text-slate-500">Maliyet kapsama</p>
                        <p class="mt-2 text-2xl font-bold text-slate-900">{{ $formatPercent($metrics['cost_coverage']) }}</p>
                    </div>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-4 lg:grid-cols-3 lg:gap-6">
                <div class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-900">AI strateji notu</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $report->ai_analysis ? 'Son analiz hazır. Panelden detayını okuyabilirsiniz.' : 'Raporu AI ile yorumlatıp aksiyon planı çıkarın.' }}</p>
                        </div>
                        <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-1 text-xs font-medium text-slate-600">{{ $report->ai_analysis ? 'Hazır' : 'Boş' }}</span>
                    </div>
                    <div class="mt-4 flex gap-2">
                        <button type="button" wire:click="generateAIAnalysis" class="inline-flex min-h-[44px] flex-1 items-center justify-center rounded-[6px] bg-slate-900 px-3 py-2 text-sm font-medium text-white transition hover:bg-slate-800">
                            <span wire:loading.remove wire:target="generateAIAnalysis">{{ $report->ai_analysis ? 'Yenile' : 'Başlat' }}</span>
                            <span wire:loading wire:target="generateAIAnalysis">İşleniyor...</span>
                        </button>
                        <button type="button" x-on:click="$dispatch('openAiPanel', { tab: 'campaign' })" class="inline-flex min-h-[44px] flex-1 items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                            Aç
                        </button>
                    </div>
                </div>

                <div class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-900">Zarar denetimi</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $worstLoss ? 'En zayıf satır: ' . \Illuminate\Support\Str::limit($worstLoss->product_name ?: $worstLoss->stock_code, 42) : 'Mevcut fiyatla zarar eden ürün görünmüyor.' }}</p>
                        </div>
                        <span class="rounded-[6px] border border-rose-200 bg-rose-50 px-2 py-1 text-xs font-medium text-rose-700">{{ number_format($metrics['risk_count'], 0, ',', '.') }} risk</span>
                    </div>
                    <div class="mt-4 flex gap-2">
                        <button type="button" wire:click="analyzeLosses" class="inline-flex min-h-[44px] flex-1 items-center justify-center rounded-[6px] bg-rose-600 px-3 py-2 text-sm font-medium text-white transition hover:bg-rose-700">
                            <span wire:loading.remove wire:target="analyzeLosses">{{ $report->loss_analysis ? 'Yenile' : 'Denetle' }}</span>
                            <span wire:loading wire:target="analyzeLosses">Denetleniyor...</span>
                        </button>
                        <button type="button" x-on:click="$dispatch('openAiPanel', { tab: 'loss' })" class="inline-flex min-h-[44px] flex-1 items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                            Aç
                        </button>
                    </div>
                </div>

                <div class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-900">Seçim etkisi</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $metrics['selected_count'] }} ürün seçili; tahmini etki {{ $formatSignedMoney($metrics['selected_impact']) }}.</p>
                        </div>
                        <span class="rounded-[6px] border border-emerald-200 bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700">{{ $formatMoneyShort($metrics['visible_extra_profit']) }}</span>
                    </div>
                    <div class="mt-4 flex gap-2">
                        <button type="button" wire:click="selectFilteredOpportunities" class="inline-flex min-h-[44px] flex-1 items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                            Görünen fırsatları seç
                        </button>
                        <button type="button" wire:click="toggleChat" x-on:click="$dispatch('openAiPanel', { tab: 'chat' })" class="inline-flex min-h-[44px] flex-1 items-center justify-center rounded-[6px] border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-white">
                            AI sohbet
                        </button>
                    </div>
                </div>
            </section>

            <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm" x-data="{ columnsOpen: false, profitabilityOpen: {{ $profitabilityTariffIndex !== null ? 'true' : 'false' }} }">
                <div class="border-b border-slate-200 p-4 lg:p-5">
                    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <button type="button" wire:click="$set('tabView', 'products')" class="rounded-[6px] px-3 py-2 text-sm font-medium transition {{ $tabView === 'products' ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50' }}">
                                    Ürün ledger
                                </button>
                                <button type="button" wire:click="$set('tabView', 'categories')" class="rounded-[6px] px-3 py-2 text-sm font-medium transition {{ $tabView === 'categories' ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50' }}">
                                    Kategori görünümü
                                </button>
                            </div>
                            <p class="mt-3 text-sm text-slate-500">
                                {{ number_format($metrics['filtered_count'], 0, ',', '.') }} satır gösteriliyor.
                                @if($statusFilter !== 'all' || $search || $profitabilityTariffIndex !== null)
                                    Aktif filtre uygulanıyor.
                                @endif
                            </p>
                        </div>

                        <div class="flex w-full flex-col gap-2 sm:flex-row xl:w-auto">
                            <button type="button" wire:click="selectAllOpportunities" class="inline-flex min-h-[44px] flex-1 items-center justify-center rounded-[6px] border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 transition hover:bg-emerald-100 sm:flex-none sm:py-2">
                                Tüm fırsatları seç
                            </button>
                            <button type="button" wire:click="deselectAll" class="inline-flex min-h-[44px] flex-1 items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 sm:flex-none sm:py-2">
                                Seçimi temizle
                            </button>
                            <button type="button" x-on:click="columnsOpen = !columnsOpen" class="inline-flex min-h-[44px] flex-1 items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 sm:flex-none sm:py-2">
                                Kolonlar
                            </button>
                        </div>
                    </div>

                    <div x-show="columnsOpen" x-cloak x-transition class="mt-3 rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-6">
                            @foreach([
                                'costs' => 'Maliyet',
                                'tariff_1' => 'Mevcut',
                                'tariff_2' => '2. Tarife',
                                'tariff_3' => '3. Tarife',
                                'tariff_4' => '4. Tarife',
                                'price_action' => 'Fiyat aksiyonu',
                            ] as $columnKey => $columnLabel)
                                <label class="flex min-h-[44px] cursor-pointer items-center gap-2 rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
                                    <input type="checkbox" wire:click="toggleColumn('{{ $columnKey }}')" @checked($visibleColumns[$columnKey] ?? false) class="rounded border-slate-300 text-slate-900 focus:ring-slate-900">
                                    <span>{{ $columnLabel }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-3 xl:grid-cols-12">
                        <div class="xl:col-span-4">
                            <label class="sr-only" for="tariff-search">Ürün ara</label>
                            <input
                                id="tariff-search"
                                type="search"
                                wire:model.live.debounce.300ms="search"
                                placeholder="Ürün, stok kodu veya barkod ara"
                                class="min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-slate-900 sm:py-2 sm:text-sm"
                            >
                        </div>

                        <div class="flex gap-2 overflow-x-auto pb-1 xl:col-span-5">
                            @foreach($statusFilters as $filterKey => $filterLabel)
                                <button type="button" wire:click="setStatusFilter('{{ $filterKey }}')" class="whitespace-nowrap rounded-[6px] border px-3 py-2 text-sm font-medium transition {{ $statusFilter === $filterKey ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}">
                                    {{ $filterLabel }}
                                </button>
                            @endforeach
                        </div>

                        <div class="flex gap-2 xl:col-span-3 xl:justify-end">
                            <button type="button" x-on:click="profitabilityOpen = !profitabilityOpen" class="inline-flex min-h-[44px] flex-1 items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 xl:flex-none">
                                Kârlılık filtresi
                            </button>
                            <button type="button" wire:click="clearTableFilters" class="inline-flex min-h-[44px] flex-1 items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 xl:flex-none">
                                Temizle
                            </button>
                        </div>
                    </div>

                    <div x-show="profitabilityOpen" x-cloak x-transition class="mt-3 rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-xs font-semibold text-slate-600">Hedef tarife</span>
                            @foreach([0 => 'Mevcut', 1 => '2. Tarife', 2 => '3. Tarife', 3 => '4. Tarife'] as $tIdx => $tName)
                                <button type="button" wire:click="setProfitabilityFilter({{ $tIdx }}, {{ $profitabilityMin ?? 0 }}, {{ $profitabilityMax ?? 999 }})" class="rounded-[6px] border px-3 py-2 text-xs font-semibold transition {{ $profitabilityTariffIndex === $tIdx ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}">
                                    {{ $tName }}
                                </button>
                            @endforeach
                            @if($profitabilityTariffIndex !== null)
                                <button type="button" wire:click="clearProfitabilityFilter" class="rounded-[6px] border border-rose-200 bg-white px-3 py-2 text-xs font-semibold text-rose-700 transition hover:bg-rose-50">
                                    Kârlılık temizle
                                </button>
                            @endif
                        </div>

                        @if($profitabilityTariffIndex !== null)
                            <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-slate-200 pt-3">
                                <span class="text-xs font-semibold text-slate-600">Kârlılık aralığı</span>
                                @foreach([[-100,0],[0,20],[20,40],[40,60],[60,100],[100,null]] as [$rMin, $rMax])
                                    <button type="button" wire:click="setProfitabilityFilter({{ $profitabilityTariffIndex }}, {{ $rMin }}, {{ $rMax ?? 'null' }})" class="rounded-[6px] border px-3 py-2 text-xs font-medium transition {{ $profitabilityMin == $rMin && ($profitabilityMax == $rMax || ($rMax === null && $profitabilityMax == 999)) ? 'border-emerald-700 bg-emerald-700 text-white' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}">
                                        {{ $rMax !== null ? "%{$rMin}-%{$rMax}" : "%{$rMin}+" }}
                                    </button>
                                @endforeach
                                <div class="flex items-center gap-2">
                                    <input type="number" x-ref="customMin" value="{{ $profitabilityMin ?? 0 }}" class="min-h-[44px] w-20 rounded-[6px] border border-slate-200 bg-white px-2 py-2 text-base text-slate-900 sm:text-sm" min="-100" max="1000" step="0.1">
                                    <span class="text-slate-400">-</span>
                                    <input type="number" x-ref="customMax" value="{{ $profitabilityMax != 999 ? $profitabilityMax : 100 }}" class="min-h-[44px] w-20 rounded-[6px] border border-slate-200 bg-white px-2 py-2 text-base text-slate-900 sm:text-sm" min="-100" max="1000" step="0.1">
                                    <button type="button" x-on:click="$wire.setProfitabilityFilter({{ $profitabilityTariffIndex }}, $refs.customMin.value, $refs.customMax.value)" class="min-h-[44px] rounded-[6px] bg-slate-900 px-3 py-2 text-sm font-medium text-white">
                                        Uygula
                                    </button>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                @if($tabView === 'products')
                    <div class="hidden md:block">
                        <div class="tariff-scrollbar overflow-x-auto" x-data="tariffColumnResize()">
                            <table class="tariff-ledger-table w-full text-xs">
                                <thead class="border-b border-slate-200 bg-slate-50 text-slate-600">
                                    <tr>
                                        <th class="relative w-16 px-3 py-3 text-left font-semibold">Seç</th>
                                        <th class="relative w-[280px] px-3 py-3 text-left font-semibold">
                                            <button type="button" wire:click="sortTable('product_name')" class="flex w-full items-center justify-between gap-2 text-left">
                                                <span>Ürün</span>
                                                <span class="text-[10px] text-slate-400">{{ $sortLabel('product_name') }}</span>
                                            </button>
                                            <span class="tariff-resize-handle" x-on:mousedown.prevent="startResize($event, $el.closest('th'))"></span>
                                        </th>
                                        <th class="relative w-36 px-3 py-3 text-right font-semibold">
                                            <button type="button" wire:click="sortTable('current_net_profit')" class="flex w-full items-center justify-between gap-2 text-left">
                                                <span>Mevcut</span>
                                                <span class="text-[10px] text-slate-400">{{ $sortLabel('current_net_profit') }}</span>
                                            </button>
                                            <span class="tariff-resize-handle" x-on:mousedown.prevent="startResize($event, $el.closest('th'))"></span>
                                        </th>
                                        @if($this->showColumn('costs'))
                                            <th class="relative w-32 px-3 py-3 text-right font-semibold">
                                                <button type="button" wire:click="sortTable('total_cost')" class="flex w-full items-center justify-between gap-2 text-left">
                                                    <span>Maliyet</span>
                                                    <span class="text-[10px] text-slate-400">{{ $sortLabel('total_cost') }}</span>
                                                </button>
                                                <span class="tariff-resize-handle" x-on:mousedown.prevent="startResize($event, $el.closest('th'))"></span>
                                            </th>
                                        @endif
                                        @foreach($tariffColumns as $idx => $column)
                                            @if($this->showColumn($column['key']))
                                                <th class="relative w-36 px-3 py-3 text-center font-semibold">
                                                    {{ $column['label'] }}
                                                    <span class="tariff-resize-handle" x-on:mousedown.prevent="startResize($event, $el.closest('th'))"></span>
                                                </th>
                                            @endif
                                        @endforeach
                                        @if($this->showColumn('price_action'))
                                            <th class="relative w-44 px-3 py-3 text-center font-semibold">Fiyat aksiyonu</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    @forelse($items as $item)
                                        @php
                                            $scenarios = $item->scenario_details ?? [];
                                            $selectedIdx = $item->selected_tariff_index;
                                            $statusClass = $item->action === 'update'
                                                ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                                : ($item->action === 'warning' ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-slate-200 bg-slate-50 text-slate-600');
                                            $statusLabel = $item->action === 'update' ? 'Fırsat' : ($item->action === 'warning' ? 'Risk' : 'Koru');
                                            $isChecked = in_array($item->id, $selectedItems, true);
                                            $displayPrice = $item->custom_price ?: ($item->suggested_price ?: $item->current_price);
                                            $suggestion = $suggestedPrices[$item->id] ?? null;
                                        @endphp
                                        <tr wire:key="tariff-row-{{ $item->id }}" class="transition hover:bg-slate-50/80">
                                            <td class="px-3 py-3">
                                                <button type="button" wire:click="toggleItem({{ $item->id }})" class="flex h-8 w-8 items-center justify-center rounded-[6px] border text-xs font-semibold transition {{ $isChecked ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-500 hover:border-slate-400' }}">
                                                    {{ $isChecked ? '✓' : '+' }}
                                                </button>
                                            </td>
                                            <td class="px-3 py-3">
                                                <div class="min-w-0">
                                                    <div class="flex items-start gap-2">
                                                        <span class="mt-0.5 shrink-0 rounded-[6px] border px-1.5 py-0.5 text-[10px] font-semibold {{ $statusClass }}">{{ $statusLabel }}</span>
                                                        <p class="min-w-0 text-sm font-semibold leading-5 text-slate-900">{{ $item->product_name ?: $item->stock_code }}</p>
                                                    </div>
                                                    <div class="mt-1 flex flex-wrap gap-2 text-[11px] text-slate-500">
                                                        <span class="font-mono">{{ $item->stock_code }}</span>
                                                        @if($item->barcode)
                                                            <span class="font-mono">{{ $item->barcode }}</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-3 py-3 text-right">
                                                <p class="font-semibold text-slate-900">{{ $formatMoney($item->current_price) }}</p>
                                                <p class="mt-1 text-[11px] text-slate-500">Kom. {{ $formatPercent($item->current_commission) }}</p>
                                                <p class="mt-1 font-semibold {{ (float) $item->current_net_profit >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $formatMoney($item->current_net_profit) }}</p>
                                            </td>
                                            @if($this->showColumn('costs'))
                                                <td class="px-3 py-3 text-right">
                                                    <p class="font-semibold text-slate-900">{{ $formatMoney($item->totalCost()) }}</p>
                                                    <p class="mt-1 text-[11px] text-slate-500">Üretim {{ $formatMoneyShort($item->production_cost) }}</p>
                                                    <p class="text-[11px] text-slate-500">Kargo {{ $formatMoneyShort($item->shipping_cost) }}</p>
                                                </td>
                                            @endif
                                            @foreach($tariffColumns as $idx => $column)
                                                @if($this->showColumn($column['key']))
                                                    @php
                                                        $scenario = $scenarios[$idx] ?? null;
                                                        $isSelected = $selectedIdx === $idx;
                                                        $isBest = $scenario && ($scenario['is_best'] ?? false);
                                                        $scenarioProfit = (float) ($scenario['net_profit'] ?? 0);
                                                        $scenarioRoi = (float) ($scenario['margin_pct'] ?? $item->profitMarginPercent($scenarioProfit));
                                                        $scenarioShell = $isSelected
                                                            ? 'border-slate-900 bg-slate-900 text-white'
                                                            : ($isBest ? 'border-emerald-200 bg-emerald-50 text-slate-900' : 'border-slate-200 bg-white text-slate-900 hover:border-slate-300 hover:bg-slate-50');
                                                    @endphp
                                                    <td class="px-2 py-3 text-center">
                                                        @if($scenario)
                                                            <button type="button" wire:click="selectTariff({{ $item->id }}, {{ $idx }})" class="w-full rounded-[8px] border p-2 text-left transition {{ $scenarioShell }}">
                                                                <div class="flex items-center justify-between gap-2">
                                                                    <span class="text-[11px] font-semibold {{ $isSelected ? 'text-slate-200' : 'text-slate-500' }}">{{ $scenario['name'] ?? $column['label'] }}</span>
                                                                    @if($isSelected)
                                                                        <span class="rounded-[6px] bg-white/15 px-1.5 py-0.5 text-[9px] font-semibold text-white">Seçili</span>
                                                                    @elseif($isBest)
                                                                        <span class="rounded-[6px] bg-emerald-600 px-1.5 py-0.5 text-[9px] font-semibold text-white">En iyi</span>
                                                                    @endif
                                                                </div>
                                                                <p class="mt-1 text-sm font-bold">{{ $formatMoney($scenario['price'] ?? 0) }}</p>
                                                                <p class="mt-1 text-[11px] {{ $isSelected ? 'text-slate-200' : 'text-slate-500' }}">Kom. {{ $formatPercent($scenario['commission'] ?? 0) }}</p>
                                                                <p class="mt-1 text-sm font-bold {{ $isSelected ? 'text-white' : ($scenarioProfit >= 0 ? 'text-emerald-700' : 'text-rose-700') }}">{{ $formatMoney($scenarioProfit) }}</p>
                                                                <p class="mt-1 text-[11px] {{ $isSelected ? 'text-slate-200' : 'text-slate-500' }}">Kârlılık {{ $formatMultiplier($scenarioRoi) }}</p>
                                                            </button>
                                                        @else
                                                            <div class="rounded-[8px] border border-dashed border-slate-200 bg-slate-50 p-3 text-center text-slate-400">Yok</div>
                                                        @endif
                                                    </td>
                                                @endif
                                            @endforeach
                                            @if($this->showColumn('price_action'))
                                                <td class="px-3 py-3">
                                                    <div class="space-y-2" x-data>
                                                        <div class="flex items-center gap-2">
                                                            <input type="number" x-ref="price{{ $item->id }}" value="{{ number_format((float) $displayPrice, 2, '.', '') }}" step="0.01" min="0" class="min-h-[40px] w-full rounded-[6px] border border-slate-200 bg-white px-2 py-2 text-right text-sm font-semibold text-slate-900 outline-none focus:border-slate-900">
                                                            <button type="button" wire:click="getAiPriceSuggestion({{ $item->id }})" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-[6px] border border-indigo-200 bg-indigo-50 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100" title="AI fiyat öner">
                                                                <span wire:loading.remove wire:target="getAiPriceSuggestion({{ $item->id }})">AI</span>
                                                                <span wire:loading wire:target="getAiPriceSuggestion({{ $item->id }})" class="h-4 w-4 animate-spin rounded-full border-2 border-indigo-200 border-t-indigo-700"></span>
                                                            </button>
                                                        </div>
                                                        <button type="button" x-on:click="$wire.updateCustomPrice({{ $item->id }}, $refs.price{{ $item->id }}.value)" class="min-h-[40px] w-full rounded-[6px] bg-slate-900 px-3 py-2 text-xs font-semibold text-white transition hover:bg-slate-800">
                                                            Kaydet
                                                        </button>
                                                        @if($suggestion && !($suggestion['loading'] ?? false))
                                                            <div class="rounded-[6px] border {{ isset($suggestion['error']) ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-indigo-200 bg-indigo-50 text-indigo-800' }} p-2 text-[11px]">
                                                                @if(isset($suggestion['error']))
                                                                    {{ $suggestion['error'] }}
                                                                @else
                                                                    <div class="flex items-center justify-between gap-2">
                                                                        <span class="font-semibold">{{ $formatMoney($suggestion['price']) }}</span>
                                                                        <button type="button" wire:click="applySuggestedPrice({{ $item->id }})" class="rounded-[6px] bg-indigo-700 px-2 py-1 text-[10px] font-semibold text-white">Uygula</button>
                                                                    </div>
                                                                    <p class="mt-1 text-indigo-700">{{ \Illuminate\Support\Str::limit($suggestion['reason'] ?? '', 72) }}</p>
                                                                @endif
                                                            </div>
                                                        @endif
                                                    </div>
                                                </td>
                                            @endif
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="{{ $tableColspan }}" class="px-4 py-10 text-center text-sm text-slate-500">
                                                Bu filtrelerle eşleşen ürün bulunamadı.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="divide-y divide-slate-200 md:hidden">
                        @forelse($items as $item)
                            @php
                                $scenarios = $item->scenario_details ?? [];
                                $selectedIdx = $item->selected_tariff_index;
                                $displayPrice = $item->custom_price ?: ($item->suggested_price ?: $item->current_price);
                                $suggestion = $suggestedPrices[$item->id] ?? null;
                                $isChecked = in_array($item->id, $selectedItems, true);
                            @endphp
                            <article wire:key="tariff-mobile-{{ $item->id }}" class="p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-slate-900">{{ $item->product_name ?: $item->stock_code }}</p>
                                        <p class="mt-1 font-mono text-[11px] text-slate-500">{{ $item->stock_code }}</p>
                                    </div>
                                    <button type="button" wire:click="toggleItem({{ $item->id }})" class="shrink-0 rounded-[6px] border px-2 py-1 text-xs font-semibold {{ $isChecked ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-600' }}">
                                        {{ $isChecked ? 'Seçili' : 'Seç' }}
                                    </button>
                                </div>

                                <div class="mt-3 grid grid-cols-2 gap-2">
                                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                                        <p class="text-xs text-slate-500">Mevcut kâr</p>
                                        <p class="mt-1 font-semibold {{ (float) $item->current_net_profit >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $formatMoney($item->current_net_profit) }}</p>
                                    </div>
                                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                                        <p class="text-xs text-slate-500">Toplam maliyet</p>
                                        <p class="mt-1 font-semibold text-slate-900">{{ $formatMoney($item->totalCost()) }}</p>
                                    </div>
                                </div>

                                <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                    @foreach($tariffColumns as $idx => $column)
                                        @php
                                            $scenario = $scenarios[$idx] ?? null;
                                            $isSelected = $selectedIdx === $idx;
                                            $isBest = $scenario && ($scenario['is_best'] ?? false);
                                            $scenarioProfit = (float) ($scenario['net_profit'] ?? 0);
                                            $scenarioShell = $isSelected
                                                ? 'border-slate-900 bg-slate-900 text-white'
                                                : ($isBest ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200 bg-white');
                                        @endphp
                                        @if($scenario)
                                            <button type="button" wire:click="selectTariff({{ $item->id }}, {{ $idx }})" class="rounded-[8px] border p-3 text-left {{ $scenarioShell }}">
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="text-xs font-semibold {{ $isSelected ? 'text-slate-200' : 'text-slate-500' }}">{{ $scenario['name'] ?? $column['label'] }}</span>
                                                    @if($isSelected)
                                                        <span class="rounded-[6px] bg-white/15 px-1.5 py-0.5 text-[10px] text-white">Seçili</span>
                                                    @elseif($isBest)
                                                        <span class="rounded-[6px] bg-emerald-600 px-1.5 py-0.5 text-[10px] text-white">En iyi</span>
                                                    @endif
                                                </div>
                                                <div class="mt-2 flex items-end justify-between gap-2">
                                                    <div>
                                                        <p class="text-sm font-bold">{{ $formatMoney($scenario['price'] ?? 0) }}</p>
                                                        <p class="mt-1 text-xs {{ $isSelected ? 'text-slate-200' : 'text-slate-500' }}">Kom. {{ $formatPercent($scenario['commission'] ?? 0) }}</p>
                                                    </div>
                                                    <p class="text-sm font-bold {{ $isSelected ? 'text-white' : ($scenarioProfit >= 0 ? 'text-emerald-700' : 'text-rose-700') }}">{{ $formatMoney($scenarioProfit) }}</p>
                                                </div>
                                            </button>
                                        @endif
                                    @endforeach
                                </div>

                                <div class="mt-3 rounded-[8px] border border-slate-200 bg-slate-50/70 p-3" x-data>
                                    <p class="text-xs font-semibold text-slate-600">Fiyat aksiyonu</p>
                                    <div class="mt-2 flex items-center gap-2">
                                        <input type="number" x-ref="mobilePrice{{ $item->id }}" value="{{ number_format((float) $displayPrice, 2, '.', '') }}" step="0.01" min="0" class="min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-2 py-2 text-base font-semibold text-slate-900 outline-none sm:text-sm">
                                        <button type="button" wire:click="getAiPriceSuggestion({{ $item->id }})" class="min-h-[44px] rounded-[6px] border border-indigo-200 bg-indigo-50 px-3 text-sm font-semibold text-indigo-700">AI</button>
                                    </div>
                                    <button type="button" x-on:click="$wire.updateCustomPrice({{ $item->id }}, $refs.mobilePrice{{ $item->id }}.value)" class="mt-2 min-h-[44px] w-full rounded-[6px] bg-slate-900 px-3 py-2 text-sm font-semibold text-white">
                                        Kaydet
                                    </button>
                                    @if($suggestion && !($suggestion['loading'] ?? false) && !isset($suggestion['error']))
                                        <div class="mt-2 rounded-[6px] border border-indigo-200 bg-indigo-50 p-2 text-xs text-indigo-800">
                                            <div class="flex items-center justify-between gap-2">
                                                <span class="font-semibold">{{ $formatMoney($suggestion['price']) }}</span>
                                                <button type="button" wire:click="applySuggestedPrice({{ $item->id }})" class="rounded-[6px] bg-indigo-700 px-2 py-1 text-[11px] font-semibold text-white">Uygula</button>
                                            </div>
                                            <p class="mt-1">{{ \Illuminate\Support\Str::limit($suggestion['reason'] ?? '', 80) }}</p>
                                        </div>
                                    @endif
                                </div>
                            </article>
                        @empty
                            <div class="p-8 text-center text-sm text-slate-500">Bu filtrelerle eşleşen ürün bulunamadı.</div>
                        @endforelse
                    </div>
                @endif

                @if($tabView === 'categories')
                    <div class="p-4 lg:p-5">
                        <div class="mb-4 rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                            <h3 class="text-sm font-semibold text-slate-900">Kategori performansı</h3>
                            <p class="mt-1 text-sm text-slate-500">{{ count($this->categoryReport) }} kategori ürün adlarından otomatik ayrıştırıldı.</p>
                        </div>

                        @if(count($this->categoryReport) === 0)
                            <div class="rounded-[8px] border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">
                                Kategori verisi bulunamadı.
                            </div>
                        @else
                            <div class="hidden overflow-hidden rounded-[8px] border border-slate-200 md:block">
                                <table class="w-full text-sm">
                                    <thead class="bg-slate-50 text-xs font-semibold text-slate-600">
                                        <tr>
                                            <th class="px-4 py-3 text-left">Kategori</th>
                                            <th class="px-4 py-3 text-center">Ürün</th>
                                            <th class="px-4 py-3 text-right">Maliyet</th>
                                            <th class="px-4 py-3 text-right">Ciro</th>
                                            <th class="px-4 py-3 text-right">Net kâr</th>
                                            <th class="px-4 py-3 text-right">Marj</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 bg-white">
                                        @foreach($this->categoryReport as $cat)
                                            @php $margin = $cat['revenue'] > 0 ? ($cat['profit'] / $cat['revenue']) * 100 : 0; @endphp
                                            <tr class="hover:bg-slate-50">
                                                <td class="px-4 py-3 font-semibold text-slate-900">{{ $cat['name'] }}</td>
                                                <td class="px-4 py-3 text-center text-slate-600">{{ number_format($cat['count'], 0, ',', '.') }}</td>
                                                <td class="px-4 py-3 text-right text-slate-600">{{ $formatMoney($cat['cost']) }}</td>
                                                <td class="px-4 py-3 text-right text-slate-900">{{ $formatMoney($cat['revenue']) }}</td>
                                                <td class="px-4 py-3 text-right font-semibold {{ $cat['profit'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $formatMoney($cat['profit']) }}</td>
                                                <td class="px-4 py-3 text-right font-semibold text-slate-900">{{ $formatPercent($margin) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="grid grid-cols-1 gap-3 md:hidden">
                                @foreach($this->categoryReport as $cat)
                                    @php $margin = $cat['revenue'] > 0 ? ($cat['profit'] / $cat['revenue']) * 100 : 0; @endphp
                                    <div class="rounded-[8px] border border-slate-200 bg-white p-4">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <p class="font-semibold text-slate-900">{{ $cat['name'] }}</p>
                                                <p class="mt-1 text-xs text-slate-500">{{ number_format($cat['count'], 0, ',', '.') }} ürün</p>
                                            </div>
                                            <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-1 text-xs font-semibold text-slate-700">{{ $formatPercent($margin) }}</span>
                                        </div>
                                        <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
                                            <div class="rounded-[6px] bg-slate-50 p-2 text-slate-600">Ciro<br><span class="font-semibold text-slate-900">{{ $formatMoney($cat['revenue']) }}</span></div>
                                            <div class="rounded-[6px] bg-slate-50 p-2 text-slate-600">Net kâr<br><span class="font-semibold {{ $cat['profit'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $formatMoney($cat['profit']) }}</span></div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
            </section>
        @endif
    @endif

    @if($activeTab === 'history')
        <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Geçmiş komisyon raporları</h2>
                    <p class="mt-1 text-sm text-slate-500">Önceki analizleri açıp aynı AI ve export akışını kullanabilirsiniz.</p>
                </div>
                <button type="button" wire:click="switchTab('analyze')" class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 sm:py-2">
                    Yeni analiz
                </button>
            </div>

            @if($this->reports->isEmpty())
                <div class="mt-5 rounded-[8px] border border-dashed border-slate-300 bg-slate-50/60 p-8 text-center">
                    <h3 class="text-sm font-semibold text-slate-900">Henüz rapor yok</h3>
                    <p class="mt-1 text-sm text-slate-500">İlk tarife dosyasını analiz ettiğinizde raporlar burada listelenir.</p>
                </div>
            @else
                <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach($this->reports as $rpt)
                        <article class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-4 transition hover:bg-white hover:shadow-sm">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h3 class="truncate text-sm font-semibold text-slate-900">{{ $rpt->name }}</h3>
                                    <p class="mt-1 text-xs text-slate-500">{{ $rpt->created_at->format('d.m.Y H:i') }}</p>
                                </div>
                                <span class="rounded-[6px] border border-slate-200 bg-white px-2 py-1 text-[11px] font-medium text-slate-600">{{ $rpt->status === 'exported' ? 'İndirildi' : ($rpt->status === 'applied' ? 'Uygulandı' : 'Tamamlandı') }}</span>
                            </div>
                            <div class="mt-4 grid grid-cols-3 gap-2">
                                <div class="rounded-[6px] border border-slate-200 bg-white p-2">
                                    <p class="text-[11px] text-slate-500">Ürün</p>
                                    <p class="mt-1 font-semibold text-slate-900">{{ number_format($rpt->total_products, 0, ',', '.') }}</p>
                                </div>
                                <div class="rounded-[6px] border border-slate-200 bg-white p-2">
                                    <p class="text-[11px] text-slate-500">Fırsat</p>
                                    <p class="mt-1 font-semibold text-emerald-700">{{ number_format($rpt->opportunity_count, 0, ',', '.') }}</p>
                                </div>
                                <div class="rounded-[6px] border border-slate-200 bg-white p-2">
                                    <p class="text-[11px] text-slate-500">Ek kâr</p>
                                    <p class="mt-1 font-semibold text-emerald-700">{{ $formatMoneyShort($rpt->total_extra_profit) }}</p>
                                </div>
                            </div>
                            @if($rpt->original_filename)
                                <p class="mt-3 truncate text-xs text-slate-500">{{ $rpt->original_filename }}</p>
                            @endif
                            <div class="mt-4 flex gap-2">
                                <button type="button" wire:click="viewReport({{ $rpt->id }})" class="inline-flex min-h-[44px] flex-1 items-center justify-center rounded-[6px] bg-slate-900 px-3 py-2 text-sm font-medium text-white transition hover:bg-slate-800">
                                    Görüntüle
                                </button>
                                <button type="button" wire:click="deleteReport({{ $rpt->id }})" wire:confirm="Bu raporu silmek istediğinize emin misiniz?" class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] border border-rose-200 bg-white px-3 py-2 text-sm font-medium text-rose-700 transition hover:bg-rose-50">
                                    Sil
                                </button>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    @endif

    @include('livewire.partials.campaign-ai-panel', ['themeColor' => 'indigo'])
</div>

@script
<script>
    Livewire.on('chat-scroll', () => {
        const el = document.getElementById('chat-messages');
        if (el) {
            setTimeout(() => el.scrollTop = el.scrollHeight, 100);
        }
    });
</script>
@endscript
