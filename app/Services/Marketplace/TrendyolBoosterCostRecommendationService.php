<?php

namespace App\Services\Marketplace;

use App\Models\ChannelOrderPackage;
use App\Models\TrendyolBoosterCostRecommendation;
use App\Models\TrendyolBoosterProduct;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TrendyolBoosterCostRecommendationService
{
    public function __construct(
        protected TrendyolBoosterCommissionEstimator $commissionEstimator,
        protected TrendyolBoosterDesiEstimator $desiEstimator,
        protected TrendyolBoosterShippingRateService $shippingRateService,
    ) {}

    /**
     * @param  array<string, mixed>  $productData
     * @return array<string, mixed>
     */
    public function recommend(int $userId, array $productData, ?TrendyolBoosterProduct $tracked = null): array
    {
        $productData = $this->mergeTrackedPayload($productData, $tracked);
        $commission = $this->commissionEstimator->estimate($userId, $productData, $tracked);
        $desi = $this->desiEstimator->estimate($userId, $productData, $tracked);
        $cargoCompany = $this->preferredCargoCompany($userId, (string) ($productData['cargo_company'] ?? ''));
        $shipping = $this->shippingRateService->recommend(
            (float) ($productData['sale_price'] ?? $tracked?->sale_price ?? 0),
            (float) $desi['estimated_desi'],
            $cargoCompany,
            true,
            $userId,
        );
        $warnings = collect([
            (float) $commission['confidence'] < 60 ? 'Komisyon oranı düşük güvenli tahmindir; Satıcı Paneli oranıyla doğrulayın.' : null,
            ($commission['seller_level_source'] ?? '') === 'unavailable' ? 'Satıcı seviyesi için 180 günlük net ciro ve sipariş verisi bulunamadı; seviye uydurulmadı.' : null,
            ($commission['seller_status'] ?? '') === 'inactive' ? 'Satıcı son 30 günde sipariş almadığı için inaktif görünüyor.' : null,
            (float) $desi['confidence'] < 60 ? 'Desi kategori veya görsel tahminidir; paket ölçüsüyle doğrulayın.' : null,
            ($shipping['cost_gross'] ?? null) === null ? 'Kargo tarifesi bulunamadı; otomatik kargo tutarı uygulanmadı.' : null,
        ])->filter()->values()->all();
        $confidenceParts = collect([
            (float) ($commission['confidence'] ?? 0),
            (float) ($desi['confidence'] ?? 0),
            (float) ($shipping['confidence'] ?? 0),
        ])->filter(fn (float $value): bool => $value > 0);
        $recommendation = [
            'version' => 2,
            'generated_at' => now()->toIso8601String(),
            'product' => [
                'tracked_product_id' => $tracked?->id,
                'title' => $productData['title'] ?? $tracked?->title,
                'category_name' => $productData['category_name'] ?? $tracked?->category_name,
                'source_url' => $productData['source_url'] ?? $tracked?->source_url,
                'sale_price' => (float) ($productData['sale_price'] ?? $tracked?->sale_price ?? 0),
            ],
            'commission' => $commission,
            'desi' => $desi,
            'shipping' => $shipping,
            'overall_confidence' => $confidenceParts->isNotEmpty()
                ? round((float) $confidenceParts->average(), 1)
                : 0,
            'warnings' => $warnings,
        ];

        $this->persist($userId, $recommendation, $tracked);

        return $recommendation;
    }

    /** @param array<string, mixed> $productData @return array<string, mixed> */
    protected function mergeTrackedPayload(array $productData, ?TrendyolBoosterProduct $tracked): array
    {
        if (! $tracked) {
            return $productData;
        }

        $tracked->loadMissing('latestSnapshot');
        $page = (array) data_get($tracked->latestSnapshot?->raw_payload, 'page', []);

        return array_replace($page, array_filter(
            $productData,
            fn ($value): bool => $value !== null && $value !== '' && $value !== []
        ), [
            'source_url' => $productData['source_url'] ?? $tracked->source_url,
            'title' => $productData['title'] ?? $tracked->title,
            'category_name' => $productData['category_name'] ?? $tracked->category_name,
            'image_url' => $productData['image_url'] ?? $tracked->image_url,
            'sale_price' => $productData['sale_price'] ?? $tracked->sale_price,
        ]);
    }

    protected function preferredCargoCompany(int $userId, string $requested): string
    {
        if (trim($requested) !== '') {
            return trim($requested);
        }

        if (! Schema::hasTable('channel_order_packages')) {
            return 'TEX';
        }

        $company = ChannelOrderPackage::query()
            ->select('cargo_company', DB::raw('COUNT(*) AS usage_count'))
            ->whereNotNull('cargo_company')
            ->where('cargo_company', '!=', '')
            ->whereHas('store', fn ($query) => $query
                ->where('user_id', $userId)
                ->where('marketplace', 'trendyol'))
            ->groupBy('cargo_company')
            ->orderByDesc('usage_count')
            ->value('cargo_company');

        return trim((string) $company) ?: 'TEX';
    }

    /** @param array<string, mixed> $recommendation */
    protected function persist(
        int $userId,
        array $recommendation,
        ?TrendyolBoosterProduct $tracked,
    ): void {
        if (! Schema::hasTable('trendyol_booster_cost_recommendations')) {
            return;
        }

        $sourceUrl = trim((string) data_get($recommendation, 'product.source_url', ''));
        $sourceUrlHash = hash('sha256', $sourceUrl !== '' ? $sourceUrl : 'tracked:'.($tracked?->id ?? 'unknown'));
        $model = TrendyolBoosterCostRecommendation::query()->firstOrNew([
            'user_id' => $userId,
            'trendyol_booster_product_id' => $tracked?->id,
            'source_url_hash' => $sourceUrlHash,
        ]);
        $model->forceFill([
            'category_name' => Str::limit((string) data_get($recommendation, 'product.category_name', ''), 180, ''),
            'seller_score' => data_get($recommendation, 'commission.seller_score'),
            'seller_level' => data_get($recommendation, 'commission.seller_level'),
            'commission_rate' => data_get($recommendation, 'commission.rate'),
            'commission_source' => data_get($recommendation, 'commission.source'),
            'commission_confidence' => data_get($recommendation, 'commission.confidence', 0),
            'estimated_desi' => data_get($recommendation, 'desi.estimated_desi'),
            'billable_desi' => data_get($recommendation, 'desi.billable_desi'),
            'desi_source' => data_get($recommendation, 'desi.source'),
            'desi_confidence' => data_get($recommendation, 'desi.confidence', 0),
            'cargo_company' => data_get($recommendation, 'shipping.cargo_company'),
            'cargo_cost_net' => data_get($recommendation, 'shipping.cost_net'),
            'cargo_cost_gross' => data_get($recommendation, 'shipping.cost_gross'),
            'cargo_source' => data_get($recommendation, 'shipping.source'),
            'cargo_confidence' => data_get($recommendation, 'shipping.confidence', 0),
            'evidence' => [
                'commission' => $recommendation['commission'] ?? [],
                'desi' => $recommendation['desi'] ?? [],
                'warnings' => $recommendation['warnings'] ?? [],
            ],
            'scenarios' => data_get($recommendation, 'shipping.scenarios', []),
            'estimated_at' => now(),
        ])->save();
    }
}
