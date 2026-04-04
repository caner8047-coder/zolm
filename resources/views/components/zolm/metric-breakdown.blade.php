@props([
    'title' => null,
    'subtitle' => null,
    'rows' => [],
    'resultLabel' => null,
    'resultValue' => null,
    'resultTone' => 'default',
    'formulas' => [],
    'note' => null,
    'position' => 'right',
    'width' => 'w-72 max-w-[min(18rem,calc(100vw-2rem))]',
])

@php
    $panelPositionClasses = match ($position) {
        'left' => 'left-0 top-full mt-2',
        'top' => 'bottom-full right-0 mb-2',
        'center' => 'left-1/2 top-full mt-2 -translate-x-1/2',
        default => 'right-0 top-full mt-2',
    };

    $toneClasses = [
        'default' => 'text-slate-900',
        'success' => 'text-emerald-600',
        'danger' => 'text-rose-600',
        'warning' => 'text-amber-600',
        'info' => 'text-sky-600',
    ];
@endphp

<div
    x-data="{
        open: false,
        touchMode: window.matchMedia('(hover: none)').matches,
        show() { if (!this.touchMode) this.open = true },
        hide() { if (!this.touchMode) this.open = false },
        toggle() { this.open = !this.open },
    }"
    class="relative block"
    :class="open ? 'z-[70]' : ''"
    @mouseenter="show()"
    @mouseleave="hide()"
>
    <button
        type="button"
        @click.prevent.stop="toggle()"
        @focus="open = true"
        @keydown.escape.stop="open = false"
        class="block w-full text-inherit"
    >
        {{ $slot }}
    </button>

    <div
        x-cloak
        x-show="open"
        x-transition.opacity.scale.origin.top
        @click.outside="open = false"
        class="absolute {{ $panelPositionClasses }} {{ $width }} text-left"
    >
        <div class="rounded-[10px] border border-slate-200 bg-white p-3 shadow-xl shadow-slate-900/10">
            @if($title)
                <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-500">{{ $title }}</p>
            @endif

            @if($subtitle)
                <p class="mt-1.5 text-[11px] leading-4 text-slate-600">{{ $subtitle }}</p>
            @endif

            @if(!empty($rows))
                <div class="mt-3 space-y-1.5">
                    @foreach($rows as $row)
                        <div class="flex items-start justify-between gap-3 text-[11px] leading-4">
                            <span class="min-w-0 flex-1 text-slate-500">{{ $row['label'] ?? '-' }}</span>
                            <span class="shrink-0 font-medium {{ $toneClasses[$row['tone'] ?? 'default'] ?? $toneClasses['default'] }}">
                                {{ $row['value'] ?? '-' }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif

            @if($resultLabel || $resultValue)
                <div class="mt-3 rounded-[8px] border border-slate-200 bg-slate-50/70 px-3 py-2">
                    <div class="flex items-start justify-between gap-3 text-[11px] leading-4">
                        <span class="min-w-0 flex-1 font-medium text-slate-600">{{ $resultLabel }}</span>
                        <span class="shrink-0 font-semibold {{ $toneClasses[$resultTone] ?? $toneClasses['default'] }}">
                            {{ $resultValue }}
                        </span>
                    </div>
                </div>
            @endif

            @if(!empty($formulas))
                <div class="mt-3 space-y-1 border-t border-slate-100 pt-3">
                    @foreach($formulas as $formula)
                        <p class="text-[10px] leading-4 text-slate-500">{{ $formula }}</p>
                    @endforeach
                </div>
            @endif

            @if($note)
                <p class="mt-3 border-t border-slate-100 pt-3 text-[10px] leading-4 text-slate-400">{{ $note }}</p>
            @endif
        </div>
    </div>
</div>
