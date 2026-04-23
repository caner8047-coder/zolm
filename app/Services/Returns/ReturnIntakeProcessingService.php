<?php

namespace App\Services\Returns;

use App\Models\ReturnIntakeItem;

class ReturnIntakeProcessingService
{
    public function __construct(
        protected ReturnVisionService $visionService,
        protected ReturnMatchingService $matchingService,
        protected ReturnDecisionSuggestionService $decisionSuggestionService,
    ) {
    }

    public function process(ReturnIntakeItem $item): ReturnIntakeItem
    {
        $item->update([
            'intake_status' => 'analyzing',
            'analysis_started_at' => now(),
            'last_error' => null,
        ]);

        try {
            $analysis = $this->visionService->analyze($item->fresh('media'));

            $item->analyses()->create([
                'provider' => $analysis['provider'] ?? null,
                'model' => $analysis['model'] ?? null,
                'prompt_version' => $analysis['prompt_version'] ?? null,
                'confidence' => (float) ($analysis['confidence'] ?? 0),
                'ocr_json' => $analysis['ocr'] ?? [],
                'classification_json' => $analysis['classification'] ?? [],
                'raw_response_json' => $analysis['raw_response_json'] ?? [],
            ]);

            $match = $this->matchingService->match($item->fresh('media'), $analysis['ocr'] ?? []);
            $classification = $analysis['classification'] ?? [];
            $summary = $item->raw_summary_json ?? [];
            $hydratedItem = $item->fresh('media');
            $hydratedItem->forceFill([
                'channel_claim_id' => $match['channel_claim_id'] ?? $item->channel_claim_id,
                'channel_order_id' => $match['channel_order_id'] ?? $item->channel_order_id,
                'matching_confidence' => $match['matching_confidence'] ?? $item->matching_confidence,
                'product_verification_status' => $match['product_verification_status'] ?? $item->product_verification_status,
                'condition_status' => $classification['condition_status'] ?? $item->condition_status,
            ]);
            $suggestion = $this->decisionSuggestionService->suggest($hydratedItem, $classification);

            $item->update([
                'store_id' => $match['store_id'] ?? $item->store_id,
                'channel_claim_id' => $match['channel_claim_id'] ?? $item->channel_claim_id,
                'channel_order_id' => $match['channel_order_id'] ?? $item->channel_order_id,
                'channel_order_package_id' => $match['channel_order_package_id'] ?? $item->channel_order_package_id,
                'matched_by' => $match['matched_by'] ?? $item->matched_by,
                'matching_confidence' => $match['matching_confidence'] ?? $item->matching_confidence,
                'detected_tracking_number' => data_get($analysis, 'ocr.tracking_number'),
                'detected_order_number' => data_get($analysis, 'ocr.order_number'),
                'detected_barcode' => data_get($analysis, 'ocr.product_barcode'),
                'detected_customer_name' => data_get($analysis, 'ocr.customer_name'),
                'cargo_provider' => data_get($analysis, 'ocr.cargo_provider'),
                'condition_status' => $classification['condition_status'] ?? $item->condition_status,
                'product_verification_status' => $match['product_verification_status'] ?? $item->product_verification_status,
                'intake_status' => $match['intake_status'] ?? 'needs_review',
                'suggested_decision' => $suggestion['decision'] ?? null,
                'suggested_confidence' => $suggestion['confidence'] ?? null,
                'suggestion_summary' => $suggestion['summary'] ?? null,
                'analysis_completed_at' => now(),
                'raw_summary_json' => array_merge($summary, [
                    'issue_tags' => array_values(array_filter(array_map('strval', $classification['issue_tags'] ?? []))),
                    'analysis_summary' => (string) ($classification['summary'] ?? ''),
                    'analysis_confidence' => (float) ($analysis['confidence'] ?? 0),
                    'suggested_decision' => $suggestion['decision'] ?? null,
                    'suggested_reasons' => $suggestion['reasons'] ?? [],
                ]),
            ]);
        } catch (\Throwable $exception) {
            $item->update([
                'intake_status' => 'failed',
                'analysis_completed_at' => now(),
                'last_error' => $exception->getMessage(),
            ]);
        }

        return $item->fresh([
            'batch.user',
            'store',
            'claim.items',
            'order.items',
            'package',
            'media',
            'latestAnalysis',
            'latestDecision',
        ]);
    }
}
