@php
    $formatMoney = fn ($value) => '₺' . number_format((float) $value, 2, ',', '.');
    $formatCount = fn ($value) => number_format((float) $value, 0, ',', '.');
@endphp

<div class="mp-overview-page w-full space-y-4 lg:space-y-6 overflow-hidden">
    @if($flashMessage)
        <div class="rounded-[8px] border p-4 text-sm {{ $flashTone === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-slate-50 text-slate-700' }}">
            {{ $flashMessage }}
        </div>
    @endif

    <!-- 1. KONTROL MERKEZİ (ÜST KART) -->
    <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm p-4 lg:p-6">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl lg:text-2xl font-bold text-slate-900">Pazaryeri Kontrol Merkezi</h1>
                <p class="mt-1 text-sm text-slate-500">Entegrasyon, sipariş ve finans akışını tek noktadan izleyin.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('mp.orders') }}" class="inline-flex min-h-[36px] items-center justify-center rounded-[6px] bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800">Siparişler</a>
                <a href="{{ route('mp.products') }}" class="inline-flex min-h-[36px] items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">Ürünler</a>
                <a href="{{ route('mp.finance') }}" class="inline-flex min-h-[36px] items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">Finans</a>
            </div>
        </div>

        <!-- Önemli Metrikler -->
        <div class="mt-6 grid grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4">
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Aktif Mağaza</p>
                <p class="mt-2 text-2xl font-bold text-slate-900">{{ $formatCount($heroStats['active_stores']) }}</p>
            </div>
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Net Alacak</p>
                <p class="mt-2 text-2xl font-bold text-emerald-600">{{ $formatMoney($heroStats['net_receivable']) }}</p>
            </div>
            <a href="{{ route('mp.finance', ['deltaStateFilter' => 'material']) }}" class="block rounded-[8px] border border-slate-200 bg-slate-50/70 p-4 transition duration-200 hover:border-slate-300 hover:bg-white cursor-pointer group">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Materyal Fark</p>
                <p class="mt-2 text-2xl font-bold transition group-hover:text-rose-700 {{ $reconciliationStats['material_orders'] > 0 ? 'text-rose-600' : 'text-slate-900' }}">{{ $formatCount($reconciliationStats['material_orders']) }}</p>
            </a>
            <a href="{{ route('mp.matching', ['statusFilter' => 'pending']) }}" class="block rounded-[8px] border border-slate-200 bg-slate-50/70 p-4 transition duration-200 hover:border-slate-300 hover:bg-white cursor-pointer group">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Açık Sorun</p>
                <p class="mt-2 text-2xl font-bold transition group-hover:text-amber-700 {{ $healthStats['open_match_issues'] > 0 ? 'text-amber-600' : 'text-slate-900' }}">{{ $formatCount($healthStats['open_match_issues']) }}</p>
            </a>
        </div>
    </section>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 lg:gap-6">
        <!-- 2. OPERASYON VE HATA YÖNETİMİ -->
        <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm p-4 lg:p-6 flex flex-col">
            <div>
                <h2 class="text-base font-bold text-slate-900">Operasyon Sağlığı</h2>
                <p class="mt-1 text-sm text-slate-500">Sistem arkası senkronizasyon durumu.</p>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-3 mb-6">
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4 text-center">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Başarılı Senkron</p>
                    <p class="mt-2 text-2xl font-bold text-emerald-600">{{ $formatCount($healthStats['completed_syncs']) }}</p>
                </div>
                <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4 text-center">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">İş Kuyruğu</p>
                    <p class="mt-2 text-2xl font-bold text-sky-600">{{ $formatCount($healthStats['processing_syncs'] + $healthStats['queued_pushes'] + $healthStats['queued_actions']) }}</p>
                </div>
            </div>

            <div class="mt-auto border-t border-slate-100 pt-4">
                @php $totalErrors = $healthStats['failed_syncs'] + $healthStats['failed_pushes'] + $healthStats['failed_actions'] + $healthStats['failed_webhooks']; @endphp
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-slate-900">Hata Kayıtları</p>
                        <p class="text-xs text-slate-500 mt-0.5">{{ $totalErrors }} hata düzeltme bekliyor.</p>
                    </div>
                    @if($totalErrors > 0)
                        <button type="button" wire:click="repairFailedOperations" wire:loading.attr="disabled" class="min-h-[36px] rounded-[6px] bg-slate-900 px-4 py-2 text-xs font-semibold text-white transition hover:bg-slate-800 disabled:opacity-50 inline-flex items-center gap-2">
                            <span wire:loading.remove wire:target="repairFailedOperations">Toplu Onarım Başlat</span>
                            <span wire:loading wire:target="repairFailedOperations">Onarılıyor...</span>
                        </button>
                    @else
                        <span class="rounded-[6px] bg-emerald-50 text-emerald-700 px-3 py-1.5 text-xs font-medium inline-flex self-start">Sistem Temiz</span>
                    @endif
                </div>
            </div>
        </section>

        <!-- 3. TEŞHİS VE YÖNLENDİRME REHBERİ -->
        <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm p-4 lg:p-6 flex flex-col">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Aksiyon Rehberi</h2>
                    <p class="mt-1 text-sm text-slate-500">Müdahale gerektiren güncel uyarılar.</p>
                </div>
                <button type="button" wire:click="exportDiagnosticsGuidanceCsv" class="min-h-[36px] rounded-[6px] border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50 inline-flex self-start">Rapor İndir</button>
            </div>

            <div class="space-y-2 flex-grow">
                @forelse(array_slice($diagnosticsGuidance['items'], 0, 4) as $item)
                    <a href="{{ $this->guidanceRoute($item) }}" class="flex items-center justify-between gap-3 rounded-[8px] border border-slate-200 bg-slate-50/70 p-3 transition hover:bg-white hover:border-slate-300">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-900 truncate">{{ $item['title'] }}</p>
                            <p class="mt-0.5 text-xs text-slate-500 truncate">{{ $item['store_name'] ?: 'Genel Sistem' }} - {{ $item['recommended_action'] }}</p>
                        </div>
                        <x-zolm.status-badge :tone="$this->guidanceSeverityTone($item['severity'])">{{ $this->guidanceSeverityLabel($item['severity']) }}</x-zolm.status-badge>
                    </a>
                @empty
                    <div class="flex h-full min-h-[120px] flex-col items-center justify-center rounded-[8px] border border-dashed border-slate-300 bg-slate-50 p-6 text-center">
                        <svg class="mb-2 h-6 w-6 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 13l4 4L19 7" />
                        </svg>
                        <p class="text-sm font-medium text-slate-900">Harika gidiyorsunuz</p>
                        <p class="mt-1 text-xs text-slate-500">Acil bir teşhis uyarısı bulunmuyor.</p>
                    </div>
                @endforelse
            </div>
        </section>
    </div>

    <section class="rounded-[10px] border border-slate-200 bg-white shadow-sm p-4 lg:p-6">
        <h2 class="text-base font-bold text-slate-900">Legacy Projection Etkisi</h2>
        <p class="mt-1 text-sm text-slate-500">Legacy finans satirlari V2 ledger'a tasinmamis durumunu ve aktarım etkisini izleyin.</p>

        <div class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Bekleyen</p>
                <p class="mt-2 text-2xl font-bold text-amber-600">{{ $formatCount($legacyProjectionSummary['pending_rows'] ?? 0) }}</p>
            </div>
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Tamamlanan</p>
                <p class="mt-2 text-2xl font-bold text-slate-900">{{ $formatCount($legacyProjectionSummary['projected_rows'] ?? 0) }}</p>
            </div>
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Legacy Olay</p>
                <p class="mt-2 text-2xl font-bold text-slate-900">{{ $formatCount($legacyProjectionSummary['legacy_event_orders'] ?? 0) }}</p>
            </div>
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Kesin Sipariş</p>
                <p class="mt-2 text-2xl font-bold text-emerald-600">{{ $formatCount($legacyProjectionSummary['confirmed_orders'] ?? 0) }}</p>
            </div>
        </div>

        <div class="mt-5 grid grid-cols-1 gap-4 xl:grid-cols-2">
            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <h3 class="text-sm font-semibold text-slate-900">Mağaza kırılımı</h3>
                <div class="mt-3 space-y-2">
                    @foreach(array_slice($legacyProjectionStoreRows, 0, 6) as $row)
                        <div class="rounded-[6px] border border-slate-200 bg-white px-3 py-2">
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-slate-900">{{ $row['store_name'] }}</p>
                                    <p class="mt-0.5 text-xs text-slate-500">{{ $this->humanMarketplace($row['marketplace']) }}</p>
                                </div>
                                <div class="text-right text-xs">
                                    <p class="text-amber-700">Bekleyen {{ $row['pending_rows'] }}</p>
                                    <p class="text-emerald-700">Kesine dönen {{ $row['confirmed_orders'] }}</p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <h3 class="text-sm font-semibold text-slate-900">Pilot Canlıya Geçiş</h3>
                <div class="mt-3 space-y-2">
                    @foreach(array_slice($pilotRolloutRows, 0, 6) as $row)
                        <div class="rounded-[6px] border border-slate-200 bg-white px-3 py-2">
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-slate-900">{{ $row['store_name'] }}</p>
                                    <p class="mt-0.5 text-xs text-slate-500">{{ $this->pilotRolloutStageLabel($row['stage']) }}</p>
                                </div>
                                <div class="flex gap-2">
                                    <button type="button" wire:click="previewLegacyProjection({{ $row['store_id'] }})" class="inline-flex min-h-[32px] items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                        Önizle
                                    </button>
                                    <button type="button" wire:click="runLegacyProjection({{ $row['store_id'] }})" class="inline-flex min-h-[32px] items-center justify-center rounded-[6px] bg-slate-900 px-3 py-1 text-xs font-medium text-white hover:bg-slate-800">
                                        Aktar
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>
</div>
