<div class="space-y-4 lg:space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold text-slate-900 lg:text-2xl">İK Analitiği</h1>
            <p class="mt-1 text-sm text-slate-500">Her KPI kaynağı ve üretim zamanı ile birlikte değiştirilemez anlık görüntüye alınır.</p>
        </div>
        @if(auth()->user()?->hasHrPermission('hr.workforce.view'))
            <a href="{{ route('hr.workforce-planning') }}" class="w-full rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-center text-sm text-slate-700 sm:w-auto sm:py-2">Kadro ve bütçe planlama</a>
        @endif
    </div>

    @if(session('success'))
        <div class="rounded-[8px] border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    <form wire:submit="generate" class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <label class="flex-1 text-xs text-slate-500">Dönem başlangıcı
                <input wire:model.defer="periodStart" type="date" class="mt-1 w-full rounded-[6px] border border-slate-200 px-3 py-3 text-base sm:text-sm">
            </label>
            <label class="flex-1 text-xs text-slate-500">Dönem bitişi
                <input wire:model.defer="periodEnd" type="date" class="mt-1 w-full rounded-[6px] border border-slate-200 px-3 py-3 text-base sm:text-sm">
            </label>
            <button class="w-full rounded-[6px] bg-slate-900 px-4 py-3 text-white sm:w-auto sm:py-2">Anlık görüntü oluştur</button>
        </div>
    </form>

    @php
        $metricDefinitions = [
            'headcount' => ['Aktif çalışan', 'headcount', 0],
            'hires' => ['İşe giriş', 'hires_exits', 0],
            'exits' => ['Çıkış', 'hires_exits', 0],
            'turnover_rate' => ['Turnover %', 'hires_exits', 1],
            'approved_leave_units' => ['Onaylı izin', 'leave', 1],
            'attendance_anomalies' => ['PDKS anomalisi', 'attendance', 0],
            'training_completions' => ['Eğitim tamamlama', 'training', 0],
            'open_headcount' => ['Açık kadro', 'recruitment', 0],
            'monthly_gross_cost' => ['Aylık brüt ücret', 'salary', 2],
            'monthly_benefit_cost' => ['Aylık yan hak maliyeti', 'benefits', 2],
            'latest_payroll_employer_cost' => ['Son bordro işveren maliyeti', 'payroll', 2],
            'average_gross_salary' => ['Ortalama brüt ücret', 'salary', 2],
            'salary_coverage' => ['Ücret kapsamındaki çalışan', 'salary', 0],
        ];
    @endphp

    @forelse($snapshots as $snapshot)
        <section class="overflow-hidden rounded-[10px] border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-2 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-sm font-semibold">{{ $snapshot->period_start->format('d.m.Y') }}–{{ $snapshot->period_end->format('d.m.Y') }}</h2>
                    <p class="text-xs text-slate-500">{{ $snapshot->generated_at->format('d.m.Y H:i') }} · {{ substr($snapshot->source_hash, 0, 12) }}</p>
                </div>
                <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-mono">Kaynak izli</span>
            </div>
            <div class="grid grid-cols-1 gap-3 p-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach($metricDefinitions as $key => [$label, $sourceKey, $decimals])
                    @php
                        $value = data_get($snapshot->metrics, $key);
                        $source = data_get($snapshot->sources, $sourceKey, 'Eski anlık görüntüde kaynak bilgisi yok');
                    @endphp
                    <div class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                        <p class="text-xs text-slate-500">{{ $label }}</p>
                        <p class="mt-2 text-xl font-semibold">{{ $value === null ? '—' : number_format((float) $value, $decimals, ',', '.') }}</p>
                        <p class="mt-2 truncate text-xs text-slate-400" title="{{ $source }}">Kaynak: {{ $source }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    @empty
        <section class="rounded-[10px] border border-slate-200 bg-white p-10 text-center text-sm text-slate-500">Henüz analitik anlık görüntüsü yok.</section>
    @endforelse
</div>
