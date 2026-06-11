@props([
    'count' => 1,
    'recipeId' => null,
    'stockCode' => null,
])

@php
    $recipeCount = max(0, (int) $count);
    $hasStockCode = filled($stockCode);
    $stockLabel = $hasStockCode ? (string) $stockCode : 'Doğrudan ürün bağlantısı';
    $badgeTitle = $hasStockCode
        ? ($recipeCount > 1
            ? "{$stockLabel} stok kodunda {$recipeCount} aktif reçete bağlı"
            : "{$stockLabel} stok kodu aktif reçeteye bağlı")
        : ($recipeCount > 1
            ? "Ürüne {$recipeCount} aktif reçete bağlı"
            : 'Ürün aktif reçeteye bağlı');
@endphp

@if($recipeCount > 0)
    <span {{ $attributes->merge(['class' => 'group relative inline-flex shrink-0 cursor-help align-middle']) }}
          data-recipe-id="{{ $recipeId ?: '' }}"
          title="{{ $badgeTitle }}"
          aria-label="Reçeteye bağlı ürün">
        <span class="inline-flex h-4 w-4 items-center justify-center rounded-full bg-blue-500 text-white shadow-sm ring-1 ring-blue-600/10">
            <svg class="h-2.5 w-2.5" viewBox="0 0 12 12" fill="none" aria-hidden="true">
                <path d="M3 6.15 5.05 8.2 9.1 3.8" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </span>

        <span class="pointer-events-none absolute left-1/2 top-full z-50 mt-2 hidden w-56 -translate-x-1/2 rounded-[8px] border border-blue-100 bg-white p-3 text-left shadow-lg group-hover:block">
            <span class="block text-xs font-semibold text-slate-900">Reçeteye bağlı ürün</span>
            @if($hasStockCode)
                <span class="mt-1 block text-[11px] leading-4 text-slate-500">
                    Stok kodu eşleşti: <span class="font-mono font-semibold text-slate-700">{{ $stockLabel }}</span>
                </span>
            @else
                <span class="mt-1 block text-[11px] leading-4 text-slate-500">
                    Reçete ürün kartına doğrudan bağlı.
                </span>
            @endif
            <span class="mt-1 block text-[11px] leading-4 text-blue-700">
                {{ $recipeCount > 1 ? $recipeCount . ' aktif reçete' : 'Aktif reçete onayı' }}
            </span>
        </span>
    </span>
@endif
