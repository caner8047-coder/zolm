<?php

namespace App\Services\Marketplace;

use App\Models\TrendyolBoosterCampaignScenario;
use App\Models\TrendyolBoosterProduct;
use Illuminate\Support\Str;

class TrendyolBoosterCampaignScenarioService
{
    public function __construct(
        protected MarketplacePricingSimulationService $pricingSimulationService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function simulateAndStore(TrendyolBoosterProduct $tracked, array $input): TrendyolBoosterCampaignScenario
    {
        $storedInput = (array) data_get($tracked->simulation_json, 'input', []);
        $discountRate = $this->percent($input['discount_rate'] ?? 0);
        $campaignPrice = $this->money($input['campaign_price'] ?? 0);

        if ($campaignPrice <= 0) {
            $campaignPrice = round((float) $tracked->sale_price * (1 - ($discountRate / 100)), 2);
        }

        $commissionDiscountRate = $this->percent($input['commission_discount_rate'] ?? 0);
        $advertisingRate = $this->percent($input['advertising_rate'] ?? ($storedInput['advertising_rate'] ?? 0));
        $expectedUnits = max(1, min(1000000, (int) ($input['expected_units'] ?? 1)));
        $currentInput = $this->baseSimulationInput($tracked, $storedInput, (float) $tracked->sale_price);
        $campaignInput = array_merge($currentInput, [
            'sale_price' => $campaignPrice,
            'commission_rate' => max(0, (float) $tracked->commission_rate - $commissionDiscountRate),
            'advertising_rate' => $advertisingRate,
        ]);

        $currentSimulation = $this->pricingSimulationService->simulate($currentInput);
        $campaignSimulation = $this->pricingSimulationService->simulate($campaignInput);
        $profitDelta = round((float) $campaignSimulation['net_profit'] - (float) $currentSimulation['net_profit'], 2);
        $totalDelta = round($profitDelta * $expectedUnits, 2);
        $decision = $this->decision($campaignSimulation, $profitDelta, $totalDelta);

        return TrendyolBoosterCampaignScenario::query()->create([
            'trendyol_booster_product_id' => $tracked->id,
            'user_id' => $tracked->user_id,
            'name' => $this->name($input['name'] ?? ''),
            'campaign_type' => (string) ($input['campaign_type'] ?? 'discount'),
            'discount_rate' => $discountRate,
            'campaign_price' => $campaignPrice,
            'commission_discount_rate' => $commissionDiscountRate,
            'advertising_rate' => $advertisingRate,
            'expected_units' => $expectedUnits,
            'current_net_profit' => (float) $currentSimulation['net_profit'],
            'campaign_net_profit' => (float) $campaignSimulation['net_profit'],
            'profit_delta_per_unit' => $profitDelta,
            'total_profit_delta' => $totalDelta,
            'campaign_margin_percent' => (float) $campaignSimulation['profit_margin_percent'],
            'decision_status' => $decision['status'],
            'decision_note' => $decision['note'],
            'simulation_json' => [
                'current' => $currentSimulation,
                'campaign' => $campaignSimulation,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $storedInput
     * @return array<string, mixed>
     */
    protected function baseSimulationInput(TrendyolBoosterProduct $tracked, array $storedInput, float $salePrice): array
    {
        return array_merge($storedInput, [
            'marketplace' => 'trendyol',
            'sale_price' => $salePrice,
            'cogs' => (float) $tracked->cogs,
            'packaging_cost' => (float) $tracked->packaging_cost,
            'cargo_cost' => (float) $tracked->cargo_cost,
            'return_cargo_cost' => (float) ($storedInput['return_cargo_cost'] ?? $tracked->cargo_cost),
            'commission_rate' => (float) $tracked->commission_rate,
            'service_fee_rate' => (float) ($storedInput['service_fee_rate'] ?? 0),
            'advertising_rate' => (float) ($storedInput['advertising_rate'] ?? 0),
            'return_rate' => (float) $tracked->return_rate,
            'vat_rate' => (float) $tracked->vat_rate,
            'cost_vat_rate' => (float) $tracked->cost_vat_rate,
            'expense_vat_rate' => (float) ($storedInput['expense_vat_rate'] ?? 20),
            'withholding_rate' => (float) ($storedInput['withholding_rate'] ?? 1),
            'target_mode' => 'margin',
            'target_margin_percent' => (float) ($storedInput['target_margin_percent'] ?? 20),
        ]);
    }

    /**
     * @param  array<string, mixed>  $campaignSimulation
     * @return array{status: string, note: string}
     */
    protected function decision(array $campaignSimulation, float $profitDelta, float $totalDelta): array
    {
        $campaignProfit = (float) $campaignSimulation['net_profit'];
        $margin = (float) $campaignSimulation['profit_margin_percent'];

        if ($campaignProfit < 0) {
            return [
                'status' => 'reject',
                'note' => 'Kampanya fiyatı ürün başına zarar üretiyor.',
            ];
        }

        if ($profitDelta >= 0 && $margin >= 12) {
            return [
                'status' => 'approve',
                'note' => 'Kampanya kârı koruyor ve marj sağlıklı.',
            ];
        }

        if ($totalDelta >= 0 && $margin >= 8) {
            return [
                'status' => 'watch',
                'note' => 'Birim kâr sınırlı; hacim varsayımıyla izlenebilir.',
            ];
        }

        return [
            'status' => 'risk',
            'note' => 'Kampanya marjı zayıf; fiyat veya komisyon desteği gerekiyor.',
        ];
    }

    protected function name(mixed $value): string
    {
        $name = html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $name = preg_replace('/\s+/u', ' ', $name) ?: '';
        $name = trim(Str::limit($name, 160, ''));

        return $name !== '' ? $name : 'Trendyol Kampanya';
    }

    protected function money(mixed $value): float
    {
        return round(max(0, (float) str_replace(',', '.', (string) ($value ?? 0))), 2);
    }

    protected function percent(mixed $value): float
    {
        return round(max(0, min(100, (float) str_replace(',', '.', (string) ($value ?? 0)))), 2);
    }
}
