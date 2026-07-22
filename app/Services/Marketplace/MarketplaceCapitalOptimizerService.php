<?php

namespace App\Services\Marketplace;

use App\Models\ChannelOrderItem;
use App\Models\MpProduct;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MarketplaceCapitalOptimizerService
{
    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function analyze(int $userId, array $filters = []): array
    {
        $sales = ChannelOrderItem::query()
            ->selectRaw('channel_order_items.mp_product_id')
            ->selectRaw('COALESCE(SUM(channel_order_items.quantity), 0) as sold_quantity')
            ->selectRaw('COALESCE(SUM(COALESCE(NULLIF(channel_order_items.billable_amount, 0), NULLIF(channel_order_items.gross_amount, 0), COALESCE(channel_order_items.unit_price, 0) * COALESCE(channel_order_items.quantity, 0)) * COALESCE(channel_orders.exchange_rate, 1)), 0) as gross_revenue')
            ->join('channel_orders', 'channel_orders.id', '=', 'channel_order_items.channel_order_id')
            ->join('marketplace_stores', 'marketplace_stores.id', '=', 'channel_orders.store_id')
            ->where('marketplace_stores.user_id', $userId)
            ->whereNotNull('channel_order_items.mp_product_id')
            ->when(filled($filters['marketplace'] ?? null), fn ($query) => $query->where('marketplace_stores.marketplace', $filters['marketplace']))
            ->when(filled($filters['store_id'] ?? null), fn ($query) => $query->where('channel_orders.store_id', (int) $filters['store_id']))
            ->when(filled($filters['legal_entity_id'] ?? null), fn ($query) => $query->where('channel_orders.legal_entity_id', (int) $filters['legal_entity_id']))
            ->when(filled($filters['date_from'] ?? null), fn ($query) => $query->whereDate('channel_orders.ordered_at', '>=', $filters['date_from']))
            ->when(filled($filters['date_to'] ?? null), fn ($query) => $query->whereDate('channel_orders.ordered_at', '<=', $filters['date_to']))
            ->groupBy('channel_order_items.mp_product_id');

        $rows = MpProduct::query()
            ->leftJoinSub($sales, 'capital_sales', fn ($join) => $join->on('capital_sales.mp_product_id', '=', 'mp_products.id'))
            ->where('mp_products.user_id', $userId)
            ->whereIn('mp_products.status', ['active', 'out_of_stock'])
            ->select([
                'mp_products.id',
                'mp_products.product_name',
                'mp_products.stock_code',
                'mp_products.barcode',
                'mp_products.stock_quantity',
                'mp_products.cogs',
                'mp_products.packaging_cost',
                'mp_products.cargo_cost',
                'mp_products.sale_price',
                'mp_products.commission_rate',
                DB::raw('COALESCE(capital_sales.sold_quantity, 0) as sold_quantity'),
                DB::raw('COALESCE(capital_sales.gross_revenue, 0) as gross_revenue'),
            ])
            ->limit(500)
            ->get();

        return $this->analyzeRows($rows, $this->periodDays($filters));
    }

    /** @param Collection<int, mixed> $rows @return array<string, mixed> */
    public function analyzeRows(Collection $rows, int $periodDays): array
    {
        $periodDays = max(1, $periodDays);
        $items = $rows->map(function ($row) use ($periodDays): array {
            $stock = max(0, (int) data_get($row, 'stock_quantity', 0));
            $sold = max(0, (float) data_get($row, 'sold_quantity', 0));
            $revenue = max(0, (float) data_get($row, 'gross_revenue', 0));
            $unitCost = max(0, (float) data_get($row, 'cogs', 0))
                + max(0, (float) data_get($row, 'packaging_cost', 0))
                + max(0, (float) data_get($row, 'cargo_cost', 0));
            $dailySales = $sold / $periodDays;
            $daysCover = $dailySales > 0 ? round($stock / $dailySales, 1) : null;
            $averageSalePrice = $sold > 0 ? $revenue / $sold : max(0, (float) data_get($row, 'sale_price', 0));
            $commission = $averageSalePrice * (max(0, (float) data_get($row, 'commission_rate', 0)) / 100);
            $unitContribution = $averageSalePrice - $unitCost - $commission;
            $marginPercent = $averageSalePrice > 0 ? ($unitContribution / $averageSalePrice) * 100 : 0;
            $inventoryCapital = $stock * $unitCost;
            $monthlyProfitRunRate = $dailySales * 30 * $unitContribution;
            $capitalEfficiency = $inventoryCapital > 0 ? ($monthlyProfitRunRate / $inventoryCapital) * 100 : null;
            [$decision, $priority, $reason] = $this->decision($unitCost, $sold, $stock, $daysCover, $marginPercent, $unitContribution);
            $targetStock = $dailySales > 0 ? (int) ceil($dailySales * 45) : 0;
            $releasableUnits = $decision === 'reduce'
                ? max(0, $stock - $targetStock)
                : 0;

            return [
                'product_id' => (int) data_get($row, 'id', 0),
                'product_name' => (string) data_get($row, 'product_name', 'Ürün'),
                'stock_code' => (string) data_get($row, 'stock_code', ''),
                'stock_quantity' => $stock,
                'sold_quantity' => round($sold, 1),
                'daily_sales' => round($dailySales, 2),
                'days_cover' => $daysCover,
                'unit_cost' => round($unitCost, 2),
                'unit_contribution' => round($unitContribution, 2),
                'margin_percent' => round($marginPercent, 1),
                'inventory_capital' => round($inventoryCapital, 2),
                'monthly_profit_run_rate' => round($monthlyProfitRunRate, 2),
                'capital_efficiency_percent' => $capitalEfficiency !== null ? round($capitalEfficiency, 1) : null,
                'decision' => $decision,
                'decision_label' => $this->decisionLabel($decision),
                'priority' => $priority,
                'reason' => $reason,
                'target_stock_45d' => $targetStock,
                'releasable_units' => $releasableUnits,
                'releasable_capital' => round($releasableUnits * $unitCost, 2),
            ];
        })->sortBy(fn (array $item): array => [$this->priorityOrder($item['priority']), -1 * $item['inventory_capital']])->values();

        $totalCapital = (float) $items->sum('inventory_capital');
        $releasableCapital = (float) $items->sum('releasable_capital');
        $dataReady = $items->where('unit_cost', '>', 0)->where('sold_quantity', '>', 0)->count();

        return [
            'summary' => [
                'product_count' => $items->count(),
                'inventory_capital' => round($totalCapital, 2),
                'releasable_capital' => round($releasableCapital, 2),
                'releasable_percent' => $totalCapital > 0 ? round(($releasableCapital / $totalCapital) * 100, 1) : 0.0,
                'grow_count' => $items->where('decision', 'grow')->count(),
                'protect_count' => $items->where('decision', 'protect')->count(),
                'reduce_count' => $items->where('decision', 'reduce')->count(),
                'investigate_count' => $items->where('decision', 'investigate')->count(),
                'data_readiness_percent' => $items->isNotEmpty() ? round(($dataReady / $items->count()) * 100, 1) : 0.0,
            ],
            'items' => $items->take(20)->all(),
            'period_days' => $periodDays,
            'generated_at' => now()->toIso8601String(),
            'evidence_note' => 'Tahmini sermaye = stok × (ürün maliyeti + ambalaj + kargo). Stok gün kapsamı seçili dönemdeki satış hızına, aylık katkı tahmini ise aynı hızın 30 güne taşınmasına dayanır. Öneriler otomatik satın alma veya tasfiye emri değildir.',
        ];
    }

    /** @return array{string, string, string} */
    private function decision(float $unitCost, float $sold, int $stock, ?float $daysCover, float $margin, float $contribution): array
    {
        if ($unitCost <= 0 || ($sold <= 0 && $stock > 0)) {
            return ['investigate', $unitCost <= 0 ? 'high' : 'medium', $unitCost <= 0 ? 'Maliyet eksik; sermaye hesabı güvenilir değil.' : 'Seçili dönemde satış yok; stok talebi doğrulanmalı.'];
        }
        if ($contribution <= 0 || ($daysCover !== null && $daysCover > 90 && $margin < 15)) {
            return ['reduce', 'high', $contribution <= 0 ? 'Birim katkı negatif; stok yeni sermaye bağlamamalı.' : 'Stok kapsamı 90 günün üzerinde ve marj sınırlı.'];
        }
        if ($daysCover !== null && $daysCover < 14 && $margin >= 15) {
            return ['protect', 'high', 'Kârlı ürünün stok kapsamı 14 günün altında; satış kaybı riski var.'];
        }
        if ($margin >= 20 && $daysCover !== null && $daysCover <= 60) {
            return ['grow', 'medium', 'Pozitif marj ve sağlıklı stok devri büyüme adaylığı gösteriyor.'];
        }

        return ['hold', 'low', 'Marj ve stok kapsamı dengeli; mevcut sermaye seviyesini izleyin.'];
    }

    private function decisionLabel(string $decision): string
    {
        return match ($decision) {
            'grow' => 'Sermaye artır',
            'protect' => 'Stoku koru',
            'reduce' => 'Sermaye azalt',
            'investigate' => 'Veriyi tamamla',
            default => 'Seviyeyi koru',
        };
    }

    private function priorityOrder(string $priority): int
    {
        return match ($priority) {
            'high' => 0,
            'medium' => 1,
            default => 2,
        };
    }

    /** @param array<string, mixed> $filters */
    private function periodDays(array $filters): int
    {
        if (filled($filters['date_from'] ?? null) && filled($filters['date_to'] ?? null)) {
            return max(1, Carbon::parse($filters['date_from'])->diffInDays(Carbon::parse($filters['date_to'])) + 1);
        }

        return 30;
    }
}
