<div class="w-full space-y-6">
    {{-- Başlık --}}
    <section class="rounded-[28px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
        <div class="flex flex-col sm:flex-row items-start sm:items-center sm:justify-between gap-3 lg:gap-4">
            <div>
                <a href="{{ route('ads.product-ads') }}" class="text-sm text-slate-500 hover:text-slate-700 transition-colors">
                    ← Ürün Reklamları
                </a>
                <h1 class="mt-2 text-xl lg:text-2xl font-bold text-slate-900">{{ $campaign->name }}</h1>
                <p class="mt-1 text-sm text-slate-500">
                    @if($campaign->start_at)
                        {{ $campaign->start_at->format('d.m.Y') }}
                        @if($campaign->end_at)
                            — {{ $campaign->end_at->format('d.m.Y') }}
                        @endif
                    @endif
                    · {{ ucfirst($campaign->status) }}
                </p>
            </div>
            <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium uppercase tracking-[0.24em] text-slate-500">
                Kampanya Detayı
            </span>
        </div>
    </section>

    {{-- Özet Kartları --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 lg:gap-4">
        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Toplam Harcama</p>
            <p class="mt-3 text-xl lg:text-2xl font-bold text-slate-900">{{ number_format($stats['total_spend'], 0, ',', '.') }} ₺</p>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Doğrudan Ciro</p>
            <p class="mt-3 text-xl lg:text-2xl font-bold text-slate-900">{{ number_format($stats['total_revenue_direct'], 0, ',', '.') }} ₺</p>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Dolaylı Ciro</p>
            <p class="mt-3 text-xl lg:text-2xl font-bold text-slate-900">{{ number_format($stats['total_revenue_indirect'], 0, ',', '.') }} ₺</p>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Satış Adedi</p>
            <p class="mt-3 text-xl lg:text-2xl font-bold text-slate-900">{{ $stats['total_sales'] }}</p>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">ROAS</p>
            <p class="mt-3 text-xl lg:text-2xl font-bold {{ $stats['avg_roas'] >= 2 ? 'text-emerald-600' : 'text-rose-600' }}">
                {{ number_format($stats['avg_roas'], 2) }}
            </p>
            <p class="mt-1 text-xs text-slate-500">
                D: {{ number_format($stats['direct_roas'], 2) }} / Dl: {{ number_format($stats['indirect_roas'], 2) }}
            </p>
        </div>
    </div>

    {{-- Ürün Bazlı Performans --}}
    <section class="rounded-[28px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900">Ürün Bazlı Performans</h2>

        @if(count($productSnapshots) > 0)
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200">
                            <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 uppercase">Ürün</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 uppercase">Harcama</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 uppercase">Gösterim</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 uppercase">Tıklama</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 uppercase">Satış</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 uppercase">Ciro</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 uppercase">ROAS</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($productSnapshots as $ps)
                            <tr class="border-b border-slate-100">
                                <td class="px-3 py-2">
                                    <p class="text-sm font-medium text-slate-900 truncate max-w-xs">
                                        {{ $ps['ad_campaign_product']['product_name_snapshot'] ?? 'Bilinmeyen' }}
                                    </p>
                                </td>
                                <td class="px-3 py-2 text-right text-slate-700">{{ number_format($ps['spend'], 0, ',', '.') }} ₺</td>
                                <td class="px-3 py-2 text-right text-slate-600">{{ number_format($ps['impressions']) }}</td>
                                <td class="px-3 py-2 text-right text-slate-600">{{ number_format($ps['clicks']) }}</td>
                                <td class="px-3 py-2 text-right text-slate-600">{{ $ps['sales_total'] }}</td>
                                <td class="px-3 py-2 text-right text-slate-600">{{ number_format($ps['revenue_total'], 0, ',', '.') }} ₺</td>
                                <td class="px-3 py-2 text-right">
                                    @php $roas = $ps['roas']; @endphp
                                    <span class="font-bold {{ $roas >= 5 ? 'text-emerald-600' : ($roas >= 2 ? 'text-amber-600' : 'text-rose-600') }}">
                                        {{ number_format($roas, 2) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="mt-4 text-sm text-slate-500">Henüz ürün verisi bulunmuyor.</p>
        @endif
    </section>

    {{-- Mutabakat --}}
    <section class="rounded-[28px] border border-slate-200 bg-white p-4 lg:p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900">Veri Mutabakatı</h2>

        @if(count($reconciliations) > 0)
            <div class="mt-4 space-y-3">
                @foreach($reconciliations as $rec)
                    @php
                        $statusColors = [
                            'compatible' => 'bg-emerald-100 text-emerald-700',
                            'check_needed' => 'bg-amber-100 text-amber-700',
                            'critical' => 'bg-rose-100 text-rose-700',
                        ];
                        $statusLabels = [
                            'compatible' => 'Uyumlu',
                            'check_needed' => 'Kontrol Edilmeli',
                            'critical' => 'Kritik Fark',
                        ];
                    @endphp
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-slate-900">{{ $rec['comparison_type'] }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $rec['calculated_at'] }}</p>
                            </div>
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusColors[$rec['status']] ?? 'bg-slate-100 text-slate-600' }}">
                                {{ $statusLabels[$rec['status']] ?? $rec['status'] }}
                            </span>
                        </div>
                        <div class="mt-3 grid grid-cols-3 gap-4 text-xs">
                            <div>
                                <p class="text-slate-500">Harcama Farkı</p>
                                <p class="font-medium {{ abs($rec['spend_difference']) > 0 ? 'text-amber-600' : 'text-slate-700' }}">
                                    {{ number_format($rec['spend_difference'], 0, ',', '.') }} ₺
                                </p>
                            </div>
                            <div>
                                <p class="text-slate-500">Ciro Farkı</p>
                                <p class="font-medium {{ abs($rec['revenue_difference']) > 0 ? 'text-amber-600' : 'text-slate-700' }}">
                                    {{ number_format($rec['revenue_difference'], 0, ',', '.') }} ₺
                                </p>
                            </div>
                            <div>
                                <p class="text-slate-500">Fark Oranı</p>
                                <p class="font-medium {{ $rec['difference_percent'] > 2 ? 'text-rose-600' : ($rec['difference_percent'] > 0.5 ? 'text-amber-600' : 'text-slate-700') }}">
                                    {{ number_format($rec['difference_percent'], 2) }}%
                                </p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <p class="mt-4 text-sm text-slate-500">Henüz mutabakat kaydı bulunmuyor.</p>
        @endif
    </section>
</div>
