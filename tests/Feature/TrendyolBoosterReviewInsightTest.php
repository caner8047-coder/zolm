<?php

namespace Tests\Feature;

use App\Livewire\TrendyolBooster;
use App\Models\TrendyolBoosterReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TrendyolBoosterReviewInsightTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_reviews_workspace_can_generate_product_scoped_insights(): void
    {
        config()->set('ai.api_key', '');
        $user = User::factory()->create();

        foreach ([
            [5, 'Ürün kaliteli ve çok kullanışlı.'],
            [2, 'Görseldeki gibi değil, renk farklı.'],
            [1, 'Paketleme kötü ve ürün ezilmiş geldi.'],
            [4, 'Kumaşı güzel ve yumuşak.'],
            [2, 'Renk farklı ve görseldeki gibi değil.'],
        ] as $index => [$rating, $comment]) {
            TrendyolBoosterReview::query()->create([
                'user_id' => $user->id,
                'trendyol_product_id' => '76241080',
                'trendyol_review_id' => 'insight-review-'.$index,
                'product_title' => 'Long Line Puf',
                'reviewer_name_masked' => 'Müşteri',
                'reviewer_name_hash' => hash('sha256', 'customer-'.$index),
                'rating' => $rating,
                'comment' => $comment,
                'comment_length' => mb_strlen($comment),
                'reviewed_at' => now()->subDays($index),
                'fetched_at' => now(),
                'status' => 'approved',
                'is_spam' => false,
            ]);
        }

        $this->actingAs($user);

        Livewire::test(TrendyolBooster::class)
            ->set('activeModule', 'reviews')
            ->call('setReviewWorkspaceTab', 'insights')
            ->call('runReviewInsights', '76241080')
            ->assertSet('reviewWorkspaceTab', 'insights')
            ->assertSet('reviewInsights.sample_count', 5)
            ->assertSeeHtml('data-testid="booster-review-insights"')
            ->assertSee('Renk ve görsel uyumu')
            ->assertSee('Önerilen aksiyonlar');
    }
}
