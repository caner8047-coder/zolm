@props([
    'tag' => 'span',
    'class' => '',
    'variant' => 'default',
])

@php
    $baseClasses = $variant === 'classic'
        ? 'text-xs font-semibold uppercase tracking-wider text-gray-500 '
        : 'inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium uppercase tracking-[0.24em] text-slate-500 ';
@endphp

<{{ $tag }} {{ $attributes->merge([
    'class' => $baseClasses . $class,
]) }}>
    {{ $slot }}
</{{ $tag }}>
