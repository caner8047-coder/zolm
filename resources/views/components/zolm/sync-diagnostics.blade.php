@props([
    'run',
    'compact' => false,
])

@php
    $metrics = method_exists($run, 'diagnosticMetrics') ? $run->diagnosticMetrics() : [];
    $warnings = method_exists($run, 'diagnosticsWarnings') ? $run->diagnosticsWarnings() : [];
    $tone = method_exists($run, 'diagnosticsTone') ? $run->diagnosticsTone() : 'default';
    $warningCount = method_exists($run, 'diagnosticWarningCount') ? $run->diagnosticWarningCount() : count($warnings);
@endphp

@if($metrics !== [] || $warnings !== [])
    <div {{ $attributes->merge([
        'class' => ($compact ? 'mt-3 rounded-2xl border border-slate-200 bg-slate-50/70 p-3' : 'mt-4 rounded-3xl border border-slate-200 bg-slate-50/70 p-4'),
    ]) }}>
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-xs uppercase tracking-[0.16em] text-slate-500">Mapping Diagnostik</p>
                <p class="mt-1 text-sm text-slate-500">
                    {{ $warningCount > 0 ? $warningCount . ' uyarı bulundu.' : 'Bu sync için kritik mapping uyarısı görünmüyor.' }}
                </p>
            </div>
            <x-zolm.status-badge :tone="$tone">
                {{ $warningCount > 0 ? 'İncelenecek' : 'Temiz' }}
            </x-zolm.status-badge>
        </div>

        @if($metrics !== [])
            <div class="mt-3 grid grid-cols-2 xl:grid-cols-3 gap-2">
                @foreach(array_slice($metrics, 0, $compact ? 4 : 6) as $metric)
                    <div class="rounded-xl border border-slate-200 bg-white px-3 py-2">
                        <p class="text-[10px] uppercase tracking-[0.16em] text-slate-500">{{ $metric['label'] }}</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ $metric['value'] }}</p>
                    </div>
                @endforeach
            </div>
        @endif

        @if($warnings !== [])
            <div class="mt-3 space-y-1">
                @foreach(array_slice($warnings, 0, $compact ? 2 : 3) as $warning)
                    <p class="text-xs text-amber-700">• {{ $warning }}</p>
                @endforeach
            </div>
        @endif
    </div>
@endif
