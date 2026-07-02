@props([
    'guidance' => [],
    'contextLabel' => 'Çalışma alanı',
])

@php
    $primary = $guidance['primary'] ?? null;
    $severityClasses = [
        'critical' => 'border-rose-200 bg-rose-50 text-rose-700',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-700',
        'info' => 'border-sky-200 bg-sky-50 text-sky-700',
    ];
@endphp

@if(config('marketplace.features.risk_center_enabled', false) && ($guidance['has_risk'] ?? false) && $primary)
    <section {{ $attributes->merge(['class' => 'rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-5']) }}>
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex min-w-0 items-start gap-3">
                <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-[8px] bg-slate-900 text-white">
                    <x-lucide.icon name="alert-triangle" class="h-5 w-5" />
                </span>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-xs font-medium text-slate-500">{{ $contextLabel }} risk odağı</p>
                        <span class="rounded-[6px] border px-2 py-0.5 text-xs font-mono {{ $severityClasses[$primary['severity']] ?? 'border-slate-200 bg-slate-50 text-slate-600' }}">
                            {{ $primary['severity_label'] }}
                        </span>
                        <span class="text-xs text-slate-400">{{ number_format((float) ($primary['priority_score'] ?? 0), 1, ',', '.') }} puan</span>
                    </div>
                    <h2 class="mt-1 text-sm font-semibold text-slate-900">{{ $primary['title'] }}</h2>
                    <p class="mt-1 line-clamp-2 text-sm leading-5 text-slate-500">{{ $primary['recommendation'] }}</p>
                </div>
            </div>

            <div class="flex shrink-0 flex-col gap-2 sm:flex-row">
                <a href="{{ $primary['action_url'] }}"
                   class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 sm:w-auto sm:py-2">
                    {{ $primary['action_label'] }}
                </a>
                <a href="{{ $guidance['route'] ?? route('mp.risk-center') }}"
                   class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 sm:w-auto sm:py-2">
                    Risk Merkezi · {{ number_format((int) ($guidance['total'] ?? 0), 0, ',', '.') }}
                </a>
            </div>
        </div>
    </section>
@endif
