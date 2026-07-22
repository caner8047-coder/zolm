@props([
    'evidence' => [],
])

@php
    $evidence = (array) $evidence;
    $sources = collect((array) ($evidence['data_sources'] ?? []))
        ->filter()
        ->map(fn ($source) => str_replace('_', ' ', (string) $source))
        ->map(fn ($source) => \Illuminate\Support\Str::headline($source))
        ->values();
@endphp

<section data-testid="booster-decision-evidence" class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Karar kanıtı</p>
            <h3 class="mt-1 text-base font-semibold text-slate-900">Gözlenen veri ve hesaplanan sinyaller ayrıdır</h3>
            <p class="mt-1 text-sm text-slate-500">Kararı hangi verinin taşıdığını görün; tahminleri kesin veri gibi okumayın.</p>
        </div>
        <x-zolm.status-badge :tone="$evidence['source_tone'] ?? 'default'" size="sm">
            {{ $evidence['source_label'] ?? 'Kaynak bekleniyor' }}
        </x-zolm.status-badge>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3 lg:gap-4">
        <div class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
            <p class="text-xs text-slate-500">Gözlenen veri</p>
            <p class="mt-1 truncate text-lg font-semibold text-slate-900">{{ (int) ($evidence['observed_metric_count'] ?? 0) }} metrik</p>
            <p class="mt-1 text-xs leading-5 text-slate-500">{{ $evidence['direct_note'] ?? '' }}</p>
        </div>
        <div class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
            <div class="flex items-center justify-between gap-2">
                <p class="text-xs text-slate-500">Veri güveni</p>
                <x-zolm.status-badge :tone="$evidence['confidence_tone'] ?? 'default'" size="xs">{{ $evidence['confidence_label'] ?? 'Veri yok' }}</x-zolm.status-badge>
            </div>
            <p class="mt-1 truncate text-lg font-semibold text-slate-900">%{{ (int) ($evidence['confidence_score'] ?? 0) }}</p>
            <p class="mt-1 text-xs leading-5 text-slate-500">Veri kalitesi: %{{ (int) ($evidence['data_quality_score'] ?? 0) }}</p>
        </div>
        <div class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
            <div class="flex items-center justify-between gap-2">
                <p class="text-xs text-slate-500">Satış sinyali</p>
                <x-zolm.status-badge :tone="$evidence['sales_tone'] ?? 'default'" size="xs">{{ ($evidence['sales_status'] ?? 'unavailable') === 'observed' ? 'Gözleme dayalı' : 'Tahmin' }}</x-zolm.status-badge>
            </div>
            <p class="mt-1 text-sm font-semibold text-slate-900">{{ $evidence['sales_label'] ?? 'Hazır değil' }}</p>
            <p class="mt-1 text-xs leading-5 text-slate-500">{{ $evidence['derived_note'] ?? '' }}</p>
        </div>
    </div>

    <div class="mt-4 flex flex-col gap-2 border-t border-slate-100 pt-3 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between">
        <span>Son kontrol: {{ !empty($evidence['checked_at']) ? \Carbon\Carbon::parse($evidence['checked_at'])->format('d.m.Y H:i') : 'Bilinmiyor' }}</span>
        @if($sources->isNotEmpty())
            <span class="truncate">Kaynak: {{ $sources->implode(' · ') }}</span>
        @endif
    </div>
</section>
