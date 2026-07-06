<div class="w-full space-y-6">
    {{-- Başlık --}}
    <section class="rounded-[28px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
        <div class="flex flex-col sm:flex-row items-start sm:items-center sm:justify-between gap-3 lg:gap-4">
            <div>
                <div class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium uppercase tracking-[0.24em] text-slate-500">
                    Kârlılık Merkezi
                </div>
                <h1 class="mt-3 text-xl lg:text-2xl font-bold text-slate-900">Reklam Sonrası Net Kâr</h1>
                <p class="mt-1 text-sm text-slate-500">Reklam harcamalarınızın gerçek kârlılık etkisini analiz edin.</p>
            </div>
        </div>
    </section>

    {{-- Özet Kartları --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4">
        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Toplam Hesaplama</p>
            <p class="mt-3 text-2xl lg:text-3xl font-bold text-slate-900">{{ $stats['total_calculations'] }}</p>
            <p class="mt-1 text-xs text-slate-500">
                {{ $stats['complete_calculations'] }} kesin / {{ $stats['partial_calculations'] }} eksik
            </p>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Toplam Net Kâr</p>
            <p class="mt-3 text-2xl lg:text-3xl font-bold {{ $stats['total_net_profit'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                {{ number_format($stats['total_net_profit'], 0, ',', '.') }} ₺
            </p>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Ortalama Marj</p>
            <p class="mt-3 text-2xl lg:text-3xl font-bold {{ $stats['avg_margin'] >= 10 ? 'text-emerald-600' : ($stats['avg_margin'] >= 0 ? 'text-amber-600' : 'text-rose-600') }}">
                {{ number_format($stats['avg_margin'], 1) }}%
            </p>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Yetersiz Veri</p>
            <p class="mt-3 text-2xl lg:text-3xl font-bold {{ $stats['insufficient_calculations'] > 0 ? 'text-amber-600' : 'text-slate-900' }}">
                {{ $stats['insufficient_calculations'] }}
            </p>
        </div>
    </div>

    {{-- Filtreler --}}
    <section class="rounded-[28px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 lg:gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700">Hesaplama Durumu</label>
                <select wire:model.live="statusFilter" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-base sm:text-sm text-slate-900 focus:border-slate-400 focus:outline-none">
                    <option value="">Tümü</option>
                    <option value="complete">Kesin Hesaplama</option>
                    <option value="partial">Eksik Veri ile</option>
                    <option value="insufficient_data">Yetersiz Veri</option>
                </select>
            </div>
            <div class="flex items-end">
                <button wire:click="loadStats" class="w-full sm:w-auto px-4 py-3 sm:py-2 text-sm font-medium border border-slate-200 bg-white text-slate-700 rounded-lg hover:bg-slate-50 transition-colors">
                    Yenile
                </button>
            </div>
        </div>
    </section>

    {{-- Kârlılık Tablosu --}}
    <section class="rounded-[28px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900">Kârlılık Detayları</h2>

        {{-- Mobil Kart Görünümü --}}
        <div class="lg:hidden mt-4 space-y-3">
            @forelse($this->profitabilityData as $item)
                @php
                    $statusColors = [
                        'complete' => 'bg-emerald-100 text-emerald-700',
                        'partial' => 'bg-amber-100 text-amber-700',
                        'insufficient_data' => 'bg-rose-100 text-rose-700',
                    ];
                    $statusLabels = [
                        'complete' => 'Kesin',
                        'partial' => 'Eksik Veri',
                        'insufficient_data' => 'Yetersiz',
                    ];
                @endphp
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-slate-900 truncate">{{ $item->campaign?->name ?? 'Genel' }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $item->period_start->format('d.m.Y') }} — {{ $item->period_end->format('d.m.Y') }}</p>
                        </div>
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $statusColors[$item->calculation_status] ?? 'bg-slate-100 text-slate-600' }}">
                            {{ $statusLabels[$item->calculation_status] ?? $item->calculation_status }}
                        </span>
                    </div>
                    <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                        <div>
                            <p class="text-slate-500">Net Gelir</p>
                            <p class="font-medium text-slate-700">{{ number_format($item->net_revenue, 0, ',', '.') }} ₺</p>
                        </div>
                        <div>
                            <p class="text-slate-500">Reklam Harcaması</p>
                            <p class="font-medium text-slate-700">{{ number_format($item->ad_spend, 0, ',', '.') }} ₺</p>
                        </div>
                        <div>
                            <p class="text-slate-500">Net Kâr</p>
                            <p class="font-bold {{ $item->net_contribution_profit >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                                {{ number_format($item->net_contribution_profit, 0, ',', '.') }} ₺
                            </p>
                        </div>
                        <div>
                            <p class="text-slate-500">Net Marj</p>
                            <p class="font-bold {{ $item->net_margin_percent >= 10 ? 'text-emerald-600' : ($item->net_margin_percent >= 0 ? 'text-amber-600' : 'text-rose-600') }}">
                                {{ number_format($item->net_margin_percent, 1) }}%
                            </p>
                        </div>
                    </div>
                    @if($item->missing_inputs && count($item->missing_inputs) > 0)
                        <div class="mt-2 text-xs text-amber-600">
                            Eksik: {{ implode(', ', $item->missing_inputs) }}
                        </div>
                    @endif
                </div>
            @empty
                <div class="text-center py-8">
                    <p class="text-sm text-slate-500">Henüz kârlılık hesaplaması bulunmuyor.</p>
                </div>
            @endforelse
        </div>

        {{-- Masaüstü Tablo Görünümü --}}
        <div class="hidden lg:block mt-4 overflow-x-auto">
            <table class="w-full text-sm" style="table-layout: fixed">
                <thead>
                    <tr class="border-b border-slate-200">
                        <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase" style="width: 25%">Kampanya</th>
                        <th class="px-3 py-2 text-center text-xs font-medium text-slate-500 uppercase" style="width: 12%">Dönem</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 uppercase" style="width: 10%">Net Gelir</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 uppercase" style="width: 10%">Reklam</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 uppercase" style="width: 12%">Net Kâr</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 uppercase" style="width: 10%">Marj</th>
                        <th class="px-3 py-2 text-center text-xs font-medium text-slate-500 uppercase" style="width: 12%">Durum</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->profitabilityData as $item)
                        @php
                            $statusColors = [
                                'complete' => 'bg-emerald-100 text-emerald-700',
                                'partial' => 'bg-amber-100 text-amber-700',
                                'insufficient_data' => 'bg-rose-100 text-rose-700',
                            ];
                            $statusLabels = [
                                'complete' => 'Kesin',
                                'partial' => 'Eksik Veri',
                                'insufficient_data' => 'Yetersiz',
                            ];
                        @endphp
                        <tr class="border-b border-slate-100 hover:bg-slate-50">
                            <td class="px-3 py-3">
                                <p class="text-sm font-medium text-slate-900 truncate">{{ $item->campaign?->name ?? 'Genel' }}</p>
                            </td>
                            <td class="px-3 py-3 text-center text-xs text-slate-600">
                                {{ $item->period_start->format('d.m') }} — {{ $item->period_end->format('d.m') }}
                            </td>
                            <td class="px-3 py-3 text-right text-slate-700">
                                {{ number_format($item->net_revenue, 0, ',', '.') }} ₺
                            </td>
                            <td class="px-3 py-3 text-right text-slate-600">
                                {{ number_format($item->ad_spend, 0, ',', '.') }} ₺
                            </td>
                            <td class="px-3 py-3 text-right">
                                <span class="font-bold {{ $item->net_contribution_profit >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                                    {{ number_format($item->net_contribution_profit, 0, ',', '.') }} ₺
                                </span>
                            </td>
                            <td class="px-3 py-3 text-right">
                                <span class="font-bold {{ $item->net_margin_percent >= 10 ? 'text-emerald-600' : ($item->net_margin_percent >= 0 ? 'text-amber-600' : 'text-rose-600') }}">
                                    {{ number_format($item->net_margin_percent, 1) }}%
                                </span>
                            </td>
                            <td class="px-3 py-3 text-center">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $statusColors[$item->calculation_status] ?? 'bg-slate-100 text-slate-600' }}">
                                    {{ $statusLabels[$item->calculation_status] ?? $item->calculation_status }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-3 py-8 text-center text-slate-500">
                                Henüz kârlılık hesaplaması bulunmuyor.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Sayfalama --}}
        @if($this->profitabilityData->hasPages())
            <div class="mt-4">
                {{ $this->profitabilityData->links() }}
            </div>
        @endif
    </section>
</div>
