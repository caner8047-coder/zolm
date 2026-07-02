@php
    $marketplaceStats = $this->marketplaceStats;
    $cargoCompanies = $this->cargoCompanies;
    $errorTypes = $this->errorTypes;
    $currentReport = $this->report;
    $suratReportOptions = $this->suratReportOptions;
    $columnDefs = \App\Livewire\Cargo\CargoChecker::$allColumnDefs;
    $sortableColumns = \App\Livewire\Cargo\CargoChecker::$sortableColumns;

    $messageClasses = match ($messageType) {
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
        'error' => 'border-rose-200 bg-rose-50 text-rose-800',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-800',
        default => 'border-sky-200 bg-sky-50 text-sky-800',
    };

    $formatCount = fn ($value) => number_format((float) $value, 0, ',', '.');
    $formatMoney = fn ($value) => '₺' . number_format((float) $value, 2, ',', '.');
    $formatSignedMoney = fn ($value) => (($value > 0) ? '+₺' : (($value < 0) ? '-₺' : '₺')) . number_format(abs((float) $value), 2, ',', '.');
    $formatDecimal = fn ($value) => number_format((float) $value, 2, ',', '.');
    $sortIcon = function (string $columnKey) use ($sortableColumns, $sortField, $sortDirection) {
        $dbColumn = $sortableColumns[$columnKey] ?? null;
        if (!$dbColumn) {
            return '';
        }

        return $sortField === $dbColumn
            ? ($sortDirection === 'asc' ? '▲' : '▼')
            : '⇅';
    };
    $showAdvancedFilters = ($filterErrorType ?? 'all') !== 'all' || ($filterMatched ?? 'all') !== 'all';
@endphp

<div class="w-full space-y-6 overflow-hidden">
    @if($message)
        <div class="rounded-2xl border p-4 text-sm shadow-sm {{ $messageClasses }}">
            {{ $message }}
        </div>
    @endif

    @if(!$hasResults)
        <div class="flex flex-col xl:flex-row xl:items-start xl:justify-between gap-4 lg:gap-6">
            <div class="min-w-0 max-w-3xl">
                <h2 class="text-xl lg:text-2xl font-bold text-slate-900">Desi ve tutar kontrolü</h2>
                <p class="mt-1 text-sm lg:text-base text-slate-700">
                    Kargo Excel’ini yükleyin, sistem referanslarıyla karşılaştırın ve farkları rapor olarak kaydedin.
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 lg:gap-4">
            <x-zolm.stat-card variant="orders" label="Ürün referansı" :value="$formatCount($marketplaceStats['products_ready'] ?? 0)" description="Karşılaştırma için hazır ürünler" :tone="($marketplaceStats['products_ready'] ?? 0) > 0 ? 'success' : 'warning'">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8 5-8-5m16 0l-8-5-8 5m16 0v10l-8 5-8-5V7" />
                    </svg>
                </x-slot:icon>
            </x-zolm.stat-card>
            <x-zolm.stat-card variant="orders" label="Sipariş referansı" :value="$formatCount($marketplaceStats['orders_total'] ?? 0)" description="Eşleşmede kullanılacak sipariş verisi" :tone="($marketplaceStats['orders_total'] ?? 0) > 0 ? 'success' : 'warning'">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6M7 4h10a2 2 0 012 2v12a2 2 0 01-2 2H7a2 2 0 01-2-2V6a2 2 0 012-2z" />
                    </svg>
                </x-slot:icon>
            </x-zolm.stat-card>
            <x-zolm.stat-card variant="orders" label="Takip no dolu" :value="$formatCount($marketplaceStats['orders_with_tracking'] ?? 0)" description="Takip kodu bulunan sipariş sayısı" tone="info">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A2 2 0 012 15.382V8.618a2 2 0 011.106-1.789L9 4m0 16l6-3m-6 3V4m6 13l6.894-3.447A2 2 0 0023 11.764V5.236a2 2 0 00-1.106-1.789L15 0m0 17V0m0 0L9 4" />
                    </svg>
                </x-slot:icon>
            </x-zolm.stat-card>
        </div>

        <x-zolm.section-card variant="orders" padding="p-4 lg:p-5">
            <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
                <div class="min-w-0">
                    <h3 class="text-base lg:text-lg font-semibold text-slate-900">Kayıtlı Sürat raporundan kontrol</h3>
                    <p class="mt-1 text-sm text-slate-500">
                        Sürat Raporları menüsünde kaydedilen günlük raporu seçin; Excel yüklemeden desi ve tutar check çalışır.
                    </p>
                </div>

                <div class="grid w-full grid-cols-1 gap-3 sm:grid-cols-[minmax(0,220px)_auto] xl:w-auto">
                    <select
                        wire:model.live="selectedSuratReportDate"
                        class="min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 sm:text-sm"
                    >
                        <option value="">Rapor tarihi seçin</option>
                        @foreach($suratReportOptions as $option)
                            <option value="{{ \Illuminate\Support\Carbon::parse($option->report_date)->toDateString() }}">
                                {{ \Illuminate\Support\Carbon::parse($option->report_date)->format('d.m.Y') }} · {{ number_format((int) $option->row_count, 0, ',', '.') }} gönderi · {{ number_format((float) $option->total_amount, 2, ',', '.') }} TL
                            </option>
                        @endforeach
                    </select>

                    <button
                        type="button"
                        wire:click="runFromSavedSuratReport"
                        wire:loading.attr="disabled"
                        wire:target="runFromSavedSuratReport"
                        class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 disabled:opacity-60 sm:w-auto sm:py-2"
                        @disabled($suratReportOptions->isEmpty())
                    >
                        <span wire:loading.remove wire:target="runFromSavedSuratReport">Raporla Check Yap</span>
                        <span wire:loading wire:target="runFromSavedSuratReport">Kontrol Ediliyor...</span>
                    </button>
                </div>
            </div>

            @if($suratReportOptions->isEmpty())
                <div class="mt-4 rounded-[8px] border border-dashed border-slate-300 bg-slate-50/70 px-4 py-4 text-sm text-slate-500">
                    Henüz kayıtlı Sürat günlük raporu yok. Önce Sürat Raporları menüsünden tarih aralığı raporu çekin.
                </div>
            @endif
        </x-zolm.section-card>

        <x-zolm.section-card variant="orders" padding="p-4 lg:p-6">
            <div class="space-y-5">
                <div>
                    <h3 class="text-base lg:text-lg font-semibold text-slate-900">Karşılaştırma dosyasını yükleyin</h3>
                    <p class="mt-1 text-sm text-slate-500">
                        Kargo raporunu içeri alın, firma seçimini yapın ve sonucu tek adımda kaydedin.
                    </p>
                </div>

                <label class="group block cursor-pointer rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-4 transition hover:border-slate-400 hover:bg-white">
                    <input type="file" wire:model="cargoFile" accept=".xlsx,.xls" class="hidden">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-3 lg:gap-4">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-indigo-600">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-6l-4-4m0 0L8 10m4-4v12" />
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-slate-900">{{ $cargoFile ? $cargoFile->getClientOriginalName() : 'Kargo Excel dosyasını seçin' }}</p>
                            <p class="mt-1 text-xs sm:text-sm text-slate-500">`.xlsx` veya `.xls` raporunu bu alandan yükleyin.</p>
                        </div>
                    </div>
                </label>
                @error('cargoFile') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror

                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                    <div>
                        <p class="text-sm font-medium text-slate-900">Varsayılan akış aktif</p>
                        <p class="mt-1 text-sm text-slate-500">Kargo raporu, sipariş ve ürün referanslarıyla otomatik eşleştirilir.</p>
                    </div>
                    <label class="flex items-center gap-3 text-sm text-slate-700">
                        <input type="checkbox" wire:model.live="useLegacyExcelReference" class="rounded border-slate-300 text-indigo-600 focus:ring-slate-200">
                        Legacy Excel referansı kullan
                    </label>
                </div>

                @if($useLegacyExcelReference)
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 lg:gap-4">
                        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-800">
                            <p class="font-semibold">Legacy ürün listesi kullanılacak</p>
                            <p class="mt-1 text-xs sm:text-sm">{{ $formatCount($this->productCount) }} ürün hazır.</p>
                        </div>
                        <label class="group block cursor-pointer rounded-lg border border-dashed border-amber-300 bg-white px-4 py-4 transition hover:border-amber-400">
                            <input type="file" wire:model="orderFile" accept=".xlsx,.xls" class="hidden">
                            <p class="text-sm font-medium text-slate-900">{{ $orderFile ? $orderFile->getClientOriginalName() : 'Sipariş Excel dosyasını seçin' }}</p>
                            <p class="mt-1 text-xs sm:text-sm text-slate-500">Legacy modda sipariş dosyası zorunludur.</p>
                        </label>
                    </div>
                    @error('orderFile') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 lg:gap-4">
                    <div>
                        <label class="block text-xs sm:text-sm font-medium text-slate-500">Kargo firması</label>
                        <select wire:model="cargoCompany" class="mt-1 min-h-[44px] w-full rounded-md border border-slate-300 px-3 py-2 text-base sm:text-sm text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                            @foreach($cargoCompanies as $name => $label)
                                <option value="{{ $name }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs sm:text-sm font-medium text-slate-500">Rapor adı</label>
                        <input
                            type="text"
                            wire:model="reportName"
                            placeholder="Örn: Sürat Kargo Mart 2026"
                            class="mt-1 min-h-[44px] w-full rounded-md border border-slate-300 px-3 py-2 text-base sm:text-sm text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200"
                        >
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                    <x-zolm.primary-button color="indigo" wire:click="runComparison" wire:loading.attr="disabled" wire:target="runComparison,cargoFile,orderFile">
                        <span wire:loading.remove wire:target="runComparison">Kontrol Et</span>
                        <span wire:loading wire:target="runComparison">Kontrol Ediliyor...</span>
                    </x-zolm.primary-button>
                    <p class="text-sm text-slate-500">Sonuçlar yeni bir rapor olarak kaydedilir.</p>
                </div>
            </div>
        </x-zolm.section-card>
    @else
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 lg:gap-6">
            <div>
                <h2 class="text-xl lg:text-2xl font-bold text-slate-900">{{ $currentReport?->name ?? 'Kargo karşılaştırması' }}</h2>
                <p class="mt-1 text-sm lg:text-base text-slate-700">
                    {{ $currentReport?->cargo_company ?? $cargoCompany }} · {{ $currentReport?->report_date?->format('d.m.Y') ?? now()->format('d.m.Y') }}
                </p>
            </div>

            <div class="flex w-full sm:w-auto flex-col sm:flex-row gap-3">
                <button wire:click="resetForm" class="min-h-[44px] w-full sm:w-auto rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                    Yeni Karşılaştırma
                </button>
                <x-zolm.primary-button color="emerald" compact wire:click="exportReport">Excel İndir</x-zolm.primary-button>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 lg:gap-4">
            <x-zolm.stat-card variant="orders" label="İncelenen sipariş" :value="$formatCount($stats['total_orders'])" description="Karşılaştırılan toplam kayıt" tone="info">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6M7 4h10a2 2 0 012 2v12a2 2 0 01-2 2H7a2 2 0 01-2-2V6a2 2 0 012-2z" />
                    </svg>
                </x-slot:icon>
            </x-zolm.stat-card>
            <x-zolm.stat-card variant="orders" label="Eşleşen" :value="$formatCount($stats['matched_orders'])" tone="success" description="Referansla eşleşen siparişler">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </x-slot:icon>
            </x-zolm.stat-card>
            <x-zolm.stat-card variant="orders" label="Hatalı" :value="$formatCount($stats['error_count'])" tone="danger" description="İnceleme gerektiren satırlar">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M4.93 19h14.14A2 2 0 0020.8 16L13.73 4a2 2 0 00-3.46 0L3.2 16A2 2 0 004.93 19z" />
                    </svg>
                </x-slot:icon>
            </x-zolm.stat-card>
            <x-zolm.stat-card variant="orders" label="Tutar farkı" :value="$formatSignedMoney($stats['total_tutar_diff'] ?? 0)" :tone="($stats['total_tutar_diff'] ?? 0) > 0 ? 'danger' : (($stats['total_tutar_diff'] ?? 0) < 0 ? 'success' : 'default')" description="Toplam finansal etki">
                <x-slot:icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 17l6-6 4 4 8-8" />
                    </svg>
                </x-slot:icon>
            </x-zolm.stat-card>
        </div>

        <x-zolm.section-card variant="orders" eyebrow="Fark Özeti" title="Beklenen ve gerçek değerler" description="Desi, tutar ve ek uyarılar tek blokta özetlenir." padding="p-4 lg:p-6">
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 lg:gap-6">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm font-semibold text-slate-900">Desi özeti</p>
                    <div class="mt-3 space-y-2">
                        <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-3 text-sm">
                            <span class="text-slate-500">Beklenen toplam</span>
                            <span class="font-semibold text-slate-900">{{ $formatDecimal($stats['total_expected_desi'] ?? 0) }}</span>
                        </div>
                        <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-3 text-sm">
                            <span class="text-slate-500">Gerçek toplam</span>
                            <span class="font-semibold text-slate-900">{{ $formatDecimal($stats['total_actual_desi'] ?? 0) }}</span>
                        </div>
                        <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-3 text-sm">
                            <span class="text-slate-500">Toplam fark</span>
                            <span class="font-semibold {{ ($stats['total_desi_diff'] ?? 0) > 0 ? 'text-rose-600' : (($stats['total_desi_diff'] ?? 0) < 0 ? 'text-emerald-600' : 'text-slate-900') }}">{{ $formatDecimal($stats['total_desi_diff'] ?? 0) }}</span>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm font-semibold text-slate-900">Tutar ve uyarı özeti</p>
                    <div class="mt-3 space-y-2">
                        <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-3 text-sm">
                            <span class="text-slate-500">Beklenen toplam</span>
                            <span class="font-semibold text-slate-900">{{ $formatMoney($stats['total_expected_tutar'] ?? 0) }}</span>
                        </div>
                        <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-3 text-sm">
                            <span class="text-slate-500">Gerçek toplam</span>
                            <span class="font-semibold text-slate-900">{{ $formatMoney($stats['total_actual_tutar'] ?? 0) }}</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                            <div class="rounded-lg border border-slate-200 bg-white px-3 py-3 text-sm">
                                <p class="text-slate-500">Referans uyarısı</p>
                                <p class="mt-1 font-semibold text-slate-900">{{ $formatCount($stats['reference_issue_count'] ?? 0) }}</p>
                            </div>
                            <div class="rounded-lg border border-slate-200 bg-white px-3 py-3 text-sm">
                                <p class="text-slate-500">İade / değişim</p>
                                <p class="mt-1 font-semibold text-amber-600">{{ $formatMoney($stats['iade_tutar'] ?? 0) }}</p>
                            </div>
                            <div class="rounded-lg border border-slate-200 bg-white px-3 py-3 text-sm">
                                <p class="text-slate-500">Parça gönderisi</p>
                                <p class="mt-1 font-semibold text-sky-600">{{ $formatMoney($stats['parca_tutar'] ?? 0) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </x-zolm.section-card>

        <x-zolm.section-card
            variant="orders"
            eyebrow="Filtreler"
            title="Sonuç listesi"
            description="Müşteri, hata tipi ve kayıt türüne göre detay filtreleyin."
            headerPadding="px-4 pt-4 pb-2 lg:px-6 lg:pt-6 lg:pb-2"
            bodyPadding="px-4 pb-4 lg:px-6 lg:pb-6"
        >
            @once
                <style>
                    .col-resize-handle { position: absolute; right: 0; top: 0; bottom: 0; width: 4px; cursor: col-resize; background: transparent; z-index: 10; transition: background 0.15s; }
                    .col-resize-handle:hover, .col-resize-handle.active { background: #6366f1; }
                    .sortable-th { cursor: pointer; user-select: none; position: relative; }
                    .sortable-th:hover { background: #f8fafc; }
                    #cargoCheckerResultsTable .text-xs { font-size: 10px !important; }
                    #cargoCheckerResultsTable .text-sm { font-size: 12px !important; }
                    #cargoCheckerResultsTable .text-\[10px\] { font-size: 8px !important; }
                    #cargoCheckerResultsTable { table-layout: fixed; width: 100%; }
                    #cargoCheckerResultsTable th, #cargoCheckerResultsTable td { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
                </style>
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
                                    const newWidth = Math.max(40, this.startWidth + (ev.pageX - this.startX));
                                    this.currentTh.style.width = newWidth + 'px';
                                    this.currentTh.style.minWidth = newWidth + 'px';
                                };

                                const onMouseUp = () => {
                                    this.resizing = false;
                                    if (this.handle) this.handle.classList.remove('active');
                                    document.removeEventListener('mousemove', onMouseMove);
                                    document.removeEventListener('mouseup', onMouseUp);
                                };

                                document.addEventListener('mousemove', onMouseMove);
                                document.addEventListener('mouseup', onMouseUp);
                            }
                        }));
                    });
                </script>
            @endonce

            @php
                $columnMeta = [
                    'date' => ['width' => '86px', 'align' => 'text-left'],
                    'customer' => ['width' => '132px', 'align' => 'text-left'],
                    'tracking' => ['width' => '116px', 'align' => 'text-left'],
                    'product' => ['width' => '200px', 'align' => 'text-left'],
                    'quantity' => ['width' => '54px', 'align' => 'text-center'],
                    'pieces' => ['width' => '58px', 'align' => 'text-center'],
                    'expected_desi' => ['width' => '86px', 'align' => 'text-center'],
                    'actual_desi' => ['width' => '86px', 'align' => 'text-center'],
                    'expected_amount' => ['width' => '96px', 'align' => 'text-center'],
                    'actual_amount' => ['width' => '96px', 'align' => 'text-center'],
                    'status' => ['width' => '78px', 'align' => 'text-center'],
                    'actions' => ['width' => '48px', 'align' => 'text-center'],
                ];
            @endphp

            <div x-data="{ showAdvanced: @js($showAdvancedFilters) }" class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3 lg:gap-4">
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="searchCustomer"
                        placeholder="Müşteri adı ara..."
                        class="w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200"
                    >
                    <select wire:model.live="filterType" class="w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                        <option value="siparis">Siparişler</option>
                        <option value="iade">İade / değişim</option>
                        <option value="parca">Parça gönderileri</option>
                        <option value="all">Tümü</option>
                    </select>
                    <button type="button" @click="showAdvanced = !showAdvanced" class="w-full rounded-lg border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                        <span x-text="showAdvanced ? 'Gelişmiş filtreleri gizle' : 'Gelişmiş filtreler'"></span>
                    </button>
                </div>

                <div x-show="showAdvanced" x-cloak x-transition.opacity.duration.150ms class="rounded-[8px] border border-dashed border-slate-200 bg-slate-50/80 p-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 lg:gap-4">
                        <select wire:model.live="filterErrorType" class="w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                            @foreach($errorTypes as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <select
                            wire:model.live="filterMatched"
                            @disabled(in_array($filterType, ['iade', 'parca'], true))
                            class="w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400"
                        >
                            <option value="all">Tüm eşleşmeler</option>
                            <option value="matched">Eşleşenler</option>
                            <option value="unmatched">Eşleşmeyenler</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="mt-6 mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 lg:gap-4">
                <div class="text-xs text-slate-500">{{ count($visibleColumns) }} / {{ count($columnDefs) }} kolon gösteriliyor</div>
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open" type="button" class="w-full sm:w-auto rounded-lg border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                        Kolonlar
                    </button>
                    <div x-show="open" @click.outside="open = false" x-transition class="absolute right-0 top-full z-30 mt-2 w-60 rounded-[10px] border border-slate-200 bg-white p-3 shadow-xl">
                        <p class="text-xs font-medium uppercase tracking-[0.16em] text-slate-500">Görünür Kolonlar</p>
                        <div class="mt-3 space-y-1.5">
                            @foreach($columnDefs as $colKey => $colLabel)
                                <label class="flex items-center gap-2 rounded-lg px-2 py-2 text-sm text-slate-700 transition hover:bg-slate-50">
                                    <input type="checkbox" wire:click="toggleColumn('{{ $colKey }}')" {{ in_array($colKey, $visibleColumns, true) ? 'checked' : '' }} class="rounded border-slate-300 text-slate-900 shadow-sm focus:ring-slate-200">
                                    <span>{{ $colLabel }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white overflow-hidden">
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full" x-data="columnResize()" id="cargoCheckerResultsTable">
                        <thead class="bg-slate-50 text-slate-500">
                            <tr>
                                @foreach($columnDefs as $colKey => $colLabel)
                                    @if(in_array($colKey, $visibleColumns, true))
                                        @php
                                            $meta = $columnMeta[$colKey];
                                            $isSortable = isset($sortableColumns[$colKey]);
                                            $align = $meta['align'];
                                        @endphp
                                        <th
                                            class="px-2 py-2 {{ $align }} text-xs font-medium uppercase tracking-[0.14em] {{ $isSortable ? 'sortable-th' : '' }}"
                                            style="position: relative; width: {{ $meta['width'] }}; min-width: 40px;"
                                            @if($isSortable) wire:click="sortTable('{{ $colKey }}')" @endif
                                        >
                                            <div class="flex items-center gap-1 {{ $align === 'text-right' ? 'justify-end' : ($align === 'text-center' ? 'justify-center' : '') }}">
                                                <span>{{ $colLabel }}</span>
                                                @if($isSortable)
                                                    <span class="text-[10px] {{ ($sortableColumns[$colKey] ?? null) === $sortField ? 'text-slate-700' : 'text-slate-300' }}">{{ $sortIcon($colKey) }}</span>
                                                @endif
                                            </div>
                                            <div class="col-resize-handle" @mousedown.stop.prevent="startResize($event, $el.parentElement)"></div>
                                        </th>
                                    @endif
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            @forelse($this->filteredItems as $item)
                                @php
                                    $referenceCodes = $item->reference_issue_codes;
                                    $referenceDetails = $item->reference_issue_details;
                                    $editableStockCode = $item->editable_stock_code;
                                    $errorInfo = $item->error_info;
                                    $rowClass = $item->is_iade
                                        ? 'bg-amber-50'
                                        : ($item->is_parca_gonderi
                                            ? 'bg-sky-50'
                                            : ($item->has_error
                                                ? ($item->isAgainstUs() ? 'bg-rose-50' : 'bg-amber-50/60')
                                                : 'bg-white'));
                                    $statusTone = match ($errorInfo['color']) {
                                        'red' => 'danger',
                                        'yellow' => 'warning',
                                        'green' => 'success',
                                        'orange' => 'warning',
                                        default => 'default',
                                    };
                                @endphp
                                <tr class="{{ $rowClass }}">
                                    @foreach($columnDefs as $colKey => $colLabel)
                                        @if(in_array($colKey, $visibleColumns, true))
                                            @switch($colKey)
                                                @case('date')
                                                    <td class="px-2 py-2 text-xs text-slate-500">{{ $item->tarih?->format('d.m.Y') ?? '-' }}</td>
                                                    @break
                                                @case('customer')
                                                    <td class="px-2 py-2 align-top">
                                                        <div class="truncate text-sm font-medium text-slate-900" title="{{ $item->musteri_adi }}">{{ $item->musteri_adi }}</div>
                                                    </td>
                                                    @break
                                                @case('tracking')
                                                    <td class="px-2 py-2 align-top">
                                                        @if($item->tracking_url)
                                                            <a href="{{ $item->tracking_url }}" target="_blank" class="block truncate text-xs font-mono text-slate-700 hover:text-slate-900 hover:underline">{{ $item->takip_kodu }}</a>
                                                        @else
                                                            <span class="block truncate text-xs font-mono text-slate-500">{{ Str::limit($item->takip_kodu, 18) }}</span>
                                                        @endif
                                                    </td>
                                                    @break
                                                @case('product')
                                                    <td class="px-2 py-2 align-top">
                                                        <p class="line-clamp-2 text-sm font-medium text-slate-900" title="{{ $item->urun_adi }}">{{ $item->urun_adi }}</p>
                                                        @if($item->error_type === 'referans_eksik' && count($referenceCodes) > 0)
                                                            <div class="mt-2 flex flex-wrap gap-1">
                                                                @foreach(array_slice($referenceCodes, 0, 2) as $code)
                                                                    <span class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2 py-1 text-[10px] font-mono text-amber-700">{{ Str::limit($code, 14) }}</span>
                                                                @endforeach
                                                                @if(count($referenceCodes) > 2)
                                                                    <span class="inline-flex items-center rounded-full border border-slate-200 bg-white px-2 py-1 text-[10px] text-slate-500">+{{ count($referenceCodes) - 2 }}</span>
                                                                @endif
                                                            </div>
                                                        @elseif($item->error_type === 'referans_eksik' && count($referenceDetails) > 0)
                                                            <p class="mt-2 text-[11px] text-amber-700">{{ $referenceDetails[0]['reference_note'] ?? 'Referans verisi eksik.' }}</p>
                                                        @endif
                                                    </td>
                                                    @break
                                                @case('quantity')
                                                    <td class="px-1 py-2 text-center text-sm text-slate-900">{{ $item->adet }}</td>
                                                    @break
                                                @case('pieces')
                                                    <td class="px-1 py-2 text-center text-sm {{ $item->parca_fark != 0 ? 'font-semibold text-rose-600' : 'text-slate-700' }}">{{ $item->gercek_parca }}</td>
                                                    @break
                                                @case('expected_desi')
                                                    <td class="px-1 py-2 text-center text-sm text-slate-500">{{ number_format($item->beklenen_desi, 1, ',', '.') }}</td>
                                                    @break
                                                @case('actual_desi')
                                                    <td class="px-1 py-2 text-center text-sm {{ $item->desi_fark > 2 ? 'font-semibold text-rose-600' : ($item->desi_fark < -2 ? 'font-semibold text-amber-600' : 'text-slate-900') }}">{{ number_format($item->gercek_desi, 1, ',', '.') }}</td>
                                                    @break
                                                @case('expected_amount')
                                                    <td class="px-1 py-2 text-center text-sm text-slate-500">{{ number_format($item->beklenen_tutar, 0, ',', '.') }}</td>
                                                    @break
                                                @case('actual_amount')
                                                    <td class="px-1 py-2 text-center text-sm {{ $item->tutar_fark > 5 ? 'font-semibold text-rose-600' : ($item->tutar_fark < -5 ? 'font-semibold text-amber-600' : 'text-slate-900') }}">{{ number_format($item->gercek_tutar, 0, ',', '.') }}</td>
                                                    @break
                                                @case('status')
                                                    <td class="px-1 py-2 text-center">
                                                        <x-zolm.status-badge class="px-1.5 py-0.5 text-[9px]" :tone="$statusTone">{{ Str::limit($errorInfo['label'], 8) }}</x-zolm.status-badge>
                                                    </td>
                                                    @break
                                                @case('actions')
                                                    <td class="px-1 py-2 text-center">
                                                        @if(auth()->user()?->isAdmin() && $editableStockCode && !$item->is_iade)
                                                            <button
                                                                wire:click="openProductEditModal(@js($editableStockCode), {{ $item->gercek_desi }}, {{ $item->gercek_tutar }}, {{ $item->gercek_parca }}, @js($item->urun_adi ?? ''))"
                                                                class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:border-slate-300 hover:text-slate-900"
                                                                title="{{ $item->error_type === 'referans_eksik' ? 'Eksik referansı tamamla' : 'Güncelle' }}"
                                                            >
                                                                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                                </svg>
                                                            </button>
                                                        @else
                                                            <span class="text-sm text-slate-300">-</span>
                                                        @endif
                                                    </td>
                                                    @break
                                            @endswitch
                                        @endif
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ max(count($visibleColumns), 1) }}" class="px-4 py-10 text-center text-sm text-slate-500">Sonuç bulunamadı.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="md:hidden space-y-3 p-4">
                    @forelse($this->filteredItems as $item)
                        @php
                            $errorInfo = $item->error_info;
                            $referenceCodes = $item->reference_issue_codes;
                            $referenceDetails = $item->reference_issue_details;
                            $editableStockCode = $item->editable_stock_code;
                            $cardTone = $item->is_iade
                                ? 'border-amber-200 bg-amber-50'
                                : ($item->is_parca_gonderi
                                    ? 'border-sky-200 bg-sky-50'
                                    : ($item->has_error
                                        ? ($item->isAgainstUs() ? 'border-rose-200 bg-rose-50' : 'border-amber-200 bg-amber-50')
                                        : 'border-slate-200 bg-white'));
                            $statusTone = match ($errorInfo['color']) {
                                'red' => 'danger',
                                'yellow' => 'warning',
                                'green' => 'success',
                                'orange' => 'warning',
                                default => 'default',
                            };
                        @endphp
                        <div class="rounded-xl border p-4 {{ $cardTone }}">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                        @if(in_array('date', $visibleColumns, true))
                                            <span>{{ $item->tarih?->format('d.m.Y') ?? '-' }}</span>
                                        @endif
                                        @if(in_array('tracking', $visibleColumns, true) && $item->takip_kodu)
                                            @if($item->tracking_url)
                                                <a href="{{ $item->tracking_url }}" target="_blank" class="font-mono text-[11px] text-slate-700 hover:underline">{{ Str::limit($item->takip_kodu, 14) }}</a>
                                            @else
                                                <span class="font-mono text-[11px]">{{ Str::limit($item->takip_kodu, 14) }}</span>
                                            @endif
                                        @endif
                                    </div>
                                    @if(in_array('customer', $visibleColumns, true))
                                        <p class="mt-2 truncate text-sm font-semibold text-slate-900">{{ $item->musteri_adi }}</p>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2">
                                    @if(in_array('status', $visibleColumns, true))
                                        <x-zolm.status-badge :tone="$statusTone">{{ $errorInfo['label'] }}</x-zolm.status-badge>
                                    @endif
                                    @if(in_array('actions', $visibleColumns, true) && auth()->user()?->isAdmin() && $editableStockCode && !$item->is_iade)
                                        <button
                                            wire:click="openProductEditModal(@js($editableStockCode), {{ $item->gercek_desi }}, {{ $item->gercek_tutar }}, {{ $item->gercek_parca }}, @js($item->urun_adi ?? ''))"
                                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500"
                                            title="{{ $item->error_type === 'referans_eksik' ? 'Eksik referansı tamamla' : 'Güncelle' }}"
                                        >
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                            </div>

                            @if(in_array('product', $visibleColumns, true))
                                <p class="mt-3 line-clamp-2 text-sm text-slate-700">{{ $item->urun_adi }}</p>
                            @endif

                            @if(in_array('product', $visibleColumns, true) && $item->error_type === 'referans_eksik' && count($referenceCodes) > 0)
                                <div class="mt-3 flex flex-wrap gap-1">
                                    @foreach(array_slice($referenceCodes, 0, 2) as $code)
                                        <span class="inline-flex items-center rounded-full border border-amber-200 bg-white px-2 py-1 text-[10px] font-mono text-amber-700">{{ Str::limit($code, 14) }}</span>
                                    @endforeach
                                    @if(count($referenceCodes) > 2)
                                        <span class="inline-flex items-center rounded-full border border-slate-200 bg-white px-2 py-1 text-[10px] text-slate-500">+{{ count($referenceCodes) - 2 }}</span>
                                    @endif
                                </div>
                            @elseif(in_array('product', $visibleColumns, true) && $item->error_type === 'referans_eksik' && count($referenceDetails) > 0)
                                <p class="mt-3 text-xs text-amber-700">{{ $referenceDetails[0]['reference_note'] ?? 'Referans verisi eksik.' }}</p>
                            @endif

                            <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-2">
                                @if(in_array('quantity', $visibleColumns, true))
                                    <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                        <span class="text-slate-500">Adet</span>
                                        <span class="font-semibold text-slate-900">{{ $item->adet }}</span>
                                    </div>
                                @endif
                                @if(in_array('pieces', $visibleColumns, true))
                                    <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                        <span class="text-slate-500">Parça</span>
                                        <span class="font-semibold {{ $item->parca_fark != 0 ? 'text-rose-600' : 'text-slate-900' }}">{{ $item->gercek_parca }}</span>
                                    </div>
                                @endif
                                @if(in_array('expected_desi', $visibleColumns, true))
                                    <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                        <span class="text-slate-500">Beklenen desi</span>
                                        <span class="font-semibold text-slate-900">{{ number_format($item->beklenen_desi, 1, ',', '.') }}</span>
                                    </div>
                                @endif
                                @if(in_array('actual_desi', $visibleColumns, true))
                                    <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                        <span class="text-slate-500">Gerçek desi</span>
                                        <span class="font-semibold {{ $item->desi_fark > 2 ? 'text-rose-600' : ($item->desi_fark < -2 ? 'text-amber-600' : 'text-slate-900') }}">{{ number_format($item->gercek_desi, 1, ',', '.') }}</span>
                                    </div>
                                @endif
                                @if(in_array('expected_amount', $visibleColumns, true))
                                    <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                        <span class="text-slate-500">Beklenen tutar</span>
                                        <span class="font-semibold text-slate-900">{{ number_format($item->beklenen_tutar, 0, ',', '.') }}</span>
                                    </div>
                                @endif
                                @if(in_array('actual_amount', $visibleColumns, true))
                                    <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                        <span class="text-slate-500">Gerçek tutar</span>
                                        <span class="font-semibold {{ $item->tutar_fark > 5 ? 'text-rose-600' : ($item->tutar_fark < -5 ? 'text-amber-600' : 'text-slate-900') }}">{{ number_format($item->gercek_tutar, 0, ',', '.') }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-8 text-center text-sm text-slate-500">Sonuç bulunamadı.</div>
                    @endforelse
                </div>

                @if(method_exists($this->filteredItems, 'hasPages') && $this->filteredItems->hasPages())
                    <div class="border-t border-slate-200 px-4 py-3">
                        {{ $this->filteredItems->links() }}
                    </div>
                @endif
            </div>
        </x-zolm.section-card>
    @endif

    @if($showEditModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
            <div class="flex min-h-screen items-center justify-center px-4 py-6 sm:py-8">
                <button type="button" class="fixed inset-0 bg-slate-900/40" wire:click="closeProductEditModal"></button>

                <div class="relative w-full max-w-lg rounded-[28px] border border-slate-200 bg-white p-4 lg:p-6 shadow-xl">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <x-zolm.eyebrow variant="classic">Hızlı Güncelleme</x-zolm.eyebrow>
                            <h3 class="mt-3 text-xl font-bold text-slate-900">Ürün kartını güncelle</h3>
                            <p class="mt-2 text-sm text-slate-500">Kargo raporundaki gerçek değerleri referans ürün kartına kaydedin.</p>
                        </div>
                        <button wire:click="closeProductEditModal" class="rounded-lg border border-slate-200 bg-white p-2 text-slate-400 transition hover:text-slate-700">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="mt-6 space-y-4">
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50 p-4">
                            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Stok kodu</p>
                            <p class="mt-2 text-lg font-semibold text-slate-900">{{ $editStokKodu }}</p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 lg:gap-4">
                            <div>
                                <label for="editDesi" class="text-xs sm:text-sm font-medium text-slate-700">Desi</label>
                                <input id="editDesi" type="number" step="0.01" wire:model="editDesi" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-center font-semibold text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                            </div>
                            <div>
                                <label for="editParca" class="text-xs sm:text-sm font-medium text-slate-700">Parça</label>
                                <input id="editParca" type="number" min="1" wire:model="editParca" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-center font-semibold text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                            </div>
                            <div>
                                <label for="editTutar" class="text-xs sm:text-sm font-medium text-slate-700">Tutar</label>
                                <input id="editTutar" type="number" step="0.01" wire:model="editTutar" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-center font-semibold text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-200">
                            </div>
                        </div>

                        <div class="rounded-[8px] border border-sky-200 bg-sky-50 p-4 text-sm text-sky-800">
                            Bu güncelleme sonraki karşılaştırmalarda beklenen desi, parça ve tutar hesabında kullanılacaktır.
                        </div>
                    </div>

                    <div class="mt-6 flex flex-col sm:flex-row justify-end gap-3">
                        <button type="button" wire:click="closeProductEditModal" class="w-full sm:w-auto rounded-lg border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                            İptal
                        </button>
                        <x-zolm.primary-button color="indigo" compact type="button" wire:click="updateProductFromModal">Güncelle</x-zolm.primary-button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
