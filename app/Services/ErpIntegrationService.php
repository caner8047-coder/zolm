<?php

namespace App\Services;

use App\Models\MpOrder;
use App\Models\MpErpSetting;
use App\Jobs\PushOrderToErpJob;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ErpIntegrationService
{
    /**
     * Siparişi standart ZOLM JSON şemasına çevirir.
     */
    public function prepareOrderPayload(MpOrder $order): array
    {
        return [
            'metadata' => [
                'zolm_version' => '1.0',
                'pushed_at'    => now()->toIso8601String(),
                'platform'     => 'Trendyol',
            ],
            'order' => [
                'id'           => $order->id,
                'order_number' => $order->order_number,
                'order_date'   => $order->order_date ? $order->order_date->format('Y-m-d H:i:s') : null,
                'status'       => $order->status,
                'is_reconciled'=> $order->is_reconciled,
            ],
            'lines' => [
                [
                    'barcode'      => $order->barcode,
                    'stock_code'   => $order->stock_code,
                    'product_name' => $order->product_name,
                    'quantity'     => $order->quantity,
                ]
            ],
            'financials' => [
                'gross_sales'        => (float) $order->gross_amount,
                'discount_amount'    => (float) $order->discount_amount,
                'campaign_discount'  => (float) $order->campaign_discount,
                'net_hakedis'        => (float) $order->net_hakedis,
            ],
            'deductions' => [
                'commission' => [
                    'amount' => (float) $order->commission_amount,
                    'tax'    => (float) $order->commission_tax,
                ],
                'cargo' => [
                    'amount' => (float) $order->cargo_amount,
                    'tax'    => (float) $order->cargo_tax,
                    'desi'   => (float) $order->cargo_desi,
                ],
                'service_fee'     => (float) $order->service_fee,
                'withholding_tax' => (float) $order->withholding_tax,
            ],
            'economics' => [
                'cogs'           => (float) $order->cogs_at_time,
                'packaging_cost' => (float) $order->packaging_cost_at_time,
                'vat_balance'    => (float) $order->vat_balance,
                'real_net_profit'=> (float) $order->real_net_profit,
                'is_bleeding'    => (bool) $order->is_bleeding,
            ]
        ];
    }

    /**
     * Siparişleri ERP kuyruğuna atar
     */
    public function queueForErp(array $orderIds, MpErpSetting $setting)
    {
        if (!$setting || !$setting->webhook_url || !$setting->is_active) {
            Log::warning("ERP Gönderimi başarısız: Aktif ayar veya Webhook URL yok.");
            return;
        }

        $orders = MpOrder::whereIn('id', $orderIds)->get();

        foreach ($orders as $order) {
            // Statüyü pendinge çekiyoruz ki UI üzerinde kullanıcının anında "Bekliyor" gördüğü anlaşılsın.
            $order->update([
                'erp_status'   => 'pending',
                'erp_response' => null,
            ]);

            $payload = $this->prepareOrderPayload($order);

            // Job fırlat.
            PushOrderToErpJob::dispatch($order, $payload, $setting);
        }
    }
}
