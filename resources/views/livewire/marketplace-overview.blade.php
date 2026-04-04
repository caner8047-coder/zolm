@php
    $formatMoney = fn ($value) => '₺' . number_format((float) $value, 2, ',', '.');
    $formatCount = fn ($value) => number_format((float) $value, 0, ',', '.');
@endphp

@once
    <style>
        .mp-overview-page .rounded-2xl { border-radius: 10px; }
        .mp-overview-page .rounded-xl { border-radius: 8px; }
        .mp-overview-page .rounded-lg { border-radius: 6px; }
        .mp-overview-page .rounded-md { border-radius: 6px; }
        .mp-overview-page > .space-y-6 > section,
        .mp-overview-page > section {
            border-color: rgb(226 232 240 / 0.9);
            box-shadow: 0 1px 2px rgb(15 23 42 / 0.04);
        }
        .mp-overview-surface {
            background:
                radial-gradient(circle at top left, rgba(191, 219, 254, 0.18), transparent 28%),
                linear-gradient(180deg, rgba(248, 250, 252, 0.96), rgba(255, 255, 255, 1));
        }
        .mp-overview-stat {
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.92), rgba(248, 250, 252, 0.92));
        }
        .mp-overview-page .overview-section-kicker {
            letter-spacing: 0.22em;
        }
        @media (max-width: 640px) {
            .mp-overview-page {
                margin-top: -0.25rem;
            }
        }
    </style>
@endonce

<div class="mp-overview-page w-full space-y-5 overflow-hidden">
    @if($flashMessage)
        <div class="rounded-lg border p-4 text-sm {{ $flashTone === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-slate-50 text-slate-700' }}">
            {{ $flashMessage }}
        </div>
    @endif

    <section class="mp-overview-surface rounded-2xl border border-slate-200 p-4 lg:p-6 shadow-sm">
        <div class="grid grid-cols-1 gap-4 xl:grid-cols-12">
            <div class="xl:col-span-5 rounded-2xl border border-slate-200 bg-white p-5 lg:p-6">
                <div class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-[11px] font-semibold uppercase overview-section-kicker text-slate-500">
                    Pazaryeri Nabız Panosu
                </div>
                <h1 class="mt-4 text-3xl font-bold tracking-tight text-slate-900 lg:text-4xl">Kontrol Merkezi</h1>
                <p class="mt-3 max-w-xl text-sm leading-6 text-slate-500 lg:text-base">
                    Entegrasyon, sipariş, ürün ve finans akışını tek bir kurumsal görüşte izleyin. Yoğun operasyon anında hangi modüle geçmeniz gerektiği burada netleşir.
                </p>

                <div class="mt-6 grid grid-cols-2 gap-3">
                    <a href="{{ route('mp.orders') }}" class="inline-flex min-h-[48px] items-center justify-center rounded-lg border border-slate-200 bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800">Siparişler</a>
                    <a href="{{ route('mp.products') }}" class="inline-flex min-h-[48px] items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50">Ürünler</a>
                    <a href="{{ route('mp.finance') }}" class="inline-flex min-h-[48px] items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50">Finans</a>
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" type="button" class="inline-flex min-h-[48px] w-full items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                            Raporlar
                            <svg class="h-4 w-4 transition" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 9l-7 7-7-7" /></svg>
                        </button>
                        <div x-cloak x-show="open" @click.outside="open = false" x-transition class="absolute left-0 right-0 top-full z-30 mt-2 rounded-2xl border border-slate-200 bg-white p-2 shadow-xl sm:left-auto sm:right-0 sm:w-56">
                            <button type="button" wire:click="exportHealthReportCsv" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50"><span>Sağlık raporu</span><span class="text-xs text-slate-400">CSV</span></button>
                            <button type="button" wire:click="exportFailureReportCsv" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50"><span>Hata raporu</span><span class="text-xs text-slate-400">CSV</span></button>
                            <button type="button" wire:click="exportDiagnosticsReportCsv" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50"><span>Teşhis raporu</span><span class="text-xs text-slate-400">CSV</span></button>
                            <button type="button" wire:click="exportDiagnosticsGuidanceCsv" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50"><span>Öncelik rehberi</span><span class="text-xs text-slate-400">CSV</span></button>
                        </div>
                    </div>
                </div>

                <div class="mt-6 grid grid-cols-2 gap-2 text-xs text-slate-600 sm:grid-cols-4">
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="uppercase tracking-[0.18em] text-slate-400">Sipariş</p>
                        <p class="mt-1 font-semibold text-slate-900">{{ $formatCount($heroStats['total_orders']) }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="uppercase tracking-[0.18em] text-slate-400">Listeleme</p>
                        <p class="mt-1 font-semibold text-slate-900">{{ $formatCount($heroStats['total_listings']) }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="uppercase tracking-[0.18em] text-slate-400">Senkron</p>
                        <p class="mt-1 font-semibold text-slate-900">{{ $formatCount($healthStats['processing_syncs']) }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="uppercase tracking-[0.18em] text-slate-400">Sorun</p>
                        <p class="mt-1 font-semibold text-slate-900">{{ $formatCount($healthStats['open_match_issues']) }}</p>
                    </div>
                </div>
            </div>

            <div class="xl:col-span-4 grid grid-cols-2 gap-3">
                <div class="mp-overview-stat rounded-2xl border border-slate-200 p-4 lg:p-5">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Aktif mağaza</p>
                    <p class="mt-3 text-3xl font-bold text-slate-900">{{ $formatCount($heroStats['active_stores']) }}</p>
                    <p class="mt-2 text-sm text-slate-500">{{ $heroStats['total_stores'] }} toplam bağlantı</p>
                </div>
                <div class="mp-overview-stat rounded-2xl border border-slate-200 p-4 lg:p-5">
                    <div class="flex items-center gap-1.5">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Net alacak</p>
                        <x-zolm.help-tip title="Net alacak" summary="Kesinleşen hakedişlerden, kesinti ve iade etkileri düşüldükten sonra beklenen tahsilatı gösterir." source="Sipariş snapshot'ları, finans olayları ve kesinti toplamları." refresh="Finans senkronu veya mutabakat güncellendiğinde yenilenir." impact="Tahsilat önceliğini ve finans modülüne inilecek mağazayı belirler." />
                    </div>
                    <p class="mt-3 text-3xl font-bold text-emerald-600">{{ $formatMoney($heroStats['net_receivable']) }}</p>
                    <p class="mt-2 text-sm text-slate-500">Kesin kâr {{ $formatMoney($heroStats['confirmed_profit']) }}</p>
                </div>
                <a href="{{ route('mp.finance', ['deltaStateFilter' => 'material']) }}" class="mp-overview-stat rounded-2xl border border-slate-200 p-4 transition hover:border-slate-300 hover:bg-white lg:p-5">
                    <div class="flex items-center gap-1.5">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Materyal fark</p>
                        <x-zolm.help-tip title="Materyal fark" summary="Tahmini kâr ile kesin finans sonuçları arasında anlamlı fark çıkan sipariş sayısını verir." source="Kâr snapshot'ı, kesin hakediş ve kesinti hareketleri." refresh="Mutabakat veya yeni finans olayı işlendiğinde." impact="Finans ekranında hangi siparişlerin detay inceleme istediğini öne çıkarır." />
                    </div>
                    <p class="mt-3 text-3xl font-bold {{ $reconciliationStats['material_orders'] > 0 ? 'text-rose-600' : 'text-slate-900' }}">{{ $formatCount($reconciliationStats['material_orders']) }}</p>
                    <p class="mt-2 text-sm text-slate-500">{{ $reconciliationStats['minor_orders'] }} izleme alanında</p>
                </a>
                <a href="{{ route('mp.orders') }}" class="rounded-2xl border {{ $legacyProjectionSummary['pending_rows'] > 0 ? 'border-amber-200 bg-amber-50/80' : 'border-slate-200 bg-white/90' }} p-4 transition hover:border-slate-300 lg:p-5">
                    <div class="flex items-center gap-1.5">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] {{ $legacyProjectionSummary['pending_rows'] > 0 ? 'text-amber-700' : 'text-slate-400' }}">Eski veri kuyruğu</p>
                        <x-zolm.help-tip title="Eski veri kuyruğu" summary="Eski veri finans satırlarından V2 kayıt defterine henüz taşınmamış kalan yükü gösterir." source="Yansıtma önizlemesi ve eski veri taşıma kayıtları." refresh="Deneme, yansıtma veya taşıma aksiyonu çalıştığında." impact="Köprü riski olan mağazaları ve siparişleri önceliklendirir." />
                    </div>
                    <p class="mt-3 text-3xl font-bold {{ $legacyProjectionSummary['pending_rows'] > 0 ? 'text-amber-700' : 'text-slate-900' }}">{{ $formatCount($legacyProjectionSummary['pending_rows']) }}</p>
                    <p class="mt-2 text-sm {{ $legacyProjectionSummary['pending_rows'] > 0 ? 'text-amber-800/80' : 'text-slate-500' }}">{{ $legacyProjectionSummary['confirmed_orders'] }} siparişte kesin etki</p>
                </a>
            </div>

            <div class="xl:col-span-3 rounded-2xl border border-slate-200 bg-white/85 p-4 lg:p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">Bugünkü yönlendirme</p>
                        <h2 class="mt-2 text-lg font-semibold text-slate-900">Modül rotası</h2>
                    </div>
                    <span class="rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[11px] font-medium text-emerald-700">Canlı</span>
                </div>
                <div class="mt-4 space-y-3">
                    <a href="{{ route('mp.finance', ['deltaStateFilter' => 'waiting']) }}" class="block rounded-lg border border-slate-200 bg-slate-50/80 px-4 py-3 transition hover:bg-white">
                        <p class="text-xs uppercase tracking-[0.16em] text-slate-400">Önce finans</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ $formatCount($reconciliationStats['waiting_orders']) }} sipariş finans bekliyor</p>
                    </a>
                    <a href="{{ route('mp.matching', ['statusFilter' => 'pending']) }}" class="block rounded-lg border border-slate-200 bg-slate-50/80 px-4 py-3 transition hover:bg-white">
                        <p class="text-xs uppercase tracking-[0.16em] text-slate-400">Sonra eşleşme</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ $formatCount($healthStats['open_match_issues']) }} açık sorun var</p>
                    </a>
                    <a href="{{ route('mp.orders') }}" class="block rounded-lg border border-slate-200 bg-slate-50/80 px-4 py-3 transition hover:bg-white">
                        <p class="text-xs uppercase tracking-[0.16em] text-slate-400">Eski veri köprüsü</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ $formatCount($legacyProjectionSummary['pending_rows']) }} kuyruk satırı izleniyor</p>
                    </a>
                </div>
            </div>
        </div>
    </section>

    {{-- OPERASYON SAĞLIĞI --}}
    <section x-data="{ opsTab: 'sync', legacyOpen: false, repairOpen: false }" class="rounded-2xl border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
        <h2 class="text-lg font-bold text-slate-900">Operasyon Sağlığı</h2>
        <p class="mt-1 text-sm text-slate-500">Bugünkü iş akışı ve son hareketler</p>

        <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-3 lg:gap-4">
            <div class="flex flex-col justify-center rounded-xl border border-slate-200 bg-slate-50 p-4 lg:p-5">
                <p class="text-[10px] lg:text-xs uppercase tracking-[0.2em] font-medium text-slate-500 line-clamp-1 text-ellipsis overflow-hidden">Başarılı senkron</p>
                <p class="mt-2 text-2xl lg:text-3xl font-bold text-emerald-600">{{ $formatCount($healthStats['completed_syncs']) }}</p>
                <p class="mt-1 text-[11px] font-medium text-slate-500 truncate">Son 24 saat</p>
            </div>
            <div class="flex flex-col justify-center rounded-xl border border-slate-200 bg-slate-50 p-4 lg:p-5">
                <div class="flex items-center gap-1.5">
                    <p class="text-[10px] lg:text-xs uppercase tracking-[0.2em] font-medium text-slate-500 line-clamp-1 text-ellipsis overflow-hidden">Açık sorun</p>
                    <x-zolm.help-tip title="Açık sorun" summary="Başarısız senkron, gönderim, aksiyon ve webhook denemelerinin toplam aktif yükünü verir." source="Son operasyon kayıtları ve hata durumları." refresh="Her yeni işlem sonucu işlendiğinde." impact="Operasyon sağlığı panelinde önce hangi soruna eğileceğinizi gösterir." />
                </div>
                <p class="mt-2 text-2xl lg:text-3xl font-bold {{ ($healthStats['failed_syncs'] + $healthStats['failed_pushes'] + $healthStats['failed_actions'] + $healthStats['failed_webhooks']) > 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ $formatCount($healthStats['failed_syncs'] + $healthStats['failed_pushes'] + $healthStats['failed_actions'] + $healthStats['failed_webhooks']) }}</p>
            </div>
            <div class="flex flex-col justify-center rounded-xl border border-slate-200 bg-slate-50 p-4 lg:p-5">
                <div class="flex items-center gap-1.5">
                    <p class="text-[10px] lg:text-xs uppercase tracking-[0.2em] font-medium text-slate-500 line-clamp-1 text-ellipsis overflow-hidden">İş kuyruğu</p>
                    <x-zolm.help-tip title="İş kuyruğu" summary="Arka planda işlenmekte olan senkron, gönderim ve aksiyon toplamını gösterir." source="Kuyruk üzerindeki aktif operasyon kayıtları." refresh="Kuyruk ilerledikçe ve yeni iş eklendikçe." impact="Sistemin şu an yoğun mu sakin mi çalıştığını anlamanızı sağlar." />
                </div>
                <p class="mt-2 text-2xl lg:text-3xl font-bold text-sky-600">{{ $formatCount($healthStats['processing_syncs'] + $healthStats['queued_pushes'] + $healthStats['queued_actions']) }}</p>
            </div>
            <div class="flex flex-col justify-center rounded-xl border border-slate-200 bg-slate-50 p-4 lg:p-5">
                <div class="flex items-center gap-1.5">
                    <p class="text-[10px] lg:text-xs uppercase tracking-[0.2em] font-medium text-slate-500 line-clamp-1 text-ellipsis overflow-hidden">Bekleyen finans</p>
                    <x-zolm.help-tip title="Bekleyen finans" summary="Siparişi gelmiş ama kesin finans olayı henüz oluşmamış kayıtları gösterir." source="Channel order kayıtları ve financial event eşleşmesi." refresh="Yeni hakediş veya kesinti olayı işlendiğinde." impact="Finans modülünde önce hangi siparişlerin tamamlanması gerektiğini işaret eder." />
                </div>
                <p class="mt-2 text-2xl lg:text-3xl font-bold {{ $healthStats['pending_financial_events'] > 0 ? 'text-amber-600' : 'text-emerald-600' }}">{{ $formatCount($healthStats['pending_financial_events']) }}</p>
            </div>
        </div>

        {{-- Sekme tabları --}}
        <div class="mt-4 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
            <div class="inline-flex flex-wrap rounded-lg border border-slate-200 bg-slate-50 p-1">
                <button type="button" @click="opsTab = 'sync'" :class="opsTab === 'sync' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-white'" class="rounded-md px-3 py-2 text-sm font-medium transition">Senkron</button>
                <button type="button" @click="opsTab = 'push'" :class="opsTab === 'push' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-white'" class="rounded-md px-3 py-2 text-sm font-medium transition">Gönderim</button>
                <button type="button" @click="opsTab = 'action'" :class="opsTab === 'action' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-white'" class="rounded-md px-3 py-2 text-sm font-medium transition">Aksiyon</button>
                <button type="button" @click="opsTab = 'webhook'" :class="opsTab === 'webhook' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-white'" class="rounded-md px-3 py-2 text-sm font-medium transition">Webhook</button>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" @click="repairOpen = !repairOpen" class="inline-flex min-h-[36px] items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50">Toplu Onarım</button>
            </div>
        </div>

        {{-- Onarım paneli --}}
        <div x-cloak x-show="repairOpen" x-transition class="mt-3 rounded-lg border border-slate-200 bg-slate-50 p-4">
            <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500 mb-3">
                <span class="rounded-full border border-slate-200 bg-white px-3 py-1">Senkron {{ $formatCount($healthStats['failed_syncs']) }}</span>
                <span class="rounded-full border border-slate-200 bg-white px-3 py-1">Gönderim {{ $formatCount($healthStats['failed_pushes']) }}</span>
                <span class="rounded-full border border-slate-200 bg-white px-3 py-1">Aksiyon {{ $formatCount($healthStats['failed_actions']) }}</span>
                <span class="rounded-full border border-slate-200 bg-white px-3 py-1">Webhook {{ $formatCount($healthStats['failed_webhooks']) }}</span>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="repairFailedOperations" wire:loading.attr="disabled" @disabled(($healthStats['failed_syncs'] + $healthStats['failed_pushes'] + $healthStats['failed_actions'] + $healthStats['failed_webhooks']) === 0) class="inline-flex min-h-[36px] items-center justify-center rounded-lg bg-slate-900 px-3 py-2 text-xs font-medium text-white transition hover:bg-slate-800 disabled:opacity-50">Toplu onarım</button>
                <button type="button" wire:click="retryFailedSyncs" wire:loading.attr="disabled" @disabled($healthStats['failed_syncs'] === 0) class="inline-flex min-h-[36px] items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-50">Senkron</button>
                <button type="button" wire:click="retryFailedPushes" wire:loading.attr="disabled" @disabled($healthStats['failed_pushes'] === 0) class="inline-flex min-h-[36px] items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-50">Gönderim</button>
                <button type="button" wire:click="retryFailedOrderActions" wire:loading.attr="disabled" @disabled($healthStats['failed_actions'] === 0) class="inline-flex min-h-[36px] items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-50">Aksiyon</button>
                <button type="button" wire:click="replayFailedWebhooks" wire:loading.attr="disabled" @disabled($healthStats['failed_webhooks'] === 0) class="inline-flex min-h-[36px] items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-50">Webhook</button>
            </div>
        </div>

        {{-- Sekme içerikleri --}}
        <div class="mt-4 space-y-3">
            <div x-show="opsTab === 'sync'" class="space-y-2">
                @forelse($recentSyncRuns as $run)
                    <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-slate-900">{{ $run->store?->store_name ?: '-' }} · {{ $this->syncTypeLabel($run->sync_type) }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $this->humanMarketplace($run->store?->marketplace) }} · {{ $run->created_at?->format('d.m.Y H:i') ?: '-' }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <x-zolm.status-badge :tone="$this->syncStatusTone($run->status)">{{ $this->actionStatusLabel($run->status) }}</x-zolm.status-badge>
                                <button type="button" wire:click="retrySyncRun({{ $run->id }})" wire:loading.attr="disabled" class="inline-flex min-h-[36px] items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-60">Tekrar</button>
                            </div>
                        </div>
                        @if(data_get($run, 'notes_json.last_error'))<p class="mt-2 text-xs text-rose-600">{{ data_get($run, 'notes_json.last_error') }}</p>@endif
                    </div>
                @empty
                    <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-sm text-slate-500">Henüz senkron kaydı yok.</div>
                @endforelse
            </div>

            <div x-cloak x-show="opsTab === 'push'" class="space-y-2">
                @forelse($recentPushRuns as $run)
                    <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-slate-900">{{ $run->store?->store_name ?: '-' }} · {{ $run->push_type === 'price' ? 'Fiyat' : 'Stok' }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $run->created_at?->format('d.m.Y H:i') ?: '-' }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <x-zolm.status-badge :tone="$this->pushStatusTone($run->status)">{{ $this->actionStatusLabel($run->status) }}</x-zolm.status-badge>
                                <button type="button" wire:click="retryPushRun({{ $run->id }})" wire:loading.attr="disabled" class="inline-flex min-h-[36px] items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-60">Tekrar</button>
                            </div>
                        </div>
                        @if($run->error_message)<p class="mt-2 text-xs text-rose-600">{{ $run->error_message }}</p>@endif
                    </div>
                @empty
                    <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-sm text-slate-500">Henüz gönderim kaydı yok.</div>
                @endforelse
            </div>

            <div x-cloak x-show="opsTab === 'action'" class="space-y-2">
                @forelse($recentActionRuns as $run)
                    <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-slate-900">{{ $run->store?->store_name ?: '-' }} · {{ $this->orderActionLabel($run->action_type) }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $run->order?->order_number ?: '-' }} · {{ $run->created_at?->format('d.m.Y H:i') ?: '-' }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <x-zolm.status-badge :tone="$this->actionStatusTone($run->status)">{{ $this->actionStatusLabel($run->status) }}</x-zolm.status-badge>
                                <button type="button" wire:click="retryOrderActionRun({{ $run->id }})" wire:loading.attr="disabled" class="inline-flex min-h-[36px] items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-60">Tekrar</button>
                            </div>
                        </div>
                        @if($run->error_message)<p class="mt-2 text-xs text-rose-600">{{ $run->error_message }}</p>@endif
                    </div>
                @empty
                    <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-sm text-slate-500">Henüz aksiyon kaydı yok.</div>
                @endforelse
            </div>

            <div x-cloak x-show="opsTab === 'webhook'" class="space-y-2">
                @forelse($recentWebhookEvents as $event)
                    <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-slate-900">{{ $event->store?->store_name ?: '-' }} · {{ Str::headline((string) $event->event_type) }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $this->humanMarketplace($event->store?->marketplace) }} · {{ $event->received_at?->format('d.m.Y H:i') ?: '-' }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <x-zolm.status-badge :tone="$this->webhookStatusTone($event->status)">{{ $this->webhookStatusLabel($event->status) }}</x-zolm.status-badge>
                                <button type="button" wire:click="replayWebhookEvent({{ $event->id }})" wire:loading.attr="disabled" class="inline-flex min-h-[36px] items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-60">Yeniden</button>
                            </div>
                        </div>
                        @if($event->error_message)<p class="mt-2 text-xs text-rose-600">{{ $event->error_message }}</p>@endif
                    </div>
                @empty
                    <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-sm text-slate-500">Henüz webhook kaydı yok.</div>
                @endforelse
            </div>
        </div>

        {{-- Legacy projection --}}
        <div class="mt-6 rounded-lg border border-slate-200 bg-slate-50/60 p-4">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">Eski Veri Yansıtma Etkisi</h3>
                    <p class="mt-1 text-sm text-slate-500">Eski muhasebe kayıtlarının yeni finans akışına geçiş durumu</p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('mp.orders') }}" class="inline-flex min-h-[36px] items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50">Siparişlere git</a>
                    <button type="button" wire:click="exportLegacyProjectionCsv" class="inline-flex min-h-[36px] items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50">CSV</button>
                </div>
            </div>
            <div class="mt-3 grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="rounded-lg border border-slate-200 bg-white p-3">
                    <p class="text-xs uppercase tracking-[0.12em] text-slate-500">Bekleyen</p>
                    <p class="mt-1 text-xl font-semibold {{ $legacyProjectionSummary['pending_rows'] > 0 ? 'text-amber-600' : 'text-slate-900' }}">{{ $formatCount($legacyProjectionSummary['pending_rows']) }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-3">
                    <p class="text-xs uppercase tracking-[0.12em] text-slate-500">Tamamlanan</p>
                    <p class="mt-1 text-xl font-semibold text-slate-900">{{ $formatCount($legacyProjectionSummary['projected_rows']) }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-3">
                    <p class="text-xs uppercase tracking-[0.12em] text-slate-500">Eski Veri Olayı</p>
                    <p class="mt-1 text-xl font-semibold text-slate-900">{{ $formatCount($legacyProjectionSummary['legacy_event_orders']) }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-3">
                    <p class="text-xs uppercase tracking-[0.12em] text-slate-500">Kesin Sipariş</p>
                    <p class="mt-1 text-xl font-semibold {{ $legacyProjectionSummary['confirmed_orders'] > 0 ? 'text-emerald-600' : 'text-slate-900' }}">{{ $formatCount($legacyProjectionSummary['confirmed_orders']) }}</p>
                </div>
            </div>

            @if(count($legacyProjectionStoreRows) > 0)
                <div class="mt-3">
                    <button type="button" @click="legacyOpen = !legacyOpen" class="text-sm font-medium text-slate-700 hover:text-slate-900">
                        <span x-text="legacyOpen ? 'Mağaza kırılımını gizle ▲' : 'Mağaza kırılımını göster ▼'"></span>
                    </button>
                    <div x-cloak x-show="legacyOpen" x-transition class="mt-3 space-y-2">
                        @foreach(array_slice($legacyProjectionStoreRows, 0, 4) as $row)
                            <div class="rounded-lg border border-slate-200 bg-white p-3">
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-slate-900">{{ $row['store_name'] }}</p>
                                        <p class="text-xs text-slate-500">{{ $this->humanMarketplace($row['marketplace']) }}{{ $row['legal_entity_name'] ? ' · ' . $row['legal_entity_name'] : '' }}</p>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs text-slate-600">Bekleyen {{ $formatCount($row['pending_rows']) }}</span>
                                        <span class="rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs text-slate-600">Kesin {{ $formatCount($row['confirmed_orders']) }}</span>
                                        <button type="button" wire:click="previewLegacyProjection({{ $row['store_id'] }})" class="inline-flex min-h-[36px] items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-700 transition hover:bg-slate-50">Önizleme</button>
                                        <button type="button" wire:click="runLegacyProjection({{ $row['store_id'] }})" class="inline-flex min-h-[36px] items-center justify-center rounded-lg bg-slate-900 px-3 py-1 text-xs font-medium text-white transition hover:bg-slate-800">Aktar</button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </section>

    {{-- MODÜL KAPSAMI --}}
    <section class="rounded-2xl border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
        <h2 class="text-lg font-bold text-slate-900">Modül Kapsamı</h2>
        <p class="mt-1 text-sm text-slate-500">Master ürün, listing, sipariş ve snapshot katmanları</p>
        <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-3 lg:gap-4">
            <div class="flex flex-col justify-center rounded-xl border border-slate-200 bg-slate-50 p-4 lg:p-5">
                <p class="text-[10px] lg:text-xs uppercase tracking-[0.2em] font-medium text-slate-500 line-clamp-1 text-ellipsis overflow-hidden">Master ürün</p>
                <p class="mt-2 text-2xl lg:text-3xl font-bold text-slate-900">{{ $formatCount($moduleStats['master_products']) }}</p>
                <p class="mt-1 text-[11px] font-medium text-slate-500 truncate">{{ $moduleStats['listed_products'] }} listelenen</p>
            </div>
            <div class="flex flex-col justify-center rounded-xl border border-slate-200 bg-slate-50 p-4 lg:p-5">
                <p class="text-[10px] lg:text-xs uppercase tracking-[0.2em] font-medium text-slate-500 line-clamp-1 text-ellipsis overflow-hidden">Gönderim hazırlığı</p>
                <p class="mt-2 text-2xl lg:text-3xl font-bold text-slate-900">{{ $formatCount($moduleStats['price_push_ready']) }}</p>
                <p class="mt-1 text-[11px] font-medium text-slate-500 truncate">{{ $moduleStats['stock_push_ready'] }} stok gönderimi açık</p>
            </div>
            <div class="flex flex-col justify-center rounded-xl border border-slate-200 bg-slate-50 p-4 lg:p-5">
                <p class="text-[10px] lg:text-xs uppercase tracking-[0.2em] font-medium text-slate-500 line-clamp-1 text-ellipsis overflow-hidden">Kesin snapshot</p>
                <p class="mt-2 text-2xl lg:text-3xl font-bold text-emerald-600">{{ $formatCount($moduleStats['confirmed_snapshots']) }}</p>
                <p class="mt-1 text-[11px] font-medium text-slate-500 truncate">Finansı gelen</p>
            </div>
            <div class="flex flex-col justify-center rounded-xl border border-slate-200 bg-slate-50 p-4 lg:p-5">
                <p class="text-[10px] lg:text-xs uppercase tracking-[0.2em] font-medium text-slate-500 line-clamp-1 text-ellipsis overflow-hidden">Tahmini snapshot</p>
                <p class="mt-2 text-2xl lg:text-3xl font-bold text-sky-600">{{ $formatCount($moduleStats['estimated_snapshots']) }}</p>
                <p class="mt-1 text-[11px] font-medium text-slate-500 truncate">Finans bekleyen</p>
            </div>
        </div>
    </section>

    {{-- PILOT ROLLOUT --}}
    <section x-data="{ pilotOpen: true }" class="rounded-2xl border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold text-slate-900">Pilot Canlıya Geçiş</h2>
                <p class="mt-1 text-sm text-slate-500">Hazırlık, smoke test ve mağaza bazlı durum</p>
            </div>
            <div class="flex gap-2">
                <button type="button" wire:click="exportPilotRolloutCsv" class="inline-flex min-h-[36px] items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50">CSV</button>
                <button @click="pilotOpen = !pilotOpen" class="text-slate-400 hover:text-slate-900">
                    <svg class="h-5 w-5 transition" :class="{ 'rotate-180': pilotOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 9l-7 7-7-7" /></svg>
                </button>
            </div>
        </div>

        {{-- Hazırlık kartları --}}
        <div class="mt-4 grid grid-cols-3 gap-3 lg:gap-4">
            <div class="flex flex-col justify-center rounded-xl border border-slate-200 bg-slate-50 p-4 text-center">
                <p class="text-[10px] lg:text-xs uppercase tracking-[0.2em] font-medium text-slate-500 line-clamp-1 text-ellipsis overflow-hidden">Hazır</p>
                <p class="mt-2 text-2xl lg:text-3xl font-bold text-emerald-600">{{ $formatCount($connectionReadinessSummary['totals']['ready']) }}</p>
            </div>
            <div class="flex flex-col justify-center rounded-xl border {{ $connectionReadinessSummary['totals']['warning'] > 0 ? 'border-amber-200 bg-amber-50' : 'border-slate-200 bg-slate-50' }} p-4 text-center">
                <p class="text-[10px] lg:text-xs uppercase tracking-[0.2em] font-medium {{ $connectionReadinessSummary['totals']['warning'] > 0 ? 'text-amber-800' : 'text-slate-500' }} line-clamp-1 text-ellipsis overflow-hidden">Uyarılı</p>
                <p class="mt-2 text-2xl lg:text-3xl font-bold {{ $connectionReadinessSummary['totals']['warning'] > 0 ? 'text-amber-600' : 'text-emerald-600' }}">{{ $formatCount($connectionReadinessSummary['totals']['warning']) }}</p>
            </div>
            <div class="flex flex-col justify-center rounded-xl border {{ $connectionReadinessSummary['totals']['missing'] > 0 ? 'border-rose-200 bg-rose-50' : 'border-slate-200 bg-slate-50' }} p-4 text-center">
                <p class="text-[10px] lg:text-xs uppercase tracking-[0.2em] font-medium {{ $connectionReadinessSummary['totals']['missing'] > 0 ? 'text-rose-800' : 'text-slate-500' }} line-clamp-1 text-ellipsis overflow-hidden">Eksik</p>
                <p class="mt-2 text-2xl lg:text-3xl font-bold {{ $connectionReadinessSummary['totals']['missing'] > 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ $formatCount($connectionReadinessSummary['totals']['missing']) }}</p>
            </div>
        </div>

        <div x-cloak x-show="pilotOpen" x-transition class="mt-4 space-y-3">
            @php $pilotRows = array_slice($pilotRolloutRows, 0, 5); @endphp
            @forelse($pilotRows as $row)
                <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-4">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-sm font-semibold text-slate-900">{{ $row['store_name'] }}</p>
                                <span class="text-xs text-slate-400">·</span>
                                <p class="text-xs text-slate-500">{{ $this->humanMarketplace($row['marketplace']) }}</p>
                                @if($row['legal_entity_name'])
                                    <span class="text-xs text-slate-400">·</span>
                                    <p class="text-xs text-slate-500">{{ $row['legal_entity_name'] }}</p>
                                @endif
                            </div>
                            <p class="mt-1 text-sm text-slate-600">{{ $this->pilotRolloutSummary($row) }}</p>
                            @if($row['guidance_title'])
                                <p class="mt-1 text-xs text-slate-500">İlk öneri: <span class="font-medium text-slate-700">{{ $row['guidance_title'] }}</span></p>
                            @endif
                        </div>
                        <x-zolm.status-badge :tone="$this->pilotRolloutStageTone($row['stage'])">{{ $this->pilotRolloutStageLabel($row['stage']) }}</x-zolm.status-badge>
                    </div>

                    <div class="mt-3 grid grid-cols-2 lg:grid-cols-4 gap-2">
                        <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">
                            <p class="text-[10px] uppercase tracking-[0.12em] text-slate-500">Hazırlık</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900">{{ $this->readinessStateLabel($row['readiness_state']) }}</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">
                            <p class="text-[10px] uppercase tracking-[0.12em] text-slate-500">Son ön test</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900">{{ $this->pilotRolloutSmokeLabel($row) }}</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">
                            <p class="text-[10px] uppercase tracking-[0.12em] text-slate-500">Eski veri bekleyen</p>
                            <p class="mt-1 text-sm font-semibold {{ $row['legacy_pending_rows'] > 0 ? 'text-amber-600' : 'text-slate-900' }}">{{ $formatCount($row['legacy_pending_rows']) }}</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">
                            <p class="text-[10px] uppercase tracking-[0.12em] text-slate-500">Eski veri kesinleşen</p>
                            <p class="mt-1 text-sm font-semibold {{ $row['legacy_confirmed_orders'] > 0 ? 'text-emerald-600' : 'text-slate-900' }}">{{ $formatCount($row['legacy_confirmed_orders']) }}</p>
                        </div>
                    </div>

                    @if(in_array($row['stage'], ['smoke_pending', 'smoke_failed', 'mapping_hardening'], true))
                        <p class="mt-2 text-xs font-mono text-slate-500 bg-slate-50 rounded p-2">{{ $this->pilotSmokeCommand($row) }}</p>
                    @endif

                    <div class="mt-3 flex flex-wrap gap-2">
                        <a href="{{ $this->pilotRolloutIntegrationsRoute($row) }}" class="inline-flex min-h-[36px] items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50">Entegrasyonlar</a>
                        <a href="{{ $this->pilotRolloutOrdersRoute($row) }}" class="inline-flex min-h-[36px] items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50">Siparişler</a>
                        <a href="{{ $this->pilotRolloutFinanceRoute($row, $row['legacy_pending_rows'] > 0 ? 'backlog' : 'confirmed') }}" class="inline-flex min-h-[36px] items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50">{{ $row['legacy_pending_rows'] > 0 ? 'Finans backlogu' : 'Kesin etki' }}</a>
                        @if($row['legacy_pending_rows'] > 0)
                            <button type="button" wire:click="previewLegacyProjection({{ $row['store_id'] }})" class="inline-flex min-h-[36px] items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50">Önizleme</button>
                            <button type="button" wire:click="runLegacyProjection({{ $row['store_id'] }})" class="inline-flex min-h-[36px] items-center justify-center rounded-lg bg-slate-900 px-3 py-2 text-xs font-medium text-white transition hover:bg-slate-800">Aktar</button>
                        @elseif($row['guidance_route'])
                            <a href="{{ $row['guidance_route'] }}" class="inline-flex min-h-[36px] items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50">Öneri ekranı</a>
                        @endif
                    </div>

                    @if(isset($legacyProjectionPreviews[$row['store_id']]))
                        @php $preview = $legacyProjectionPreviews[$row['store_id']]; @endphp
                        <div class="mt-3 rounded-lg border border-slate-200 bg-slate-50/70 p-3">
                            <div class="flex flex-wrap items-center gap-2 text-xs text-slate-600">
                                <span class="font-medium text-slate-900">{{ !empty($preview['executed']) ? 'Aktarım sonucu' : 'Önizleme' }}</span>
                                <span class="rounded-full border border-slate-200 bg-white px-2 py-0.5">Aday {{ $formatCount($preview['projected_rows'] ?? 0) }}</span>
                                <span class="rounded-full border border-slate-200 bg-white px-2 py-0.5">Yeni {{ $formatCount($preview['created'] ?? 0) }}</span>
                                <span class="rounded-full border border-slate-200 bg-white px-2 py-0.5">Güncelleme {{ $formatCount($preview['updated'] ?? 0) }}</span>
                            </div>
                        </div>
                    @endif
                </div>
            @empty
                <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-sm text-slate-500">Pilot planına alınacak aktif mağaza bulunamadı.</div>
            @endforelse
        </div>
    </section>

    {{-- RİSKLER --}}
    <section class="rounded-2xl border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
        <h2 class="text-lg font-bold text-slate-900">Anlık Dikkat Noktaları</h2>
        <p class="mt-1 text-sm text-slate-500">Bugün öne çıkan finans, senkron ve eşleşme riskleri</p>
        <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-3 lg:gap-4">
            <a href="{{ route('mp.finance', ['deltaStateFilter' => 'waiting']) }}" class="flex flex-col justify-center rounded-xl border border-slate-200 bg-slate-50 p-4 lg:p-5 transition hover:border-slate-300 hover:shadow-sm hover:bg-white">
                <p class="text-[10px] lg:text-xs uppercase tracking-[0.2em] font-medium text-slate-500 line-clamp-1 text-ellipsis overflow-hidden">Bekleyen Finans</p>
                <p class="mt-2 text-2xl lg:text-3xl font-bold transition duration-300 group-hover:text-amber-700 {{ $reconciliationStats['waiting_orders'] > 0 ? 'text-amber-600' : 'text-emerald-600' }}">{{ $formatCount($reconciliationStats['waiting_orders']) }}</p>
            </a>
            <a href="{{ route('mp.finance', ['deltaStateFilter' => 'material']) }}" class="flex flex-col justify-center rounded-xl border border-slate-200 bg-slate-50 p-4 lg:p-5 transition hover:border-slate-300 hover:shadow-sm hover:bg-white group">
                <p class="text-[10px] lg:text-xs uppercase tracking-[0.2em] font-medium text-slate-500 line-clamp-1 text-ellipsis overflow-hidden">Materyal Fark</p>
                <p class="mt-2 text-2xl lg:text-3xl font-bold transition duration-300 group-hover:text-rose-700 {{ $reconciliationStats['material_orders'] > 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ $formatCount($reconciliationStats['material_orders']) }}</p>
            </a>
            <a href="{{ route('mp.matching', ['statusFilter' => 'pending']) }}" class="flex flex-col justify-center rounded-xl border border-slate-200 bg-slate-50 p-4 lg:p-5 transition hover:border-slate-300 hover:shadow-sm hover:bg-white group">
                <p class="text-[10px] lg:text-xs uppercase tracking-[0.2em] font-medium text-slate-500 line-clamp-1 text-ellipsis overflow-hidden">Açık eşleşme sorunu</p>
                <p class="mt-2 text-2xl lg:text-3xl font-bold transition duration-300 group-hover:text-amber-700 {{ $healthStats['open_match_issues'] > 0 ? 'text-amber-600' : 'text-emerald-600' }}">{{ $formatCount($healthStats['open_match_issues']) }}</p>
            </a>
            <a href="{{ route('mp.integrations') }}" class="flex flex-col justify-center rounded-xl border border-slate-200 bg-slate-50 p-4 lg:p-5 transition hover:border-slate-300 hover:shadow-sm hover:bg-white group">
                <p class="text-[10px] lg:text-xs uppercase tracking-[0.2em] font-medium text-slate-500 line-clamp-1 text-ellipsis overflow-hidden">Senkron hatası</p>
                <p class="mt-2 text-2xl lg:text-3xl font-bold transition duration-300 group-hover:text-rose-700 {{ $healthStats['failed_syncs'] > 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ $formatCount($healthStats['failed_syncs']) }}</p>
            </a>
        </div>
    </section>

    {{-- TEŞHİS REHBERİ --}}
    <section x-data="{ guidanceOpen: false }" class="rounded-2xl border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold text-slate-900">Öncelikli Düzeltme Rehberi</h2>
                <p class="mt-1 text-sm text-slate-500">Kritik {{ $formatCount($diagnosticsGuidance['totals']['critical']) }} · Uyarı {{ $formatCount($diagnosticsGuidance['totals']['warning']) }} · Bilgi {{ $formatCount($diagnosticsGuidance['totals']['info']) }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if(!empty($diagnosticsGuidance['items']))
                    <button type="button" wire:click="focusTopGuidance" class="inline-flex min-h-[36px] items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50">{{ $this->guidanceFocusLabel() }}</button>
                    <button type="button" wire:click="syncTopGuidance" class="inline-flex min-h-[36px] items-center justify-center rounded-lg bg-slate-900 px-3 py-2 text-xs font-medium text-white transition hover:bg-slate-800">{{ $this->guidanceSyncLabel() }}</button>
                @endif
                <button type="button" wire:click="exportDiagnosticsGuidanceCsv" class="inline-flex min-h-[36px] items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50">CSV</button>
                <button @click="guidanceOpen = !guidanceOpen" class="text-slate-400 hover:text-slate-900">
                    <svg class="h-5 w-5 transition" :class="{ 'rotate-180': guidanceOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 9l-7 7-7-7" /></svg>
                </button>
            </div>
        </div>
        <div x-cloak x-show="guidanceOpen" x-transition class="mt-4 space-y-3">
            @forelse(array_slice($diagnosticsGuidance['items'], 0, 4) as $item)
                <a href="{{ $this->guidanceRoute($item) }}" class="block rounded-xl border border-slate-200 bg-slate-50/60 p-4 transition hover:border-slate-300 hover:shadow-sm hover:bg-white">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-900">{{ $item['store_name'] ?: '-' }} · {{ $this->humanMarketplace($item['marketplace']) }}</p>
                            <p class="mt-1 text-sm text-slate-600">{{ $item['title'] }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $item['recommended_action'] }}</p>
                        </div>
                        <x-zolm.status-badge :tone="$this->guidanceSeverityTone($item['severity'])">{{ $this->guidanceSeverityLabel($item['severity']) }}</x-zolm.status-badge>
                    </div>
                </a>
            @empty
                <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500 mb-2">Kritik düzeltme önceliği yok.</div>
            @endforelse
        </div>
    </section>

    {{-- FİRMA YAPISI --}}
    <section x-data="{ entitiesOpen: false }" class="rounded-2xl border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
        <div class="flex items-center justify-between gap-3 cursor-pointer" @click="entitiesOpen = !entitiesOpen">
            <div>
                <h2 class="text-lg font-bold text-slate-900">Vergi Yapısı ve Mağaza Dağılımı</h2>
                <p class="mt-1 text-sm text-slate-500">{{ count($legalEntities) }} firma</p>
            </div>
            <svg class="h-5 w-5 text-slate-400 transition" :class="{ 'rotate-180': entitiesOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 9l-7 7-7-7" /></svg>
        </div>
        <div x-cloak x-show="entitiesOpen" x-transition class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            @forelse($legalEntities as $entity)
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 lg:p-5">
                    <div class="flex flex-col gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-900">{{ $entity->name }}</p>
                            <p class="text-xs font-mono text-slate-500 mt-1">{{ $entity->tax_number ?: '-' }}</p>
                        </div>
                        <div class="flex gap-4 text-xs text-slate-500">
                            <div class="flex flex-col">
                                <span class="uppercase tracking-[0.12em] font-medium text-[10px]">Aktif</span>
                                <span class="font-semibold text-slate-900 text-lg mt-0.5">{{ $entity->active_stores_count }}</span>
                            </div>
                            <div class="flex flex-col">
                                <span class="uppercase tracking-[0.12em] font-medium text-[10px]">Toplam</span>
                                <span class="font-semibold text-slate-900 text-lg mt-0.5">{{ $entity->stores_count }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="sm:col-span-2 lg:col-span-3 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">Henüz firma tanımı yok.</div>
            @endforelse
        </div>
    </section>
</div>
