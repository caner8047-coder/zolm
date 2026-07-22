<?php

namespace Tests\Feature;

use App\Livewire\MpProductsManager;
use App\Models\MpProduct;
use App\Models\TrendyolBoosterReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MpProductsListingQualityTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_product_can_be_analyzed_and_drafts_are_applied_only_to_the_form(): void
    {
        config()->set('ai.api_key', '');
        $user = User::factory()->create();
        $product = MpProduct::query()->create([
            'user_id' => $user->id,
            'barcode' => '869000000099',
            'stock_code' => 'Z-TIS-01',
            'product_name' => 'Basic Tişört',
            'brand' => 'Zolm',
            'category_name' => 'Tişört',
            'color' => 'Kırmızı',
            'size' => 'M',
            'description' => null,
            'image_url' => 'https://cdn.example.com/basic.jpg',
            'image_urls' => ['https://cdn.example.com/basic.jpg'],
            'cogs' => 100,
            'packaging_cost' => 5,
            'cargo_cost' => 20,
            'sale_price' => 299,
            'market_price' => 349,
            'commission_rate' => 20,
            'vat_rate' => 20,
            'stock_quantity' => 10,
            'desi' => 1,
            'pieces' => 1,
            'status' => 'active',
        ]);

        TrendyolBoosterReview::query()->create([
            'user_id' => $user->id,
            'trendyol_product_id' => 'P-99',
            'trendyol_review_id' => 'review-listing-quality-1',
            'product_title' => 'Basic Tişört',
            'reviewer_name_masked' => 'Müşteri',
            'reviewer_name_hash' => hash('sha256', 'listing-quality-customer'),
            'rating' => 2,
            'comment' => 'Renk farklı ve görseldeki gibi değil.',
            'comment_length' => 38,
            'reviewed_at' => now(),
            'fetched_at' => now(),
            'mp_product_id' => $product->id,
            'status' => 'approved',
            'is_spam' => false,
        ]);

        $this->actingAs($user);

        Livewire::test(MpProductsManager::class)
            ->call('openEditProductTab', $product->id, 'listing_quality')
            ->assertSet('editTab', 'listing_quality')
            ->call('runListingQualityAnalysis')
            ->assertSet('listingQualityAnalysis.review_insights.sample_count', 1)
            ->assertSeeHtml('data-testid="mp-products-listing-quality"')
            ->assertSee('Öncelikli geliştirme alanları')
            ->call('applyListingQualityTitleDraft')
            ->assertSet('f_product_name', 'Zolm Basic Tişört Kırmızı M')
            ->set('creativeStudioImage', ['url' => '/storage/mp-products/generated/test.png'])
            ->call('applyCreativeStudioImage')
            ->assertSet('f_image_url', '/storage/mp-products/generated/test.png')
            ->assertSet('f_image_urls.0', '/storage/mp-products/generated/test.png')
            ->set('creativeStudioVideo', ['url' => '/storage/mp-products/generated/test.mp4'])
            ->call('applyCreativeStudioVideo')
            ->assertSet('f_video_urls.0', '/storage/mp-products/generated/test.mp4');

        $this->assertSame('Basic Tişört', $product->fresh()->product_name);
        $this->assertSame('https://cdn.example.com/basic.jpg', $product->fresh()->image_url);
    }
}
