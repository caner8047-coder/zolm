@php
    $listing = $issue->channelListing;
    $channelProduct = $listing?->channelProduct;
    $matchedProduct = $listing?->product;
    $candidates = $candidateMap[$issue->id] ?? collect();
    $recommended = $candidates->first();
@endphp

<div class="rounded-[8px] border border-slate-200 bg-white p-4 shadow-sm lg:p-5">
    <div class="flex flex-col gap-3 border-b border-slate-200 pb-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Seçili kanal ürünü</p>
            <p class="mt-1 truncate text-sm font-semibold text-slate-900">{{ $channelProduct?->title ?: 'Kanal ürünü bulunamadı' }}</p>
            <p class="mt-1 text-xs text-slate-500">
                {{ $channelProduct?->stock_code ?: '-' }} · {{ $channelProduct?->barcode ?: '-' }} · {{ $issue->store?->store_name ?: '-' }}
            </p>
        </div>

        <div class="flex shrink-0 flex-wrap items-center gap-2">
            @if($issue->match_status === 'pending' && $listing)
                <form method="POST" action="{{ route('mp.matching.create-product') }}">
                    @csrf
                    <input type="hidden" name="issue_id" value="{{ $issue->id }}">
                    <button type="submit" class="inline-flex min-h-[40px] items-center justify-center rounded-lg bg-slate-900 px-3 py-2 text-sm font-medium text-white transition hover:bg-slate-800">Ana ürün olarak ekle</button>
                </form>
            @endif

            @if($issue->match_status === 'pending' && $listing && $this->canAutoRecommend($recommended))
                <button type="button"
                        wire:click="manualMatchRecommended({{ $issue->id }})"
                        wire:loading.attr="disabled"
                        wire:target="manualMatchRecommended({{ $issue->id }})"
                        class="inline-flex min-h-[40px] items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                    Önerileni bağla
                </button>
            @endif

            @if($issue->match_status === 'pending')
                <form method="POST" action="{{ route('mp.matching.defer') }}">
                    @csrf
                    <input type="hidden" name="issue_id" value="{{ $issue->id }}">
                    <button type="submit" class="inline-flex min-h-[40px] items-center justify-center rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-medium text-amber-700 transition hover:bg-amber-100">İnceleme dışı bırak</button>
                </form>
            @else
                <button type="button"
                        wire:click="reopenIssue({{ $issue->id }})"
                        class="inline-flex min-h-[40px] items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                    Yeniden aç
                </button>
            @endif
        </div>
    </div>

    @if($matchedProduct)
        <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
            Bu listeleme <span class="font-semibold">{{ $matchedProduct->product_name }}</span> ürününe bağlı.
        </div>
    @elseif(!$listing)
        <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
            Listeleme bilgisi olmadığı için yalnızca göz ardı veya yeniden inceleme kullanılabilir.
        </div>
    @endif

    <div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-[minmax(0,1fr)_220px] lg:items-end">
        <div>
            <label class="block text-xs font-medium text-slate-500">Mevcut ana ürünü ara</label>
            <input wire:model.live.debounce.300ms="issueSearchTerms.{{ $issue->id }}"
                   type="text"
                   placeholder="Ürün adı, stok kodu veya barkod"
                   class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:text-sm">
        </div>
        <p class="text-xs leading-5 text-slate-500">Mevcut ürün yoksa üstteki “Ana ürün olarak ekle” ile devam edin.</p>
    </div>

    @if($candidates->isNotEmpty())
        <div class="mt-3 divide-y divide-slate-200 overflow-hidden rounded-lg border border-slate-200">
            @foreach($candidates as $candidate)
                <div class="flex flex-col gap-3 p-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="truncate text-sm font-semibold text-slate-900">{{ $candidate->product_name ?: 'İsimsiz ürün' }}</p>
                            @if($recommended && $recommended->id === $candidate->id && $this->candidateScore($candidate) > 0)
                                <x-zolm.status-badge :tone="$this->canAutoRecommend($candidate) ? 'info' : 'warning'">
                                    {{ $this->canAutoRecommend($candidate) ? 'Önerilen' : 'Yakın aday' }}
                                </x-zolm.status-badge>
                            @endif
                        </div>
                        <p class="mt-1 text-xs text-slate-500">{{ $candidate->stock_code ?: '-' }} · {{ $candidate->barcode ?: '-' }} · Skor {{ $this->candidateScore($candidate) }}</p>
                    </div>
                    <button type="button"
                            wire:click="manualMatch({{ $issue->id }}, {{ $candidate->id }})"
                            wire:loading.attr="disabled"
                            wire:target="manualMatch({{ $issue->id }}, {{ $candidate->id }})"
                            @disabled(!$listing || $issue->match_status !== 'pending')
                            class="inline-flex min-h-[40px] shrink-0 items-center justify-center rounded-lg px-3 py-2 text-sm font-medium transition {{ $listing && $issue->match_status === 'pending' ? 'bg-slate-900 text-white hover:bg-slate-800' : 'cursor-not-allowed border border-slate-200 bg-slate-100 text-slate-400' }}">
                        Bu ürüne bağla
                    </button>
                </div>
            @endforeach
        </div>
    @else
        <p class="mt-3 text-sm text-slate-500">Uygun ana ürün bulunamadı. İsterseniz bu kaydı ana ürün olarak ekleyebilirsiniz.</p>
    @endif
</div>
