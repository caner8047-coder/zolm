<div class="flex flex-col gap-4 p-4 lg:gap-6 lg:p-6">
    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
            <div class="min-w-0">
                <div class="inline-flex items-center rounded-[6px] border border-slate-200 bg-slate-50/70 px-2.5 py-1 font-mono text-xs text-slate-500">
                    ZOLM Fiyatlandırma
                </div>
                <h1 class="mt-3 text-xl font-bold text-slate-900 lg:text-2xl">Kâr Simülatörü</h1>
                <p class="mt-1 max-w-2xl text-sm text-slate-500">Farklı satış fiyatlarında ne kadar kâr edeceğinizi, komisyon ve kargo kesintilerini anında hesaplayın.</p>
            </div>

            <div class="grid w-full grid-cols-2 gap-3 sm:grid-cols-4 xl:w-auto">
                <div class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                    <p class="text-xs text-slate-500">Pazaryeri</p>
                    <p class="mt-1 truncate text-sm font-semibold text-slate-900">{{ ucfirst($marketplace) }}</p>
                </div>
                <div class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                    <p class="text-xs text-slate-500">Durum</p>
                    <p class="mt-1 text-sm font-semibold {{ $simulation['status'] === 'healthy' ? 'text-emerald-700' : ($simulation['status'] === 'warning' ? 'text-amber-700' : 'text-rose-700') }}">
                        {{ $simulation['status'] === 'healthy' ? 'Kârlı' : ($simulation['status'] === 'warning' ? 'Düşük marj' : 'Zarar') }}
                    </p>
                </div>
                <div class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                    <p class="text-xs text-slate-500">KDV</p>
                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ $vatEnabled ? ($microExport ? 'İhracat' : '%' . number_format((float) $vatRate, 1, ',', '.')) : 'Kapalı' }}</p>
                </div>
                <div class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                    <p class="text-xs text-slate-500">Fiyat gönderimi</p>
                    <p class="mt-1 text-sm font-semibold text-slate-500">Kapalı</p>
                </div>
            </div>
        </div>

        <div class="mt-5 grid grid-cols-1 gap-3 lg:grid-cols-12 lg:gap-4">
            <label class="lg:col-span-4">
                <span class="text-xs font-medium text-slate-600">Ürün ara</span>
                <input type="search" wire:model.live.debounce.300ms="productSearch" placeholder="Ürün, stok kodu veya barkod"
                       class="mt-1.5 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none focus:border-slate-400 sm:py-2 sm:text-sm">
            </label>
            <label class="lg:col-span-4">
                <span class="text-xs font-medium text-slate-600">Ürün</span>
                <select wire:model.live="selectedProductId"
                        class="mt-1.5 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none focus:border-slate-400 sm:py-2 sm:text-sm">
                    <option value="">Manuel senaryo</option>
                    @foreach($productOptions as $product)
                        <option value="{{ $product->id }}">{{ $product->product_name }} · {{ $product->stock_code ?: $product->barcode }}</option>
                    @endforeach
                </select>
            </label>
            <label class="lg:col-span-4">
                <span class="text-xs font-medium text-slate-600">Kanal kaydı</span>
                <select wire:model.live="selectedListingId"
                        @disabled($listingOptions->isEmpty())
                        class="mt-1.5 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none focus:border-slate-400 disabled:bg-slate-50 disabled:text-slate-400 sm:py-2 sm:text-sm">
                    <option value="">Ürün kartı değerleri</option>
                    @foreach($listingOptions as $listing)
                        <option value="{{ $listing->id }}">{{ $listing->store?->store_name }} · {{ ucfirst((string) $listing->store?->marketplace) }} · {{ $this->formatMoney($listing->sale_price) }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </section>

    @if($message)
        <div class="rounded-[8px] border px-4 py-3 text-sm {{ $messageType === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-800' }}">
            {{ $message }}
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-12 lg:gap-6">
        <section class="min-w-0 rounded-[10px] border border-slate-200 bg-white shadow-sm xl:col-span-7">
            <div class="border-b border-slate-200 px-4 py-4 lg:px-6">
                <h2 class="text-base font-semibold text-slate-900">Senaryo girdileri</h2>
                <p class="mt-1 text-sm text-slate-500">Satış, maliyet ve kanal kesintileri</p>
            </div>

            <div class="space-y-5 p-4 lg:p-6">
                <div>
                    <p class="text-xs font-semibold uppercase text-slate-500">Fiyat ve maliyet</p>
                    <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3 lg:gap-4">
                        @foreach([
                            ['salePrice', 'Satış fiyatı', '₺'],
                            ['cogs', 'Ürün maliyeti', '₺'],
                            ['packagingCost', 'Ambalaj', '₺'],
                            ['cargoCost', 'Kargo', '₺'],
                            ['returnCargoCost', 'İade kargo', '₺'],
                            ['extraCostFixed', 'Diğer sabit gider', '₺'],
                        ] as [$model, $label, $suffix])
                            <label class="min-w-0">
                                <span class="text-xs font-medium text-slate-600">{{ $label }}</span>
                                <div class="relative mt-1.5">
                                    <input type="number" min="0" step="0.01" wire:model.live.debounce.250ms="{{ $model }}"
                                           class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 pr-9 text-base text-slate-900 outline-none focus:border-slate-400 sm:py-2 sm:text-sm">
                                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-slate-400">{{ $suffix }}</span>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="border-t border-slate-100 pt-5">
                    <p class="text-xs font-semibold uppercase text-slate-500">Oransal kesintiler</p>
                    <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3 lg:gap-4">
                        @foreach([
                            ['commissionRate', 'Komisyon'],
                            ['serviceFeeRate', 'Hizmet oranı'],
                            ['advertisingRate', 'Reklam payı'],
                            ['returnRate', 'İade rezervi'],
                            ['withholdingRate', 'Stopaj'],
                            ['serviceFeeFixed', 'Sabit hizmet bedeli'],
                        ] as [$model, $label])
                            <label class="min-w-0">
                                <span class="text-xs font-medium text-slate-600">{{ $label }}</span>
                                <div class="relative mt-1.5">
                                    <input type="number" min="0" step="0.01" wire:model.live.debounce.250ms="{{ $model }}"
                                           class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 pr-9 text-base text-slate-900 outline-none focus:border-slate-400 sm:py-2 sm:text-sm">
                                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-slate-400">{{ $model === 'serviceFeeFixed' ? '₺' : '%' }}</span>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="border-t border-slate-100 pt-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-500">Vergi yaklaşımı</p>
                            <p class="mt-1 text-sm text-slate-500">KDV ve ödeme kesintisi kontrolleri</p>
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
                    </div>

                    <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3 lg:gap-4">
                        @foreach([
                            ['vatRate', 'Satış KDV'],
                            ['costVatRate', 'Maliyet KDV'],
                            ['expenseVatRate', 'Gider KDV'],
                        ] as [$model, $label])
                            <label>
                                <span class="text-xs font-medium text-slate-600">{{ $label }}</span>
                                <div class="relative mt-1.5">
                                    <input type="number" min="0" max="100" step="0.01" wire:model.live.debounce.250ms="{{ $model }}"
                                           @disabled(!$vatEnabled)
                                           class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 pr-9 text-base text-slate-900 outline-none focus:border-slate-400 disabled:bg-slate-50 disabled:text-slate-400 sm:py-2 sm:text-sm">
                                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-slate-400">%</span>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="border-t border-slate-100 pt-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-500">Hedef</p>
                            <p class="mt-1 text-sm text-slate-500">Önerilen satış fiyatı</p>
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
                        <span class="text-xs font-medium text-slate-600">{{ $targetMode === 'margin' ? 'Hedef kâr marjı' : 'Hedef net kâr' }}</span>
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

        <div class="min-w-0 space-y-4 xl:col-span-5">
            <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-5 xl:sticky xl:top-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase text-slate-500">Simülasyon sonucu</p>
                        <h2 class="mt-1 text-lg font-semibold text-slate-900">{{ $this->formatMoney($simulation['net_profit']) }} net kâr</h2>
                    </div>
                    <span class="rounded-[6px] px-2 py-1 font-mono text-xs {{ $simulation['status'] === 'healthy' ? 'bg-emerald-50 text-emerald-700' : ($simulation['status'] === 'warning' ? 'bg-amber-50 text-amber-700' : 'bg-rose-50 text-rose-700') }}">
                        %{{ number_format((float) $simulation['profit_margin_percent'], 1, ',', '.') }}
                    </span>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-3">
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                        <p class="text-xs text-slate-500">Net alacak</p>
                        <p class="mt-1 text-base font-semibold text-slate-900">{{ $this->formatMoney($simulation['net_receivable']) }}</p>
                    </div>
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                        <p class="text-xs text-slate-500">Maliyet getirisi</p>
                        <p class="mt-1 text-base font-semibold text-slate-900">%{{ number_format((float) $simulation['roi_percent'], 1, ',', '.') }}</p>
                    </div>
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                        <p class="text-xs text-slate-500">Başabaş fiyatı</p>
                        <p class="mt-1 text-base font-semibold text-slate-900">{{ $this->formatMoney($simulation['break_even_price']) }}</p>
                    </div>
                    <div class="rounded-[8px] border border-slate-900 bg-slate-900 p-3 text-white">
                        <p class="text-xs text-slate-300">Hedef fiyat</p>
                        <p class="mt-1 text-base font-semibold">{{ $simulation['target_price'] !== null ? $this->formatMoney($simulation['target_price']) : 'Hesaplanamadı' }}</p>
                    </div>
                </div>

                <div class="mt-5">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-slate-900">Kesinti dağılımı</p>
                        <span class="font-mono text-xs text-slate-500">{{ $this->formatMoney($simulation['breakdown']['total_deductions']) }}</span>
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

                <div class="mt-5 border-t border-slate-100 pt-4">
                    <label>
                        <span class="text-xs font-medium text-slate-600">Senaryo adı</span>
                        <input type="text" wire:model.defer="scenarioName" maxlength="160"
                               class="mt-1.5 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none focus:border-slate-400 sm:py-2 sm:text-sm">
                    </label>
                    @error('scenarioName') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                        <button type="button" wire:click="saveScenario" wire:loading.attr="disabled" wire:target="saveScenario"
                                class="w-full rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-60 sm:w-auto sm:py-2">
                            <span wire:loading.remove wire:target="saveScenario">{{ $activeScenarioId ? 'Senaryoyu güncelle' : 'Senaryoyu kaydet' }}</span>
                            <span wire:loading wire:target="saveScenario">Kaydediliyor...</span>
                        </button>
                        @if($selectedProduct)
                            <a href="{{ $this->productUrl() }}" class="w-full rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-center text-sm font-medium text-slate-700 hover:bg-slate-50 sm:w-auto sm:py-2">
                                Ürün maliyetini aç
                            </a>
                        @endif
                    </div>
                </div>
            </section>
        </div>
    </div>

    <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-200 px-4 py-4 sm:flex-row sm:items-center sm:justify-between lg:px-6">
            <div>
                <h2 class="text-base font-semibold text-slate-900">Kayıtlı senaryolar</h2>
                <p class="mt-1 text-sm text-slate-500">Son 12 fiyatlandırma çalışması</p>
            </div>
            <button type="button" wire:click="resetSimulator"
                    class="w-full rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 hover:bg-slate-50 sm:w-auto sm:py-2">
                Yeni senaryo
            </button>
        </div>

        <div class="divide-y divide-slate-100">
            @forelse($savedScenarios as $scenario)
                <div class="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between lg:px-6">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="truncate text-sm font-semibold text-slate-900">{{ $scenario->name }}</p>
                            <span class="rounded-[6px] bg-slate-100 px-2 py-0.5 font-mono text-xs text-slate-600">{{ ucfirst($scenario->marketplace) }}</span>
                        </div>
                        <p class="mt-1 truncate text-xs text-slate-500">
                            {{ $scenario->product?->product_name ?: 'Manuel senaryo' }}
                            · {{ $this->formatMoney(data_get($scenario->result_json, 'net_profit', 0)) }} kâr
                            · %{{ number_format((float) data_get($scenario->result_json, 'profit_margin_percent', 0), 1, ',', '.') }}
                        </p>
                    </div>
                    <div class="flex w-full gap-2 sm:w-auto">
                        <button type="button" wire:click="loadScenario({{ $scenario->id }})"
                                class="flex-1 rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-sm font-medium text-slate-700 hover:bg-slate-50 sm:flex-none sm:py-2">
                            Aç
                        </button>
                        <button type="button" wire:click="deleteScenario({{ $scenario->id }})"
                                class="flex-1 rounded-[6px] border border-rose-200 bg-white px-3 py-3 text-sm font-medium text-rose-700 hover:bg-rose-50 sm:flex-none sm:py-2">
                            Sil
                        </button>
                    </div>
                </div>
            @empty
                <div class="px-4 py-10 text-center text-sm text-slate-500 lg:px-6">Henüz kayıtlı fiyatlandırma senaryosu yok.</div>
            @endforelse
        </div>
    </section>
</div>
