{{-- Hızlı ürün eşleştirme modalı --}}
@if($showQuickMatchModal)
    @php
        $quickMatchProduct = $this->quickMatchProduct;
        $quickMatchIssues = $this->quickMatchIssues;
    @endphp

    <div class="fixed inset-0 z-50 overflow-y-auto" x-data @keydown.escape.window="$wire.closeQuickMatchModal()">
        <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" wire:click="closeQuickMatchModal"></div>

        <div class="relative flex min-h-full items-end justify-center p-4 text-left sm:items-center sm:p-6">
            <div class="relative w-full overflow-hidden rounded-[10px] border border-slate-200 bg-white shadow-xl sm:max-w-3xl">
                <div class="border-b border-slate-200 px-4 py-4 lg:px-6">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Hızlı Eşleştirme</p>
                            <h3 class="mt-1 text-lg font-semibold text-slate-900">Bekleyen kanal kayıtlarını ürüne bağla</h3>
                            @if($quickMatchProduct)
                                <p class="mt-1 truncate text-sm text-slate-500">
                                    {{ $quickMatchProduct->product_name ?: 'İsimsiz ürün' }}
                                    <span class="font-mono text-slate-400">· {{ $quickMatchProduct->stock_code ?: $quickMatchProduct->barcode }}</span>
                                </p>
                            @endif
                        </div>

                        <button type="button"
                                wire:click="closeQuickMatchModal"
                                class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-[6px] border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50 hover:text-slate-700">
                            <span class="sr-only">Kapat</span>
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="space-y-4 px-4 py-4 lg:px-6">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-[minmax(0,1fr)_220px]">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600">Arama</label>
                            <input type="text"
                                   wire:model.live.debounce.300ms="quickMatchSearch"
                                   class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 placeholder:text-slate-400 focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-200 sm:py-2 sm:text-sm"
                                   placeholder="Stok kodu, barkod veya ürün adı">
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600">Pazaryeri</label>
                            <select wire:model.live="quickMatchMarketplaceFilter"
                                    class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-200 sm:py-2 sm:text-sm">
                                <option value="all">Tüm pazaryerleri</option>
                                @foreach($marketplaceOptions as $marketplace)
                                    <option value="{{ $marketplace }}">{{ $this->humanMarketplace($marketplace) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm font-semibold text-slate-900">Aday kayıtlar</p>
                                <p class="mt-0.5 text-xs text-slate-500">Trendyol, Hepsiburada, N11, Pazarama, Çiçeksepeti, Koçtaş ve WooCommerce kayıtları aynı merkezden bağlanır.</p>
                            </div>
                            <span class="inline-flex w-fit rounded-[6px] border border-slate-200 bg-white px-2 py-1 text-xs font-semibold text-slate-600">
                                {{ $formatCount($quickMatchIssues->count()) }} aday
                            </span>
                        </div>
                    </div>

                    <div class="max-h-[48vh] space-y-2 overflow-y-auto pr-1">
                        @forelse($quickMatchIssues as $issue)
                            @php
                                $store = $issue->store;
                                $listing = $issue->channelListing;
                                $channelProduct = $listing?->channelProduct;
                                $candidateIds = collect((array) $issue->candidate_ids_json)->filter()->values();
                            @endphp

                            <article class="rounded-[8px] border border-slate-200 bg-white p-3 shadow-sm">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex min-w-0 flex-wrap items-center gap-2">
                                            <x-zolm.status-badge tone="default">{{ $this->humanMarketplace($store?->marketplace) }}</x-zolm.status-badge>
                                            <span class="truncate text-sm font-semibold text-slate-900">{{ $store?->store_name ?: 'Mağaza adı yok' }}</span>
                                        </div>
                                        <p class="product-name-clamp mt-2 text-sm text-slate-700">
                                            {{ $channelProduct?->title ?: ($issue->match_reason ?: 'Kanal ürün bilgisi eksik') }}
                                        </p>
                                        <div class="mt-2 flex flex-wrap gap-1.5 text-[11px] text-slate-500">
                                            <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-1">
                                                SKU <span class="font-mono text-slate-700">{{ $channelProduct?->stock_code ?: '-' }}</span>
                                            </span>
                                            <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-1">
                                                Barkod <span class="font-mono text-slate-700">{{ $channelProduct?->barcode ?: '-' }}</span>
                                            </span>
                                            <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-1">
                                                Aday {{ $candidateIds->count() }}
                                            </span>
                                        </div>
                                    </div>

                                    <button type="button"
                                            wire:click="quickMatchIssue({{ $issue->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="quickMatchIssue({{ $issue->id }})"
                                            class="inline-flex min-h-[44px] shrink-0 items-center justify-center rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60 sm:py-2">
                                        Bu ürüne bağla
                                    </button>
                                </div>
                            </article>
                        @empty
                            <div class="rounded-[8px] border border-dashed border-slate-300 bg-white px-4 py-8 text-center text-sm text-slate-500">
                                Bu ürün için bekleyen eşleşme adayı bulunamadı.
                            </div>
                        @endforelse
                    </div>
                </div>

                <div class="flex flex-col gap-3 border-t border-slate-200 bg-slate-50/60 px-4 py-4 sm:flex-row sm:items-center sm:justify-between lg:px-6">
                    <p class="text-xs leading-5 text-slate-500">Toplu karar, düşük güvenli adaylar ve detaylı filtreler için Eşleştirme Merkezi kullanılır.</p>
                    <a href="{{ route('mp.matching', ['statusFilter' => 'pending']) }}"
                       class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 sm:py-2">
                        Eşleştirme Merkezi
                    </a>
                </div>
            </div>
        </div>
    </div>
@endif
