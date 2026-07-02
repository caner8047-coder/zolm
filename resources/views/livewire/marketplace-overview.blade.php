@php
    $formatMoney = fn ($value) => '₺' . number_format((float) $value, 2, ',', '.');
    $formatCount = fn ($value) => number_format((float) $value, 0, ',', '.');
    $onboarding = $onboardingGuide ?? ['enabled' => false];
    $onboardingPercent = (int) ($onboarding['readiness_percent'] ?? 0);
    $totalErrors = ($healthStats['failed_syncs'] ?? 0) + ($healthStats['failed_pushes'] ?? 0) + ($healthStats['failed_actions'] ?? 0) + ($healthStats['failed_webhooks'] ?? 0);
    $pendingLegacy = (int) ($legacyProjectionSummary['pending_rows'] ?? 0);
@endphp

<div class="w-full space-y-4 lg:space-y-6">
    @if($flashMessage)
        <div class="rounded-[8px] border p-3 text-sm {{ $flashTone === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-slate-50 text-slate-700' }}">
            {{ $flashMessage }}
        </div>
    @endif

    {{-- ═══════════════════════════════════════════
         1. WORKSPACE HEADER — minimal, tek satır
    ═══════════════════════════════════════════ --}}
    <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-4 p-4 lg:flex-row lg:items-center lg:justify-between lg:px-6 lg:py-5">
            <div class="min-w-0">
                <h1 class="text-lg font-semibold text-slate-900 lg:text-xl">Kontrol Merkezi</h1>
                <p class="mt-0.5 text-sm text-slate-500">Entegrasyon, sipariş ve finans akışını tek noktadan izleyin.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('mp.orders') }}" class="inline-flex min-h-[36px] items-center justify-center rounded-[6px] bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800">Siparişler</a>
                <a href="{{ route('mp.products') }}" class="inline-flex min-h-[36px] items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">Ürünler</a>
                <a href="{{ route('mp.finance') }}" class="inline-flex min-h-[36px] items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">Finans</a>
                @if(config('marketplace.features.profit_center_enabled'))
                    <a href="{{ route('mp.profit-center') }}" class="inline-flex min-h-[36px] items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">Sipariş Kârlılığı</a>
                @endif
            </div>
        </div>

        {{-- KPI Band — kompakt tek satır --}}
        <div class="border-t border-slate-100 px-4 py-3 lg:px-6">
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/70 px-3 py-3">
                    <p class="text-xs font-medium text-slate-500">Aktif Mağaza</p>
                    <p class="mt-1 text-xl font-bold text-slate-900">{{ $formatCount($heroStats['active_stores']) }}</p>
                </div>
                <div class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/70 px-3 py-3">
                    <p class="text-xs font-medium text-slate-500">Net Alacak</p>
                    <p class="mt-1 text-xl font-bold text-emerald-700">{{ $formatMoney($heroStats['net_receivable']) }}</p>
                </div>
                <a href="{{ route('mp.finance', ['deltaStateFilter' => 'material']) }}" class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/70 px-3 py-3 transition hover:border-slate-300 hover:bg-white group">
                    <p class="text-xs font-medium text-slate-500">Sorunlu Siparişler</p>
                    <p class="mt-1 text-xl font-bold {{ $reconciliationStats['material_orders'] > 0 ? 'text-rose-600 group-hover:text-rose-700' : 'text-slate-900' }}">{{ $formatCount($reconciliationStats['material_orders']) }}</p>
                </a>
                <a href="{{ route('mp.matching', ['statusFilter' => 'pending']) }}" class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/70 px-3 py-3 transition hover:border-slate-300 hover:bg-white group">
                    <p class="text-xs font-medium text-slate-500">Eşleşmeyen Ürün</p>
                    <p class="mt-1 text-xl font-bold {{ $healthStats['open_match_issues'] > 0 ? 'text-amber-600 group-hover:text-amber-700' : 'text-slate-900' }}">{{ $formatCount($healthStats['open_match_issues']) }}</p>
                </a>
            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════
         2. ONBOARDING — kompakt hazırlık rehberi
    ═══════════════════════════════════════════ --}}
    @if(($onboarding['enabled'] ?? false) && config('marketplace.features.onboarding_guide_enabled', true))
        <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm" x-data="{ expanded: {{ $onboardingPercent < 50 ? 'true' : 'false' }} }">
            <button type="button" @click="expanded = !expanded" class="flex w-full items-center justify-between gap-4 p-4 text-left lg:px-6 lg:py-4">
                <div class="flex items-center gap-3 min-w-0">
                    <h2 class="text-sm font-semibold text-slate-900">Veri Hazırlık Rehberi</h2>
                    <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-mono text-slate-500">
                        {{ $onboarding['completed_steps'] ?? 0 }}/{{ $onboarding['total_steps'] ?? 8 }}
                    </span>
                </div>
                <div class="flex items-center gap-3 shrink-0">
                    <div class="hidden sm:flex items-center gap-2 min-w-[140px]">
                        <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-slate-900 transition-all duration-500" style="width: {{ $onboardingPercent }}%"></div>
                        </div>
                        <span class="text-xs font-mono text-slate-500">%{{ $onboardingPercent }}</span>
                    </div>
                    <svg class="h-4 w-4 text-slate-400 transition-transform" :class="expanded && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </div>
            </button>

            <div x-show="expanded" x-collapse>
                <div class="border-t border-slate-100 p-4 lg:px-6 lg:pb-6">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        @foreach($onboarding['steps'] ?? [] as $step)
                            <div class="min-w-0 rounded-[8px] border {{ $step['status'] === 'completed' ? 'border-emerald-200 bg-emerald-50/40' : ($step['status'] === 'action' ? 'border-amber-200 bg-amber-50/30' : 'border-slate-200 bg-slate-50/60') }} p-3">
                                <div class="flex items-center gap-2">
                                    @if($step['status'] === 'completed')
                                        <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                        </span>
                                    @else
                                        <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border border-slate-200 bg-white text-[10px] font-bold text-slate-500">{{ $step['number'] }}</span>
                                    @endif
                                    <p class="truncate text-sm font-medium text-slate-900">{{ $step['title'] }}</p>
                                </div>
                                <p class="mt-2 text-xs leading-5 text-slate-500 line-clamp-2">{{ $step['description'] }}</p>
                                @if($step['status'] !== 'completed')
                                    <a href="{{ $step['action_url'] }}" class="mt-2 inline-flex w-full min-h-[32px] items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50">
                                        {{ $step['action_label'] }}
                                    </a>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    @if(!empty($onboarding['primary_action']['url'] ?? null))
                        <div class="mt-4 flex flex-col gap-2 rounded-[8px] border border-slate-200 bg-slate-50/60 p-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-slate-900">{{ $onboarding['primary_action']['title'] ?? 'Sonraki adım' }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">{{ $onboarding['summary'] ?? 'Veri hazırlık durumunu kontrol edin.' }}</p>
                            </div>
                            <a href="{{ $onboarding['primary_action']['url'] }}" class="inline-flex min-h-[36px] shrink-0 items-center justify-center rounded-[6px] bg-slate-900 px-4 py-2 text-xs font-medium text-white transition hover:bg-slate-800">
                                {{ $onboarding['primary_action']['label'] ?? 'Devam et' }}
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </section>
    @endif

    {{-- ═══════════════════════════════════════════
         3. ANA İÇERİK — 2 kolon grid
    ═══════════════════════════════════════════ --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 lg:gap-6">

        {{-- Aksiyon Rehberi --}}
        <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm flex flex-col">
            <div class="flex items-center justify-between gap-3 p-4 lg:px-6 lg:pt-5 lg:pb-4">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">Aksiyon Rehberi</h2>
                    <p class="mt-0.5 text-xs text-slate-500">Müdahale gerektiren güncel uyarılar</p>
                </div>
                <button type="button" wire:click="exportDiagnosticsGuidanceCsv" class="shrink-0 rounded-[6px] border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50 transition">Rapor</button>
            </div>

            <div class="flex-grow space-y-2 px-4 pb-4 lg:px-6 lg:pb-5">
                @forelse(array_slice($diagnosticsGuidance['items'], 0, 4) as $item)
                    <a href="{{ $this->guidanceRoute($item) }}" class="flex items-center justify-between gap-3 rounded-[8px] border border-slate-200 bg-slate-50/70 p-3 transition hover:bg-white hover:border-slate-300">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-900 truncate">{{ $item['title'] }}</p>
                            <p class="mt-0.5 text-xs text-slate-500 truncate">{{ $item['store_name'] ?: 'Genel' }} · {{ $item['recommended_action'] }}</p>
                        </div>
                        <x-zolm.status-badge :tone="$this->guidanceSeverityTone($item['severity'])">{{ $this->guidanceSeverityLabel($item['severity']) }}</x-zolm.status-badge>
                    </a>
                @empty
                    <div class="flex h-full min-h-[100px] flex-col items-center justify-center rounded-[8px] border border-dashed border-slate-200 bg-slate-50/50 p-4 text-center">
                        <svg class="mb-1.5 h-5 w-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 13l4 4L19 7"/></svg>
                        <p class="text-sm font-medium text-slate-700">Temiz</p>
                        <p class="mt-0.5 text-xs text-slate-500">Acil uyarı bulunmuyor.</p>
                    </div>
                @endforelse
            </div>
        </section>

        {{-- Operasyon Sağlığı --}}
        <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm flex flex-col">
            <div class="p-4 lg:px-6 lg:pt-5 lg:pb-4">
                <h2 class="text-sm font-semibold text-slate-900">Operasyon Sağlığı</h2>
                <p class="mt-0.5 text-xs text-slate-500">Son 24 saat senkronizasyon durumu</p>
            </div>

            <div class="flex-grow px-4 pb-4 lg:px-6 lg:pb-5">
                <div class="grid grid-cols-3 gap-3">
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 px-3 py-3 text-center">
                        <p class="text-xs font-medium text-slate-500">Başarılı</p>
                        <p class="mt-1 text-xl font-bold text-emerald-700">{{ $formatCount($healthStats['completed_syncs']) }}</p>
                    </div>
                    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 px-3 py-3 text-center">
                        <p class="text-xs font-medium text-slate-500">Kuyruk</p>
                        <p class="mt-1 text-xl font-bold text-sky-700">{{ $formatCount($healthStats['processing_syncs'] + $healthStats['queued_pushes'] + $healthStats['queued_actions']) }}</p>
                    </div>
                    <div class="rounded-[8px] border border-slate-200 {{ $totalErrors > 0 ? 'bg-rose-50/50 border-rose-200' : 'bg-slate-50/70' }} px-3 py-3 text-center">
                        <p class="text-xs font-medium {{ $totalErrors > 0 ? 'text-rose-600' : 'text-slate-500' }}">Hata</p>
                        <p class="mt-1 text-xl font-bold {{ $totalErrors > 0 ? 'text-rose-700' : 'text-slate-900' }}">{{ $formatCount($totalErrors) }}</p>
                    </div>
                </div>

                <div class="mt-4 flex items-center justify-between gap-3">
                    @if($totalErrors > 0)
                        <p class="text-xs text-slate-500">{{ $totalErrors }} hata düzeltme bekliyor</p>
                        <button type="button" wire:click="repairFailedOperations" wire:loading.attr="disabled" class="shrink-0 rounded-[6px] bg-slate-900 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-slate-800 disabled:opacity-50">
                            <span wire:loading.remove wire:target="repairFailedOperations">Onarım Başlat</span>
                            <span wire:loading wire:target="repairFailedOperations">Onarılıyor…</span>
                        </button>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-[6px] bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">
                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Sistem Temiz
                        </span>
                    @endif
                </div>
            </div>
        </section>
    </div>

    {{-- ═══════════════════════════════════════════
         4. LEGACY PROJECTION — sadece pending > 0 ise, accordion
    ═══════════════════════════════════════════ --}}
    @if($pendingLegacy > 0)
        <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm" x-data="{ open: false }">
            <button type="button" @click="open = !open" class="flex w-full items-center justify-between gap-4 p-4 text-left lg:px-6 lg:py-4">
                <div class="flex items-center gap-3 min-w-0">
                    <h2 class="text-sm font-semibold text-slate-900">Legacy Projection Etkisi</h2>
                    <span class="rounded-[6px] border border-amber-200 bg-amber-50 px-2 py-0.5 text-xs font-mono text-amber-700">{{ $formatCount($pendingLegacy) }} bekleyen</span>
                </div>
                <svg class="h-4 w-4 shrink-0 text-slate-400 transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>

            <div x-show="open" x-collapse>
                <div class="border-t border-slate-100 p-4 lg:px-6 lg:pb-6">
                    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 px-3 py-3">
                            <p class="text-xs font-medium text-slate-500">Bekleyen</p>
                            <p class="mt-1 text-xl font-bold text-amber-700">{{ $formatCount($legacyProjectionSummary['pending_rows'] ?? 0) }}</p>
                        </div>
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 px-3 py-3">
                            <p class="text-xs font-medium text-slate-500">Tamamlanan</p>
                            <p class="mt-1 text-xl font-bold text-slate-900">{{ $formatCount($legacyProjectionSummary['projected_rows'] ?? 0) }}</p>
                        </div>
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 px-3 py-3">
                            <p class="text-xs font-medium text-slate-500">Legacy Olay</p>
                            <p class="mt-1 text-xl font-bold text-slate-900">{{ $formatCount($legacyProjectionSummary['legacy_event_orders'] ?? 0) }}</p>
                        </div>
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 px-3 py-3">
                            <p class="text-xs font-medium text-slate-500">Kesin Sipariş</p>
                            <p class="mt-1 text-xl font-bold text-emerald-700">{{ $formatCount($legacyProjectionSummary['confirmed_orders'] ?? 0) }}</p>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-2">
                        {{-- Mağaza kırılımı --}}
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                            <h3 class="text-xs font-semibold text-slate-900 mb-2">Mağaza kırılımı</h3>
                            <div class="space-y-1.5">
                                @foreach(array_slice($legacyProjectionStoreRows, 0, 5) as $row)
                                    <div class="flex items-center justify-between gap-3 rounded-[6px] border border-slate-200 bg-white px-3 py-2">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-medium text-slate-900">{{ $row['store_name'] }}</p>
                                            <p class="text-[11px] text-slate-500">{{ $this->humanMarketplace($row['marketplace']) }}</p>
                                        </div>
                                        <div class="text-right text-xs shrink-0">
                                            <p class="text-amber-700">{{ $row['pending_rows'] }} bekleyen</p>
                                            <p class="text-emerald-700">{{ $row['confirmed_orders'] }} kesin</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Pilot geçiş --}}
                        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3">
                            <h3 class="text-xs font-semibold text-slate-900 mb-2">Pilot Canlıya Geçiş</h3>
                            <div class="space-y-1.5">
                                @foreach(array_slice($pilotRolloutRows, 0, 5) as $row)
                                    <div class="flex items-center justify-between gap-3 rounded-[6px] border border-slate-200 bg-white px-3 py-2">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-medium text-slate-900">{{ $row['store_name'] }}</p>
                                            <p class="text-[11px] text-slate-500">{{ $this->pilotRolloutStageLabel($row['stage']) }}</p>
                                        </div>
                                        <div class="flex gap-1.5 shrink-0">
                                            <button type="button" wire:click="previewLegacyProjection({{ $row['store_id'] }})" class="rounded-[6px] border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50">Önizle</button>
                                            <button type="button" wire:click="runLegacyProjection({{ $row['store_id'] }})" class="rounded-[6px] bg-slate-900 px-2.5 py-1 text-xs font-medium text-white hover:bg-slate-800">Aktar</button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    @endif
</div>
