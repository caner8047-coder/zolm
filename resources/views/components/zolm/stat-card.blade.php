@props([
    'label' => null,
    'value' => null,
    'description' => null,
    'tone' => 'default',
    'variant' => 'default',
    'size' => 'default',
])

@php
    $toneClasses = match ($tone) {
        'success' => 'text-emerald-600',
        'warning' => 'text-amber-600',
        'danger' => 'text-rose-600',
        'info' => 'text-sky-600',
        default => 'text-slate-900',
    };

    $dotClasses = match ($tone) {
        'success' => 'bg-emerald-500',
        'warning' => 'bg-amber-500',
        'danger' => 'bg-rose-500',
        'info' => 'bg-sky-500',
        default => 'bg-indigo-500',
    };

    $classicBorderClasses = match ($tone) {
        'success' => 'border-l-emerald-500',
        'warning' => 'border-l-amber-500',
        'danger' => 'border-l-rose-500',
        'info' => 'border-l-sky-500',
        default => 'border-l-indigo-500',
    };

    $ordersBorderClasses = match ($tone) {
        'success' => 'border-l-green-500',
        'warning' => 'border-l-amber-500',
        'danger' => 'border-l-rose-500',
        'info' => 'border-l-indigo-500',
        default => 'border-l-purple-500',
    };

    $ordersIconWrapClasses = match ($tone) {
        'success' => 'bg-green-100 text-green-600',
        'warning' => 'bg-amber-100 text-amber-600',
        'danger' => 'bg-rose-100 text-rose-600',
        'info' => 'bg-indigo-100 text-indigo-600',
        default => 'bg-purple-100 text-purple-600',
    };

    $cardClasses = match ($variant) {
        'classic' => 'rounded-xl border border-gray-200 border-l-4 bg-white p-4 shadow-sm ' . $classicBorderClasses,
        'orders' => $size === 'compact'
            ? 'rounded-lg border-l-4 border border-gray-200 bg-white px-3 py-3 shadow ' . $ordersBorderClasses
            : 'rounded-lg border-l-4 border border-gray-200 bg-white p-3 lg:p-4 shadow ' . $ordersBorderClasses,
        'soft' => $size === 'compact'
            ? 'rounded-xl border border-slate-200 bg-white px-3.5 py-3 shadow-sm'
            : 'rounded-xl border border-slate-200 bg-white px-4 py-3.5 shadow-sm',
        default => 'rounded-3xl border border-slate-200 bg-slate-50 p-4',
    };

    $labelClasses = match ($variant) {
        'classic' => 'text-xs font-medium text-gray-500 uppercase',
        'orders' => 'text-xs font-medium text-gray-500 uppercase',
        'soft' => $size === 'compact'
            ? 'text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500'
            : 'text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-500',
        default => 'text-xs uppercase tracking-[0.2em] text-slate-500',
    };

    $valueClasses = match ($variant) {
        'classic' => 'mt-2 text-lg lg:text-xl font-bold ' . match ($tone) {
            'success' => 'text-emerald-600',
            'warning' => 'text-amber-600',
            'danger' => 'text-rose-600',
            'info' => 'text-sky-600',
            default => 'text-gray-900',
        },
        'orders' => $size === 'compact'
            ? 'mt-1 text-base sm:text-lg font-bold leading-tight whitespace-nowrap ' . match ($tone) {
                'success' => 'text-green-600',
                'warning' => 'text-amber-600',
                'danger' => 'text-rose-600',
                'info' => 'text-indigo-600',
                default => 'text-gray-900',
            }
            : 'text-lg lg:text-xl font-bold leading-tight whitespace-nowrap ' . match ($tone) {
                'success' => 'text-gray-900',
                'warning' => 'text-gray-900',
                'danger' => 'text-gray-900',
                'info' => 'text-gray-900',
                default => 'text-gray-900',
            },
        'soft' => $size === 'compact'
            ? 'mt-2 text-xl sm:text-2xl leading-none font-semibold tracking-tight whitespace-nowrap ' . $toneClasses
            : 'mt-2.5 text-[1.75rem] sm:text-[1.9rem] leading-none font-semibold tracking-tight whitespace-nowrap ' . $toneClasses,
        default => 'mt-2 text-2xl lg:text-3xl font-semibold ' . $toneClasses,
    };

    $descriptionClasses = match ($variant) {
        'classic' => 'mt-2 text-xs text-gray-500',
        'orders' => $size === 'compact'
            ? 'mt-1 text-[11px] leading-4 text-gray-500'
            : 'mt-1 text-xs text-gray-500',
        'soft' => $size === 'compact'
            ? 'mt-1.5 text-[11px] leading-4 text-slate-500'
            : 'mt-2 text-xs leading-4 text-slate-500',
        default => 'mt-2 text-[10px] sm:text-sm leading-4 sm:leading-5 text-slate-500',
    };

    $hasIcon = isset($icon) && trim((string) $icon) !== '';
@endphp

<div {{ $attributes->merge([
    'class' => $cardClasses,
]) }}>
    @if($variant === 'orders')
        <div class="flex items-center">
            @if($hasIcon)
                <div class="flex-shrink-0">
                    <div class="{{ $size === 'compact' ? 'h-9 w-9' : 'w-10 h-10' }} rounded-full flex items-center justify-center {{ $ordersIconWrapClasses }}">
                        {{ $icon }}
                    </div>
                </div>
            @endif

            <div class="{{ $hasIcon ? 'ml-3' : '' }} min-w-0 flex-1">
                @if($label)
                    <p class="{{ $labelClasses }}">{{ $label }}</p>
                @endif

                @if(!is_null($value))
                    <p class="{{ $valueClasses }}">{{ $value }}</p>
                @endif

                @if($description)
                    <p class="{{ $descriptionClasses }}">{{ $description }}</p>
                @endif

                @if($slot->isNotEmpty())
                    <div class="{{ $size === 'compact' ? 'mt-1' : 'mt-1.5' }}">
                        {{ $slot }}
                    </div>
                @endif
            </div>
        </div>
    @elseif($variant === 'soft')
        <div class="flex items-center justify-between gap-2">
            @if($label)
                <p class="{{ $labelClasses }}">{{ $label }}</p>
            @endif
            <span class="{{ $size === 'compact' ? 'h-1.5 w-1.5' : 'h-2 w-2' }} shrink-0 rounded-full {{ $dotClasses }}"></span>
        </div>
    @elseif($label)
        <p class="{{ $labelClasses }}">{{ $label }}</p>
    @endif

    @unless($variant === 'orders')
        @if(!is_null($value))
            <p class="{{ $valueClasses }}">{{ $value }}</p>
        @endif

        @if($description)
            <p class="{{ $descriptionClasses }}">{{ $description }}</p>
        @endif

        @if($slot->isNotEmpty())
            <div class="{{ $variant === 'soft' ? ($size === 'compact' ? 'mt-1.5' : 'mt-2') : 'mt-3' }}">
                {{ $slot }}
            </div>
        @endif
    @endunless
</div>
