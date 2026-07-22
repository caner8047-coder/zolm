@php
    $columnLabels = [
        'employee' => 'Çalışan', 'date' => 'Tarih', 'day_type' => 'Gün türü',
        'scheduled' => 'Plan', 'worked' => 'Çalışma', 'leave' => 'İzin',
        'overtime' => 'Fazla', 'missing' => 'Eksik', 'first_in' => 'İlk giriş',
        'last_out' => 'Son çıkış', 'anomalies' => 'Kontrol', 'status' => 'Durum', 'actions' => 'İşlem',
    ];
    $sortFields = [
        'date' => 'work_date', 'day_type' => 'day_type', 'scheduled' => 'scheduled_minutes',
        'worked' => 'worked_minutes', 'leave' => 'leave_minutes', 'overtime' => 'overtime_minutes',
        'missing' => 'missing_minutes', 'first_in' => 'first_in_at', 'last_out' => 'last_out_at',
        'anomalies' => 'anomaly_count', 'status' => 'status',
    ];
@endphp

<div class="space-y-4 lg:space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
        <div>
            <a href="{{ route('hr.timesheets') }}" class="text-xs text-slate-500">← Dönemler</a>
            <h1 class="mt-1 text-xl lg:text-2xl font-semibold text-slate-900">{{ $period->name }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $period->starts_on->format('d.m.Y') }} — {{ $period->ends_on->format('d.m.Y') }} · {{ $period->status->label() }}</p>
        </div>
        <div class="flex flex-col sm:flex-row gap-2">
            <a href="{{ route('hr.timesheets.export', $period->id) }}" class="w-full sm:w-auto px-4 py-3 sm:py-2 rounded-[6px] border border-slate-200 bg-white text-center text-sm text-slate-700">Excel</a>
            @if($period->status->value !== 'closed' && auth()->user()?->hasHrPermission('hr.timesheet.confirm'))
                <button wire:click="calculate" wire:loading.attr="disabled" class="w-full sm:w-auto px-4 py-3 sm:py-2 rounded-[6px] border border-slate-200 bg-white text-sm text-slate-700 disabled:opacity-50">Yeniden hesapla</button>
                <button wire:click="confirmAll" wire:confirm="Yalnızca anomalisi bulunmayan taslak satırlar onaylanacak. Devam edilsin mi?" class="w-full sm:w-auto px-4 py-3 sm:py-2 rounded-[6px] border border-slate-200 bg-white text-sm text-slate-700">Temizleri onayla</button>
            @endif
            @if($period->status->value === 'calculated' && auth()->user()?->hasHrPermission('hr.timesheet.close'))
                <button wire:click="close" wire:confirm="Dönem kapandıktan sonra yalnız revizyonla düzeltilebilir. Devam edilsin mi?" class="w-full sm:w-auto px-4 py-3 sm:py-2 rounded-[6px] bg-slate-900 text-sm font-medium text-white">Dönemi kapat</button>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-[8px] border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    @if($legacyRowCount > 0)
        <div class="rounded-[8px] border border-amber-200 bg-amber-50 px-4 py-3">
            <p class="text-sm font-medium text-amber-900">{{ $legacyRowCount }} satır eski hesap motoruyla üretilmiş</p>
            <p class="mt-0.5 text-xs text-amber-700">Açık dönemlerde “Yeniden hesapla” çalıştırılmadan onay ve kapanış yapılamaz. Kapanmış dönemler denetim izi korunarak değiştirilmez.</p>
        </div>
    @endif

    @if($openAnomalyCount > 0)
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 rounded-[8px] border border-red-200 bg-red-50 px-4 py-3">
            <div>
                <p class="text-sm font-medium text-red-900">{{ $openAnomalyCount }} açık puantaj kontrolü var</p>
                <p class="mt-0.5 text-xs text-red-700">Bu satırlar onaylanmaz ve açık anomaliler çözülmeden dönem kapatılamaz.</p>
            </div>
            <a href="{{ route('hr.attendance.anomalies') }}" class="w-full sm:w-auto px-4 py-3 sm:py-2 rounded-[6px] border border-red-200 bg-white text-center text-sm font-medium text-red-800">Anomalileri çöz</a>
        </div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-3 lg:gap-4">
        @foreach(['scheduled_minutes' => 'Planlanan', 'worked_minutes' => 'Çalışılan', 'leave_minutes' => 'Mahsup edilen izin', 'overtime_minutes' => 'Normal fazla', 'missing_minutes' => 'Eksik'] as $key => $label)
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <p class="text-xs text-slate-500">{{ $label }}</p>
                <p class="mt-1 text-lg font-semibold text-slate-900">{{ intdiv($totals[$key], 60) }}s {{ $totals[$key] % 60 }}dk</p>
            </div>
        @endforeach
    </div>

    @if($totals['holiday_work_minutes'] > 0 || $totals['weekly_rest_work_minutes'] > 0)
        <div class="flex flex-wrap gap-2 text-xs">
            <span class="px-2 py-1 rounded bg-violet-50 text-violet-700">Resmî tatil çalışması: {{ intdiv($totals['holiday_work_minutes'], 60) }}s {{ $totals['holiday_work_minutes'] % 60 }}dk</span>
            <span class="px-2 py-1 rounded bg-amber-50 text-amber-700">Hafta tatili çalışması: {{ intdiv($totals['weekly_rest_work_minutes'], 60) }}s {{ $totals['weekly_rest_work_minutes'] % 60 }}dk</span>
        </div>
    @endif

    <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="border-b border-slate-200 bg-slate-50/60 p-4 lg:p-6">
            <div class="mb-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">Günlük puantaj kontrolü</h2>
                    <p class="mt-1 text-xs text-slate-500">Aktif filtre: {{ $search ?: 'arama yok' }} · {{ $statusFilter ?: 'tüm durumlar' }} · {{ $dayTypeFilter ?: 'tüm günler' }} · {{ $anomalyFilter ?: 'tüm kontroller' }}</p>
                </div>
                <div class="flex flex-col sm:flex-row gap-2">
                    <div class="inline-flex rounded-[6px] border border-slate-200 bg-white p-1">
                        <button wire:click="setViewMode('ledger')" class="flex-1 sm:flex-none px-3 py-2 rounded-[4px] text-xs {{ $viewMode === 'ledger' ? 'bg-slate-900 text-white' : 'text-slate-600' }}">Ledger</button>
                        <button wire:click="setViewMode('matrix')" class="flex-1 sm:flex-none px-3 py-2 rounded-[4px] text-xs {{ $viewMode === 'matrix' ? 'bg-slate-900 text-white' : 'text-slate-600' }}">Aylık matris</button>
                    </div>
                    <div x-data="{open:false}" class="relative">
                        <button @click="open=!open" class="w-full sm:w-auto px-4 py-3 sm:py-2 rounded-[6px] border border-slate-200 bg-white text-sm">Kolonlar · {{ count($visibleColumns) }}</button>
                        <div x-show="open" x-cloak @click.outside="open=false" class="absolute right-0 z-20 mt-2 w-56 max-h-80 overflow-y-auto rounded-[8px] border border-slate-200 bg-white p-2 shadow-md">
                            @foreach($columnLabels as $key => $label)
                                <label class="flex items-center gap-2 px-2 py-2 text-sm"><input type="checkbox" wire:click="toggleColumn('{{ $key }}')" @checked(in_array($key, $visibleColumns, true))>{{ $label }}</label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
                <input wire:model.live.debounce.300ms="search" class="text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2 xl:col-span-2" placeholder="Çalışan veya sicil ara">
                <select wire:model.live="statusFilter" class="text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2"><option value="">Tüm durumlar</option><option value="draft">Taslak</option><option value="confirmed">Onaylandı</option><option value="closed">Kapandı</option></select>
                <select wire:model.live="dayTypeFilter" class="text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2"><option value="">Tüm gün türleri</option><option value="workday">İş günü</option><option value="weekly_rest">Hafta tatili</option><option value="official_holiday">Resmî tatil</option></select>
                <select wire:model.live="anomalyFilter" class="text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2"><option value="">Tüm kontroller</option><option value="with">Anomalili</option><option value="clean">Temiz</option></select>
                <select wire:model.live="branchFilter" class="text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2"><option value="">Tüm şubeler</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select>
                <select wire:model.live="departmentFilter" class="text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2"><option value="">Tüm departmanlar</option>@foreach($departments as $department)<option value="{{ $department->id }}">{{ $department->name }}</option>@endforeach</select>
                <select wire:model.live="positionFilter" class="text-base sm:text-sm rounded-[6px] border border-slate-200 bg-white px-3 py-3 sm:py-2"><option value="">Tüm pozisyonlar</option>@foreach($positions as $position)<option value="{{ $position->id }}">{{ $position->title }}</option>@endforeach</select>
            </div>
        </div>

        @if($viewMode === 'ledger')
        <div class="hidden md:block overflow-x-auto rounded-lg" x-data="columnResize()">
            <table class="w-full table-fixed">
                <thead class="bg-slate-50/60 text-left text-xs uppercase text-slate-500">
                    <tr>
                        @foreach($columnLabels as $key => $label)
                            @if(in_array($key, $visibleColumns, true))
                                <th class="px-3 py-3 {{ $key === 'actions' ? 'text-right' : '' }}">
                                    @if(isset($sortFields[$key]))
                                        <button wire:click="sortTable('{{ $sortFields[$key] }}')" class="inline-flex items-center gap-1 text-left uppercase hover:text-slate-900">{{ $label }} @if($sortField === $sortFields[$key])<span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif</button>
                                    @else
                                        {{ $label }}
                                    @endif
                                </th>
                            @endif
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($rows as $row)
                        <tr class="{{ $row->anomaly_count > 0 ? 'bg-red-50/40' : '' }}">
                            @if(in_array('employee', $visibleColumns, true))<td class="overflow-hidden text-ellipsis px-3 py-3 text-sm font-medium text-slate-900">{{ $row->employee?->full_name }}</td>@endif
                            @if(in_array('date', $visibleColumns, true))<td class="px-3 py-3 text-sm text-slate-600">{{ $row->work_date->format('d.m.Y') }}</td>@endif
                            @if(in_array('day_type', $visibleColumns, true))<td class="px-3 py-3"><span class="px-2 py-0.5 text-xs font-mono rounded {{ $row->day_type->value === 'official_holiday' ? 'bg-violet-50 text-violet-700' : ($row->day_type->value === 'weekly_rest' ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-700') }}">{{ $row->day_type->label() }}</span></td>@endif
                            @foreach(['scheduled' => 'scheduled_minutes', 'worked' => 'worked_minutes', 'leave' => 'leave_minutes', 'overtime' => 'overtime_minutes', 'missing' => 'missing_minutes'] as $column => $field)
                                @if(in_array($column, $visibleColumns, true))<td class="px-3 py-3 text-sm text-slate-600">{{ intdiv((int) $row->effective($field), 60) }}:{{ str_pad((int) $row->effective($field) % 60, 2, '0', STR_PAD_LEFT) }}@if($column === 'leave' && $row->requested_leave_minutes !== (int) $row->effective('leave_minutes'))<span class="ml-1 text-xs text-red-600" title="Talep edilen izin: {{ $row->requested_leave_minutes }} dk">!</span>@endif</td>@endif
                            @endforeach
                            @if(in_array('first_in', $visibleColumns, true))<td class="px-3 py-3 text-sm text-slate-600">{{ $row->first_in_at?->format('H:i') ?: '—' }}</td>@endif
                            @if(in_array('last_out', $visibleColumns, true))<td class="px-3 py-3 text-sm text-slate-600">{{ $row->last_out_at?->format('H:i') ?: '—' }}</td>@endif
                            @if(in_array('anomalies', $visibleColumns, true))<td class="px-3 py-3">@if($row->anomaly_count > 0)<a href="{{ route('hr.attendance.anomalies') }}" class="px-2 py-0.5 text-xs font-mono rounded bg-red-50 text-red-700">{{ $row->anomaly_count }} açık</a>@else<span class="px-2 py-0.5 text-xs font-mono rounded bg-emerald-50 text-emerald-700">Temiz</span>@endif</td>@endif
                            @if(in_array('status', $visibleColumns, true))<td class="px-3 py-3"><span class="px-2 py-0.5 text-xs font-mono rounded bg-slate-100 text-slate-700">{{ $row->status->label() }}</span>@if($row->latestCorrection)<span class="ml-1 text-xs text-amber-700">R{{ $row->latestCorrection->revision_number }}</span>@endif</td>@endif
                            @if(in_array('actions', $visibleColumns, true))
                                <td class="px-3 py-3 text-right">
                                    @if($row->status->value === 'draft' && auth()->user()?->hasHrPermission('hr.timesheet.confirm'))
                                        @if($row->anomaly_count === 0)<button wire:click="confirm({{ $row->id }})" class="px-2 py-2 text-xs text-slate-700">Onayla</button>@else<a href="{{ route('hr.attendance.anomalies') }}" class="px-2 py-2 text-xs text-red-700">İncele</a>@endif
                                    @endif
                                    @if(in_array($row->status->value, ['confirmed', 'closed'], true) && auth()->user()?->hasHrPermission('hr.timesheet.correct'))<button wire:click="startCorrection({{ $row->id }})" class="px-2 py-2 text-xs text-amber-700">Revizyon</button>@endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($visibleColumns) }}" class="px-4 py-12 text-center text-sm text-slate-500">Puantaj satırı bulunamadı.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="md:hidden divide-y divide-slate-100">
            @forelse($rows as $row)
                <article class="p-4 {{ $row->anomaly_count > 0 ? 'bg-red-50/40' : '' }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0"><p class="truncate text-sm font-medium text-slate-900">{{ $row->employee?->full_name }}</p><p class="mt-1 text-xs text-slate-500">{{ $row->work_date->format('d.m.Y') }} · {{ $row->day_type->label() }} · {{ $row->first_in_at?->format('H:i') ?: '—' }}–{{ $row->last_out_at?->format('H:i') ?: '—' }}</p></div>
                        <span class="px-2 py-0.5 text-xs font-mono rounded {{ $row->anomaly_count > 0 ? 'bg-red-50 text-red-700' : 'bg-slate-100 text-slate-700' }}">{{ $row->anomaly_count > 0 ? $row->anomaly_count.' açık' : $row->status->label() }}</span>
                    </div>
                    <div class="mt-3 grid grid-cols-3 gap-2 text-xs text-slate-600"><span>Çalışma {{ $row->effective('worked_minutes') }}dk</span><span>İzin {{ $row->effective('leave_minutes') }}dk</span><span>Eksik {{ $row->effective('missing_minutes') }}dk</span></div>
                    @if($row->status->value === 'draft' && $row->anomaly_count === 0)<button wire:click="confirm({{ $row->id }})" class="mt-3 w-full px-4 py-3 rounded-[6px] border border-slate-200 text-sm">Onayla</button>@elseif($row->anomaly_count > 0)<a href="{{ route('hr.attendance.anomalies') }}" class="mt-3 block w-full px-4 py-3 rounded-[6px] border border-red-200 text-center text-sm text-red-700">Anomaliyi incele</a>@elseif(auth()->user()?->hasHrPermission('hr.timesheet.correct'))<button wire:click="startCorrection({{ $row->id }})" class="mt-3 w-full px-4 py-3 rounded-[6px] border border-amber-200 text-sm text-amber-700">Revizyon oluştur</button>@endif
                </article>
            @empty
                <div class="p-8 text-center text-sm text-slate-500">Puantaj satırı bulunamadı.</div>
            @endforelse
        </div>
        <div class="border-t border-slate-200 p-4">{{ $rows->links() }}</div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-max w-full border-separate border-spacing-0 text-xs">
                    <thead class="sticky top-0 z-10 bg-slate-50/95 text-slate-500">
                        <tr>
                            <th class="sticky left-0 z-20 min-w-56 border-b border-r border-slate-200 bg-slate-50 px-3 py-3 text-left">Çalışan / Organizasyon</th>
                            @foreach($matrixDays as $day)
                                <th class="min-w-20 border-b border-r border-slate-200 px-2 py-3 text-center {{ $day->isWeekend() ? 'bg-amber-50/70' : '' }}"><span class="block font-semibold text-slate-700">{{ $day->format('d') }}</span><span class="block mt-0.5">{{ $day->locale('tr')->shortDayName }}</span></th>
                            @endforeach
                            <th class="min-w-28 border-b border-slate-200 px-3 py-3 text-center">Toplam</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($matrixEmployees as $matrixEmployeeRef)
                            @php
                                $employeeRows = $matrixRows->get($matrixEmployeeRef->employee_id, collect());
                                $employee = $employeeRows->first()?->employee;
                                $employment = $employee?->activeEmployment;
                            @endphp
                            <tr>
                                <td class="sticky left-0 z-10 border-b border-r border-slate-200 bg-white px-3 py-3">
                                    <p class="font-medium text-slate-900">{{ $employee?->full_name ?: 'Çalışan bulunamadı' }}</p>
                                    <p class="mt-0.5 text-[11px] text-slate-500">{{ $employment?->department?->name ?: 'Departman yok' }} · {{ $employment?->position?->title ?: 'Pozisyon yok' }}</p>
                                </td>
                                @foreach($matrixDays as $day)
                                    @php
                                        $cell = $employeeRows->get($day->toDateString());
                                        $cellClass = match (true) {
                                            !$cell => 'bg-slate-50 text-slate-400',
                                            $cell->anomaly_count > 0 => 'bg-red-50 text-red-800',
                                            $cell->day_type->value === 'official_holiday' => 'bg-violet-50 text-violet-800',
                                            $cell->day_type->value === 'weekly_rest' => 'bg-amber-50 text-amber-800',
                                            (int) $cell->effective('leave_minutes') > 0 => 'bg-blue-50 text-blue-800',
                                            (int) $cell->effective('missing_minutes') > 0 => 'bg-orange-50 text-orange-800',
                                            default => 'bg-emerald-50/60 text-emerald-800',
                                        };
                                    @endphp
                                    <td class="border-b border-r border-slate-200 p-1.5 text-center align-top {{ $cellClass }}">
                                        @if($cell)
                                            <span class="block font-semibold">{{ intdiv((int)$cell->effective('worked_minutes'),60) }}:{{ str_pad((int)$cell->effective('worked_minutes')%60,2,'0',STR_PAD_LEFT) }}</span>
                                            <span class="mt-0.5 block text-[10px]">@if($cell->anomaly_count > 0){{ $cell->anomaly_count }} kontrol @elseif((int)$cell->effective('leave_minutes') > 0)İ {{ intdiv((int)$cell->effective('leave_minutes'),60) }}s @elseif((int)$cell->effective('missing_minutes') > 0)E {{ (int)$cell->effective('missing_minutes') }}dk @else{{ $cell->day_type->label() }}@endif</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                @endforeach
                                <td class="border-b border-slate-200 bg-slate-50/60 px-3 py-3 text-center">
                                    <span class="block font-semibold text-slate-900">{{ intdiv($employeeRows->sum(fn($row)=>(int)$row->effective('worked_minutes')),60) }}s</span>
                                    <span class="mt-0.5 block text-[10px] text-slate-500">Eksik {{ $employeeRows->sum(fn($row)=>(int)$row->effective('missing_minutes')) }}dk</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ $matrixDays->count() + 2 }}" class="px-4 py-12 text-center text-sm text-slate-500">Matris için puantaj kaydı bulunamadı.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 p-4">{{ $matrixEmployees->links() }}</div>
            <div class="flex flex-wrap gap-2 border-t border-slate-200 bg-slate-50/60 px-4 py-3 text-[11px]"><span class="px-2 py-1 rounded bg-emerald-50 text-emerald-700">Normal</span><span class="px-2 py-1 rounded bg-blue-50 text-blue-700">İzin</span><span class="px-2 py-1 rounded bg-orange-50 text-orange-700">Eksik</span><span class="px-2 py-1 rounded bg-red-50 text-red-700">Kontrol gerekli</span><span class="px-2 py-1 rounded bg-violet-50 text-violet-700">Resmî tatil</span><span class="px-2 py-1 rounded bg-amber-50 text-amber-700">Hafta tatili</span></div>
        @endif
    </section>

    @if($correctingId)
        <div class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-slate-900/40 p-4">
            <form wire:submit="saveCorrection" class="w-full max-w-lg rounded-[10px] border border-slate-200 bg-white p-4 lg:p-6 shadow-md">
                <h2 class="text-lg font-semibold text-slate-900">Puantaj revizyonu</h2>
                <p class="mt-1 text-sm text-slate-500">Ana kayıt değişmez; bu değerler yeni revizyon olarak saklanır.</p>
                <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @foreach(['worked_minutes' => 'Çalışılan dk', 'break_minutes' => 'Mola dk', 'leave_minutes' => 'İzin dk', 'overtime_minutes' => 'Fazla dk', 'missing_minutes' => 'Eksik dk'] as $field => $label)
                        <label class="text-xs text-slate-500">{{ $label }}<input type="number" min="0" wire:model.defer="correction.{{ $field }}" class="mt-1 w-full text-base sm:text-sm rounded-[6px] border border-slate-200 px-3 py-3 sm:py-2"></label>
                    @endforeach
                </div>
                <textarea wire:model.defer="correctionReason" rows="3" class="mt-3 w-full text-base sm:text-sm rounded-[6px] border border-slate-200 px-3 py-3 sm:py-2" placeholder="Zorunlu revizyon gerekçesi"></textarea>
                @if($errors->any())<p class="mt-2 text-xs text-red-600">{{ $errors->first() }}</p>@endif
                <div class="mt-4 flex flex-col-reverse sm:flex-row sm:justify-end gap-2"><button type="button" wire:click="$set('correctingId', null)" class="w-full sm:w-auto px-4 py-3 sm:py-2 rounded-[6px] border border-slate-200 text-sm">Vazgeç</button><button class="w-full sm:w-auto px-4 py-3 sm:py-2 rounded-[6px] bg-slate-900 text-sm text-white">Revizyonu kaydet</button></div>
            </form>
        </div>
    @endif
</div>
