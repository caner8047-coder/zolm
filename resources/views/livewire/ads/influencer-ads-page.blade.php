<div class="w-full space-y-6">
    {{-- Başlık --}}
    <section class="rounded-[28px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
        <div class="flex flex-col sm:flex-row items-start sm:items-center sm:justify-between gap-3 lg:gap-4">
            <div>
                <div class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium uppercase tracking-[0.24em] text-slate-500">
                    Influencer Reklamları
                </div>
                <h1 class="mt-3 text-xl lg:text-2xl font-bold text-slate-900">Creator Performansı</h1>
                <p class="mt-1 text-sm text-slate-500">Influencer kampanyalarınızın creator bazlı analizini inceleyin.</p>
            </div>
            <a href="{{ route('ads.import') }}" class="w-full sm:w-auto px-4 py-3 sm:py-2 text-base sm:text-sm font-medium border border-slate-200 bg-white text-slate-700 rounded-lg hover:bg-slate-50 transition-colors">
                Yeni İçe Aktar
            </a>
        </div>
    </section>

    {{-- Özet Kartları --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 lg:gap-4">
        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Toplam Creator</p>
            <p class="mt-3 text-2xl lg:text-3xl font-bold text-slate-900">{{ $stats['total_creators'] }}</p>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Toplam Kampanya</p>
            <p class="mt-3 text-2xl lg:text-3xl font-bold text-slate-900">{{ $stats['total_campaigns'] }}</p>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Toplam Ciro</p>
            <p class="mt-3 text-2xl lg:text-3xl font-bold text-slate-900">{{ number_format($stats['total_revenue'], 0, ',', '.') }} ₺</p>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Toplam Satış</p>
            <p class="mt-3 text-2xl lg:text-3xl font-bold text-slate-900">{{ $stats['total_sales'] }}</p>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Yeni Müşteri</p>
            <p class="mt-3 text-2xl lg:text-3xl font-bold text-emerald-600">{{ $stats['total_new_customers'] }}</p>
        </div>
    </div>

    {{-- Filtreler --}}
    <section class="rounded-[28px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 lg:gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700">Creator Ara</label>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Handle veya isim..."
                    class="mt-1 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-base sm:text-sm text-slate-900 focus:border-slate-400 focus:outline-none">
            </div>
            <div class="flex items-end">
                <button wire:click="loadStats" class="w-full sm:w-auto px-4 py-3 sm:py-2 text-sm font-medium border border-slate-200 bg-white text-slate-700 rounded-lg hover:bg-slate-50 transition-colors">
                    Yenile
                </button>
            </div>
        </div>
    </section>

    {{-- Creator Tablosu --}}
    <section class="rounded-[28px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900">Creator Listesi</h2>

        {{-- Mobil Kart Görünümü --}}
        <div class="lg:hidden mt-4 space-y-3">
            @forelse($this->creators as $creator)
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div class="flex items-start gap-3">
                        @if($creator->avatar_url)
                            <img src="{{ $creator->avatar_url }}" alt="{{ $creator->display_name }}" class="h-10 w-10 rounded-full object-cover">
                        @else
                            <div class="h-10 w-10 rounded-full bg-slate-200 flex items-center justify-center">
                                <span class="text-sm font-medium text-slate-600">{{ strtoupper(substr($creator->handle, 0, 2)) }}</span>
                            </div>
                        @endif
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-slate-900">{{ $creator->display_name ?? $creator->handle }}</p>
                            <p class="text-xs text-slate-500">@{{ $creator->handle }}</p>
                            <p class="text-xs text-slate-500">{{ ucfirst($creator->platform) }}</p>
                        </div>
                    </div>
                    <div class="mt-3 grid grid-cols-3 gap-2 text-xs">
                        <div>
                            <p class="text-slate-500">Ciro</p>
                            <p class="font-medium text-slate-700">{{ number_format($creator->total_revenue ?? 0, 0, ',', '.') }} ₺</p>
                        </div>
                        <div>
                            <p class="text-slate-500">Satış</p>
                            <p class="font-medium text-slate-700">{{ $creator->total_sales_sum ?? 0 }}</p>
                        </div>
                        <div>
                            <p class="text-slate-500">Dönüşüm</p>
                            <p class="font-medium text-slate-700">
                                @php
                                    $totalVisits = $creator->creatorSnapshots->sum('link_visits') ?? 0;
                                    $totalSales = $creator->total_sales_sum ?? 0;
                                    $conversion = $totalVisits > 0 ? ($totalSales / $totalVisits) * 100 : 0;
                                @endphp
                                {{ number_format($conversion, 1) }}%
                            </p>
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center py-8">
                    <p class="text-sm text-slate-500">Henüz creator bulunmuyor.</p>
                </div>
            @endforelse
        </div>

        {{-- Masaüstü Tablo Görünümü --}}
        <div class="hidden lg:block mt-4 overflow-x-auto">
            <table class="w-full text-sm" style="table-layout: fixed">
                <thead>
                    <tr class="border-b border-slate-200">
                        <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase" style="width: 30%">Creator</th>
                        <th class="px-3 py-2 text-center text-xs font-medium text-slate-500 uppercase" style="width: 10%">Platform</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 uppercase" style="width: 12%">Ciro</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 uppercase" style="width: 10%">Satış</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 uppercase" style="width: 12%">Ziyaret</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 uppercase" style="width: 10%">Dönüşüm</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 uppercase" style="width: 12%">Yeni Müşteri</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->creators as $creator)
                        <tr class="border-b border-slate-100 hover:bg-slate-50">
                            <td class="px-3 py-3">
                                <div class="flex items-center gap-3">
                                    @if($creator->avatar_url)
                                        <img src="{{ $creator->avatar_url }}" alt="" class="h-8 w-8 rounded-full object-cover">
                                    @else
                                        <div class="h-8 w-8 rounded-full bg-slate-200 flex items-center justify-center shrink-0">
                                            <span class="text-xs font-medium text-slate-600">{{ strtoupper(substr($creator->handle, 0, 2)) }}</span>
                                        </div>
                                    @endif
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-slate-900 truncate">{{ $creator->display_name ?? $creator->handle }}</p>
                                        <p class="text-xs text-slate-500">@{{ $creator->handle }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-3 text-center">
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">
                                    {{ ucfirst($creator->platform) }}
                                </span>
                            </td>
                            <td class="px-3 py-3 text-right font-medium text-slate-700">
                                {{ number_format($creator->total_revenue ?? 0, 0, ',', '.') }} ₺
                            </td>
                            <td class="px-3 py-3 text-right text-slate-600">
                                {{ $creator->total_sales_sum ?? 0 }}
                            </td>
                            <td class="px-3 py-3 text-right text-slate-600">
                                {{ number_format($creator->creatorSnapshots->sum('link_visits') ?? 0) }}
                            </td>
                            <td class="px-3 py-3 text-right">
                                @php
                                    $totalVisits = $creator->creatorSnapshots->sum('link_visits') ?? 0;
                                    $totalSales = $creator->total_sales_sum ?? 0;
                                    $conversion = $totalVisits > 0 ? ($totalSales / $totalVisits) * 100 : 0;
                                @endphp
                                <span class="font-bold {{ $conversion >= 2 ? 'text-emerald-600' : ($conversion >= 0.5 ? 'text-amber-600' : 'text-rose-600') }}">
                                    {{ number_format($conversion, 1) }}%
                                </span>
                            </td>
                            <td class="px-3 py-3 text-right text-slate-600">
                                {{ $creator->creatorSnapshots->sum('new_customers') ?? 0 }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-3 py-8 text-center text-slate-500">
                                Henüz creator bulunmuyor.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Sayfalama --}}
        @if($this->creators->hasPages())
            <div class="mt-4">
                {{ $this->creators->links() }}
            </div>
        @endif
    </section>
</div>
