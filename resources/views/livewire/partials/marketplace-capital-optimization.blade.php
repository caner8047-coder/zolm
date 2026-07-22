@php
    $capitalSummary = (array) data_get($capitalOptimization, 'summary', []);
    $capitalItems = (array) data_get($capitalOptimization, 'items', []);
    $decisionTones = [
        'grow' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
        'protect' => 'border-blue-200 bg-blue-50 text-blue-700',
        'reduce' => 'border-rose-200 bg-rose-50 text-rose-700',
        'investigate' => 'border-amber-200 bg-amber-50 text-amber-700',
        'hold' => 'border-slate-200 bg-slate-50 text-slate-600',
    ];
@endphp

<section class="mb-4 rounded-[10px] border border-slate-200 bg-white shadow-sm" data-testid="marketplace-capital-optimization">
    <div class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-start sm:justify-between lg:p-6">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="text-lg font-bold text-slate-900">Portföy sermaye optimizasyonu</h2>
                <span class="rounded-[6px] border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-mono text-slate-500">{{ (int) data_get($capitalOptimization, 'period_days', 30) }} gün</span>
            </div>
            <p class="mt-1 text-sm text-slate-500">Stokta bağlı parayı, gerçekleşen satış hızını ve birim katkıyı birlikte okuyarak hangi üründe sermayeyi artırma, koruma veya azaltma adayı olduğunu gösterir.</p>
        </div>
        <span class="inline-flex shrink-0 rounded-[6px] border border-slate-200 bg-slate-50/70 px-2 py-1 text-xs font-mono text-slate-600">Karar desteği · otomatik işlem yok</span>
    </div>

    <div class="grid grid-cols-1 gap-3 p-4 sm:grid-cols-2 xl:grid-cols-4 lg:p-6">
        @foreach([
            ['Stokta bağlı sermaye', $formatCompactMoney((float) ($capitalSummary['inventory_capital'] ?? 0)), 'Ürün maliyeti + ambalaj + kargo'],
            ['Azaltma adayı sermaye', $formatCompactMoney((float) ($capitalSummary['releasable_capital'] ?? 0)), '%'.number_format((float) ($capitalSummary['releasable_percent'] ?? 0), 1, ',', '.').' portföy payı'],
            ['Büyüt / koru', $formatCount((int) ($capitalSummary['grow_count'] ?? 0)).' / '.$formatCount((int) ($capitalSummary['protect_count'] ?? 0)), 'Kârlı ve satış hızı olan ürünler'],
            ['Veri hazırlığı', '%'.number_format((float) ($capitalSummary['data_readiness_percent'] ?? 0), 1, ',', '.'), $formatCount((int) ($capitalSummary['investigate_count'] ?? 0)).' ürün veri bekliyor'],
        ] as [$label, $value, $hint])
            <div class="min-w-0 rounded-[8px] border border-slate-200 bg-slate-50/70 p-4">
                <p class="text-xs font-medium text-slate-500">{{ $label }}</p>
                <p class="mt-1 truncate text-lg font-semibold text-slate-900" title="{{ $value }}">{{ $value }}</p>
                <p class="mt-1 truncate text-xs text-slate-500" title="{{ $hint }}">{{ $hint }}</p>
            </div>
        @endforeach
    </div>

    <div class="border-t border-slate-200">
        <div class="hidden overflow-x-auto md:block">
            <table class="w-full table-fixed text-left text-sm">
                <thead class="bg-slate-50/60 text-xs uppercase tracking-[0.08em] text-slate-500">
                    <tr>
                        <th class="w-[25%] px-4 py-3 font-semibold">Ürün</th>
                        <th class="w-[14%] px-3 py-3 font-semibold">Karar</th>
                        <th class="w-[12%] px-3 py-3 text-right font-semibold">Bağlı sermaye</th>
                        <th class="w-[11%] px-3 py-3 text-right font-semibold">Stok günü</th>
                        <th class="w-[12%] px-3 py-3 text-right font-semibold">Birim katkı</th>
                        <th class="w-[26%] px-4 py-3 font-semibold">Neden</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($capitalItems as $item)
                        <tr class="hover:bg-slate-50/60">
                            <td class="px-4 py-3"><p class="truncate font-medium text-slate-900" title="{{ $item['product_name'] }}">{{ $item['product_name'] }}</p><p class="mt-0.5 truncate text-xs text-slate-500">{{ $item['stock_code'] ?: 'Stok kodu yok' }} · {{ $formatCount($item['stock_quantity']) }} stok</p></td>
                            <td class="px-3 py-3"><span class="inline-flex rounded-[6px] border px-2 py-1 text-xs font-medium {{ $decisionTones[$item['decision']] ?? $decisionTones['hold'] }}">{{ $item['decision_label'] }}</span></td>
                            <td class="px-3 py-3 text-right font-semibold text-slate-900">{{ $formatCompactMoney($item['inventory_capital']) }}</td>
                            <td class="px-3 py-3 text-right text-slate-700">{{ $item['days_cover'] !== null ? number_format($item['days_cover'], 1, ',', '.').' gün' : 'Satış yok' }}</td>
                            <td class="px-3 py-3 text-right font-semibold {{ $item['unit_contribution'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $formatCompactMoney($item['unit_contribution']) }}</td>
                            <td class="px-4 py-3"><p class="line-clamp-2 text-xs leading-5 text-slate-600">{{ $item['reason'] }}</p>@if($item['releasable_capital'] > 0)<p class="mt-1 text-xs font-medium text-rose-700">Tahmini serbestleşme: {{ $formatCompactMoney($item['releasable_capital']) }}</p>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">Sermaye analizi için ürün bulunamadı.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="divide-y divide-slate-100 md:hidden">
            @forelse($capitalItems as $item)
                <div class="p-4">
                    <div class="flex items-start justify-between gap-3"><div class="min-w-0"><p class="truncate text-sm font-semibold text-slate-900">{{ $item['product_name'] }}</p><p class="mt-1 text-xs text-slate-500">{{ $formatCount($item['stock_quantity']) }} stok · {{ $item['days_cover'] !== null ? number_format($item['days_cover'], 1, ',', '.').' gün' : 'satış yok' }}</p></div><span class="shrink-0 rounded-[6px] border px-2 py-1 text-xs font-medium {{ $decisionTones[$item['decision']] ?? $decisionTones['hold'] }}">{{ $item['decision_label'] }}</span></div>
                    <div class="mt-3 grid grid-cols-2 gap-2"><div class="rounded-[6px] border border-slate-200 bg-slate-50/60 p-2"><p class="text-xs text-slate-500">Bağlı sermaye</p><p class="mt-1 font-semibold text-slate-900">{{ $formatCompactMoney($item['inventory_capital']) }}</p></div><div class="rounded-[6px] border border-slate-200 bg-slate-50/60 p-2"><p class="text-xs text-slate-500">Birim katkı</p><p class="mt-1 font-semibold {{ $item['unit_contribution'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $formatCompactMoney($item['unit_contribution']) }}</p></div></div>
                    <p class="mt-3 text-xs leading-5 text-slate-600">{{ $item['reason'] }}</p>
                </div>
            @empty
                <div class="p-6 text-center text-sm text-slate-500">Sermaye analizi için ürün bulunamadı.</div>
            @endforelse
        </div>
    </div>

    <p class="border-t border-slate-200 bg-slate-50/60 px-4 py-3 text-xs leading-5 text-slate-500">{{ data_get($capitalOptimization, 'evidence_note') }}</p>
</section>
