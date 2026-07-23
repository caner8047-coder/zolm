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
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-1.5">
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
                    <p class="mt-1.5 truncate text-sm font-medium text-slate-800">{{ $headline }}</p>
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
                 class="space-y-3 border-t border-slate-200 bg-slate-50/40 p-3 sm:p-4">
                <div class="rounded-[8px] border border-slate-200 bg-white p-3">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold text-slate-800">Bu uyarılar ne anlama geliyor?</p>
                            <p class="mt-0.5 text-[11px] text-slate-500">Yalnızca aksiyon gerektiren açık kayıtlar gösterilir.</p>
                        </div>
                        <span class="text-[10px] text-slate-400">Öncelik sırasıyla</span>
                    </div>
                    <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-3">
                        <div class="rounded-[6px] border border-rose-100 bg-rose-50/70 px-2.5 py-2">
                            <p class="text-[11px] font-semibold text-rose-700">Kritik</p>
                            <p class="mt-0.5 text-[10px] leading-4 text-rose-600">Satış, para veya veri akışını etkiler; önce çözülür.</p>
                        </div>
                        <div class="rounded-[6px] border border-amber-100 bg-amber-50/70 px-2.5 py-2">
                            <p class="text-[11px] font-semibold text-amber-700">Uyarı</p>
                            <p class="mt-0.5 text-[10px] leading-4 text-amber-600">Yakın zamanda kontrol edilmesi gereken operasyon riskidir.</p>
                        </div>
                        <div class="rounded-[6px] border border-sky-100 bg-sky-50/70 px-2.5 py-2">
                            <p class="text-[11px] font-semibold text-sky-700">Bilgi</p>
                            <p class="mt-0.5 text-[10px] leading-4 text-sky-600">Çalışmayı engellemez; veri kalitesini iyileştirir.</p>
                        </div>
                    </div>
                </div>

                @if($riskPrimary)
                    <article class="rounded-[8px] border border-slate-200 bg-white p-3">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">Öncelikli risk</span>
                                    <x-zolm.status-badge :tone="$this->guidanceSeverityTone($riskPrimary['severity'] ?? null)">
                                        {{ $riskPrimary['severity_label'] ?? $this->guidanceSeverityLabel($riskPrimary['severity'] ?? null) }}
                                    </x-zolm.status-badge>
                                </div>
                                <h3 class="mt-1.5 text-sm font-semibold text-slate-900">{{ $riskPrimary['title'] }}</h3>
                            </div>
                            <span class="shrink-0 text-[10px] text-slate-400">{{ $riskPrimary['source_label'] ?? 'Risk Merkezi' }}</span>
                        </div>

                        <div class="mt-3 grid gap-2 sm:grid-cols-2">
                            <div class="rounded-[6px] bg-slate-50 px-3 py-2">
                                <p class="text-[10px] font-semibold text-slate-500">Neden önemli?</p>
                                <p class="mt-1 text-xs leading-5 text-slate-700">{{ $riskPrimary['description'] }}</p>
                            </div>
                            <div class="rounded-[6px] bg-slate-50 px-3 py-2">
                                <p class="text-[10px] font-semibold text-slate-500">Ne yapmalıyım?</p>
                                <p class="mt-1 text-xs leading-5 text-slate-700">{{ $riskPrimary['recommendation'] }}</p>
                            </div>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            @if(filled($riskPrimary['action_url'] ?? null))
                                <a href="{{ $riskPrimary['action_url'] }}"
                                   class="inline-flex min-h-9 items-center justify-center rounded-[6px] bg-slate-900 px-3 text-xs font-medium text-white transition hover:bg-slate-800">
                                    {{ $riskPrimary['action_label'] ?? 'Kaydı aç' }}
                                </a>
                            @endif
                            <a href="{{ $riskGuidance['route'] ?? route('mp.risk-center', ['category' => 'product']) }}"
                               class="inline-flex min-h-9 items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 text-xs font-medium text-slate-700 transition hover:bg-slate-50">
                                Risk Merkezi · {{ $formatCount($riskTotal) }}
                            </a>
                        </div>
                    </article>
                @endif

                @if($diagnosticTotal > 0)
                    <div class="rounded-[8px] border border-slate-200 bg-white p-3">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-xs font-semibold text-slate-800">Veri kalitesi kontrolleri</p>
                                <p class="mt-0.5 text-[11px] text-slate-500">Son ürün senkronlarının eksik eşleştirme ve listing alanları.</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                @if(method_exists($this, 'focusTopGuidance'))
                                    <button type="button"
                                            wire:click="focusTopGuidance"
                                            class="inline-flex min-h-9 items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 text-xs font-medium text-slate-700 transition hover:bg-slate-50">
                                        {{ $this->guidanceFocusLabel() }}
                                    </button>
                                @endif
                                @if(method_exists($this, 'syncTopGuidance'))
                                    <button type="button"
                                            wire:click="syncTopGuidance"
                                            class="inline-flex min-h-9 items-center justify-center rounded-[6px] bg-slate-900 px-3 text-xs font-medium text-white transition hover:bg-slate-800">
                                        {{ $this->guidanceSyncLabel() }}
                                    </button>
                                @endif
                            </div>
                        </div>

                        <div class="mt-3 space-y-2">
                            @foreach($diagnosticItems as $item)
                                <article class="rounded-[7px] border border-slate-200 bg-slate-50/50 p-3">
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <p class="text-sm font-semibold text-slate-900">{{ $item['title'] }}</p>
                                                <span class="text-[11px] text-slate-400">
                                                    {{ $item['store_name'] ?: '-' }} · {{ $this->humanMarketplace($item['marketplace']) }}
                                                </span>
                                            </div>
                                            <p class="mt-1 text-xs leading-5 text-slate-600">
                                                <span class="font-medium text-slate-700">Neden:</span> {{ $item['why'] ?? 'Veri kalitesini ve ilgili iş akışını etkiler.' }}
                                            </p>
                                            <p class="mt-0.5 text-xs leading-5 text-slate-600">
                                                <span class="font-medium text-slate-700">Çözüm:</span> {{ $item['recommended_action'] }}
                                            </p>
                                        </div>
                                        <div class="flex shrink-0 flex-wrap items-center gap-1.5">
                                            <x-zolm.status-badge :tone="$this->guidanceSeverityTone($item['severity'])">
                                                {{ $this->guidanceSeverityLabel($item['severity']) }}
                                            </x-zolm.status-badge>
                                            <span class="rounded-[5px] border border-slate-200 bg-white px-2 py-0.5 text-[11px] text-slate-500">
                                                {{ $formatCount($item['impact_count']) }} kayıt
                                            </span>
                                        </div>
                                    </div>
                                    <div class="mt-2 flex justify-end">
                                        <a href="{{ $this->guidanceRoute($item) }}"
                                           class="text-[11px] font-medium text-slate-700 underline decoration-slate-300 underline-offset-4 hover:text-slate-950">
                                            {{ $this->guidanceRouteLabel($item['route'] ?? null) }} ekranını aç
                                        </a>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </section>
    </div>
@endif
