{{--
    Akıllı Eşleştirme Sihirbazı Modalı
    Eşleşmeyen sipariş stok kodlarını mp_products ile fuzzy match eder.
--}}
<div x-data="{ open: @entangle('showMatchWizard') }"
     x-show="open"
     x-cloak
     @keydown.escape.window="open = false; $wire.closeMatchWizard()"
     class="relative z-50"
     style="display: none;">

    {{-- Backdrop --}}
    <div x-show="open" x-transition.opacity class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm"></div>

    {{-- Modal --}}
    <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 sm:items-center sm:p-6">
            <div x-show="open"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:scale-95"
                 class="relative w-full max-w-4xl transform overflow-hidden rounded-[10px] bg-white text-left shadow-xl transition-all">

                {{-- Header --}}
                <div class="border-b border-slate-100 px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="flex h-8 w-8 items-center justify-center rounded-[6px] bg-indigo-100">
                                <svg class="h-4 w-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-base font-semibold text-slate-900">Akıllı Ürün Eşleştirme</h2>
                                <p class="text-xs text-slate-500">Eşleşmeyen siparişler için AI destekli stok kodu önerisi</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            @if(!empty($matchSuggestions))
                                <span class="rounded-[4px] bg-indigo-100 px-2 py-0.5 text-xs font-semibold text-indigo-700">
                                    {{ count($matchSuggestions) }} önerili grup
                                </span>
                            @endif
                            <button type="button" wire:click="closeMatchWizard"
                                    class="rounded-[6px] p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Body --}}
                <div class="max-h-[70vh] overflow-y-auto px-6 py-4">

                    @if($matchLoading)
                        <div class="flex items-center justify-center py-16">
                            <svg class="h-6 w-6 animate-spin text-indigo-600" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            <span class="ml-3 text-sm text-slate-600">Eşleştirmeler hesaplanıyor...</span>
                        </div>
                    @elseif(empty($matchSuggestions))
                        <div class="flex flex-col items-center justify-center py-16 text-center">
                            <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100">
                                <svg class="h-6 w-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                            <p class="text-sm font-semibold text-slate-900">Tüm siparişler eşleşmiş!</p>
                            <p class="mt-1 text-xs text-slate-500">Eşleştirilmesi gereken kayıt bulunamadı.</p>
                        </div>
                    @else
                        <div class="space-y-4">
                            @foreach($matchSuggestions as $idx => $row)
                                <div class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-4">
                                    {{-- Sipariş bilgisi --}}
                                    <div class="mb-3 flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold text-slate-900">{{ $row['order_product_name'] ?: '(ürün adı yok)' }}</p>
                                            <div class="mt-0.5 flex items-center gap-2">
                                                @if($row['order_stock_code'])
                                                    <span class="rounded-[4px] border border-slate-200 bg-white px-1.5 py-0.5 font-mono text-[10px] text-slate-500">{{ $row['order_stock_code'] }}</span>
                                                @endif
                                                <span class="text-xs text-slate-400">{{ $row['order_count'] }} sipariş</span>
                                            </div>
                                        </div>
                                        <button type="button"
                                                wire:click="skipMatchSuggestion('{{ addslashes($row['order_stock_code']) }}', '{{ addslashes($row['order_product_name']) }}')"
                                                class="shrink-0 rounded-[4px] border border-slate-200 bg-white px-2 py-1 text-xs text-slate-400 hover:bg-slate-50">
                                            Atla
                                        </button>
                                    </div>

                                    {{-- Öneriler --}}
                                    @if(!empty($row['suggestions']))
                                        <div class="space-y-2">
                                            @foreach($row['suggestions'] as $suggestion)
                                                @php
                                                    $pct = round($suggestion['score'] * 100);
                                                    $badgeColor = $pct >= 90 ? 'bg-emerald-100 text-emerald-700' : ($pct >= 70 ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600');
                                                    $barColor = $pct >= 90 ? 'bg-emerald-400' : ($pct >= 70 ? 'bg-amber-400' : 'bg-slate-400');
                                                @endphp
                                                <div class="flex items-center gap-3 rounded-[6px] border border-slate-200 bg-white px-3 py-2.5">
                                                    {{-- Confidence bar --}}
                                                    <div class="flex w-16 flex-col items-center gap-1 shrink-0">
                                                        <div class="h-1.5 w-full rounded-full bg-slate-100">
                                                            <div class="{{ $barColor }} h-1.5 rounded-full transition-all" style="width: {{ $pct }}%"></div>
                                                        </div>
                                                        <span class="text-[10px] font-bold {{ $badgeColor }} rounded px-1">%{{ $pct }}</span>
                                                    </div>

                                                    {{-- Ürün bilgisi --}}
                                                    <div class="min-w-0 flex-1">
                                                        <p class="truncate text-xs font-semibold text-slate-900">{{ $suggestion['product_name'] }}</p>
                                                        <div class="mt-0.5 flex items-center gap-1.5">
                                                            <span class="rounded-[3px] border border-slate-100 bg-slate-50 px-1 font-mono text-[10px] text-slate-400">{{ $suggestion['stock_code'] }}</span>
                                                            <span class="rounded-[3px] bg-slate-100 px-1 text-[10px] text-slate-400">{{ $suggestion['type'] }}</span>
                                                        </div>
                                                    </div>

                                                    {{-- Onayla --}}
                                                    <button type="button"
                                                            wire:click="confirmProductMatch('{{ addslashes($row['order_stock_code']) }}', '{{ addslashes($row['order_product_name']) }}', {{ $suggestion['product_id'] }}, {{ $suggestion['score'] }}, '{{ $suggestion['type'] }}')"
                                                            wire:loading.attr="disabled"
                                                            class="shrink-0 rounded-[6px] bg-slate-900 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-slate-700 disabled:opacity-50">
                                                        <span wire:loading.remove wire:target="confirmProductMatch">Eşleştir</span>
                                                        <span wire:loading wire:target="confirmProductMatch">...</span>
                                                    </button>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <p class="text-xs text-slate-400 italic">Bu sipariş için öneri üretilemedi.</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Footer --}}
                <div class="flex items-center justify-between border-t border-slate-100 px-6 py-4">
                    <p class="text-xs text-slate-400">
                        Eşleştirme güven skoru %80+ olanlar otomatik öneri olarak işaretlenir.
                    </p>
                    <button type="button" wire:click="closeMatchWizard"
                            class="rounded-[6px] border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50">
                        Kapat
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
