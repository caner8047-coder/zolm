@php
    $user = auth()->user();
    $workspaceGroups = collect([
        [
            'title' => 'İnsan ve organizasyon',
            'description' => 'Çalışan yaşam döngüsü ve kurum yapısı',
            'tone' => 'bg-blue-50 text-blue-700',
            'items' => collect([
                ['label' => 'Personel', 'route' => 'hr.personnel', 'permission' => 'hr.employees.view'],
                ['label' => 'Belgeler', 'route' => 'hr.documents', 'permission' => 'hr.documents.view'],
                ['label' => 'Organizasyon', 'route' => 'hr.settings.organization', 'permission' => 'hr.org_structure.view'],
                ['label' => 'İşe giriş / çıkış', 'route' => 'hr.lifecycle', 'permission' => 'hr.lifecycle.view'],
            ])->filter(fn ($item) => $user?->hasHrPermission($item['permission'])),
        ],
        [
            'title' => 'Zaman ve devam',
            'description' => 'Vardiya, PDKS, izin ve puantaj',
            'tone' => 'bg-emerald-50 text-emerald-700',
            'items' => collect([
                ['label' => 'İzin yönetimi', 'route' => 'hr.leaves', 'permission' => 'hr.leaves.view'],
                ['label' => 'Vardiya planı', 'route' => 'hr.shifts', 'permission' => 'hr.shifts.view'],
                ['label' => 'PDKS olayları', 'route' => 'hr.attendance', 'permission' => 'hr.attendance.view'],
                ['label' => 'Puantaj', 'route' => 'hr.timesheets', 'permission' => 'hr.timesheet.view'],
                ['label' => 'Fazla mesai', 'route' => 'hr.overtime', 'permission' => 'hr.timesheet.view'],
            ])->filter(fn ($item) => $user?->hasHrPermission($item['permission'])),
        ],
        [
            'title' => 'Ücret ve operasyon',
            'description' => 'Bordro, yan haklar ve çalışan işlemleri',
            'tone' => 'bg-amber-50 text-amber-700',
            'items' => collect([
                ['label' => 'Bordro', 'route' => 'hr.payroll', 'permission' => 'hr.payroll.view'],
                ['label' => 'Ücret ve yan haklar', 'route' => 'hr.compensation', 'permission' => 'hr.salary.view'],
                ['label' => 'Masraflar', 'route' => 'hr.expenses', 'permission' => 'hr.expenses.view'],
                ['label' => 'Avanslar', 'route' => 'hr.advances', 'permission' => 'hr.advances.view'],
                ['label' => 'Zimmetler', 'route' => 'hr.assets', 'permission' => 'hr.assets.view'],
            ])->filter(fn ($item) => $user?->hasHrPermission($item['permission'])),
        ],
        [
            'title' => 'Yetenek ve gelişim',
            'description' => 'Performans, eğitim ve işe alım',
            'tone' => 'bg-violet-50 text-violet-700',
            'items' => collect([
                ['label' => 'Performans', 'route' => 'hr.performance', 'permission' => 'hr.performance.view'],
                ['label' => 'Eğitim', 'route' => 'hr.training', 'permission' => 'hr.training.view'],
                ['label' => 'Çalışan bağlılığı', 'route' => 'hr.engagement', 'permission' => 'hr.engagement.view'],
                ['label' => 'Aday takip', 'route' => 'hr.recruitment', 'permission' => 'hr.recruitment.view'],
            ])->filter(fn ($item) => $user?->hasHrPermission($item['permission'])),
        ],
    ])->filter(fn ($group) => $group['items']->isNotEmpty());
@endphp

<div class="space-y-4 lg:space-y-6">
    <section class="overflow-hidden rounded-[10px] border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-4 p-4 lg:flex-row lg:items-center lg:justify-between lg:p-6">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">İK çalışma alanı</span>
                    <span class="rounded bg-emerald-50 px-2 py-0.5 text-xs font-mono text-emerald-700">{{ $modules->count() }} servis aktif</span>
                </div>
                <h1 class="mt-3 text-xl font-semibold text-slate-900 lg:text-2xl">İnsan Kaynakları</h1>
                <p class="mt-1 text-sm text-slate-500">{{ $tenant->name }} · {{ now()->locale('tr')->translatedFormat('d F Y, l') }}</p>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                @if($user?->hasHrPermission('hr.employees.create'))
                    <a href="{{ route('hr.personnel.create') }}" class="w-full rounded-[6px] bg-slate-900 px-4 py-3 text-center text-sm font-medium text-white sm:w-auto sm:py-2">+ Yeni çalışan</a>
                @endif
                @if($user?->hasHrPermission('hr.leaves.create'))
                    <a href="{{ route('hr.leaves.create') }}" class="w-full rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-center text-sm font-medium text-slate-700 sm:w-auto sm:py-2">İzin talebi</a>
                @endif
                @if($user?->hasHrPermission('hr.assistant.query'))
                    <a href="{{ route('hr.assistant') }}" class="w-full rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-center text-sm font-medium text-slate-700 sm:w-auto sm:py-2">İK Asistanı</a>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-1 border-t border-slate-200 sm:grid-cols-2 xl:grid-cols-4">
            <a href="{{ route('hr.personnel') }}" class="min-w-0 border-b border-slate-100 p-4 transition hover:bg-slate-50/60 sm:border-r xl:border-b-0">
                <div class="flex items-center justify-between gap-3"><p class="text-xs font-medium uppercase tracking-[0.12em] text-slate-500">Aktif çalışan</p><span class="h-2 w-2 rounded-full bg-emerald-500"></span></div>
                <p class="mt-3 text-2xl font-semibold text-slate-900">{{ number_format($overviewMetrics['active_employees'], 0, ',', '.') }}</p>
                <p class="mt-1 text-xs text-slate-500">Personel ana kaydı</p>
            </a>
            <a href="{{ $user?->hasHrPermission('hr.leaves.approve') ? route('hr.leaves.approvals') : route('hr.leaves') }}" class="min-w-0 border-b border-slate-100 p-4 transition hover:bg-slate-50/60 xl:border-b-0 xl:border-r">
                <div class="flex items-center justify-between gap-3"><p class="text-xs font-medium uppercase tracking-[0.12em] text-slate-500">İzin onayı</p><span class="rounded bg-amber-50 px-2 py-0.5 text-xs font-mono text-amber-700">Bekleyen</span></div>
                <p class="mt-3 text-2xl font-semibold {{ $overviewMetrics['pending_leave'] > 0 ? 'text-amber-700' : 'text-slate-900' }}">{{ $overviewMetrics['pending_leave'] }}</p>
                <p class="mt-1 text-xs text-slate-500">Karar bekleyen talep</p>
            </a>
            @if($overviewMetrics['attendance_risks'] !== null)
                <a href="{{ route('hr.attendance.anomalies') }}" class="min-w-0 border-b border-slate-100 p-4 transition hover:bg-slate-50/60 sm:border-r sm:border-b-0 xl:border-r">
                    <div class="flex items-center justify-between gap-3"><p class="text-xs font-medium uppercase tracking-[0.12em] text-slate-500">PDKS riski</p><span class="rounded bg-red-50 px-2 py-0.5 text-xs font-mono text-red-700">Açık</span></div>
                    <p class="mt-3 text-2xl font-semibold {{ $overviewMetrics['attendance_risks'] > 0 ? 'text-red-700' : 'text-slate-900' }}">{{ $overviewMetrics['attendance_risks'] }}</p>
                    <p class="mt-1 text-xs text-slate-500">Çözülmemiş anomali</p>
                </a>
            @endif
            @if($overviewMetrics['open_positions'] !== null)
                <a href="{{ route('hr.recruitment') }}" class="min-w-0 p-4 transition hover:bg-slate-50/60">
                    <div class="flex items-center justify-between gap-3"><p class="text-xs font-medium uppercase tracking-[0.12em] text-slate-500">Açık kadro</p><span class="rounded bg-blue-50 px-2 py-0.5 text-xs font-mono text-blue-700">İşe alım</span></div>
                    <p class="mt-3 text-2xl font-semibold text-slate-900">{{ $overviewMetrics['open_positions'] }}</p>
                    <p class="mt-1 text-xs text-slate-500">Yayınlanmış pozisyon</p>
                </a>
            @endif
        </div>
    </section>

    <div class="grid grid-cols-1 gap-3 lg:gap-4 xl:grid-cols-12">
        <section class="overflow-hidden rounded-[10px] border border-slate-200 bg-white shadow-sm xl:col-span-8">
            <div class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50/60 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">Komuta yüzeyi</p>
                    <h2 class="mt-1 text-sm font-semibold text-slate-900">İK operasyonları</h2>
                    <p class="mt-1 text-xs text-slate-500">Günlük işi uygulama isimleri yerine süreçlerden yönetin.</p>
                </div>
                @if($user?->hasHrPermission('hr.analytics.view'))
                    <a href="{{ route('hr.analytics') }}" class="w-full rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-center text-xs font-medium text-slate-700 sm:w-auto">Analitiği aç →</a>
                @endif
            </div>
            <div class="grid grid-cols-1 gap-3 p-4 sm:grid-cols-2 lg:gap-4">
                @foreach($workspaceGroups as $group)
                    <article class="min-w-0 rounded-[8px] border border-slate-200 bg-white p-4">
                        <div class="flex items-start gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[6px] {{ $group['tone'] }}">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16M4 12h16M4 18h10"/></svg>
                            </span>
                            <div class="min-w-0"><h3 class="text-sm font-semibold text-slate-900">{{ $group['title'] }}</h3><p class="mt-1 text-xs text-slate-500">{{ $group['description'] }}</p></div>
                        </div>
                        <div class="mt-4 divide-y divide-slate-100 border-t border-slate-100">
                            @foreach($group['items'] as $item)
                                <a href="{{ route($item['route']) }}" class="flex items-center justify-between gap-3 py-2.5 text-sm text-slate-700 transition hover:text-slate-950">
                                    <span class="truncate">{{ $item['label'] }}</span><span class="text-slate-300">→</span>
                                </a>
                            @endforeach
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <aside class="space-y-3 lg:space-y-4 xl:col-span-4">
            <section class="overflow-hidden rounded-[10px] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 bg-slate-50/60 p-4"><p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">Bugünün akışı</p><h2 class="mt-1 text-sm font-semibold text-slate-900">Takvim ve önemli tarihler</h2></div>
                <div class="divide-y divide-slate-100">
                    @forelse($upcomingHolidays as $holiday)
                        <div class="flex items-center justify-between gap-3 px-4 py-3"><div class="min-w-0"><p class="truncate text-sm font-medium text-slate-800">{{ $holiday->name }}</p><p class="mt-1 text-xs text-slate-500">Resmî tatil</p></div><span class="shrink-0 rounded bg-slate-100 px-2 py-1 text-xs font-mono text-slate-600">{{ $holiday->date->format('d.m') }}</span></div>
                    @empty
                        <div class="p-4"><p class="text-sm font-medium text-slate-800">Takvim sakin</p><p class="mt-1 text-xs leading-5 text-slate-500">Yaklaşan resmî tatil veya şirket günü bulunmuyor.</p></div>
                    @endforelse
                </div>
            </section>

            <section class="overflow-hidden rounded-[10px] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 p-4"><h2 class="text-sm font-semibold text-slate-900">Kontrol ve destek</h2><p class="mt-1 text-xs text-slate-500">Risk, uyum ve sistem araçları</p></div>
                <div class="grid grid-cols-1 gap-2 p-3 sm:grid-cols-2 xl:grid-cols-1">
                    @if($user?->hasHrPermission('hr.workforce.view'))<a href="{{ route('hr.workforce-planning') }}" class="flex items-center justify-between rounded-[6px] border border-slate-200 px-3 py-3 text-sm text-slate-700"><span>Kadro planlama</span><span>→</span></a>@endif
                    @if($user?->hasHrPermission('hr.isg.view'))<a href="{{ route('hr.isg') }}" class="flex items-center justify-between rounded-[6px] border border-slate-200 px-3 py-3 text-sm text-slate-700"><span>İSG ve uyum</span><span>→</span></a>@endif
                    @if($user?->hasHrPermission('hr.support.view'))<a href="{{ route('hr.support') }}" class="flex items-center justify-between rounded-[6px] border border-slate-200 px-3 py-3 text-sm text-slate-700"><span>Çalışan destek</span><span>→</span></a>@endif
                    @if($user?->hasHrPermission('hr.integrations.view'))<a href="{{ route('hr.integrations') }}" class="flex items-center justify-between rounded-[6px] border border-slate-200 px-3 py-3 text-sm text-slate-700"><span>Entegrasyon sağlığı</span><span>→</span></a>@endif
                </div>
            </section>
        </aside>
    </div>

    <section class="overflow-hidden rounded-[10px] border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-2 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between">
            <div><p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">Personel defteri</p><h2 class="mt-1 text-sm font-semibold text-slate-900">Son eklenen çalışanlar</h2></div>
            @if($user?->hasHrPermission('hr.employees.view'))<a href="{{ route('hr.personnel') }}" class="text-sm font-medium text-slate-700">Tüm personeli gör →</a>@endif
        </div>

        @if($recentEmployees->isNotEmpty())
            <div class="hidden overflow-x-auto md:block">
                <table class="w-full table-fixed">
                    <thead class="bg-slate-50/60 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="w-2/5 px-4 py-3">Çalışan</th><th class="w-1/5 px-4 py-3">Sicil</th><th class="w-1/5 px-4 py-3">Pozisyon</th><th class="w-1/5 px-4 py-3">Durum</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">@foreach($recentEmployees as $employee)<tr class="hover:bg-slate-50/60"><td class="overflow-hidden text-ellipsis whitespace-nowrap px-4 py-3 text-sm font-medium text-slate-900">{{ $employee->full_name }}</td><td class="overflow-hidden text-ellipsis whitespace-nowrap px-4 py-3 text-sm font-mono text-slate-600">{{ $employee->employee_number }}</td><td class="overflow-hidden text-ellipsis whitespace-nowrap px-4 py-3 text-sm text-slate-600">{{ $employee->activeEmployment?->position?->name ?? '—' }}</td><td class="px-4 py-3"><span class="rounded bg-emerald-50 px-2 py-0.5 text-xs font-mono text-emerald-700">{{ $employee->status->label() }}</span></td></tr>@endforeach</tbody>
                </table>
            </div>
            <div class="divide-y divide-slate-100 md:hidden">@foreach($recentEmployees as $employee)<a href="{{ route('hr.personnel.show', $employee) }}" class="flex items-center justify-between gap-3 p-4"><div class="min-w-0"><p class="truncate text-sm font-medium text-slate-900">{{ $employee->full_name }}</p><p class="mt-1 truncate text-xs text-slate-500">{{ $employee->employee_number }} · {{ $employee->activeEmployment?->position?->name ?? 'Pozisyon yok' }}</p></div><span class="text-slate-300">→</span></a>@endforeach</div>
        @else
            <div class="flex flex-col gap-4 p-6 sm:flex-row sm:items-center sm:justify-between lg:p-8">
                <div class="min-w-0"><h3 class="text-sm font-semibold text-slate-900">İK çalışma alanınız hazır</h3><p class="mt-1 max-w-2xl text-sm leading-6 text-slate-500">İlk çalışanı eklediğinizde personel, izin, vardiya, PDKS ve bordro akışları bu panelde gerçek verilerle dolmaya başlayacak.</p></div>
                @if($user?->hasHrPermission('hr.employees.create'))<a href="{{ route('hr.personnel.create') }}" class="w-full shrink-0 rounded-[6px] bg-slate-900 px-4 py-3 text-center text-sm font-medium text-white sm:w-auto sm:py-2">İlk çalışanı ekle</a>@endif
            </div>
        @endif
    </section>
</div>
