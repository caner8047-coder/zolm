@php
    $lineCollection = collect($productionLines);
    $bomCollection = collect($masterBom);
    $selectedRecipeCount = $lineCollection->filter(fn ($line) => !empty($line['recipe_id']))->count();
    $plannedQuantity = $lineCollection->sum(fn ($line) => (float) ($line['quantity'] ?? 0));
    $totalCost = $bomCollection->sum('total_cost');
    $riskMaterialCount = $bomCollection->filter(fn ($item) => (float) ($item['unit_price'] ?? 0) <= 0)->count();
    $topCostItems = $bomCollection->sortByDesc('total_cost')->take(3);
@endphp

<div class="w-full space-y-4 lg:space-y-6">
    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
        <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1.25fr)_minmax(320px,0.75fr)]">
            <div class="min-w-0">
                <div class="inline-flex items-center rounded-[6px] border border-slate-200 bg-slate-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">
                    Fabrika Workspace
                </div>
                <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div class="min-w-0">
                        <h1 class="text-xl font-bold tracking-tight text-slate-950 lg:text-2xl">Üretim Planlama</h1>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">
                            Reçete bazlı üretim hedeflerini tek yerde toplayın; toplam malzeme ihtiyacı ve maliyet hesabı aynı ledger üzerinde güncellenir.
                        </p>
                    </div>
                    <button
                        type="button"
                        wire:click="calculateMasterBom"
                        class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 sm:w-auto sm:py-2"
                    >
                        <x-lucide.icon name="activity" class="h-4 w-4" />
                        Hesapla
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3 lg:gap-4">
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3 lg:p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Reçete</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-950">{{ $selectedRecipeCount }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ count($productionLines) }} plan satırı</p>
                </div>
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3 lg:p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Üretim</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-950">{{ number_format($plannedQuantity, 0, ',', '.') }}</p>
                    <p class="mt-1 text-xs text-slate-500">Toplam adet</p>
                </div>
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3 lg:p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Malzeme</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-950">{{ $bomCollection->count() }}</p>
                    <p class="mt-1 text-xs text-slate-500">Konsolide satır</p>
                </div>
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3 lg:p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Maliyet</p>
                    <p class="mt-2 text-xl font-semibold text-slate-950">{{ number_format($totalCost, 2, ',', '.') }} ₺</p>
                    <p class="mt-1 text-xs {{ $riskMaterialCount > 0 ? 'text-amber-600' : 'text-slate-500' }}">
                        {{ $riskMaterialCount > 0 ? $riskMaterialCount . ' fiyatsız satır' : 'Fiyatlar tamam' }}
                    </p>
                </div>
            </div>
        </div>
    </section>

    <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 p-4 lg:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Plan Kurucu</p>
                    <h2 class="mt-2 text-lg font-semibold text-slate-900">Üretilecek ürünler</h2>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <button
                        type="button"
                        wire:click="addLine"
                        class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 sm:w-auto sm:py-2"
                    >
                        <span class="text-base leading-none">+</span>
                        Ürün Ekle
                    </button>
                    <button
                        type="button"
                        wire:click="calculateMasterBom"
                        class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 sm:w-auto sm:py-2"
                    >
                        <x-lucide.icon name="check-circle" class="h-4 w-4" />
                        Planı Güncelle
                    </button>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 p-4 lg:grid-cols-[minmax(0,1fr)_300px] lg:p-6">
            <div class="space-y-3">
                @forelse($productionLines as $index => $line)
                    <article class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3 shadow-sm lg:p-4">
                        <div class="flex flex-col gap-3 md:flex-row md:items-start">
                            <div class="min-w-0 flex-1">
                                <div class="mb-2 flex items-center justify-between gap-3">
                                    <label class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Reçete</label>
                                    <span class="rounded-[6px] border border-slate-200 bg-white px-2 py-0.5 text-xs font-medium text-slate-500">Satır {{ $index + 1 }}</span>
                                </div>

                                @if($line['recipe_id'])
                                    <div class="flex min-h-[44px] items-center justify-between gap-3 rounded-[6px] border border-emerald-200 bg-emerald-50 px-3 py-2">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold text-emerald-900">{{ $line['recipe_name'] }}</p>
                                            <p class="mt-0.5 text-xs text-emerald-700">Aktif reçete seçildi</p>
                                        </div>
                                        <button
                                            type="button"
                                            wire:click="$set('productionLines.{{ $index }}.recipe_id', null)"
                                            class="shrink-0 rounded-[6px] border border-emerald-200 bg-white px-3 py-2 text-xs font-medium text-emerald-700 transition hover:bg-emerald-100"
                                        >
                                            Değiştir
                                        </button>
                                    </div>
                                @else
                                    <div class="relative">
                                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                            <x-lucide.icon name="search" class="h-4 w-4" />
                                        </div>
                                        <input
                                            wire:keyup.debounce.300ms="searchRecipe({{ $index }}, $event.target.value)"
                                            type="text"
                                            class="min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white py-2 pl-10 pr-3 text-base text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-slate-900 focus:ring-1 focus:ring-slate-900 sm:text-sm"
                                            placeholder="Aktif reçetelerde ara"
                                        >
                                        @if($searchingIndex === $index && count($recipeSearchResults) > 0)
                                            <div class="absolute z-30 mt-1 max-h-56 w-full overflow-y-auto rounded-[8px] border border-slate-200 bg-white shadow-lg">
                                                @foreach($recipeSearchResults as $rr)
                                                    <button
                                                        type="button"
                                                        wire:click="selectRecipe({{ $index }}, {{ $rr['id'] }})"
                                                        class="flex w-full items-center justify-between gap-3 border-b border-slate-100 px-3 py-2.5 text-left text-sm transition last:border-0 hover:bg-slate-50"
                                                    >
                                                        <span class="min-w-0">
                                                            <span class="block truncate font-medium text-slate-900">{{ $rr['name'] }}</span>
                                                            <span class="text-xs text-slate-500">{{ $rr['version'] }}</span>
                                                        </span>
                                                        <span class="shrink-0 text-xs font-semibold text-slate-700">{{ number_format($rr['total_cost'], 2, ',', '.') }} ₺</span>
                                                    </button>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            <div class="w-full md:w-36">
                                <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Adet</label>
                                <input
                                    wire:model.live.debounce.500ms="productionLines.{{ $index }}.quantity"
                                    type="number"
                                    min="1"
                                    class="min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-center text-base font-semibold text-slate-900 shadow-sm outline-none transition focus:border-slate-900 focus:ring-1 focus:ring-slate-900 sm:text-sm"
                                >
                            </div>

                            <div class="flex md:pt-7">
                                <button
                                    type="button"
                                    wire:click="removeLine({{ $index }})"
                                    class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-500 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600 md:w-auto"
                                    title="Satırı sil"
                                >
                                    Sil
                                </button>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="rounded-[8px] border border-dashed border-slate-300 bg-slate-50/60 p-6 text-center">
                        <p class="text-sm font-medium text-slate-700">Plan satırı yok</p>
                        <button type="button" wire:click="addLine" class="mt-3 inline-flex min-h-[44px] items-center justify-center rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 sm:py-2">
                            Ürün Ekle
                        </button>
                    </div>
                @endforelse
            </div>

            <aside class="space-y-3">
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Plan Özeti</p>
                    <div class="mt-4 space-y-3 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-slate-500">Seçili reçete</span>
                            <span class="font-semibold text-slate-900">{{ $selectedRecipeCount }}/{{ count($productionLines) }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-slate-500">Üretim adedi</span>
                            <span class="font-semibold text-slate-900">{{ number_format($plannedQuantity, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-slate-500">Malzeme satırı</span>
                            <span class="font-semibold text-slate-900">{{ $bomCollection->count() }}</span>
                        </div>
                    </div>
                </div>

                <div class="rounded-[8px] border border-slate-200 bg-white p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Maliyet Yoğunluğu</p>
                    <div class="mt-3 space-y-2">
                        @forelse($topCostItems as $item)
                            <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-3 py-2">
                                <div class="flex items-start justify-between gap-3">
                                    <p class="min-w-0 truncate text-xs font-medium text-slate-700">{{ $item['material_name'] }}</p>
                                    <span class="shrink-0 text-xs font-semibold text-slate-900">{{ number_format($item['total_cost'], 2, ',', '.') }} ₺</span>
                                </div>
                            </div>
                        @empty
                            <p class="rounded-[6px] border border-dashed border-slate-300 bg-slate-50/70 px-3 py-3 text-sm text-slate-500">Hesap bekleniyor.</p>
                        @endforelse
                    </div>
                </div>
            </aside>
        </div>
    </section>

    @if(count($masterBom) > 0)
        <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 p-4 lg:p-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Master BOM</p>
                        <h3 class="mt-2 text-lg font-semibold text-slate-900">Patlatılmış malzeme ihtiyacı</h3>
                    </div>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                        <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-3 py-2">
                            <p class="text-[11px] text-slate-500">Satır</p>
                            <p class="text-sm font-semibold text-slate-900">{{ $bomCollection->count() }}</p>
                        </div>
                        <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-3 py-2">
                            <p class="text-[11px] text-slate-500">Risk</p>
                            <p class="text-sm font-semibold {{ $riskMaterialCount > 0 ? 'text-amber-700' : 'text-slate-900' }}">{{ $riskMaterialCount }}</p>
                        </div>
                        <div class="rounded-[6px] border border-slate-200 bg-slate-900 px-3 py-2 text-white">
                            <p class="text-[11px] text-slate-300">Toplam</p>
                            <p class="whitespace-nowrap text-sm font-semibold">{{ number_format($totalCost, 2, ',', '.') }} ₺</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="hidden md:block overflow-x-auto rounded-b-[10px]">
                <table class="w-full text-left text-sm" style="table-layout: fixed; min-width: 920px;">
                    <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                        <tr>
                            <th class="px-4 py-3" style="width: 130px;">Stok Kodu</th>
                            <th class="px-4 py-3">Malzeme</th>
                            <th class="px-4 py-3" style="width: 140px;">Kategori</th>
                            <th class="px-4 py-3 text-right" style="width: 150px;">Miktar</th>
                            <th class="px-4 py-3 text-right" style="width: 130px;">Birim Fiyat</th>
                            <th class="px-4 py-3 text-right" style="width: 140px;">Tutar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($masterBom as $bom)
                            <tr class="transition hover:bg-slate-50">
                                <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ $bom['material_code'] }}</td>
                                <td class="px-4 py-3">
                                    <p class="truncate font-medium text-slate-900" title="{{ $bom['material_name'] }}">{{ $bom['material_name'] }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-medium text-slate-600">
                                        {{ \App\Models\Material::CATEGORIES[$bom['category']] ?? $bom['category'] }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <span class="font-semibold text-slate-900">{{ number_format($bom['total_qty'], 4, ',', '.') }}</span>
                                    <span class="ml-1 text-xs text-slate-500">{{ \App\Models\Material::UNITS[$bom['unit']] ?? $bom['unit'] }}</span>
                                </td>
                                <td class="px-4 py-3 text-right text-slate-600">{{ number_format($bom['unit_price'], 2, ',', '.') }} ₺</td>
                                <td class="px-4 py-3 text-right font-semibold text-slate-900">{{ number_format($bom['total_cost'], 2, ',', '.') }} ₺</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="divide-y divide-slate-100 md:hidden">
                @foreach($masterBom as $bom)
                    <article class="p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-900">{{ $bom['material_name'] }}</p>
                                <p class="mt-1 font-mono text-xs text-slate-500">{{ $bom['material_code'] }}</p>
                            </div>
                            <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs text-slate-600">
                                {{ \App\Models\Material::CATEGORIES[$bom['category']] ?? $bom['category'] }}
                            </span>
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                            <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-3 py-2">
                                <span class="block text-slate-500">Miktar</span>
                                <span class="mt-1 block font-semibold text-slate-900">{{ number_format($bom['total_qty'], 4, ',', '.') }} {{ \App\Models\Material::UNITS[$bom['unit']] ?? $bom['unit'] }}</span>
                            </div>
                            <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-3 py-2">
                                <span class="block text-slate-500">Toplam</span>
                                <span class="mt-1 block font-semibold text-slate-900">{{ number_format($bom['total_cost'], 2, ',', '.') }} ₺</span>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @else
        <section class="rounded-[10px] border border-dashed border-slate-300 bg-white p-8 text-center shadow-sm lg:p-12">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-[8px] border border-slate-200 bg-slate-50 text-slate-400">
                <x-lucide.icon name="package" class="h-7 w-7" />
            </div>
            <h3 class="mt-4 text-lg font-semibold text-slate-900">Planlama bekleniyor</h3>
            <p class="mx-auto mt-2 max-w-sm text-sm text-slate-500">Reçete ve adet bilgileri girildiğinde malzeme ledgerı burada oluşur.</p>
        </section>
    @endif
</div>
