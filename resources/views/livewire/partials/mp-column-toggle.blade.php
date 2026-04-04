{{-- Column visibility toggle dropdown --}}
{{-- Required: $columnDefs, $visibleColumns --}}
<div x-data="{ open: false }" class="relative">
    <button @click="open = !open"
            type="button"
            class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-[6px] border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
        <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7" />
        </svg>
        Kolonlar
        <span class="rounded-[6px] bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-500">{{ count($visibleColumns) }}/{{ count($columnDefs) }}</span>
    </button>

    <div x-show="open"
         @click.outside="open = false"
         x-transition
         class="absolute right-0 top-full z-30 mt-2 w-60 rounded-[8px] border border-slate-200 bg-white p-3 shadow-xl">
        <p class="text-xs font-medium uppercase tracking-[0.16em] text-slate-500">Görünür kolonlar</p>
        <div class="mt-3 space-y-1.5">
            @foreach($columnDefs as $colKey => $colLabel)
                <label class="flex items-center gap-2 rounded-[6px] px-2 py-2 text-sm text-slate-700 transition hover:bg-slate-50">
                    <input type="checkbox"
                           wire:click="toggleColumn('{{ $colKey }}')"
                           {{ in_array($colKey, $visibleColumns, true) ? 'checked' : '' }}
                           class="rounded border-slate-300 text-slate-900 shadow-sm focus:ring-indigo-200">
                    <span>{{ $colLabel }}</span>
                </label>
            @endforeach
        </div>
    </div>
</div>
