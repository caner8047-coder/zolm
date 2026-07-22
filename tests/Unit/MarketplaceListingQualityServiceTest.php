<?php

namespace Tests\Unit;

use App\Models\ChannelListing;
use App\Models\ChannelProduct;
use App\Models\MarketplaceStore;
use App\Models\MpProduct;
use App\Models\TrendyolBoosterReview;
use App\Services\AIService;
use App\Services\Marketplace\MarketplaceListingQualityService;
use App\Services\Marketplace\TrendyolBoosterReviewInsightService;
use Tests\TestCase;

class MarketplaceListingQualityServiceTest extends TestCase
{
    public function test_it_scores_real_product_listing_and_review_evidence_without_inventing_attributes(): void
    {
        config()->set('ai.api_key', '');
        $ai = $this->createMock(AIService::class);
        $service = new MarketplaceListingQualityService(
            $ai,
            new TrendyolBoosterReviewInsightService($ai),
        );
        $product = new MpProduct([
            'barcode' => '869000000001',
            'stock_code' => 'P-KRM-M',
            'product_name' => 'Basic Tişört',
            'brand' => 'Zolm',
            'category_name' => 'Tişört',
            'color' => 'Kırmızı',
            'size' => 'M',
            'description' => '',
            'image_url' => 'https://cdn.example.com/product.jpg',
            'image_urls' => ['https://cdn.example.com/product.jpg'],
        ]);
        $channelProduct = new ChannelProduct(['title' => 'Zolm Basic Tişört Kırmızı M']);
        $store = new MarketplaceStore(['marketplace' => 'trendyol', 'store_name' => 'Ana Mağaza']);
        $listing = new ChannelListing(['listing_status' => 'active']);
        $listing->setRelation('channelProduct', $channelProduct);
        $listing->setRelation('store', $store);
        $product->setRelation('channelListings', collect([$listing]));

        $reviews = collect([
            $this->review(1, 2, 'Renk farklı ve görseldeki gibi değil.'),
            $this->review(2, 5, 'Ürün kaliteli ve kullanışlı.'),
        ]);

        $result = $service->analyze($product, $reviews);

        $this->assertSame('evidence_engine', $result['provider']);
        $this->assertSame(1, $result['listing_count']);
        $this->assertSame(2, $result['review_insights']['sample_count']);
        $this->assertStringContainsString('Zolm', $result['draft']['title']);
        $this->assertStringContainsString('Kırmızı', $result['draft']['description']);
        $this->assertStringNotContainsString('pamuk', mb_strtolower($result['draft']['description']));
        $this->assertNotEmpty(collect($result['issues'])->firstWhere('action_type', 'ai_studio'));
        $this->assertContains('U1', collect($result['evidence'])->pluck('id')->all());
        $this->assertContains('L1', collect($result['evidence'])->pluck('id')->all());
    }

    public function test_ai_findings_without_valid_evidence_are_rejected(): void
    {
        config()->set('ai.api_key', 'test-key');
        config()->set('ai.provider', 'gemini');
        $ai = $this->createMock(AIService::class);
        $ai->expects($this->once())->method('ask')->willReturn(json_encode([
            'summary' => 'Başlık ürün kartındaki doğrulanmış bilgilerle güçlendirildi.',
            'title' => 'Zolm Masa Lambası Siyah',
            'description' => 'Zolm marka siyah masa lambası.',
            'findings' => [
                ['severity' => 'warning', 'category' => 'title', 'title' => 'Kanıtlı başlık', 'reason' => 'Marka başlıkta yok.', 'action_type' => 'listing', 'evidence_ids' => ['U1']],
                ['severity' => 'critical', 'category' => 'images', 'title' => 'Uydurma bulgu', 'reason' => 'Kanıtsız.', 'action_type' => 'ai_studio', 'evidence_ids' => ['X99']],
            ],
        ], JSON_UNESCAPED_UNICODE));
        $service = new MarketplaceListingQualityService(
            $ai,
            new TrendyolBoosterReviewInsightService($ai),
        );
        $product = new MpProduct([
            'barcode' => 'L-1',
            'product_name' => 'Masa Lambası',
            'brand' => 'Zolm',
            'color' => 'Siyah',
        ]);
        $product->setRelation('channelListings', collect());

        $result = $service->analyze($product, collect());

        $this->assertSame('gemini', $result['provider']);
        $this->assertSame('Zolm Masa Lambası Siyah', $result['draft']['title']);
        $this->assertNotNull(collect($result['issues'])->firstWhere('title', 'Kanıtlı başlık'));
        $this->assertNull(collect($result['issues'])->firstWhere('title', 'Uydurma bulgu'));
    }

    private function review(int $id, int $rating, string $comment): TrendyolBoosterReview
    {
        $review = new TrendyolBoosterReview([
            'trendyol_product_id' => 'P-1',
            'product_title' => 'Basic Tişört',
            'rating' => $rating,
            'comment' => $comment,
            'is_spam' => false,
            'status' => 'approved',
        ]);
        $review->id = $id;
        $review->reviewed_at = now()->subDays($id);

        return $review;
    }
}
