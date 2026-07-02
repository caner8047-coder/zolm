<?php

namespace App\Livewire;

use App\Services\Marketplace\MarketplacePricingSimulationService;
use Livewire\Attributes\Computed;
use Livewire\Component;

class PublicTrendyolProfitCalculator extends Component
{
    public $salePrice = 1000;
    public $cogs = 400;
    public $packagingCost = 20;
    public $cargoCost = 60;
    public $commissionRate = 20;
    public $serviceFeeFixed = 0;
    public $serviceFeeRate = 0;
    public $advertisingRate = 0;
    public $returnRate = 0;
    public $returnCargoCost = 60;
    public $extraCostFixed = 0;

    public bool $vatEnabled = true;
    public $vatRate = 20;
    public $costVatRate = 20;
    public $expenseVatRate = 20;
    public bool $withholdingEnabled = true;
    public $withholdingRate = 1;
    public bool $microExport = false;
    public string $deliveryType = 'standard';

    public string $targetMode = 'margin';
    public $targetMarginPercent = 20;
    public $targetProfitAmount = 200;

    #[Computed]
    public function simulation(): array
    {
        return app(MarketplacePricingSimulationService::class)->simulate($this->simulationInput());
    }

    public function setTargetMode(string $mode): void
    {
        if (in_array($mode, ['margin', 'amount'], true)) {
            $this->targetMode = $mode;
        }
    }

    public function applyCommissionPreset(float $rate): void
    {
        if ($rate >= 0 && $rate <= 100) {
            $this->commissionRate = $rate;
        }
    }

    public function updatedMicroExport(bool $enabled): void
    {
        if ($enabled) {
            $this->withholdingEnabled = false;
        }
    }

    public function resetCalculator(): void
    {
        $this->reset();
        $this->salePrice = 1000;
        $this->cogs = 400;
        $this->packagingCost = 20;
        $this->cargoCost = 60;
        $this->commissionRate = 20;
        $this->returnCargoCost = 60;
        $this->vatEnabled = true;
        $this->vatRate = 20;
        $this->costVatRate = 20;
        $this->expenseVatRate = 20;
        $this->withholdingEnabled = true;
        $this->withholdingRate = 1;
        $this->deliveryType = 'standard';
        $this->targetMode = 'margin';
        $this->targetMarginPercent = 20;
        $this->targetProfitAmount = 200;
    }

    public function formatMoney(float|int|string|null $value): string
    {
        return number_format((float) $value, 2, ',', '.') . ' ₺';
    }

    public function render()
    {
        return view('livewire.public-trendyol-profit-calculator', [
            'simulation' => $this->simulation,
        ])->layout('layouts.public-tool', [
            'title' => 'Trendyol Kâr Hesaplama',
            'description' => 'Trendyol satış fiyatı, komisyon, kargo, KDV ve maliyet bilgileriyle net kârınızı hesaplayın.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function simulationInput(): array
    {
        return [
            'marketplace' => 'trendyol',
            'delivery_type' => $this->deliveryType,
            'sale_price' => $this->salePrice,
            'cogs' => $this->cogs,
            'packaging_cost' => $this->packagingCost,
            'cargo_cost' => $this->cargoCost,
            'return_cargo_cost' => $this->returnCargoCost,
            'service_fee_fixed' => $this->serviceFeeFixed,
            'extra_cost_fixed' => $this->extraCostFixed,
            'commission_rate' => $this->commissionRate,
            'service_fee_rate' => $this->serviceFeeRate,
            'advertising_rate' => $this->advertisingRate,
            'return_rate' => $this->returnRate,
            'vat_rate' => $this->vatRate,
            'cost_vat_rate' => $this->costVatRate,
            'expense_vat_rate' => $this->expenseVatRate,
            'withholding_rate' => $this->withholdingRate,
            'vat_enabled' => $this->vatEnabled,
            'withholding_enabled' => $this->withholdingEnabled,
            'micro_export' => $this->microExport,
            'target_mode' => $this->targetMode,
            'target_profit_amount' => $this->targetProfitAmount,
            'target_margin_percent' => $this->targetMarginPercent,
        ];
    }
}
