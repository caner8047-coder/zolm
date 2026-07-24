{{-- Birleşik risk ve veri kalitesi rehberi --}}
@php
    $formatCount = fn ($value) => number_format((float) $value, 0, ',', '.');
    $riskGuidance = $riskGuidance ?? [];
    $riskPrimary = $riskGuidance['primary'] ?? null;
    $riskTotal = (int) ($riskGuidance['total'] ?? 0);
    $diagnosticItems = collect($guidanceItems ?? []);
    $diagnosticTotal = (int) ($diagnosticsGuidance['totals']['items'] ?? $diagnosticItems->count());
    $totalControls = $riskTotal + $diagnosticTotal;
    $primaryGuidance = $primaryGuidance ?? $diagnosticItems->first();
    $secondaryGuidance = $secondaryGuidance ?? $diagnosticItems->slice(1)->take(4)->values();
    $headerContextLabel = $headerContextLabel ?? 'Ürünler';
    $headline = $riskPrimary['title'] ?? ($primaryGuidance['title'] ?? 'Kontrol gereken kayıtlar var');
    $headlineSeverity = $riskPrimary['severity'] ?? ($primaryGuidance['severity'] ?? 'info');
@endphp

@if($totalControls > 0)
    <div class="mb-4"
         x-data="{ guidanceOpen: @js($defaultOpen ?? false) }">
        <section class="overflow-hidden rounded-[10px] border border-slate-200 bg-white shadow-sm">
            <button type="button"
                    @click="guidanceOpen = !guidanceOpen"
                    :aria-expanded="guidanceOpen"
                    class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left">
                <div class="flex min-w-0 flex-col gap-1 sm:flex-row sm:items-center sm:gap-2">
                    <div class="flex shrink-0 flex-wrap items-center gap-1.5">
                        <span class="rounded-[5px] border border-slate-200 bg-slate-50 px-2 py-0.5 text-[11px] font-semibold text-slate-600">
                            {{ $formatCount($totalControls) }} kontrol
                        </span>
                        <x-zolm.status-badge :tone="$this->guidanceSeverityTone($headlineSeverity)">
                            {{ $this->guidanceSeverityLabel($headlineSeverity) }}
                        </x-zolm.status-badge>
                        <span class="rounded-[5px] border border-slate-200 bg-white px-2 py-0.5 text-[11px] text-slate-500">
                            {{ $headerContextLabel }}
                        </span>
                    </div>
                    <span class="hidden text-slate-300 sm:inline">·</span>
                    <p class="truncate text-sm font-medium text-slate-800">{{ $headline }}</p>
                </div>

                <div class="flex shrink-0 items-center gap-2">
                    <span class="inline-flex min-h-8 items-center justify-center rounded-[6px] border border-slate-200 bg-slate-50 px-3 text-xs font-medium text-slate-600"
                          x-text="guidanceOpen ? 'Gizle' : 'Rehberi aç'"></span>
                    <svg class="h-4 w-4 text-slate-400 transition"
                         :class="{ 'rotate-180': guidanceOpen }"
                         fill="none"
                         stroke="currentColor"
                         viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </button>

            <div x-show="guidanceOpen"
                 x-cloak
                 x-transition
                 data-testid="mp-guidance-compact-panel"
                 class="border-t border-slate-200 bg-white px-3 py-2.5 sm:px-4">
                <div class="flex flex-col gap-2 rounded-[6px] border border-slate-200 bg-slate-50/60 px-3 py-2 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <span class="text-[11px] font-semibold text-slate-800">Bu uyarılar ne anlama geliyor?</span>
                        <span class="ml-1 text-[11px] text-slate-500">Yalnızca aksiyon gerektiren açık kayıtlar gösterilir.</span>
                    </div>
                    <div class="flex shrink-0 flex-wrap items-center gap-x-3 gap-y-1 text-[10px]">
                        <span class="inline-flex items-center gap-1 font-medium text-rose-700">
                            <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                            Kritik · önce çöz
                        </span>
                        <span class="inline-flex items-center gap-1 font-medium text-amber-700">
                            <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                            Uyarı · kontrol et
                        </span>
                        <span class="inline-flex items-center gap-1 font-medium text-sky-700">
                            <span class="h-1.5 w-1.5 rounded-full bg-sky-500"></span>
                            Bilgi · iyileştir
                        </span>
                    </div>
                </div>

                @if($riskPrimary)
                    <article class="border-b border-slate-200 py-2.5">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">Öncelikli risk</span>
                                    <x-zolm.status-badge :tone="$this->guidanceSeverityTone($riskPrimary['severity'] ?? null)">
                                        {{ $riskPrimary['severity_label'] ?? $this->guidanceSeverityLabel($riskPrimary['severity'] ?? null) }}
                                    </x-zolm.status-badge>
                                    <h3 class="text-sm font-semibold text-slate-900">{{ $riskPrimary['title'] }}</h3>
                                    <span class="text-[10px] text-slate-400">· {{ $riskPrimary['source_label'] ?? 'Risk Merkezi' }}</span>
                                </div>
                                <div class="mt-1 grid gap-x-4 gap-y-1 text-[11px] leading-4 text-slate-600 sm:grid-cols-2">
                                    <p class="min-w-0 truncate" title="{{ $riskPrimary['description'] }}">
                                        <span class="font-medium text-slate-700">Neden:</span> {{ $riskPrimary['description'] }}
                                    </p>
                                    <p class="min-w-0 truncate" title="{{ $riskPrimary['recommendation'] }}">
                                        <span class="font-medium text-slate-700">Aksiyon:</span> {{ $riskPrimary['recommendation'] }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex shrink-0 flex-col gap-2 sm:flex-row sm:items-center">
                                @if(filled($riskPrimary['action_url'] ?? null))
                                    <a href="{{ $riskPrimary['action_url'] }}"
                                       class="inline-flex w-full items-center justify-center rounded-[6px] bg-slate-900 px-4 py-3 text-xs font-medium text-white transition hover:bg-slate-800 sm:w-auto sm:py-2">
                                        {{ $riskPrimary['action_label'] ?? 'Kaydı aç' }}
                                    </a>
                                @endif
                                <a href="{{ $riskGuidance['route'] ?? route('mp.risk-center', ['category' => 'product']) }}"
                                   class="inline-flex w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-xs font-medium text-slate-700 transition hover:bg-slate-50 sm:w-auto sm:py-2">
                                    Risk Merkezi · {{ $formatCount($riskTotal) }}
                                </a>
                            </div>
                        </div>
                    </article>
                @endif

                @if($diagnosticTotal > 0)
                    <div class="pt-2.5">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex min-w-0 items-center gap-2">
                                <p class="text-xs font-semibold text-slate-800">Veri kalitesi kontrolleri</p>
                                <span class="rounded-[5px] border border-slate-200 bg-slate-50 px-2 py-0.5 text-[10px] text-slate-500">
                                    {{ $formatCount($diagnosticTotal) }} kontrol
                                </span>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                @if(method_exists($this, 'focusTopGuidance'))
                                    <button type="button"
                                            wire:click="focusTopGuidance"
                                            class="inline-flex w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-xs font-medium text-slate-700 transition hover:bg-slate-50 sm:w-auto sm:py-2">
                                        {{ $this->guidanceFocusLabel() }}
                                    </button>
                                @endif
                                @if(method_exists($this, 'syncTopGuidance'))
                                    <button type="button"
                                            wire:click="syncTopGuidance"
                                            class="inline-flex w-full items-center justify-center rounded-[6px] bg-slate-900 px-4 py-3 text-xs font-medium text-white transition hover:bg-slate-800 sm:w-auto sm:py-2">
                                        {{ $this->guidanceSyncLabel() }}
                                    </button>
                                @endif
                            </div>
                        </div>

                        <div class="mt-2 overflow-hidden rounded-lg border border-slate-200 bg-white">
                            @foreach($diagnosticItems as $item)
                                <details data-testid="mp-guidance-diagnostic-row"
                                         class="group border-b border-slate-200 last:border-b-0">
                                    <summary class="flex cursor-pointer list-none flex-col gap-2 px-3 py-2.5 transition hover:bg-slate-50/70 sm:flex-row sm:items-center sm:justify-between [&::-webkit-details-marker]:hidden">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-1.5">
                                                <p class="text-sm font-semibold text-slate-900">{{ $item['title'] }}</p>
                                                <span class="text-[11px] text-slate-400">
                                                    {{ $item['store_name'] ?: '-' }} · {{ $this->humanMarketplace($item['marketplace']) }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="flex shrink-0 flex-wrap items-center gap-1.5">
                                            <x-zolm.status-badge :tone="$this->guidanceSeverityTone($item['severity'])">
                                                {{ $this->guidanceSeverityLabel($item['severity']) }}
                                            </x-zolm.status-badge>
                                            <span class="rounded-[5px] border border-slate-200 bg-white px-2 py-0.5 text-[11px] text-slate-500">
                                                {{ $formatCount($item['impact_count']) }} kayıt
                                            </span>
                                            <span class="text-[10px] font-medium text-slate-500">Detay</span>
                                            <svg class="h-3.5 w-3.5 text-slate-400 transition group-open:rotate-180"
                                                 fill="none"
                                                 stroke="currentColor"
                                                 viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </div>
                                    </summary>
                                    <div class="grid gap-2 border-t border-slate-100 bg-slate-50/60 px-3 py-2.5 sm:grid-cols-2">
                                        <p class="text-[11px] leading-4 text-slate-600">
                                            <span class="font-medium text-slate-700">Neden:</span> {{ $item['why'] ?? 'Veri kalitesini ve ilgili iş akışını etkiler.' }}
                                        </p>
                                        <p class="text-[11px] leading-4 text-slate-600">
                                            <span class="font-medium text-slate-700">Çözüm:</span> {{ $item['recommended_action'] }}
                                        </p>
                                        <div class="sm:col-span-2 sm:text-right">
                                            <a href="{{ $this->guidanceRoute($item) }}"
                                               class="text-[11px] font-medium text-slate-700 underline decoration-slate-300 underline-offset-4 hover:text-slate-950">
                                                {{ $this->guidanceRouteLabel($item['route'] ?? null) }} ekranını aç
                                            </a>
                                        </div>
                                    </div>
                                </details>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </section>
    </div>
@endif
