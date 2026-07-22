<?php

namespace App\Services\Marketplace;

use App\Models\CargoInvoiceLine;
use App\Models\ChannelListing;
use App\Models\MarketplaceStore;
use App\Models\MpBuyboxListing;
use App\Models\MpPriceRecommendation;
use App\Models\MpProduct;
use Illuminate\Support\Carbon;

class MarketplaceBuyboxRecommendationService
{
    public function __construct(
        protected MarketplacePricingSimulationService $simulationService,
        protected MarketplacePricePolicyService $policyService,
    ) {
    }

    /**
     * Generate or update recommendation for a single Buybox listing.
     */
    public function generateForListing(MpBuyboxListing $listing): MpPriceRecommendation
    {
        $listing->loadMissing(['store']);
        $store = $listing->store;

        $policy = $this->policyService->getPolicy($store);

        // Find associated product / listing to get costs and commission
        $channelListing = ChannelListing::where('store_id', $store->id)
            ->whereHas('channelProduct', fn ($q) => $q->where('barcode', $listing->barcode))
            ->with(['channelProduct', 'product'])
            ->first();

        $mpProduct = MpProduct::where('user_id', $store->user_id)
            ->where('barcode', $listing->barcode)
            ->first();

        // 1. Resolve Cost Components
        $cogs = (float) ($channelListing?->product?->purchase_price
            ?? $mpProduct?->cogs
            ?? 0);

        $commissionRate = (float) ($channelListing?->commission_rate
            ?? $mpProduct?->commission_rate
            ?? 15.0);

        // Resolve Cargo Cost: Real cargo invoice line if exists, else estimate
        $realCargo = CargoInvoiceLine::where('store_id', $store->id)
            ->where('barcode', $listing->barcode)
            ->orderByDesc('invoice_date')
            ->value('total_amount');

        $cargoCost = $realCargo !== null ? (float) $realCargo : 35.00; // default estimated cargo cost

        $packagingCost = 5.00;
        $vatRate = 20.0;

        $currentPrice = (float) ($listing->seller_price ?? $channelListing?->sale_price ?? 0);
        $buyboxPrice = $listing->buybox_price !== null ? (float) $listing->buybox_price : null;
        $secondPrice = $listing->second_price !== null ? (float) $listing->second_price : null;
        $thirdPrice = $listing->third_price !== null ? (float) $listing->third_price : null;

        // 2. Minimum Safe Price Calculation using Pricing Simulation Service
        $minProfitAmount = (float) $policy['min_profit_amount'];
        $minProfitMargin = (float) $policy['min_profit_margin'];
        $returnReservePct = (float) $policy['return_reserve_percent'];

        $simBase = [
            'marketplace' => 'trendyol',
            'cogs' => $cogs,
            'packaging_cost' => $packagingCost,
            'cargo_cost' => $cargoCost,
            'commission_rate' => $commissionRate,
            'return_rate' => $returnReservePct,
            'vat_rate' => $vatRate,
            'vat_enabled' => true,
            'target_mode' => 'margin',
            'target_margin_percent' => $minProfitMargin,
            'target_profit_amount' => $minProfitAmount,
        ];

        $minSafePriceByMargin = $this->calculateMinSafePriceForMargin($simBase, $minProfitMargin);
        $minSafePriceByAmount = $this->calculateMinSafePriceForAmount($simBase, $minProfitAmount);
        $minimumSafePrice = max($minSafePriceByMargin, $minSafePriceByAmount);

        // Maximum allowed price (e.g. current + max_single_raise_percent)
        $maxRaisePct = (float) $policy['max_single_raise_percent'];
        $maximumAllowedPrice = $currentPrice > 0 ? round($currentPrice * (1 + $maxRaisePct / 100), 2) : null;

        // Current profit simulation
        $currentSim = $this->simulationService->simulate($simBase + ['sale_price' => $currentPrice]);
        $currentProfit = (float) ($currentSim['net_profit'] ?? 0);
        $currentProfitMargin = (float) ($currentSim['profit_margin_percent'] ?? 0);

        // 3. Evaluation & Recommendation Type Determination
        $recommendationType = 'KEEP_PRICE';
        $riskLevel = 'low';
        $reasonCodes = [];
        $recommendedPrice = $currentPrice;

        $staleThreshold = now()->subMinutes((int) $policy['stale_threshold_minutes']);
        $isStale = $listing->retrieved_at && Carbon::parse($listing->retrieved_at)->lt($staleThreshold);

        if ($cogs <= 0) {
            $recommendationType = 'MISSING_COST';
            $riskLevel = 'blocked';
            $reasonCodes[] = 'COGS_NOT_SET';
            $recommendedPrice = null;
        } elseif ($buyboxPrice === null) {
            $recommendationType = 'MANUAL_REVIEW_REQUIRED';
            $riskLevel = 'medium';
            $reasonCodes[] = 'NO_BUYBOX_PRICE';
            $recommendedPrice = $currentPrice;
        } elseif ($isStale) {
            $recommendationType = 'STALE_BUYBOX_DATA';
            $riskLevel = 'medium';
            $reasonCodes[] = 'BUYBOX_DATA_STALE';
            $recommendedPrice = $currentPrice;
        } else {
            $priceStep = (float) $policy['price_step'];
            $maxDropPct = (float) $policy['max_single_drop_percent'];
            $iAmWinner = $listing->seller_rank === 1;

            if (! $iAmWinner) {
                // We are losing buybox. Target candidate: buybox_price - priceStep
                $targetCandidate = round(max(0, $buyboxPrice - $priceStep), 2);

                // Max allowed drop check
                $minPriceByMaxDrop = $currentPrice > 0 ? round($currentPrice * (1 - $maxDropPct / 100), 2) : 0;
                $effectiveCandidate = max($targetCandidate, $minPriceByMaxDrop);

                if ($effectiveCandidate >= $minimumSafePrice) {
                    $recommendationType = ($effectiveCandidate == $buyboxPrice) ? 'MATCH_BUYBOX' : 'LOWER_TO_WIN';
                    $recommendedPrice = $effectiveCandidate;
                    $reasonCodes[] = 'LOWER_PRICE_SAFE';

                    if ($effectiveCandidate > $targetCandidate) {
                        $riskLevel = 'medium';
                        $reasonCodes[] = 'MAX_DROP_LIMITED';
                    }
                } else {
                    // Cannot safely beat buybox price without violating minimum safe price
                    $recommendationType = 'PROTECT_MARGIN';
                    $recommendedPrice = max($currentPrice, $minimumSafePrice);
                    $riskLevel = 'medium';
                    $reasonCodes[] = 'BUYBOX_BELOW_SAFE_PRICE';
                }
            } else {
                // We are winning buybox. Can we raise price without losing buybox?
                if ($secondPrice !== null && $secondPrice > ($currentPrice + $priceStep)) {
                    $candidateRaise = round($secondPrice - $priceStep, 2);

                    if ($maximumAllowedPrice !== null && $candidateRaise > $maximumAllowedPrice) {
                        $candidateRaise = $maximumAllowedPrice;
                    }

                    if ($candidateRaise > $currentPrice) {
                        $recommendationType = 'RAISE_WHILE_KEEPING_BUYBOX';
                        $recommendedPrice = $candidateRaise;
                        $riskLevel = 'medium'; // Raising is opportunistic, medium risk
                        $reasonCodes[] = 'RAISE_OPPORTUNITY_SECOND_SELLER';
                    }
                } else {
                    $recommendationType = 'KEEP_PRICE';
                    $recommendedPrice = $currentPrice;
                    $reasonCodes[] = 'OPTIMAL_BUYBOX_POSITION';
                }
            }
        }

        // Recommended profit simulation
        $expectedProfit = 0.0;
        $expectedProfitMargin = 0.0;
        $vatAmount = (float) data_get($currentSim, 'breakdown.sales_vat', 0);
        $commissionAmount = (float) data_get($currentSim, 'breakdown.commission', 0);

        if ($recommendedPrice !== null && $recommendedPrice > 0) {
            $recSim = $this->simulationService->simulate($simBase + ['sale_price' => $recommendedPrice]);
            $expectedProfit = (float) ($recSim['net_profit'] ?? 0);
            $expectedProfitMargin = (float) ($recSim['profit_margin_percent'] ?? 0);
            $vatAmount = (float) data_get($recSim, 'breakdown.sales_vat', 0);
            $commissionAmount = (float) data_get($recSim, 'breakdown.commission', 0);
        }

        $priceDiff = $recommendedPrice !== null ? round($recommendedPrice - $currentPrice, 2) : 0.0;

        // Upsert recommendation
        return MpPriceRecommendation::updateOrCreate(
            [
                'store_id' => $store->id,
                'barcode' => $listing->barcode,
            ],
            [
                'marketplace_product_id' => $mpProduct?->id,
                'mp_buybox_listing_id' => $listing->id,
                'listing_id' => $listing->listing_id,
                'current_price' => $currentPrice,
                'buybox_price' => $buyboxPrice,
                'second_price' => $secondPrice,
                'third_price' => $thirdPrice,
                'recommended_price' => $recommendedPrice,
                'minimum_safe_price' => $minimumSafePrice,
                'maximum_allowed_price' => $maximumAllowedPrice,
                'unit_cost' => $cogs,
                'commission_amount' => $commissionAmount,
                'cargo_cost' => $cargoCost,
                'vat_amount' => $vatAmount,
                'service_cost' => $packagingCost,
                'other_cost' => 0.0,
                'expected_profit' => $expectedProfit,
                'expected_profit_margin' => $expectedProfitMargin,
                'current_profit' => $currentProfit,
                'current_profit_margin' => $currentProfitMargin,
                'price_difference' => $priceDiff,
                'recommendation_type' => $recommendationType,
                'risk_level' => $riskLevel,
                'reason_codes' => $reasonCodes,
                'calculation_snapshot' => [
                    'cogs' => $cogs,
                    'commission_rate' => $commissionRate,
                    'cargo_cost' => $cargoCost,
                    'packaging_cost' => $packagingCost,
                    'vat_rate' => $vatRate,
                    'policy' => $policy,
                    'sim_base' => $simBase,
                ],
                'status' => 'new',
                'expires_at' => now()->addHours(24),
            ]
        );
    }

    protected function calculateMinSafePriceForMargin(array $simBase, float $targetMarginPct): float
    {
        $res = $this->simulationService->simulate(array_merge($simBase, [
            'target_mode' => 'margin',
            'target_margin_percent' => $targetMarginPct,
        ]));

        return (float) ($res['target_price'] ?? 0.0);
    }

    protected function calculateMinSafePriceForAmount(array $simBase, float $targetAmount): float
    {
        $res = $this->simulationService->simulate(array_merge($simBase, [
            'target_mode' => 'amount',
            'target_profit_amount' => $targetAmount,
        ]));

        return (float) ($res['target_price'] ?? 0.0);
    }
}
