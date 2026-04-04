@php
    $snapshot = data_get($order, 'order_snapshot');
    $legacyOperationalOrder = data_get($order, 'legacy_operational_order');
    $legacyHasFinancial = $legacyOperationalOrder?->financialOrders?->isNotEmpty() ?? false;
    $legacyNetProfit = $legacyHasFinancial ? (float) $legacyOperationalOrder->total_net_profit : null;
    $profitState = $order->profit_state_metric ?? ($snapshot?->profit_state ?: 'estimated');
    $profitValue = (float) ($order->profit_value_metric ?? ($profitState === 'confirmed' ? $snapshot?->confirmed_profit : $snapshot?->estimated_profit));
    $grossRevenue = (float) ($order->gross_revenue_metric ?? $snapshot?->gross_revenue);
    $packages = $order->packages ?? collect();
    $items = $order->items ?? collect();
    $financialEvents = $order->financialEvents ?? collect();
    $actionRuns = ($order->actionRuns ?? collect())->sortByDesc('created_at')->values();
    $matchIssues = max(0, (int) ($order->item_lines_count ?? 0) - (int) ($order->matched_lines_count ?? 0));
    $formatMoney = fn ($value) => '₺' . number_format((float) $value, 2, ',', '.');
    $supportsPackagePicking = $this->orderSupportsCapability($order, 'package_picking');
    $supportsPackageInvoiced = $this->orderSupportsCapability($order, 'package_invoiced');
    $supportsCommonLabelCreate = $this->orderSupportsCapability($order, 'package_common_label_create');
    $supportsCommonLabelGet = $this->orderSupportsCapability($order, 'package_common_label_get');
    $supportsInvoiceLink = $this->orderSupportsCapability($order, 'package_invoice_link');
    $supportsAnyPackageOperation = $supportsPackagePicking || $supportsPackageInvoiced || $supportsCommonLabelCreate || $supportsCommonLabelGet || $supportsInvoiceLink;
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

    <div class="rounded-[8px] border border-slate-200 bg-slate-50/70 p-3 sm:p-4">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
            <div class="flex-1 min-w-0">
                <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Operasyon Aksiyonları</p>
                <h3 class="mt-2 text-lg font-semibold text-slate-900">Sipariş bazlı kontrollü işlemler</h3>
                <p class="mt-2 text-sm text-slate-500 line-clamp-2">
                    Bu katman güvenli yenileme, kâr yeniden hesaplama ve kanalın desteklediği paket operasyonları için kuyruklu çalışır. Desteklenmeyen operasyonlar bu ekranda gizlenir.
                </p>
            </div>

            <div class="text-sm text-slate-500">
                Son işlem: {{ $actionRuns->first()?->created_at?->format('d.m.Y H:i') ?: 'Henüz yok' }}
            </div>
        </div>

        @if(config('marketplace.features.order_actions_enabled', true))
            <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
                <button type="button"
                        wire:click="runOrderAction({{ $order->id }}, 'refresh_order')"
                        wire:loading.attr="disabled"
                        wire:target="runOrderAction"
                        class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-60">
                    Siparişi yenile
                </button>
                <button type="button"
                        wire:click="runOrderAction({{ $order->id }}, 'refresh_cargo')"
                        wire:loading.attr="disabled"
                        wire:target="runOrderAction"
                        class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-60">
                    Kargoyu yenile
                </button>
                <button type="button"
                        wire:click="runOrderAction({{ $order->id }}, 'refresh_finance')"
                        wire:loading.attr="disabled"
                        wire:target="runOrderAction"
                        class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-60">
                    Finansı yenile
                </button>
                <button type="button"
                        wire:click="runOrderAction({{ $order->id }}, 'recalculate_profit')"
                        wire:loading.attr="disabled"
                        wire:target="runOrderAction"
                        class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] bg-slate-900 px-4 py-3 sm:py-2 text-sm font-medium text-white transition hover:bg-slate-800 disabled:opacity-60">
                    Kârı hesapla
                </button>
            </div>

            <div class="mt-4 rounded-[6px] border border-slate-200 bg-slate-50/70 p-3 text-sm text-slate-500" wire:loading wire:target="runOrderAction">
                Sipariş aksiyonu kuyruğa alınıyor...
            </div>
        @endif

        @if($actionRuns->isNotEmpty())
            <div class="mt-4 space-y-2">
                @foreach($actionRuns->take(6) as $actionRun)
                    <div class="flex flex-col gap-3 rounded-[6px] border border-slate-200 bg-slate-50/70 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-medium text-slate-900">{{ $this->orderActionLabel($actionRun->action_type) }}</p>
                                <x-zolm.status-badge :tone="$this->orderActionStatusTone($actionRun->status)">
                                    {{ $this->orderActionStatusLabel($actionRun->status) }}
                                </x-zolm.status-badge>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ $actionRun->created_at?->format('d.m.Y H:i') ?: '-' }}
                                @if($actionRun->triggeredBy?->name)
                                    · {{ $actionRun->triggeredBy->name }}
                                @endif
                                · Deneme {{ max(1, (int) $actionRun->attempt_count) }}
                            </p>
                            @if($actionRun->error_message)
                                <p class="mt-1 text-xs text-rose-600">{{ $actionRun->error_message }}</p>
                            @endif
                        </div>
                        <div class="text-right text-xs text-slate-500">
                            @if($actionRun->package?->package_number)
                                <p>Paket {{ $actionRun->package->package_number }}</p>
                            @endif
                            @if($this->actionResponseSummary($actionRun))
                                <p>{{ $this->actionResponseSummary($actionRun) }}</p>
                            @endif
                            <p>{{ $actionRun->finished_at?->format('d.m.Y H:i') ?: 'Tamamlanma bekleniyor' }}</p>
                            @if(config('marketplace.features.order_action_retry_enabled', true) && $this->actionCanRetry($actionRun->status))
                                <button type="button"
                                        wire:click="retryActionRun({{ $actionRun->id }})"
                                        class="mt-2 inline-flex min-h-[36px] items-center justify-center rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50">
                                    Tekrar dene
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
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
            </div>
        @endunless

        <div class="rounded-[8px] border border-slate-200 bg-white p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Kargo ve paketler</p>
            @if($packages->isNotEmpty())
                <div class="mt-3 space-y-3">
                    @foreach($packages as $package)
                        <div class="rounded-[6px] border border-slate-200 bg-slate-50/70 p-3">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <p class="font-medium text-slate-900">{{ $package->cargo_company ?: 'Kargo bilgisi yok' }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $package->package_number ?: 'Paket no yok' }}</p>
                                </div>
                                <div class="flex flex-col items-stretch gap-2 sm:flex-row sm:items-center">
                                    <label class="inline-flex w-full items-center justify-between gap-2 rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-600 sm:w-auto sm:justify-start">
                                        <input type="checkbox"
                                               wire:model.live="selectedPackageIds"
                                               value="{{ $package->id }}"
                                               class="rounded border-slate-300 text-slate-900 shadow-sm focus:ring-indigo-200">
                                        <span>Toplu seç</span>
                                    </label>
                                    <x-zolm.status-badge :tone="$this->statusTone($package->package_status)">
                                        {{ $this->humanStatus($package->package_status) }}
                                    </x-zolm.status-badge>
                                </div>
                            </div>
                            <div class="mt-3 grid grid-cols-1 gap-2 text-xs text-slate-500 sm:grid-cols-2">
                                <div>
                                    <p>Takip</p>
                                    <p class="mt-1 font-mono text-slate-900">{{ $package->cargo_tracking_number ?: '-' }}</p>
                                </div>
                                <div>
                                    <p>Desi</p>
                                    <p class="mt-1 font-medium text-slate-900">{{ $package->cargo_desi ? number_format((float) $package->cargo_desi, 2, ',', '.') : '-' }}</p>
                                </div>
                                <div>
                                    <p>Kargoya verildi</p>
                                    <p class="mt-1 font-medium text-slate-900">{{ $package->shipped_at?->format('d.m.Y H:i') ?: '-' }}</p>
                                </div>
                                <div>
                                    <p>Teslim</p>
                                    <p class="mt-1 font-medium text-slate-900">{{ $package->delivered_at?->format('d.m.Y H:i') ?: '-' }}</p>
                                </div>
                            </div>

                            <div class="mt-4 rounded-[6px] border border-slate-200 bg-white p-3">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <p class="text-xs uppercase tracking-[0.16em] text-slate-500">Paket Operasyonları</p>
                                        <p class="mt-1 text-sm text-slate-500">Bu mağaza için aktif olan kanal operasyonları bu karttan tetiklenir.</p>
                                    </div>
                                    <div class="text-xs text-slate-400">PKT #{{ $package->id }}</div>
                                </div>

                                @if(config('marketplace.features.package_actions_enabled', true) && $supportsAnyPackageOperation)
                                    <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
                                        @if($supportsPackageInvoiced || $supportsInvoiceLink)
                                            <div>
                                                <label class="block text-xs font-medium text-slate-500">Fatura no</label>
                                                <input type="text"
                                                       wire:model.defer="packageActionForms.{{ $package->id }}.invoice_number"
                                                       class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:text-sm"
                                                       placeholder="FTR-2026-0001">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-slate-500">Fatura tarihi</label>
                                                <input type="date"
                                                       wire:model.defer="packageActionForms.{{ $package->id }}.invoice_date"
                                                       class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:text-sm">
                                            </div>
                                        @endif

                                        @if($supportsInvoiceLink)
                                            <div class="{{ ($supportsCommonLabelCreate || $supportsCommonLabelGet) ? '' : 'sm:col-span-2' }}">
                                                <label class="block text-xs font-medium text-slate-500">Fatura linki</label>
                                                <input type="url"
                                                       wire:model.defer="packageActionForms.{{ $package->id }}.invoice_link"
                                                       class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:text-sm"
                                                       placeholder="https://.../invoice.pdf">
                                            </div>
                                        @endif

                                        @if($supportsCommonLabelCreate || $supportsCommonLabelGet)
                                            <div>
                                                <label class="block text-xs font-medium text-slate-500">Label formatı</label>
                                                <select wire:model.defer="packageActionForms.{{ $package->id }}.label_format"
                                                        class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:text-sm">
                                                    <option value="ZPL">ZPL</option>
                                                    <option value="PDF">PDF</option>
                                                </select>
                                            </div>
                                            @if($supportsCommonLabelCreate)
                                                <div>
                                                    <label class="block text-xs font-medium text-slate-500">Koli adedi</label>
                                                    <input type="number"
                                                           min="1"
                                                           wire:model.defer="packageActionForms.{{ $package->id }}.box_quantity"
                                                           class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:text-sm"
                                                           placeholder="1">
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-medium text-slate-500">Desi</label>
                                                    <input type="number"
                                                           step="0.01"
                                                           min="0"
                                                           wire:model.defer="packageActionForms.{{ $package->id }}.desi"
                                                           class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:text-sm"
                                                           placeholder="{{ $package->cargo_desi ?: '0.00' }}">
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-medium text-slate-500">Volümetrik ağırlık</label>
                                                    <input type="number"
                                                           step="0.01"
                                                           min="0"
                                                           wire:model.defer="packageActionForms.{{ $package->id }}.volumetric_weight"
                                                           class="mt-1 w-full rounded-[6px] border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:text-sm"
                                                           placeholder="Opsiyonel">
                                                </div>
                                            @endif
                                        @endif
                                    </div>

                                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-3">
                                        @if($supportsPackagePicking)
                                            <button type="button"
                                                    wire:click="runPackageAction({{ $package->id }}, 'package_picking')"
                                                    wire:loading.attr="disabled"
                                                    wire:target="runPackageAction"
                                                    class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-60">
                                                Picking bildir
                                            </button>
                                        @endif

                                        @if($supportsPackageInvoiced)
                                            <button type="button"
                                                    wire:click="runPackageAction({{ $package->id }}, 'package_invoiced')"
                                                    wire:loading.attr="disabled"
                                                    wire:target="runPackageAction"
                                                    class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-60">
                                                Fatura kesildi
                                            </button>
                                        @endif

                                        @if($supportsCommonLabelCreate)
                                            <button type="button"
                                                    wire:click="runPackageAction({{ $package->id }}, 'package_common_label_create')"
                                                    wire:loading.attr="disabled"
                                                    wire:target="runPackageAction"
                                                    class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-60">
                                                Barkod talep et
                                            </button>
                                        @endif

                                        @if($supportsCommonLabelGet)
                                            <button type="button"
                                                    wire:click="runPackageAction({{ $package->id }}, 'package_common_label_get')"
                                                    wire:loading.attr="disabled"
                                                    wire:target="runPackageAction"
                                                    class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] border border-slate-200 bg-white px-4 py-3 sm:py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-60">
                                                Barkodu getir
                                            </button>
                                        @endif

                                        @if($supportsInvoiceLink)
                                            <button type="button"
                                                    wire:click="runPackageAction({{ $package->id }}, 'package_invoice_link')"
                                                    wire:loading.attr="disabled"
                                                    wire:target="runPackageAction"
                                                    class="inline-flex min-h-[44px] items-center justify-center rounded-[6px] bg-slate-900 px-4 py-3 sm:py-2 text-sm font-medium text-white transition hover:bg-slate-800 disabled:opacity-60">
                                                Fatura linki gönder
                                            </button>
                                        @endif
                                    </div>
                                @else
                                    <div class="mt-3 rounded-[6px] border border-dashed border-slate-300 bg-slate-50 px-4 py-5 text-sm text-slate-500">
                                        Bu mağazanın bağlayıcısında paket operasyon servisi tanımlı değil.
                                    </div>
                                @endif
                            </div>
                        </div>
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
                    Marj: <span class="font-semibold text-slate-900">%{{ number_format((float) ($snapshot->margin_percent ?? 0), 1, ',', '.') }}</span>
                    · İade etkisi: <span class="font-semibold text-slate-900">{{ $formatMoney($snapshot->return_effect ?? 0) }}</span>
                </div>
            @else
                <div class="mt-3 rounded-[6px] border border-dashed border-slate-300 bg-slate-50 px-4 py-5 text-sm text-slate-500">
                    Bu sipariş için henüz kâr snapshot’ı üretilmemiş.
                </div>
            @endif
        </div>
    </div>

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
                <article class="rounded-[6px] border border-slate-200 bg-slate-50/70 p-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-medium text-slate-900">{{ $item->product_name ?: 'Ürün adı yok' }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $item->stock_code ?: '-' }} · {{ $item->barcode ?: '-' }}</p>
                        </div>
                        <div class="text-right">
                            <p class="font-semibold text-slate-900">{{ $formatMoney($item->billable_amount ?: $item->gross_amount) }}</p>
                            <p class="mt-1 text-xs text-slate-500">x{{ (int) $item->quantity }}</p>
                        </div>
                    </div>
                    <div class="mt-3 grid grid-cols-1 gap-2 text-xs sm:grid-cols-2">
                        <div class="rounded-[6px] border border-slate-200 bg-white px-3 py-2">
                            <p class="text-slate-500">Komisyon</p>
                            <p class="mt-1 font-medium text-slate-900">%{{ number_format((float) ($item->commission_rate ?? 0), 1, ',', '.') }}</p>
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
                        <tr>
                            <td class="px-3 py-3">
                                <div class="font-medium text-slate-900">{{ $item->product_name ?: 'Ürün adı yok' }}</div>
                                @if($item->product?->brand)
                                    <div class="mt-1 text-xs text-slate-500">{{ $item->product->brand }}</div>
                                @endif
                            </td>
                            <td class="px-3 py-3">
                                <div class="font-mono text-xs text-slate-900">{{ $item->stock_code ?: '-' }}</div>
                                <div class="mt-1 text-[11px] text-slate-500">{{ $item->barcode ?: '-' }}</div>
                            </td>
                            <td class="px-3 py-3 text-center">{{ (int) $item->quantity }}</td>
                            <td class="px-3 py-3 text-right">{{ $formatMoney($item->gross_amount) }}</td>
                            <td class="px-3 py-3 text-right text-rose-600">
                                {{ $formatMoney(($item->discount_amount ?? 0) + ($item->marketplace_discount_amount ?? 0)) }}
                            </td>
                            <td class="px-3 py-3 text-right font-medium text-slate-900">{{ $formatMoney($item->billable_amount ?: $item->gross_amount) }}</td>
                            <td class="px-3 py-3 text-right">
                                <div class="font-medium text-slate-900">%{{ number_format((float) ($item->commission_rate ?? 0), 1, ',', '.') }}</div>
                                <div class="mt-1 text-[11px] text-slate-500">{{ $formatMoney((($item->billable_amount ?: $item->gross_amount) * ((float) ($item->commission_rate ?? 0))) / 100) }}</div>
                            </td>
                            <td class="px-3 py-3 text-right">
                                <div class="font-medium text-slate-900">{{ $formatMoney(((float) ($item->product?->cogs ?? 0) + (float) ($item->product?->packaging_cost ?? 0) + (float) ($item->product?->cargo_cost ?? 0)) * (int) $item->quantity) }}</div>
                                <div class="mt-1 text-[11px] text-slate-500">{{ $item->product ? 'Master ürün bağlı' : 'Bağlantı yok' }}</div>
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
