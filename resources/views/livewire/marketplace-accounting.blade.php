@php
    $tabs = $this->tabs;
    $tabKeys = array_keys($tabs);
    $tabPositions = array_flip($tabKeys);
@endphp

@once
    <style>
        .accounting-page-shell .rounded-2xl { border-radius: 10px; }
        .accounting-page-shell .rounded-xl { border-radius: 8px; }
        .accounting-page-shell .rounded-lg { border-radius: 6px; }
        .accounting-page-shell .rounded-md { border-radius: 6px; }
        .accounting-page-shell .rounded-3xl { border-radius: 10px; }
        .accounting-page-shell .rounded-\[28px\] { border-radius: 10px; }
        .accounting-page-shell .rounded-\[24px\] { border-radius: 10px; }
        .accounting-page-shell .rounded-\[18px\] { border-radius: 8px; }
        .accounting-page-shell .rounded-\[10px\] { border-radius: 10px; }
        .accounting-page-shell .rounded-\[8px\] { border-radius: 8px; }
        .accounting-page-shell .rounded-\[6px\] { border-radius: 6px; }
        .accounting-page-shell {
            --accounting-border: #e2e8f0;
        }
        .accounting-header-surface {
            background:
                radial-gradient(circle at top right, rgba(191, 219, 254, 0.16), transparent 24%),
                linear-gradient(180deg, rgba(248, 250, 252, 0.96), rgba(255, 255, 255, 1));
        }
        .accounting-nav-surface {
            background: linear-gradient(180deg, rgba(248, 250, 252, 0.94), rgba(255, 255, 255, 1));
        }
        .accounting-tooltip-surface {
            background: rgba(255, 255, 255, 0.98);
            color: #475569;
            border: 1px solid #e2e8f0;
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.12);
        }
        .accounting-tooltip-surface p,
        .accounting-tooltip-surface li {
            color: #475569;
        }
        .accounting-tooltip-surface .accounting-tooltip-title {
            color: #0f172a;
        }
        .accounting-bulk-surface {
            background: linear-gradient(180deg, rgba(248, 250, 252, 0.96), rgba(255, 255, 255, 1));
        }
        .accounting-chip {
            border-radius: 999px;
            border: 1px solid #e2e8f0;
            background: #fff;
            color: #475569;
            transition: all 160ms ease;
        }
        .accounting-chip:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }
        .accounting-chip.is-active {
            background: #0f172a;
            border-color: #0f172a;
            color: #fff;
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.08);
        }
        .accounting-soft-panel {
            background: linear-gradient(180deg, rgba(248, 250, 252, 0.94), rgba(255, 255, 255, 1));
        }
        .accounting-upload-surface {
            background: linear-gradient(180deg, rgba(248, 250, 252, 0.96), rgba(255, 255, 255, 1));
            border: 1px solid #e2e8f0;
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.06);
        }
        .accounting-upload-surface.is-dragging {
            border-style: dashed;
            border-color: #0f172a;
            background: linear-gradient(180deg, rgba(241, 245, 249, 1), rgba(255, 255, 255, 1));
            box-shadow: 0 0 0 4px rgba(226, 232, 240, 0.7);
        }
        .accounting-upload-card {
            background: linear-gradient(180deg, rgba(255, 255, 255, 1), rgba(248, 250, 252, 0.96));
            border: 1px solid #e2e8f0;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.04);
        }
        .accounting-upload-card.is-detected {
            border-color: #cbd5e1;
            background: linear-gradient(180deg, rgba(248, 250, 252, 1), rgba(255, 255, 255, 1));
            box-shadow: 0 0 0 2px rgba(226, 232, 240, 0.8);
        }
    </style>
@endonce

<div class="accounting-page-shell -mt-1 lg:-mt-4 space-y-5 overflow-hidden">
    {{-- ═══════════════════════════════════════════════════════════════ --}}
    {{-- HEADER + DÖNEM SEÇİCİ --}}
    {{-- ═══════════════════════════════════════════════════════════════ --}}
    <section class="accounting-header-surface rounded-[10px] border border-slate-200 p-4 shadow-sm lg:p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-4">
            <div>
                <div class="inline-flex items-center rounded-full border border-slate-200 bg-white px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">Accounting Control</div>
                <h1 class="mt-3 text-2xl lg:text-3xl font-bold tracking-tight text-slate-900">Pazaryeri Muhasebe</h1>
                <p class="mt-2 text-sm lg:text-base text-slate-500">Trendyol finansal analiz, yükleme ve denetim akışı</p>
            </div>

            {{-- Dönem Seçici --}}
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                <select wire:model.live="selectedYear" class="px-3 py-2 border border-slate-200 rounded-lg text-base sm:text-sm bg-white text-slate-900 shadow-sm">
                    @for($y = 2024; $y <= 2027; $y++)
                        <option value="{{ $y }}">{{ $y }}</option>
                    @endfor
                </select>
                <select wire:model.live="selectedMonth" class="px-3 py-2 border border-slate-200 rounded-lg text-base sm:text-sm bg-white text-slate-900 shadow-sm">
                    <option value="0">Tüm Yıl (Bütün Aylar)</option>
                    @foreach(['Ocak','Şubat','Mart','Nisan','Mayıs','Haziran','Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık'] as $i => $ay)
                        <option value="{{ $i + 1 }}">{{ $ay }}</option>
                    @endforeach
                </select>
                <button wire:click="selectPeriod" class="px-4 py-3 sm:py-2 bg-slate-900 text-white rounded-lg text-sm font-medium hover:bg-slate-800 transition-colors shadow-sm">
                    Seç
                </button>
                @if(!$selectedPeriodId)
                    <button wire:click="createPeriod" class="px-4 py-3 sm:py-2 bg-white text-slate-700 border border-slate-200 rounded-lg text-sm font-medium hover:bg-slate-50 transition-colors shadow-sm">
                        + Yeni Dönem
                    </button>
                @else
                    @php 
                        $per = \App\Models\MpPeriod::find($selectedPeriodId); 
                        $isLocked = $per ? $per->is_locked : false; 
                    @endphp
                    
                    @if($isLocked)
                        <button wire:click="unlockPeriod" class="px-4 py-3 sm:py-2 bg-amber-500 text-white rounded-lg text-sm font-medium hover:bg-amber-600 transition-colors flex items-center gap-2 shadow-sm" title="Kilitli Dönemi Aç">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
                            Kilitli
                        </button>
                    @else
                        <button wire:click="lockPeriod" wire:confirm="Bu dönemi kilitlemek istediğinize emin misiniz? Dönem kilitlendiğinde yeni excel verisi yüklenemez." class="px-4 py-3 sm:py-2 bg-white text-slate-700 border border-slate-200 rounded-lg text-sm font-medium hover:bg-slate-50 transition-colors flex items-center gap-2 shadow-sm" title="Mutabakatı Kapat / Kilitle">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            Kilitle
                        </button>
                    @endif
                @endif
            </div>
        </div>

        {{-- Status Bar --}}
        @if($importStatus)
            <div class="px-4 py-3 rounded-lg border text-sm {{ str_starts_with($importStatus, '✅') ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : (str_starts_with($importStatus, '❌') ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-sky-200 bg-sky-50 text-sky-700') }}">
                {{ $importStatus }}
            </div>
        @endif
    </section>

    {{-- ═══════════════════════════════════════════════════════════════ --}}
    {{-- TAB NAVİGASYONU --}}
    {{-- ═══════════════════════════════════════════════════════════════ --}}
    <section class="accounting-nav-surface rounded-[10px] border border-slate-200 px-4 py-3 shadow-sm lg:px-6">
        <div class="rounded-lg border border-slate-200 bg-white p-2">
            <nav class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-7 gap-2" aria-label="Pazaryeri muhasebe sekmeleri">
                @foreach($tabs as $tabKey => $tab)
                    @php
                        $isActive = $activeTab === $tabKey;
                        $tabStep = ($tabPositions[$tabKey] ?? 0) + 1;
                    @endphp

                    <a
                        wire:key="accounting-tab-{{ $tabKey }}"
                        href="{{ route('marketplace-accounting', ['tab' => $tabKey]) }}"
                        aria-current="{{ $isActive ? 'page' : 'false' }}"
                        class="min-h-[44px] inline-flex items-center justify-center gap-2 px-3 py-2 text-sm font-medium rounded-md transition-colors whitespace-nowrap {{ $isActive ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50' }}"
                    >
                        <span>{{ $tab['label'] }}</span>
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs {{ $isActive ? 'bg-white/15 text-white' : 'bg-slate-100 text-slate-500' }}">
                            {{ $tabStep }}
                        </span>
                    </a>
                @endforeach
            </nav>
        </div>
    </section>

    <div class="pt-1 pb-4 lg:pt-2 lg:pb-6">

        {{-- ═══════════════════════════════════════════════════════════════ --}}
        {{-- TAB 1: DASHBOARD --}}
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        @if($activeTab === 'dashboard')
            @if($selectedPeriodId)
                @php
                    $stats = $this->dashboardStats;
                    $period = $this->selectedPeriod;
                    $monthNames = [
                        1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan',
                        5 => 'Mayıs', 6 => 'Haziran', 7 => 'Temmuz', 8 => 'Ağustos',
                        9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık',
                    ];
                    $periodLabel = $period?->period_name ?? (($selectedMonth > 0 ? ($monthNames[(int) $selectedMonth] ?? 'Dönem') : 'Tüm Yıl') . ' ' . $selectedYear);
                    $formatMoney = fn ($value) => number_format((float) $value, 0, ',', '.') . ' ₺';
                    $formatMoneyDetailed = fn ($value) => number_format((float) $value, 2, ',', '.') . ' ₺';
                    $formatCount = fn ($value) => number_format((float) $value, 0, ',', '.');
                    $profitTotal = (float) ($stats['real_profit']['total_profit'] ?? 0);
                    $profitPositive = $profitTotal >= 0;
                    $profitCardClasses = $profitPositive
                        ? 'border-emerald-200 bg-emerald-50/80'
                        : 'border-rose-200 bg-rose-50/80';
                    $profitTextClasses = $profitPositive
                        ? 'text-emerald-700'
                        : 'text-rose-700';
                    $vatIsPayable = (bool) ($stats['net_vat']['is_payable'] ?? false);
                    $cashFlowKanban = $stats['cash_flow']['kanban'] ?? [];
                    $cashFlowToneMap = [
                        'red' => [
                            'panel' => 'border-rose-200 bg-rose-50/80',
                            'text' => 'text-rose-700',
                            'badge' => 'bg-rose-100 text-rose-700',
                        ],
                        'emerald' => [
                            'panel' => 'border-emerald-200 bg-emerald-50/80',
                            'text' => 'text-emerald-700',
                            'badge' => 'bg-emerald-100 text-emerald-700',
                        ],
                        'blue' => [
                            'panel' => 'border-sky-200 bg-sky-50/80',
                            'text' => 'text-sky-700',
                            'badge' => 'bg-sky-100 text-sky-700',
                        ],
                        'gray' => [
                            'panel' => 'border-slate-200 bg-slate-50/80',
                            'text' => 'text-slate-700',
                            'badge' => 'bg-slate-100 text-slate-700',
                        ],
                    ];
                @endphp

                <div class="space-y-5">
                    <x-zolm.section-card
                        headerPadding="px-4 pt-4 pb-3 lg:px-6 lg:pt-6 lg:pb-3"
                        bodyPadding="px-4 pb-4 lg:px-6 lg:pb-6"
                    >
                        <x-slot:header>
                            <div class="flex flex-col xl:flex-row xl:items-start xl:justify-between gap-4 lg:gap-5">
                                <div class="max-w-3xl">
                                    <x-zolm.eyebrow>Dashboard</x-zolm.eyebrow>
                                    <div class="mt-3 flex items-center gap-2">
                                        <h2 class="text-xl lg:text-2xl font-bold text-slate-900">Dönem özeti</h2>
                                        <x-zolm.help-tip title="Dönem özeti" summary="Seçili dönemin satış, tahsilat, vergi ve denetim baskısını tek bakışta toplar." source="Sipariş, cari, fatura, stopaj ve ödeme detay yüklemeleri." refresh="Dönem değiştiğinde veya yeni Excel işlendiğinde." impact="Muhasebe ekibinin ilk bakışta nereye eğilmesi gerektiğini belirler." />
                                    </div>
                                    <p class="mt-2 text-sm lg:text-base text-slate-500">
                                        {{ $periodLabel }} dönemi için finansal görünüm, mutabakat durumu ve beklenen tahsilat akışını tek yerde görün.
                                    </p>
                                    <div class="mt-4 flex flex-wrap gap-2">
                                        <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-600">
                                            {{ $formatCount($stats['total_orders']) }} sipariş
                                        </span>
                                        <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-600">
                                            {{ $formatCount($stats['total_returns']) }} iade
                                        </span>
                                        <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-600">
                                            {{ $formatCount($stats['total_cancels']) }} iptal
                                        </span>
                                        <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-600">
                                            %{{ number_format((float) $stats['return_rate'], 1, ',', '.') }} iade oranı
                                        </span>
                                    </div>
                                </div>

                                <div class="grid w-full grid-cols-1 gap-2 sm:grid-cols-3 xl:w-[420px]">
                                    <button wire:click="exportMonthlyPivot" class="inline-flex min-h-[44px] w-full items-center justify-center rounded-lg bg-slate-900 px-4 py-3 text-center text-sm font-medium text-white transition hover:bg-slate-800 sm:py-2">
                                        Aylık Özet Excel
                                    </button>
                                    <button wire:click="exportAllOrders" class="inline-flex min-h-[44px] w-full items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-3 text-center text-sm font-medium text-slate-700 transition hover:bg-slate-50 sm:py-2">
                                        Tüm Siparişler Excel
                                    </button>
                                    <button wire:click="exportStopajReport" class="inline-flex min-h-[44px] w-full items-center justify-center rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-center text-sm font-medium text-amber-700 transition hover:bg-amber-100 sm:py-2" title="Mali Müşavir (193 Kodu) Formu">
                                        Stopaj 193
                                    </button>
                                </div>
                            </div>
                        </x-slot:header>

                        <div class="space-y-4">
                            @if($stats['audit_count'] > 0)
                                <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-4">
                                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
                                        <div class="flex items-start gap-3">
                                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-rose-100 text-rose-600">
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86l-7.5 13A1 1 0 003.66 18h16.68a1 1 0 00.87-1.5l-7.5-13a1 1 0 00-1.74 0z" />
                                                </svg>
                                            </div>
                                            <div>
                                                <p class="text-sm font-semibold text-rose-800">Açık finansal denetim bulguları var</p>
                                                <p class="mt-1 text-sm text-rose-700">
                                                    {{ $formatCount($stats['audit_count']) }} açık bulguda {{ $formatMoney($stats['audit_amount']) }} potansiyel fark görünüyor.
                                                    Denetim sekmesinden detay inceleme yapabilirsiniz.
                                                </p>
                                            </div>
                                        </div>
                                        <button wire:click="setTab('audit')" class="min-h-[44px] w-full sm:w-auto rounded-lg bg-rose-600 px-4 py-3 sm:py-2 text-sm font-medium text-white transition hover:bg-rose-700">
                                            Denetime Git
                                        </button>
                                    </div>
                                </div>
                            @else
                                <div class="rounded-xl border border-emerald-200 bg-emerald-50/80 px-4 py-4 text-sm text-emerald-700">
                                    Açık denetim uyarısı görünmüyor. Dönem özeti temiz ilerliyor.
                                </div>
                            @endif

                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                <div class="h-full rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                                    <div class="flex items-center gap-1.5">
                                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Brüt Ciro</p>
                                        <x-zolm.help-tip title="Brüt ciro" summary="İptal ve iade etkileri ayrı okunarak satış tarafındaki toplam hacmi gösterir." source="Sipariş kayıtları ve sipariş toplamları." refresh="Sipariş dosyası işlendiğinde." impact="Dönemin hacmini ve diğer KPI'ların tabanını oluşturur." />
                                    </div>
                                    <p class="mt-2 text-2xl font-semibold tracking-tight text-slate-900">{{ $formatMoney($stats['total_brut']) }}</p>
                                    <p class="mt-2 text-xs text-slate-500">Dönem içindeki toplam brüt satış hacmi</p>
                                </div>
                                <div class="h-full rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                                    <div class="flex items-center gap-1.5">
                                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Net Hakediş</p>
                                        <x-zolm.help-tip title="Net hakediş" summary="Pazaryerinden satıcıya beklenen net tahsilat toplamını gösterir." source="Ödeme detay / hakediş kayıtları ve teslim edilen siparişler." refresh="Hakediş dosyası veya teslim bilgisi değiştiğinde." impact="Tahsilat beklentisini ve nakit akışı planını yönlendirir." />
                                    </div>
                                    <p class="mt-2 text-2xl font-semibold tracking-tight text-slate-900">{{ $formatMoney($stats['total_hakedis']) }}</p>
                                    <p class="mt-2 text-xs text-slate-500">Teslim edilen siparişlerden beklenen net tahsilat</p>
                                </div>
                                <div class="h-full rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                                    <div class="flex items-center gap-1.5">
                                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Stopaj</p>
                                        <x-zolm.help-tip title="Stopaj" summary="E-ticaret stopajı olarak kesilen toplam tutarı gösterir." source="Stopaj / tevkifat excel yüklemeleri." refresh="Stopaj dosyası işlendiğinde." impact="Net vergi etkisini ve müşavir raporlarını doğrudan etkiler." />
                                    </div>
                                    <p class="mt-2 text-2xl font-semibold tracking-tight text-indigo-600">{{ $formatMoney($stats['total_stopaj']) }}</p>
                                    <p class="mt-2 text-xs text-slate-500">%1 e-ticaret stopajı, yıl sonu mahsup kalemi</p>
                                </div>
                                <div class="h-full rounded-xl border p-4 {{ $profitCardClasses }}">
                                    <div class="flex items-center gap-1.5">
                                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] {{ $profitTextClasses }}">Gerçek Net Kâr</p>
                                        <x-zolm.help-tip title="Gerçek net kâr" summary="Satış, maliyet, kargo, kesinti ve vergi etkileri birlikte düşünülerek hesaplanan dönem kârıdır." source="Siparişler, maliyetler, finans kesintileri, stopaj ve KDV hesapları." refresh="Maliyet senkronu veya muhasebe verisi güncellendiğinde." impact="Dönemin gerçekten kârlı mı zararlı mı olduğunu gösterir." />
                                    </div>
                                    <p class="mt-2 text-2xl font-semibold tracking-tight {{ $profitTextClasses }}">{{ $formatMoney($stats['real_profit']['total_profit']) }}</p>
                                    <p class="mt-2 text-xs {{ $profitTextClasses }}">
                                        {{ $formatCount($stats['real_profit']['profitable_count']) }} kârlı, {{ $formatCount($stats['real_profit']['bleeding_count']) }} zararlı ürün
                                    </p>
                                    @if(!$stats['real_profit']['has_cogs'])
                                        <p class="mt-2 text-xs text-amber-700">Maliyet verisi eksik, kâr hesabı tahmini ilerliyor.</p>
                                    @endif
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                <div class="h-full rounded-xl border border-rose-200 bg-rose-50/70 p-4">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-rose-500">Lojistik Zararı</p>
                                    <p class="mt-2 text-xl font-semibold tracking-tight text-rose-700">-{{ $formatMoney($stats['logistic_loss']['total']) }}</p>
                                    <p class="mt-2 text-xs text-rose-600">
                                        Gidiş: {{ $formatMoney($stats['logistic_loss']['sunk_cargo']) }} · Dönüş: {{ $formatMoney($stats['logistic_loss']['return_cargo']) }}
                                    </p>
                                </div>
                                <div class="h-full rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                                    <div class="flex items-center gap-1.5">
                                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Net KDV</p>
                                        <x-zolm.help-tip title="Net KDV" summary="Satış KDV'si ile gider KDV'sinin mahsuplaşması sonrası kalan vergi etkisini gösterir." source="Fatura kayıtları, satış matrahı ve KDV parametreleri." refresh="Fatura dosyası veya KDV ayarı değiştiğinde." impact="Vergi yükü ve gerçek kâr hesabını etkiler." />
                                    </div>
                                    <p class="mt-2 text-xl font-semibold tracking-tight {{ $vatIsPayable ? 'text-amber-600' : 'text-emerald-600' }}">
                                        {{ $vatIsPayable ? '' : '+' }}{{ $formatMoney($stats['net_vat']['net_vat']) }}
                                    </p>
                                    <p class="mt-2 text-xs text-slate-500">
                                        {{ $vatIsPayable ? 'Devlete ödenecek KDV yükü' : 'KDV avantajı / mahsup alanı' }}
                                    </p>
                                </div>
                                <div class="h-full rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">İade ve İptal</p>
                                    <p class="mt-2 text-xl font-semibold tracking-tight text-slate-900">{{ $formatCount($stats['total_returns']) }} / {{ $formatCount($stats['total_cancels']) }}</p>
                                    <p class="mt-2 text-xs text-slate-500">İade / iptal adetleri ve operasyon baskısı</p>
                                </div>
                                <div class="h-full rounded-xl border {{ $stats['audit_count'] > 0 ? 'border-rose-200 bg-rose-50/70' : 'border-slate-200 bg-slate-50/70' }} p-4">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] {{ $stats['audit_count'] > 0 ? 'text-rose-500' : 'text-slate-500' }}">Denetim Uyarıları</p>
                                    <p class="mt-2 text-xl font-semibold tracking-tight {{ $stats['audit_count'] > 0 ? 'text-rose-700' : 'text-slate-900' }}">{{ $formatCount($stats['audit_count']) }}</p>
                                    <p class="mt-2 text-xs {{ $stats['audit_count'] > 0 ? 'text-rose-600' : 'text-slate-500' }}">
                                        {{ $stats['audit_amount'] > 0 ? $formatMoney($stats['audit_amount']) . ' fark' : 'Açık bulgu görünmüyor' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </x-zolm.section-card>

                    <div class="grid grid-cols-1 gap-4 lg:gap-5 xl:grid-cols-2 xl:items-stretch">
                        <x-zolm.section-card
                            class="h-full"
                            eyebrow="Aylık Kontrol"
                            title="Fatura mutabakat durumu"
                            helpTitle="Fatura mutabakat durumu"
                            helpSummary="Komisyon ve kargo faturalarının sipariş toplamlarıyla uyumunu gösterir."
                            helpSource="Fatura/cari yüklemeleri ve sipariş bazlı finans toplamları."
                            helpRefresh="Fatura veya sipariş verisi değiştiğinde."
                            helpImpact="Muhasebe farklarını ve eksik kayıt baskısını görünür kılar."
                            description="Komisyon ve kargo faturalarını sipariş toplamlarıyla karşılaştırın."
                            headerPadding="px-4 pt-4 pb-3 lg:px-6 lg:pt-6 lg:pb-3"
                            bodyPadding="px-4 pb-4 lg:px-6 lg:pb-6"
                        >
                            @if($this->invoiceReconciliation)
                                @php $recon = $this->invoiceReconciliation; @endphp
                                <div class="space-y-4">
                                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                        <p class="text-sm text-slate-500">Aynı dönemde fatura edilen komisyon ve kargo kalemleri sipariş bazlı net tutarlarla kıyaslanır.</p>
                                        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium {{ ($recon['commission_match'] && $recon['cargo_match']) ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                            {{ ($recon['commission_match'] && $recon['cargo_match']) ? 'Mutabık' : 'Fark Var' }}
                                        </span>
                                    </div>

                                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 lg:gap-4">
                                        <div class="rounded-xl border {{ $recon['commission_match'] ? 'border-slate-200 bg-slate-50/60' : 'border-amber-200 bg-amber-50/70' }} p-4">
                                            <div class="flex items-center justify-between gap-3">
                                                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Komisyon</p>
                                                <span class="text-xs font-medium {{ $recon['commission_match'] ? 'text-emerald-700' : 'text-amber-700' }}">
                                                    {{ $recon['commission_match'] ? 'Mutabık' : 'Uyuşmazlık' }}
                                                </span>
                                            </div>
                                            <div class="mt-4 space-y-2 text-sm">
                                                <div class="flex items-center justify-between gap-3">
                                                    <span class="text-slate-500">Fatura edilen (KDV hariç)</span>
                                                    <span class="font-semibold text-slate-900">{{ $formatMoneyDetailed($recon['invoice_commission']) }}</span>
                                                </div>
                                                <div class="flex items-center justify-between gap-3">
                                                    <span class="text-slate-500">Sipariş toplamı</span>
                                                    <span class="font-semibold text-slate-900">{{ $formatMoneyDetailed($recon['order_commission']) }}</span>
                                                </div>
                                            </div>
                                            <div class="mt-4 flex items-center justify-between border-t border-slate-200 pt-3">
                                                <span class="text-xs uppercase tracking-[0.14em] text-slate-500">Fark</span>
                                                <span class="text-sm font-semibold {{ $recon['commission_match'] ? 'text-slate-900' : 'text-rose-600' }}">
                                                    {{ $formatMoneyDetailed($recon['commission_diff']) }}
                                                </span>
                                            </div>
                                        </div>

                                        <div class="rounded-xl border {{ $recon['cargo_match'] ? 'border-slate-200 bg-slate-50/60' : 'border-amber-200 bg-amber-50/70' }} p-4">
                                            <div class="flex items-center justify-between gap-3">
                                                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Kargo</p>
                                                <span class="text-xs font-medium {{ $recon['cargo_match'] ? 'text-emerald-700' : 'text-amber-700' }}">
                                                    {{ $recon['cargo_match'] ? 'Mutabık' : 'Uyuşmazlık' }}
                                                </span>
                                            </div>
                                            <div class="mt-4 space-y-2 text-sm">
                                                <div class="flex items-center justify-between gap-3">
                                                    <span class="text-slate-500">Fatura edilen (KDV hariç)</span>
                                                    <span class="font-semibold text-slate-900">{{ $formatMoneyDetailed($recon['invoice_cargo']) }}</span>
                                                </div>
                                                <div class="flex items-center justify-between gap-3">
                                                    <span class="text-slate-500">Sipariş toplamı</span>
                                                    <span class="font-semibold text-slate-900">{{ $formatMoneyDetailed($recon['order_cargo']) }}</span>
                                                </div>
                                            </div>
                                            <div class="mt-4 flex items-center justify-between border-t border-slate-200 pt-3">
                                                <span class="text-xs uppercase tracking-[0.14em] text-slate-500">Fark</span>
                                                <span class="text-sm font-semibold {{ $recon['cargo_match'] ? 'text-slate-900' : 'text-rose-600' }}">
                                                    {{ $formatMoneyDetailed($recon['cargo_diff']) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center text-sm text-slate-500">
                                    Bu dönem için fatura mutabakatı gösterecek veri bulunmuyor.
                                </div>
                            @endif
                        </x-zolm.section-card>

                        <x-zolm.section-card
                            class="h-full"
                            eyebrow="Tahsilat"
                            title="Nakit akışı"
                            helpTitle="Nakit akışı"
                            helpSummary="Yaklaşan veya geciken tahsilat kümelerini vade mantığıyla sıralar."
                            helpSource="Ödeme detayları, vade tarihi ve teslim kayıtları."
                            helpRefresh="Tahsilat verisi işlendiğinde veya teslim durumu değiştiğinde."
                            helpImpact="Likidite planı ve geciken ödemelerin takibini kolaylaştırır."
                            description="Vadesi yaklaşan ya da geciken transferleri öncelik sırasıyla görün."
                            headerPadding="px-4 pt-4 pb-3 lg:px-6 lg:pt-6 lg:pb-3"
                            bodyPadding="px-4 pb-4 lg:px-6 lg:pb-6"
                        >
                            @if(count($cashFlowKanban) > 0)
                                <div class="space-y-3">
                                    <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-4">
                                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Toplam Beklenen</p>
                                        <p class="mt-2 text-2xl font-semibold tracking-tight text-slate-900">{{ $formatMoneyDetailed($stats['cash_flow']['total_expected']) }}</p>
                                        <p class="mt-2 text-xs text-slate-500">Vadesi gelen ve beklenen transferlerin toplam görünümü</p>
                                    </div>

                                    @foreach($cashFlowKanban as $kanban)
                                        @php $tone = $cashFlowToneMap[$kanban['color']] ?? $cashFlowToneMap['gray']; @endphp
                                        <div class="rounded-xl border p-4 {{ $tone['panel'] }}">
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="min-w-0">
                                                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] {{ $tone['text'] }}">{{ $kanban['label'] }}</p>
                                                    <p class="mt-2 text-xl font-semibold tracking-tight text-slate-900">{{ $formatMoneyDetailed($kanban['amount']) }}</p>
                                                </div>
                                                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium {{ $tone['badge'] }}">
                                                    {{ $formatCount($kanban['count']) }} işlem
                                                </span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center text-sm text-slate-500">
                                    Beklenen transfer verisi oluşmadı. İlgili settlement kayıtları geldiğinde burada listelenecek.
                                </div>
                            @endif
                        </x-zolm.section-card>
                    </div>
                </div>
            @else
                <x-zolm.section-card
                    eyebrow="Dashboard"
                    title="Henüz dönem seçilmedi"
                    description="Yukarıdan yıl ve ay seçip mevcut dönemi açın ya da yeni dönem oluşturun."
                    headerPadding="px-4 pt-4 pb-3 lg:px-6 lg:pt-6 lg:pb-3"
                    bodyPadding="px-4 pb-6 lg:px-6 lg:pb-8"
                >
                    <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-12 text-center text-sm text-slate-500">
                        Dashboard verileri seçilen döneme göre yüklenir.
                    </div>
                </x-zolm.section-card>
            @endif

        {{-- ═══════════════════════════════════════════════════════════════ --}}
        {{-- TAB 2: VERİ YÜKLEME --}}
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        @elseif($activeTab === 'upload')

        <div x-data="{
            isProcessing: false,
            procProgress: 0,
            interval: null,
            startProcessing() {
                this.isProcessing = true;
                this.procProgress = 0;
                this.interval = setInterval(() => {
                    if (this.procProgress < 99) {
                        let inc = this.procProgress < 50 ? 2 : (this.procProgress < 85 ? 1 : 0.2);
                        this.procProgress += inc;
                    }
                }, 300);
            },
            stopProcessing() {
                clearInterval(this.interval);
                this.procProgress = 100;
                setTimeout(() => {
                    this.isProcessing = false;
                    this.procProgress = 0;
                }, 500);
            }
        }" x-on:import-finished.window="stopProcessing()">

            {{-- Toplu İşlem Kartı --}}
            <div x-data="{ 
                    isDragging: false, 
                    showSafeOrder: false,
                    fileCount: 0,
                    fileNames: [],
                    detectedTypes: { orders: [], transactions: [], stopaj: [], invoices: [], settlements: [], unknown: [] },
                    detectType(name) {
                        let n = name.toLowerCase();
                        if (n.includes('siparis') || n.includes('sipariş')) return 'orders';
                        if (n.includes('cari') || n.includes('ekstre')) return 'transactions';
                        if (n.includes('stopaj') || n.includes('tevkifat')) return 'stopaj';
                        if (n.includes('fatura') || n.includes('invoice') || n.includes('toplu')) return 'invoices';
                        if (n.includes('ödeme') || n.includes('odeme') || n.includes('hakediş') || n.includes('hakedis')) return 'settlements';
                        return 'unknown';
                    },
                    typeLabel(t) {
                        return {orders:'📦 Sipariş',transactions:'🏦 Cari',stopaj:'🏛️ Stopaj',invoices:'🧾 Fatura',settlements:'💸 Ödeme',unknown:'❓ Bilinmiyor'}[t] || t;
                    },
                    typeBadge(t) {
                        return {orders:'bg-indigo-100 text-indigo-800 border-indigo-200',transactions:'bg-teal-100 text-teal-800 border-teal-200',stopaj:'bg-purple-100 text-purple-800 border-purple-200',invoices:'bg-amber-100 text-amber-800 border-amber-200',settlements:'bg-emerald-100 text-emerald-800 border-emerald-200',unknown:'bg-gray-100 text-gray-600 border-gray-200'}[t] || '';
                    },
                    handleFiles(files) {
                        this.fileCount = files.length;
                        this.fileNames = Array.from(files).map(f => f.name);
                        this.detectedTypes = { orders: [], transactions: [], stopaj: [], invoices: [], settlements: [], unknown: [] };
                        this.fileNames.forEach(n => { let t = this.detectType(n); this.detectedTypes[t].push(n); });
                        @this.uploadMultiple('bulkFiles', files);
                    },
                    hasType(t) { return this.detectedTypes[t] && this.detectedTypes[t].length > 0; }
                 }"
                 x-on:dragover.prevent="isDragging = true"
                 x-on:dragleave.prevent="isDragging = false"
                 x-on:drop.prevent="isDragging = false; handleFiles($event.dataTransfer.files)"
                 :class="{ 'is-dragging': isDragging }"
                 class="accounting-upload-surface mb-6 rounded-xl p-4 lg:p-6 flex flex-col sm:flex-row items-center justify-between gap-4 relative overflow-hidden transition-all duration-300 group"
                 id="bulkUploadZone">
                 
                <div class="flex-grow w-full relative z-10 pointer-events-none">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="flex items-center gap-2 text-lg font-bold text-slate-900">
                            🚀 Toplu İşlem Seçeneği (Dosyaları Buraya Sürükleyin)
                        </h3>

                        <div class="relative pointer-events-auto"
                             @mouseenter="showSafeOrder = true"
                             @mouseleave="showSafeOrder = false">
                            <button type="button"
                                    @focus="showSafeOrder = true"
                                    @blur="showSafeOrder = false"
                                    @click="showSafeOrder = !showSafeOrder"
                                    class="inline-flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800 transition-colors hover:bg-amber-100 focus:outline-none focus:ring-2 focus:ring-amber-300 focus:ring-offset-2">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86l-7.12 12.3A1 1 0 004.03 18h15.94a1 1 0 00.86-1.84l-7.12-12.3a1 1 0 00-1.72 0z"/>
                                </svg>
                                Güvenli Yükleme Sırası
                            </button>

                            <div x-show="showSafeOrder"
                                 x-transition
                                 @click.outside="showSafeOrder = false"
                                 class="accounting-tooltip-surface absolute left-0 top-full z-50 mt-2 w-[22rem] max-w-[85vw] rounded-xl p-4 text-xs sm:left-auto sm:right-0">
                                <p class="accounting-tooltip-title mb-2 font-bold">En güvenli yükleme sırası</p>
                                <ol class="list-decimal space-y-1.5 pl-4 leading-relaxed">
                                    <li>Ürünler tarafında ürün kütüphanesi ve maliyetleri önce hazır olsun.</li>
                                    <li>Varsa Siparişler Detaylı operasyonel export’unu yükleyin.</li>
                                    <li>Sipariş Kayıtları yükleyin.</li>
                                    <li>Ödeme Detay dosyalarını yükleyin.</li>
                                    <li>Cari Hesap Ekstresi yükleyin.</li>
                                    <li>Stopaj dosyalarını yükleyin.</li>
                                    <li>Faturaları en son yükleyin.</li>
                                </ol>
                                <p class="mt-3 text-[11px] text-slate-500">
                                    Not: Sipariş ayı ile ödeme ayı farklı olabilir. Geciken/kayıp ödeme alarmını doğru görmek için takip eden haftaların ödeme dosyalarını da yükleyin.
                                </p>
                            </div>
                        </div>

                        <span x-show="isDragging" x-transition class="rounded-full bg-slate-900 px-2 py-0.5 text-xs text-white animate-pulse">Bırakın yüklesin...</span>
                    </div>
                    <p class="mt-1 mb-3 text-sm text-slate-600">
                        Trendyol Paneli'nden indirdiğiniz <strong>tüm Excel dosyalarını (Sipariş, Cari, Fatura, Stopaj, Ödeme)</strong> tek seferde sürükleyip bırakabilir veya çoklu seçebilirsiniz. Sistem dosyaları isimlerinden otomatik tanıyacaktır.
                    </p>
                    
                    <div class="pointer-events-auto">
                        <input type="file" wire:model.live="bulkFiles" multiple accept=".xlsx,.xls"
                               x-on:change="handleFiles($event.target.files)"
                               class="w-full cursor-pointer rounded-lg border border-slate-200 bg-white text-sm text-slate-600 transition-colors file:mr-4 file:rounded-lg file:border-0 file:bg-slate-900 file:px-4 file:py-2 file:text-white hover:border-slate-300 hover:file:bg-slate-800 focus:outline-none">
                    </div>

                    {{-- Seçilen Dosya Bilgisi (Tip Tanımalı) --}}
                    <div x-show="fileCount > 0" x-transition.opacity class="accounting-soft-panel mt-4 rounded-lg border border-slate-200 p-3 pointer-events-auto">
                        <p class="mb-2 text-sm font-semibold text-slate-900">
                            ✅ <span x-text="fileCount"></span> adet dosya algılandı — Otomatik tip tanıma sonuçları:
                        </p>
                        <div class="space-y-1.5">
                            <template x-for="fname in fileNames" :key="fname">
                                <div class="flex items-center gap-2 text-xs">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full border text-[11px] font-bold" :class="typeBadge(detectType(fname))" x-text="typeLabel(detectType(fname))"></span>
                                    <span class="truncate text-slate-600" x-text="fname"></span>
                                </div>
                            </template>
                        </div>
                        <template x-if="detectedTypes.unknown.length > 0">
                            <p class="text-xs text-amber-600 mt-2 font-medium">⚠️ Tanımlanamayan dosyalar atlanacaktır. Dosya isminde anahtar kelime (sipariş, cari, fatura vb.) olmalıdır.</p>
                        </template>
                    </div>
                    
                    <div wire:loading wire:target="bulkFiles" class="mt-2 flex items-center gap-2 text-xs font-medium text-slate-600">
                        <svg class="animate-spin h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        Sisteme alınıyor...
                    </div>
                </div>

                <div class="flex-shrink-0 w-full sm:w-auto flex flex-col gap-2 relative mt-4 sm:mt-0">
                    <button wire:click="importAll" x-on:click="startProcessing()" wire:loading.attr="disabled" wire:target="importAll,ordersFile,transactionsFile,stopajFile,invoicesFile,bulkFiles"
                            class="group relative w-full overflow-hidden rounded-lg bg-slate-900 px-6 py-3 font-bold text-white shadow-sm transition-colors hover:bg-slate-800 disabled:opacity-50">
                        
                        <div x-show="isProcessing" class="absolute inset-0 origin-left bg-slate-700 transition-all duration-300" x-bind:style="'transform: scaleX(' + (procProgress/100) + ')'"></div>

                        <div class="relative z-10">
                            <span wire:loading.remove wire:target="importAll">Tümünü Veritabanına Aktar</span>
                            <span wire:loading wire:target="importAll">
                                ⏳ Aktarılıyor <span x-text="'%' + Math.floor(procProgress)"></span>
                            </span>
                        </div>
                    </button>
                    <div x-show="isProcessing" class="h-1.5 w-full overflow-hidden rounded-full bg-slate-200" style="display: none;">
                        <div class="h-full bg-slate-900 transition-all duration-300" x-bind:style="'width: ' + procProgress + '%'"></div>
                    </div>
                </div>
            </div>

            @if(session()->has('import_success'))
                <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg flex items-center gap-3">
                    <span class="text-xl">✅</span>
                    <span class="font-medium">{{ session('import_success') }}</span>
                </div>
            @endif
            @if(session()->has('import_error'))
                <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg flex items-center gap-3">
                    <span class="text-xl">⚠️</span>
                    <span class="font-medium">{{ session('import_error') }}</span>
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 lg:gap-6">
                {{-- Sipariş Excel --}}
                <div class="accounting-upload-card rounded-xl p-4 lg:p-6 transition-all duration-500"
                     :class="hasType('orders') ? 'is-detected' : ''">
                    <div class="flex items-center justify-between mb-1">
                        <h3 class="font-semibold text-gray-900">📦 Sipariş Verileri</h3>
                        <div x-data="{ show: false }" class="relative">
                            <button @click="show = !show" @click.outside="show = false" class="text-gray-400 transition-colors hover:text-slate-700"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></button>
                            <div x-show="show" x-transition class="accounting-tooltip-surface absolute right-0 top-8 z-50 w-72 rounded-xl p-3 text-xs leading-relaxed">
                                <p class="accounting-tooltip-title mb-1 font-bold">📍 Nereden İndirilir?</p>
                                <p>Trendyol Satıcı Paneli → Sipariş → Siparişlerim → "Tümü" seçip Excel İndir</p>
                                <p class="accounting-tooltip-title mt-2 mb-1 font-bold">📊 Ne İçerir?</p>
                                <p>Sipariş No, Tarih, Tutar, Komisyon, Kargo Bedeli, Hizmet Bedeli, Net Hakediş, Sipariş Durumu</p>
                                <p class="accounting-tooltip-title mt-2 mb-1 font-bold">🔗 Neyle Bağlantılı?</p>
                                <p>Dashboard KPI'ları (Brüt Ciro, Lojistik Zararı, Net Hakediş), Denetim Motoru, Kârlılık Analizi</p>
                            </div>
                        </div>
                    </div>
                    <p class="text-sm text-gray-500 mb-3">Trendyol Paneli → Sipariş → Excel İndir</p>
                    <template x-if="hasType('orders')">
                        <p class="mb-3 flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-bold text-slate-700">✅ Toplu yüklemede algılandı — <span x-text="detectedTypes.orders.length"></span> dosya</p>
                    </template>
                    
                    <div x-data="{ isUploading: false, progress: 0 }" x-on:livewire-upload-start="isUploading = true" x-on:livewire-upload-finish="isUploading = false" x-on:livewire-upload-error="isUploading = false" x-on:livewire-upload-progress="progress = $event.detail.progress">
                        <input type="file" wire:model="ordersFile" accept=".xlsx,.xls" id="ordersFileInput" class="w-full text-base sm:text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-300 transition-colors cursor-pointer">
                        <div x-show="isUploading" class="mt-3">
                            <div class="flex justify-between text-xs mb-1"><span class="font-medium text-emerald-600">Dosya sisteme yükleniyor...</span><span class="font-bold text-emerald-600" x-text="progress + '%'"></span></div>
                            <div class="w-full bg-gray-100 rounded-full h-2 border border-gray-200"><div class="bg-emerald-500 h-2 rounded-full transition-all duration-300" x-bind:style="'width: ' + progress + '%'"></div></div>
                        </div>
                    </div>
                    @if($ordersFile)<p class="text-xs text-green-700 font-medium mt-3 bg-green-50 p-2 rounded border border-green-100">✅ {{ $ordersFile->getClientOriginalName() }} ({{ number_format($ordersFile->getSize() / 1024, 0) }} KB) — Seçildi</p>@endif
                    @error('ordersFile') <p class="text-red-500 text-xs mt-2">{{ $message }}</p> @enderror
                    <div class="mt-4 flex justify-end"><button wire:click="importOrders" x-on:click="startProcessing()" wire:loading.attr="disabled" wire:target="ordersFile,importOrders,importAll" class="w-full sm:w-auto px-4 py-2 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-gray-900 disabled:opacity-50 transition-colors shadow relative overflow-hidden"><div class="relative z-10"><span wire:loading.remove wire:target="importOrders">Sadece Bunu Kaydet</span><span wire:loading wire:target="importOrders">⏳ Kaydediliyor</span></div></button></div>
                </div>

                {{-- Cari Hesap Ekstresi --}}
                <div class="accounting-upload-card rounded-xl p-4 lg:p-6 transition-all duration-500"
                     :class="hasType('transactions') ? 'is-detected' : ''">
                    <div class="flex items-center justify-between mb-1">
                        <h3 class="font-semibold text-gray-900">🏦 Cari Hesap Ekstresi</h3>
                        <div x-data="{ show: false }" class="relative">
                            <button @click="show = !show" @click.outside="show = false" class="text-gray-400 transition-colors hover:text-slate-700"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></button>
                            <div x-show="show" x-transition class="accounting-tooltip-surface absolute right-0 top-8 z-50 w-72 rounded-xl p-3 text-xs leading-relaxed">
                                <p class="accounting-tooltip-title mb-1 font-bold">📍 Nereden İndirilir?</p>
                                <p>Trendyol Satıcı Paneli → Finansman → Cari Hesap Ekstresi → Dönem seçip İndir</p>
                                <p class="accounting-tooltip-title mt-2 mb-1 font-bold">📊 Ne İçerir?</p>
                                <p>İşlem Tarihi, Fiş Türü, Dekont No, Borç/Alacak, Barkod, Sipariş No, Açıklama</p>
                                <p class="accounting-tooltip-title mt-2 mb-1 font-bold">🔗 Neyle Bağlantılı?</p>
                                <p>Siparişlerdeki eksik barkodu tamamlar → Ürün maliyeti eşleşir → Kârlılık hesaplanır. Fatura Mutabakat Sistemi bu veriyi kullanır.</p>
                            </div>
                        </div>
                    </div>
                    <p class="text-sm text-gray-500 mb-3">Finansman → Cari Hesap Ekstresi → İndir</p>
                    <template x-if="hasType('transactions')">
                        <p class="mb-3 flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-bold text-slate-700">✅ Toplu yüklemede algılandı — <span x-text="detectedTypes.transactions.length"></span> dosya</p>
                    </template>
                    
                    <div x-data="{ isUploading: false, progress: 0 }" x-on:livewire-upload-start="isUploading = true" x-on:livewire-upload-finish="isUploading = false" x-on:livewire-upload-error="isUploading = false" x-on:livewire-upload-progress="progress = $event.detail.progress">
                        <input type="file" wire:model="transactionsFile" accept=".xlsx,.xls" id="transactionsFileInput" class="w-full text-base sm:text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-300 transition-colors cursor-pointer">
                        <div x-show="isUploading" class="mt-3">
                            <div class="flex justify-between text-xs mb-1"><span class="font-medium text-emerald-600">Dosya sisteme yükleniyor...</span><span class="font-bold text-emerald-600" x-text="progress + '%'"></span></div>
                            <div class="w-full bg-gray-100 rounded-full h-2 border border-gray-200"><div class="bg-emerald-500 h-2 rounded-full transition-all duration-300" x-bind:style="'width: ' + progress + '%'"></div></div>
                        </div>
                    </div>
                    @if($transactionsFile)<p class="text-xs text-green-700 font-medium mt-3 bg-green-50 p-2 rounded border border-green-100">✅ {{ $transactionsFile->getClientOriginalName() }} ({{ number_format($transactionsFile->getSize() / 1024, 0) }} KB) — Seçildi</p>@endif
                    @error('transactionsFile') <p class="text-red-500 text-xs mt-2">{{ $message }}</p> @enderror
                    <div class="mt-4 flex justify-end"><button wire:click="importTransactions" x-on:click="startProcessing()" wire:loading.attr="disabled" wire:target="transactionsFile,importTransactions,importAll" class="w-full sm:w-auto px-4 py-2 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-gray-900 disabled:opacity-50 transition-colors shadow relative overflow-hidden"><div class="relative z-10"><span wire:loading.remove wire:target="importTransactions">Sadece Bunu Kaydet</span><span wire:loading wire:target="importTransactions">⏳ Kaydediliyor</span></div></button></div>
                </div>

                {{-- Stopaj --}}
                <div class="accounting-upload-card rounded-xl p-4 lg:p-6 transition-all duration-500"
                     :class="hasType('stopaj') ? 'is-detected' : ''">
                    <div class="flex items-center justify-between mb-1">
                        <h3 class="font-semibold text-gray-900">🏛️ Stopaj / Tevkifat</h3>
                        <div x-data="{ show: false }" class="relative">
                            <button @click="show = !show" @click.outside="show = false" class="text-gray-400 transition-colors hover:text-slate-700"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></button>
                            <div x-show="show" x-transition class="accounting-tooltip-surface absolute right-0 top-8 z-50 w-72 rounded-xl p-3 text-xs leading-relaxed">
                                <p class="accounting-tooltip-title mb-1 font-bold">📍 Nereden İndirilir?</p>
                                <p>Trendyol Satıcı Paneli → Finansman → E-Ticaret Stopajı (veya gelir.gov.tr GİB Portal)</p>
                                <p class="accounting-tooltip-title mt-2 mb-1 font-bold">📊 Ne İçerir?</p>
                                <p>Sipariş No, Matrah (KDV Hariç Tutar), Hesaplanan Stopaj Tutarı (%1)</p>
                                <p class="accounting-tooltip-title mt-2 mb-1 font-bold">🔗 Neyle Bağlantılı?</p>
                                <p>Dashboard "Stopaj KPI" kartı, 193 Kodu Excel exportu (Mali Müşavir için), Denetim Motoru stopaj doğrulaması</p>
                            </div>
                        </div>
                    </div>
                    <p class="text-sm text-gray-500 mb-3">Finansman → E-Ticaret Stopajı → İndir</p>
                    <template x-if="hasType('stopaj')">
                        <p class="mb-3 flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-bold text-slate-700">✅ Toplu yüklemede algılandı — <span x-text="detectedTypes.stopaj.length"></span> dosya</p>
                    </template>
                    
                    <div x-data="{ isUploading: false, progress: 0 }" x-on:livewire-upload-start="isUploading = true" x-on:livewire-upload-finish="isUploading = false" x-on:livewire-upload-error="isUploading = false" x-on:livewire-upload-progress="progress = $event.detail.progress">
                        <input type="file" wire:model="stopajFile" accept=".xlsx,.xls" id="stopajFileInput" class="w-full text-base sm:text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-300 transition-colors cursor-pointer">
                        <div x-show="isUploading" class="mt-3">
                            <div class="flex justify-between text-xs mb-1"><span class="font-medium text-emerald-600">Dosya sisteme yükleniyor...</span><span class="font-bold text-emerald-600" x-text="progress + '%'"></span></div>
                            <div class="w-full bg-gray-100 rounded-full h-2 border border-gray-200"><div class="bg-emerald-500 h-2 rounded-full transition-all duration-300" x-bind:style="'width: ' + progress + '%'"></div></div>
                        </div>
                    </div>
                    @if($stopajFile)<p class="text-xs text-green-700 font-medium mt-3 bg-green-50 p-2 rounded border border-green-100">✅ {{ $stopajFile->getClientOriginalName() }} ({{ number_format($stopajFile->getSize() / 1024, 0) }} KB) — Seçildi</p>@endif
                    @error('stopajFile') <p class="text-red-500 text-xs mt-2">{{ $message }}</p> @enderror
                    <div class="mt-4 flex justify-end"><button wire:click="importStopaj" x-on:click="startProcessing()" wire:loading.attr="disabled" wire:target="stopajFile,importStopaj,importAll" class="w-full sm:w-auto px-4 py-2 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-gray-900 disabled:opacity-50 transition-colors shadow relative overflow-hidden"><div class="relative z-10"><span wire:loading.remove wire:target="importStopaj">Sadece Bunu Kaydet</span><span wire:loading wire:target="importStopaj">⏳ Kaydediliyor</span></div></button></div>
                </div>

                {{-- Faturalar --}}
                <div class="accounting-upload-card rounded-xl p-4 lg:p-6 transition-all duration-500"
                     :class="hasType('invoices') ? 'is-detected' : ''">
                    <div class="flex items-center justify-between mb-1">
                        <h3 class="font-semibold text-gray-900">🧾 Faturalar</h3>
                        <div x-data="{ show: false }" class="relative">
                            <button @click="show = !show" @click.outside="show = false" class="text-gray-400 transition-colors hover:text-slate-700"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></button>
                            <div x-show="show" x-transition class="accounting-tooltip-surface absolute right-0 top-8 z-50 w-72 rounded-xl p-3 text-xs leading-relaxed">
                                <p class="accounting-tooltip-title mb-1 font-bold">📍 Nereden İndirilir?</p>
                                <p>Trendyol Satıcı Paneli → Finansman → Faturalarım → Toplu İndir</p>
                                <p class="accounting-tooltip-title mt-2 mb-1 font-bold">📊 Ne İçerir?</p>
                                <p>Fatura No, Tarih, Tip (Komisyon/Kargo/Hizmet), KDV Tutarı, KDV Hariç ve Dahil Tutarlar</p>
                                <p class="accounting-tooltip-title mt-2 mb-1 font-bold">🔗 Neyle Bağlantılı?</p>
                                <p>Dashboard "Net KDV" kartı, Aylık Fatura Mutabakat paneli (Komisyon ve Kargo eşleştirmesi), Vergi hesaplamaları</p>
                            </div>
                        </div>
                    </div>
                    <p class="text-sm text-gray-500 mb-3">Finansman → Faturalarım → Toplu İndir</p>
                    <template x-if="hasType('invoices')">
                        <p class="mb-3 flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-bold text-slate-700">✅ Toplu yüklemede algılandı — <span x-text="detectedTypes.invoices.length"></span> dosya</p>
                    </template>
                    
                    <div x-data="{ isUploading: false, progress: 0 }" x-on:livewire-upload-start="isUploading = true" x-on:livewire-upload-finish="isUploading = false" x-on:livewire-upload-error="isUploading = false" x-on:livewire-upload-progress="progress = $event.detail.progress">
                        <input type="file" wire:model="invoicesFile" accept=".xlsx,.xls" id="invoicesFileInput" class="w-full text-base sm:text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-300 transition-colors cursor-pointer">
                        <div x-show="isUploading" class="mt-3">
                            <div class="flex justify-between text-xs mb-1"><span class="font-medium text-emerald-600">Dosya sisteme yükleniyor...</span><span class="font-bold text-emerald-600" x-text="progress + '%'"></span></div>
                            <div class="w-full bg-gray-100 rounded-full h-2 border border-gray-200"><div class="bg-emerald-500 h-2 rounded-full transition-all duration-300" x-bind:style="'width: ' + progress + '%'"></div></div>
                        </div>
                    </div>
                    @if($invoicesFile)<p class="text-xs text-green-700 font-medium mt-3 bg-green-50 p-2 rounded border border-green-100">✅ {{ $invoicesFile->getClientOriginalName() }} ({{ number_format($invoicesFile->getSize() / 1024, 0) }} KB) — Seçildi</p>@endif
                    @error('invoicesFile') <p class="text-red-500 text-xs mt-2">{{ $message }}</p> @enderror
                    <div class="mt-4 flex justify-end"><button wire:click="importInvoices" x-on:click="startProcessing()" wire:loading.attr="disabled" wire:target="invoicesFile,importInvoices,importAll" class="w-full sm:w-auto px-4 py-2 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-gray-900 disabled:opacity-50 transition-colors shadow relative overflow-hidden"><div class="relative z-10"><span wire:loading.remove wire:target="importInvoices">Sadece Bunu Kaydet</span><span wire:loading wire:target="importInvoices">⏳ Kaydediliyor</span></div></button></div>
                </div>

                {{-- Ödeme Detay (Hakedişler) --}}
                <div class="accounting-upload-card rounded-xl p-4 lg:p-6 transition-all duration-500"
                     :class="hasType('settlements') ? 'is-detected' : ''">
                    <div class="flex items-center justify-between mb-1">
                        <h3 class="font-semibold text-gray-900">💸 Ödeme Detay / Hakediş</h3>
                        <div x-data="{ show: false }" class="relative">
                            <button @click="show = !show" @click.outside="show = false" class="text-gray-400 transition-colors hover:text-slate-700"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></button>
                            <div x-show="show" x-transition class="accounting-tooltip-surface absolute right-0 top-8 z-50 w-72 rounded-xl p-3 text-xs leading-relaxed">
                                <p class="accounting-tooltip-title mb-1 font-bold">📍 Nereden İndirilir?</p>
                                <p>Trendyol Satıcı Paneli → Finansman → Ödemelerim → Ödeme İndir</p>
                                <p class="accounting-tooltip-title mt-2 mb-1 font-bold">📊 Ne İçerir?</p>
                                <p>Sipariş No, Teslim Tarihi, Vade Tarihi, Komisyon Oranı, TY Hakediş, Satıcı Hakediş, Stopaj Tutarı</p>
                                <p class="accounting-tooltip-title mt-2 mb-1 font-bold">🔗 Neyle Bağlantılı?</p>
                                <p>Nakit Akışı Kanban tablosu, Ödeme Zaman Çizelgesi (Vade tarihleri), Banka Tahsilat durumu, Sipariş Detay ekranı</p>
                            </div>
                        </div>
                    </div>
                    <p class="text-sm text-gray-500 mb-3">Finansman → Ödemelerim → Ödeme İndir</p>
                    <template x-if="hasType('settlements')">
                        <p class="mb-3 flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-bold text-slate-700">✅ Toplu yüklemede algılandı — <span x-text="detectedTypes.settlements.length"></span> dosya</p>
                    </template>
                    
                    <div x-data="{ isUploading: false, progress: 0 }" x-on:livewire-upload-start="isUploading = true" x-on:livewire-upload-finish="isUploading = false" x-on:livewire-upload-error="isUploading = false" x-on:livewire-upload-progress="progress = $event.detail.progress">
                        <input type="file" wire:model="settlementsFile" accept=".xlsx,.xls" id="settlementsFileInput" class="w-full text-base sm:text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-300 transition-colors cursor-pointer">
                        <div x-show="isUploading" class="mt-3">
                            <div class="flex justify-between text-xs mb-1"><span class="font-medium text-emerald-600">Dosya sisteme yükleniyor...</span><span class="font-bold text-emerald-600" x-text="progress + '%'"></span></div>
                            <div class="w-full bg-gray-100 rounded-full h-2 border border-gray-200"><div class="bg-emerald-500 h-2 rounded-full transition-all duration-300" x-bind:style="'width: ' + progress + '%'"></div></div>
                        </div>
                    </div>
                    @if($settlementsFile)<p class="text-xs text-green-700 font-medium mt-3 bg-green-50 p-2 rounded border border-green-100">✅ {{ $settlementsFile->getClientOriginalName() }} ({{ number_format($settlementsFile->getSize() / 1024, 0) }} KB) — Seçildi</p>@endif
                    @error('settlementsFile') <p class="text-red-500 text-xs mt-2">{{ $message }}</p> @enderror
                    <div class="mt-4 flex justify-end"><button wire:click="importSettlements" x-on:click="startProcessing()" wire:loading.attr="disabled" wire:target="settlementsFile,importSettlements,importAll" class="w-full sm:w-auto px-4 py-2 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-gray-900 disabled:opacity-50 transition-colors shadow relative overflow-hidden"><div class="relative z-10"><span wire:loading.remove wire:target="importSettlements">Sadece Bunu Kaydet</span><span wire:loading wire:target="importSettlements">⏳ Kaydediliyor</span></div></button></div>
                </div>
            </div>


            {{-- Import Errors --}}
            @if(count($importErrors) > 0)
                <div class="mt-4 bg-red-50 border border-red-200 rounded-xl p-4">
                    <h4 class="text-sm font-medium text-red-800 mb-2">İşleme Hataları ({{ count($importErrors) }})</h4>
                    <ul class="text-xs text-red-600 space-y-1 max-h-32 overflow-y-auto">
                        @foreach($importErrors as $err)
                            <li>• {{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        {{-- ═══════════════════════════════════════════════════════════════ --}}
        {{-- TAB 3: SİPARİŞ ARA (5N1K) --}}
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        @elseif($activeTab === 'search')
            <div class="bg-white rounded-xl border border-gray-200 p-4 lg:p-6 mb-6">
                <h3 class="font-semibold text-gray-900 mb-3">🔍 5N1K Sipariş Dedektifi</h3>
                <div class="flex flex-col sm:flex-row gap-3 items-center">
                    <input type="text" wire:model.defer="searchQuery" wire:keydown.enter="searchOrder"
                           placeholder="Sipariş No, Barkod, Stok Kodu veya Ürün adı yazın..."
                           class="flex-1 w-full rounded-lg border border-slate-200 px-4 py-3 text-base shadow-sm outline-none transition-all focus:border-slate-300 focus:ring-2 focus:ring-slate-200 sm:py-2 sm:text-sm">
                    <button wire:click="searchOrder"
                            class="w-full sm:w-auto px-6 py-3 sm:py-2 bg-gray-900 text-white rounded-lg text-sm font-medium hover:bg-gray-800 transition-colors shadow-sm flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        Ara
                    </button>
                </div>
                <p class="text-xs text-gray-400 mt-2 ml-1">İpucu: Kısmi iadeleri ve çoklu sepetleri bulmak için <strong class="text-gray-500">Barkod</strong> aramasını kullanın.</p>
            </div>

            @if($searchResult !== null)
                @if(count($searchResult) === 0)
                    <div class="text-center py-12 text-gray-400">
                        <p class="text-3xl mb-2">🔎</p>
                        <p>Sonuç bulunamadı</p>
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach($searchResult as $result)
                            <div wire:click="showOrderDetail({{ $result['id'] }})"
                                 class="bg-white rounded-xl border border-gray-200 p-4 cursor-pointer hover:shadow-md transition-shadow
                                        {{ $result['is_flagged'] ? 'border-red-300 bg-red-50' : '' }}">
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                    <div class="flex-1 min-w-0">
                                        <p class="font-medium text-gray-900 truncate">{{ $result['product_name'] }}</p>
                                        <p class="text-xs text-gray-500 mt-1">
                                            #{{ $result['order_number'] }} · {{ $result['barcode'] }} · {{ $result['order_date'] }} · <span class="font-medium text-slate-700">{{ $result['period_name'] ?? '' }}</span>
                                        </p>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2 flex-shrink-0 mt-2 sm:mt-0">
                                        <span class="px-2.5 py-1 text-xs font-semibold rounded-full {{ $result['status_color'] }} border border-opacity-20 shadow-sm">
                                            {{ $result['status'] }}
                                        </span>
                                        <span class="text-sm font-bold text-gray-900 ml-2">
                                            {{ number_format($result['gross_amount'], 2, ',', '.') }} ₺
                                        </span>
                                        @if($result['is_flagged'])
                                            <span class="px-2 py-1 bg-red-100 border border-red-200 text-red-800 text-xs font-bold rounded-md shadow-sm flex items-center gap-1" title="Hata Tespit Edildi">
                                                🚩 Sorunlu Satır
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            @endif

        {{-- ═══════════════════════════════════════════════════════════════ --}}
        {{-- TAB 4: DENETİM (AUDİT) --}}
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        @elseif($activeTab === 'audit')
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                <div class="flex flex-col sm:flex-row gap-2">
                    <button wire:click="runAudit" wire:loading.attr="disabled"
                            class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-amber-600 text-white rounded-lg text-sm font-medium hover:bg-amber-700 disabled:opacity-50 transition-colors">
                        <span wire:loading.remove wire:target="runAudit">🔍 Denetimi Çalıştır ({{ $this->activeAuditRuleCount }}/{{ \App\Services\AuditEngine::getRuleCount() }} Kural)</span>
                        <span wire:loading wire:target="runAudit">Denetim yapılıyor...</span>
                    </button>
                    <button wire:click="exportAuditReport"
                            class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 transition-colors flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Audit Excel
                    </button>
                </div>

                <select wire:model.live="auditFilter" class="px-3 py-2 border rounded-lg text-base sm:text-sm bg-white">
                    <option value="all">Tümü</option>
                    <option value="critical">🔴 Kritik</option>
                    <option value="warning">🟡 Uyarı</option>
                    <option value="info">🔵 Bilgi</option>
                </select>
            </div>

            {{-- ═══ Kural Yöneticisi Paneli (Açılır/Kapanır) ═══ --}}
            <div x-data="{ showRules: false }" class="mb-6">
                <button @click="showRules = !showRules"
                        class="w-full flex items-center justify-between px-4 py-3 bg-gradient-to-r from-gray-50 to-white border border-gray-200 rounded-xl hover:border-gray-300 transition-all shadow-sm group">
                    <div class="flex items-center gap-3">
                        <span class="text-lg">🛡️</span>
                        <div class="text-left">
                            <p class="font-semibold text-gray-800 text-sm">Denetim Kuralları</p>
                            <p class="text-xs text-gray-500">{{ $this->activeAuditRuleCount }}/{{ \App\Services\AuditEngine::getRuleCount() }} kural aktif — Seçimler firma ayarına kalıcı kaydedilir</p>
                        </div>
                    </div>
                    <svg class="w-5 h-5 text-gray-400 group-hover:text-gray-600 transition-transform duration-200" :class="{'rotate-180': showRules}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>

                <div x-show="showRules" x-collapse x-cloak class="mt-2 border border-gray-200 rounded-xl bg-white shadow-sm overflow-hidden">
                    @php
                        $categories = collect(\App\Services\AuditEngine::RULE_META)->groupBy('category');
                    @endphp
                    <div class="divide-y divide-gray-100">
                        @foreach($categories as $category => $rules)
                            <div class="px-4 py-2 bg-gray-50 border-b border-gray-100">
                                <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">{{ $category }}</span>
                            </div>
                            @foreach($rules as $method => $meta)
                                @php
                                    $isDisabled = in_array($method, $disabledAuditRules);
                                    $isImplicitlySuppressed = !$settingsLogInfoRules && ($meta['severity'] === 'info');
                                    $isInactive = $isDisabled || $isImplicitlySuppressed;
                                @endphp
                                <label class="flex items-start gap-3 px-4 py-3 hover:bg-gray-50 cursor-pointer transition-colors {{ $isInactive ? 'opacity-60' : '' }}">
                                    <input type="checkbox"
                                           wire:click="toggleAuditRule('{{ $method }}')"
                                           {{ !$isDisabled ? 'checked' : '' }}
                                           class="mt-0.5 rounded border-gray-300 text-amber-600 shadow-sm focus:ring-amber-200 w-4 h-4 flex-shrink-0">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="text-sm">{{ $meta['icon'] }}</span>
                                            <span class="font-medium text-sm text-gray-900">{{ $meta['title'] }}</span>
                                            <span class="px-1.5 py-0.5 text-[10px] font-mono rounded
                                                {{ $meta['severity'] === 'critical' ? 'bg-red-100 text-red-700' : ($meta['severity'] === 'warning' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-700') }}">
                                                {{ $meta['code'] }}
                                            </span>
                                            @if($isImplicitlySuppressed)
                                                <span class="px-1.5 py-0.5 text-[10px] font-mono rounded bg-slate-100 text-slate-600">
                                                    Bilgi logu kapalı
                                                </span>
                                            @endif
                                        </div>
                                        <p class="text-xs text-gray-500 mt-0.5 leading-relaxed">{{ $meta['tooltip'] }}</p>
                                    </div>
                                </label>
                            @endforeach
                        @endforeach
                    </div>
                </div>
            </div>

            @if($lastAuditResult)
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                    <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-center">
                        <p class="text-2xl font-bold text-red-600">{{ $lastAuditResult['total_errors'] }}</p>
                        <p class="text-xs text-red-500">Kritik Hata</p>
                    </div>
                    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-center">
                        <p class="text-2xl font-bold text-amber-600">{{ $lastAuditResult['total_warnings'] }}</p>
                        <p class="text-xs text-amber-500">Uyarı</p>
                    </div>
                    <div class="accounting-soft-panel rounded-xl border border-slate-200 p-4 text-center">
                        <p class="text-2xl font-bold text-slate-900">{{ number_format(abs($lastAuditResult['total_amount']), 0, ',', '.') }} ₺</p>
                        <p class="text-xs text-slate-500">Toplam Fark</p>
                    </div>
                </div>
            @endif

            {{-- Audit Log Listesi --}}
            @if($auditLogs->isNotEmpty())
                <div class="space-y-3">
                    @foreach($auditLogs as $log)
                        @php $ruleMeta = \App\Services\AuditEngine::getMetaByCode($log->rule_code); @endphp
                        <div class="bg-white rounded-xl border p-4
                                    {{ $log->severity === 'critical' ? 'border-red-300 bg-red-50' : ($log->severity === 'warning' ? 'border-amber-300 bg-amber-50' : 'border-slate-200 bg-slate-50') }}">
                            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="text-sm">{{ $log->severity_icon }}</span>
                                        {{-- Tooltip'li Kural Badge --}}
                                        <div x-data="{ tip: false }" class="relative inline-block">
                                            <span @mouseenter="tip = true" @mouseleave="tip = false"
                                                  class="px-2 py-0.5 text-xs font-mono rounded cursor-help {{ $log->severity === 'critical' ? 'bg-red-200 text-red-800' : ($log->severity === 'warning' ? 'bg-amber-200 text-amber-800' : 'bg-slate-200 text-slate-800') }}">
                                                {{ $log->rule_code }}
                                            </span>
                                            @if($ruleMeta)
                                            <div x-show="tip" x-transition.opacity
                                                 class="accounting-tooltip-surface absolute z-50 bottom-full left-0 mb-2 w-72 rounded-xl p-3 text-xs leading-relaxed pointer-events-none"
                                                 style="display: none;">
                                                <p class="accounting-tooltip-title mb-1 font-bold">{{ $ruleMeta['icon'] }} {{ $ruleMeta['title'] }}</p>
                                                <p>{{ $ruleMeta['tooltip'] }}</p>
                                            </div>
                                            @endif
                                        </div>
                                    </div>
                                    <p class="font-medium text-gray-900 text-sm">{{ $log->title }}</p>
                                    <p class="text-xs text-gray-600 mt-1 leading-relaxed">{{ $log->description }}</p>
                                </div>
                                @if($log->difference > 0)
                                    <span class="text-sm font-bold text-red-600 flex-shrink-0">
                                        {{ number_format($log->difference, 2, ',', '.') }} ₺
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @elseif($selectedPeriodId)
                <div class="text-center py-12 text-gray-400">
                    <p class="text-3xl mb-2">🛡️</p>
                    <p>Henüz denetim çalıştırılmamış</p>
                    <p class="text-sm mt-1">Yukarıdaki butona tıklayarak {{ \App\Services\AuditEngine::getRuleCount() }} kuralı çalıştırın</p>
                </div>
            @endif

        {{-- ═══════════════════════════════════════════════════════════════ --}}
        {{-- TAB 5: KÂRLILIK (Kâr Motoru — SKU Bazlı) --}}
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        @elseif($activeTab === 'profit')
            @if($selectedPeriodId)
                @php $profitItems = $this->profitData; @endphp

                {{-- Filtre + Export + COGS Sync --}}
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                    <div class="flex items-center gap-3">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model.live="showOnlyBleeding"
                                   class="w-4 h-4 text-red-600 rounded border-gray-300 focus:ring-red-500">
                            <span class="text-sm text-gray-700">Sadece zararlı ürünleri göster</span>
                        </label>
                    </div>
                    <div class="flex flex-col sm:flex-row gap-2">
                        <button wire:click="syncCogs" wire:loading.attr="disabled" wire:target="syncCogs"
                                class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 disabled:opacity-50 transition-colors flex items-center justify-center gap-2 shadow-sm">
                            <span wire:loading.remove wire:target="syncCogs">🔄 Maliyetleri Güncelle (Maliyet Senkronu)</span>
                            <span wire:loading wire:target="syncCogs">
                                <svg class="animate-spin h-4 w-4 inline mr-1" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                Güncelleniyor...
                            </span>
                        </button>
                        <button wire:click="exportUnitEconomics"
                                class="w-full sm:w-auto px-4 py-3 sm:py-2 bg-purple-600 text-white rounded-lg text-sm font-medium hover:bg-purple-700 transition-colors flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            Birim İktisadı Excel
                        </button>
                    </div>
                </div>

                {{-- COGS Coverage Info --}}
                @php
                    $cogsStats = \App\Models\MpProduct::where('user_id', auth()->id())
                        ->selectRaw('COUNT(*) as total, SUM(CASE WHEN cogs > 0 THEN 1 ELSE 0 END) as with_cogs')
                        ->first();
                    $cogsPct = $cogsStats->total > 0 ? round($cogsStats->with_cogs / $cogsStats->total * 100) : 0;
                @endphp
                <div class="mb-4 p-3 rounded-lg border flex flex-col sm:flex-row sm:items-center justify-between gap-2
                    {{ $cogsPct >= 90 ? 'bg-emerald-50 border-emerald-200' : ($cogsPct >= 70 ? 'bg-amber-50 border-amber-200' : 'bg-red-50 border-red-200') }}">
                    <div class="flex items-center gap-2">
                        <span class="text-sm">{{ $cogsPct >= 90 ? '✅' : ($cogsPct >= 70 ? '⚠️' : '🚨') }}</span>
                        <span class="text-sm font-medium {{ $cogsPct >= 90 ? 'text-emerald-800' : ($cogsPct >= 70 ? 'text-amber-800' : 'text-red-800') }}">
                            Maliyet Kapsamı: %{{ $cogsPct }} ({{ $cogsStats->with_cogs }}/{{ $cogsStats->total }} ürün)
                        </span>
                    </div>
                    <a href="{{ route('mp.products') }}" class="text-xs font-medium text-slate-700 underline hover:text-slate-900">
                        Ürünler sayfasında maliyet gir →
                    </a>
                </div>

                @if(count($profitItems) > 0)
                    {{-- Summary Bar --}}
                    @php
                        $totalProfit = collect($profitItems)->sum('total_net_profit');
                        $bleedingTotal = collect($profitItems)->where('is_bleeding', true)->count();
                    @endphp
                    <div class="flex flex-col sm:flex-row gap-4 mb-6">
                        <div class="flex-1 rounded-xl p-4 {{ $totalProfit >= 0 ? 'bg-emerald-50 border border-emerald-200' : 'bg-red-50 border border-red-200' }}">
                            <p class="text-xs font-semibold {{ $totalProfit >= 0 ? 'text-emerald-600' : 'text-red-600' }} uppercase">Toplam Net Kâr</p>
                            <p class="text-xl font-bold {{ $totalProfit >= 0 ? 'text-emerald-700' : 'text-red-700' }}">
                                {{ number_format($totalProfit, 0, ',', '.') }} ₺
                            </p>
                        </div>
                        <div class="flex-1 rounded-xl bg-white border border-gray-200 p-4">
                            <p class="text-xs font-semibold text-gray-400 uppercase">SKU Sayısı</p>
                            <p class="text-xl font-bold text-gray-900">{{ count($profitItems) }}</p>
                        </div>
                        @if($bleedingTotal > 0)
                            <div class="flex-1 rounded-xl bg-red-50 border border-red-200 p-4">
                                <p class="text-xs font-semibold text-red-600 uppercase">Kanayan Ürün</p>
                                <p class="text-xl font-bold text-red-700">{{ $bleedingTotal }}</p>
                            </div>
                        @endif
                    </div>

                    {{-- SKU Tablosu --}}
                    <div class="overflow-x-auto bg-white rounded-xl border border-gray-200">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ürün</th>
                                    <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:text-gray-700"
                                        wire:click="sortProfit('order_count')">
                                        Sipariş {{ $profitSortBy === 'order_count' ? ($profitSortDir === 'asc' ? '↑' : '↓') : '' }}
                                    </th>
                                    <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:text-gray-700"
                                        wire:click="sortProfit('total_gross')">
                                        Ciro {{ $profitSortBy === 'total_gross' ? ($profitSortDir === 'asc' ? '↑' : '↓') : '' }}
                                    </th>
                                    <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:text-gray-700"
                                        wire:click="sortProfit('total_hakedis')">
                                        Hakediş {{ $profitSortBy === 'total_hakedis' ? ($profitSortDir === 'asc' ? '↑' : '↓') : '' }}
                                    </th>
                                    <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Maliyet</th>
                                    <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:text-gray-700"
                                        wire:click="sortProfit('total_net_profit')">
                                        Net Kâr {{ $profitSortBy === 'total_net_profit' ? ($profitSortDir === 'asc' ? '↑' : '↓') : '' }}
                                    </th>
                                    <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:text-gray-700"
                                        wire:click="sortProfit('avg_margin')">
                                        Marj {{ $profitSortBy === 'avg_margin' ? ($profitSortDir === 'asc' ? '↑' : '↓') : '' }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($profitItems as $item)
                                    <tr class="{{ $item['is_bleeding'] ? 'bg-red-100 text-red-800' : 'hover:bg-gray-50' }}">
                                        <td class="px-3 py-3">
                                            <div class="min-w-0">
                                                <p class="text-sm font-medium truncate max-w-xs {{ $item['is_bleeding'] ? 'text-red-900' : 'text-gray-900' }}">
                                                    {{ $item['product_name'] }}
                                                </p>
                                                <p class="text-xs {{ $item['is_bleeding'] ? 'text-red-600' : 'text-gray-500' }}">
                                                    {{ $item['barcode'] }} · {{ $item['stock_code'] }}
                                                    @if(!$item['has_cogs'])
                                                        <span class="text-amber-500" title="{{ $item['cogs_missing_reason'] ?? 'Maliyet eşleşmesi bulunamadı' }}">⚠️ Maliyet yok</span>
                                                    @endif
                                                </p>
                                            </div>
                                        </td>
                                        <td class="px-3 py-3 text-right text-sm">{{ $item['order_count'] }} ({{ $item['total_quantity'] }} ad.)</td>
                                        <td class="px-3 py-3 text-right text-sm font-medium">{{ number_format($item['total_gross'], 0, ',', '.') }} ₺</td>
                                        <td class="px-3 py-3 text-right text-sm">{{ number_format($item['total_hakedis'], 0, ',', '.') }} ₺</td>
                                        <td class="px-3 py-3 text-right text-sm">{{ number_format($item['total_cogs'], 0, ',', '.') }} ₺</td>
                                        <td class="px-3 py-3 text-right text-sm font-bold {{ $item['is_bleeding'] ? 'text-red-800' : ($item['total_net_profit'] > 0 ? 'text-emerald-600' : 'text-gray-900') }}">
                                            {{ number_format($item['total_net_profit'], 0, ',', '.') }} ₺
                                            @if($item['is_bleeding'])
                                                <span class="ml-1">🩸</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-3 text-right text-sm font-medium {{ $item['avg_margin'] < 0 ? 'text-red-600' : ($item['avg_margin'] > 20 ? 'text-emerald-600' : 'text-gray-700') }}">
                                            %{{ $item['avg_margin'] }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-12 text-gray-400">
                        <p class="text-3xl mb-2">💰</p>
                        <p>Kârlılık verisi bulunamadı</p>
                        <p class="text-sm mt-1">"Teslim Edildi" durumunda sipariş olmalı</p>
                    </div>
                @endif
            @else
                <div class="text-center py-16 text-gray-400">
                    <p class="text-4xl mb-4">💰</p>
                    <p class="text-lg font-medium">Dönem seçin</p>
                </div>
            @endif

        {{-- ═══════════════════════════════════════════════════════════════ --}}
        {{-- TAB 6: SİPARİŞLER LİSTESİ --}}
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        @elseif($activeTab === 'orders')
            @if($selectedPeriodId)
                <div class="flex flex-col gap-4 mb-6">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="flex flex-wrap gap-3">

                            <select wire:model.live="orderStatusFilter" class="min-w-[150px] rounded-lg border border-slate-200 bg-white px-3 py-2 text-base text-slate-700 shadow-sm focus:ring-2 focus:ring-slate-200 sm:text-sm">
                                <option value="all">Durum: Tümü</option>
                                <option value="Teslim Edildi">Teslim Edilenler</option>
                                <option value="İade Edildi">İadeler</option>
                                <option value="İptal Edildi">İptaller</option>
                                <option value="Kargoda">Kargoda</option>
                            </select>

                            <select wire:model.live="perPage" class="min-w-[120px] rounded-lg border border-slate-200 bg-white px-3 py-2 text-base text-slate-700 shadow-sm focus:ring-2 focus:ring-slate-200 sm:text-sm" title="Sayfadaki Satır Sınırı">
                                <option value="20">20 Satır</option>
                                <option value="50">50 Satır</option>
                                <option value="100">100 Satır</option>
                            </select>

                            {{-- ⚙️ Kolon Özelleştirme Butonu --}}
                            <div x-data="{ open: false }" class="relative hidden md:block">
                                <button @click="open = !open" class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-600 shadow-sm transition-colors hover:bg-slate-50">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                                    ⚙️ Kolonlar
                                </button>
                                <div x-show="open" @click.outside="open = false" x-transition
                                     class="absolute left-0 top-full z-50 mt-1 w-56 rounded-xl border border-slate-200 bg-white p-3 shadow-xl space-y-1.5">
                                    <p class="mb-2 text-xs font-bold uppercase tracking-wider text-slate-400">Görünür Kolonlar</p>
                                    @foreach(\App\Livewire\MarketplaceAccounting::$allColumnDefs as $colKey => $colLabel)
                                        <label class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 transition-colors hover:bg-slate-50">
                                            <input type="checkbox" 
                                                   wire:click="toggleColumn('{{ $colKey }}')"
                                                   {{ in_array($colKey, $visibleColumns) ? 'checked' : '' }}
                                                   class="h-4 w-4 rounded border-gray-300 text-slate-900 shadow-sm focus:ring-slate-200">
                                            <span class="text-sm text-slate-700">{{ $colLabel }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        
                        <button wire:click="exportAllOrders"
                                class="flex w-full items-center justify-center gap-2 rounded-lg bg-slate-900 px-4 py-3 text-sm font-medium text-white transition-colors hover:bg-slate-800 sm:w-auto sm:py-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            Excel İndir
                        </button>
                    </div>

                    {{-- Bulk Reconcile İşlemleri --}}
                    @if(count($selectedOrders) > 0)
                        <div class="accounting-bulk-surface rounded-xl border border-slate-200 p-3 flex flex-col sm:flex-row items-center justify-between gap-4 mt-2 mb-2 shadow-sm">
                            <div class="flex items-center gap-2">
                                <span class="bg-slate-900 text-white text-xs font-bold px-2 py-1 rounded-[6px]">{{ count($selectedOrders) }}</span>
                                <span class="text-sm font-medium text-slate-900">Sipariş seçildi</span>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <button wire:click="bulkReconcile('lock')" class="px-3 py-2 bg-slate-900 text-white rounded-lg text-sm font-medium hover:bg-slate-800 transition-colors shadow-sm flex items-center gap-1.5">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                    Mutabık Kıl
                                </button>
                                <button wire:click="bulkReconcile('unlock')" class="px-3 py-2 bg-white text-slate-700 border border-slate-200 rounded-lg text-sm font-medium hover:bg-slate-50 transition-colors shadow-sm flex items-center gap-1.5">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
                                    Aç
                                </button>
                                <button wire:click="bulkPushToErp" class="px-3 py-2 bg-white text-slate-700 rounded-lg text-sm font-medium hover:bg-slate-50 transition-colors shadow-sm flex items-center gap-1.5 border border-slate-200">
                                    ☁️ ERP'ye Gönder
                                </button>
                                <button wire:click="retryFailedErpPushes" class="px-3 py-2 bg-white text-slate-700 border border-slate-200 rounded-lg text-sm font-medium hover:bg-slate-50 transition-colors shadow-sm flex items-center gap-1.5">
                                    🔄 Hatalıları Tekrarla
                                </button>
                            </div>
                        </div>
                    @endif

                    {{-- 5N1K Hızlı Filtre Çipleri --}}
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-sm font-semibold text-slate-500 mr-2">5N1K Dedektifi:</span>
                        
                        <button wire:click="$set('advancedOrderFilter', 'all')" 
                                class="accounting-chip px-3 py-1.5 text-xs font-semibold {{ $advancedOrderFilter === 'all' ? 'is-active' : '' }}">
                            Tümü
                        </button>
                        
                        <button wire:click="$set('advancedOrderFilter', 'lost_payments')" 
                                class="accounting-chip px-3 py-1.5 text-xs font-semibold {{ $advancedOrderFilter === 'lost_payments' ? 'is-active' : '' }}">
                            🤔 Kayıp Ödemeler (Yatmayanlar)
                        </button>
                        
                        <button wire:click="$set('advancedOrderFilter', 'underpaid')" 
                                class="accounting-chip px-3 py-1.5 text-xs font-semibold {{ $advancedOrderFilter === 'underpaid' ? 'is-active' : '' }}">
                            🚨 Eksik Yatanlar
                        </button>
                        
                        <button wire:click="$set('advancedOrderFilter', 'penalized')" 
                                class="accounting-chip px-3 py-1.5 text-xs font-semibold {{ $advancedOrderFilter === 'penalized' ? 'is-active' : '' }}">
                            ⚠️ Cezalı İşlemler
                        </button>

                        <button wire:click="$set('advancedOrderFilter', 'returned')" 
                                class="accounting-chip px-3 py-1.5 text-xs font-semibold {{ $advancedOrderFilter === 'returned' ? 'is-active' : '' }}">
                            📦 Sadece İadeler
                        </button>

                        <button wire:click="$set('advancedOrderFilter', 'cancelled')" 
                                class="accounting-chip px-3 py-1.5 text-xs font-semibold {{ $advancedOrderFilter === 'cancelled' ? 'is-active' : '' }}">
                            🚫 Sadece İptaller
                        </button>
                    </div>
                </div>



                @if(isset($orders) && count($orders) > 0)

                    {{-- ═══ DESKTOP TABLO (md ve üstü) ═══ --}}
                    <div class="hidden md:block overflow-x-auto bg-white rounded-xl border border-gray-200">
                        {{-- Resize CSS --}}
                        <style>
                            .col-resize-handle {
                                position: absolute; right: 0; top: 0; bottom: 0; width: 4px;
                                cursor: col-resize; background: transparent; z-index: 10;
                                transition: background 0.15s;
                            }
                            .col-resize-handle:hover, .col-resize-handle.active { background: #6366f1; }
                            .sortable-th { cursor: pointer; user-select: none; position: relative; }
                            .sortable-th:hover { background: #f3f4f6; }
                            /* Kompakt yazı boyutları */
                            #ordersTable .text-xs { font-size: 11px !important; }
                            #ordersTable .text-sm { font-size: 13px !important; }
                            #ordersTable .text-\[10px\] { font-size: 9px !important; }
                            /* Kolon genişliği kontrolü */
                            #ordersTable { table-layout: fixed; width: 100%; }
                            #ordersTable th, #ordersTable td { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
                        </style>
                        <table class="min-w-full divide-y divide-gray-200" x-data="columnResize()" id="ordersTable">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-3 w-8 text-center text-xs font-medium text-gray-500 uppercase" style="min-width:40px">
                                        <input type="checkbox" wire:model.live="selectAll" class="rounded border-gray-300 text-slate-900 shadow-sm focus:border-slate-300 focus:ring focus:ring-slate-200 focus:ring-opacity-50">
                                    </th>
                                    @php
                                        $sortableMap = \App\Livewire\MarketplaceAccounting::$sortableColumns;
                                        $colAligns = [
                                            'siparis' => 'text-left', 'urun' => 'text-left', 'durum' => 'text-left',
                                            'brut' => 'text-right', 'hakedis' => 'text-right', 'komisyon' => 'text-right',
                                            'kargo' => 'text-right', 'cogs' => 'text-right', 'net_kar' => 'text-right',
                                            'margin' => 'text-right', 'detay' => 'text-center',
                                        ];
                                    @endphp
                                    @foreach(\App\Livewire\MarketplaceAccounting::$allColumnDefs as $colKey => $colLabel)
                                        @if(in_array($colKey, $visibleColumns))
                                            @php
                                                $isSortable = isset($sortableMap[$colKey]);
                                                $dbCol = $sortableMap[$colKey] ?? null;
                                                $isActive = $dbCol && $orderSortBy === $dbCol;
                                                $align = $colAligns[$colKey] ?? 'text-left';
                                            @endphp
                                            <th class="px-3 py-3 {{ $align }} text-xs font-medium text-gray-500 uppercase {{ $isSortable ? 'sortable-th' : '' }}" style="position:relative; min-width:60px"
                                                @if($isSortable) wire:click="sortOrders('{{ $colKey }}')" @endif>
                                                <div class="flex items-center gap-1 {{ $align === 'text-right' ? 'justify-end' : ($align === 'text-center' ? 'justify-center' : '') }}">
                                                    <span>{{ $colLabel }}</span>
                                                    @if($isSortable)
                                                        @if($isActive)
                                                            <span class="text-slate-700 text-[10px]">{{ $orderSortDir === 'asc' ? '▲' : '▼' }}</span>
                                                        @else
                                                            <span class="text-gray-300 text-[10px]">⇅</span>
                                                        @endif
                                                    @endif
                                                </div>
                                                <div class="col-resize-handle" @mousedown.stop.prevent="startResize($event, $el.parentElement)"></div>
                                            </th>
                                        @endif
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($orders as $order)
                                    @php
                                        $isFuturePayment = false;
                                        if ($order->expected_payment_date) {
                                            $isFuturePayment = $order->expected_payment_date->copy()->startOfDay()->isAfter(\Carbon\Carbon::today());
                                        }
                                        $displayCogs = (float) $order->resolved_cogs_at_time;
                                        $hasDisplayCogs = $displayCogs > 0;
                                        $netProfit = $hasDisplayCogs ? $order->real_net_profit : null;
                                        $margin = ($hasDisplayCogs && (float) $order->gross_amount > 0) ? round($netProfit / (float) $order->gross_amount * 100, 1) : null;
                                    @endphp
                                    <tr class="{{ $order->is_flagged ? 'bg-red-50' : ($order->is_reconciled ? 'bg-gray-100 opacity-75' : ($isFuturePayment ? 'bg-gray-50 opacity-80 border-l-4 border-amber-400' : 'hover:bg-gray-50')) }}">
                                        <td class="px-3 py-3 text-center">
                                            <input type="checkbox" wire:model.live="selectedOrders" value="{{ $order->id }}" class="rounded border-gray-300 text-slate-900 shadow-sm focus:border-slate-300 focus:ring focus:ring-slate-200 focus:ring-opacity-50">
                                        </td>
                                        @if(in_array('siparis', $visibleColumns))
                                        <td class="px-3 py-3">
                                            <div class="flex items-center gap-2">
                                                <div>
                                                    <div class="flex items-center gap-1">
                                                        <p class="text-sm font-medium text-gray-900">#{{ $order->order_number }}</p>
                                                        @if($order->is_reconciled)
                                                            <svg class="w-3.5 h-3.5 text-gray-500" fill="currentColor" viewBox="0 0 20 20" title="Mutabık (Kilitli)"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clipped-rule="evenodd" /></svg>
                                                        @endif
                                                        @if($order->erp_status === 'success')
                                                            <span title="ERP'ye İletildi" class="text-xs cursor-default">☁️</span>
                                                        @elseif($order->erp_status === 'pending' || $order->erp_status === 'retry')
                                                            <span title="ERP'ye Gönderilecek (Kuyrukta)" class="text-xs cursor-default animate-pulse">⏳</span>
                                                        @elseif($order->erp_status === 'failed')
                                                            <span title="ERP Hatası! Bknz: {{ Str::limit($order->erp_response, 100) }}" class="text-xs cursor-help">❌</span>
                                                        @endif
                                                    </div>
                                                    <p class="text-xs text-gray-500">{{ $order->order_date?->format('d.m.Y') }}</p>
                                                </div>
                                                @if($isFuturePayment)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-amber-100 text-amber-800" title="Beklenen Ödeme Tarihi: {{ $order->expected_payment_date?->format('d.m.Y') }}">
                                                        ⏳ Gelecek Vade
                                                    </span>
                                                @endif
                                            </div>
                                        </td>
                                        @endif
                                        @if(in_array('urun', $visibleColumns))
                                        <td class="px-3 py-3">
                                            <p class="text-sm truncate max-w-xs">{{ $order->resolved_product_name ?: 'Ürün Bilgisi Excel\'de Yok (Sadece Finansal Kayıt)' }}</p>
                                            <p class="text-xs text-gray-400">Barkod: {{ $order->resolved_barcode ?: 'Belirtilmedi' }} | Stok Kodu: {{ $order->resolved_stock_code ?: 'Belirtilmedi' }}</p>
                                        </td>
                                        @endif
                                        @if(in_array('durum', $visibleColumns))
                                        <td class="px-3 py-3">
                                            <span class="px-2 py-1 text-xs font-medium rounded-full {{ $order->status_color }}">
                                                {{ $order->status }}
                                            </span>
                                        </td>
                                        @endif
                                        @if(in_array('brut', $visibleColumns))
                                        <td class="px-3 py-3 text-right text-sm font-medium {{ $isFuturePayment ? 'opacity-50' : '' }}">{{ number_format($order->gross_amount, 2, ',', '.') }} ₺</td>
                                        @endif
                                        @if(in_array('hakedis', $visibleColumns))
                                        <td class="px-3 py-3 text-right text-sm {{ $isFuturePayment ? 'font-bold text-amber-600' : '' }}">
                                            {{ number_format($order->net_hakedis, 2, ',', '.') }} ₺
                                            @if($isFuturePayment)
                                                <div class="text-[10px] text-amber-500 font-normal">Henüz Yatmadı</div>
                                            @endif
                                        </td>
                                        @endif
                                        @if(in_array('komisyon', $visibleColumns))
                                        <td class="px-3 py-3 text-right text-sm {{ $isFuturePayment ? 'opacity-50' : '' }}">{{ number_format($order->commission_amount, 2, ',', '.') }} ₺</td>
                                        @endif
                                        @if(in_array('kargo', $visibleColumns))
                                        @php
                                            $svcCargo = new \App\Services\MpSettingsService();
                                            $kargoDisplay = $svcCargo->usesOwnCargo()
                                                ? (float)($order->resolved_own_cargo_cost_at_time ?? 0)
                                                : (float)$order->cargo_amount;
                                        @endphp
                                        <td class="px-3 py-3 text-right text-sm {{ $isFuturePayment ? 'opacity-50' : '' }}">
                                            {{ $kargoDisplay > 0 ? number_format($kargoDisplay, 2, ',', '.') . ' ₺' : '0,00 ₺' }}
                                        </td>
                                        @endif
                                        @if(in_array('cogs', $visibleColumns))
                                        <td class="px-3 py-3 text-right text-sm {{ $hasDisplayCogs ? '' : 'text-gray-400' }}"
                                            @if(!$hasDisplayCogs && $order->cogs_missing_reason) title="{{ $order->cogs_missing_reason }}" @endif>
                                            {{ $hasDisplayCogs ? number_format($displayCogs, 2, ',', '.') . ' ₺' : '—' }}
                                        </td>
                                        @endif
                                        @if(in_array('net_kar', $visibleColumns))
                                        <td class="px-3 py-3 text-right text-sm font-semibold {{ $netProfit !== null ? ($netProfit > 0 ? 'text-emerald-600' : ($netProfit < 0 ? 'text-red-600' : 'text-gray-400')) : 'text-gray-400' }}">
                                            {{ $netProfit !== null ? number_format($netProfit, 2, ',', '.') . ' ₺' : '—' }}
                                        </td>
                                        @endif
                                        @if(in_array('margin', $visibleColumns))
                                        <td class="px-3 py-3 text-right text-sm font-medium {{ $margin !== null ? ($margin > 20 ? 'text-emerald-600' : ($margin > 0 ? 'text-amber-600' : 'text-red-600')) : 'text-gray-400' }}">
                                            {{ $margin !== null ? '%' . $margin : '—' }}
                                        </td>
                                        @endif
                                        @if(in_array('detay', $visibleColumns))
                                        <td class="px-3 py-3 text-center">
                                            <button wire:click="showOrderDetail({{ $order->id }})" class="text-sm font-medium text-slate-700 hover:text-slate-900">
                                                5N1K
                                            </button>
                                        </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- ═══ MOBİL KART GÖRÜNÜMÜ (md altı) ═══ --}}
                    <div class="md:hidden space-y-3">
                        @foreach($orders as $order)
                            @php
                                $isFuturePayment = false;
                                if ($order->expected_payment_date) {
                                    $isFuturePayment = $order->expected_payment_date->copy()->startOfDay()->isAfter(\Carbon\Carbon::today());
                                }
                                $displayCogs = (float) $order->resolved_cogs_at_time;
                                $hasDisplayCogs = $displayCogs > 0;
                                $netProfit = $hasDisplayCogs ? $order->real_net_profit : null;
                                $margin = ($hasDisplayCogs && (float) $order->gross_amount > 0) ? round($netProfit / (float) $order->gross_amount * 100, 1) : null;
                            @endphp
                            <div class="bg-white rounded-xl border border-gray-200 p-4 {{ $order->is_flagged ? 'border-red-300 bg-red-50' : ($order->is_reconciled ? 'opacity-75' : '') }}">
                                {{-- Kart Başlık --}}
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center gap-2">
                                        <input type="checkbox" wire:model.live="selectedOrders" value="{{ $order->id }}" class="h-4 w-4 rounded border-gray-300 text-slate-900 shadow-sm">
                                        <div>
                                            <div class="flex items-center gap-1.5">
                                                <span class="text-sm font-bold text-gray-900">#{{ $order->order_number }}</span>
                                                @if($order->is_reconciled)
                                                    <svg class="w-3.5 h-3.5 text-gray-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clipped-rule="evenodd" /></svg>
                                                @endif
                                            </div>
                                            <p class="text-xs text-gray-400">{{ $order->order_date?->format('d.m.Y') }}</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full {{ $order->status_color }}">{{ $order->status }}</span>
                                        <button wire:click="showOrderDetail({{ $order->id }})" class="rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700 transition-colors hover:bg-slate-100">
                                            5N1K
                                        </button>
                                    </div>
                                </div>

                                {{-- Ürün --}}
                                <p class="text-xs text-gray-600 truncate mb-3">{{ $order->resolved_product_name ?: 'Finansal Kayıt' }}</p>

                                {{-- Finansal Grid --}}
                                <div class="grid grid-cols-2 gap-x-4 gap-y-2 text-xs">
                                    @if(in_array('brut', $visibleColumns))
                                    <div class="flex justify-between">
                                        <span class="text-gray-400">Brüt</span>
                                        <span class="font-medium text-gray-700">{{ number_format($order->gross_amount, 2, ',', '.') }} ₺</span>
                                    </div>
                                    @endif
                                    @if(in_array('hakedis', $visibleColumns))
                                    <div class="flex justify-between">
                                        <span class="text-gray-400">Hakediş</span>
                                        <span class="font-medium {{ $isFuturePayment ? 'text-amber-600' : 'text-gray-700' }}">{{ number_format($order->net_hakedis, 2, ',', '.') }} ₺</span>
                                    </div>
                                    @endif
                                    @if(in_array('komisyon', $visibleColumns))
                                    <div class="flex justify-between">
                                        <span class="text-gray-400">Komisyon</span>
                                        <span class="font-medium text-gray-700">{{ number_format($order->commission_amount, 2, ',', '.') }} ₺</span>
                                    </div>
                                    @endif
                                    @if(in_array('kargo', $visibleColumns))
                                    <div class="flex justify-between">
                                        <span class="text-gray-400">Kargo</span>
                                        @php
                                            $svcCargoM = new \App\Services\MpSettingsService();
                                            $kargoDisplayM = $svcCargoM->usesOwnCargo()
                                                ? (float)($order->resolved_own_cargo_cost_at_time ?? 0)
                                                : (float)$order->cargo_amount;
                                        @endphp
                                        <span class="font-medium text-gray-700">{{ $kargoDisplayM > 0 ? number_format($kargoDisplayM, 2, ',', '.') . ' ₺' : '0,00 ₺' }}</span>
                                    </div>
                                    @endif
                                    @if(in_array('cogs', $visibleColumns))
                                    <div class="flex justify-between">
                                        <span class="text-gray-400">Maliyet</span>
                                        <span class="font-medium {{ $hasDisplayCogs ? 'text-gray-700' : 'text-gray-300' }}"
                                            @if(!$hasDisplayCogs && $order->cogs_missing_reason) title="{{ $order->cogs_missing_reason }}" @endif>
                                            {{ $hasDisplayCogs ? number_format($displayCogs, 2, ',', '.') . ' ₺' : '—' }}
                                        </span>
                                    </div>
                                    @endif
                                    @if(in_array('net_kar', $visibleColumns))
                                    <div class="flex justify-between">
                                        <span class="text-gray-400">Net Kâr</span>
                                        <span class="font-semibold {{ $netProfit !== null ? ($netProfit > 0 ? 'text-emerald-600' : ($netProfit < 0 ? 'text-red-600' : 'text-gray-300')) : 'text-gray-300' }}">
                                            {{ $netProfit !== null ? number_format($netProfit, 2, ',', '.') . ' ₺' : '—' }}
                                        </span>
                                    </div>
                                    @endif
                                    @if(in_array('margin', $visibleColumns))
                                    <div class="flex justify-between">
                                        <span class="text-gray-400">Marj</span>
                                        <span class="font-semibold {{ $margin !== null ? ($margin > 20 ? 'text-emerald-600' : ($margin > 0 ? 'text-amber-600' : 'text-red-600')) : 'text-gray-300' }}">
                                            {{ $margin !== null ? '%' . $margin : '—' }}
                                        </span>
                                    </div>
                                    @endif
                                </div>

                                @if($isFuturePayment)
                                    <div class="mt-2 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-amber-100 text-amber-800">⏳ Gelecek Vade</span>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    @if($orders instanceof \Illuminate\Pagination\LengthAwarePaginator)
                        <div class="mt-4">{{ $orders->links() }}</div>
                    @endif
                @else
                    <div class="text-center py-12 text-gray-400">
                        <p class="text-3xl mb-2">📋</p>
                        <p>Bu filtrelere uygun sipariş bulunamadı</p>
                    </div>
                @endif
            @else
                <div class="text-center py-16 text-gray-400">
                    <p class="text-4xl mb-4">📋</p>
                    <p class="text-lg font-medium">Dönem seçin</p>
                </div>
            @endif
        
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        {{-- TAB 7: AYARLAR (PROFESYONEL PANEL) --}}
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        @elseif($activeTab === 'settings')
            @include('livewire.mp-settings-panel')
        @endif
    </div>

    {{-- ═══════════════════════════════════════════════════════════════ --}}
    {{-- 5N1K SİPARİŞ DETAY MODAL --}}
    {{-- ═══════════════════════════════════════════════════════════════ --}}
    {{-- Orijinal Modal yerine yeni zenginleştirilmiş Sub-View --}}
    @include('livewire.mp-order-modal')

    {{-- Column Resize Alpine.js Component --}}
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('columnResize', () => ({
                resizing: false,
                startX: 0,
                startWidth: 0,
                currentTh: null,
                handle: null,

                startResize(e, th) {
                    this.resizing = true;
                    this.startX = e.pageX;
                    this.currentTh = th;
                    this.startWidth = th.offsetWidth;
                    this.handle = e.target;
                    this.handle.classList.add('active');

                    const onMouseMove = (ev) => {
                        if (!this.resizing) return;
                        const diff = ev.pageX - this.startX;
                        const newWidth = Math.max(40, this.startWidth + diff);
                        this.currentTh.style.width = newWidth + 'px';
                        this.currentTh.style.minWidth = newWidth + 'px';
                    };

                    const onMouseUp = () => {
                        this.resizing = false;
                        if (this.handle) this.handle.classList.remove('active');
                        this.currentTh = null;
                        document.removeEventListener('mousemove', onMouseMove);
                        document.removeEventListener('mouseup', onMouseUp);
                    };

                    document.addEventListener('mousemove', onMouseMove);
                    document.addEventListener('mouseup', onMouseUp);
                }
            }));
        });
    </script>
</div>
