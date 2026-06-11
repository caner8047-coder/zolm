@php
    $setItems = $product->productSet?->items ?? collect();
    $componentCount = $setItems->count();
    $popoverFocusable = $popoverFocusable ?? true;
    $badgeLabel = $badgeLabel ?? ('Set · ' . $componentCount);
    $formatSetQuantity = function ($value) {
        $number = (float) $value;

        return floor($number) === $number
            ? number_format($number, 0, ',', '.')
            : number_format($number, 2, ',', '.');
    };
    $formatSetMoney = fn ($value) => '₺' . number_format((float) $value, 2, ',', '.');
    $formatSetDecimal = function ($value) {
        $number = (float) $value;

        return floor($number) === $number
            ? number_format($number, 0, ',', '.')
            : number_format($number, 1, ',', '.');
    };
@endphp

<div x-data="{ open: false }"
     @mouseenter="open = true"
     @mouseleave="open = false"
     @focusin="open = true"
     @focusout="open = false"
     @click.stop="open = !open"
     @click.outside="open = false"
     class="relative mt-0.5 inline-flex shrink-0"
     @if($popoverFocusable) tabindex="0" aria-label="Set içeriğini göster" @endif>
    <span class="inline-flex items-center rounded-[6px] border border-amber-200 bg-amber-50 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700">
        {{ $badgeLabel }}
    </span>

    <div x-show="open"
         x-cloak
         x-transition
         class="absolute left-0 top-full z-[80] mt-2 w-80 max-w-[calc(100vw-2rem)] rounded-[8px] border border-slate-200 bg-white p-3 text-left shadow-xl">
        <div class="absolute -top-1 left-5 h-2 w-2 rotate-45 border-l border-t border-slate-200 bg-white"></div>

        <div class="min-w-0 border-b border-slate-100 pb-2">
            <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-amber-600">Set içeriği</p>
            <p class="mt-1 line-clamp-2 text-xs font-semibold leading-4 text-slate-900">{{ $product->product_name ?: 'İsimsiz ürün' }}</p>
            <p class="mt-1 text-[11px] text-slate-500">{{ $componentCount }} bileşen satırı</p>
        </div>

        @if($componentCount > 0)
            <div class="mt-2 max-h-64 space-y-1.5 overflow-y-auto pr-1">
                @foreach($setItems->take(6) as $setItem)
                    @php
                        $component = $setItem->componentProduct;
                        $componentName = $component?->product_name ?: 'Bağlı ürün bulunamadı';
                        $componentCode = $component?->stock_code ?: $component?->barcode;
                        $displayCost = $setItem->include_cost
                            ? ($setItem->cost_override ?? ($component?->cogs ?? 0))
                            : 0;
                        $displayCargoCost = $setItem->include_logistics
                            ? ($setItem->cargo_cost_override ?? ($component?->cargo_cost ?? 0))
                            : 0;
                        $displayDesi = $setItem->include_logistics
                            ? ($setItem->desi_override ?? ($component?->desi ?? 0))
                            : 0;
                        $displayPieces = $setItem->include_logistics
                            ? ($setItem->pieces_override ?? ($component?->pieces ?? 1))
                            : 0;
                    @endphp

                    <div class="grid grid-cols-[34px_minmax(0,1fr)] gap-2 rounded-[6px] border border-slate-100 bg-slate-50/70 px-2 py-1.5">
                        <span class="inline-flex h-6 items-center justify-center rounded-[6px] bg-white text-[10px] font-semibold text-amber-700">
                            {{ $formatSetQuantity($setItem->quantity) }}x
                        </span>
                        <div class="min-w-0">
                            <p class="truncate text-[11px] font-semibold leading-4 text-slate-900">{{ $componentName }}</p>
                            @if($componentCode)
                                <p class="truncate text-[10px] leading-4 text-slate-500">{{ $componentCode }}</p>
                            @endif
                            @if($component)
                                <div class="mt-1 flex flex-wrap gap-1 text-[10px] text-slate-500">
                                    <span class="rounded-[5px] border border-slate-200 bg-white px-1.5 py-0.5">M {{ $formatSetMoney($displayCost) }}</span>
                                    <span class="rounded-[5px] border border-slate-200 bg-white px-1.5 py-0.5">K {{ $formatSetMoney($displayCargoCost) }}</span>
                                    <span class="rounded-[5px] border border-slate-200 bg-white px-1.5 py-0.5">D {{ $formatSetDecimal($displayDesi) }}</span>
                                    <span class="rounded-[5px] border border-slate-200 bg-white px-1.5 py-0.5">P {{ (int) $displayPieces }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            @if($componentCount > 6)
                <p class="mt-2 text-[11px] font-medium text-slate-500">+{{ $componentCount - 6 }} bileşen daha</p>
            @endif
        @else
            <div class="mt-2 rounded-[6px] border border-amber-100 bg-amber-50 px-2 py-2 text-[11px] text-amber-700">
                İçerik henüz tanımlanmamış.
            </div>
        @endif
    </div>
</div>
