@php
    $summary = (array) ($marketInsightDashboard['summary'] ?? []);
    $rows = collect($marketInsightDashboard['rows'] ?? []);
@endphp

<div class="space-y-4 lg:space-y-6" data-testid="booster-keyword-tracking">
    <section class="overflow-hidden rounded-[10px] border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 p-4 lg:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between lg:gap-4">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Canlı listeleme kontrolü</p>
                    <h2 class="mt-1 text-xl font-semibold text-slate-900 lg:text-2xl">Ürün hangi kelimede kaçıncı sırada?</h2>
                    <p class="mt-1 max-w-3xl text-sm text-slate-500">Ürün linkini ve aranacak kelimeleri girin. Her kelime için gerçek sıra, ilk kaçta olduğu ve taranan sonuçlarda bulunup bulunmadığı ayrı ayrı gösterilir.</p>
                </div>
                <div class="shrink-0 rounded-[6px] border border-sky-200 bg-sky-50 px-3 py-2 text-xs leading-5 text-sky-800">
                    En fazla 6 kelimeyi tek seferde kontrol edebilirsiniz.
                </div>
            </div>

            <div class="mt-5 space-y-4">
                <div>
                    <label class="block">
                        <span class="text-xs font-medium text-slate-600">Kontrol edilecek Trendyol ürün linki veya ID'si</span>
                        <input type="text" wire:model.defer="keywordTrackingInput" placeholder="https://www.trendyol.com/...-p-123456 veya ürün ID'si"
                               class="mt-1.5 min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none focus:border-slate-400 sm:py-2 sm:text-sm">
                    </label>
                    @error('keywordTrackingInput')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div class="grid grid-cols-1 gap-3 lg:grid-cols-[minmax(280px,1fr)_140px_auto] lg:items-end lg:gap-4">
                    <div>
                        <label class="block">
                            <span class="text-xs font-medium text-slate-600">Sırası kontrol edilecek kelimeler <span class="font-normal text-slate-400">(en fazla 6)</span></span>
                            <textarea rows="2" wire:model.defer="keywordTrackingKeyword" placeholder="Örnek: puf, beyaz puf, welsoft puf"
                                      class="mt-1.5 min-h-[44px] w-full resize-y rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none focus:border-slate-400 sm:py-2 sm:text-sm"></textarea>
                        </label>
                        @error('keywordTrackingKeyword')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block">
                            <span class="text-xs font-medium text-slate-600">Hedef sıra</span>
                            <input type="number" min="1" max="500" wire:model.defer="keywordTrackingTarget"
                                   class="mt-1.5 min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none focus:border-slate-400 sm:py-2 sm:text-sm">
                        </label>
                    </div>
                    <button type="button" wire:click="trackKeywordFromTool" wire:loading.attr="disabled" wire:target="trackKeywordFromTool"
                            class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] bg-slate-900 px-4 py-3 text-base font-medium text-white transition hover:bg-slate-800 disabled:cursor-wait disabled:opacity-60 sm:w-auto sm:py-2 sm:text-sm">
                        <span wire:loading.remove wire:target="trackKeywordFromTool" class="inline-flex items-center gap-2"><x-lucide.icon name="search" class="h-4 w-4" /> Sıralamayı Kontrol Et</span>
                        <span wire:loading wire:target="trackKeywordFromTool">Sonuçlar taranıyor...</span>
                    </button>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-3 p-4 sm:grid-cols-2 xl:grid-cols-4 lg:gap-4 lg:p-6">
            <div class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/60 p-4">
                <p class="text-xs text-slate-500">Kontrol edilen kelime</p>
                <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $summary['total'] ?? 0 }}</p>
                <p class="mt-1 text-xs text-slate-500">Bu ürün için kayıtlı sorgu</p>
            </div>
            <div class="min-w-0 rounded-[8px] border border-emerald-200 bg-emerald-50/70 p-4">
                <p class="text-xs text-emerald-700">Listede bulunan</p>
                <p class="mt-1 text-2xl font-semibold text-emerald-800">{{ $summary['found'] ?? $summary['active'] ?? 0 }}</p>
                <p class="mt-1 text-xs text-emerald-700">En iyi sıra: {{ !empty($summary['best_rank']) ? '#'.$summary['best_rank'] : '—' }}</p>
            </div>
            <div class="min-w-0 rounded-[8px] border border-sky-200 bg-sky-50/70 p-4">
                <p class="text-xs text-sky-700">İlk 10 içinde</p>
                <p class="mt-1 text-2xl font-semibold text-sky-800">{{ $summary['top_ten'] ?? 0 }}</p>
                <p class="mt-1 text-xs text-sky-700">Yüksek görünürlük</p>
            </div>
            <div class="min-w-0 rounded-[8px] border border-rose-200 bg-rose-50/70 p-4">
                <p class="text-xs text-rose-700">Taranıp bulunamayan</p>
                <p class="mt-1 text-2xl font-semibold text-rose-800">{{ $summary['missing'] ?? 0 }}</p>
                <p class="mt-1 text-xs text-rose-700">Kontrol kapsamının dışında</p>
            </div>
        </div>
    </section>

    @if($this->keywordTrackingCurrentProduct)
        <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between lg:gap-4">
                <div class="flex min-w-0 items-center gap-3 sm:gap-4">
                    @if($this->keywordTrackingCurrentProduct->image_url)
                        <img src="{{ $this->keywordTrackingCurrentProduct->image_url }}" alt="" class="h-16 w-16 shrink-0 rounded-[6px] border border-slate-200 object-cover">
                    @else
                        <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-[6px] border border-slate-200 bg-slate-50 text-slate-400"><x-lucide.icon name="package" class="h-6 w-6" /></div>
                    @endif
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">Kontrol edilen ürün</p>
                        <h3 class="mt-1 truncate font-semibold text-slate-900">{{ $this->keywordTrackingCurrentProduct->title ?: 'İsimsiz Ürün' }}</h3>
                        <p class="mt-1 text-sm text-slate-500">Ürün ID: <span class="font-medium text-slate-700">{{ $this->keywordTrackingCurrentProduct->trendyol_product_id ?: '-' }}</span> <span class="mx-1 text-slate-300">·</span> Satıcı ID: <span class="font-medium text-slate-700">{{ $this->keywordTrackingCurrentProduct->seller_id ?: '-' }}</span></p>
                    </div>
                </div>
                <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-center">
                    <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 px-3 py-2 text-sm text-slate-600">
                        <span class="font-semibold text-slate-900">{{ $summary['found'] ?? 0 }}/{{ $summary['total'] ?? 0 }}</span> kelimede listede
                    </div>
                    @if($this->keywordTrackingCurrentProduct->source_url)
                        <a href="{{ $this->keywordTrackingCurrentProduct->source_url }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 sm:w-auto sm:py-2">Ürün sayfasını aç <x-lucide.icon name="external-link" class="h-4 w-4" /></a>
                    @endif
                </div>
            </div>
        </section>
    @endif

    <section class="overflow-hidden rounded-[10px] border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-end sm:justify-between lg:px-6">
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Görünürlük ledger</p>
                <h2 class="mt-1 text-lg font-semibold text-slate-900">Kelime bazında listeleme durumu</h2>
                <p class="mt-1 text-sm text-slate-500">Sıra ile toplam sonuç sayısı farklıdır: “kontrol edilen” alanı ürünün kaç listeleme içinde arandığını gösterir.</p>
                @if(trim($insightSearch) !== '')
                    <p class="mt-2 text-xs font-medium text-sky-700">“{{ $insightSearch }}” filtresi aktif · {{ $rows->count() }} kayıt gösteriliyor</p>
                @endif
            </div>
            <label class="sm:w-80">
                <span class="sr-only">Takip kaydında ara</span>
                <div class="relative">
                    <x-lucide.icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input type="search" wire:model.live.debounce.300ms="insightSearch" placeholder="Kelime veya ürün ara"
                           class="min-h-[44px] w-full rounded-[6px] border border-slate-200 bg-white py-3 pl-10 pr-3 text-base text-slate-900 outline-none focus:border-slate-400 sm:py-2 sm:text-sm">
                </div>
            </label>
        </div>

        <div class="space-y-3 p-4 md:hidden">
            @forelse($rows as $row)
                <article class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="break-words text-base font-semibold text-slate-900">{{ $row['name'] }}</p>
                            <p class="mt-1 truncate text-xs text-slate-500">{{ $row['product'] }}</p>
                        </div>
                        <span class="shrink-0 rounded-[6px] border px-2 py-1 text-xs font-medium {{ $this->keywordStatusClasses($row['status']) }}">{{ $this->keywordStatusLabel($row['status']) }}</span>
                    </div>

                    <div class="mt-4 rounded-[8px] border {{ $row['observed_rank'] ? 'border-emerald-200 bg-emerald-50/70' : 'border-rose-200 bg-rose-50/70' }} p-3">
                        @if($row['observed_rank'])
                            <div class="flex items-end justify-between gap-3">
                                <div><p class="text-xs text-emerald-700">Gerçek arama sırası</p><p class="mt-1 text-3xl font-bold text-emerald-900">#{{ $row['observed_rank'] }}</p></div>
                                <span class="rounded-[6px] border border-emerald-200 bg-white px-2 py-1 text-xs font-semibold text-emerald-700">{{ $row['rank_band_label'] }}</span>
                            </div>
                            @if(!empty($row['history']) && count($row['history']) > 1)
                                <div class="mt-3 w-full h-10">
                                    @php
                                        $rankData = collect($row['history'])->take(-14)->pluck('rank')->filter()->toArray();
                                    @endphp
                                    <x-zolm.sparkline :data="$rankData" width="150" height="40" color="text-sky-500" :fill="true" :inverse="true" />
                                </div>
                            @endif
                        @else
                            <p class="text-xs text-rose-700">Listeleme sonucu</p>
                            <p class="mt-1 text-lg font-bold text-rose-900">Bulunamadı</p>
                            <p class="mt-1 text-xs text-rose-700">{{ $row['rank_band_label'] }}</p>
                        @endif
                    </div>

                    <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                        <div class="rounded-[6px] border border-slate-200 bg-white p-2.5"><p class="text-slate-500">Kontrol edilen</p><p class="mt-1 font-semibold text-slate-900">{{ number_format($row['checked_count'], 0, ',', '.') }} ürün</p></div>
                        <div class="rounded-[6px] border border-slate-200 bg-white p-2.5"><p class="text-slate-500">Toplam sonuç</p><p class="mt-1 font-semibold text-slate-900">{{ number_format($row['result_count'], 0, ',', '.') }}</p></div>
                        <div class="rounded-[6px] border border-slate-200 bg-white p-2.5"><p class="text-slate-500">Hedef sıra</p><p class="mt-1 font-semibold text-slate-900">#{{ $row['target_rank'] }}</p></div>
                        <div class="rounded-[6px] border border-slate-200 bg-white p-2.5"><p class="text-slate-500">Son kontrol</p><p class="mt-1 font-semibold text-slate-900">{{ $row['last_checked_at']?->diffForHumans() ?: 'Bekliyor' }}</p></div>
                    </div>

                    <p class="mt-3 text-xs leading-5 text-slate-600">
                        @if($row['observed_rank'] && $row['target_gap'] <= 0)
                            Ürün belirlediğiniz #{{ $row['target_rank'] }} hedefinin içinde.
                        @elseif($row['observed_rank'])
                            Ürün hedef sıranın {{ $row['target_gap'] }} basamak gerisinde.
                        @else
                            {{ $row['note'] ?: 'Ürün kontrol edilen listeleme aralığında yer almıyor.' }}
                        @endif
                    </p>

                    <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                        <a href="{{ $row['search_url'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-[44px] flex-1 items-center justify-center gap-2 rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700"><x-lucide.icon name="external-link" class="h-4 w-4" /> Trendyol'da ara</a>
                        <button type="button" wire:click="refreshKeyword({{ $row['id'] }})" wire:loading.attr="disabled" wire:target="refreshKeyword({{ $row['id'] }})" class="inline-flex min-h-[44px] flex-1 items-center justify-center gap-2 rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 disabled:opacity-60"><x-lucide.icon name="refresh-cw" class="h-4 w-4" /> Yenile</button>
                        <button type="button" wire:click="removeKeyword({{ $row['id'] }})" class="inline-flex h-11 w-full items-center justify-center rounded-[6px] border border-rose-200 bg-white text-rose-700 sm:w-11" title="Takibi sil"><x-lucide.icon name="trash-2" class="h-4 w-4" /></button>
                    </div>
                </article>
            @empty
                <div class="rounded-[8px] border border-dashed border-slate-300 bg-slate-50/60 p-5 text-sm text-slate-500">Henüz sıralama kontrolü yok. Ürün linkini ve kelimeleri girerek ilk sonucu oluşturun.</div>
            @endforelse
        </div>

        <div class="hidden overflow-x-auto md:block">
            <div class="min-w-[1080px]">
                <div class="grid grid-cols-[minmax(170px,1fr)_minmax(230px,1.25fr)_minmax(210px,1.1fr)_150px_130px_150px] gap-3 border-b border-slate-200 bg-slate-50/70 px-6 py-3 text-xs font-semibold uppercase text-slate-500">
                    @foreach(['Kelime', 'Ürün', 'Listeleme sonucu', 'Tarama kapsamı', 'Hedef / Kontrol', 'İşlem'] as $label)<span>{{ $label }}</span>@endforeach
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse($rows as $row)
                        <div class="grid grid-cols-[minmax(170px,1fr)_minmax(230px,1.25fr)_minmax(210px,1.1fr)_150px_130px_150px] items-center gap-3 px-6 py-4 text-sm">
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-slate-900">{{ $row['name'] }}</p>
                                <a href="{{ $row['search_url'] }}" target="_blank" rel="noopener noreferrer" class="mt-1 inline-flex items-center gap-1 text-xs font-medium text-sky-700 hover:text-sky-800">Canlı aramayı aç <x-lucide.icon name="external-link" class="h-3 w-3" /></a>
                            </div>
                            <p class="truncate text-slate-600" title="{{ $row['product'] }}">{{ $row['product'] }}</p>
                            <div class="min-w-0">
                                @if($row['observed_rank'])
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono text-xl font-bold text-emerald-800">#{{ $row['observed_rank'] }}</span>
                                        <span class="rounded-[6px] border border-emerald-200 bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700">{{ $row['rank_band_label'] }}</span>
                                    </div>
                                    <p class="mt-1 truncate text-xs text-slate-500">{{ $row['target_gap'] <= 0 ? 'Hedef sıranın içinde' : 'Hedefin '.$row['target_gap'].' basamak gerisinde' }}</p>
                                    @if(!empty($row['history']) && count($row['history']) > 1)
                                        <div class="mt-3 w-full max-w-[200px] h-10">
                                            @php
                                                $rankData = collect($row['history'])->take(-14)->pluck('rank')->filter()->toArray();
                                            @endphp
                                            <x-zolm.sparkline :data="$rankData" width="200" height="40" color="text-sky-500" :fill="true" :inverse="true" />
                                        </div>
                                    @endif
                                @else
                                    <div class="flex items-center gap-2"><span class="font-semibold text-rose-800">Bulunamadı</span><span class="rounded-[6px] border border-rose-200 bg-rose-50 px-2 py-1 text-xs font-medium text-rose-700">{{ $row['rank_band_label'] }}</span></div>
                                    <p class="mt-1 truncate text-xs text-slate-500" title="{{ $row['note'] }}">{{ $row['note'] ?: 'Kontrol edilen aralıkta yok.' }}</p>
                                @endif
                            </div>
                            <div>
                                <p class="font-semibold text-slate-900">{{ number_format($row['checked_count'], 0, ',', '.') }} ürün kontrol edildi</p>
                                <p class="mt-1 text-xs text-slate-500">{{ number_format($row['result_count'], 0, ',', '.') }} toplam sonuç</p>
                            </div>
                            <div>
                                <p class="font-mono font-semibold text-slate-900">Hedef #{{ $row['target_rank'] }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $row['last_checked_at']?->diffForHumans() ?: 'Kontrol bekliyor' }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="rounded-[6px] border px-2 py-1 text-xs font-medium {{ $this->keywordStatusClasses($row['status']) }}">{{ $this->keywordStatusLabel($row['status']) }}</span>
                                <button type="button" wire:click="refreshKeyword({{ $row['id'] }})" wire:loading.attr="disabled" wire:target="refreshKeyword({{ $row['id'] }})" class="inline-flex h-10 w-10 items-center justify-center rounded-[6px] border border-slate-200 text-slate-600 disabled:opacity-60" title="Sırayı yenile"><x-lucide.icon name="refresh-cw" class="h-4 w-4" /></button>
                                <button type="button" wire:click="removeKeyword({{ $row['id'] }})" class="inline-flex h-10 w-10 items-center justify-center rounded-[6px] border border-rose-200 text-rose-700" title="Sil"><x-lucide.icon name="trash-2" class="h-4 w-4" /></button>
                            </div>
                        </div>
                    @empty
                        <p class="px-6 py-10 text-center text-sm text-slate-500">Filtrelerle eşleşen sıralama kaydı yok.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </section>
</div>

@script
<script>
    $wire.on('booster:keyword-tracking-bridge', (event) => {
        const payload = event[0] || event;
        const requestId = 'kw_' + Date.now();
        const fallbackToServer = () => {
            $wire.keywordTrackingServerFallback(
                payload.url || '',
                Array.isArray(payload.keywords) ? payload.keywords : [],
                Number(payload.target_rank || 10)
            );
        };

        if (window.__zolmBoosterCompanionReady !== true) {
            fallbackToServer();
            return;
        }

        const timer = window.setTimeout(() => {
            window.removeEventListener('message', messageHandler);
            fallbackToServer();
        }, 12000);

        const messageHandler = (e) => {
            if (e.origin !== window.location.origin || e.data?.source !== 'zolm-booster-extension') {
                return;
            }

            if (e.data?.type === 'BRIDGE_ERROR') {
                window.clearTimeout(timer);
                window.removeEventListener('message', messageHandler);
                fallbackToServer();
                return;
            }

            if (e.data?.type === 'KEYWORD_TRACKING_RESULT' && e.data?.request_id === requestId) {
                window.clearTimeout(timer);
                window.removeEventListener('message', messageHandler);
                const response = e.data.response || { ok: false, message: 'Geçersiz yanıt.' };

                if (!response.ok) {
                    fallbackToServer();
                    return;
                }

                $wire.keywordTrackingBridgeCompleted(response);
            }
        };

        window.addEventListener('message', messageHandler);

        window.postMessage({
            source: 'zolm-booster-page',
            type: 'KEYWORD_TRACKING_QUERY',
            request_id: requestId,
            source_url: payload.url,
            keywords: payload.keywords,
        }, window.location.origin);
    });
</script>
@endscript
