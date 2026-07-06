<div class="w-full space-y-6">
    {{-- Başlık --}}
    <section class="rounded-[28px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
        <div class="flex flex-col sm:flex-row items-start sm:items-center sm:justify-between gap-3 lg:gap-4">
            <div>
                <div class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium uppercase tracking-[0.24em] text-slate-500">
                    Ürün Reklamları
                </div>
                <h1 class="mt-3 text-xl lg:text-2xl font-bold text-slate-900">Kampanya Performansı</h1>
                <p class="mt-1 text-sm text-slate-500">Ürün reklamlarınızın kampanya bazlı performansını analiz edin.</p>
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
            <p class="mt-1 text-xs text-slate-500">{{ $stats['active_campaigns'] }} aktif</p>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Toplam Harcama</p>
            <p class="mt-3 text-2xl lg:text-3xl font-bold text-slate-900">{{ number_format($stats['total_spend'], 0, ',', '.') }} ₺</p>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Ortalama ROAS</p>
            <p class="mt-3 text-2xl lg:text-3xl font-bold {{ $stats['avg_roas'] >= 2 ? 'text-emerald-600' : 'text-rose-600' }}">
                {{ number_format($stats['avg_roas'], 2, ',', '.') }}
            </p>
            <p class="mt-1 text-xs text-slate-500">
                D: {{ number_format($stats['direct_roas'], 2) }} / Dl: {{ number_format($stats['indirect_roas'], 2) }}
            </p>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Sıfır Satışlı Harcama</p>
            <p class="mt-3 text-2xl lg:text-3xl font-bold {{ $stats['zero_sale_spend'] > 0 ? 'text-amber-600' : 'text-slate-900' }}">
                {{ number_format($stats['zero_sale_spend'], 0, ',', '.') }} ₺
            </p>
        </div>
    </div>

    {{-- Filtreler --}}
    <section class="rounded-[28px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700">Kampanya Ara</label>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Kampanya adı..."
                    class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-base sm:text-sm text-slate-900 focus:border-slate-400 focus:outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">ROAS Durumu</label>
                <select wire:model.live="roasFilter" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-base sm:text-sm text-slate-900 focus:border-slate-400 focus:outline-none">
                    <option value="">Tümü</option>
                    <option value="high">Yüksek (5+)</option>
                    <option value="medium">Orta (2-5)</option>
                    <option value="low">Düşük (2 altı)</option>
                    <option value="zero">Sıfır</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Kampanya Durumu</label>
                <select wire:model.live="statusFilter" class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-base sm:text-sm text-slate-900 focus:border-slate-400 focus:outline-none">
                    <option value="">Tümü</option>
                    <option value="active">Aktif</option>
                    <option value="paused">Duraklatılmış</option>
                    <option value="ended">Tamamlanmış</option>
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
        {{-- Mobil Kart Görünümü --}}
        <div class="lg:hidden space-y-3">
            @forelse($this->campaigns as $campaign)
                <a href="{{ route('ads.product-ads.detail', $campaign->id) }}"
                   class="block rounded-2xl border border-slate-200 bg-slate-50 p-4 hover:border-slate-300 transition-colors">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-slate-900 truncate">{{ $campaign->name }}</p>
                            <p class="mt-1 text-xs text-slate-500">
                                @if($campaign->start_at)
                                    {{ $campaign->start_at->format('d.m.Y') }}
                                @endif
                            </p>
                        </div>
                        @if($campaign->latestSnapshot)
                            @php $snap = $campaign->latestSnapshot; @endphp
                            <div class="text-right shrink-0">
                                <p class="text-sm font-bold {{ $snap->roas >= 2 ? 'text-emerald-600' : 'text-rose-600' }}">
                                    ROAS: {{ number_format($snap->roas, 2) }}
                                </p>
                                <p class="text-xs text-slate-500">{{ number_format($snap->spend, 0, ',', '.') }} ₺ harcama</p>
                            </div>
                        @endif
                    </div>
                    @if($campaign->latestSnapshot)
                        @php $snap = $campaign->latestSnapshot; @endphp
                        <div class="mt-3 grid grid-cols-3 gap-2 text-xs">
                            <div>
                                <p class="text-slate-500">Gösterim</p>
                                <p class="font-medium text-slate-700">{{ number_format($snap->impressions) }}</p>
                            </div>
                            <div>
                                <p class="text-slate-500">Tıklama</p>
                                <p class="font-medium text-slate-700">{{ number_format($snap->clicks) }}</p>
                            </div>
                            <div>
                                <p class="text-slate-500">Satış</p>
                                <p class="font-medium text-slate-700">{{ $snap->sales_total }}</p>
                            </div>
                        </div>
                        <div class="mt-2 grid grid-cols-2 gap-2 text-xs">
                            <div>
                                <p class="text-slate-500">Doğrudan Ciro</p>
                                <p class="font-medium text-slate-700">{{ number_format($snap->revenue_direct, 0, ',', '.') }} ₺</p>
                            </div>
                            <div>
                                <p class="text-slate-500">Dolaylı Ciro</p>
                                <p class="font-medium text-slate-700">{{ number_format($snap->revenue_indirect, 0, ',', '.') }} ₺</p>
                            </div>
                        </div>
                    @endif
                </a>
            @empty
                <div class="text-center py-8">
                    <p class="text-sm text-slate-500">Henüz kampanya bulunmuyor.</p>
                </div>
            @endforelse
        </div>

        {{-- Masaüstü Tablo Görünümü --}}
        <div class="hidden lg:block overflow-x-auto">
            <table class="w-full text-sm" style="table-layout: fixed">
                <thead>
                    <tr class="border-b border-slate-200">
                        <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase" style="width: 30%">Kampanya</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 uppercase cursor-pointer" style="width: 10%" wire:click="sortTable('status')">Durum</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 uppercase" style="width: 10%">Harcama</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 uppercase" style="width: 8%">Gösterim</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 uppercase" style="width: 8%">Tıklama</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 uppercase" style="width: 7%">Satış</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 uppercase" style="width: 12%">D. Ciro</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 uppercase" style="width: 12%">Dl. Ciro</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 uppercase cursor-pointer" style="width: 8%" wire:click="sortTable('roas')">ROAS</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->campaigns as $campaign)
                        <tr class="border-b border-slate-100 hover:bg-slate-50 cursor-pointer"
                            onclick="window.location='{{ route('ads.product-ads.detail', $campaign->id) }}'">
                            <td class="px-3 py-3">
                                <p class="text-sm font-medium text-slate-900 truncate">{{ $campaign->name }}</p>
                                @if($campaign->start_at)
                                    <p class="text-xs text-slate-500">{{ $campaign->start_at->format('d.m.Y') }}</p>
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
                            <td class="px-3 py-3 text-right font-medium text-slate-700">
                                {{ $campaign->latestSnapshot ? number_format($campaign->latestSnapshot->spend, 0, ',', '.') : '-' }} ₺
                            </td>
                            <td class="px-3 py-3 text-right text-slate-600">
                                {{ $campaign->latestSnapshot ? number_format($campaign->latestSnapshot->impressions) : '-' }}
                            </td>
                            <td class="px-3 py-3 text-right text-slate-600">
                                {{ $campaign->latestSnapshot ? number_format($campaign->latestSnapshot->clicks) : '-' }}
                            </td>
                            <td class="px-3 py-3 text-right text-slate-600">
                                {{ $campaign->latestSnapshot ? $campaign->latestSnapshot->sales_total : '-' }}
                            </td>
                            <td class="px-3 py-3 text-right text-slate-600">
                                {{ $campaign->latestSnapshot ? number_format($campaign->latestSnapshot->revenue_direct, 0, ',', '.') : '-' }} ₺
                            </td>
                            <td class="px-3 py-3 text-right text-slate-600">
                                {{ $campaign->latestSnapshot ? number_format($campaign->latestSnapshot->revenue_indirect, 0, ',', '.') : '-' }} ₺
                            </td>
                            <td class="px-3 py-3 text-right">
                                @if($campaign->latestSnapshot)
                                    @php $roas = $campaign->latestSnapshot->roas; @endphp
                                    <span class="font-bold {{ $roas >= 5 ? 'text-emerald-600' : ($roas >= 2 ? 'text-amber-600' : 'text-rose-600') }}">
                                        {{ number_format($roas, 2) }}
                                    </span>
                                @else
                                    <span class="text-slate-400">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-3 py-8 text-center text-slate-500">
                                Henüz kampanya bulunmuyor.
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
