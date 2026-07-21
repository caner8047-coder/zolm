<div class="space-y-4 lg:space-y-6">
    <div>
        <h1 class="text-xl font-semibold text-slate-900 lg:text-2xl">Çalışan Destek Merkezi</h1>
        <p class="mt-1 text-sm text-slate-500">İK, bordro, izin, PDKS ve teknik talepleri konuşma geçmişiyle izleyin.</p>
    </div>

    @if(session('success'))<div class="rounded-[8px] border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="rounded-[8px] border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>@endif

    @if(auth()->user()?->hasHrPermission('hr.support.create'))
        <form wire:submit="create" class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
            <div class="mb-3"><h2 class="text-sm font-semibold text-slate-900">Yeni destek talebi</h2><p class="text-xs text-slate-500">Açıklama ve yazışmalar şifreli saklanır.</p></div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <select wire:model.defer="category" class="rounded-[6px] border border-slate-200 px-3 py-3 text-base sm:py-2 sm:text-sm"><option value="hr">İK</option><option value="payroll">Bordro</option><option value="leave">İzin</option><option value="attendance">PDKS</option><option value="technical">Teknik</option><option value="other">Diğer</option></select>
                <select wire:model.defer="priority" class="rounded-[6px] border border-slate-200 px-3 py-3 text-base sm:py-2 sm:text-sm"><option value="low">Düşük</option><option value="normal">Normal</option><option value="high">Yüksek</option><option value="urgent">Acil</option></select>
                <input wire:model.defer="subject" class="rounded-[6px] border border-slate-200 px-3 py-3 text-base sm:py-2 sm:text-sm" placeholder="Konu">
                <textarea wire:model.defer="description" rows="3" class="rounded-[6px] border border-slate-200 px-3 py-3 text-base sm:col-span-2 sm:text-sm xl:col-span-2" placeholder="Talebinizi açıklayın"></textarea>
                <button class="w-full rounded-[6px] bg-slate-900 px-4 py-3 text-white sm:w-auto sm:py-2" wire:loading.attr="disabled" wire:target="create">Talep oluştur</button>
            </div>
        </form>
    @endif

    <section class="overflow-hidden rounded-[10px] border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50/60 p-4 sm:flex-row sm:items-center sm:justify-between">
            <div><h2 class="text-sm font-semibold text-slate-900">Talep defteri</h2><p class="text-xs text-slate-500">{{ $tickets->count() }} kayıt gösteriliyor</p></div>
            <div class="flex flex-col gap-2 sm:flex-row">
                <input wire:model.live.debounce.300ms="search" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base sm:w-56 sm:py-2 sm:text-sm" placeholder="Talep no veya konu ara">
                <select wire:model.live="statusFilter" class="rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base sm:py-2 sm:text-sm"><option value="">Tüm durumlar</option><option value="open">Açık</option><option value="in_progress">İşlemde</option><option value="resolved">Çözüldü</option><option value="closed">Kapalı</option></select>
                <div class="relative" x-data="{ open: false }"><button @click="open = !open" type="button" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-sm sm:py-2">Kolonlar · {{ count($visibleColumns) }}</button><div x-show="open" x-cloak @click.outside="open = false" class="absolute right-0 z-20 mt-2 w-48 rounded-[8px] border border-slate-200 bg-white p-3 shadow-md">@foreach($columnLabels as $key => $label)<label class="flex items-center gap-2 py-1 text-xs"><input type="checkbox" wire:click="toggleColumn('{{ $key }}')" @checked(in_array($key, $visibleColumns, true)) @disabled(in_array($key, ['number', 'subject'], true))>{{ $label }}</label>@endforeach</div></div>
            </div>
        </div>

        <div class="hidden overflow-x-auto rounded-lg md:block" x-data="columnResize()">
            <table class="w-full table-fixed" style="min-width: 820px">
                <thead class="bg-slate-50/60 text-left text-xs uppercase text-slate-500"><tr>@foreach($columnLabels as $key => $label)@if(in_array($key, $visibleColumns, true))<th class="relative px-4 py-3" style="width: {{ in_array($key, ['subject', 'requester'], true) ? '190px' : '125px' }}">@if(isset($sortableColumns[$key]))<button wire:click="sortTable('{{ $key }}')" class="flex items-center gap-1">{{ $label }}@if($sortField === $sortableColumns[$key])<span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif</button>@else{{ $label }}@endif<span class="col-resize-handle" @mousedown.prevent="startResize($event, $el.parentElement)"></span></th>@endif @endforeach</tr></thead>
                <tbody class="divide-y divide-slate-100">@forelse($tickets as $ticket)<tr wire:click="select({{ $ticket->id }})" class="cursor-pointer hover:bg-slate-50/60 {{ $selectedTicketId === $ticket->id ? 'bg-slate-50' : '' }}">@if(in_array('number', $visibleColumns, true))<td class="overflow-hidden text-ellipsis px-4 py-3 text-xs font-mono">{{ $ticket->ticket_number }}</td>@endif @if(in_array('subject', $visibleColumns, true))<td class="overflow-hidden text-ellipsis px-4 py-3 text-sm font-medium">{{ $ticket->subject }}</td>@endif @if(in_array('requester', $visibleColumns, true))<td class="overflow-hidden text-ellipsis px-4 py-3 text-sm text-slate-600">{{ $ticket->requester?->full_name ?? $ticket->requesterUser?->name ?? '—' }}</td>@endif @if(in_array('priority', $visibleColumns, true))<td class="px-4 py-3"><span class="rounded px-2 py-0.5 text-xs font-mono {{ $ticket->priority === 'urgent' ? 'bg-red-50 text-red-700' : ($ticket->priority === 'high' ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-700') }}">{{ $ticket->priority }}</span></td>@endif @if(in_array('status', $visibleColumns, true))<td class="px-4 py-3 text-xs text-slate-600">{{ $ticket->status }}</td>@endif @if(in_array('updated', $visibleColumns, true))<td class="px-4 py-3 text-xs text-slate-500">{{ $ticket->updated_at->format('d.m.Y H:i') }}</td>@endif</tr>@empty<tr><td colspan="{{ count($visibleColumns) }}" class="p-10 text-center text-sm text-slate-500">Destek talebi bulunamadı.</td></tr>@endforelse</tbody>
            </table>
        </div>

        <div class="divide-y divide-slate-100 md:hidden">@forelse($tickets as $ticket)<button wire:click="select({{ $ticket->id }})" class="w-full p-4 text-left"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><p class="truncate text-sm font-medium">{{ $ticket->subject }}</p><p class="mt-1 text-xs font-mono text-slate-500">{{ $ticket->ticket_number }}</p></div><span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-mono">{{ $ticket->status }}</span></div></button>@empty<p class="p-8 text-center text-sm text-slate-500">Destek talebi bulunamadı.</p>@endforelse</div>
    </section>

    @if($selected)
        <section class="overflow-hidden rounded-[10px] border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50/60 p-4 sm:flex-row sm:items-center sm:justify-between"><div class="min-w-0"><h2 class="truncate text-sm font-semibold">{{ $selected->ticket_number }} · {{ $selected->subject }}</h2><p class="mt-1 text-xs text-slate-500">{{ $selected->category }} · {{ $selected->priority }} · {{ $selected->status }}</p></div>@if(auth()->user()?->hasHrPermission('hr.support.manage'))<div class="flex flex-wrap gap-2">@if(!$selected->assigned_to && $selected->status !== 'closed')<button wire:click="assignToSelf" class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-xs">Üzerime al</button>@endif @if(in_array($selected->status, ['open', 'in_progress'], true))<button wire:click="changeStatus('resolved')" class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-xs">Çözüldü</button>@endif @if($selected->status !== 'closed')<button wire:click="changeStatus('closed')" class="rounded-[6px] bg-slate-900 px-3 py-2 text-xs text-white">Kapat</button>@else<button wire:click="changeStatus('open')" class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-xs">Yeniden aç</button>@endif</div>@endif</div>
            <div class="border-b border-slate-200 p-4"><p class="whitespace-pre-wrap text-sm text-slate-700">{{ $selected->description() }}</p></div>
            <div class="divide-y divide-slate-100">@forelse($messages as $row)<article class="p-4 {{ $row->is_internal ? 'bg-amber-50/60' : '' }}"><div class="flex items-center justify-between gap-3"><p class="text-xs font-medium text-slate-700">{{ $row->author?->name ?? 'Sistem' }} @if($row->is_internal)<span class="ml-1 text-amber-700">İç not</span>@endif</p><time class="text-xs text-slate-400">{{ $row->created_at->format('d.m.Y H:i') }}</time></div><p class="mt-2 whitespace-pre-wrap text-sm text-slate-700">{{ $row->body() }}</p></article>@empty<p class="p-6 text-sm text-slate-500">Henüz yanıt yok.</p>@endforelse</div>
            @if($selected->status !== 'closed')<form wire:submit="addMessage" class="border-t border-slate-200 p-4"><textarea wire:model.defer="message" rows="3" class="w-full rounded-[6px] border border-slate-200 px-3 py-3 text-base sm:text-sm" placeholder="Yanıtınızı yazın"></textarea><div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">@if(auth()->user()?->hasHrPermission('hr.support.manage'))<label class="flex items-center gap-2 text-xs text-slate-600"><input type="checkbox" wire:model.defer="internalMessage"> Yalnız destek ekibi iç notu</label>@else<span></span>@endif<button class="w-full rounded-[6px] bg-slate-900 px-4 py-3 text-sm text-white sm:w-auto sm:py-2">Mesaj gönder</button></div></form>@endif
        </section>
    @endif
</div>
