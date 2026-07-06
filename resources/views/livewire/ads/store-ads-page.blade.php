<div class="w-full space-y-6">
    {{-- Başlık --}}
    <section class="rounded-[28px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
        <div class="flex flex-col sm:flex-row items-start sm:items-center sm:justify-between gap-3 lg:gap-4">
            <div>
                <div class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium uppercase tracking-[0.24em] text-slate-500">
                    Mağaza Reklamları
                </div>
                <h1 class="mt-3 text-xl lg:text-2xl font-bold text-slate-900">Kelime Performansı</h1>
                <p class="mt-1 text-sm text-slate-500">Mağaza reklamlarınızın kelime bazlı analizini inceleyin.</p>
            </div>
            <a href="{{ route('ads.import') }}" class="w-full sm:w-auto px-4 py-3 sm:py-2 text-base sm:text-sm font-medium border border-slate-200 bg-white text-slate-700 rounded-lg hover:bg-slate-50 transition-colors">
                Yeni İçe Aktar
            </a>
        </div>
    </section>

    {{-- Özet Kartları --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4">
        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Toplam Kampanya</p>
            <p class="mt-3 text-2xl lg:text-3xl font-bold text-slate-900">{{ $stats['total_campaigns'] }}</p>
            <p class="mt-1 text-xs text-slate-500">
                A: {{ $stats['smart_campaigns'] }} akıllı / M: {{ $stats['manual_campaigns'] }} manuel
            </p>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Toplam Kelime</p>
            <p class="mt-3 text-2xl lg:text-3xl font-bold text-slate-900">{{ $stats['total_keywords'] }}</p>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Sıfır Satışlı Kelime</p>
            <p class="mt-3 text-2xl lg:text-3xl font-bold {{ $stats['zero_sale_keywords'] > 0 ? 'text-amber-600' : 'text-slate-900' }}">
                {{ $stats['zero_sale_keywords'] }}
            </p>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Ortalama GBM</p>
            <p class="mt-3 text-2xl lg:text-3xl font-bold text-slate-900">{{ number_format($stats['avg_gbm'], 2, ',', '.') }} ₺</p>
        </div>
    </div>

    {{-- Filtreler --}}
    <section class="rounded-[28px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 lg:gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700">Kampanya Ara</label>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Kampanya adı..."
                    class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-base sm:text-sm text-slate-900 focus:border-slate-400 focus:outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Hedefleme Tipi</label>
                <select wire:model.live="targetingFilter" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-base sm:text-sm text-slate-900 focus:border-slate-400 focus:outline-none">
                    <option value="">Tümü</option>
                    <option value="smart">Akıllı Hedefleme</option>
                    <option value="manual">Manuel Hedefleme</option>
                </select>
            </div>
            <div class="flex items-end">
                <button wire:click="loadStats" class="w-full sm:w-auto px-4 py-3 sm:py-2 text-sm font-medium border border-slate-200 bg-white text-slate-700 rounded-lg hover:bg-slate-50 transition-colors">
                    Yenile
                </button>
            </div>
        </div>
    </section>

    {{-- Kampanya Tablosu --}}
    <section class="rounded-[28px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900">Kampanya Listesi</h2>

        {{-- Mobil Kart Görünümü --}}
        <div class="lg:hidden mt-4 space-y-3">
            @forelse($this->campaigns as $campaign)
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-slate-900 truncate">{{ $campaign->name }}</p>
                            <p class="mt-1">
                                @if($campaign->targeting_type === 'smart')
                                    <span class="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700">Akıllı</span>
                                @elseif($campaign->targeting_type === 'manual')
                                    <span class="inline-flex items-center rounded-full bg-purple-100 px-2 py-0.5 text-xs font-medium text-purple-700">Manuel</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">Belirsiz</span>
                                @endif
                            </p>
                        </div>
                        @if($campaign->actual_gbm)
                            <div class="text-right shrink-0">
                                <p class="text-sm font-bold text-slate-900">GBM: {{ number_format($campaign->actual_gbm, 2) }} ₺</p>
                            </div>
                        @endif
                    </div>
                    @if($campaign->selected_gbm || $campaign->recommended_gbm)
                        <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                            <div>
                                <p class="text-slate-500">Seçilen GBM</p>
                                <p class="font-medium text-slate-700">{{ $campaign->selected_gbm ? number_format($campaign->selected_gbm, 2) : '-' }} ₺</p>
                            </div>
                            <div>
                                <p class="text-slate-500">Önerilen GBM</p>
                                <p class="font-medium text-slate-700">{{ $campaign->recommended_gbm ? number_format($campaign->recommended_gbm, 2) : '-' }} ₺</p>
                            </div>
                        </div>
                    @endif
                </div>
            @empty
                <div class="text-center py-8">
                    <p class="text-sm text-slate-500">Henüz mağaza reklamı kampanyası bulunmuyor.</p>
                </div>
            @endforelse
        </div>

        {{-- Masaüstü Tablo Görünümü --}}
        <div class="hidden lg:block mt-4 overflow-x-auto">
            <table class="w-full text-sm" style="table-layout: fixed">
                <thead>
                    <tr class="border-b border-slate-200">
                        <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase" style="width: 35%">Kampanya</th>
                        <th class="px-3 py-2 text-center text-xs font-medium text-slate-500 uppercase" style="width: 12%">Hedefleme</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 uppercase" style="width: 12%">Seçilen GBM</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 uppercase" style="width: 12%">Önerilen GBM</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 uppercase" style="width: 12%">Gerçekleşen GBM</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 uppercase" style="width: 12%">Durum</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->campaigns as $campaign)
                        <tr class="border-b border-slate-100 hover:bg-slate-50">
                            <td class="px-3 py-3">
                                <p class="text-sm font-medium text-slate-900 truncate">{{ $campaign->name }}</p>
                                @if($campaign->start_at)
                                    <p class="text-xs text-slate-500">{{ $campaign->start_at->format('d.m.Y') }}</p>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-center">
                                @if($campaign->targeting_type === 'smart')
                                    <span class="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700">Akıllı</span>
                                @elseif($campaign->targeting_type === 'manual')
                                    <span class="inline-flex items-center rounded-full bg-purple-100 px-2 py-0.5 text-xs font-medium text-purple-700">Manuel</span>
                                @else
                                    <span class="text-slate-400">-</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-right text-slate-700">
                                {{ $campaign->selected_gbm ? number_format($campaign->selected_gbm, 2) : '-' }} ₺
                            </td>
                            <td class="px-3 py-3 text-right text-slate-700">
                                {{ $campaign->recommended_gbm ? number_format($campaign->recommended_gbm, 2) : '-' }} ₺
                            </td>
                            <td class="px-3 py-3 text-right">
                                @if($campaign->actual_gbm)
                                    @php $gbm = $campaign->actual_gbm; @endphp
                                    <span class="font-bold {{ $gbm > 50 ? 'text-rose-600' : ($gbm > 20 ? 'text-amber-600' : 'text-emerald-600') }}">
                                        {{ number_format($gbm, 2) }} ₺
                                    </span>
                                @else
                                    <span class="text-slate-400">-</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-right">
                                @php
                                    $statusColors = [
                                        'active' => 'bg-emerald-100 text-emerald-700',
                                        'paused' => 'bg-amber-100 text-amber-700',
                                        'ended' => 'bg-slate-100 text-slate-600',
                                    ];
                                @endphp
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $statusColors[$campaign->status] ?? 'bg-slate-100 text-slate-600' }}">
                                    {{ $campaign->status }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-3 py-8 text-center text-slate-500">
                                Henüz mağaza reklamı kampanyası bulunmuyor.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Sayfalama --}}
        @if($this->campaigns->hasPages())
            <div class="mt-4">
                {{ $this->campaigns->links() }}
            </div>
        @endif
    </section>
</div>
