@props([
    'tone' => 'default',
    'size' => 'md',
])

@php
    $toneClasses = match ($tone) {
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-700',
        'danger' => 'border-rose-200 bg-rose-50 text-rose-700',
        'info' => 'border-sky-200 bg-sky-50 text-sky-700',
        default => 'border-slate-200 bg-slate-100 text-slate-700',
    };

    $sizeClasses = match ($size) {
        'xs' => 'px-1.5 py-0.5 text-[10px] leading-4',
        'sm' => 'px-2 py-0.5 text-[11px] leading-4',
        default => 'px-3 py-1 text-xs',
    };
@endphp

<span {{ $attributes->merge([
    'class' => 'inline-flex max-w-full items-center whitespace-nowrap rounded-full border font-medium ' . $sizeClasses . ' ' . $toneClasses,
]) }}>
    {{ $slot }}
</span>
