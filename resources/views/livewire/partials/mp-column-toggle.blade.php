{{-- Column visibility toggle dropdown --}}
{{-- Required: $columnDefs, $visibleColumns --}}
@php
    $requiredColumns = \App\Livewire\MpProductsManager::REQUIRED_COLUMNS;
    $recommendedColumns = \App\Livewire\MpProductsManager::RECOMMENDED_COLUMNS;
    $isRecommendedView = $visibleColumns === $recommendedColumns;
@endphp

<div
    x-data="{
        open: false,
        panelStyle: {},
        toggleMenu() {
            if (this.open) {
                this.closeMenu();
                return;
            }

            this.open = true;
            this.positionMenu();
        },
        closeMenu(restoreFocus = true) {
            this.open = false;

            if (restoreFocus) {
                this.$nextTick(() => this.$refs.trigger?.focus());
            }
        },
        positionMenu() {
            this.$nextTick(() => {
                const trigger = this.$refs.trigger;

                if (!trigger) {
                    return;
                }

                const viewportPadding = 12;
                const gap = 8;
                const viewportHeight = window.visualViewport?.height ?? window.innerHeight;
                const triggerRect = trigger.getBoundingClientRect();
                const panelWidth = Math.min(248, window.innerWidth - (viewportPadding * 2));
                const left = Math.min(
                    Math.max(viewportPadding, triggerRect.right - panelWidth),
                    window.innerWidth - panelWidth - viewportPadding,
                );
                const spaceBelow = viewportHeight - triggerRect.bottom - viewportPadding - gap;
                const spaceAbove = triggerRect.top - viewportPadding - gap;
                const opensUp = spaceBelow < 360 && spaceAbove > spaceBelow;
                const availableHeight = Math.max(220, opensUp ? spaceAbove : spaceBelow);
                const maxHeight = Math.min(560, availableHeight);

                this.panelStyle = {
                    left: `${left}px`,
                    top: opensUp ? 'auto' : `${triggerRect.bottom + gap}px`,
                    bottom: opensUp ? `${viewportHeight - triggerRect.top + gap}px` : 'auto',
                    width: `${panelWidth}px`,
                    maxHeight: `${maxHeight}px`,
                };
            });
        },
    }"
    @keydown.escape.window="open && closeMenu()"
    @resize.window="open && positionMenu()"
    @scroll.window="open && positionMenu()"
    class="relative"
>
    <button @click="toggleMenu()"
            x-ref="trigger"
            type="button"
            aria-label="Görünür tablo kolonlarını düzenle"
            :aria-expanded="open"
            aria-controls="mp-products-column-menu"
            class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-[6px] border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
        <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7" />
        </svg>
        Kolonlar
        <span class="rounded-[6px] bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-500">{{ count($visibleColumns) }}/{{ count($columnDefs) }}</span>
    </button>

    <div x-show="open"
         x-ref="panel"
         :style="panelStyle"
         @click.outside="closeMenu(false)"
         x-transition
         x-cloak
         id="mp-products-column-menu"
         data-testid="mp-products-column-menu"
         role="dialog"
         aria-label="Tablo görünümü"
         class="fixed z-[120] overflow-y-auto overscroll-contain rounded-[8px] border border-slate-200 bg-white shadow-xl">
        <div class="sticky top-0 z-10 border-b border-slate-200 bg-slate-50/95 p-2">
            <div class="flex items-center justify-between gap-2">
                <p class="text-[11px] font-semibold text-slate-700">Kolon görünümü</p>
                <span class="shrink-0 rounded-[5px] border border-slate-200 bg-white px-1.5 py-0.5 text-[10px] font-semibold text-slate-500">
                    {{ count($visibleColumns) }}/{{ count($columnDefs) }}
                </span>
            </div>

            <div class="mt-2 grid grid-cols-2 gap-1.5">
                <button type="button"
                        wire:click="useRecommendedColumns"
                        @click="closeMenu(false)"
                        class="inline-flex min-h-8 items-center justify-center rounded-[5px] border px-2 py-1.5 text-[11px] font-medium transition {{ $isRecommendedView ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50' }}">
                    Önerilen
                </button>
                <button type="button"
                        wire:click="showAllColumns"
                        @click="closeMenu(false)"
                        class="inline-flex min-h-8 items-center justify-center rounded-[5px] border border-slate-200 bg-white px-2 py-1.5 text-[11px] font-medium text-slate-700 transition hover:bg-slate-50">
                    Tümü
                </button>
            </div>
        </div>

        <div class="space-y-0.5 p-2">
            @foreach($columnDefs as $colKey => $colLabel)
                @php($isRequired = in_array($colKey, $requiredColumns, true))
                <label class="flex items-center gap-2 rounded-[5px] px-2 py-1.5 text-xs transition {{ $isRequired ? 'cursor-not-allowed bg-slate-50/80 text-slate-500' : 'cursor-pointer text-slate-700 hover:bg-slate-50' }}">
                    <input type="checkbox"
                           wire:click="toggleColumn('{{ $colKey }}')"
                           {{ in_array($colKey, $visibleColumns, true) ? 'checked' : '' }}
                           {{ $isRequired ? 'disabled' : '' }}
                           class="rounded border-slate-300 text-slate-900 shadow-sm focus:ring-indigo-200">
                    <span class="min-w-0 flex-1">{{ $colLabel }}</span>
                    @if($isRequired)
                        <span class="text-[10px] font-medium uppercase tracking-[0.12em] text-slate-400">Sabit</span>
                    @endif
                </label>
            @endforeach
        </div>
    </div>
</div>
