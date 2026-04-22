@php
    $snapshot = data_get($order, 'order_snapshot');
    $packages = $order->packages ?? collect();
    $items = $order->items ?? collect();
    $financialEvents = $order->financialEvents ?? collect();
    $formatMoney = fn ($value) => '₺' . number_format((float) $value, 2, ',', '.');
    $formatCount = fn ($value) => number_format((float) $value, 0, ',', '.');
    $profitValue = (float) ($order->profit_value_metric ?? ($snapshot?->profit_state === 'confirmed' ? $snapshot?->confirmed_profit : $snapshot?->estimated_profit));
    $profitDelta = (float) ($order->profit_delta_metric ?? 0);
    $deductionDelta = (float) ($order->deduction_delta_metric ?? 0);
    $reconciliationState = $order->reconciliation_state_metric ?? 'waiting';
@endphp

<div class="space-y-4 lg:space-y-5">
    <div class="grid grid-cols-1 sm:grid-cols-2 2xl:grid-cols-4 gap-3 lg:gap-4">
        <div class="min-w-0 rounded-3xl border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Kâr durumu</p>
            <div class="mt-2 flex items-center gap-2">
                <x-zolm.status-badge :tone="$this->profitStateTone($order->profit_state_metric ?? $snapshot?->profit_state)">
                    {{ $this->profitStateLabel($order->profit_state_metric ?? $snapshot?->profit_state) }}
                </x-zolm.status-badge>
            </div>
            <p class="mt-3 text-xl font-semibold {{ $profitValue >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ $formatMoney($profitValue) }}</p>
        </div>

        <div class="min-w-0 rounded-3xl border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Net alacak</p>
            <p class="mt-3 text-xl font-semibold text-slate-900">{{ $formatMoney($order->net_receivable_metric ?? 0) }}</p>
            <p class="mt-2 text-sm text-slate-500">{{ $formatCount($order->financial_event_count ?? 0) }} finans olayı</p>
        </div>

        <div class="min-w-0 rounded-3xl border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Mutabakat</p>
            <div class="mt-2 flex items-center gap-2">
                <x-zolm.status-badge :tone="$this->reconciliationStateTone($reconciliationState)">
                    {{ $this->reconciliationStateLabel($reconciliationState) }}
                </x-zolm.status-badge>
            </div>
            <p class="mt-3 text-xl font-semibold {{ $profitDelta >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ $formatMoney($profitDelta) }}</p>
            <p class="mt-2 text-sm text-slate-500">Kesinti farkı {{ $formatMoney($deductionDelta) }}</p>
        </div>

        <div class="min-w-0 rounded-3xl border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Son finans tarihi</p>
            <p class="mt-3 text-lg font-semibold text-slate-900">{{ $order->last_financial_event_at ? \Illuminate\Support\Carbon::parse($order->last_financial_event_at)->format('d.m.Y H:i') : '-' }}</p>
            <p class="mt-2 text-sm text-slate-500">Anlık kayıt {{ $snapshot?->calculated_at?->format('d.m.Y H:i') ?: 'henüz yok' }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 2xl:grid-cols-3 gap-3 lg:gap-4">
        <div class="min-w-0 rounded-3xl border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Sipariş özeti</p>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex items-start justify-between gap-3">
                    <dt class="text-slate-500">Müşteri</dt>
                    <dd class="min-w-0 break-words text-right font-medium text-slate-900">{{ $order->customer_name ?: '-' }}</dd>
                </div>
                <div class="flex items-start justify-between gap-3">
                    <dt class="text-slate-500">Mağaza</dt>
                    <dd class="min-w-0 break-words text-right font-medium text-slate-900">{{ $order->store?->store_name ?: '-' }}</dd>
                </div>
                <div class="flex items-start justify-between gap-3">
                    <dt class="text-slate-500">Firma</dt>
                    <dd class="min-w-0 break-words text-right font-medium text-slate-900">{{ data_get($order, 'store.legalEntity.name') ?: ($order->legal_entity_name_alias ?: '-') }}</dd>
                </div>
                <div class="flex items-start justify-between gap-3">
                    <dt class="text-slate-500">Durum</dt>
                    <dd>
                        <x-zolm.status-badge :tone="$this->statusTone($order->order_status, $order->marketplace_alias, data_get($order, 'packages.0.cargo_tracking_number'), data_get($order, 'packages.0.delivered_at'))">
                            {{ $this->humanStatus($order->order_status, $order->marketplace_alias, data_get($order, 'packages.0.cargo_tracking_number'), data_get($order, 'packages.0.delivered_at')) }}
                        </x-zolm.status-badge>
                    </dd>
                </div>
                <div class="flex items-start justify-between gap-3">
                    <dt class="text-slate-500">Sipariş tarihi</dt>
                    <dd class="min-w-0 break-words text-right font-medium text-slate-900">{{ $order->ordered_at?->format('d.m.Y H:i') ?: '-' }}</dd>
                </div>
            </dl>
        </div>

        <div class="min-w-0 rounded-3xl border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Anlık kayıt ve mutabakat</p>
            <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-3">
                    <p class="text-[10px] uppercase tracking-[0.16em] text-slate-500">Ciro</p>
                    <p class="mt-2 text-sm font-semibold text-slate-900">{{ $formatMoney($snapshot?->gross_revenue ?? $order->gross_revenue_metric ?? 0) }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-3">
                    <p class="text-[10px] uppercase tracking-[0.16em] text-slate-500">Tahmini kâr</p>
                    <p class="mt-2 text-sm font-semibold {{ (float) ($order->estimated_profit_metric ?? 0) >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ $formatMoney($order->estimated_profit_metric ?? 0) }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-3">
                    <p class="text-[10px] uppercase tracking-[0.16em] text-slate-500">Kesin kâr</p>
                    <p class="mt-2 text-sm font-semibold {{ (float) ($order->confirmed_profit_metric ?? 0) >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ $formatMoney($order->confirmed_profit_metric ?? 0) }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-3">
                    <p class="text-[10px] uppercase tracking-[0.16em] text-slate-500">Kâr farkı</p>
                    <p class="mt-2 text-sm font-semibold {{ $profitDelta >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ $formatMoney($profitDelta) }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-3">
                    <p class="text-[10px] uppercase tracking-[0.16em] text-slate-500">Kesinti farkı</p>
                    <p class="mt-2 text-sm font-semibold {{ $deductionDelta <= 0 ? 'text-emerald-600' : 'text-amber-600' }}">{{ $formatMoney($deductionDelta) }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-3">
                    <p class="text-[10px] uppercase tracking-[0.16em] text-slate-500">Marj</p>
                    <p class="mt-2 text-sm font-semibold text-slate-900">%{{ number_format((float) ($snapshot?->margin_percent ?? $order->margin_percent_metric ?? 0), 1, ',', '.') }}</p>
                </div>
            </div>
        </div>

        <div class="min-w-0 rounded-3xl border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Paket, satır ve kesinti özeti</p>
            <div class="mt-3 space-y-3 text-sm">
                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-3">
                    <p class="text-slate-500">Paket</p>
                    <p class="mt-1 font-medium text-slate-900">{{ $formatCount($packages->count()) }} paket</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-3">
                    <p class="text-slate-500">Satır</p>
                    <p class="mt-1 font-medium text-slate-900">{{ $formatCount($items->count()) }} satır · {{ $formatCount($order->total_quantity ?? 0) }} adet</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-3">
                    <p class="text-slate-500">Eşleşme</p>
                    <p class="mt-1 font-medium {{ ($order->item_lines_count ?? 0) === ($order->matched_lines_count ?? 0) ? 'text-emerald-600' : 'text-amber-600' }}">
                        {{ $formatCount($order->matched_lines_count ?? 0) }}/{{ $formatCount($order->item_lines_count ?? 0) }}
                    </p>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-3">
                        <p class="text-slate-500">Komisyon</p>
                        <p class="mt-1 font-medium text-rose-600">{{ $formatMoney($snapshot?->commission_total ?? $order->commission_total_metric ?? 0) }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-3">
                        <p class="text-slate-500">Kargo</p>
                        <p class="mt-1 font-medium text-amber-600">{{ $formatMoney($snapshot?->cargo_total ?? $order->cargo_total_metric ?? 0) }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-3">
                        <p class="text-slate-500">Hizmet</p>
                        <p class="mt-1 font-medium text-slate-900">{{ $formatMoney($snapshot?->service_fee_total ?? $order->service_fee_total_metric ?? 0) }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-3">
                        <p class="text-slate-500">Stopaj</p>
                        <p class="mt-1 font-medium text-indigo-600">{{ $formatMoney($snapshot?->withholding_total ?? $order->withholding_total_metric ?? 0) }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($financialEvents->isNotEmpty())
        <div class="rounded-3xl border border-slate-200 bg-white p-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex-1 min-w-0">
                    <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Finans Hareketleri</p>
                    <h3 class="mt-2 text-lg font-semibold text-slate-900">Son hareketler</h3>
                </div>
                <div class="text-sm text-slate-500">{{ $financialEvents->count() }} kayıt</div>
            </div>

            <div class="mt-4 space-y-3 md:hidden">
                @foreach($financialEvents->take(6) as $event)
                    @php
                        $signedAmount = $event->direction === 'credit' ? abs((float) $event->amount) : -abs((float) $event->amount);
                    @endphp
                    <article class="rounded-2xl border border-slate-200 bg-slate-50/70 p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-medium text-slate-900">{{ $this->humanEventType($event->event_type) }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $event->reference_number ?: '-' }}</p>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold {{ $signedAmount >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ $formatMoney($signedAmount) }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ ($event->settlement_date ?: $event->event_date)?->format('d.m.Y') ?: '-' }}</p>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="mt-4 hidden md:block overflow-x-auto rounded-lg border border-slate-200 pb-2 [scrollbar-gutter:stable]">
                <div class="mb-2 flex items-center justify-between gap-3 border-b border-slate-200 bg-slate-50/70 px-3 py-2 text-xs text-slate-500 2xl:hidden">
                    <span>Detay tablosu sığmazsa sağa kaydırabilirsiniz.</span>
                    <span class="font-medium text-slate-600">{{ $financialEvents->take(8)->count() }} hareket</span>
                </div>
                <table class="w-max min-w-full divide-y divide-slate-200" style="min-width: 920px;">
                    <thead class="bg-slate-50 text-slate-500">
                        <tr>
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em]">Tür</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em]">Kaynak</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em]">Referans</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em]">Tarih</th>
                            <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-[0.16em]">Tutar</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em]">Durum</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white text-sm text-slate-700">
                        @foreach($financialEvents->take(8) as $event)
                            @php
                                $signedAmount = $event->direction === 'credit' ? abs((float) $event->amount) : -abs((float) $event->amount);
                            @endphp
                            <tr>
                                <td class="px-3 py-3 font-medium text-slate-900">{{ $this->humanEventType($event->event_type) }}</td>
                                <td class="px-3 py-3 text-slate-500">{{ ucfirst((string) $event->event_source) }}</td>
                                <td class="px-3 py-3 text-slate-500">{{ $event->reference_number ?: '-' }}</td>
                                <td class="px-3 py-3 text-slate-500">{{ ($event->settlement_date ?: $event->event_date)?->format('d.m.Y H:i') ?: '-' }}</td>
                                <td class="px-3 py-3 text-right font-semibold {{ $signedAmount >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ $formatMoney($signedAmount) }}</td>
                                <td class="px-3 py-3">
                                    <x-zolm.status-badge :tone="in_array($event->status, ['posted', 'completed', 'settled'], true) ? 'success' : 'warning'">
                                        {{ in_array($event->status, ['posted', 'completed', 'settled'], true) ? 'İşlendi' : ($event->status ?: 'Bekliyor') }}
                                    </x-zolm.status-badge>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
