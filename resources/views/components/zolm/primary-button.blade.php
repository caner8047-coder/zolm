@props([
    'type' => 'button',
    'compact' => false,
    'color' => 'slate',
])

@php
    $sizeClasses = $compact
        ? 'px-3 py-2 text-sm'
        : 'px-4 py-3 sm:py-2 text-base sm:text-sm';

    $colorClasses = match ($color) {
        'indigo' => 'bg-indigo-600 text-white hover:bg-indigo-700',
        'emerald' => 'bg-emerald-600 text-white hover:bg-emerald-700',
        'rose' => 'bg-rose-600 text-white hover:bg-rose-700',
        'amber' => 'bg-amber-500 text-white hover:bg-amber-600',
        default => 'bg-slate-900 text-white hover:bg-slate-800',
    };
@endphp

<button
    type="{{ $type }}"
    {{ $attributes->merge([
        'class' => 'w-full sm:w-auto rounded-lg font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-60 ' . $colorClasses . ' ' . $sizeClasses,
    ]) }}
>
    {{ $slot }}
</button>
