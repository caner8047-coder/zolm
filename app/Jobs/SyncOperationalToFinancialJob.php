<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\MpOrder;
use App\Models\MpOperationalOrder;
use App\Models\MpProduct;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncOperationalToFinancialJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1800; // 30 minutes

    public function handle(): void
    {
        Log::info("SyncOperationalToFinancialJob started.");

        // Find squashed financial orders that lack barcode/product info
        // We will match them by order_number
        $squashedOrders = MpOrder::whereNull('barcode')
            ->orWhere('barcode', '')
            ->orWhereNull('product_name')
            ->get();

        Log::info("Found " . $squashedOrders->count() . " squashed/empty-barcode orders to sync.");

        foreach ($squashedOrders as $finOrder) {
            $masterOp = MpOperationalOrder::with('items')->where('order_number', $finOrder->order_number)->first();
            if (!$masterOp || $masterOp->items->isEmpty()) {
                continue; // Cannot sync, operational data missing
            }

            try {
                DB::beginTransaction();

                $items = $masterOp->items;
                $itemCount = $items->count();

                if ($itemCount === 1) {
                    // 1-to-1 match. Just update the row.
                    $this->updateSingleFinancialRow($finOrder, $items->first());
                } else {
                    // Multi-Item split (The Blind Spot fix)
                    $this->splitAndDistributeFinancialRow($finOrder, $items);
                }

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Failed to sync order " . $finOrder->order_number . ": " . $e->getMessage());
            }
        }

        Log::info("SyncOperationalToFinancialJob completed.");
    }

    protected function updateSingleFinancialRow(MpOrder $finOrder, $item): void
    {
        $finOrder->barcode = $item->barcode;
        $finOrder->stock_code = $item->stock_code;
        $finOrder->product_name = $item->product_name;
        $finOrder->quantity = $item->quantity;
        
        // Operasyonel tablodan (master) tarihleri al
        if ($finOrder->operationalOrder) {
            $finOrder->delivery_date = $finOrder->operationalOrder->delivery_date;
        }

        // Cogs calculation
        $this->applyCogsAndSave($finOrder, $item->barcode, $item->quantity);
    }

    protected function splitAndDistributeFinancialRow(MpOrder $originalFinOrder, $items): void
    {
        // Toplam satış tutarı oranlaması (Ratio)
        $totalSalePrice = $items->sum('sale_price');
        // Eğer her ürün 0 TL ise, eşit dağıtmak için fallback
        if ($totalSalePrice <= 0) {
            $totalSalePrice = $items->count();
        }

        $originalData = $originalFinOrder->toArray();
        $baseGross = (float) $originalFinOrder->gross_amount;
        $baseDiscount = (float) $originalFinOrder->discount_amount;
        $baseCampaign = (float) $originalFinOrder->campaign_discount;
        $baseCommAmount = (float) $originalFinOrder->commission_amount;
        $baseCommTax = (float) $originalFinOrder->commission_tax;
        $baseCargoAmount = (float) $originalFinOrder->cargo_amount;
        $baseCargoTax = (float) $originalFinOrder->cargo_tax;
        $baseServiceFee = (float) $originalFinOrder->service_fee;
        $baseWithholding = (float) $originalFinOrder->withholding_tax;
        $baseNetHakedis = (float) $originalFinOrder->net_hakedis;

        $accumulatedGross = 0;
        $accumulatedDiscount = 0;
        $accumulatedCampaign = 0;
        $accumulatedCommAmount = 0;
        $accumulatedCommTax = 0;
        $accumulatedCargoAmount = 0;
        $accumulatedCargoTax = 0;
        $accumulatedServiceFee = 0;
        $accumulatedWithholding = 0;
        $accumulatedNetHakedis = 0;

        $count = $items->count();
        $index = 0;

        foreach ($items as $item) {
            $index++;
            
            // Eğer total sale price 0'dan büyükse ona orantıla, yoksa eşit böl (1/count)
            $ratio = $totalSalePrice > 0 ? ($item->sale_price / $totalSalePrice) : (1 / $count);
            
            // Son elemansa rounding error kalmasın diye kalan tutarı direkt aktar
            if ($index === $count) {
                $itemGross = $baseGross - $accumulatedGross;
                $itemDiscount = $baseDiscount - $accumulatedDiscount;
                $itemCampaign = $baseCampaign - $accumulatedCampaign;
                $itemCommAmount = $baseCommAmount - $accumulatedCommAmount;
                $itemCommTax = $baseCommTax - $accumulatedCommTax;
                $itemCargoAmount = $baseCargoAmount - $accumulatedCargoAmount;
                $itemCargoTax = $baseCargoTax - $accumulatedCargoTax;
                $itemServiceFee = $baseServiceFee - $accumulatedServiceFee;
                $itemWithholding = $baseWithholding - $accumulatedWithholding;
                $itemNetHakedis = $baseNetHakedis - $accumulatedNetHakedis;
            } else {
                $itemGross = round($baseGross * $ratio, 2);
                $itemDiscount = round($baseDiscount * $ratio, 2);
                $itemCampaign = round($baseCampaign * $ratio, 2);
                $itemCommAmount = round($baseCommAmount * $ratio, 2);
                $itemCommTax = round($baseCommTax * $ratio, 2);
                $itemCargoAmount = round($baseCargoAmount * $ratio, 2);
                $itemCargoTax = round($baseCargoTax * $ratio, 2);
                $itemServiceFee = round($baseServiceFee * $ratio, 2);
                $itemWithholding = round($baseWithholding * $ratio, 2);
                $itemNetHakedis = round($baseNetHakedis * $ratio, 2);

                $accumulatedGross += $itemGross;
                $accumulatedDiscount += $itemDiscount;
                $accumulatedCampaign += $itemCampaign;
                $accumulatedCommAmount += $itemCommAmount;
                $accumulatedCommTax += $itemCommTax;
                $accumulatedCargoAmount += $itemCargoAmount;
                $accumulatedCargoTax += $itemCargoTax;
                $accumulatedServiceFee += $itemServiceFee;
                $accumulatedWithholding += $itemWithholding;
                $accumulatedNetHakedis += $itemNetHakedis;
            }

            // Create or update record
            if ($index === 1) {
                // Update the original row for the first item
                $targetOrder = $originalFinOrder;
            } else {
                // Duplicate into a new row for subsequent items
                $targetOrder = new MpOrder();
                $targetOrder->fill($originalData);
                // Unset IDs so it creates a new record
                $targetOrder->id = null;
                $targetOrder->created_at = null;
                $targetOrder->updated_at = null;
                // Avoid composite key duplicate if applicable, though MpOrder composite is order_number + barcode
            }

            $targetOrder->barcode = $item->barcode;
            $targetOrder->stock_code = $item->stock_code;
            $targetOrder->product_name = $item->product_name;
            $targetOrder->quantity = $item->quantity;

            // Apply distributed values
            $targetOrder->gross_amount = $itemGross;
            $targetOrder->discount_amount = $itemDiscount;
            $targetOrder->campaign_discount = $itemCampaign;
            $targetOrder->commission_amount = $itemCommAmount;
            $targetOrder->commission_tax = $itemCommTax;
            $targetOrder->cargo_amount = $itemCargoAmount;
            $targetOrder->cargo_tax = $itemCargoTax;
            $targetOrder->service_fee = $itemServiceFee;
            $targetOrder->withholding_tax = $itemWithholding;
            $targetOrder->net_hakedis = $itemNetHakedis;

            $this->applyCogsAndSave($targetOrder, $item->barcode, $item->quantity);
        }
    }

    protected function applyCogsAndSave(MpOrder $order, $barcode, $quantity): void
    {
        // Ürün kütüphanesinden eşleştir:
        $product = MpProduct::where('barcode', $barcode)->first();
        if ($product) {
            $order->product_vat_rate = $product->vat_rate;
            $order->cogs_at_time = $product->cogs * $quantity;
            $order->packaging_cost_at_time = $product->packaging_cost * $quantity;
        }

        // Net Kâr Motorunu çalıştır
        $unitEconomics = new \App\Services\UnitEconomicsService();
        $calc = $unitEconomics->calculateForOrder($order);
        $order->calculated_net_profit = $calc['real_net_profit'] ?? 0;

        $order->save();
    }
}
