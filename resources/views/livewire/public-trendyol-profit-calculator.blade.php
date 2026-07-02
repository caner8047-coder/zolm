<div class="mx-auto w-full max-w-[1440px] space-y-4 px-4 py-5 lg:space-y-6 lg:px-6 lg:py-8">
    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <div class="inline-flex items-center rounded-[6px] border border-slate-200 bg-slate-50/70 px-2.5 py-1 font-mono text-xs text-slate-500">
                    Ücretsiz ZOLM Aracı
                </div>
                <h1 class="mt-3 text-xl font-bold text-slate-900 lg:text-2xl">Trendyol Kâr Hesaplama</h1>
                <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-500">
                    Satış fiyatı, komisyon, kargo, KDV ve ürün maliyetini girerek gerçekçi net kârınızı görün.
                </p>
            </div>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:min-w-[520px]">
                <div class="min-w-0 border-l-2 border-slate-900 pl-3">
                    <p class="text-xs text-slate-500">Net kâr</p>
                    <p class="mt-1 truncate text-base font-semibold {{ $simulation['net_profit'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                        {{ $this->formatMoney($simulation['net_profit']) }}
                    </p>
                </div>
                <div class="min-w-0 border-l-2 border-slate-200 pl-3">
                    <p class="text-xs text-slate-500">Kâr marjı</p>
                    <p class="mt-1 text-base font-semibold text-slate-900">%{{ number_format((float) $simulation['profit_margin_percent'], 1, ',', '.') }}</p>
                </div>
                <div class="min-w-0 border-l-2 border-slate-200 pl-3">
                    <p class="text-xs text-slate-500">Başabaş</p>
                    <p class="mt-1 truncate text-base font-semibold text-slate-900">{{ $this->formatMoney($simulation['break_even_price']) }}</p>
                </div>
                <div class="min-w-0 border-l-2 border-slate-200 pl-3">
                    <p class="text-xs text-slate-500">Hedef fiyat</p>
                    <p class="mt-1 truncate text-base font-semibold text-slate-900">
                        {{ $simulation['target_price'] !== null ? $this->formatMoney($simulation['target_price']) : '-' }}
                    </p>
                </div>
            </div>
        </div>
    </section>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-12 lg:gap-6">
        <section class="min-w-0 rounded-[10px] border border-slate-200 bg-white shadow-sm xl:col-span-7">
            <div class="flex flex-col gap-3 border-b border-slate-200 px-4 py-4 sm:flex-row sm:items-center sm:justify-between lg:px-6">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Hesaplama bilgileri</h2>
                    <p class="mt-1 text-sm text-slate-500">Değerler değiştikçe sonuçlar otomatik yenilenir.</p>
                </div>
                <button type="button" wire:click="resetCalculator"
                        class="w-full rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 hover:bg-slate-50 sm:w-auto sm:py-2">
                    Sıfırla
                </button>
            </div>

            <div class="space-y-5 p-4 lg:p-6">
                <div>
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-xs font-semibold uppercase text-slate-500">Temel bilgiler</p>
                        <span class="font-mono text-xs text-slate-400">TRY</span>
                    </div>
                    <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3 lg:gap-4">
                        @foreach([
                            ['salePrice', 'Satış fiyatı', 'Ürünün müşteriye satış fiyatı'],
                            ['cogs', 'Alış / üretim maliyeti', 'KDV dahil ürün maliyeti'],
                            ['packagingCost', 'Ambalaj maliyeti', 'Kutu, paket ve sarf gideri'],
                            ['cargoCost', 'Kargo ücreti', 'Sipariş başına beklenen kargo'],
                            ['commissionRate', 'Komisyon oranı', 'Kategori veya sözleşme oranı'],
                            ['serviceFeeFixed', 'Sabit hizmet bedeli', 'Sipariş başına sabit kesinti'],
                        ] as [$model, $label, $hint])
                            <label class="min-w-0">
                                <span class="text-xs font-medium text-slate-600">{{ $label }}</span>
                                <div class="relative mt-1.5">
                                    <input type="number" min="0" step="0.01" wire:model.live.debounce.250ms="{{ $model }}"
                                           class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 pr-9 text-base text-slate-900 outline-none focus:border-slate-400 sm:py-2 sm:text-sm">
                                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-slate-400">{{ $model === 'commissionRate' ? '%' : '₺' }}</span>
                                </div>
                                <span class="mt-1 block text-xs leading-5 text-slate-400">{{ $hint }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="border-t border-slate-100 pt-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-500">Komisyon kısayolları</p>
                            <p class="mt-1 text-sm text-slate-500">Sözleşmenizdeki gerçek oranı kullanın.</p>
                        </div>
                        <div class="grid grid-cols-4 gap-2">
                            @foreach([10, 15, 20, 25] as $rate)
                                <button type="button" wire:click="applyCommissionPreset({{ $rate }})"
                                        class="rounded-[6px] border px-3 py-2 text-sm font-medium {{ (float) $commissionRate === (float) $rate ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}">
                                    %{{ $rate }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                <details data-testid="advanced-settings" class="border-t border-slate-100 pt-5">
                    <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-3 text-sm font-semibold text-slate-900">
                        <span>İleri ayarlar</span>
                        <span class="font-mono text-xs font-normal text-slate-400">KDV · stopaj · reklam · iade</span>
                    </summary>

                    <div class="mt-4 space-y-5">
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3 lg:gap-4">
                            <label>
                                <span class="text-xs font-medium text-slate-600">Teslimat tipi</span>
                                <select wire:model.live="deliveryType"
                                        class="mt-1.5 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none focus:border-slate-400 sm:py-2 sm:text-sm">
                                    <option value="standard">Standart teslimat</option>
                                    <option value="fast">Hızlı teslimat</option>
                                    <option value="seller">Satıcı kargosu</option>
                                </select>
                            </label>
                            @foreach([
                                ['serviceFeeRate', 'Oransal hizmet bedeli'],
                                ['advertisingRate', 'Reklam payı'],
                                ['returnRate', 'Beklenen iade oranı'],
                                ['returnCargoCost', 'İade kargo maliyeti'],
                                ['extraCostFixed', 'Diğer sabit gider'],
                            ] as [$model, $label])
                                <label>
                                    <span class="text-xs font-medium text-slate-600">{{ $label }}</span>
                                    <div class="relative mt-1.5">
                                        <input type="number" min="0" step="0.01" wire:model.live.debounce.250ms="{{ $model }}"
                                               class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 pr-9 text-base text-slate-900 outline-none focus:border-slate-400 sm:py-2 sm:text-sm">
                                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-slate-400">{{ in_array($model, ['serviceFeeRate', 'advertisingRate', 'returnRate'], true) ? '%' : '₺' }}</span>
                                    </div>
                                </label>
                            @endforeach
                        </div>

                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                            @foreach([
                                ['vatEnabled', 'KDV hesabı'],
                                ['withholdingEnabled', 'Stopaj hesabı'],
                                ['microExport', 'Mikro ihracat'],
                            ] as [$model, $label])
                                <label class="flex min-h-11 cursor-pointer items-center justify-between gap-3 rounded-[6px] border border-slate-200 bg-slate-50/60 px-3 py-2 text-sm text-slate-700">
                                    <span>{{ $label }}</span>
                                    <input type="checkbox" wire:model.live="{{ $model }}" class="h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-500">
                                </label>
                            @endforeach
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4 lg:gap-4">
                            @foreach([
                                ['vatRate', 'Satış KDV'],
                                ['costVatRate', 'Maliyet KDV'],
                                ['expenseVatRate', 'Gider KDV'],
                                ['withholdingRate', 'Stopaj oranı'],
                            ] as [$model, $label])
                                <label>
                                    <span class="text-xs font-medium text-slate-600">{{ $label }}</span>
                                    <div class="relative mt-1.5">
                                        <input type="number" min="0" max="100" step="0.01" wire:model.live.debounce.250ms="{{ $model }}"
                                               @disabled($model !== 'withholdingRate' ? !$vatEnabled : !$withholdingEnabled)
                                               class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 pr-9 text-base text-slate-900 outline-none focus:border-slate-400 disabled:bg-slate-50 disabled:text-slate-400 sm:py-2 sm:text-sm">
                                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-slate-400">%</span>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </details>

                <div class="border-t border-slate-100 pt-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-500">Hedef fiyat</p>
                            <p class="mt-1 text-sm text-slate-500">İstenen kâr düzeyine göre satış fiyatını bulun.</p>
                        </div>
                        <div class="inline-flex w-full rounded-[6px] border border-slate-200 bg-slate-50 p-1 sm:w-auto">
                            <button type="button" wire:click="setTargetMode('margin')"
                                    class="flex-1 rounded-[5px] px-4 py-2 text-sm font-medium sm:flex-none {{ $targetMode === 'margin' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-white' }}">
                                Marj
                            </button>
                            <button type="button" wire:click="setTargetMode('amount')"
                                    class="flex-1 rounded-[5px] px-4 py-2 text-sm font-medium sm:flex-none {{ $targetMode === 'amount' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-white' }}">
                                Kâr tutarı
                            </button>
                        </div>
                    </div>
                    <label class="mt-3 block max-w-sm">
                        <span class="text-xs font-medium text-slate-600">{{ $targetMode === 'margin' ? 'Hedef net kâr marjı' : 'Hedef net kâr tutarı' }}</span>
                        <div class="relative mt-1.5">
                            <input type="number" min="0" step="0.01"
                                   wire:model.live.debounce.250ms="{{ $targetMode === 'margin' ? 'targetMarginPercent' : 'targetProfitAmount' }}"
                                   class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 pr-9 text-base text-slate-900 outline-none focus:border-slate-400 sm:py-2 sm:text-sm">
                            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-slate-400">{{ $targetMode === 'margin' ? '%' : '₺' }}</span>
                        </div>
                    </label>
                </div>
            </div>
        </section>

        <aside class="min-w-0 space-y-4 xl:col-span-5">
            <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-5 xl:sticky xl:top-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase text-slate-500">Hesaplama sonucu</p>
                        <h2 data-testid="public-net-profit" class="mt-1 text-xl font-bold {{ $simulation['net_profit'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                            {{ $this->formatMoney($simulation['net_profit']) }}
                        </h2>
                        <p class="mt-1 text-sm text-slate-500">Sipariş başına tahmini net kâr</p>
                    </div>
                    <span class="rounded-[6px] px-2 py-1 font-mono text-xs {{ $simulation['status'] === 'healthy' ? 'bg-emerald-50 text-emerald-700' : ($simulation['status'] === 'warning' ? 'bg-amber-50 text-amber-700' : 'bg-rose-50 text-rose-700') }}">
                        {{ $simulation['status'] === 'healthy' ? 'Kârlı' : ($simulation['status'] === 'warning' ? 'Düşük marj' : 'Zarar') }}
                    </span>
                </div>

                <div class="mt-5 grid grid-cols-2 border-y border-slate-200">
                    <div class="border-b border-r border-slate-200 py-3 pr-3">
                        <p class="text-xs text-slate-500">Kâr marjı</p>
                        <p class="mt-1 text-base font-semibold text-slate-900">%{{ number_format((float) $simulation['profit_margin_percent'], 1, ',', '.') }}</p>
                    </div>
                    <div class="border-b border-slate-200 py-3 pl-3">
                        <p class="text-xs text-slate-500">Maliyet getirisi</p>
                        <p class="mt-1 text-base font-semibold text-slate-900">%{{ number_format((float) $simulation['roi_percent'], 1, ',', '.') }}</p>
                    </div>
                    <div class="border-r border-slate-200 py-3 pr-3">
                        <p class="text-xs text-slate-500">Net alacak</p>
                        <p class="mt-1 text-base font-semibold text-slate-900">{{ $this->formatMoney($simulation['net_receivable']) }}</p>
                    </div>
                    <div class="py-3 pl-3">
                        <p class="text-xs text-slate-500">Toplam kesinti</p>
                        <p class="mt-1 text-base font-semibold text-slate-900">{{ $this->formatMoney($simulation['breakdown']['total_deductions']) }}</p>
                    </div>
                </div>

                <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="border-l-2 border-slate-300 pl-3">
                        <p class="text-xs text-slate-500">Zarar etmeme fiyatı</p>
                        <p class="mt-1 text-lg font-semibold text-slate-900">{{ $this->formatMoney($simulation['break_even_price']) }}</p>
                    </div>
                    <div class="border-l-2 border-slate-900 pl-3">
                        <p class="text-xs text-slate-500">Hedef satış fiyatı</p>
                        <p data-testid="public-target-price" class="mt-1 text-lg font-semibold text-slate-900">
                            {{ $simulation['target_price'] !== null ? $this->formatMoney($simulation['target_price']) : 'Hesaplanamadı' }}
                        </p>
                    </div>
                </div>

                <div class="mt-5">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-sm font-semibold text-slate-900">Kesinti dağılımı</h3>
                        <span class="font-mono text-xs text-slate-400">Satış fiyatına oran</span>
                    </div>
                    <div class="mt-3 space-y-3">
                        @foreach([
                            ['commission', 'Komisyon', 'bg-amber-500'],
                            ['cargo', 'Kargo', 'bg-sky-500'],
                            ['service_fee', 'Hizmet', 'bg-slate-500'],
                            ['advertising', 'Reklam', 'bg-indigo-500'],
                            ['return_reserve', 'İade rezervi', 'bg-rose-500'],
                            ['withholding', 'Stopaj', 'bg-violet-500'],
                            ['net_vat', 'Net KDV', 'bg-emerald-500'],
                        ] as [$key, $label, $color])
                            @php
                                $value = (float) $simulation['breakdown'][$key];
                                $width = (float) $simulation['sale_price'] > 0 ? min(100, abs($value) / (float) $simulation['sale_price'] * 100) : 0;
                            @endphp
                            <div>
                                <div class="flex items-center justify-between gap-3 text-xs">
                                    <span class="text-slate-600">{{ $label }}</span>
                                    <span class="font-medium text-slate-900">{{ $this->formatMoney($value) }}</span>
                                </div>
                                <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full {{ $color }}" style="width: {{ $width }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                @if(!empty($simulation['warnings']))
                    <div class="mt-5 space-y-2">
                        @foreach($simulation['warnings'] as $warning)
                            <p class="rounded-[6px] border border-amber-200 bg-amber-50 px-3 py-2 text-xs leading-5 text-amber-800">{{ $warning }}</p>
                        @endforeach
                    </div>
                @endif

                <div class="mt-5 border-t border-slate-200 pt-4">
                    <p class="text-sm font-semibold text-slate-900">Mağazanızın gerçek verisiyle hesaplayın</p>
                    <p class="mt-1 text-xs leading-5 text-slate-500">ZOLM; ürün maliyetini, kanal komisyonunu, siparişleri ve ödemeleri tek yerde birleştirir.</p>
                    <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                        <a href="{{ route('login') }}"
                           class="w-full rounded-[6px] bg-slate-900 px-4 py-3 text-center text-sm font-medium text-white hover:bg-slate-800 sm:w-auto sm:py-2">
                            ZOLM’e giriş yap
                        </a>
                        <a href="{{ route('login') }}"
                           class="w-full rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-center text-sm font-medium text-slate-700 hover:bg-slate-50 sm:w-auto sm:py-2">
                            Excel verisini analiz et
                        </a>
                    </div>
                </div>
            </section>
        </aside>
    </div>

    <section class="border-t border-slate-200 py-4">
        <div class="grid grid-cols-1 gap-3 text-xs leading-5 text-slate-500 sm:grid-cols-3">
            <p><span class="font-semibold text-slate-700">Veri kaydı yok:</span> Bu araç girdilerinizi senaryo tablosuna kaydetmez.</p>
            <p><span class="font-semibold text-slate-700">Aynı hesap motoru:</span> ZOLM içindeki ürün simülatörüyle aynı formülü kullanır.</p>
            <p><span class="font-semibold text-slate-700">Kontrol sizde:</span> Komisyon ve vergi oranlarını güncel sözleşmenize göre girin.</p>
        </div>
    </section>
</div>
