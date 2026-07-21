<div class="space-y-4 lg:space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
        <div>
            <h1 class="text-xl lg:text-2xl font-semibold text-slate-900">İzin Talepleri</h1>
            <p class="mt-1 text-sm text-slate-500">Çalışan izin talepleri, onay durumları ve tarih çakışmaları.</p>
        </div>
        <div class="flex flex-col sm:flex-row gap-2">@if(auth()->user()?->hasHrPermission('hr.leaves.export'))<a href="{{ route('hr.leaves.export') }}" class="w-full sm:w-auto px-4 py-3 sm:py-2 rounded-[6px] border border-slate-200 bg-white text-slate-700 text-sm font-medium text-center">Excel dışa aktar</a>@endif<a href="{{ route('hr.leaves.create') }}" class="w-full sm:w-auto px-4 py-3 sm:py-2 rounded-[6px] bg-slate-900 text-white text-sm font-medium text-center">+ Yeni İzin Talebi</a></div>
    </div>

    @if(session('success'))
        <div class="rounded-[8px] border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="p-4 lg:p-6 border-b border-slate-200 bg-slate-50/60">
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 lg:gap-4">
                <input wire:model.live.debounce.300ms="search" class="text-base sm:text-sm rounded-[6px] border border-slate-200 px-3 py-3 sm:py-2" placeholder="Çalışan veya sicil ara">
                <select wire:model.live="leaveTypeFilter" class="text-base sm:text-sm rounded-[6px] border border-slate-200 px-3 py-3 sm:py-2"><option value="">Tüm izin türleri</option>@foreach($leaveTypes as $type)<option value="{{ $type->id }}">{{ $type->name }}</option>@endforeach</select>
                <select wire:model.live="statusFilter" class="text-base sm:text-sm rounded-[6px] border border-slate-200 px-3 py-3 sm:py-2"><option value="">Tüm durumlar</option><option value="pending_manager">Yönetici bekliyor</option><option value="pending_hr">İK bekliyor</option><option value="approved">Onaylandı</option><option value="rejected">Reddedildi</option><option value="cancelled">İptal</option></select>
                <button wire:click="resetFilters" class="px-4 py-3 sm:py-2 rounded-[6px] border border-slate-200 bg-white text-sm text-slate-700">Temizle</button>
            </div>
        </div>

        <div class="hidden md:block overflow-x-auto">
            <table class="w-full table-fixed">
                <thead class="bg-slate-50/60 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Çalışan</th><th class="px-4 py-3">İzin türü</th><th class="px-4 py-3">Tarih</th><th class="px-4 py-3">Süre</th><th class="px-4 py-3">Durum</th><th class="px-4 py-3 text-right">İşlem</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($requests as $request)
                        <tr class="hover:bg-slate-50/60">
                            <td class="px-4 py-3 text-sm font-medium text-slate-900 truncate">{{ $request->employee?->full_name }} <span class="text-slate-400">{{ $request->employee?->employee_number }}</span></td>
                            <td class="px-4 py-3 text-sm text-slate-600 truncate">{{ $request->leaveType?->name }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $request->start_date->format('d.m.Y') }} — {{ $request->end_date->format('d.m.Y') }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $request->requested_amount }} {{ $request->unit->label() }}</td>
                            <td class="px-4 py-3"><span class="px-2 py-0.5 text-xs font-mono rounded bg-slate-100 text-slate-700">{{ $request->status->label() }}</span></td>
                            <td class="px-4 py-3 text-right">@if(in_array($request->status->value, ['pending_manager', 'pending_hr', 'approved'], true))<button wire:click="startCancel({{ $request->id }})" class="px-3 py-2 rounded-[6px] text-xs text-red-700 hover:bg-red-50">İptal et</button>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">İzin talebi bulunmuyor.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="md:hidden divide-y divide-slate-100">
            @forelse($requests as $request)
                <article class="p-4">
                    <div class="flex items-start justify-between gap-3"><div class="min-w-0"><p class="text-sm font-medium text-slate-900 truncate">{{ $request->employee?->full_name }}</p><p class="mt-1 text-xs text-slate-500">{{ $request->leaveType?->name }} · {{ $request->start_date->format('d.m.Y') }} — {{ $request->end_date->format('d.m.Y') }}</p></div><span class="shrink-0 px-2 py-0.5 text-xs font-mono rounded bg-slate-100 text-slate-700">{{ $request->status->label() }}</span></div>
                    @if(in_array($request->status->value, ['pending_manager', 'pending_hr', 'approved'], true))<button wire:click="startCancel({{ $request->id }})" class="mt-3 w-full px-4 py-3 rounded-[6px] border border-red-200 text-sm text-red-700">İptal et</button>@endif
                </article>
            @empty
                <div class="p-8 text-center text-sm text-slate-500">İzin talebi bulunmuyor.</div>
            @endforelse
        </div>
        <div class="p-4 border-t border-slate-200">{{ $requests->links() }}</div>
    </section>

    @if($cancellingId)
        <div class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-slate-900/40 p-4" role="dialog" aria-modal="true">
            <form wire:submit="cancel" class="w-full max-w-md rounded-[10px] border border-slate-200 bg-white p-4 lg:p-6 shadow-md">
                <h2 class="text-lg font-semibold text-slate-900">İzin talebini iptal et</h2>
                <p class="mt-1 text-sm text-slate-500">Bu işlem onaylanmış ücretli izinlerde bakiyeyi otomatik iade eder.</p>
                <textarea wire:model.defer="cancellationReason" rows="3" class="mt-4 w-full text-base sm:text-sm rounded-[6px] border border-slate-200 px-3 py-3 sm:py-2" placeholder="İptal gerekçesi"></textarea>
                @error('cancellationReason')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                <div class="mt-4 flex flex-col-reverse sm:flex-row sm:justify-end gap-2"><button type="button" wire:click="$set('cancellingId', null)" class="w-full sm:w-auto px-4 py-3 sm:py-2 rounded-[6px] border border-slate-200 text-sm text-slate-700">Vazgeç</button><button class="w-full sm:w-auto px-4 py-3 sm:py-2 rounded-[6px] bg-red-700 text-white text-sm">İptal talebini onayla</button></div>
            </form>
        </div>
    @endif
</div>
