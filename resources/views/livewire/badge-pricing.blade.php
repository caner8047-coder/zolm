@php
    $formatMoney = fn ($value) => '₺' . number_format((float) $value, 2, ',', '.');
    $formatMoneyShort = fn ($value) => '₺' . number_format((float) $value, 0, ',', '.');
    $formatSignedMoney = fn ($value) => ((float) $value >= 0 ? '+' : '-') . '₺' . number_format(abs((float) $value), 2, ',', '.');
    $formatPercent = fn ($value) => '%' . number_format((float) $value, 1, ',', '.');
    $formatMultiplier = fn ($value) => '%' . number_format((((float) $value) - 1) * 100, 1, ',', '.');
    $statusFilters = [
        'all' => 'Tümü',
        'opportunity' => 'Yıldız avantajlı',
        'risk' => 'Risk',
        'selected' => 'Seçili',
        'missing_cost' => 'Maliyet eksik',
        'unmatched' => 'Eşleşmeyen',
        'kept' => 'Korunan',
        'star1' => '1 Yıldız',
        'star2' => '2 Yıldız',
        'star3' => '3 Yıldız',
    ];
    $scenarioColumns = [
        0 => ['key' => 'current', 'label' => 'Mevcut'],
        1 => ['key' => 'star1', 'label' => '1 Yıldız'],
        2 => ['key' => 'star2', 'label' => '2 Yıldız'],
        3 => ['key' => 'star3', 'label' => '3 Yıldız'],
    ];
    $visibleOptionalColumns = collect($visibleColumns)->filter()->count();
    $tableColspan = 2 + $visibleOptionalColumns;
    $sortLabel = fn ($field) => $sortField === $field ? ($sortDirection === 'asc' ? 'Artan' : 'Azalan') : 'Sırala';
@endphp

<div class="w-full space-y-4 overflow-x-hidden lg:space-y-6" x-data>
    @once
        <style>
            [x-cloak] {
                display: none !important;
            }

            .badge-scrollbar::-webkit-scrollbar {
                height: 8px;
                width: 8px;
            }

            .badge-scrollbar::-webkit-scrollbar-thumb {
                background: rgba(148, 163, 184, 0.55);
                border-radius: 999px;
            }

            .badge-ledger-table {
                table-layout: fixed;
                min-width: 1180px;
            }

            .badge-ledger-table th,
            .badge-ledger-table td {
                vertical-align: top;
            }

            .badge-resize-handle {
                position: absolute;
                inset-block: 0;
                right: 0;
                width: 4px;
                cursor: col-resize;
            }

            .badge-resize-handle:hover,
            .badge-resize-handle.active {
                background: #0f172a;
            }
        </style>
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('badgeColumnResize', () => ({
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
                <h1 class="mt-3 text-xl font-bold text-slate-900 lg:text-2xl">Avantajlı Ürün Etiketleri</h1>
                <p class="mt-1 max-w-3xl text-sm text-slate-500">
                    Yıldız fiyat limitlerini mevcut fiyat, komisyon ve ürün maliyetiyle karşılaştırır; seçim, fiyat ve export kararını tek yüzeyde toplar.
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
                    <p class="mt-1 text-xs text-slate-500">Yıldız bağlamlı</p>
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
                            <h2 class="text-lg font-semibold text-slate-900">Yeni yıldız analizi</h2>
                            <p class="mt-1 text-sm text-slate-500">Avantajlı Ürün Excel dosyasını yükleyin; motor mevcut fiyat ve 1/2/3 yıldız limitlerini aynı formülle hesaplasın.</p>
                        </div>
                        <div class="rounded-[6px] border border-slate-200 bg-slate-50/80 px-3 py-2 text-xs font-medium text-slate-600">
                            Max 10 MB · XLSX/XLS
                        </div>
                    </div>

                    <div class="mt-5 grid grid-cols-1 gap-3 lg:gap-4">
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
                                        <p class="truncate text-sm font-semibold text-slate-900">{{ $excelFile ? $excelFile->getClientOriginalName() : 'Avantajlı Ürün Etiketleri dosyası seçin' }}</p>
                                        <p class="mt-1 text-xs text-slate-500">Ürün, barkod, mevcut fiyat ve yıldız üst fiyat kolonları otomatik eşleşir.</p>
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

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700">Rapor adı</label>
                            <input
                                type="text"
                                wire:model="reportName"
                                placeholder="Örn: Nisan 2026 Yıldız Etiketi Kontrolü"
                                class="min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-slate-900 sm:py-2 sm:text-sm"
                            >
                        </div>
                    </div>

                    @if($excelFile)
                        <div class="mt-5 flex flex-col gap-3 rounded-[8px] border border-emerald-200 bg-emerald-50 p-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0 text-sm text-emerald-800">
                                <span class="font-semibold">Dosya hazır.</span>
                                Analiz çalışınca yıldız senaryoları kalıcı rapora yazılacak.
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
                    <h3 class="text-sm font-semibold text-slate-900">Yıldız karar akışı</h3>
                    <div class="mt-4 space-y-3">
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                            <p class="text-sm font-semibold text-slate-900">1. Eşleşme</p>
                            <p class="mt-1 text-xs text-slate-500">Barkod, model kodu ve ürün adıyla maliyet kartı bulunur.</p>
                        </div>
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                            <p class="text-sm font-semibold text-slate-900">2. Senaryo kıyaslaması</p>
                            <p class="mt-1 text-xs text-slate-500">Mevcut fiyat ve 1/2/3 yıldız limitleri aynı net kâr formülüyle karşılaştırılır.</p>
                        </div>
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                            <p class="text-sm font-semibold text-slate-900">3. AI destekli karar</p>
                            <p class="mt-1 text-xs text-slate-500">AI önerisi, komisyonu ve yıldız senaryolarını bağlam olarak kullanır.</p>
                        </div>
                    </div>
                </aside>
            </div>

            @if($this->costCount === 0)
                <div class="rounded-[10px] border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                    Pazaryeri Ürünlerim modülünde maliyetli ürün bulunamadı. Analiz çalışır, fakat yıldız kârlılık kararları güvenilir olmaz.
                </div>
            @endif
        @endif

        @if($step === 2)
            <section class="rounded-[10px] border border-slate-200 bg-white p-8 text-center shadow-sm lg:p-12">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full border border-slate-200 bg-slate-50">
                    <div class="h-8 w-8 animate-spin rounded-full border-2 border-slate-200 border-t-slate-900"></div>
                </div>
                <h2 class="mt-5 text-xl font-semibold text-slate-900">Yıldız etiket motoru çalışıyor</h2>
                <p class="mt-2 text-sm text-slate-500">Ürünler eşleşiyor, maliyetler okunuyor ve yıldız limitleri net kâra çevriliyor.</p>
            </section>
        @endif

        @if($step === 3 && $this->activeReport)
            @php
                $report = $this->activeReport;
                $items = $this->filteredItems;
                $metrics = $this->reportMetrics;
                $topOpportunity = $metrics['top_opportunity'];
                $worstLoss = $metrics['worst_loss'];
                $starCounts = $metrics['star_counts'];
            @endphp

            <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-1 text-xs font-medium text-slate-600">Tamamlanan yıldız raporu</span>
                            <span class="rounded-[6px] border border-slate-200 bg-white px-2 py-1 text-xs text-slate-500">{{ $report->created_at->format('d.m.Y H:i') }}</span>
                        </div>
                        <h2 class="mt-3 text-xl font-bold text-slate-900 lg:text-2xl">{{ $report->name }}</h2>
                        <p class="mt-1 truncate text-sm text-slate-500">{{ $report->original_filename }}</p>
                    </div>

                    <div class="flex w-full flex-col gap-2 sm:flex-row xl:w-auto">
                        <button type="button" wire:click="resetAnalysis" class="inline-flex min-h-[44px] flex-1 items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 sm:flex-none sm:py-2">
                            Yeni analiz
                        </button>
                        <button type="button" wire:click="generateAIAnalysis" x-on:click="$dispatch('openAiPanel', { tab: 'campaign' })" class="inline-flex min-h-[44px] flex-1 items-center justify-center rounded-[6px] border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800 transition hover:bg-amber-100 sm:flex-none sm:py-2">
                            <span wire:loading.remove wire:target="generateAIAnalysis">AI strateji</span>
                            <span wire:loading wire:target="generateAIAnalysis">AI çalışıyor...</span>
                        </button>
                        <button type="button" wire:click="exportSelected" class="inline-flex min-h-[44px] flex-1 items-center justify-center rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 sm:flex-none sm:py-2">
                            Excel indir
                        </button>
                    </div>
                </div>

                <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                        <p class="text-xs font-medium text-slate-500">Analiz edilen</p>
                        <p class="mt-2 text-2xl font-bold text-slate-900">{{ number_format($report->total_products, 0, ',', '.') }}</p>
                    </div>
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                        <p class="text-xs font-medium text-slate-500">Yıldız avantajlı</p>
                        <p class="mt-2 text-2xl font-bold text-amber-700">{{ number_format($report->opportunity_count, 0, ',', '.') }}</p>
                    </div>
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                        <p class="text-xs font-medium text-slate-500">Ek kâr</p>
                        <p class="mt-2 text-2xl font-bold {{ (float) $report->total_extra_profit >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $formatSignedMoney($report->total_extra_profit) }}</p>
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
                            <p class="text-sm font-semibold text-slate-900">Yıldız dağılımı</p>
                            <p class="mt-1 text-xs text-slate-500">1Y: {{ $starCounts[1] }} · 2Y: {{ $starCounts[2] }} · 3Y: {{ $starCounts[3] }} · Mevcut: {{ $starCounts[0] }}</p>
                        </div>
                        <span class="rounded-[6px] border border-amber-200 bg-amber-50 px-2 py-1 text-xs font-medium text-amber-800">{{ $report->ai_analysis ? 'Hazır' : 'AI bekliyor' }}</span>
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
                        <span class="rounded-[6px] border border-amber-200 bg-amber-50 px-2 py-1 text-xs font-medium text-amber-800">{{ $formatMoneyShort($metrics['visible_extra_profit']) }}</span>
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

            <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm" x-data="{ columnsOpen: false }">
                <div class="border-b border-slate-200 p-4 lg:p-5">
                    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                        <div class="min-w-0">
                            <h3 class="text-lg font-semibold text-slate-900">Yıldız karar ledger'ı</h3>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ number_format($metrics['filtered_count'], 0, ',', '.') }} satır gösteriliyor.
                                @if($statusFilter !== 'all' || $searchQuery)
                                    Aktif filtre uygulanıyor.
                                @endif
                            </p>
                        </div>

                        <div class="flex w-full flex-col gap-2 sm:flex-row xl:w-auto">
                            <button type="button" wire:click="selectAllOpportunities" class="inline-flex min-h-[44px] flex-1 items-center justify-center rounded-[6px] border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800 transition hover:bg-amber-100 sm:flex-none sm:py-2">
                                Tüm avantajlıları seç
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
                                'current' => 'Mevcut',
                                'star1' => '1 Yıldız',
                                'star2' => '2 Yıldız',
                                'star3' => '3 Yıldız',
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
                            <label class="sr-only" for="badge-search">Ürün ara</label>
                            <input
                                id="badge-search"
                                type="search"
                                wire:model.live.debounce.300ms="searchQuery"
                                placeholder="Ürün, barkod veya stok kodu ara"
                                class="min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-slate-900 sm:py-2 sm:text-sm"
                            >
                        </div>

                        <div class="flex gap-2 overflow-x-auto pb-1 xl:col-span-6">
                            @foreach($statusFilters as $filterKey => $filterLabel)
                                <button type="button" wire:click="setStatusFilter('{{ $filterKey }}')" class="whitespace-nowrap rounded-[6px] border px-3 py-2 text-sm font-medium transition {{ $statusFilter === $filterKey ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}">
                                    {{ $filterLabel }}
                                </button>
                            @endforeach
                        </div>

                        <div class="flex gap-2 xl:col-span-2 xl:justify-end">
                            <button type="button" wire:click="clearTableFilters" class="inline-flex min-h-[44px] flex-1 items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 xl:flex-none">
                                Temizle
                            </button>
                        </div>
                    </div>
                </div>

                <div class="hidden md:block">
                    <div class="badge-scrollbar overflow-x-auto" x-data="badgeColumnResize()">
                        <table class="badge-ledger-table w-full text-xs">
                            <thead class="border-b border-slate-200 bg-slate-50 text-slate-600">
                                <tr>
                                    <th class="relative w-16 px-3 py-3 text-left font-semibold">Seç</th>
                                    <th class="relative w-[300px] px-3 py-3 text-left font-semibold">
                                        <button type="button" wire:click="sortTable('product_name')" class="flex w-full items-center justify-between gap-2 text-left">
                                            <span>Ürün</span>
                                            <span class="text-[10px] text-slate-400">{{ $sortLabel('product_name') }}</span>
                                        </button>
                                        <span class="badge-resize-handle" x-on:mousedown.prevent="startResize($event, $el.closest('th'))"></span>
                                    </th>
                                    @if($this->showColumn('costs'))
                                        <th class="relative w-32 px-3 py-3 text-right font-semibold">
                                            <button type="button" wire:click="sortTable('total_cost')" class="flex w-full items-center justify-between gap-2 text-left">
                                                <span>Maliyet</span>
                                                <span class="text-[10px] text-slate-400">{{ $sortLabel('total_cost') }}</span>
                                            </button>
                                            <span class="badge-resize-handle" x-on:mousedown.prevent="startResize($event, $el.closest('th'))"></span>
                                        </th>
                                    @endif
                                    @foreach($scenarioColumns as $idx => $column)
                                        @if($this->showColumn($column['key']))
                                            <th class="relative w-36 px-3 py-3 text-center font-semibold">
                                                {{ $column['label'] }}
                                                <span class="badge-resize-handle" x-on:mousedown.prevent="startResize($event, $el.closest('th'))"></span>
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
                                            ? 'border-amber-200 bg-amber-50 text-amber-800'
                                            : ($item->action === 'warning' ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-slate-200 bg-slate-50 text-slate-600');
                                        $statusLabel = $item->action === 'update' ? 'Yıldız avantajlı' : ($item->action === 'warning' ? 'Risk' : 'Koru');
                                        $isChecked = in_array($item->id, $selectedItems, true);
                                        $displayPrice = $item->custom_price ?: ($item->suggested_price ?: $item->current_price);
                                        $suggestion = $suggestedPrices[$item->id] ?? null;
                                        $isMatched = (bool) data_get($item->campaign_data, 'matched', true);
                                    @endphp
                                    <tr wire:key="badge-row-{{ $item->id }}" class="transition hover:bg-slate-50/80">
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
                                                    @if($item->barcode && $item->barcode !== $item->stock_code)
                                                        <span class="font-mono">{{ $item->barcode }}</span>
                                                    @endif
                                                    @unless($isMatched)
                                                        <span class="font-semibold text-amber-700">Eşleşmedi</span>
                                                    @endunless
                                                </div>
                                            </div>
                                        </td>
                                        @if($this->showColumn('costs'))
                                            <td class="px-3 py-3 text-right">
                                                <p class="font-semibold text-slate-900">{{ $formatMoney($item->totalCost()) }}</p>
                                                <p class="mt-1 text-[11px] text-slate-500">Üretim {{ $formatMoneyShort($item->production_cost) }}</p>
                                                <p class="text-[11px] text-slate-500">Kargo+amb. {{ $formatMoneyShort($item->shipping_cost) }}</p>
                                            </td>
                                        @endif
                                        @foreach($scenarioColumns as $idx => $column)
                                            @if($this->showColumn($column['key']))
                                                @php
                                                    $scenario = $scenarios[$idx] ?? null;
                                                    $isSelected = $selectedIdx === $idx;
                                                    $isBest = $scenario && ($scenario['is_best'] ?? false);
                                                    $scenarioProfit = (float) ($scenario['net_profit'] ?? 0);
                                                    $scenarioRoi = (float) ($scenario['margin_pct'] ?? 0);
                                                    $scenarioShell = $isSelected
                                                        ? 'border-slate-900 bg-slate-900 text-white'
                                                        : ($isBest ? ($idx > 0 ? 'border-amber-200 bg-amber-50 text-slate-900' : 'border-slate-200 bg-slate-50 text-slate-900') : 'border-slate-200 bg-white text-slate-900 hover:border-slate-300 hover:bg-slate-50');
                                                @endphp
                                                <td class="px-2 py-3 text-center">
                                                    @if($scenario && ($idx === 0 || (float) ($scenario['price'] ?? 0) > 0))
                                                        <button type="button" wire:click="selectTariff({{ $item->id }}, {{ $idx }})" class="w-full rounded-[8px] border p-2 text-left transition {{ $scenarioShell }}">
                                                            <div class="flex items-center justify-between gap-2">
                                                                <span class="text-[11px] font-semibold {{ $isSelected ? 'text-slate-200' : 'text-slate-500' }}">{{ $scenario['name'] ?? $column['label'] }}</span>
                                                                @if($isSelected)
                                                                    <span class="rounded-[6px] bg-white/15 px-1.5 py-0.5 text-[9px] font-semibold text-white">Seçili</span>
                                                                @elseif($isBest)
                                                                    <span class="rounded-[6px] {{ $idx > 0 ? 'bg-amber-700' : 'bg-slate-700' }} px-1.5 py-0.5 text-[9px] font-semibold text-white">En iyi</span>
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
                                                        <button type="button" wire:click="getAiPriceSuggestion({{ $item->id }})" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-[6px] border border-amber-200 bg-amber-50 text-xs font-semibold text-amber-800 transition hover:bg-amber-100" title="AI fiyat öner">
                                                            <span wire:loading.remove wire:target="getAiPriceSuggestion({{ $item->id }})">AI</span>
                                                            <span wire:loading wire:target="getAiPriceSuggestion({{ $item->id }})" class="h-4 w-4 animate-spin rounded-full border-2 border-amber-200 border-t-amber-700"></span>
                                                        </button>
                                                    </div>
                                                    <button type="button" x-on:click="$wire.updateCustomPrice({{ $item->id }}, $refs.price{{ $item->id }}.value)" class="min-h-[40px] w-full rounded-[6px] bg-slate-900 px-3 py-2 text-xs font-semibold text-white transition hover:bg-slate-800">
                                                        Kaydet
                                                    </button>
                                                    @if($suggestion && !($suggestion['loading'] ?? false))
                                                        <div class="rounded-[6px] border {{ isset($suggestion['error']) ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-amber-200 bg-amber-50 text-amber-900' }} p-2 text-[11px]">
                                                            @if(isset($suggestion['error']))
                                                                {{ $suggestion['error'] }}
                                                            @else
                                                                <div class="flex items-center justify-between gap-2">
                                                                    <span class="font-semibold">{{ $formatMoney($suggestion['price']) }}</span>
                                                                    <button type="button" wire:click="applySuggestedPrice({{ $item->id }})" class="rounded-[6px] bg-amber-700 px-2 py-1 text-[10px] font-semibold text-white">Uygula</button>
                                                                </div>
                                                                <p class="mt-1 text-amber-800">{{ \Illuminate\Support\Str::limit($suggestion['reason'] ?? '', 72) }}</p>
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
                        <article wire:key="badge-mobile-{{ $item->id }}" class="p-4">
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
                                @foreach($scenarioColumns as $idx => $column)
                                    @php
                                        $scenario = $scenarios[$idx] ?? null;
                                        $isSelected = $selectedIdx === $idx;
                                        $isBest = $scenario && ($scenario['is_best'] ?? false);
                                        $scenarioProfit = (float) ($scenario['net_profit'] ?? 0);
                                        $scenarioShell = $isSelected
                                            ? 'border-slate-900 bg-slate-900 text-white'
                                            : ($isBest ? ($idx > 0 ? 'border-amber-200 bg-amber-50' : 'border-slate-200 bg-slate-50') : 'border-slate-200 bg-white');
                                    @endphp
                                    @if($scenario && ($idx === 0 || (float) ($scenario['price'] ?? 0) > 0))
                                        <button type="button" wire:click="selectTariff({{ $item->id }}, {{ $idx }})" class="rounded-[8px] border p-3 text-left {{ $scenarioShell }}">
                                            <div class="flex items-center justify-between gap-2">
                                                <span class="text-xs font-semibold {{ $isSelected ? 'text-slate-200' : 'text-slate-500' }}">{{ $scenario['name'] ?? $column['label'] }}</span>
                                                @if($isSelected)
                                                    <span class="rounded-[6px] bg-white/15 px-1.5 py-0.5 text-[10px] text-white">Seçili</span>
                                                @elseif($isBest)
                                                    <span class="rounded-[6px] {{ $idx > 0 ? 'bg-amber-700' : 'bg-slate-700' }} px-1.5 py-0.5 text-[10px] text-white">En iyi</span>
                                                @endif
                                            </div>
                                            <div class="mt-2 flex items-end justify-between gap-2">
                                                <div>
                                                    <p class="text-sm font-bold">{{ $formatMoney($scenario['price'] ?? 0) }}</p>
                                                    <p class="mt-1 text-xs {{ $isSelected ? 'text-slate-200' : 'text-slate-500' }}">Kom. {{ $formatPercent($scenario['commission'] ?? 0) }}</p>
                                                </div>
                                                <div class="text-right">
                                                    <p class="text-sm font-bold {{ $isSelected ? 'text-white' : ($scenarioProfit >= 0 ? 'text-emerald-700' : 'text-rose-700') }}">{{ $formatMoney($scenarioProfit) }}</p>
                                                    <p class="mt-1 text-xs {{ $isSelected ? 'text-slate-200' : 'text-slate-500' }}">Kârlılık {{ $formatMultiplier($scenario['margin_pct'] ?? 0) }}</p>
                                                </div>
                                            </div>
                                        </button>
                                    @endif
                                @endforeach
                            </div>

                            <div class="mt-3 rounded-[8px] border border-slate-200 bg-slate-50/70 p-3" x-data>
                                <p class="text-xs font-semibold text-slate-600">Fiyat aksiyonu</p>
                                <div class="mt-2 flex items-center gap-2">
                                    <input type="number" x-ref="mobilePrice{{ $item->id }}" value="{{ number_format((float) $displayPrice, 2, '.', '') }}" step="0.01" min="0" class="min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-2 py-2 text-base font-semibold text-slate-900 outline-none sm:text-sm">
                                    <button type="button" wire:click="getAiPriceSuggestion({{ $item->id }})" class="min-h-[44px] rounded-[6px] border border-amber-200 bg-amber-50 px-3 text-sm font-semibold text-amber-800">AI</button>
                                </div>
                                <button type="button" x-on:click="$wire.updateCustomPrice({{ $item->id }}, $refs.mobilePrice{{ $item->id }}.value)" class="mt-2 min-h-[44px] w-full rounded-[6px] bg-slate-900 px-3 py-2 text-sm font-semibold text-white">
                                    Kaydet
                                </button>
                                @if($suggestion && !($suggestion['loading'] ?? false) && !isset($suggestion['error']))
                                    <div class="mt-2 rounded-[6px] border border-amber-200 bg-amber-50 p-2 text-xs text-amber-900">
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="font-semibold">{{ $formatMoney($suggestion['price']) }}</span>
                                            <button type="button" wire:click="applySuggestedPrice({{ $item->id }})" class="rounded-[6px] bg-amber-700 px-2 py-1 text-[11px] font-semibold text-white">Uygula</button>
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
            </section>
        @endif
    @endif

    @if($activeTab === 'history')
        <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Geçmiş yıldız raporları</h2>
                    <p class="mt-1 text-sm text-slate-500">Önceki analizleri açıp aynı AI ve export akışını kullanabilirsiniz.</p>
                </div>
                <button type="button" wire:click="switchTab('analyze')" class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 sm:py-2">
                    Yeni analiz
                </button>
            </div>

            @if($this->reports->isEmpty())
                <div class="mt-5 rounded-[8px] border border-dashed border-slate-300 bg-slate-50/60 p-8 text-center">
                    <h3 class="text-sm font-semibold text-slate-900">Henüz rapor yok</h3>
                    <p class="mt-1 text-sm text-slate-500">İlk yıldız dosyasını analiz ettiğinizde raporlar burada listelenir.</p>
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
                                    <p class="mt-1 font-semibold text-amber-700">{{ number_format($rpt->opportunity_count, 0, ',', '.') }}</p>
                                </div>
                                <div class="rounded-[6px] border border-slate-200 bg-white p-2">
                                    <p class="text-[11px] text-slate-500">Ek kâr</p>
                                    <p class="mt-1 font-semibold {{ (float) $rpt->total_extra_profit >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $formatMoneyShort($rpt->total_extra_profit) }}</p>
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

    @include('livewire.partials.campaign-ai-panel', ['themeColor' => 'amber'])
</div>
