{{-- Compact collapsible guidance banner --}}
{{-- Required: $diagnosticsGuidance, $guidanceItems, $primaryGuidance, $secondaryGuidance --}}
@php
    $formatCount = fn ($value) => number_format((float) $value, 0, ',', '.');
    $totalItems = $diagnosticsGuidance['totals']['items'] ?? 0;
    $totalCritical = $diagnosticsGuidance['totals']['critical'] ?? 0;
    $totalWarning = $diagnosticsGuidance['totals']['warning'] ?? 0;
    $accordionStyle = $accordionStyle ?? false;
    $defaultOpen = $defaultOpen ?? false;
    $headerContextLabel = $headerContextLabel ?? null;
@endphp

@if($totalItems > 0)
    @if($accordionStyle)
        <div class="mb-4" x-data="{ guidanceOpen: @js($defaultOpen) }">
            <div class="overflow-hidden rounded-[10px] border border-slate-200 bg-white shadow-sm">
                <button type="button"
                        @click="guidanceOpen = !guidanceOpen"
                        class="flex w-full items-center justify-between gap-3 px-4 py-4 text-left">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            @if($totalCritical > 0)
                                <span class="rounded-[6px] border border-rose-200 bg-rose-100 px-2.5 py-0.5 text-xs font-medium text-rose-700">
                                    {{ $formatCount($totalCritical) }} kritik
                                </span>
                            @endif
                            @if($totalWarning > 0)
                                <span class="rounded-[6px] border border-amber-200 bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-700">
                                    {{ $formatCount($totalWarning) }} uyarı
                                </span>
                            @endif
                            @if(filled($headerContextLabel))
                                <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2.5 py-0.5 text-xs font-medium text-slate-600">
                                    {{ $headerContextLabel }}
                                </span>
                            @elseif(method_exists($this, 'guidanceFocusLabel'))
                                <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2.5 py-0.5 text-xs font-medium text-slate-600">
                                    {{ $this->guidanceFocusLabel() }}
                                </span>
                            @endif
                        </div>
                        @if($primaryGuidance)
                            <p class="mt-1.5 text-sm font-medium text-slate-800">{{ $primaryGuidance['title'] }}</p>
                        @endif
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <span class="inline-flex min-h-[32px] items-center justify-center rounded-[6px] border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-600"
                              x-text="guidanceOpen ? 'Gizle' : 'Detaylar'"></span>
                        <svg class="h-5 w-5 text-slate-400 transition" :class="{ 'rotate-180': guidanceOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>
                </button>

                <div x-show="guidanceOpen" x-cloak x-transition class="border-t border-slate-200 bg-slate-50/40 px-4 py-4 space-y-3">
                    <div class="flex flex-wrap items-center gap-2">
                        @if(method_exists($this, 'focusTopGuidance'))
                            <button type="button"
                                    wire:click="focusTopGuidance"
                                    class="inline-flex min-h-[36px] items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50">
                                {{ $this->guidanceFocusLabel() }}
                            </button>
                        @endif
                        @if(method_exists($this, 'syncTopGuidance'))
                            <button type="button"
                                    wire:click="syncTopGuidance"
                                    class="inline-flex min-h-[36px] items-center justify-center rounded-[6px] bg-slate-900 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-slate-800">
                                {{ $this->guidanceSyncLabel() }}
                            </button>
                        @endif
                    </div>

                    @if($primaryGuidance)
                        <a href="{{ $this->guidanceRoute($primaryGuidance) }}"
                           class="block rounded-[8px] border border-slate-200 bg-white px-4 py-3 transition hover:border-slate-300 hover:bg-slate-50">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="text-sm font-semibold text-slate-900">{{ $primaryGuidance['store_name'] ?: '-' }}</p>
                                        <span class="text-xs text-slate-400">·</span>
                                        <p class="text-xs text-slate-500">{{ $this->humanMarketplace($primaryGuidance['marketplace']) }}</p>
                                    </div>
                                    <p class="mt-1 text-sm text-slate-500">{{ $primaryGuidance['recommended_action'] }}</p>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <x-zolm.status-badge :tone="$this->guidanceSeverityTone($primaryGuidance['severity'])">
                                        {{ $this->guidanceSeverityLabel($primaryGuidance['severity']) }}
                                    </x-zolm.status-badge>
                                    <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2.5 py-0.5 text-xs font-medium text-slate-600">
                                        {{ $formatCount($primaryGuidance['impact_count']) }} kayıt
                                    </span>
                                </div>
                            </div>
                        </a>
                    @endif

                    @if($secondaryGuidance->isNotEmpty())
                        @foreach($secondaryGuidance as $item)
                            <a href="{{ $this->guidanceRoute($item) }}"
                               class="block rounded-[8px] border border-slate-200 bg-white px-4 py-2.5 transition hover:border-slate-300 hover:bg-slate-50">
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-slate-800">{{ $item['title'] }}</p>
                                        <p class="mt-0.5 text-xs text-slate-500">{{ $item['store_name'] ?: '-' }} · {{ $this->humanMarketplace($item['marketplace']) }}</p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <x-zolm.status-badge :tone="$this->guidanceSeverityTone($item['severity'])">
                                            {{ $this->guidanceSeverityLabel($item['severity']) }}
                                        </x-zolm.status-badge>
                                        <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2.5 py-0.5 text-xs font-medium text-slate-600">
                                            {{ $formatCount($item['impact_count']) }} kayıt
                                        </span>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    @else
        <div class="mb-4" x-data="{ guidanceOpen: false }">
            <div class="rounded-xl border {{ $totalCritical > 0 ? 'border-rose-200 bg-rose-50/60' : 'border-amber-200 bg-amber-50/60' }} px-4 py-3">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <div class="flex flex-wrap items-center gap-2 min-w-0">
                        @if($totalCritical > 0)
                            <span class="rounded-full border border-rose-200 bg-rose-100 px-2.5 py-0.5 text-xs font-medium text-rose-700">
                                {{ $formatCount($totalCritical) }} kritik
                            </span>
                        @endif
                        @if($totalWarning > 0)
                            <span class="rounded-full border border-amber-200 bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-700">
                                {{ $formatCount($totalWarning) }} uyarı
                            </span>
                        @endif
                        @if($primaryGuidance)
                            <span class="text-sm text-slate-700 truncate">
                                {{ $primaryGuidance['title'] }}
                            </span>
                        @endif
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        @if(method_exists($this, 'focusTopGuidance'))
                            <button type="button"
                                    wire:click="focusTopGuidance"
                                    class="inline-flex min-h-[36px] items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50">
                                {{ $this->guidanceFocusLabel() }}
                            </button>
                        @endif
                        @if(method_exists($this, 'syncTopGuidance'))
                            <button type="button"
                                    wire:click="syncTopGuidance"
                                    class="inline-flex min-h-[36px] items-center justify-center rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-slate-800">
                                {{ $this->guidanceSyncLabel() }}
                            </button>
                        @endif
                        <button type="button"
                                @click="guidanceOpen = !guidanceOpen"
                                class="inline-flex min-h-[36px] items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50">
                            <span x-text="guidanceOpen ? 'Gizle' : 'Detaylar'"></span>
                        </button>
                    </div>
                </div>

                <div x-show="guidanceOpen" x-cloak x-transition class="mt-3 space-y-2">
                    @if($primaryGuidance)
                        <a href="{{ $this->guidanceRoute($primaryGuidance) }}"
                           class="block rounded-lg border border-slate-200 bg-white px-4 py-3 transition hover:border-slate-300 hover:bg-slate-50">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="text-sm font-semibold text-slate-900">{{ $primaryGuidance['store_name'] ?: '-' }}</p>
                                        <span class="text-xs text-slate-400">·</span>
                                        <p class="text-xs text-slate-500">{{ $this->humanMarketplace($primaryGuidance['marketplace']) }}</p>
                                    </div>
                                    <p class="mt-1 text-sm text-slate-500">{{ $primaryGuidance['recommended_action'] }}</p>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <x-zolm.status-badge :tone="$this->guidanceSeverityTone($primaryGuidance['severity'])">
                                        {{ $this->guidanceSeverityLabel($primaryGuidance['severity']) }}
                                    </x-zolm.status-badge>
                                    <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-0.5 text-xs font-medium text-slate-600">
                                        {{ $formatCount($primaryGuidance['impact_count']) }} kayıt
                                    </span>
                                </div>
                            </div>
                        </a>
                    @endif

                    @if($secondaryGuidance->isNotEmpty())
                        @foreach($secondaryGuidance as $item)
                            <a href="{{ $this->guidanceRoute($item) }}"
                               class="block rounded-lg border border-slate-200 bg-white px-4 py-2.5 transition hover:border-slate-300 hover:bg-slate-50">
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-slate-800">{{ $item['title'] }}</p>
                                        <p class="mt-0.5 text-xs text-slate-500">{{ $item['store_name'] ?: '-' }} · {{ $this->humanMarketplace($item['marketplace']) }}</p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <x-zolm.status-badge :tone="$this->guidanceSeverityTone($item['severity'])">
                                            {{ $this->guidanceSeverityLabel($item['severity']) }}
                                        </x-zolm.status-badge>
                                        <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-0.5 text-xs font-medium text-slate-600">
                                            {{ $formatCount($item['impact_count']) }} kayıt
                                        </span>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    @endif
@endif
