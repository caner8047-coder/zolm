<?php

namespace App\Livewire;

use App\Models\MpProduct;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\MarketplaceCampaignSimulator as SimulatorService;
use Livewire\Component;
use Livewire\Attributes\Layout;

#[Layout('layouts.app')]
class MarketplaceCampaignSimulator extends Component
{
    public $simulationMode = 'single'; // 'single' or 'portfolio'
    
    // Single Product Mode
    public $searchQuery = '';
    public $selectedProductId = null;
    public $targetPrice = 0;
    
    // Portfolio Mode
    public $portfolioPriceChangePercent = 10; // e.g. 10%
    public $portfolioCategory = '';
    
    // Shared Scenario options
    public $commissionDiscount = 0; // percent discount on commission
    public $customCargoCost = 0;

    public $results = [];
    public $portfolioResults = [];

    public function updatedSearchQuery()
    {
        $this->selectedProductId = null;
        $this->results = [];
    }
    
    public function updatedSimulationMode()
    {
        $this->results = [];
        $this->portfolioResults = [];
        if ($this->simulationMode === 'portfolio') {
            $this->runPortfolioSimulation();
        }
    }

    public function selectProduct($id)
    {
        $this->selectedProductId = $id;
        $product = MpProduct::find($id);
        if ($product) {
            $this->targetPrice = $product->sale_price > 0 ? $product->sale_price : 100;
            $this->searchQuery = $product->product_name;
            $this->runSimulation();
        }
    }

    public function runSimulation()
    {
        if (!$this->selectedProductId || $this->targetPrice <= 0) {
            return;
        }

        $product = MpProduct::find($this->selectedProductId);
        if (!$product) {
            return;
        }

        $simulator = app(SimulatorService::class);
        $this->results = [];

        // Fetch active stores
        $stores = MarketplaceStore::where('is_active', true)->get();
        $marketplaces = $stores->pluck('marketplace')->unique();

        // If no active stores, mock a few for demonstration
        if ($marketplaces->isEmpty()) {
            $marketplaces = collect(['trendyol', 'hepsiburada', 'amazon']);
        }

        foreach ($marketplaces as $mp) {
            $baseCommission = $product->commission_rate ?? 15;
            $effectiveCommission = max(0, $baseCommission - (float)$this->commissionDiscount);
            
            $options = [
                'commission_rate' => $effectiveCommission,
                'cargo_cost' => (float)$this->customCargoCost > 0 ? (float)$this->customCargoCost : 55.0, // base mock cargo
                'vat_rate' => 20, // default
            ];

            $this->results[] = $simulator->simulate($product, (float)$this->targetPrice, $mp, $options);
        }
    }

    public function runPortfolioSimulation()
    {
        $simulator = app(SimulatorService::class);
        $this->portfolioResults = [];

        // Fetch products for portfolio (mocking the active sample of 100 products)
        $query = MpProduct::query()->where('is_active', true);
        if (!empty($this->portfolioCategory)) {
            $query->where('category', $this->portfolioCategory);
        }
        $products = $query->limit(100)->get();

        if ($products->isEmpty()) {
            return;
        }

        // Fetch active stores
        $stores = MarketplaceStore::where('is_active', true)->get();
        $marketplaces = $stores->pluck('marketplace')->unique();
        if ($marketplaces->isEmpty()) {
            $marketplaces = collect(['trendyol', 'hepsiburada', 'amazon']);
        }

        foreach ($marketplaces as $mp) {
            $options = [
                'cargo_cost' => (float)$this->customCargoCost > 0 ? (float)$this->customCargoCost : 55.0,
                'vat_rate' => 20,
            ];

            // If a commission discount is applied globally, we subtract it from product's base commission inside simulatePortfolio
            // But we need to pass it differently or let simulatePortfolio handle it.
            // For simplicity, we just pass the discount and apply it per product inside the loop if needed.
            // Actually, we pass `commission_discount` in options, then modify simulatePortfolio to use it if we want.
            // But simulatePortfolio expects exact `commission_rate`. Let's just use the product's base commission minus discount in `simulatePortfolio`.
            $options['commission_discount'] = (float)$this->commissionDiscount;

            $this->portfolioResults[] = $simulator->simulatePortfolio($products, (float)$this->portfolioPriceChangePercent, $mp, $options);
        }
    }

    public function getProductsProperty()
    {
        if (strlen($this->searchQuery) < 2 || $this->selectedProductId) {
            return [];
        }

        return MpProduct::query()
            ->where('product_name', 'like', '%' . $this->searchQuery . '%')
            ->orWhere('barcode', 'like', '%' . $this->searchQuery . '%')
            ->orWhere('stock_code', 'like', '%' . $this->searchQuery . '%')
            ->limit(5)
            ->get();
    }

    public function render()
    {
        return view('livewire.marketplace-campaign-simulator', [
            'products' => $this->products,
        ]);
    }
}
