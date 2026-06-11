<?php

namespace App\Models;

use App\Services\ProfitabilityMetric;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OptimizationReportItem extends Model
{
    protected $fillable = [
        'report_id',
        'stock_code',
        'barcode',
        'product_name',
        'current_price',
        'current_commission',
        'current_net_profit',
        'suggested_tariff',
        'suggested_price',
        'suggested_commission',
        'suggested_net_profit',
        'extra_profit',
        'production_cost',
        'shipping_cost',
        'action',
        'is_selected',
        'scenario_details',
        'campaign_data',
        'selected_tariff_index',
        'custom_price',
    ];

    protected $casts = [
        'current_price' => 'decimal:2',
        'current_commission' => 'decimal:2',
        'current_net_profit' => 'decimal:2',
        'suggested_price' => 'decimal:2',
        'suggested_commission' => 'decimal:2',
        'suggested_net_profit' => 'decimal:2',
        'extra_profit' => 'decimal:2',
        'production_cost' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'is_selected' => 'boolean',
        'scenario_details' => 'array',
        'campaign_data' => 'array',
        'selected_tariff_index' => 'integer',
        'custom_price' => 'decimal:2',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(OptimizationReport::class, 'report_id');
    }

    /**
     * Fırsat var mı?
     */
    public function isOpportunity(): bool
    {
        return $this->action === 'update' && $this->extra_profit > 0;
    }

    /**
     * Toplam maliyet
     */
    public function totalCost(): float
    {
        $scenarioTotalCost = data_get($this->scenario_details, '0.total_cost');

        if (is_numeric($scenarioTotalCost) && (float) $scenarioTotalCost > 0) {
            return (float) $scenarioTotalCost;
        }

        return (float) $this->production_cost + (float) $this->shipping_cost;
    }

    public function productCostForProfitability(): float
    {
        $packagingCost = data_get($this->campaign_data, 'packaging_cost');

        if (is_numeric($packagingCost)) {
            return ProfitabilityMetric::productCost((float) $this->production_cost, (float) $packagingCost);
        }

        $scenarioProductCost = data_get($this->scenario_details, '0.product_cost');

        if (is_numeric($scenarioProductCost) && (float) $scenarioProductCost > 0) {
            return (float) $scenarioProductCost;
        }

        $productionCost = (float) $this->production_cost;

        return $productionCost > 0 ? $productionCost : $this->totalCost();
    }

    /**
     * Geriye uyumluluk için çarpan döner; ekranda (çarpan - 1) * 100 gösterilir.
     */
    public function profitMarginPercent(?float $netProfit = null): float
    {
        $profit = $netProfit ?? (float) $this->current_net_profit;
        return ProfitabilityMetric::multiplierOrZero($profit, $this->productCostForProfitability());
    }
}
