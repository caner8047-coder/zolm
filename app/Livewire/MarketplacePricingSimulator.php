<?php

namespace App\Livewire;

use App\Models\ChannelListing;
use App\Models\MarketplacePricingScenario;
use App\Models\MpProduct;
use App\Services\Marketplace\MarketplacePricingSimulationService;
use App\Services\MpSettingsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class MarketplacePricingSimulator extends Component
{
    #[Url(as: 'product', history: true)]
    public ?int $selectedProductId = null;

    public ?int $selectedListingId = null;
    public string $productSearch = '';
    public string $scenarioName = '';
    public string $marketplace = 'trendyol';
    public string $currency = 'TRY';

    public $salePrice = 0;
    public $cogs = 0;
    public $packagingCost = 0;
    public $cargoCost = 0;
    public $returnCargoCost = 0;
    public $serviceFeeFixed = 0;
    public $extraCostFixed = 0;
    public $commissionRate = 0;
    public $serviceFeeRate = 0;
    public $advertisingRate = 0;
    public $returnRate = 0;
    public $vatRate = 10;
    public $costVatRate = 20;
    public $expenseVatRate = 20;
    public $withholdingRate = 1;
    public bool $vatEnabled = false;
    public bool $withholdingEnabled = false;
    public bool $microExport = false;
    public string $targetMode = 'margin';
    public $targetProfitAmount = 0;
    public $targetMarginPercent = 20;

    public ?int $activeScenarioId = null;
    public string $message = '';
    public string $messageType = 'success';

    public function mount(): void
    {
        $settings = new MpSettingsService($this->userId());
        $this->vatEnabled = $settings->isKdvEnabled();
        $this->withholdingEnabled = $settings->isEstimatedWithholdingEnabled();
        $this->withholdingRate = $this->percentValue($settings->getStopajRate());
        $this->vatRate = $this->percentValue($settings->getDefaultProductVatRate());
        $this->costVatRate = $this->vatRate;
        $this->expenseVatRate = $this->percentValue($settings->getExpenseVatRate());

        if ($this->selectedProductId) {
            $this->loadProduct($this->selectedProductId);
        }
    }

    #[Computed]
    public function productOptions(): Collection
    {
        return MpProduct::query()
            ->where('user_id', $this->userId())
            ->when(trim($this->productSearch) !== '', function (Builder $query): void {
                $search = trim($this->productSearch);
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('product_name', 'like', "%{$search}%")
                        ->orWhere('stock_code', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%");
                });
            })
            ->orderBy('product_name')
            ->limit(50)
            ->get([
                'id',
                'product_name',
                'stock_code',
                'barcode',
                'sale_price',
                'cogs',
                'packaging_cost',
                'cargo_cost',
                'commission_rate',
                'vat_rate',
                'cost_vat_rate',
            ]);
    }

    #[Computed]
    public function listingOptions(): Collection
    {
        if (! $this->selectedProductId) {
            return collect();
        }

        return ChannelListing::query()
            ->with('store:id,marketplace,store_name,currency')
            ->where('mp_product_id', $this->selectedProductId)
            ->whereHas('product', fn (Builder $query) => $query->where('user_id', $this->userId()))
            ->orderByDesc('last_synced_at')
            ->get();
    }

    #[Computed]
    public function selectedProduct(): ?MpProduct
    {
        if (! $this->selectedProductId) {
            return null;
        }

        return MpProduct::query()
            ->where('user_id', $this->userId())
            ->find($this->selectedProductId);
    }

    #[Computed]
    public function simulation(): array
    {
        return app(MarketplacePricingSimulationService::class)->simulate($this->simulationInput());
    }

    #[Computed]
    public function savedScenarios(): Collection
    {
        return MarketplacePricingScenario::query()
            ->with([
                'product:id,product_name,stock_code',
                'listing.store:id,store_name,marketplace',
            ])
            ->where('user_id', $this->userId())
            ->latest()
            ->limit(12)
            ->get();
    }

    public function updatedSelectedProductId(mixed $value): void
    {
        $this->activeScenarioId = null;
        $this->loadProduct((int) $value);
    }

    public function updatedSelectedListingId(mixed $value): void
    {
        $listingId = (int) $value;

        if ($listingId <= 0) {
            return;
        }

        $listing = $this->listingQuery()->find($listingId);

        if ($listing) {
            $this->applyListing($listing);
        }
    }

    public function updatedMicroExport(bool $enabled): void
    {
        if ($enabled) {
            $this->withholdingEnabled = false;
        }
    }

    public function setTargetMode(string $mode): void
    {
        if (in_array($mode, ['amount', 'margin'], true)) {
            $this->targetMode = $mode;
        }
    }

    public function saveScenario(): void
    {
        $validated = $this->validate([
            'scenarioName' => ['required', 'string', 'max:160'],
            'selectedProductId' => ['nullable', 'integer'],
            'selectedListingId' => ['nullable', 'integer'],
            'salePrice' => ['required', 'numeric', 'min:0'],
            'cogs' => ['required', 'numeric', 'min:0'],
            'packagingCost' => ['required', 'numeric', 'min:0'],
            'cargoCost' => ['required', 'numeric', 'min:0'],
            'commissionRate' => ['required', 'numeric', 'min:0', 'max:100'],
            'targetMarginPercent' => ['required', 'numeric', 'min:0', 'max:100'],
            'targetProfitAmount' => ['required', 'numeric', 'min:0'],
        ]);

        $product = $this->selectedProductId
            ? MpProduct::query()->where('user_id', $this->userId())->findOrFail($this->selectedProductId)
            : null;
        $listing = $this->selectedListingId
            ? $this->listingQuery()->findOrFail($this->selectedListingId)
            : null;
        $result = $this->simulation;

        $scenario = MarketplacePricingScenario::query()->updateOrCreate(
            [
                'id' => $this->activeScenarioId,
                'user_id' => $this->userId(),
            ],
            [
                'mp_product_id' => $product?->id,
                'channel_listing_id' => $listing?->id,
                'name' => $validated['scenarioName'],
                'marketplace' => $this->marketplace,
                'currency' => $this->currency,
                'input_json' => $this->simulationInput(),
                'result_json' => $result,
                'status' => 'draft',
                'created_by' => $this->userId(),
            ]
        );

        $this->activeScenarioId = $scenario->id;
        $this->message = 'Fiyatlandırma senaryosu kaydedildi.';
        $this->messageType = 'success';
        unset($this->savedScenarios);
    }

    public function loadScenario(int $id): void
    {
        $scenario = MarketplacePricingScenario::query()
            ->where('user_id', $this->userId())
            ->findOrFail($id);
        $input = (array) $scenario->input_json;

        $this->activeScenarioId = $scenario->id;
        $this->scenarioName = $scenario->name;
        $this->selectedProductId = $scenario->mp_product_id;
        $this->selectedListingId = $scenario->channel_listing_id;
        $this->marketplace = $scenario->marketplace;
        $this->currency = $scenario->currency;
        $this->fillFromInput($input);
        $this->message = 'Senaryo çalışma yüzeyine yüklendi.';
        $this->messageType = 'success';
    }

    public function deleteScenario(int $id): void
    {
        MarketplacePricingScenario::query()
            ->where('user_id', $this->userId())
            ->whereKey($id)
            ->delete();

        if ($this->activeScenarioId === $id) {
            $this->activeScenarioId = null;
        }

        $this->message = 'Senaryo silindi.';
        $this->messageType = 'success';
        unset($this->savedScenarios);
    }

    public function resetSimulator(): void
    {
        $this->reset([
            'selectedProductId',
            'selectedListingId',
            'productSearch',
            'scenarioName',
            'salePrice',
            'cogs',
            'packagingCost',
            'cargoCost',
            'returnCargoCost',
            'serviceFeeFixed',
            'extraCostFixed',
            'commissionRate',
            'serviceFeeRate',
            'advertisingRate',
            'returnRate',
            'activeScenarioId',
            'message',
        ]);
        $this->marketplace = 'trendyol';
        $this->currency = 'TRY';
        $this->targetMode = 'margin';
        $this->targetProfitAmount = 0;
        $this->targetMarginPercent = 20;
    }

    public function productUrl(): string
    {
        if (! $this->selectedProductId) {
            return route('mp.products');
        }

        return route('mp.products', [
            'edit' => $this->selectedProductId,
            'tab' => 'pricing',
        ]);
    }

    public function formatMoney(float|int|string|null $value): string
    {
        return number_format((float) $value, 2, ',', '.') . ' ₺';
    }

    public function render()
    {
        return view('livewire.marketplace-pricing-simulator', [
            'productOptions' => $this->productOptions,
            'listingOptions' => $this->listingOptions,
            'selectedProduct' => $this->selectedProduct,
            'simulation' => $this->simulation,
            'savedScenarios' => $this->savedScenarios,
        ])->layout('layouts.app', ['title' => 'Ürün Fiyatlandırma Simülatörü']);
    }

    protected function loadProduct(int $productId): void
    {
        if ($productId <= 0) {
            $this->selectedProductId = null;
            $this->selectedListingId = null;

            return;
        }

        $product = MpProduct::query()
            ->with(['channelListings.store'])
            ->where('user_id', $this->userId())
            ->findOrFail($productId);

        $this->selectedProductId = $product->id;
        $this->cogs = (float) $product->cogs;
        $this->packagingCost = (float) $product->packaging_cost;
        $this->cargoCost = (float) $product->cargo_cost;
        $this->extraCostFixed = (float) $product->extra_cost_fixed;
        $this->returnRate = (float) $product->return_rate;
        $this->vatRate = (float) ($product->vat_rate ?? $this->vatRate);
        $this->costVatRate = (float) ($product->cost_vat_rate ?? $product->vat_rate ?? $this->costVatRate);
        $this->scenarioName = $product->product_name . ' · Hedef fiyat';

        $listing = $product->channelListings
            ->sortByDesc(fn (ChannelListing $item) => $item->last_synced_at?->timestamp ?? 0)
            ->first();

        if ($listing) {
            $this->selectedListingId = $listing->id;
            $this->applyListing($listing);
        } else {
            $this->selectedListingId = null;
            $this->salePrice = (float) $product->sale_price;
            $this->commissionRate = (float) $product->commission_rate;
        }
    }

    protected function applyListing(ChannelListing $listing): void
    {
        $listing->loadMissing('store');
        $this->selectedListingId = $listing->id;
        $this->marketplace = strtolower((string) ($listing->store?->marketplace ?: $this->marketplace));
        $this->currency = (string) ($listing->currency ?: $listing->store?->currency ?: 'TRY');
        $this->salePrice = (float) (($listing->sale_price ?? 0) > 0
            ? $listing->sale_price
            : ($this->selectedProduct?->sale_price ?? 0));
        $this->commissionRate = $this->commissionRateFor(
            $this->marketplace,
            (float) ($listing->commission_rate ?? 0),
            (float) ($this->selectedProduct?->commission_rate ?? 0),
        );
    }

    protected function commissionRateFor(string $marketplace, float $listingRate, float $productRate): float
    {
        $settings = new MpSettingsService($this->userId());

        return match (strtolower($marketplace)) {
            'koctas' => $settings->getProductProfitKoctasCommissionRate(),
            'woocommerce' => $settings->getProductProfitWooCommerceCommissionRate(),
            default => $listingRate > 0 ? $listingRate : $productRate,
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function simulationInput(): array
    {
        return [
            'marketplace' => $this->marketplace,
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

    /**
     * @param  array<string, mixed>  $input
     */
    protected function fillFromInput(array $input): void
    {
        $map = [
            'sale_price' => 'salePrice',
            'cogs' => 'cogs',
            'packaging_cost' => 'packagingCost',
            'cargo_cost' => 'cargoCost',
            'return_cargo_cost' => 'returnCargoCost',
            'service_fee_fixed' => 'serviceFeeFixed',
            'extra_cost_fixed' => 'extraCostFixed',
            'commission_rate' => 'commissionRate',
            'service_fee_rate' => 'serviceFeeRate',
            'advertising_rate' => 'advertisingRate',
            'return_rate' => 'returnRate',
            'vat_rate' => 'vatRate',
            'cost_vat_rate' => 'costVatRate',
            'expense_vat_rate' => 'expenseVatRate',
            'withholding_rate' => 'withholdingRate',
            'vat_enabled' => 'vatEnabled',
            'withholding_enabled' => 'withholdingEnabled',
            'micro_export' => 'microExport',
            'target_mode' => 'targetMode',
            'target_profit_amount' => 'targetProfitAmount',
            'target_margin_percent' => 'targetMarginPercent',
        ];

        foreach ($map as $key => $property) {
            if (array_key_exists($key, $input)) {
                $this->{$property} = $input[$key];
            }
        }
    }

    protected function listingQuery(): Builder
    {
        return ChannelListing::query()
            ->with('store')
            ->whereHas('product', fn (Builder $query) => $query->where('user_id', $this->userId()));
    }

    protected function percentValue(float $rate): float
    {
        return round($rate < 1 ? $rate * 100 : $rate, 2);
    }

    protected function userId(): int
    {
        return (int) auth()->id();
    }
}
