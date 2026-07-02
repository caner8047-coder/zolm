<?php

namespace App\Services\Marketplace;

use App\Models\ChannelListing;
use App\Models\MpProduct;
use App\Models\TrendyolBoosterProduct;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TrendyolBoosterAnalysisService
{
    public function __construct(
        protected MarketplacePricingSimulationService $pricingSimulationService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function preview(array $input): array
    {
        $normalized = $this->normalizeInput($input);
        $simulation = $this->pricingSimulationService->simulate($normalized['simulation_input']);
        $score = $this->score($simulation, $normalized);
        $decision = $this->decision($simulation, $score, $normalized);

        return [
            'normalized' => $normalized,
            'simulation' => $simulation,
            'score' => $score,
            'decision' => $decision,
            'reasons' => $this->reasons($simulation, $score, $normalized),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function store(int $userId, array $input): TrendyolBoosterProduct
    {
        $preview = $this->preview($input);
        $normalized = $preview['normalized'];
        $simulation = $preview['simulation'];

        return TrendyolBoosterProduct::query()->updateOrCreate(
            [
                'user_id' => $userId,
                'source_url_hash' => $normalized['source_url_hash'],
            ],
            [
                'mp_product_id' => $normalized['mp_product_id'],
                'channel_listing_id' => $normalized['channel_listing_id'],
                'source_url' => $normalized['source_url'],
                'trendyol_product_id' => $normalized['trendyol_product_id'],
                'title' => $normalized['title'],
                'brand' => $normalized['brand'],
                'category_name' => $normalized['category_name'],
                'sale_price' => $simulation['sale_price'],
                'currency' => 'TRY',
                'commission_rate' => $normalized['simulation_input']['commission_rate'],
                'cogs' => $normalized['simulation_input']['cogs'],
                'packaging_cost' => $normalized['simulation_input']['packaging_cost'],
                'cargo_cost' => $normalized['simulation_input']['cargo_cost'],
                'return_rate' => $normalized['simulation_input']['return_rate'],
                'vat_rate' => $normalized['simulation_input']['vat_rate'],
                'cost_vat_rate' => $normalized['simulation_input']['cost_vat_rate'],
                'net_profit' => $simulation['net_profit'],
                'profit_margin_percent' => $simulation['profit_margin_percent'],
                'break_even_price' => $simulation['break_even_price'],
                'target_price' => $simulation['target_price'],
                'opportunity_score' => $preview['score'],
                'decision_status' => $preview['decision'],
                'decision_reasons' => $preview['reasons'],
                'simulation_json' => $simulation,
                'watch_price' => (bool) ($input['watch_price'] ?? true),
                'watch_stock' => (bool) ($input['watch_stock'] ?? false),
                'watch_keyword' => (bool) ($input['watch_keyword'] ?? false),
                'last_checked_at' => now(),
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(
        int $userId,
        bool $favoritesOnly = false,
        string $search = '',
        string $category = 'all',
        string $sort = 'latest',
        string $trackingStatus = 'all',
    ): array
    {
        $base = TrendyolBoosterProduct::query()
            ->where('user_id', $userId)
            ->when(
                $trackingStatus !== '' && $trackingStatus !== 'all' && Schema::hasColumn('trendyol_booster_products', 'tracking_status'),
                fn (Builder $query) => $query->where('tracking_status', $trackingStatus),
            );
        $search = trim($search);
        $category = trim($category);
        $productsQuery = (clone $base)
            ->when($favoritesOnly, fn (Builder $query) => $query->where('is_favorite', true))
            ->when($category !== '' && $category !== 'all', fn (Builder $query) => $query->where('category_name', $category))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhere('category_name', 'like', "%{$search}%")
                        ->orWhere('trendyol_product_id', 'like', "%{$search}%");
                });
            });

        $filteredCount = (clone $productsQuery)->count();

        match ($sort) {
            'oldest' => $productsQuery->oldest('updated_at'),
            'category' => $productsQuery->orderBy('category_name')->orderBy('title'),
            'price_asc' => $productsQuery->orderBy('sale_price')->orderBy('title'),
            'price_desc' => $productsQuery->orderByDesc('sale_price')->orderBy('title'),
            'score_desc' => $productsQuery->orderByDesc('opportunity_score')->latest('updated_at'),
            'interest_desc' => $productsQuery->orderByDesc('interest_score')->latest('updated_at'),
            'risk_desc' => $productsQuery->orderByDesc('risk_score')->latest('updated_at'),
            'sales_desc' => $productsQuery->orderByDesc('estimated_daily_sales')->latest('updated_at'),
            'favorite_first' => $productsQuery->orderByDesc('is_favorite')->latest('updated_at'),
            default => $productsQuery->latest('updated_at'),
        };

        $productsQuery->limit(25);

        if (Schema::hasTable('trendyol_booster_snapshots')) {
            $productsQuery->with([
                'latestSnapshot',
                'analysisSnapshots' => fn ($query) => $query->latest('checked_at')->latest('id')->limit(2),
            ]);
        }

        if (Schema::hasTable('trendyol_booster_competitors')) {
            $productsQuery->with(['competitors' => fn ($query) => $query
                ->where('is_active', true)
                ->latest('last_checked_at')
                ->limit(3)]);
        }

        if (Schema::hasTable('trendyol_booster_keywords')) {
            $productsQuery->with(['keywords' => fn ($query) => $query
                ->where('is_active', true)
                ->latest('last_checked_at')
                ->limit(3)]);
        }

        if (Schema::hasTable('trendyol_booster_campaign_scenarios')) {
            $productsQuery->with(['campaignScenarios' => fn ($query) => $query
                ->latest()
                ->limit(3)]);
        }

        $products = $productsQuery->get();
        $analysisRefreshReady = Schema::hasColumn('trendyol_booster_products', 'analysis_auto_refresh_enabled');

        return [
            'total' => (clone $base)->count(),
            'watching_price' => (clone $base)->where('watch_price', true)->count(),
            'watching_stock' => (clone $base)->where('watch_stock', true)->count(),
            'favorite_count' => (clone $base)->where('is_favorite', true)->count(),
            'auto_refresh_count' => $analysisRefreshReady
                ? (clone $base)->where('analysis_auto_refresh_enabled', true)->count()
                : 0,
            'filtered_count' => $filteredCount,
            'strong_count' => (clone $base)->where('decision_status', 'go')->count(),
            'risk_count' => (clone $base)->whereIn('decision_status', ['risk', 'loss'])->count(),
            'average_score' => round((float) ((clone $base)->avg('opportunity_score') ?? 0), 1),
            'last_checked_at' => $products->max('last_checked_at'),
            'products' => $products,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function normalizeInput(array $input): array
    {
        $product = $this->product($input['mp_product_id'] ?? null, $input['user_id'] ?? null);
        $listing = $this->listing($input['channel_listing_id'] ?? null, $product);
        $sourceUrl = $this->normalizeUrl((string) ($input['source_url'] ?? ''));
        $salePrice = $this->money($input['sale_price'] ?? $listing?->sale_price ?? $product?->sale_price ?? 0);
        $cogs = $this->money($input['cogs'] ?? $product?->cogs ?? 0);
        $packagingCost = $this->money($input['packaging_cost'] ?? $product?->packaging_cost ?? 0);
        $cargoCost = $this->money($input['cargo_cost'] ?? $product?->cargo_cost ?? 0);
        $returnCargoCost = $this->money($input['return_cargo_cost'] ?? $product?->cargo_cost ?? 0);
        $commissionRate = $this->percent($input['commission_rate'] ?? $listing?->commission_rate ?? $product?->commission_rate ?? 0);
        $vatRate = $this->percent($input['vat_rate'] ?? $listing?->channelProduct?->vat_rate ?? $product?->vat_rate ?? 20);
        $costVatRate = $this->percent($input['cost_vat_rate'] ?? $product?->cost_vat_rate ?? $vatRate);

        return [
            'source_url' => $sourceUrl,
            'source_url_hash' => hash('sha256', $sourceUrl),
            'trendyol_product_id' => trim((string) ($input['trendyol_product_id'] ?? '')) ?: $this->extractTrendyolProductId($sourceUrl),
            'mp_product_id' => $product?->id,
            'channel_listing_id' => $listing?->id,
            'title' => trim((string) ($input['title'] ?? $product?->product_name ?? $listing?->channelProduct?->title ?? '')),
            'brand' => trim((string) ($input['brand'] ?? $product?->brand ?? $listing?->channelProduct?->brand ?? '')),
            'category_name' => trim((string) ($input['category_name'] ?? $product?->category_name ?? $listing?->channelProduct?->category_name ?? '')),
            'cost_ready' => $cogs > 0,
            'logistics_ready' => $cargoCost > 0,
            'simulation_input' => [
                'marketplace' => 'trendyol',
                'sale_price' => $salePrice,
                'cogs' => $cogs,
                'packaging_cost' => $packagingCost,
                'cargo_cost' => $cargoCost,
                'return_cargo_cost' => $returnCargoCost,
                'extra_cost_fixed' => $this->money($input['extra_cost_fixed'] ?? $product?->extra_cost_fixed ?? 0),
                'commission_rate' => $commissionRate,
                'service_fee_rate' => $this->percent($input['service_fee_rate'] ?? 0),
                'advertising_rate' => $this->percent($input['advertising_rate'] ?? 0),
                'return_rate' => $this->percent($input['return_rate'] ?? $product?->return_rate ?? 0),
                'vat_enabled' => (bool) ($input['vat_enabled'] ?? false),
                'withholding_enabled' => (bool) ($input['withholding_enabled'] ?? false),
                'vat_rate' => $vatRate,
                'cost_vat_rate' => $costVatRate,
                'expense_vat_rate' => $this->percent($input['expense_vat_rate'] ?? 20),
                'withholding_rate' => $this->percent($input['withholding_rate'] ?? 1),
                'target_mode' => 'margin',
                'target_margin_percent' => $this->percent($input['target_margin_percent'] ?? 20),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $simulation
     * @param  array<string, mixed>  $normalized
     */
    protected function score(array $simulation, array $normalized): int
    {
        $margin = (float) $simulation['profit_margin_percent'];
        $commissionRate = (float) $normalized['simulation_input']['commission_rate'];
        $returnRate = (float) $normalized['simulation_input']['return_rate'];
        $salePrice = (float) $simulation['sale_price'];
        $targetPrice = $simulation['target_price'] !== null ? (float) $simulation['target_price'] : null;

        $score = match (true) {
            $margin >= 30 => 35,
            $margin >= 20 => 28,
            $margin >= 12 => 20,
            $margin >= 5 => 10,
            $margin >= 0 => 5,
            default => 0,
        };

        $score += $normalized['cost_ready'] ? 15 : 0;
        $score += $normalized['logistics_ready'] ? 10 : 0;
        $score += match (true) {
            $commissionRate <= 10 => 15,
            $commissionRate <= 18 => 10,
            $commissionRate <= 25 => 5,
            default => 0,
        };
        $score += match (true) {
            $returnRate <= 5 => 10,
            $returnRate <= 12 => 6,
            $returnRate <= 20 => 2,
            default => 0,
        };

        if ($targetPrice !== null && $salePrice > 0) {
            $gapRate = ($targetPrice - $salePrice) / $salePrice;
            $score += match (true) {
                $gapRate <= 0 => 15,
                $gapRate <= 0.05 => 10,
                $gapRate <= 0.12 => 5,
                default => 0,
            };
        }

        if ((float) $simulation['net_profit'] < 0) {
            $score = min($score, 30);
        }

        if (! $normalized['cost_ready']) {
            $score = min($score, 35);
        } elseif (! $normalized['logistics_ready']) {
            $score = min($score, 55);
        }

        return max(0, min(100, $score));
    }

    /**
     * @param  array<string, mixed>  $simulation
     * @param  array<string, mixed>  $normalized
     */
    protected function decision(array $simulation, int $score, array $normalized): string
    {
        if ((float) $simulation['net_profit'] < 0) {
            return 'loss';
        }

        if (! $normalized['cost_ready']) {
            return 'risk';
        }

        return match (true) {
            $score >= 75 => 'go',
            $score >= 55 => 'watch',
            $score >= 35 => 'risk',
            default => 'loss',
        };
    }

    /**
     * @param  array<string, mixed>  $simulation
     * @param  array<string, mixed>  $normalized
     * @return array<int, string>
     */
    protected function reasons(array $simulation, int $score, array $normalized): array
    {
        $reasons = [];
        $margin = (float) $simulation['profit_margin_percent'];

        if (! $normalized['cost_ready']) {
            $reasons[] = 'Ürün maliyeti eksik olduğu için kâr ve marj güvenilir kabul edilmedi.';
        } elseif ($margin >= 20) {
            $reasons[] = 'Hedef marj eşiğine yakın veya üzerinde.';
        } elseif ($margin >= 0) {
            $reasons[] = 'Kâr pozitif ancak marj dikkat istiyor.';
        } else {
            $reasons[] = 'Bu fiyat ürün başına zarar üretiyor.';
        }

        if (! $normalized['logistics_ready']) {
            $reasons[] = 'Kargo/desi maliyeti netleşmediği için lojistik riski var.';
        }

        if ((float) $normalized['simulation_input']['commission_rate'] > 25) {
            $reasons[] = 'Komisyon oranı yüksek bantta.';
        }

        if ($score >= 75) {
            $reasons[] = 'Fiyat, maliyet ve hedef fiyat dengesi güçlü.';
        }

        return array_values(array_unique($reasons));
    }

    protected function product(mixed $productId, mixed $userId): ?MpProduct
    {
        $id = (int) $productId;

        if ($id <= 0 || ! $userId) {
            return null;
        }

        return MpProduct::query()
            ->where('user_id', (int) $userId)
            ->find($id);
    }

    protected function listing(mixed $listingId, ?MpProduct $product): ?ChannelListing
    {
        $id = (int) $listingId;

        if ($id > 0) {
            return ChannelListing::query()
                ->with(['store', 'channelProduct'])
                ->where('mp_product_id', $product?->id)
                ->find($id);
        }

        if (! $product) {
            return null;
        }

        return $product->channelListings()
            ->with(['store', 'channelProduct'])
            ->whereHas('store', fn ($query) => $query->where('marketplace', 'trendyol'))
            ->latest('last_synced_at')
            ->first();
    }

    protected function normalizeUrl(string $url): string
    {
        $url = trim($url);
        $url = preg_replace('/\s+/', '', $url) ?: '';

        return Str::limit($url, 1000, '');
    }

    protected function extractTrendyolProductId(string $url): ?string
    {
        if (preg_match('/-p-(\d+)/', $url, $matches)) {
            return $matches[1];
        }

        return null;
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
