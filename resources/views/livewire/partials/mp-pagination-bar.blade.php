{{-- Pagination bar with page size selector --}}
{{-- Required: $paginator (the paginated collection), $perPageOptions (optional, defaults below) --}}
@php
    $perPageOptions = $perPageOptions ?? [10, 25, 50, 100];
@endphp

<div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div class="flex w-full items-center gap-2 sm:w-auto">
        <label class="text-xs sm:text-sm text-slate-500">Sayfa boyutu</label>
        <select wire:model.live="perPage"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:w-auto">
            @foreach($perPageOptions as $option)
                <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
        </select>
    </div>

    <div class="w-full overflow-x-auto pb-1 sm:w-auto">
        {{ $paginator->links() }}
    </div>
</div>
