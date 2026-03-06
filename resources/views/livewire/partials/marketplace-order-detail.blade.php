{{-- Sipariş Detay Paneli (Paylaşılan Partial) --}}
{{-- Hem mobil kart hem desktop tablo detayında kullanılır --}}
@php
    $hasFinancial = $hasFinancial ?? false;
    $netProfit = $netProfit ?? null;
@endphp

<!-- Müşteri & Fatura & Lojistik Grid -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 lg:gap-4">
    <!-- Müşteri Bilgileri -->
    <div class="bg-white rounded-lg border border-gray-200 p-3 lg:p-4">
        <h5 class="text-xs sm:text-sm font-bold text-gray-500 uppercase tracking-wide mb-2 flex items-center">
            <svg class="w-3.5 h-3.5 mr-1 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            Müşteri Bilgileri
        </h5>
        <dl class="space-y-1.5 text-xs sm:text-sm">
            <div class="flex justify-between"><dt class="text-gray-500">Ad Soyad</dt><dd class="font-medium text-gray-900">{{ $order->customer_name ?? '-' }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">Telefon</dt><dd class="font-medium text-gray-900">{{ $order->customer_phone ?? '-' }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">E-Posta</dt><dd class="font-medium text-gray-900 truncate max-w-[180px] sm:max-w-[140px]" title="{{ $order->email }}">{{ $order->email ?? '-' }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">Konum</dt><dd class="font-medium text-gray-900">{{ $order->customer_city }}, {{ $order->customer_district }} {{ $order->country ? '('.$order->country.')' : '' }}</dd></div>
            @if($order->customer_age || $order->customer_gender)
                <div class="flex justify-between"><dt class="text-gray-500">Demografik</dt><dd class="font-medium text-gray-900">{{ $order->customer_age ?? '-' }} / {{ $order->customer_gender ?? '-' }}</dd></div>
            @endif
            @if($order->customer_order_count)
                <div class="flex justify-between"><dt class="text-gray-500">Sipariş Geçmişi</dt><dd class="font-medium text-indigo-600">{{ $order->customer_order_count }}</dd></div>
            @endif
        </dl>
    </div>

    <!-- Fatura Bilgileri -->
    <div class="bg-white rounded-lg border border-gray-200 p-3 lg:p-4">
        <h5 class="text-xs sm:text-sm font-bold text-gray-500 uppercase tracking-wide mb-2 flex items-center">
            <svg class="w-3.5 h-3.5 mr-1 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Fatura Bilgileri
        </h5>
        <dl class="space-y-1.5 text-xs sm:text-sm">
            <div class="flex justify-between"><dt class="text-gray-500">Fatura No</dt><dd class="font-medium text-gray-900">{{ $order->invoice_number ?? '-' }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">Fatura Tarihi</dt><dd class="font-medium text-gray-900">{{ $order->invoice_date ? $order->invoice_date->format('d/m/Y') : '-' }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">Kesildi mi?</dt><dd class="font-medium {{ $order->is_invoiced === 'Evet' ? 'text-green-600' : 'text-gray-900' }}">{{ $order->is_invoiced ?? '-' }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">Tip</dt><dd class="font-medium text-gray-900">{{ $order->is_corporate_invoice === 'Evet' ? 'Kurumsal' : 'Bireysel' }}</dd></div>
            @if($order->company_name)
                <div class="flex justify-between"><dt class="text-gray-500">Firma</dt><dd class="font-medium text-gray-900 truncate max-w-[180px] sm:max-w-[140px]" title="{{ $order->company_name }}">{{ $order->company_name }}</dd></div>
            @endif
            @if($order->tax_number)
                <div class="flex justify-between"><dt class="text-gray-500">VKN</dt><dd class="font-mono font-medium text-gray-900">{{ $order->tax_number }}</dd></div>
            @endif
            @if($order->tax_office)
                <div class="flex justify-between"><dt class="text-gray-500">Vergi Dairesi</dt><dd class="font-medium text-gray-900">{{ $order->tax_office }}</dd></div>
            @endif
        </dl>
    </div>

    <!-- Lojistik & Tarihler -->
    <div class="bg-white rounded-lg border border-gray-200 p-3 lg:p-4">
        <h5 class="text-xs sm:text-sm font-bold text-gray-500 uppercase tracking-wide mb-2 flex items-center">
            <svg class="w-3.5 h-3.5 mr-1 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/></svg>
            Lojistik & Tarihler
        </h5>
        <dl class="space-y-1.5 text-xs sm:text-sm">
            <div class="flex justify-between"><dt class="text-gray-500">Kargo</dt><dd class="font-medium text-gray-900">{{ $order->cargo_company ?? '-' }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">Takip No</dt><dd class="font-mono font-medium text-gray-900 truncate max-w-[140px] sm:max-w-[120px]" title="{{ $order->tracking_number }}">{{ $order->tracking_number ?? '-' }}</dd></div>
            @if($order->cargo_code)
                <div class="flex justify-between"><dt class="text-gray-500">Kargo Kodu</dt><dd class="font-mono text-gray-900">{{ $order->cargo_code }}</dd></div>
            @endif
            <div class="flex justify-between"><dt class="text-gray-500">Sipariş</dt><dd class="font-medium text-gray-900">{{ $order->order_date ? $order->order_date->format('d/m/Y H:i') : '-' }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">Termin</dt><dd class="font-medium text-gray-900">{{ $order->deadline_date ? $order->deadline_date->format('d/m/Y') : '-' }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">Kargoya Teslim</dt><dd class="font-medium text-gray-900">{{ $order->cargo_delivery_date ? $order->cargo_delivery_date->format('d/m/Y H:i') : '-' }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">Teslim Tarihi</dt><dd class="font-medium text-gray-900">{{ $order->delivery_date ? $order->delivery_date->format('d/m/Y H:i') : '-' }}</dd></div>
            @if($order->second_tracking_number)
                <div class="flex justify-between"><dt class="text-gray-500">2. Teslimat</dt><dd class="font-medium text-gray-900">{{ $order->second_delivery_status }} - {{ $order->second_tracking_number }}</dd></div>
            @endif
        </dl>
    </div>
</div>

<!-- Muhasebe Özet -->
@if($hasFinancial)
<div class="bg-gradient-to-r from-indigo-50 to-purple-50 rounded-lg border border-indigo-200 p-3 lg:p-4">
    <h5 class="text-xs sm:text-sm font-bold text-indigo-700 uppercase tracking-wide mb-2 flex items-center">
        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
        Muhasebe Modülü Verileri
    </h5>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 text-center">
        <div>
            <div class="text-[10px] text-gray-500 uppercase">Net Hakediş</div>
            <div class="text-sm font-bold text-gray-900">₺{{ number_format($order->total_net_hakedis, 2, ',', '.') }}</div>
        </div>
        <div>
            <div class="text-[10px] text-gray-500 uppercase">Komisyon Toplam</div>
            <div class="text-sm font-bold text-red-600">₺{{ number_format($order->total_commission, 2, ',', '.') }}</div>
        </div>
        <div>
            <div class="text-[10px] text-gray-500 uppercase">Ort. Komisyon %</div>
            <div class="text-sm font-bold text-gray-900">%{{ number_format($order->avg_commission_rate, 1) }}</div>
        </div>
        <div>
            <div class="text-[10px] text-gray-500 uppercase">Kargo Kesintisi</div>
            <div class="text-sm font-bold text-orange-600">₺{{ number_format($order->total_cargo_amount, 2, ',', '.') }}</div>
        </div>
        <div>
            <div class="text-[10px] text-gray-500 uppercase">Net Hakediş</div>
            <div class="text-sm font-bold {{ $netProfit >= 0 ? 'text-green-600' : 'text-red-600' }}">
                {{ $netProfit >= 0 ? '+' : '' }}₺{{ number_format($netProfit, 2, ',', '.') }}
            </div>
        </div>
    </div>
</div>
@endif

<!-- Ürün Satırları -->
<div>
    <h5 class="text-xs sm:text-sm font-bold text-gray-500 uppercase tracking-wide mb-2">Siparişteki Ürünler ({{ $order->items->count() }} Satır)</h5>

    <!-- ══ MOBİL ÜRÜN KARTLARI (sm altı) ══ -->
    <div class="sm:hidden space-y-2">
        @foreach($order->items as $item)
        <div class="bg-white rounded-lg border border-gray-200 p-3">
            <!-- Ürün adı + Marka -->
            <div class="flex items-start justify-between gap-2 mb-2">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-gray-900 leading-snug">{{ $item->product_name ?? 'Ürün Adı Yok' }}</p>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="font-mono text-[11px] text-indigo-600 font-semibold">{{ $item->barcode ?? '-' }}</span>
                        @if($item->brand)
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-indigo-50 text-indigo-700">{{ $item->brand }}</span>
                        @endif
                    </div>
                </div>
                <div class="text-right flex-shrink-0">
                    <div class="text-sm font-bold text-gray-900">₺{{ number_format($item->sale_price, 2, ',', '.') }}</div>
                    <div class="text-xs text-gray-500">x{{ $item->quantity }}</div>
                </div>
            </div>
            <!-- Alt bilgi satırları -->
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs border-t border-gray-100 pt-2">
                @if($item->discount_amount > 0 || $item->trendyol_discount > 0)
                    <span class="text-red-500 font-medium">İnd: -₺{{ number_format($item->discount_amount, 2, ',', '.') }}</span>
                    @if($item->trendyol_discount > 0)
                        <span class="text-orange-500 font-medium">TY: -₺{{ number_format($item->trendyol_discount, 2, ',', '.') }}</span>
                    @endif
                @endif
                @if($item->commission_rate > 0)
                    @php
                        $commissionBase = $item->billable_amount > 0
                            ? $item->billable_amount
                            : max(0, $item->sale_price - $item->discount_amount - $item->trendyol_discount);
                        $commissionTL = $commissionBase * $item->commission_rate / 100;
                    @endphp
                    <span class="text-gray-600">Kom: %{{ number_format($item->commission_rate, 1) }}</span>
                    <span class="text-red-600 font-medium">-₺{{ number_format($commissionTL, 2, ',', '.') }}</span>
                @endif
                @if($item->billable_amount > 0)
                    <span class="text-gray-900 font-medium">Fat: ₺{{ number_format($item->billable_amount, 2, ',', '.') }}</span>
                @endif
                @if($item->cargo_desi)
                    <span class="text-gray-500">{{ number_format($item->cargo_desi, 1) }} desi</span>
                @endif
                @php $itemProduct = $item->relationLoaded('product') ? $item->product : null; @endphp
                @if($itemProduct && (float)$itemProduct->cogs > 0)
                    <span class="text-emerald-700 font-medium bg-emerald-50 px-1.5 py-0.5 rounded">COGS: ₺{{ number_format($itemProduct->cogs, 2, ',', '.') }}</span>
                    @if((float)($itemProduct->cargo_cost ?? 0) > 0)
                        <span class="text-orange-600 font-medium">Kargo: ₺{{ number_format($itemProduct->cargo_cost, 2, ',', '.') }}</span>
                    @endif
                @endif
            </div>
        </div>
        @endforeach
        <!-- Mobil Toplam -->
        <div class="bg-gray-50 rounded-lg border border-gray-200 p-3 flex items-center justify-between">
            <span class="text-xs font-bold text-gray-500 uppercase">Toplam (x{{ $order->items->sum('quantity') }})</span>
            <div class="text-right">
                <span class="text-sm font-bold text-gray-900">₺{{ number_format($order->items->sum('sale_price'), 2, ',', '.') }}</span>
                @php $totalDiscount = $order->items->sum('discount_amount') + $order->items->sum('trendyol_discount'); @endphp
                @if($totalDiscount > 0)
                    <span class="text-red-500 text-xs font-medium ml-2">-₺{{ number_format($totalDiscount, 2, ',', '.') }}</span>
                @endif
            </div>
        </div>
    </div>

    <!-- ══ DESKTOP ÜRÜN TABLOSU (sm ve üstü) ══ -->
    <div class="hidden sm:block bg-white rounded border border-gray-200 overflow-hidden shadow-sm overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr class="text-xs text-gray-500 uppercase text-left">
                    <th class="px-3 py-2 font-medium">Barkod / Stok</th>
                    <th class="px-3 py-2 font-medium">Marka</th>
                    <th class="px-3 py-2 font-medium w-full">Ürün Adı</th>
                    <th class="px-3 py-2 font-medium text-center">Adet</th>
                    <th class="px-3 py-2 font-medium text-right">Birim Fiyat</th>
                    <th class="px-3 py-2 font-medium text-right">Satış</th>
                    <th class="px-3 py-2 font-medium text-right">İndirim</th>
                    <th class="px-3 py-2 font-medium text-right">Komisyon</th>
                    <th class="px-3 py-2 font-medium text-right">Faturalanacak</th>
                    <th class="px-3 py-2 font-medium text-center">Desi</th>
                    <th class="px-3 py-2 font-medium text-right">Maliyet</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 text-sm">
                @foreach($order->items as $item)
                <tr class="hover:bg-gray-50">
                    <td class="px-3 py-2">
                        <div class="font-mono text-xs text-indigo-600 font-semibold">{{ $item->barcode ?? '-' }}</div>
                        <div class="text-[10px] text-gray-500">{{ $item->stock_code }}</div>
                    </td>
                    <td class="px-3 py-2">
                        @if($item->brand)
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-indigo-50 text-indigo-700">{{ $item->brand }}</span>
                        @else
                            <span class="text-gray-400 text-xs">-</span>
                        @endif
                    </td>
                    <td class="px-3 py-2 font-medium text-gray-700">{{ $item->product_name ?? 'Ürün Adı Yok' }}</td>
                    <td class="px-3 py-2 text-center text-gray-900 font-medium">x{{ $item->quantity }}</td>
                    <td class="px-3 py-2 text-right text-gray-500">₺{{ number_format($item->unit_price, 2, ',', '.') }}</td>
                    <td class="px-3 py-2 text-right text-gray-900 font-medium">₺{{ number_format($item->sale_price, 2, ',', '.') }}</td>
                    <td class="px-3 py-2 text-right">
                        @if($item->discount_amount > 0 || $item->trendyol_discount > 0)
                            <div class="text-red-500 text-xs">-₺{{ number_format($item->discount_amount, 2, ',', '.') }}</div>
                            @if($item->trendyol_discount > 0)
                                <div class="text-orange-500 text-[10px]">TY: -₺{{ number_format($item->trendyol_discount, 2, ',', '.') }}</div>
                            @endif
                        @else
                            <span class="text-gray-400 text-xs">-</span>
                        @endif
                    </td>
                    <td class="px-3 py-2 text-right">
                        @if($item->commission_rate > 0)
                            @php
                                $commissionBase = $item->billable_amount > 0
                                    ? $item->billable_amount
                                    : max(0, $item->sale_price - $item->discount_amount - $item->trendyol_discount);
                                $commissionTL = $commissionBase * $item->commission_rate / 100;
                            @endphp
                            <div class="text-gray-700 font-medium">%{{ number_format($item->commission_rate, 1) }}</div>
                            <div class="text-red-500 text-xs">-₺{{ number_format($commissionTL, 2, ',', '.') }}</div>
                        @else
                            <span class="text-gray-400 text-xs">-</span>
                        @endif
                    </td>
                    <td class="px-3 py-2 text-right text-gray-900 font-medium">
                        @if($item->billable_amount > 0) ₺{{ number_format($item->billable_amount, 2, ',', '.') }} @else <span class="text-gray-400 text-xs">-</span> @endif
                    </td>
                    <td class="px-3 py-2 text-center text-xs text-gray-600">
                        @if($item->cargo_desi) {{ number_format($item->cargo_desi, 1) }} @else <span class="text-gray-400">-</span> @endif
                    </td>
                    <td class="px-3 py-2 text-right">
                        @php $itemProduct = $item->relationLoaded('product') ? $item->product : null; @endphp
                        @if($itemProduct && (float)$itemProduct->cogs > 0)
                            <div class="text-xs text-red-500 font-medium">₺{{ number_format($itemProduct->cogs * $item->quantity, 2, ',', '.') }}</div>
                            @if((float)($itemProduct->cargo_cost ?? 0) > 0)
                                <div class="text-[10px] text-orange-500">+₺{{ number_format($itemProduct->cargo_cost * $item->quantity, 2, ',', '.') }}</div>
                            @endif
                        @else
                            <span class="text-gray-400 text-[10px] italic">-</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-gray-50 font-medium text-xs">
                <tr>
                    <td colspan="3" class="px-3 py-2 text-right text-gray-500 uppercase">Toplam</td>
                    <td class="px-3 py-2 text-center text-gray-900">x{{ $order->items->sum('quantity') }}</td>
                    <td class="px-3 py-2"></td>
                    <td class="px-3 py-2 text-right text-gray-900">₺{{ number_format($order->items->sum('sale_price'), 2, ',', '.') }}</td>
                    <td class="px-3 py-2 text-right text-red-500">-₺{{ number_format($order->items->sum('discount_amount') + $order->items->sum('trendyol_discount'), 2, ',', '.') }}</td>
                    <td class="px-3 py-2"></td>
                    <td class="px-3 py-2 text-right text-gray-900">
                        @php $totalBillable = $order->items->sum('billable_amount'); @endphp
                        @if($totalBillable > 0) ₺{{ number_format($totalBillable, 2, ',', '.') }} @endif
                    </td>
                    <td class="px-3 py-2"></td>
                    <td class="px-3 py-2 text-right text-red-500 font-medium">
                        @php
                            $totalCogs = $order->estimated_cogs;
                            $totalCargo = $order->estimated_cargo;
                        @endphp
                        @if($totalCogs > 0)
                            ₺{{ number_format($totalCogs + $totalCargo, 2, ',', '.') }}
                        @endif
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
