<div class="space-y-4 lg:space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
        <div><h1 class="text-xl lg:text-2xl font-semibold text-slate-900">Vardiya Planlama</h1><p class="mt-1 text-sm text-slate-500">Haftalık ekip planı, yayın durumu ve izin çakışma kontrolleri.</p></div>
        @if(auth()->user()?->hasHrPermission('hr.shifts.manage'))<a href="{{ route('hr.settings.shift-templates') }}" class="w-full sm:w-auto px-4 py-3 sm:py-2 rounded-[6px] border border-slate-200 text-center text-sm text-slate-700">Vardiya Şablonları</a>@endif
    </div>
    @if(session('success'))<div class="rounded-[8px] border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif

    @if(auth()->user()?->hasHrPermission('hr.shifts.assign'))
        <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm p-4 lg:p-6">
            <h2 class="text-sm font-semibold text-slate-900">Vardiya ata</h2>
            <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 lg:gap-4">
                <select wire:model.defer="employeeId" class="text-base sm:text-sm rounded-[6px] border border-slate-200 px-3 py-3 sm:py-2"><option value="">Tek çalışan seçin</option>@foreach($employees as $employee)<option value="{{ $employee->id }}">{{ $employee->full_name }}</option>@endforeach</select>
                <select wire:model.defer="templateId" class="text-base sm:text-sm rounded-[6px] border border-slate-200 px-3 py-3 sm:py-2"><option value="">Şablon seçin</option>@foreach($templates as $template)<option value="{{ $template->id }}">{{ $template->name }} · {{ substr($template->starts_at,0,5) }}</option>@endforeach</select>
                <input type="date" wire:model.defer="shiftDate" class="text-base sm:text-sm rounded-[6px] border border-slate-200 px-3 py-3 sm:py-2">
                <input wire:model.defer="note" class="text-base sm:text-sm rounded-[6px] border border-slate-200 px-3 py-3 sm:py-2" placeholder="Not (isteğe bağlı)">
            </div>
            <div class="mt-3 flex flex-col sm:flex-row gap-2"><button wire:click="assign" class="w-full sm:w-auto px-4 py-3 sm:py-2 rounded-[6px] bg-slate-900 text-white text-sm">Tekli Atamayı Kaydet</button><button type="button" wire:click="$set('employeeId', null)" class="w-full sm:w-auto px-4 py-3 sm:py-2 rounded-[6px] border border-slate-200 text-sm">Tekli seçimi temizle</button></div>
            <div class="mt-4 border-t border-slate-200 pt-4"><label><span class="text-sm font-medium text-slate-700">Toplu çalışan seçimi</span><select multiple size="5" wire:model.defer="selectedEmployeeIds" class="mt-1 w-full text-base sm:text-sm rounded-[6px] border border-slate-200 px-3 py-2">@foreach($employees as $employee)<option value="{{ $employee->id }}">{{ $employee->full_name }}</option>@endforeach</select><span class="mt-1 block text-xs text-slate-500">Masaüstünde birden fazla seçim için Ctrl/Cmd tuşunu kullanın.</span></label><button wire:click="bulkAssign" class="mt-3 w-full sm:w-auto px-4 py-3 sm:py-2 rounded-[6px] border border-slate-900 bg-white text-slate-900 text-sm">Seçili Çalışanlara Ata</button></div>
        </section>
    @endif

    <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3 p-4 border-b border-slate-200 bg-slate-50/60">
            <div><h2 class="text-sm font-semibold text-slate-900">{{ \Carbon\Carbon::parse($weekStart)->format('d.m.Y') }} haftası</h2><p class="mt-1 text-xs text-slate-500">Taslak ve yayımlanmış vardiyalar</p></div>
            <div class="flex flex-col sm:flex-row gap-2">
                @if(auth()->user()?->hasHrPermission('hr.shifts.plan'))<button wire:click="publishWeek" class="w-full sm:w-auto px-4 py-3 sm:py-2 rounded-[6px] bg-slate-900 text-white text-sm">Haftayı Yayımla</button>@endif
                <div class="grid grid-cols-3 gap-2"><button wire:click="previousWeek" class="px-3 py-3 sm:py-2 rounded-[6px] border border-slate-200 bg-white text-sm">←</button><button wire:click="thisWeek" class="px-3 py-3 sm:py-2 rounded-[6px] border border-slate-200 bg-white text-sm">Bu hafta</button><button wire:click="nextWeek" class="px-3 py-3 sm:py-2 rounded-[6px] border border-slate-200 bg-white text-sm">→</button></div>
            </div>
        </div>
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full table-fixed"><thead class="bg-slate-50/60 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-3 w-48">Çalışan</th>@foreach($days as $day)<th class="px-3 py-3">{{ $day->translatedFormat('D') }}<br><span class="font-normal">{{ $day->format('d.m') }}</span></th>@endforeach</tr></thead><tbody class="divide-y divide-slate-100">
                @forelse($employees as $employee)<tr><td class="px-4 py-3 text-sm font-medium text-slate-900 truncate">{{ $employee->full_name }}</td>@foreach($days as $day)@php($assignment=$assignments->get($employee->id.'-'.$day->toDateString())?->first())<td class="px-2 py-3 align-top">@if($assignment)<div class="rounded-[6px] border px-2 py-2 text-xs {{ $assignment->status->value === 'cancelled' ? 'border-red-200 bg-red-50/60 opacity-70' : 'border-slate-200' }}" style="border-left:3px solid {{ $assignment->template?->color ?? '#334155' }}"><p class="font-medium text-slate-900 truncate">{{ $assignment->template?->name }}</p><p class="text-slate-500">{{ substr($assignment->template?->starts_at,0,5) }} · {{ $assignment->status->label() }}</p>@if($assignment->status->value !== 'cancelled' && auth()->user()?->hasHrPermission('hr.shifts.assign'))<button wire:click="startCancel({{ $assignment->id }})" class="mt-2 text-red-700">İptal et</button>@endif</div>@else<span class="text-xs text-slate-300">—</span>@endif</td>@endforeach</tr>@empty<tr><td colspan="8" class="p-8 text-center text-sm text-slate-500">Aktif çalışan bulunmuyor.</td></tr>@endforelse
            </tbody></table>
        </div>
        <div class="md:hidden divide-y divide-slate-100">
            @foreach($days as $day)<article class="p-4"><h3 class="text-sm font-semibold text-slate-900">{{ $day->translatedFormat('l, d F') }}</h3><div class="mt-3 space-y-2">@php($daily=$assignments->filter(fn($items,$key)=>str_ends_with($key,'-'.$day->toDateString())))@forelse($daily as $items)@php($assignment=$items->first())<div class="rounded-[6px] border p-3 {{ $assignment->status->value === 'cancelled' ? 'border-red-200 bg-red-50/60' : 'border-slate-200' }}"><div class="flex items-start justify-between gap-2"><div><p class="text-sm font-medium text-slate-900">{{ $assignment->employee?->full_name }}</p><p class="mt-1 text-xs text-slate-500">{{ $assignment->template?->name }} · {{ substr($assignment->template?->starts_at,0,5) }}–{{ substr($assignment->template?->ends_at,0,5) }}</p></div><span class="px-2 py-0.5 text-xs font-mono rounded bg-slate-100 text-slate-600">{{ $assignment->status->label() }}</span></div>@if($assignment->status->value !== 'cancelled' && auth()->user()?->hasHrPermission('hr.shifts.assign'))<button wire:click="startCancel({{ $assignment->id }})" class="mt-3 w-full px-4 py-3 rounded-[6px] border border-red-200 text-sm text-red-700">İptal et</button>@endif</div>@empty<p class="text-sm text-slate-500">Planlanmış vardiya yok.</p>@endforelse</div></article>@endforeach
        </div>
    </section>

    @if($cancellingId)
        <div class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-slate-900/40 p-4" role="dialog" aria-modal="true"><form wire:submit="cancel" class="w-full max-w-md rounded-[10px] border border-slate-200 bg-white p-4 lg:p-6 shadow-md"><h2 class="text-lg font-semibold text-slate-900">Vardiyayı iptal et</h2><p class="mt-1 text-sm text-slate-500">İptal kaydı ve gerekçesi işlem geçmişinde saklanır.</p><textarea wire:model.defer="cancellationReason" rows="3" class="mt-4 w-full text-base sm:text-sm rounded-[6px] border border-slate-200 px-3 py-3 sm:py-2" placeholder="İptal gerekçesi"></textarea>@error('cancellationReason')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror<div class="mt-4 flex flex-col-reverse sm:flex-row sm:justify-end gap-2"><button type="button" wire:click="$set('cancellingId', null)" class="w-full sm:w-auto px-4 py-3 sm:py-2 rounded-[6px] border border-slate-200 text-sm">Vazgeç</button><button class="w-full sm:w-auto px-4 py-3 sm:py-2 rounded-[6px] bg-red-700 text-white text-sm">İptali onayla</button></div></form></div>
    @endif
</div>
