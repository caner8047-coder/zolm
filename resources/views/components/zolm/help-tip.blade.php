@props([
    'title' => null,
    'summary' => null,
    'source' => null,
    'refresh' => null,
    'impact' => null,
    'position' => 'right',
    'width' => 'w-64 max-w-[min(16rem,calc(100vw-2rem))]',
    'buttonClass' => '',
])

@php
    $helpTipsEnabled = app(\App\Services\MpSettingsService::class)->helpTipsEnabled();

    $panelPositionClasses = match ($position) {
        'left' => 'left-0 top-full mt-2',
        'top' => 'bottom-full right-0 mb-2',
        'center' => 'left-1/2 top-full mt-2 -translate-x-1/2',
        default => 'right-0 top-full mt-2',
    };

    $defaultButtonClass = 'inline-flex h-3.5 w-3.5 shrink-0 items-center justify-center rounded-full border border-slate-200 bg-white text-[9px] font-semibold leading-none text-slate-400 transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-600 focus:outline-none focus:ring-2 focus:ring-slate-200';
@endphp

@if($helpTipsEnabled)
    <div
        x-data="{
            open: false,
            touchMode: window.matchMedia('(hover: none)').matches,
            show() {
                if (!this.touchMode) this.open = true;
            },
            hide() {
                if (!this.touchMode) this.open = false;
            },
            toggle() {
                this.open = !this.open;
            },
        }"
        class="relative inline-flex shrink-0 align-middle normal-case tracking-normal whitespace-normal"
        @mouseenter="show()"
        @mouseleave="hide()"
    >
        <button
            type="button"
            @click.prevent.stop="toggle()"
            @focus="open = true"
            @keydown.escape.stop="open = false"
            class="{{ $defaultButtonClass }} {{ $buttonClass }}"
            aria-label="{{ $title ? $title . ' hakkında bilgi' : 'Bilgi' }}"
        >
            i
        </button>

        <div
            x-cloak
            x-show="open"
            x-transition.opacity.scale.origin.top
            @click.outside="open = false"
            class="absolute z-[80] {{ $panelPositionClasses }} {{ $width }} text-left normal-case tracking-normal whitespace-normal"
        >
            <div class="rounded-[10px] border border-slate-200 bg-white p-2.5 shadow-xl shadow-slate-900/8 normal-case tracking-normal whitespace-normal">
                @if($title)
                    <p class="text-[10px] font-semibold text-slate-600 normal-case tracking-normal break-words">{{ $title }}</p>
                @endif

                @if($summary)
                    <p class="mt-1.5 text-[11px] leading-4 text-slate-700 normal-case tracking-normal break-words">{{ $summary }}</p>
                @endif

                @if($source || $refresh || $impact)
                    <div class="mt-2.5 space-y-2 border-t border-slate-100 pt-2.5">
                        @if($source)
                            <div>
                                <p class="text-[9px] font-semibold uppercase tracking-[0.12em] text-slate-400">Veri kaynağı</p>
                                <p class="mt-0.5 text-[11px] leading-4 text-slate-600 normal-case tracking-normal break-words">{{ $source }}</p>
                            </div>
                        @endif

                        @if($refresh)
                            <div>
                                <p class="text-[9px] font-semibold uppercase tracking-[0.12em] text-slate-400">Güncellenme</p>
                                <p class="mt-0.5 text-[11px] leading-4 text-slate-600 normal-case tracking-normal break-words">{{ $refresh }}</p>
                            </div>
                        @endif

                        @if($impact)
                            <div>
                                <p class="text-[9px] font-semibold uppercase tracking-[0.12em] text-slate-400">Etkisi</p>
                                <p class="mt-0.5 text-[11px] leading-4 text-slate-600 normal-case tracking-normal break-words">{{ $impact }}</p>
                            </div>
                        @endif
                    </div>
                @endif

                @if($slot->isNotEmpty())
                    <div class="{{ ($summary || $source || $refresh || $impact) ? 'mt-2.5 border-t border-slate-100 pt-2.5' : 'mt-1.5' }}">
                        {{ $slot }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endif
