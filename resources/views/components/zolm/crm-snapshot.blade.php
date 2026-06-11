@props([
    'snapshot' => null,
    'variant' => 'inline',
])

@if($snapshot)
    @php
        $toneClass = function (?string $tone): string {
            return match ($tone) {
                'danger' => 'border-rose-200 bg-rose-50 text-rose-700',
                'warning' => 'border-amber-200 bg-amber-50 text-amber-700',
                'success' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                'info' => 'border-sky-200 bg-sky-50 text-sky-700',
                default => 'border-slate-200 bg-slate-50 text-slate-600',
            };
        };

        $wrapperClass = match ($variant) {
            'menu' => 'rounded-[8px] border border-slate-200 bg-slate-50/70 p-2.5',
            'table' => 'rounded-[6px] border border-slate-200 bg-white px-2 py-1.5 shadow-sm',
            'panel' => 'rounded-[8px] border border-slate-200 bg-slate-50/70 p-3',
            default => 'rounded-[8px] border border-slate-200 bg-white p-3',
        };
    @endphp

    <div {{ $attributes->merge(['class' => $wrapperClass]) }}>
        <div class="flex min-w-0 items-center justify-between gap-2">
            <div class="min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">CRM Sinyali</p>
                <p class="mt-0.5 truncate text-xs font-semibold text-slate-900">{{ $snapshot['display_name'] }}</p>
            </div>
            <a href="{{ $snapshot['url'] }}"
               class="inline-flex min-h-[32px] shrink-0 items-center justify-center rounded-[6px] border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-700 transition hover:bg-slate-50">
                360
            </a>
        </div>

        <div class="mt-2 grid grid-cols-3 gap-1.5">
            <div class="min-w-0 rounded-[6px] border px-2 py-1 {{ $toneClass($snapshot['risk_tone']) }}">
                <p class="text-[9px] font-semibold uppercase tracking-[0.12em] opacity-75">Risk</p>
                <p class="mt-0.5 truncate text-[11px] font-semibold">{{ $snapshot['risk_score'] }} · {{ $snapshot['risk_label'] }}</p>
            </div>
            <div class="min-w-0 rounded-[6px] border px-2 py-1 {{ $toneClass($snapshot['value_tone']) }}">
                <p class="text-[9px] font-semibold uppercase tracking-[0.12em] opacity-75">Değer</p>
                <p class="mt-0.5 truncate text-[11px] font-semibold">{{ $snapshot['value_score'] }} · {{ $snapshot['value_label'] }}</p>
            </div>
            <div class="min-w-0 rounded-[6px] border border-slate-200 bg-white px-2 py-1 text-slate-600">
                <p class="text-[9px] font-semibold uppercase tracking-[0.12em] text-slate-400">Vaka</p>
                <p class="mt-0.5 truncate text-[11px] font-semibold text-slate-800">{{ $snapshot['open_case_count'] }} açık</p>
            </div>
        </div>

        @if($variant !== 'table' && ($snapshot['last_event_title'] || $snapshot['gross_revenue_total'] > 0))
            <div class="mt-2 flex min-w-0 items-center justify-between gap-2 text-[11px] text-slate-500">
                <span class="min-w-0 truncate">{{ $snapshot['last_event_title'] ?: 'Son olay yok' }}</span>
                <span class="shrink-0 font-mono text-slate-600">{{ $snapshot['gross_revenue_label'] }}</span>
            </div>
        @endif

        @if(($snapshot['crm_alert_count'] ?? 0) > 0)
            <div class="mt-2 rounded-[6px] border border-amber-200 bg-amber-50 px-2 py-1 text-[11px] font-semibold text-amber-700">
                {{ $snapshot['crm_alert_count'] }} aktif CRM uyarısı
            </div>
        @endif
    </div>
@endif
