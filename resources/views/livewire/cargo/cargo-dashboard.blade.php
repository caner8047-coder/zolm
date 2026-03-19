@php
    $summaryStats = $this->summaryStats;
    $costAnalysis = $this->costAnalysis;
    $errorDistribution = $this->errorTypeDistribution;
    $topErrorProducts = $this->topErrorProducts;
    $channelInsights = $this->channelInsights;
    $cargoCompanies = $this->cargoCompanies;
    $marketplaces = $this->marketplaces;
    $stores = $this->stores;

    $periodOptions = [
        'today' => 'Bugün',
        '7days' => 'Son 7 gün',
        '30days' => 'Son 30 gün',
        'thisMonth' => 'Bu ay',
        'custom' => 'Özel',
    ];

    $periodLabel = $periodOptions[$period] ?? 'Özel dönem';
    $matchRate = $summaryStats['total_orders'] > 0 ? ($summaryStats['matched_orders'] / $summaryStats['total_orders']) * 100 : 0;
    $errorRate = $summaryStats['total_orders'] > 0 ? ($summaryStats['error_count'] / $summaryStats['total_orders']) * 100 : 0;
    $totalLoss = $summaryStats['iade_tutar'] + $summaryStats['parca_tutar'] + max(0, $costAnalysis['net_cost']);

    $formatCount = fn ($value) => number_format((float) $value, 0, ',', '.');
    $formatMoney = fn ($value) => '₺' . number_format((float) $value, 0, ',', '.');
    $formatSignedMoney = fn ($value) => (($value > 0) ? '+₺' : (($value < 0) ? '-₺' : '₺')) . number_format(abs((float) $value), 0, ',', '.');
    $netTone = $costAnalysis['net_cost'] > 0 ? 'danger' : ($costAnalysis['net_cost'] < 0 ? 'success' : 'default');
    $marketplaceInsights = array_slice($channelInsights['marketplaces'] ?? [], 0, 4);
    $storeInsights = array_slice($channelInsights['stores'] ?? [], 0, 4);
    $categoryInsights = array_slice($channelInsights['categories'] ?? [], 0, 4);
    $errorTones = [
        'referans_eksik' => 'warning',
        'parca_eksik' => 'danger',
        'parca_fazla' => 'warning',
        'desi_fazla' => 'warning',
        'desi_eksik' => 'info',
        'tutar_fazla' => 'danger',
        'tutar_eksik' => 'success',
        'eslesmedi' => 'default',
    ];
    $recordTypeLabels = [
        'all' => 'Tüm kayıt tipleri',
        'siparis' => 'Sipariş',
        'iade' => 'İade / değişim',
        'parca' => 'Parça gönderisi',
    ];
    $activeFilters = array_values(array_filter([
        $filterCargoCompany !== '' ? 'Firma: ' . $filterCargoCompany : null,
        $filterMarketplace !== '' ? 'Pazaryeri: ' . $filterMarketplace : null,
        $filterStore !== '' ? 'Mağaza: ' . $filterStore : null,
        ($filterRecordType ?? 'all') !== 'all' ? 'Kayıt: ' . ($recordTypeLabels[$filterRecordType] ?? $filterRecordType) : null,
    ]));
    $showAdvancedFilters = $filterStore !== '' || ($filterRecordType ?? 'all') !== 'all';
@endphp

<div class="space-y-4 lg:space-y-6 overflow-hidden" wire:key="dashboard-{{ $period }}">
    <div class="flex flex-col xl:flex-row xl:items-start xl:justify-between gap-4 lg:gap-6">
        <div class="max-w-3xl">
            <h2 class="text-xl lg:text-2xl font-bold text-gray-900">Genel görünüm</h2>
            <p class="mt-1 text-sm lg:text-base text-gray-700">
                Sipariş hacmini, hata yoğunluğunu ve net etkiyi tek akışta izleyin.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            @foreach($periodOptions as $optionKey => $optionLabel)
                <button
                    type="button"
                    wire:click="$set('period', '{{ $optionKey }}')"
                    class="min-h-[44px] rounded-md px-4 py-2 text-sm font-medium transition {{ $period === $optionKey ? 'bg-indigo-600 text-white shadow-sm' : 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50' }}"
                >
                    {{ $optionLabel }}
                </button>
            @endforeach
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4">
        <x-zolm.stat-card
            variant="orders"
            label="Toplam sipariş"
            :value="$formatCount($summaryStats['total_orders'])"
            :description="$formatCount($summaryStats['total_reports']) . ' rapor işlendi'"
            tone="info"
        >
            <x-slot:icon>
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
            </x-slot:icon>
        </x-zolm.stat-card>
        <x-zolm.stat-card
            variant="orders"
            label="Eşleşme oranı"
            :value="'%' . number_format($matchRate, 1, ',', '.')"
            :description="$formatCount($summaryStats['matched_orders']) . ' sipariş eşleşti'"
            tone="success"
        >
            <x-slot:icon>
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </x-slot:icon>
        </x-zolm.stat-card>
        <x-zolm.stat-card
            variant="orders"
            label="Hatalı kayıt"
            :value="$formatCount($summaryStats['error_count'])"
            :description="'Hata oranı %' . number_format($errorRate, 1, ',', '.')"
            tone="danger"
        >
            <x-slot:icon>
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86l-7.5 13A1 1 0 003.66 18h16.68a1 1 0 00.87-1.5l-7.5-13a1 1 0 00-1.74 0z" />
                </svg>
            </x-slot:icon>
        </x-zolm.stat-card>
        <x-zolm.stat-card
            variant="orders"
            label="Net etki"
            :value="$formatSignedMoney($costAnalysis['net_cost'])"
            :description="$totalLoss > 0 ? 'Toplam kayıp ' . $formatMoney($totalLoss) : 'Ek kayıp görünmüyor'"
            :tone="$netTone"
        >
            <x-slot:icon>
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 17l6-6 4 4 8-8" />
                </svg>
            </x-slot:icon>
        </x-zolm.stat-card>
    </div>

    <x-zolm.section-card variant="orders" padding="p-4 lg:p-5">
        <div class="space-y-4">
            <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-3 lg:gap-4">
                <div>
                    <h3 class="text-base lg:text-lg font-semibold text-gray-900">Operasyon filtreleri</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        Genel görünümü firma, kanal ve kayıt tipine göre daraltın.
                    </p>
                </div>
                <p class="text-sm text-gray-500">
                    {{ count($activeFilters) > 0 ? implode(' · ', $activeFilters) : 'Tüm kargo firmaları, pazaryerleri ve mağazalar dahil.' }}
                    · {{ $periodLabel }} · {{ $formatCount($summaryStats['total_reports']) }} rapor
                </p>
            </div>

            @if($period === 'custom')
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 lg:gap-4">
                    <div>
                        <label class="block text-xs sm:text-sm font-medium text-gray-500">Başlangıç</label>
                        <input type="date" wire:model.live="customStartDate" class="mt-1 min-h-[44px] w-full rounded-md border border-gray-300 px-3 py-2 text-base sm:text-sm text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs sm:text-sm font-medium text-gray-500">Bitiş</label>
                        <input type="date" wire:model.live="customEndDate" class="mt-1 min-h-[44px] w-full rounded-md border border-gray-300 px-3 py-2 text-base sm:text-sm text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                    </div>
                </div>
            @endif

            <div x-data="{ showAdvanced: @js($showAdvancedFilters) }" class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3 lg:gap-4">
                    <div>
                        <label class="block text-xs sm:text-sm font-medium text-gray-500">Kargo firması</label>
                        <select wire:model.live="filterCargoCompany" class="mt-1 min-h-[44px] w-full rounded-md border border-gray-300 px-3 py-2 text-base sm:text-sm text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                            <option value="">Tüm kargo firmaları</option>
                            @foreach($cargoCompanies as $company)
                                <option value="{{ $company }}">{{ $company }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs sm:text-sm font-medium text-gray-500">Pazaryeri</label>
                        <select wire:model.live="filterMarketplace" class="mt-1 min-h-[44px] w-full rounded-md border border-gray-300 px-3 py-2 text-base sm:text-sm text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                            <option value="">Tüm pazaryerleri</option>
                            @foreach($marketplaces as $marketplace)
                                <option value="{{ $marketplace }}">{{ $marketplace }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="button" @click="showAdvanced = !showAdvanced" class="min-h-[44px] flex-1 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                            <span x-text="showAdvanced ? 'Gelişmiş filtreleri gizle' : 'Gelişmiş filtreler'"></span>
                        </button>
                    </div>
                </div>

                <div x-show="showAdvanced" x-cloak x-transition.opacity.duration.150ms class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 lg:gap-4">
                        <div>
                            <label class="block text-xs sm:text-sm font-medium text-gray-500">Mağaza</label>
                            <select wire:model.live="filterStore" class="mt-1 min-h-[44px] w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-base sm:text-sm text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                <option value="">Tüm mağazalar</option>
                                @foreach($stores as $store)
                                    <option value="{{ $store }}">{{ $store }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs sm:text-sm font-medium text-gray-500">Kayıt tipi</label>
                            <select wire:model.live="filterRecordType" class="mt-1 min-h-[44px] w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-base sm:text-sm text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                <option value="all">Tüm kayıt tipleri</option>
                                <option value="siparis">Sipariş</option>
                                <option value="iade">İade / değişim</option>
                                <option value="parca">Parça gönderisi</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-zolm.section-card>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 lg:gap-8">
        <x-zolm.section-card
            variant="orders"
            eyebrow="Öncelik Listesi"
            title="İlk bakılacak sorunlar"
            description="Önce hata tipi, sonra tekrar eden ürünleri görün."
            headerPadding="px-4 pt-4 pb-3 lg:px-6 lg:pt-6 lg:pb-3"
            bodyPadding="px-4 pt-1 pb-4 lg:px-6 lg:pt-1 lg:pb-6"
        >
            <div class="space-y-4">
                <div class="rounded-lg border border-gray-200 bg-slate-50/60 p-4">
                    <p class="text-sm font-semibold text-gray-900">Ana hata tipleri</p>
                    <div class="mt-3 space-y-2">
                        @forelse(array_slice($errorDistribution['details'], 0, 5) as $error)
                            <div class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white px-3 py-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-gray-900">{{ $error['name'] }}</p>
                                    <p class="mt-1 text-xs text-gray-500">{{ $formatCount($error['count']) }} kayıt · %{{ number_format($error['percentage'], 1, ',', '.') }}</p>
                                </div>
                                <span class="shrink-0 text-sm font-semibold {{ ($errorTones[$error['key']] ?? 'default') === 'danger' ? 'text-rose-600' : (($errorTones[$error['key']] ?? 'default') === 'success' ? 'text-emerald-600' : 'text-gray-900') }}">
                                    {{ $formatMoney($error['tutar']) }}
                                </span>
                            </div>
                        @empty
                            <div class="rounded-lg border border-dashed border-gray-300 bg-white px-4 py-6 text-center text-sm text-gray-500">
                                Seçili dönemde hata kaydı bulunmuyor.
                            </div>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-lg border border-gray-200 bg-slate-50/60 p-4">
                    <p class="text-sm font-semibold text-gray-900">En çok tekrar eden ürünler</p>
                    <div class="mt-3 space-y-2">
                        @forelse(array_slice($topErrorProducts, 0, 5) as $product)
                            <div class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white px-3 py-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-gray-900">{{ $product['urun_adi'] ?: 'İsimsiz ürün' }}</p>
                                    <p class="mt-1 text-xs text-gray-500">{{ $product['stok_kodu'] ?: 'Stok kodu yok' }} · {{ $formatCount($product['error_count']) }} hata</p>
                                </div>
                                <span class="shrink-0 text-sm font-semibold {{ $product['total_tutar_fark'] > 0 ? 'text-rose-600' : ($product['total_tutar_fark'] < 0 ? 'text-emerald-600' : 'text-gray-900') }}">
                                    {{ $formatSignedMoney($product['total_tutar_fark']) }}
                                </span>
                            </div>
                        @empty
                            <div class="rounded-lg border border-dashed border-gray-300 bg-white px-4 py-6 text-center text-sm text-gray-500">
                                Seçili dönemde öne çıkan ürün bulunmuyor.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </x-zolm.section-card>

        <x-zolm.section-card
            variant="orders"
            eyebrow="Operasyon Özeti"
            title="Risk alanları"
            description="Finansal etkileri ve öne çıkan kanalları tek blokta görün."
            headerPadding="px-4 pt-4 pb-3 lg:px-6 lg:pt-6 lg:pb-3"
            bodyPadding="px-4 pt-1 pb-4 lg:px-6 lg:pt-1 lg:pb-6"
        >
            <div class="space-y-3">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-2">
                    <div class="min-w-0 rounded-lg border border-l-4 border-gray-200 border-l-rose-500 bg-white p-2.5 shadow-sm">
                        <div class="flex items-start gap-2">
                            <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-rose-100 text-rose-600">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6" />
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500">Bize karşı</p>
                                <p class="mt-1 text-sm font-semibold leading-tight tracking-tight text-rose-600">{{ $formatMoney($costAnalysis['against_us_tutar']) }}</p>
                                <p class="mt-0.5 text-[11px] leading-4 text-gray-500">{{ $formatCount($costAnalysis['against_us_count']) }} kayıt</p>
                            </div>
                        </div>
                    </div>

                    <div class="min-w-0 rounded-lg border border-l-4 border-gray-200 border-l-green-500 bg-white p-2.5 shadow-sm">
                        <div class="flex items-start gap-2">
                            <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-green-100 text-green-600">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19V9m0 0l-4 4m4-4l4 4m6 6H3" />
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500">Lehimize</p>
                                <p class="mt-1 text-sm font-semibold leading-tight tracking-tight text-green-600">{{ $formatMoney($costAnalysis['for_us_tutar']) }}</p>
                                <p class="mt-0.5 text-[11px] leading-4 text-gray-500">{{ $formatCount($costAnalysis['for_us_count']) }} kayıt</p>
                            </div>
                        </div>
                    </div>

                    <div class="min-w-0 rounded-lg border border-l-4 border-gray-200 {{ $netTone === 'danger' ? 'border-l-rose-500' : ($netTone === 'success' ? 'border-l-green-500' : 'border-l-slate-400') }} bg-white p-2.5 shadow-sm">
                        <div class="flex items-start gap-2">
                            <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full {{ $netTone === 'danger' ? 'bg-rose-100 text-rose-600' : ($netTone === 'success' ? 'bg-green-100 text-green-600' : 'bg-slate-100 text-slate-600') }}">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 17l6-6 4 4 8-8" />
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500">Net sonuç</p>
                                <p class="mt-1 text-sm font-semibold leading-tight tracking-tight {{ $netTone === 'danger' ? 'text-rose-600' : ($netTone === 'success' ? 'text-green-600' : 'text-gray-900') }}">{{ $formatSignedMoney($costAnalysis['net_cost']) }}</p>
                                <p class="mt-0.5 text-[11px] leading-4 text-gray-500">Toplam etki</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-gray-200 bg-slate-50/60 p-3.5">
                    <p class="text-sm font-semibold text-gray-900">Kanal kırılımı</p>
                    <div class="mt-3 space-y-3">
                        <div class="space-y-2">
                            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-gray-500">Pazaryeri</p>
                            @forelse(array_slice($marketplaceInsights, 0, 3) as $insight)
                                <div class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white px-3 py-2.5">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-gray-900">{{ $insight['label'] }}</p>
                                        <p class="mt-0.5 text-xs text-gray-500">{{ $formatCount($insight['errors']) }} hata / {{ $formatCount($insight['total']) }} kayıt</p>
                                    </div>
                                    <span class="shrink-0 text-sm font-semibold text-gray-900">{{ $formatMoney($insight['tutar_impact']) }}</span>
                                </div>
                            @empty
                                <div class="rounded-lg border border-dashed border-gray-300 bg-white px-4 py-3.5 text-center text-sm text-gray-500">Veri yok.</div>
                            @endforelse
                        </div>

                        <div class="space-y-2">
                            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-gray-500">Mağaza</p>
                            @forelse(array_slice($storeInsights, 0, 3) as $insight)
                                <div class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white px-3 py-2.5">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-gray-900">{{ $insight['label'] }}</p>
                                        <p class="mt-0.5 text-xs text-gray-500">{{ $formatCount($insight['errors']) }} hata / {{ $formatCount($insight['total']) }} kayıt</p>
                                    </div>
                                    <span class="shrink-0 text-sm font-semibold text-gray-900">{{ $formatMoney($insight['tutar_impact']) }}</span>
                                </div>
                            @empty
                                <div class="rounded-lg border border-dashed border-gray-300 bg-white px-4 py-3.5 text-center text-sm text-gray-500">Veri yok.</div>
                            @endforelse
                        </div>

                        <div class="space-y-2">
                            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-gray-500">Kategori</p>
                            @forelse(array_slice($categoryInsights, 0, 3) as $insight)
                                <div class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white px-3 py-2.5">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-gray-900">{{ $insight['label'] }}</p>
                                        <p class="mt-0.5 text-xs text-gray-500">{{ $formatCount($insight['errors']) }} hata / {{ $formatCount($insight['total']) }} kayıt</p>
                                    </div>
                                    <span class="shrink-0 text-sm font-semibold text-gray-900">{{ $formatMoney($insight['tutar_impact']) }}</span>
                                </div>
                            @empty
                                <div class="rounded-lg border border-dashed border-gray-300 bg-white px-4 py-3.5 text-center text-sm text-gray-500">Veri yok.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </x-zolm.section-card>
    </div>
</div>
