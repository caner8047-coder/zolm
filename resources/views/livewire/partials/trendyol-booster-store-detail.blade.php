@php
    $detail = $this->storeDetail;
    $watch = $detail['watch'];
    $items = $detail['items'];
    $brandDist = $detail['brand_distribution'];
    $categoryDist = $detail['category_distribution'];
    $snapshots = $detail['snapshots'] ?? collect();
    $latestSnapshot = $detail['latest_snapshot'] ?? null;
    $latestOkSnapshot = $detail['latest_ok_snapshot'] ?? null;
    $changeSummary = (array) ($latestSnapshot?->change_summary ?? []);
    $portfolio = (array) ($detail['portfolio'] ?? []);
    $portfolioCategories = array_slice((array) ($portfolio['categories'] ?? []), 0, 6);
    $showingPreservedCatalog = $latestSnapshot?->status === 'failed' && $latestOkSnapshot;
@endphp

<div class="space-y-4">
    <!-- Header -->
    <div class="flex items-center gap-3">
        <button type="button" wire:click="closeStoreDetail" class="inline-flex h-9 w-9 items-center justify-center rounded-[6px] border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50 hover:text-slate-900">
            <x-lucide.icon name="arrow-left" class="h-4 w-4" />
        </button>
        <div>
            <h2 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                {{ $watch->store_name }} Detay Analizi
                @if($watch->store_rating)
                    <span class="rounded-[4px] bg-emerald-100 px-1.5 py-0.5 text-xs font-semibold text-emerald-800">{{ number_format($watch->store_rating, 1, ',', '.') }}</span>
                @endif
            </h2>
            <p class="text-sm text-slate-500">
                @if($showingPreservedCatalog)
                    Son başarılı tarama: {{ $latestOkSnapshot->checked_at?->format('d.m.Y H:i') }} · {{ $watch->best_seller_count }} korunmuş ürün
                @else
                    Son tarama: {{ $watch->last_checked_at?->format('d.m.Y H:i') }} · {{ $watch->best_seller_count }} aktif ürün
                @endif
            </p>
        </div>
    </div>

    @if($showingPreservedCatalog)
        <div class="rounded-[8px] border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Son deneme mağaza kataloğunu güvenli okuyamadı. Aşağıdaki liste, #{{ $latestOkSnapshot->scan_number }} numaralı son başarılı taramadan korunan veridir; yeni tarama başarılı olunca otomatik güncellenir.
        </div>
    @endif

    <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-200 p-4 lg:flex-row lg:items-start lg:justify-between lg:p-6">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase text-slate-500">Mağaza ve kategori 360</p>
                <h3 class="mt-1 text-lg font-semibold text-slate-900">Katalog yapısı, momentum ve kategori gücü</h3>
                <p class="mt-1 text-sm text-slate-500">Tüm katalog üzerinden hesaplanır; aşağıdaki ürün filtresinden etkilenmez.</p>
            </div>
            <div class="shrink-0 rounded-[8px] border border-slate-200 bg-slate-50/70 px-4 py-3 text-left lg:text-right">
                <p class="text-xs text-slate-500">Katalog momentum skoru</p>
                <p class="mt-1 text-xl font-semibold text-slate-900">{{ (int) ($portfolio['score'] ?? 0) }}/100</p>
                <p class="text-xs font-medium text-slate-600">{{ $portfolio['score_label'] ?? 'Sınırlı sinyal' }}</p>
            </div>
        </div>

        <div class="space-y-4 p-4 lg:p-6">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4 lg:gap-4">
                @foreach([
                    ['Aktif / yeni', number_format((int) ($portfolio['active_product_count'] ?? 0), 0, '', '.').' / +'.number_format((int) ($portfolio['new_product_count'] ?? 0), 0, '', '.'), 'Gözlenen katalog'],
                    ['Medyan fiyat', ($portfolio['median_price'] ?? null) !== null ? $this->formatMoney($portfolio['median_price']) : 'Veri yok', (($portfolio['minimum_price'] ?? null) !== null && ($portfolio['maximum_price'] ?? null) !== null) ? $this->formatMoney($portfolio['minimum_price']).' – '.$this->formatMoney($portfolio['maximum_price']) : 'Fiyat aralığı yok'],
                    ['Kampanya payı', '%'.number_format((float) ($portfolio['campaign_share_percent'] ?? 0), 1, ',', '.'), number_format((int) ($portfolio['campaign_count'] ?? 0), 0, '', '.').' kampanyalı ürün'],
                    ['Tahmini satış sinyali', ($portfolio['estimated_daily_sales'] ?? null) !== null ? '~'.number_format((float) $portfolio['estimated_daily_sales'], 1, ',', '.').' / gün' : 'Veri birikiyor', 'Kesin sipariş verisi değildir'],
                ] as [$label, $value, $note])
                    <div class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                        <p class="text-xs text-slate-500">{{ $label }}</p>
                        <p class="mt-1 truncate text-base font-semibold text-slate-900">{{ $value }}</p>
                        <p class="mt-1 truncate text-xs text-slate-500">{{ $note }}</p>
                    </div>
                @endforeach
            </div>

            <div class="grid grid-cols-1 gap-4 xl:grid-cols-12">
                <div class="min-w-0 rounded-[8px] border border-slate-200 bg-white p-4 xl:col-span-4">
                    <p class="text-xs font-semibold uppercase text-slate-500">Portföy odağı</p>
                    <p class="mt-2 text-base font-semibold text-slate-900">{{ $portfolio['dominant_category'] ?? 'Kategori verisi yok' }}</p>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ $portfolio['concentration_label'] ?? 'Dağılım hesaplanamadı' }}
                        @if(($portfolio['dominant_category_share_percent'] ?? null) !== null)
                            · %{{ number_format((float) $portfolio['dominant_category_share_percent'], 1, ',', '.') }} pay
                        @endif
                    </p>
                    <div class="mt-4 grid grid-cols-2 gap-2 text-xs">
                        <div class="rounded-[6px] border border-slate-200 bg-slate-50/60 p-3"><span class="text-slate-500">1. satıcı payı</span><strong class="mt-1 block text-slate-900">%{{ number_format((float) ($portfolio['top_seller_share_percent'] ?? 0), 1, ',', '.') }}</strong></div>
                        <div class="rounded-[6px] border border-slate-200 bg-slate-50/60 p-3"><span class="text-slate-500">Kaldırılan</span><strong class="mt-1 block text-slate-900">{{ number_format((int) ($portfolio['removed_product_count'] ?? 0), 0, '', '.') }}</strong></div>
                    </div>
                </div>

                <div class="min-w-0 overflow-hidden rounded-[8px] border border-slate-200 bg-white xl:col-span-8">
                    <div class="border-b border-slate-200 px-4 py-3"><h4 class="text-sm font-semibold text-slate-900">Kategori performans özeti</h4></div>
                    <div class="hidden overflow-x-auto md:block">
                        <table class="w-full table-fixed text-left text-xs">
                            <thead class="bg-slate-50/70 text-slate-500"><tr><th class="w-[32%] px-3 py-2 font-medium">Kategori</th><th class="px-3 py-2 font-medium">Ürün / pay</th><th class="px-3 py-2 font-medium">Ort. fiyat</th><th class="px-3 py-2 font-medium">Yorum</th><th class="px-3 py-2 font-medium">Satış sinyali</th></tr></thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($portfolioCategories as $category)
                                    <tr><td class="truncate px-3 py-2 font-medium text-slate-900" title="{{ $category['category'] }}">{{ $category['category'] }}</td><td class="px-3 py-2 text-slate-600">{{ $category['product_count'] }} · %{{ number_format((float) $category['share_percent'], 1, ',', '.') }}</td><td class="px-3 py-2 text-slate-600">{{ $category['average_price'] !== null ? $this->formatMoney($category['average_price']) : '-' }}</td><td class="px-3 py-2 text-slate-600">{{ number_format((int) $category['review_count'], 0, '', '.') }}</td><td class="px-3 py-2 font-medium text-slate-900">{{ (float) $category['estimated_daily_sales'] > 0 ? '~'.number_format((float) $category['estimated_daily_sales'], 1, ',', '.').' / gün' : '-' }}</td></tr>
                                @empty
                                    <tr><td colspan="5" class="px-3 py-4 text-slate-500">Kategori sinyali bulunamadı.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="grid grid-cols-1 gap-2 p-3 md:hidden">
                        @forelse($portfolioCategories as $category)
                            <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3"><div class="flex items-start justify-between gap-3"><p class="min-w-0 truncate text-sm font-medium text-slate-900">{{ $category['category'] }}</p><span class="shrink-0 text-xs text-slate-500">%{{ number_format((float) $category['share_percent'], 1, ',', '.') }}</span></div><p class="mt-1 text-xs text-slate-500">{{ $category['product_count'] }} ürün · {{ $category['average_price'] !== null ? $this->formatMoney($category['average_price']) : 'Fiyat yok' }} · {{ number_format((int) $category['review_count'], 0, '', '.') }} yorum</p></div>
                        @empty
                            <p class="text-sm text-slate-500">Kategori sinyali bulunamadı.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <p class="rounded-[8px] border border-slate-200 bg-slate-50/60 px-3 py-2 text-xs text-slate-500">{{ $portfolio['evidence_note'] ?? 'Mağaza verisi gözlem ve tahmin sınırlarıyla birlikte sunulur.' }}</p>
        </div>
    </section>

    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase text-slate-500">Tarama geçmişi</p>
                <h3 class="mt-1 text-base font-semibold text-slate-900">
                    {{ $latestSnapshot ? '#'.$latestSnapshot->scan_number.' tarama' : 'İlk kayıt bekleniyor' }}
                    @if($latestSnapshot?->status === 'failed')
                        <span class="ml-2 rounded-[4px] bg-rose-100 px-1.5 py-0.5 text-xs font-semibold text-rose-700">Son tarama okunamadı</span>
                    @endif
                </h3>
                <p class="mt-1 text-sm text-slate-500">
                    @if($latestSnapshot)
                        {{ $latestSnapshot->checked_at?->format('d.m.Y H:i') }} tarihinde kaydedildi.
                    @else
                        Bu mağaza yeniden tarandıkça tarihçeli kıyas oluşacak.
                    @endif
                </p>
            </div>
            <div class="grid w-full grid-cols-2 gap-2 sm:grid-cols-4 xl:max-w-3xl">
                @foreach([
                    ['Ürün değişimi', $changeSummary['active_product_delta'] ?? null, 'adet'],
                    ['Fiyat hareketi', $changeSummary['price_change_delta'] ?? null, 'ürün'],
                    ['Yorum değişimi', $changeSummary['total_review_delta'] ?? null, 'yorum'],
                    ['Favori değişimi', $changeSummary['total_favorite_delta'] ?? null, 'favori'],
                ] as [$label, $value, $suffix])
                    @php
                        $tone = $value === null ? 'text-slate-900' : ((float) $value > 0 ? 'text-emerald-700' : ((float) $value < 0 ? 'text-rose-700' : 'text-slate-900'));
                    @endphp
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                        <p class="text-xs text-slate-500">{{ $label }}</p>
                        <p class="mt-1 text-sm font-semibold {{ $tone }}">
                            @if($value === null)
                                Baz bekleniyor
                            @else
                                {{ (float) $value > 0 ? '+' : '' }}{{ is_float($value) ? number_format((float) $value, 2, ',', '.') : number_format((int) $value, 0, '', '.') }} {{ $suffix }}
                            @endif
                        </p>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-2 md:grid-cols-2 xl:grid-cols-4">
            @forelse($snapshots->take(4) as $snapshot)
                <div class="rounded-[8px] border border-slate-200 bg-white p-3">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="text-xs font-semibold text-slate-900">#{{ $snapshot->scan_number }} · {{ $snapshot->checked_at?->format('d.m H:i') }}</p>
                            <p class="mt-1 text-[11px] text-slate-500">
                                {{ $snapshot->active_product_count }} ürün · {{ $snapshot->price_change_count }} fiyat · {{ $snapshot->campaign_count }} kampanya
                                @if($snapshot->status !== 'ok')
                                    · korunan liste
                                @endif
                            </p>
                        </div>
                        <span class="rounded-[4px] px-1.5 py-0.5 text-[10px] font-semibold {{ $snapshot->status === 'ok' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                            {{ $snapshot->status === 'ok' ? 'OK' : 'Hata' }}
                        </span>
                    </div>
                    <div class="mt-2 flex items-center justify-between text-[11px] text-slate-500">
                        <span>Ort. fiyat</span>
                        <span class="font-semibold text-slate-900">{{ $snapshot->avg_price !== null ? $this->formatMoney($snapshot->avg_price) : '-' }}</span>
                    </div>
                </div>
            @empty
                <div class="rounded-[8px] border border-dashed border-slate-300 bg-slate-50/70 p-3 text-sm text-slate-500 md:col-span-2 xl:col-span-4">Henüz tarihçeli mağaza snapshot kaydı yok.</div>
            @endforelse
        </div>
    </section>

    <!-- Analytics Charts -->
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="rounded-[8px] border border-slate-200 bg-white p-4">
            <h3 class="text-sm font-semibold text-slate-900 mb-3">Kategori Dağılımı</h3>
            <div class="space-y-3">
                @forelse(array_slice($categoryDist, 0, 5) as $cat => $count)
                    <div>
                        <div class="flex justify-between text-xs mb-1">
                            <span class="truncate text-slate-700 font-medium max-w-[70%]">{{ $cat }}</span>
                            <span class="text-slate-500">{{ $count }} ürün</span>
                        </div>
                        <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full bg-indigo-500 rounded-full" style="width: {{ $watch->best_seller_count > 0 ? min(100, round(($count / $watch->best_seller_count) * 100)) : 0 }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-slate-500">Kategori bilgisi bulunamadı.</p>
                @endforelse
            </div>
        </div>
        <div class="rounded-[8px] border border-slate-200 bg-white p-4">
            <h3 class="text-sm font-semibold text-slate-900 mb-3">Marka Dağılımı</h3>
            <div class="space-y-3">
                @forelse(array_slice($brandDist, 0, 5) as $brand => $count)
                    <div>
                        <div class="flex justify-between text-xs mb-1">
                            <span class="truncate text-slate-700 font-medium max-w-[70%]">{{ $brand }}</span>
                            <span class="text-slate-500">{{ $count }} ürün</span>
                        </div>
                        <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full bg-emerald-500 rounded-full" style="width: {{ $watch->best_seller_count > 0 ? min(100, round(($count / $watch->best_seller_count) * 100)) : 0 }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-slate-500">Marka bilgisi bulunamadı.</p>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Filters & List -->
    <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <h3 class="font-semibold text-slate-900">
                        {{ $showingPreservedCatalog ? 'Korunan Ürün Listesi' : 'Ürün Listesi' }} ({{ count($items) }})
                    </h3>
                    @if($showingPreservedCatalog)
                        <p class="mt-1 text-xs text-slate-500">Bu liste canlı okunmuş son katalog değil; son başarılı mağaza snapshot kaydıdır.</p>
                    @endif
                </div>
                <div class="flex gap-2">
                    <select wire:model.live="storeDetailFilter" class="rounded-[6px] border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-900 outline-none focus:border-slate-400">
                        <option value="all">Tümü</option>
                        <option value="new">Sadece Yeniler</option>
                        <option value="price_changed">Fiyatı Değişenler</option>
                        <option value="campaign">Kampanyalılar</option>
                        <option value="top_seller">1. Satıcı Olduğu</option>
                        <option value="removed">Yayından Kalkanlar</option>
                    </select>
                    <select wire:model.live="storeDetailSort" class="rounded-[6px] border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-900 outline-none focus:border-slate-400">
                        <option value="rank_asc">Sıra (Önce en yüksek)</option>
                        <option value="sale_price_desc">Fiyat (Pahalıdan)</option>
                        <option value="sale_price_asc">Fiyat (Ucuzdan)</option>
                        <option value="review_count_desc">Yorum (Çoktan)</option>
                        <option value="discount_rate_desc">İndirim Oranı (Yüksekten)</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="p-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            @forelse($items as $item)
                @php
                    $detailAnalysisState = (array) $this->storeDetailAnalysis;
                    $analysisForItem = (int) ($detailAnalysisState['item_id'] ?? 0) === (int) $item->id ? $detailAnalysisState : null;
                    $analysisCurrent = (array) data_get($analysisForItem, 'analysis.current', []);
                    $analysisScores = (array) data_get($analysisForItem, 'analysis.product_scores', []);
                    $quickStock = $analysisCurrent['stock_quantity'] ?? $item->stock_quantity;
                    $quickFavorite = $analysisCurrent['favorite_count'] ?? $item->favorite_count;
                    $storeSalesSignal = (array) ($item->store_sales_signal ?? []);
                    $storeEstimatedDailySales = $storeSalesSignal['estimated_daily_sales'] ?? null;
                @endphp
                <div class="relative flex h-full flex-col overflow-visible rounded-[8px] border {{ $item->is_removed ? 'border-rose-200 bg-rose-50/50 opacity-80' : 'border-slate-200 bg-white' }} p-3 transition hover:shadow-md">
                    <div class="flex gap-3 h-24 mb-3">
                        @if($item->image_url)
                            <div class="h-24 w-16 shrink-0 rounded-[4px] border border-slate-100 overflow-hidden bg-slate-50 relative">
                                <img src="{{ $item->image_url }}" alt="" loading="lazy" class="h-full w-full object-cover">
                                @if($item->is_removed)
                                    <div class="absolute inset-0 bg-rose-500/20 flex items-center justify-center backdrop-blur-[1px]">
                                        <x-lucide.icon name="trash-2" class="h-6 w-6 text-rose-600 drop-shadow" />
                                    </div>
                                @endif
                            </div>
                        @else
                            <div class="h-24 w-16 shrink-0 rounded-[4px] border border-slate-100 bg-slate-50 flex items-center justify-center">
                                <x-lucide.icon name="image" class="h-6 w-6 text-slate-300" />
                            </div>
                        @endif
                        <div class="min-w-0 flex-1 flex flex-col justify-between">
                            @if($item->source_url)
                                <a href="{{ $item->source_url }}" target="_blank" rel="noopener noreferrer" class="line-clamp-3 text-sm font-medium leading-snug text-slate-900 underline-offset-2 hover:text-slate-700 hover:underline" title="{{ $item->title }}">
                                    {{ $item->title }}
                                </a>
                            @else
                                <p class="line-clamp-3 text-sm font-medium leading-snug text-slate-900" title="{{ $item->title }}">
                                    {{ $item->title }}
                                </p>
                            @endif
                            <div class="mt-1 flex flex-wrap gap-1">
                                @if($item->is_removed)
                                    <span class="rounded-[4px] bg-rose-100 px-1.5 py-0.5 text-[10px] font-bold text-rose-800">KALDIRILDI</span>
                                @elseif($item->is_new)
                                    <span class="rounded-[4px] bg-emerald-100 px-1.5 py-0.5 text-[10px] font-bold text-emerald-800">YENİ</span>
                                @endif
                                @if($item->is_first_seller && !$item->is_removed)
                                    <span class="rounded-[4px] bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-800">1. SATICI</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="mt-auto border-t border-slate-100 pt-3">
                        <div class="flex items-end justify-between">
                            <div>
                                @if($item->original_price && $item->original_price > $item->sale_price)
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-xs text-slate-400 line-through">{{ $this->formatMoney($item->original_price) }}</span>
                                        <span class="rounded-[4px] bg-rose-50 px-1 py-0.5 text-[9px] font-bold text-rose-600">%{{ round($item->discount_rate) }}</span>
                                    </div>
                                @endif
                                <span class="text-lg font-bold {{ $item->price_delta < 0 ? 'text-emerald-600' : ($item->price_delta > 0 ? 'text-rose-600' : 'text-slate-900') }}">
                                    {{ $this->formatMoney($item->sale_price) }}
                                </span>
                            </div>
                            @if($item->rating)
                                <div class="text-right">
                                    <span class="flex items-center justify-end text-xs text-slate-700 font-semibold">
                                        <svg class="h-3.5 w-3.5 text-amber-400 mr-1" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>
                                        {{ number_format($item->rating, 1, ',', '.') }}
                                    </span>
                                    @if($item->review_count) 
                                        <span class="text-[10px] text-slate-500">{{ number_format($item->review_count, 0, '', '.') }} yorum</span>
                                    @endif
                                </div>
                            @endif
                        </div>

                        @if($item->price_delta != 0 || $item->review_delta > 0)
                            <div class="mt-2 flex gap-2">
                                @if($item->price_delta != 0)
                                    <span class="inline-flex items-center gap-1 rounded-[4px] px-1.5 py-0.5 text-[10px] font-medium {{ $item->price_delta < 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">
                                        <x-lucide.icon name="{{ $item->price_delta < 0 ? 'trending-down' : 'trending-up' }}" class="h-3 w-3" />
                                        {{ $item->price_delta > 0 ? '+' : '' }}{{ $this->formatMoney($item->price_delta) }}
                                    </span>
                                @endif
                                @if($item->review_delta > 0)
                                    <span class="inline-flex items-center gap-1 rounded-[4px] bg-sky-50 px-1.5 py-0.5 text-[10px] font-medium text-sky-700">
                                        <x-lucide.icon name="message-circle" class="h-3 w-3" />
                                        +{{ $item->review_delta }} yeni
                                    </span>
                                @endif
                            </div>
                        @endif

                        @if($storeEstimatedDailySales !== null)
                            <div class="mt-2 inline-flex items-center gap-1 rounded-[4px] border border-emerald-200 bg-emerald-50 px-1.5 py-0.5 text-[10px] font-medium text-emerald-700" title="Rakip stok geçmişindeki düşüşten hesaplanır.">
                                <x-lucide.icon name="activity" class="h-3 w-3" />
                                Tahmini {{ number_format((float) $storeEstimatedDailySales, 2, ',', '.') }} adet/gün
                            </div>
                        @endif

                        @if($item->histories && $item->histories->count() > 1)
                            <div class="mt-3 border-t border-slate-100 pt-3">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-[10px] font-medium text-slate-500">Fiyat Trendi (Son 14 tarama)</span>
                                </div>
                                <div class="h-12 w-full px-1">
                                    @php
                                        $priceData = $item->histories->sortBy('created_at')->take(-14)->pluck('sale_price')->toArray();
                                    @endphp
                                    <x-zolm.sparkline :data="$priceData" width="300" height="48" color="text-indigo-500" :fill="true" />
                                </div>
                            </div>
                        @endif

                        @if(!empty($item->campaign_badges))
                            <div class="mt-2 flex flex-wrap gap-1">
                                @foreach(array_slice($item->campaign_badges, 0, 4) as $badge)
                                    <span class="rounded-[4px] border border-sky-100 bg-sky-50/50 px-1.5 py-0.5 text-[9px] font-medium text-sky-700">{{ $badge }}</span>
                                @endforeach
                                @if(count($item->campaign_badges) > 4)
                                    <span class="rounded-[4px] border border-slate-200 bg-slate-50 px-1.5 py-0.5 text-[9px] font-medium text-slate-500">+{{ count($item->campaign_badges) - 4 }}</span>
                                @endif
                            </div>
                        @endif

                        <div class="mt-3 flex items-center justify-between gap-2">
                            <div class="min-w-0">
                                @if($item->category_name)
                                    <span class="inline-flex max-w-full items-center rounded-[4px] border border-slate-200 bg-slate-50 px-1.5 py-0.5 text-[10px] font-medium text-slate-600">
                                        <span class="truncate">{{ $item->category_name }}</span>
                                    </span>
                                @endif
                            </div>
                            <div class="ml-auto flex shrink-0 items-center gap-1">
                                @if($item->source_url)
                                    <a href="{{ $item->source_url }}" target="_blank" rel="noopener noreferrer" title="Ürün sayfasını aç" class="inline-flex h-9 w-9 items-center justify-center rounded-[6px] border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50 hover:text-slate-900">
                                        <x-lucide.icon name="external-link" class="h-3.5 w-3.5" />
                                    </a>
                                @endif
                                <button type="button" wire:click="analyzeStoreWatchItem({{ $item->id }})" wire:loading.attr="disabled" wire:target="analyzeStoreWatchItem({{ $item->id }})" title="Ürün analizi" class="inline-flex h-9 w-9 items-center justify-center rounded-[6px] bg-slate-900 text-white transition hover:bg-slate-800 disabled:cursor-wait disabled:opacity-70">
                                    <span wire:loading.remove wire:target="analyzeStoreWatchItem({{ $item->id }})">
                                        <x-lucide.icon name="line-chart" class="h-3.5 w-3.5" />
                                    </span>
                                    <span wire:loading wire:target="analyzeStoreWatchItem({{ $item->id }})">
                                        <x-lucide.icon name="refresh-cw" class="h-3.5 w-3.5 animate-spin" />
                                    </span>
                                </button>
                            </div>
                        </div>

                        @if($analysisForItem)
                            <div class="absolute bottom-14 right-3 z-30 w-80 max-w-[calc(100vw-2rem)] rounded-[8px] border border-slate-200 bg-white p-3 text-left shadow-xl">
                                <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-2">
                                    <div class="min-w-0">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Hızlı Ürün Analizi</p>
                                        <p class="mt-0.5 truncate text-sm font-semibold text-slate-900">{{ $item->title }}</p>
                                    </div>
                                    <button type="button" wire:click="closeStoreDetailAnalysis" class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-[6px] text-slate-400 hover:bg-slate-50 hover:text-slate-900">
                                        <x-lucide.icon name="x" class="h-3.5 w-3.5" />
                                    </button>
                                </div>

                                @if($analysisForItem['ok'] ?? false)
                                    <div class="mt-3 grid grid-cols-2 gap-2">
                                        <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 p-2">
                                            <p class="text-[10px] text-slate-500">Fiyat</p>
                                            <p class="mt-1 text-sm font-semibold text-slate-900">{{ $this->formatMoney($analysisCurrent['sale_price'] ?? $item->sale_price) }}</p>
                                        </div>
                                        <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 p-2" title="Fiyat, stok, yorum, favori ve diğer canlı alanların ne kadar dolu olduğuna göre oluşur.">
                                            <p class="text-[10px] text-slate-500">Güven</p>
                                            <p class="mt-1 text-sm font-semibold text-slate-900">%{{ $analysisCurrent['confidence_score'] ?? 0 }}</p>
                                            <p class="mt-0.5 text-[9px] leading-tight text-slate-400">Veri kalitesi</p>
                                        </div>
                                        <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 p-2" title="Favori, yorum, değerlendirme, puan ve büyüme sinyallerinden oluşur.">
                                            <p class="text-[10px] text-slate-500">İlgi</p>
                                            <p class="mt-1 text-sm font-semibold text-slate-900">{{ $analysisScores['interest'] ?? 0 }}/100</p>
                                            <p class="mt-0.5 text-[9px] leading-tight text-slate-400">Talep sinyali</p>
                                        </div>
                                        <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 p-2" title="Stok bitişi, düşük puan, negatif yorum, fiyat oynaklığı ve rekabet baskısından etkilenir.">
                                            <p class="text-[10px] text-slate-500">Risk</p>
                                            <p class="mt-1 text-sm font-semibold text-rose-700">{{ $analysisScores['risk'] ?? 0 }}/100</p>
                                            <p class="mt-0.5 text-[9px] leading-tight text-slate-400">Olumsuz sinyal</p>
                                        </div>
                                    </div>
                                    <div class="mt-3 rounded-[6px] border border-slate-200 bg-white p-2 text-[11px] text-slate-600">
                                        <div class="flex justify-between gap-3">
                                            <span>Stok</span>
                                            <span class="font-semibold text-slate-900">{{ $quickStock !== null ? number_format((int) $quickStock, 0, '', '.') : 'Yayınlanmıyor' }}</span>
                                        </div>
                                        @if($quickStock === null)
                                            <p class="mt-1 text-[10px] leading-snug text-slate-400">Mağaza liste kartı stok adedini yayınlamıyor; canlı ürün sayfası okuması gelirse burada görünür.</p>
                                        @endif
                                        <div class="mt-1 flex justify-between gap-3">
                                            <span>Favori</span>
                                            <span class="font-semibold text-slate-900">{{ $quickFavorite !== null ? number_format((int) $quickFavorite, 0, '', '.') : 'Yok' }}</span>
                                        </div>
                                        <div class="mt-1 flex justify-between gap-3">
                                            <span>Yorum</span>
                                            <span class="font-semibold text-slate-900">{{ isset($analysisCurrent['review_count']) ? number_format((int) $analysisCurrent['review_count'], 0, '', '.') : 'Yok' }}</span>
                                        </div>
                                        <div class="mt-1 flex justify-between gap-3">
                                            <span>Tahmini günlük satış</span>
                                            <span class="font-semibold text-slate-900">
                                                @if($storeEstimatedDailySales !== null)
                                                    {{ number_format((float) $storeEstimatedDailySales, 2, ',', '.') }} adet/gün
                                                @elseif(isset($analysisCurrent['estimated_daily_sales']))
                                                    {{ $analysisCurrent['estimated_daily_sales'] }}
                                                @else
                                                    Veri bekliyor
                                                @endif
                                            </span>
                                        </div>
                                        @if(($storeSalesSignal['stock_drop'] ?? null) !== null)
                                            <div class="mt-1 flex justify-between gap-3">
                                                <span>Stok düşüşü</span>
                                                <span class="font-semibold text-slate-900">{{ number_format((int) $storeSalesSignal['stock_drop'], 0, '', '.') }} adet / {{ number_format((float) ($storeSalesSignal['observed_hours'] ?? 0), 1, ',', '.') }} saat</span>
                                            </div>
                                        @endif
                                        @if(($storeSalesSignal['favorite_delta'] ?? null) !== null)
                                            <div class="mt-1 flex justify-between gap-3">
                                                <span>Favori değişimi</span>
                                                <span class="font-semibold text-slate-900">{{ (int) $storeSalesSignal['favorite_delta'] > 0 ? '+' : '' }}{{ number_format((int) $storeSalesSignal['favorite_delta'], 0, '', '.') }}</span>
                                            </div>
                                        @endif
                                        <div class="mt-1 flex justify-between gap-3">
                                            <span>Veri yöntemi</span>
                                            <span class="font-semibold text-slate-900">{{ $storeEstimatedDailySales !== null ? 'Stok düşüşü' : 'Analiz sinyali' }}</span>
                                        </div>
                                    </div>
                                @else
                                    <div class="mt-3 rounded-[6px] border border-rose-200 bg-rose-50 p-3 text-xs text-rose-700">
                                        {{ $analysisForItem['message'] ?? 'Ürün analizi oluşturulamadı.' }}
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="col-span-full rounded-[8px] border border-dashed border-slate-300 bg-slate-50/70 p-8 text-center text-sm text-slate-500">
                    Filtreye uygun ürün bulunamadı.
                </div>
            @endforelse
        </div>
    </section>
</div>
