<div class="space-y-4 lg:space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold text-slate-900 lg:text-2xl">Kadro ve Bütçe Planlama</h1>
            <p class="mt-1 text-sm text-slate-500">Planlanan kadroyu dolu FTE ve kaynak izli aylık maliyet anlık görüntüsüyle karşılaştırın.</p>
        </div>
        <a href="{{ route('hr.analytics') }}" class="w-full rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-center text-sm text-slate-700 sm:w-auto sm:py-2">
            İK analitiğine dön
        </a>
    </div>

    @if(session('success'))
        <div class="rounded-[8px] border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="rounded-[8px] border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
    @endif

    @if(auth()->user()?->hasHrPermission('hr.workforce.manage'))
        <form wire:submit="create" class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
            <div class="mb-3">
                <h2 class="text-sm font-semibold text-slate-900">Yeni plan</h2>
                <p class="text-xs text-slate-500">Dönem bütçesini ve raporlama para birimini belirleyin.</p>
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <input wire:model.defer="name" class="rounded-[6px] border border-slate-200 px-3 py-3 text-base sm:py-2 sm:text-sm" placeholder="Plan adı">
                <input type="date" wire:model.defer="startsOn" class="rounded-[6px] border border-slate-200 px-3 py-3 text-base sm:py-2 sm:text-sm">
                <input type="date" wire:model.defer="endsOn" class="rounded-[6px] border border-slate-200 px-3 py-3 text-base sm:py-2 sm:text-sm">
                <input type="number" step="0.01" wire:model.defer="budget" class="rounded-[6px] border border-slate-200 px-3 py-3 text-base sm:py-2 sm:text-sm" placeholder="Toplam bütçe">
                <select wire:model.defer="currency" class="rounded-[6px] border border-slate-200 px-3 py-3 text-base sm:py-2 sm:text-sm">
                    <option>TRY</option><option>EUR</option><option>USD</option><option>GBP</option>
                </select>
                <button class="w-full rounded-[6px] bg-slate-900 px-4 py-3 text-white sm:w-auto sm:py-2" wire:loading.attr="disabled" wire:target="create">
                    <span wire:loading.remove wire:target="create">Plan oluştur</span><span wire:loading wire:target="create">Oluşturuluyor…</span>
                </button>
            </div>
        </form>
    @endif

    <div class="grid grid-cols-1 gap-3 lg:gap-4 xl:grid-cols-4">
        <aside class="overflow-hidden rounded-[10px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 bg-slate-50/60 p-4 text-sm font-semibold">Planlar</div>
            @forelse($plans as $plan)
                <button wire:click="select({{ $plan->id }})" class="w-full border-b border-slate-100 p-4 text-left hover:bg-slate-50/60 {{ $selectedPlanId === $plan->id ? 'bg-slate-50' : '' }}">
                    <span class="block truncate text-sm font-medium text-slate-900">{{ $plan->name }}</span>
                    <span class="mt-1 block text-xs text-slate-500">{{ $plan->lines_count }} satır · {{ $plan->status }}</span>
                </button>
            @empty
                <p class="p-6 text-sm text-slate-500">Plan yok.</p>
            @endforelse
        </aside>

        <section class="overflow-hidden rounded-[10px] border border-slate-200 bg-white shadow-sm xl:col-span-3">
            @if($selected)
                <div class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50/60 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <h2 class="truncate text-sm font-semibold text-slate-900">{{ $selected->name }}</h2>
                        <p class="text-xs text-slate-500">Bütçe {{ number_format($selected->budget(), 2, ',', '.') }} {{ $selected->currency }} · {{ $selected->status }}</p>
                    </div>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        @if($selected->status === 'draft' && auth()->user()?->hasHrPermission('hr.workforce.manage'))
                            <button wire:click="submit" wire:confirm="Kadro planı onaya gönderilsin mi?" class="w-full rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm sm:w-auto sm:py-2">Onaya gönder</button>
                        @elseif($selected->status === 'pending_approval' && $selected->created_by !== auth()->id() && auth()->user()?->hasHrPermission('hr.workforce.approve'))
                            <button wire:click="approve" wire:confirm="Gerçekleşen FTE ve maliyet anlık görüntüsü alınarak plan onaylansın mı?" class="w-full rounded-[6px] bg-slate-900 px-4 py-3 text-sm text-white sm:w-auto sm:py-2">Onayla</button>
                        @endif
                    </div>
                </div>

                @if($selected->status === 'draft' && auth()->user()?->hasHrPermission('hr.workforce.manage'))
                    <form wire:submit="addLine" class="grid grid-cols-1 gap-3 border-b border-slate-200 p-4 sm:grid-cols-2 xl:grid-cols-3">
                        <select wire:model.defer="departmentId" class="rounded-[6px] border border-slate-200 px-3 py-3 text-base sm:py-2 sm:text-sm">
                            <option value="">Departman</option>
                            @foreach($departments as $department)<option value="{{ $department->id }}">{{ $department->name }}</option>@endforeach
                        </select>
                        <select wire:model.defer="positionId" class="rounded-[6px] border border-slate-200 px-3 py-3 text-base sm:py-2 sm:text-sm">
                            <option value="">Pozisyon</option>
                            @foreach($positions as $position)<option value="{{ $position->id }}">{{ $position->title }}</option>@endforeach
                        </select>
                        <input type="number" step="0.01" wire:model.defer="plannedFte" class="rounded-[6px] border border-slate-200 px-3 py-3 text-base sm:py-2 sm:text-sm" placeholder="Plan FTE">
                        <input type="number" step="0.01" wire:model.defer="plannedCost" class="rounded-[6px] border border-slate-200 px-3 py-3 text-base sm:py-2 sm:text-sm" placeholder="Aylık maliyet">
                        <input wire:model.defer="notes" class="rounded-[6px] border border-slate-200 px-3 py-3 text-base sm:py-2 sm:text-sm" placeholder="Not">
                        <button class="w-full rounded-[6px] bg-slate-900 px-4 py-3 text-white sm:w-auto sm:py-2" wire:loading.attr="disabled" wire:target="addLine">Satır kaydet</button>
                    </form>
                @endif

                <div class="relative flex items-center justify-between gap-3 border-b border-slate-200 p-3" x-data="{ columns: false }">
                    <p class="text-xs text-slate-500">{{ $lines->count() }} kadro satırı</p>
                    <button @click="columns = !columns" class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-xs">Kolonlar · {{ count($visibleColumns) }}</button>
                    <div x-show="columns" x-cloak @click.outside="columns = false" class="absolute right-3 top-12 z-20 w-48 rounded-[8px] border border-slate-200 bg-white p-3 shadow-md">
                        @foreach($columnLabels as $key => $label)
                            <label class="flex items-center gap-2 py-1 text-xs">
                                <input type="checkbox" wire:click="toggleColumn('{{ $key }}')" @checked(in_array($key, $visibleColumns, true)) @disabled(in_array($key, ['department', 'position'], true))>
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="hidden overflow-x-auto rounded-lg md:block" x-data="columnResize()">
                    <table class="w-full table-fixed" style="min-width: 820px">
                        <thead class="bg-slate-50/60 text-left text-xs uppercase text-slate-500">
                            <tr>
                                @foreach($columnLabels as $key => $label)
                                    @if(in_array($key, $visibleColumns, true))
                                        <th class="relative px-4 py-3" style="width: {{ in_array($key, ['department', 'position'], true) ? '170px' : '120px' }}">
                                            @if(isset($sortableColumns[$key]))
                                                <button wire:click="sortTable('{{ $key }}')" class="flex items-center gap-1">
                                                    {{ $label }}
                                                    @if($sortField === $sortableColumns[$key])<span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                                                </button>
                                            @else
                                                {{ $label }}
                                            @endif
                                            <span class="col-resize-handle" @mousedown.prevent="startResize($event, $el.parentElement)"></span>
                                        </th>
                                    @endif
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($lines as $line)
                                <tr class="hover:bg-slate-50/60">
                                    @if(in_array('department', $visibleColumns, true))<td class="overflow-hidden text-ellipsis px-4 py-3 text-sm">{{ $line->department?->name }}</td>@endif
                                    @if(in_array('position', $visibleColumns, true))<td class="overflow-hidden text-ellipsis px-4 py-3 text-sm">{{ $line->position?->title }}</td>@endif
                                    @if(in_array('planned_fte', $visibleColumns, true))<td class="px-4 py-3 text-sm">{{ $line->planned_fte }}</td>@endif
                                    @if(in_array('actual_fte', $visibleColumns, true))<td class="px-4 py-3 text-sm">{{ $line->actual_fte_snapshot ?? '—' }}</td>@endif
                                    @if(in_array('planned_cost', $visibleColumns, true))<td class="px-4 py-3 text-sm">{{ number_format($line->plannedCost(), 2, ',', '.') }}</td>@endif
                                    @if(in_array('actual_cost', $visibleColumns, true))<td class="overflow-hidden text-ellipsis px-4 py-3 text-sm">{{ auth()->user()?->hasHrPermission('hr.salary.view') && $line->actualCost() !== null ? number_format($line->actualCost(), 2, ',', '.') : 'Yetki gerekli' }}</td>@endif
                                    @if(in_array('gap', $visibleColumns, true))<td class="px-4 py-3 text-sm">{{ $line->actual_fte_snapshot === null ? '—' : number_format((float) $line->planned_fte - (float) $line->actual_fte_snapshot, 2, ',', '.') }}</td>@endif
                                </tr>
                            @empty
                                <tr><td colspan="{{ count($visibleColumns) }}" class="p-10 text-center text-sm text-slate-500">Kadro satırı yok.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="divide-y divide-slate-100 md:hidden">
                    @forelse($lines as $line)
                        <article class="p-4">
                            <p class="truncate text-sm font-medium text-slate-900">{{ $line->department?->name }} · {{ $line->position?->title }}</p>
                            <div class="mt-2 grid grid-cols-2 gap-2 text-xs text-slate-500">
                                <span>Plan {{ $line->planned_fte }} FTE</span><span>Dolu {{ $line->actual_fte_snapshot ?? '—' }}</span>
                                <span class="col-span-2">Plan maliyet {{ number_format($line->plannedCost(), 2, ',', '.') }} {{ $selected->currency }}</span>
                            </div>
                        </article>
                    @empty
                        <p class="p-8 text-center text-sm text-slate-500">Kadro satırı yok.</p>
                    @endforelse
                </div>
            @else
                <p class="p-10 text-center text-sm text-slate-500">Bir plan seçin.</p>
            @endif
        </section>
    </div>
</div>
