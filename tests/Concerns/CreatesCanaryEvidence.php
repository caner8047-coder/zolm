<?php

namespace Tests\Concerns;

use App\Models\IntegrationPushRun;
use App\Models\MpPriceAction;
use App\Models\MpPriceCanaryApproval;
use App\Models\MpPricePilotProduct;
use App\Models\MpPriceShadowEvaluation;
use App\Models\MpPriceShadowRecord;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\MarketplaceCanaryReadinessService;

/**
 * Shared trait for canary tests that need real readiness evidence
 * and fingerprinted approvals.
 */
trait CreatesCanaryEvidence
{
    /**
     * Create minimal valid readiness evidence for the given store.
     * Mirrors createValidBaselineEvidence() across test classes.
     */
    protected function createBaselineReadinessEvidence(MarketplaceStore $store, string $barcode = 'BARCODE1'): void
    {
        for ($i = 0; $i < 25; $i++) {
            $rec = MpPriceShadowRecord::create([
                'store_id' => $store->id,
                'barcode'  => $barcode,
                'current_price' => 100.0,
                'risk_level' => 'low',
                'is_actionable' => true,
                'recommendation_type' => 'LOWER_TO_WIN',
                'simulated_at' => now()->subHours(48)->addMinutes($i),
            ]);

            MpPriceShadowEvaluation::create([
                'shadow_record_id'       => $rec->id,
                'store_id'               => $store->id,
                'barcode'                => $barcode,
                'evaluated_at'           => now()->subHours(24),
                'actual_buybox_price_after' => 95.0,
                'actual_seller_rank_after'  => 1,
                'would_win_buybox'       => true,
                'would_preserve_margin'  => true,
                'was_unnecessary_drop'   => false,
            ]);
        }

        for ($i = 0; $i < 20; $i++) {
            IntegrationPushRun::create([
                'store_id'          => $store->id,
                'channel_listing_id' => null,
                'push_type'         => 'price',
                'status'            => 'completed',
                'target_price'      => 95.0,
            ]);
        }

        for ($i = 0; $i < 20; $i++) {
            MpPriceAction::create([
                'store_id'        => $store->id,
                'barcode'         => $barcode,
                'status'          => 'success',
                'old_price'       => 100.0,
                'requested_price' => 95.0,
                'action_type'     => 'price_change',
                'trigger_type'    => 'manual',
            ]);
        }

        MpPricePilotProduct::firstOrCreate(
            ['store_id' => $store->id, 'barcode' => $barcode],
            ['mode' => 'shadow', 'inclusion_reason' => 'test']
        );
    }

    /**
     * Compute the real readiness hash for the given store
     * using the actual readiness service.
     */
    protected function computeReadinessHash(MarketplaceStore $store): string
    {
        $service   = app(MarketplaceCanaryReadinessService::class);
        $readiness = $service->checkReadiness($store);
        return $service->generateReadinessHash($readiness);
    }

    /**
     * Create a fingerprinted MpPriceCanaryApproval for the given store,
     * using the real readiness hash.
     *
     * @param  MarketplaceStore  $store
     * @param  int               $approvedBy
     * @param  array<string>     $barcodes
     * @param  array             $overrides
     * @return MpPriceCanaryApproval
     */
    protected function createFingerprintedApproval(
        MarketplaceStore $store,
        int $approvedBy,
        array $barcodes = ['BARCODE1'],
        array $overrides = []
    ): MpPriceCanaryApproval {
        $hash = $this->computeReadinessHash($store);

        return MpPriceCanaryApproval::create(array_merge([
            'store_id'             => $store->id,
            'approved_by'          => $approvedBy,
            'approval_scope'       => 'single_product',
            'approved_product_ids' => $barcodes,
            'expires_at'           => now()->addHours(24),
            'status'               => 'approved',
            'readiness_hash'       => $hash,
            'readiness_version'    => '1.0',
            'policy_version'       => '1.0',
            'rule_version'         => '1.0',
            'shadow_data_cutoff'   => now(),
            'api_metrics_cutoff'   => now(),
            'queue_metrics_cutoff' => now(),
        ], $overrides));
    }
}
