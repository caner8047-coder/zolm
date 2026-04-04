@php
    $listing = $issue->channelListing;
    $channelProduct = $listing?->channelProduct;
    $matchedProduct = $listing?->product;
    $candidates = $candidateMap[$issue->id] ?? collect();
    $recommended = $candidates->first();
@endphp

<div class="space-y-4 lg:space-y-5">
    <div class="grid grid-cols-1 sm:grid-cols-2 2xl:grid-cols-4 gap-3 lg:gap-4">
        <div class="min-w-0 rounded-3xl border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Sorun durumu</p>
            <div class="mt-2">
                <x-zolm.status-badge :tone="$this->statusTone($issue->match_status)">
                    {{ $this->statusLabel($issue->match_status) }}
                </x-zolm.status-badge>
            </div>
            <p class="mt-3 text-sm text-slate-500">{{ $this->reasonLabel($issue->match_reason) }}</p>
        </div>

        <div class="min-w-0 rounded-3xl border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Listeleme</p>
            <p class="mt-3 text-base font-semibold text-slate-900">{{ $listing?->listing_id ?: 'Listeleme yok' }}</p>
            <p class="mt-2 text-sm text-slate-500">{{ $listing?->listing_status ? $this->statusLabel($listing->listing_status) : 'İlişki yok' }}</p>
        </div>

        <div class="min-w-0 rounded-3xl border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Aday sayısı</p>
            <p class="mt-3 text-base font-semibold text-slate-900">{{ number_format($candidates->count(), 0, ',', '.') }}</p>
            <p class="mt-2 text-sm text-slate-500">{{ count((array) ($issue->candidate_ids_json ?? [])) }} otomatik öneri</p>
        </div>

        <div class="min-w-0 rounded-3xl border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Çözümleyen</p>
            <p class="mt-3 text-base font-semibold text-slate-900">{{ $issue->resolver?->name ?: '-' }}</p>
            <p class="mt-2 text-sm text-slate-500">{{ $issue->resolved_at?->format('d.m.Y H:i') ?: 'Henüz çözülmedi' }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 2xl:grid-cols-2 gap-3 lg:gap-4">
        <div class="min-w-0 rounded-3xl border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Kanal kaydı</p>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex items-start justify-between gap-3">
                    <dt class="text-slate-500">Başlık</dt>
                    <dd class="min-w-0 break-words text-right font-medium text-slate-900">{{ $channelProduct?->title ?: '-' }}</dd>
                </div>
                <div class="flex items-start justify-between gap-3">
                    <dt class="text-slate-500">Stok kodu</dt>
                    <dd class="min-w-0 break-words text-right font-mono font-medium text-slate-900">{{ $channelProduct?->stock_code ?: '-' }}</dd>
                </div>
                <div class="flex items-start justify-between gap-3">
                    <dt class="text-slate-500">Barkod</dt>
                    <dd class="min-w-0 break-words text-right font-mono font-medium text-slate-900">{{ $channelProduct?->barcode ?: '-' }}</dd>
                </div>
                <div class="flex items-start justify-between gap-3">
                    <dt class="text-slate-500">Kanal</dt>
                    <dd class="min-w-0 break-words text-right font-medium text-slate-900">{{ $this->humanMarketplace($issue->store?->marketplace) }}</dd>
                </div>
                <div class="flex items-start justify-between gap-3">
                    <dt class="text-slate-500">Mağaza</dt>
                    <dd class="min-w-0 break-words text-right font-medium text-slate-900">{{ $issue->store?->store_name ?: '-' }}</dd>
                </div>
            </dl>

            @if($matchedProduct)
                <div class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-3 py-3 text-sm text-emerald-700">
                    Bu listeleme şu anda <span class="font-semibold">{{ $matchedProduct->product_name }}</span> ürününe bağlı.
                </div>
            @endif

            @if(!$listing)
                <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 px-3 py-3 text-sm text-amber-800">
                    Bu sorun bir listeleme kaydına bağlı değil. Şimdilik yalnız listeleme bazlı kayıtlar manuel bağlanabiliyor; bu kayıt için göz ardı veya tekrar inceleme akışı kullanılabilir.
                </div>
            @endif
        </div>

        <div class="min-w-0 rounded-3xl border border-slate-200 bg-white p-4">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                <div>
                    <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Aday ürünler</p>
                    <p class="mt-2 text-sm text-slate-500">İstersen otomatik adaylardan seç, istersen manuel arama ile havuzu daralt.</p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    @if($issue->match_status === 'pending' && $listing && $recommended && $this->candidateScore($recommended) > 0)
                        <button type="button"
                                wire:click="manualMatchRecommended({{ $issue->id }})"
                                class="inline-flex min-h-[44px] items-center justify-center rounded-lg bg-slate-900 px-4 py-3 sm:py-2 text-sm font-medium text-white transition hover:bg-slate-800">
                            Önerileni bağla
                        </button>
                    @endif

                    @if($issue->match_status === 'pending')
                        <button type="button"
                                wire:click="ignoreIssue({{ $issue->id }})"
                                class="inline-flex min-h-[44px] items-center justify-center rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 sm:py-2 text-sm font-medium text-amber-700 transition hover:bg-amber-100">
                            Göz ardı et
                        </button>
                    @else
                        <button type="button"
                                wire:click="reopenIssue({{ $issue->id }})"
                                class="inline-flex min-h-[44px] items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                            Yeniden aç
                        </button>
                    @endif
                </div>
            </div>

            <div class="mt-4">
                <label class="block text-xs sm:text-sm font-medium text-slate-500">Manuel ürün arama</label>
                <input wire:model.live.debounce.300ms="issueSearchTerms.{{ $issue->id }}"
                       type="text"
                       placeholder="Ürün adı, stok kodu veya barkod..."
                       class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-3 py-3 text-base sm:text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
            </div>

            @if($candidates->isNotEmpty())
                <div class="mt-4 space-y-3">
                    @foreach($candidates as $candidate)
                        <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-3">
                            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-slate-900">{{ $candidate->product_name ?: 'İsimsiz ürün' }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $candidate->stock_code ?: '-' }} · {{ $candidate->barcode ?: '-' }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $candidate->brand ?: '-' }}{{ $candidate->category_name ? ' · ' . $candidate->category_name : '' }}</p>
                                    <div class="mt-2 flex flex-wrap items-center gap-2">
                                        <x-zolm.status-badge :tone="$this->candidateScoreTone($candidate)">
                                            Skor {{ $this->candidateScore($candidate) }}
                                        </x-zolm.status-badge>
                                        @if($recommended && $recommended->id === $candidate->id && $this->candidateScore($candidate) > 0)
                                            <x-zolm.status-badge tone="info">
                                                Önerilen
                                            </x-zolm.status-badge>
                                        @endif
                                    </div>
                                    @if($this->candidateReasons($candidate) !== [])
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            @foreach($this->candidateReasons($candidate) as $reason)
                                                <span class="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-medium text-slate-600">{{ $reason }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>

                                <div class="flex flex-col items-stretch gap-2 sm:items-end">
                                    <div class="text-sm font-semibold text-slate-900">₺{{ number_format((float) $candidate->sale_price, 2, ',', '.') }}</div>
                                    <button type="button"
                                            wire:click="manualMatch({{ $issue->id }}, {{ $candidate->id }})"
                                            @disabled(!$listing || $issue->match_status !== 'pending')
                                            class="inline-flex min-h-[44px] items-center justify-center rounded-lg px-4 py-3 sm:py-2 text-sm font-medium transition {{ $listing && $issue->match_status === 'pending' ? 'bg-slate-900 text-white hover:bg-slate-800' : 'cursor-not-allowed border border-slate-200 bg-slate-100 text-slate-400' }}">
                                        Bu ürüne bağla
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="mt-4 rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                    Bu sorun için otomatik veya manuel aramaya göre aday ürün bulunamadı.
                </div>
            @endif
        </div>
    </div>
</div>
