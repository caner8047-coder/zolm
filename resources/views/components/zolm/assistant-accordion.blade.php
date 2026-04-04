@props([
    'title',
    'eyebrow' => null,
    'description' => null,
    'badge' => null,
    'open' => false,
    'tone' => 'default',
    'grouped' => false,
    'name' => null,
])

@php
    $badgeClasses = match ($tone) {
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-700',
        'danger' => 'border-rose-200 bg-rose-50 text-rose-700',
        'info' => 'border-sky-200 bg-sky-50 text-sky-700',
        default => 'border-slate-200 bg-slate-50 text-slate-600',
    };

    $isGrouped = $grouped && filled($name);
@endphp

<div
    @if(!$isGrouped)
        x-data="{ open: @js($open) }"
    @endif
    {{ $attributes->merge(['class' => 'rounded-3xl border border-slate-200 bg-slate-50/70']) }}
>
    <button type="button"
            @if($isGrouped)
                @click="assistantOpen = assistantOpen === @js($name) ? null : @js($name)"
            @else
                @click="open = !open"
            @endif
            class="flex w-full items-start justify-between gap-3 px-4 py-4 text-left">
        <div class="min-w-0">
            @if($eyebrow)
                <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-500">{{ $eyebrow }}</p>
            @endif
            <p class="mt-1 text-sm font-semibold text-slate-900">{{ $title }}</p>
            @if($description)
                <p class="mt-1 text-xs leading-5 text-slate-500">{{ $description }}</p>
            @endif
        </div>

        <div class="flex shrink-0 items-center gap-2">
            @if(filled($badge))
                <span class="rounded-full border px-3 py-1 text-[11px] font-medium {{ $badgeClasses }}">
                    {{ $badge }}
                </span>
            @endif
            <span class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-500 transition"
                  :class="{ 'rotate-180': {{ $isGrouped ? "assistantOpen === " . json_encode($name) : 'open' }} }">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 9l-7 7-7-7" />
                </svg>
            </span>
        </div>
    </button>

    <div
        x-cloak
        x-show="{{ $isGrouped ? "assistantOpen === " . json_encode($name) : 'open' }}"
        x-transition.opacity.duration.150ms
        class="border-t border-slate-200 bg-white px-4 py-4"
    >
        {{ $slot }}
    </div>
</div>
