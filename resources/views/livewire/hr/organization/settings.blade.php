<div class="space-y-4 lg:space-y-6">
    <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">Organizasyon tasarımı</p>
                <h1 class="mt-2 text-xl font-semibold text-slate-900 lg:text-2xl">Organizasyon yapısı</h1>
                <p class="mt-1 text-sm text-slate-500">{{ $tenant->name }} için birim, ekip ve pozisyon hiyerarşisini yönetin.</p>
            </div>
            <a href="{{ route('hr.settings') }}" class="w-full rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-center text-sm font-medium text-slate-700 sm:w-auto sm:py-2">İK ayarlarına dön</a>
        </div>
    </section>

    <section class="overflow-hidden rounded-[10px] border border-slate-200 bg-white shadow-sm">
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5">
            @foreach([
                ['label' => 'Şube', 'value' => $metrics['branches']],
                ['label' => 'Departman', 'value' => $metrics['departments']],
                ['label' => 'Birim', 'value' => $metrics['units']],
                ['label' => 'Ekip', 'value' => $metrics['teams']],
                ['label' => 'Pozisyon', 'value' => $metrics['positions']],
            ] as $metric)
                <div class="min-w-0 border-b border-slate-100 p-4 sm:border-r xl:border-b-0 last:border-r-0">
                    <p class="text-xs font-medium uppercase tracking-[0.12em] text-slate-500">{{ $metric['label'] }}</p>
                    <p class="mt-3 text-2xl font-semibold text-slate-900">{{ number_format($metric['value'], 0, ',', '.') }}</p>
                    <p class="mt-1 text-xs text-slate-500">Aktif kayıt</p>
                </div>
            @endforeach
        </div>
    </section>

    <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:gap-4">
        <a href="{{ route('hr.settings.units') }}" class="group rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm transition hover:shadow-md lg:p-6">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <span class="rounded-[6px] bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700">Hiyerarşi</span>
                    <h2 class="mt-3 text-base font-semibold text-slate-900">Birim yönetimi</h2>
                    <p class="mt-1 text-sm leading-6 text-slate-500">Departmanların altındaki operasyonel birimleri görüntüleyin, filtreleyin ve yönetin.</p>
                </div>
                <span class="text-slate-300 transition group-hover:text-slate-700">→</span>
            </div>
        </a>

        <a href="{{ route('hr.settings.teams') }}" class="group rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm transition hover:shadow-md lg:p-6">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <span class="rounded-[6px] bg-violet-50 px-2 py-1 text-xs font-medium text-violet-700">Çalışma grupları</span>
                    <h2 class="mt-3 text-base font-semibold text-slate-900">Ekip yönetimi</h2>
                    <p class="mt-1 text-sm leading-6 text-slate-500">Birimlere bağlı ekipleri, yöneticileri ve aktiflik durumlarını yönetin.</p>
                </div>
                <span class="text-slate-300 transition group-hover:text-slate-700">→</span>
            </div>
        </a>
    </section>
</div>
