@php
    $snapshot = data_get($order, 'order_snapshot');
    $legacyOperationalOrder = data_get($order, 'legacy_operational_order');
    $legacyHasFinancial = $legacyOperationalOrder?->financialOrders?->isNotEmpty() ?? false;
    $profitState = $order->profit_state_metric ?? ($snapshot?->profit_state ?: 'estimated');
    $profitValue = (float) ($order->profit_value_metric ?? ($profitState === 'confirmed' ? $snapshot?->confirmed_profit : $snapshot?->estimated_profit));
    $legacyNetProfit = $legacyHasFinancial ? $profitValue : null;
    $productCostForProfitability = \App\Services\ProfitabilityMetric::productCost(
        (float) ($snapshot?->cogs_cost ?? 0),
        (float) ($snapshot?->packaging_cost ?? 0),
    );
    $profitabilityPercent = \App\Services\ProfitabilityMetric::profitPercent($profitValue, $productCostForProfitability);
    $grossRevenue = (float) ($order->gross_revenue_metric ?? $snapshot?->gross_revenue);
    $packages = $order->packages ?? collect();
    $items = $order->items ?? collect();
    $financialEvents = $order->financialEvents ?? collect();
    $matchIssues = max(0, (int) ($order->item_lines_count ?? 0) - (int) ($order->matched_lines_count ?? 0));
    $marketplaceKey = $order->store?->marketplace ?? $order->marketplace_alias ?? null;
    $formatMoney = fn ($value) => '₺' . number_format((float) $value, 2, ',', '.');
    $formatDate = function ($date): string {
        if (blank($date)) {
            return '-';
        }

        try {
            return $date instanceof \Carbon\CarbonInterface
                ? $date->format('d.m.Y H:i')
                : \Carbon\Carbon::parse((string) $date)->format('d.m.Y H:i');
        } catch (\Throwable) {
            return '-';
        }
    };
@endphp

<div class="space-y-3 sm:space-y-4">
    @if($legacyOperationalOrder)
        <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3 sm:p-4">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Operasyonel Sipariş Özeti</p>
                    <h3 class="mt-2 text-lg font-semibold text-slate-900">Eski sipariş ekranındaki tüm detaylar</h3>
                    <p class="mt-2 text-sm text-slate-500">
                        Müşteri, fatura, lojistik, muhasebe ve satır bazlı eski operasyon verisi bu blokta korunur.
                    </p>
                </div>
                <div class="text-sm text-slate-500">
                    Eski veri satırı: {{ $legacyOperationalOrder->items?->count() ?? 0 }}
                </div>
            </div>

            <div class="mt-4">
                @include('livewire.partials.marketplace-order-detail', [
                    'order' => $legacyOperationalOrder,
                    'hasFinancial' => $legacyHasFinancial,
                    'netProfit' => $legacyNetProfit,
                ])
            </div>
        </div>
    @endif

    <div class="rounded-[8px] border border-slate-200 bg-white p-3 sm:p-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs uppercase tracking-[0.2em] text-slate-500">{{ $legacyOperationalOrder ? 'Kanal Satırları ve Eşleşme' : 'Ürün Satırları' }}</p>
                <h3 class="mt-2 text-lg font-semibold text-slate-900">{{ $items->count() }} satır</h3>
            </div>
            <div class="text-sm text-slate-500 sm:text-right">
                Toplam indirim: <span class="font-semibold text-slate-900">{{ $formatMoney($order->total_discount_amount ?? 0) }}</span>
            </div>
        </div>

        <div class="mt-4 space-y-3 md:hidden">
            @foreach($items as $item)
                @php
                    $listingPublicUrl = $this->marketplacePublicProductUrlForOrderItem($item, $order);
                    $productManagerUrl = $this->productManagerUrlForOrderItem($item);
                    $commissionRate = $this->effectiveCommissionRateForOrderItem($item);
                @endphp
                <article class="rounded-[6px] border border-slate-200 bg-slate-50/70 p-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            @if($listingPublicUrl)
                                <a href="{{ $listingPublicUrl }}"
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   class="inline-flex max-w-full items-center gap-1 font-medium text-slate-900 underline-offset-2 transition hover:text-slate-700 hover:underline"
                                   title="Pazaryerinde aç">
                                    <span class="truncate">{{ $item->product_name ?: 'Ürün adı yok' }}</span>
                                    <span class="shrink-0 text-xs text-slate-400">↗</span>
                                </a>
                            @else
                                <p class="font-medium text-slate-900">{{ $item->product_name ?: 'Ürün adı yok' }}</p>
                            @endif
                            <p class="mt-1 text-xs text-slate-500">
                                @if($productManagerUrl && $item->stock_code)
                                    <a href="{{ $productManagerUrl }}"
                                       class="font-mono font-medium text-slate-700 underline-offset-2 transition hover:text-slate-950 hover:underline"
                                       title="ZOLM ürün kartını aç">
                                        {{ $item->stock_code }}
                                    </a>
                                @else
                                    <span>{{ $item->stock_code ?: '-' }}</span>
                                @endif
                                <span> · {{ $item->barcode ?: '-' }}</span>
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="font-semibold text-slate-900">{{ $formatMoney($item->billable_amount ?: $item->gross_amount) }}</p>
                            <p class="mt-1 text-xs text-slate-500">x{{ (int) $item->quantity }}</p>
                        </div>
                    </div>
                    <div class="mt-3 grid grid-cols-1 gap-2 text-xs sm:grid-cols-2">
                        <div class="rounded-[6px] border border-slate-200 bg-white px-3 py-2">
                            <p class="text-slate-500">Komisyon</p>
                            <p class="mt-1 font-medium text-slate-900">%{{ number_format($commissionRate, 1, ',', '.') }}</p>
                        </div>
                        <div class="rounded-[6px] border border-slate-200 bg-white px-3 py-2">
                            <p class="text-slate-500">Eşleşme</p>
                            <p class="mt-1 font-medium {{ $item->is_matched ? 'text-emerald-600' : 'text-amber-600' }}">{{ $item->is_matched ? 'Tamam' : 'Kontrol' }}</p>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="mt-4 hidden overflow-x-auto rounded-[6px] border border-slate-200 pb-2 [scrollbar-gutter:stable] md:block">
            <div class="mx-3 mt-3 flex items-center justify-between gap-3 rounded-[6px] border border-slate-200 bg-slate-50/70 px-3 py-2 text-xs text-slate-500 xl:hidden">
                <span>Satır tablosu sığmazsa sağa kaydırabilirsiniz.</span>
                <span class="font-medium text-slate-600">{{ $items->count() }} satır</span>
            </div>
            <table class="min-w-[920px] w-full divide-y divide-slate-200">
                <thead class="bg-slate-50 text-slate-500">
                    <tr>
                        <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em]">Ürün</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em]">Kimlik</th>
                        <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-[0.16em]">Adet</th>
                        <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-[0.16em]">Brüt</th>
                        <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-[0.16em]">İndirim</th>
                        <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-[0.16em]">Faturalanacak</th>
                        <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-[0.16em]">Komisyon</th>
                        <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-[0.16em]">Maliyet</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em]">Durum</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white text-sm text-slate-700">
                    @foreach($items as $item)
                        @php
                            $listingPublicUrl = $this->marketplacePublicProductUrlForOrderItem($item, $order);
                            $productManagerUrl = $this->productManagerUrlForOrderItem($item);
                            $commissionRate = $this->effectiveCommissionRateForOrderItem($item);
                            $commissionBaseAmount = (float) ($item->billable_amount ?: $item->gross_amount ?: ((float) $item->unit_price * (int) $item->quantity));
                            $composition = $item->product
                                ? app(\App\Services\ProductCompositionResolver::class)->resolve($item->product, max(1, (int) $item->quantity))
                                : null;
                            $lineCost = $composition
                                ? ((float) $composition['cogs_cost'] + (float) $composition['packaging_cost'] + (float) $composition['own_cargo_cost'])
                                : 0;
                        @endphp
                        <tr>
                            <td class="px-3 py-3">
                                @if($listingPublicUrl)
                                    <a href="{{ $listingPublicUrl }}"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       class="inline-flex max-w-full items-center gap-1 font-medium text-slate-900 underline-offset-2 transition hover:text-slate-700 hover:underline"
                                       title="Pazaryerinde aç">
                                        <span class="truncate">{{ $item->product_name ?: 'Ürün adı yok' }}</span>
                                        <span class="shrink-0 text-xs text-slate-400">↗</span>
                                    </a>
                                @else
                                    <div class="font-medium text-slate-900">{{ $item->product_name ?: 'Ürün adı yok' }}</div>
                                @endif
                                @if($item->product?->brand)
                                    <div class="mt-1 text-xs text-slate-500">{{ $item->product->brand }}</div>
                                @endif
                            </td>
                            <td class="px-3 py-3">
                                <div class="font-mono text-xs text-slate-900">
                                    @if($productManagerUrl && $item->stock_code)
                                        <a href="{{ $productManagerUrl }}"
                                           class="underline-offset-2 transition hover:text-slate-700 hover:underline"
                                           title="ZOLM ürün kartını aç">
                                            {{ $item->stock_code }}
                                        </a>
                                    @else
                                        {{ $item->stock_code ?: '-' }}
                                    @endif
                                </div>
                                <div class="mt-1 text-[11px] text-slate-500">{{ $item->barcode ?: '-' }}</div>
                            </td>
                            <td class="px-3 py-3 text-center">{{ (int) $item->quantity }}</td>
                            <td class="px-3 py-3 text-right">{{ $formatMoney($item->gross_amount) }}</td>
                            <td class="px-3 py-3 text-right text-rose-600">
                                {{ $formatMoney(($item->discount_amount ?? 0) + ($item->marketplace_discount_amount ?? 0)) }}
                            </td>
                            <td class="px-3 py-3 text-right font-medium text-slate-900">{{ $formatMoney($item->billable_amount ?: $item->gross_amount) }}</td>
                            <td class="px-3 py-3 text-right">
                                <div class="font-medium text-slate-900">%{{ number_format($commissionRate, 1, ',', '.') }}</div>
                                <div class="mt-1 text-[11px] text-slate-500">{{ $formatMoney(($commissionBaseAmount * $commissionRate) / 100) }}</div>
                            </td>
                            <td class="px-3 py-3 text-right">
                                <div class="font-medium text-slate-900">{{ $formatMoney($lineCost) }}</div>
                                <div class="mt-1 text-[11px] text-slate-500">
                                    {{ $composition && $composition['is_set'] ? 'Set içeriğinden' : ($item->product ? 'Master ürün bağlı' : 'Bağlantı yok') }}
                                </div>
                            </td>
                            <td class="px-3 py-3">
                                <div class="flex flex-col gap-2">
                                    <x-zolm.status-badge :tone="$item->is_matched ? 'success' : 'warning'">
                                        {{ $item->is_matched ? 'Eşleşti' : 'Kontrol' }}
                                    </x-zolm.status-badge>
                                    <span class="text-xs text-slate-500">{{ $this->humanStatus($item->line_status) }}</span>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4 lg:gap-4">
        <div class="rounded-[8px] border border-slate-200 bg-white/90 p-4 min-w-0">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500 truncate">Kâr durumu</p>
            <div class="mt-2 flex items-center gap-2">
                <x-zolm.status-badge :tone="$this->profitStateTone($profitState)">
                    {{ $this->profitStateLabel($profitState) }}
                </x-zolm.status-badge>
            </div>
            <p class="mt-3 text-xl font-semibold {{ $profitValue >= 0 ? 'text-emerald-600' : 'text-rose-600' }} truncate">{{ $formatMoney($profitValue) }}</p>
        </div>

        <div class="rounded-[8px] border border-slate-200 bg-white/90 p-4 min-w-0">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500 truncate">Ciro</p>
            <p class="mt-3 text-xl font-semibold text-slate-900 truncate">{{ $formatMoney($grossRevenue) }}</p>
            <p class="mt-2 text-sm text-slate-500 truncate">{{ (int) ($order->total_quantity ?? 0) }} adet · {{ $items->count() }} satır</p>
        </div>

        <div class="rounded-[8px] border border-slate-200 bg-white/90 p-4 min-w-0">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500 truncate">Eşleşme</p>
            <p class="mt-3 text-xl font-semibold text-slate-900">{{ (int) ($order->matched_lines_count ?? 0) }}/{{ (int) ($order->item_lines_count ?? 0) }}</p>
            <p class="mt-2 text-sm {{ $matchIssues > 0 ? 'text-amber-600' : 'text-emerald-600' }} truncate">
                {{ $matchIssues > 0 ? $matchIssues . ' satır kontrol bekliyor' : 'Tüm satırlar eşleşmiş' }}
            </p>
        </div>

        <div class="rounded-[8px] border border-slate-200 bg-white/90 p-4 min-w-0">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500 truncate">Finans olayları</p>
            <p class="mt-3 text-xl font-semibold text-slate-900">{{ $financialEvents->count() }}</p>
            <p class="mt-2 text-sm text-slate-500 truncate">{{ $snapshot?->calculated_at?->format('d.m.Y H:i') ?: 'Henüz snapshot yok' }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 {{ $legacyOperationalOrder ? 'xl:grid-cols-2' : 'xl:grid-cols-3' }} gap-3 lg:gap-4">
        @unless($legacyOperationalOrder)
            <div class="rounded-[8px] border border-slate-200 bg-white p-4">
                <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Müşteri ve fatura</p>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex items-start justify-between gap-3">
                        <dt class="text-slate-500">Müşteri</dt>
                        <dd class="text-right font-medium text-slate-900">{{ $order->customer_name ?: '-' }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-3">
                        <dt class="text-slate-500">Telefon</dt>
                        <dd class="text-right font-medium text-slate-900">{{ $order->customer_phone ?: '-' }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-3">
                        <dt class="text-slate-500">E-posta</dt>
                        <dd class="min-w-0 text-right font-medium text-slate-900 truncate">{{ $order->customer_email ?: '-' }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-3">
                        <dt class="text-slate-500">Firma</dt>
                        <dd class="text-right font-medium text-slate-900">{{ $order->billing_name ?: ($order->legalEntity?->name ?: '-') }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-3">
                        <dt class="text-slate-500">Vergi No</dt>
                        <dd class="text-right font-mono font-medium text-slate-900">{{ $order->billing_tax_number ?: ($order->legalEntity?->tax_number ?: '-') }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-3">
                        <dt class="text-slate-500">Konum</dt>
                        <dd class="text-right font-medium text-slate-900">{{ $order->shipment_city ?: '-' }}{{ $order->shipment_district ? ', ' . $order->shipment_district : '' }}</dd>
                    </div>
                </dl>
                @if($order->customer_note)
                    <div class="mt-4 rounded-[6px] border border-slate-200 bg-slate-50/70 px-3 py-3">
                        <p class="text-[10px] uppercase tracking-[0.16em] text-slate-500">Müşteri notu</p>
                        <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $order->customer_note }}</p>
                    </div>
                @endif
            </div>
        @endunless

        <div class="rounded-[8px] border border-slate-200 bg-white p-3 sm:p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Kargo ve paketler</p>
                    <p class="mt-2 text-sm font-medium text-slate-900">{{ $packages->count() }} paket</p>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row">
                    @if(config('marketplace.features.order_actions_enabled', true))
                        <button type="button"
                                wire:click="runOrderAction({{ $order->id }}, 'refresh_cargo')"
                                wire:loading.attr="disabled"
                                wire:target="runOrderAction"
                                class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-60 sm:w-auto sm:py-2">
                            Kargo bilgisini yenile
                        </button>
                    @endif
                    <button type="button"
                            wire:click="openEditOrder({{ $order->id }})"
                            wire:loading.attr="disabled"
                            wire:target="openEditOrder({{ $order->id }})"
                            class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[6px] bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 disabled:opacity-60 sm:w-auto sm:py-2">
                        Manuel düzenle
                    </button>
                </div>
            </div>

            @if($packages->isNotEmpty())
                <div class="mt-3 space-y-3">
                    @foreach($packages as $package)
                        @php
                            $cargoCompany = $this->displayCargoCompany($package->cargo_company, $package->shipment_provider);
                            $trackingUrl = $this->trackingUrl($cargoCompany, $package->cargo_tracking_number);
                            $packageReference = $package->package_number ?: ($package->external_package_id ?: '-');
                            $orderDate = $this->displayOrderDate($order);
                            $cargoDueAt = $this->displayCargoDueDate($order);
                            $shipmentAt = $this->packageShipmentAt($package, $marketplaceKey);
                            $latestPackageActionRun = ($order->actionRuns ?? collect())
                                ->where('channel_order_package_id', $package->id)
                                ->sortByDesc('created_at')
                                ->first();
                        @endphp

                        <article class="rounded-[8px] border border-slate-200 bg-slate-50/60 p-3">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="font-semibold text-slate-900">{{ $cargoCompany ?: 'Kargo bilgisi yok' }}</p>
                                        <x-zolm.status-badge :tone="$this->statusTone($package->package_status, $marketplaceKey, $package->cargo_tracking_number, $package->delivered_at)">
                                            {{ $this->humanStatus($package->package_status, $marketplaceKey, $package->cargo_tracking_number, $package->delivered_at) }}
                                        </x-zolm.status-badge>
                                    </div>
                                    <p class="mt-2 text-sm text-slate-500">
                                        Takip no:
                                        @if($package->cargo_tracking_number)
                                            @if($trackingUrl)
                                                <a href="{{ $trackingUrl }}"
                                                   target="_blank"
                                                   rel="noopener noreferrer"
                                                   class="font-mono font-medium text-sky-700 underline-offset-2 transition hover:text-sky-800 hover:underline">
                                                    {{ $package->cargo_tracking_number }}
                                                </a>
                                            @else
                                                <span class="font-mono font-medium text-slate-900">{{ $package->cargo_tracking_number }}</span>
                                            @endif
                                        @else
                                            <span class="text-slate-400">-</span>
                                        @endif
                                    </p>
                                </div>

                                <div class="text-xs leading-5 text-slate-500 sm:text-right">
                                    <p>Kanal paket</p>
                                    <p class="font-mono font-medium text-slate-900">{{ $packageReference }}</p>
                                </div>
                            </div>

                            <dl class="mt-3 divide-y divide-slate-200 border-t border-slate-200 text-sm">
                                <div class="flex flex-col gap-1 py-2 sm:flex-row sm:items-center sm:justify-between">
                                    <dt class="text-slate-500">Sipariş tarihi</dt>
                                    <dd class="font-medium text-slate-900">{{ $formatDate($orderDate) }}</dd>
                                </div>
                                <div class="flex flex-col gap-1 py-2 sm:flex-row sm:items-center sm:justify-between">
                                    <dt class="text-slate-500">Kargoya son teslim</dt>
                                    <dd class="font-medium text-slate-900">{{ $formatDate($cargoDueAt) }}</dd>
                                </div>
                                <div class="flex flex-col gap-1 py-2 sm:flex-row sm:items-center sm:justify-between">
                                    <dt class="text-slate-500">{{ $this->shipmentDateLabel($marketplaceKey, $package->package_status, $package->cargo_tracking_number, $package->delivered_at, $package->raw_payload) }}</dt>
                                    <dd class="font-medium text-slate-900">{{ $formatDate($shipmentAt) }}</dd>
                                </div>
                                <div class="flex flex-col gap-1 py-2 sm:flex-row sm:items-center sm:justify-between">
                                    <dt class="text-slate-500">Teslim</dt>
                                    <dd class="font-medium text-slate-900">{{ $formatDate($package->delivered_at) }}</dd>
                                </div>
                                @if($package->cargo_desi)
                                    <div class="flex flex-col gap-1 py-2 sm:flex-row sm:items-center sm:justify-between">
                                        <dt class="text-slate-500">Desi</dt>
                                        <dd class="font-medium text-slate-900">{{ number_format((float) $package->cargo_desi, 2, ',', '.') }}</dd>
                                    </div>
                                @endif
                                <div class="flex flex-col gap-1 py-2 sm:flex-row sm:items-center sm:justify-between">
                                    <dt class="text-slate-500">Son güncelleme</dt>
                                    <dd class="font-medium text-slate-900">{{ $formatDate($package->last_synced_at) }}</dd>
                                </div>
                            </dl>

                            @if($latestPackageActionRun)
                                <div class="mt-2 flex flex-col gap-2 border-t border-slate-200 pt-3 text-xs sm:flex-row sm:items-center sm:justify-between">
                                    <div class="min-w-0">
                                        <span class="text-slate-500">Son işlem:</span>
                                        <span class="font-medium text-slate-900">{{ $this->orderActionLabel($latestPackageActionRun->action_type) }}</span>
                                        <x-zolm.status-badge :tone="$this->orderActionStatusTone($latestPackageActionRun->status)" size="xs" class="ml-1">
                                            {{ $this->orderActionStatusLabel($latestPackageActionRun->status) }}
                                        </x-zolm.status-badge>
                                        @if($latestPackageActionRun->error_message)
                                            <p class="mt-1 truncate text-rose-600" title="{{ $latestPackageActionRun->error_message }}">{{ $latestPackageActionRun->error_message }}</p>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-2 text-slate-400">
                                        <span>{{ $formatDate($latestPackageActionRun->created_at) }}</span>
                                        @if($this->actionCanRetry($latestPackageActionRun->status))
                                            <button type="button"
                                                    wire:click="retryActionRun({{ $latestPackageActionRun->id }})"
                                                    wire:loading.attr="disabled"
                                                    wire:target="retryActionRun"
                                                    class="inline-flex min-h-[32px] items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-60">
                                                Tekrar dene
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>
            @else
                <div class="mt-3 rounded-[6px] border border-dashed border-slate-300 bg-slate-50 px-4 py-5 text-sm text-slate-500">
                    Bu sipariş için henüz paket verisi yok.
                </div>
            @endif
        </div>

        <div class="rounded-[8px] border border-slate-200 bg-white p-3 sm:p-4 min-w-0">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Finans özeti</p>
            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 p-3 min-w-0">
                    <p class="text-[10px] uppercase tracking-[0.16em] text-slate-500 truncate">Net alacak</p>
                    <p class="mt-2 text-sm font-semibold text-slate-900 truncate">{{ $formatMoney($snapshot?->net_receivable ?? 0) }}</p>
                </div>
                <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 p-3 min-w-0">
                    <p class="text-[10px] uppercase tracking-[0.16em] text-slate-500 truncate">Komisyon</p>
                    <p class="mt-2 text-sm font-semibold text-rose-600 truncate">{{ $formatMoney($snapshot?->commission_total ?? 0) }}</p>
                </div>
                <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 p-3 min-w-0">
                    <p class="text-[10px] uppercase tracking-[0.16em] text-slate-500 truncate">Hizmet bedeli ve diğer</p>
                    <p class="mt-2 text-sm font-semibold text-rose-600 truncate">{{ $formatMoney($snapshot?->service_fee_total ?? 0) }}</p>
                </div>
                <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 p-3 min-w-0">
                    <p class="text-[10px] uppercase tracking-[0.16em] text-slate-500 truncate">E-ticaret stopajı</p>
                    <p class="mt-2 text-sm font-semibold text-rose-600 truncate">{{ $formatMoney($snapshot?->withholding_total ?? 0) }}</p>
                </div>
                <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 p-3 min-w-0">
                    <p class="text-[10px] uppercase tracking-[0.16em] text-slate-500 truncate">Kargo etkisi</p>
                    <p class="mt-2 text-sm font-semibold text-amber-600 truncate">{{ $formatMoney(($snapshot?->cargo_total ?? 0) + ($snapshot?->own_cargo_cost ?? 0)) }}</p>
                </div>
                <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 p-3 min-w-0">
                    <p class="text-[10px] uppercase tracking-[0.16em] text-slate-500 truncate">Maliyet + ambalaj</p>
                    <p class="mt-2 text-sm font-semibold text-slate-900 truncate">{{ $formatMoney(($snapshot?->cogs_cost ?? 0) + ($snapshot?->packaging_cost ?? 0)) }}</p>
                </div>
            </div>

            @if($snapshot)
                <div class="mt-3 rounded-[6px] border border-slate-200 bg-slate-50/70 p-3 text-sm text-slate-500">
                    Maliyet getirisi: <span class="font-semibold text-slate-900">{{ $profitabilityPercent !== null ? '%' . number_format($profitabilityPercent, 1, ',', '.') : '—' }}</span>
                    · İade etkisi: <span class="font-semibold text-slate-900">{{ $formatMoney($snapshot->return_effect ?? 0) }}</span>
                </div>
            @else
                <div class="mt-3 rounded-[6px] border border-dashed border-slate-300 bg-slate-50 px-4 py-5 text-sm text-slate-500">
                    Bu sipariş için henüz kâr snapshot’ı üretilmemiş.
                </div>
            @endif
        </div>
    </div>

    @if($financialEvents->isNotEmpty())
        <div class="rounded-[8px] border border-slate-200 bg-white p-3 sm:p-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Finans Hareketleri</p>
                    <h3 class="mt-2 text-lg font-semibold text-slate-900">Son hareketler</h3>
                </div>
                <div class="text-sm text-slate-500">{{ $financialEvents->count() }} kayıt</div>
            </div>

            <div class="mt-4 space-y-3">
                @foreach($financialEvents->take(6) as $event)
                    <div class="flex flex-col gap-3 rounded-[6px] border border-slate-200 bg-slate-50/70 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <p class="font-medium text-slate-900">{{ Str::headline((string) $event->event_type) }}</p>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ Str::headline((string) $event->event_source) }}
                                · {{ $event->event_date?->format('d.m.Y H:i') ?: ($event->settlement_date?->format('d.m.Y H:i') ?: '-') }}
                            </p>
                            @if($event->reference_number)
                                <p class="mt-1 text-[11px] font-mono text-slate-400">{{ $event->reference_number }}</p>
                            @endif
                        </div>
                        <div class="text-right">
                            <p class="font-semibold {{ $event->direction === 'credit' ? 'text-emerald-600' : 'text-rose-600' }}">
                                {{ $event->direction === 'credit' ? '+' : '-' }}{{ $formatMoney($event->amount) }}
                            </p>
                            <p class="mt-1 text-xs text-slate-500">{{ Str::headline((string) $event->status) }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
