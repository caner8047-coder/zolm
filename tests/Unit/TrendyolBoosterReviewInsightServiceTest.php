<?php

namespace Tests\Unit;

use App\Models\TrendyolBoosterReview;
use App\Services\AIService;
use App\Services\Marketplace\TrendyolBoosterReviewInsightService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class TrendyolBoosterReviewInsightServiceTest extends TestCase
{
    public function test_it_builds_explainable_themes_risk_and_actions_without_ai(): void
    {
        config()->set('ai.api_key', '');
        $service = new TrendyolBoosterReviewInsightService($this->createMock(AIService::class));
        $reviews = new Collection([
            $this->review(1, 5, 'Ürün çok kaliteli ve sağlam geldi.'),
            $this->review(2, 1, 'Görseldeki gibi değil, renk farklı ve ürün çizik geldi.'),
            $this->review(3, 2, 'Paketleme kötü, ezilmiş geldi.'),
            $this->review(4, 4, 'Kumaşı güzel ve ürün çok kullanışlı.'),
            $this->review(5, 1, 'Renk farklı, görseldeki gibi değil.'),
            $this->review(6, 1, 'Spam kayıt analiz dışı kalmalı.', true),
        ]);

        $result = $service->analyzeCollection($reviews, 12, '76241080');

        $this->assertSame(5, $result['sample_count']);
        $this->assertSame('evidence_engine', $result['provider']);
        $this->assertSame('Renk ve görsel uyumu', $result['complaints'][0]['label']);
        $this->assertSame(2, $result['complaints'][0]['count']);
        $this->assertContains('Y2', $result['complaints'][0]['evidence'][0]);
        $this->assertNotEmpty(collect($result['actions'])->firstWhere('type', 'ai_studio'));
        $this->assertGreaterThan(0, $result['risk_score']);
        $this->assertStringContainsString('kesin iade', $result['evidence_note']);
    }

    public function test_it_accepts_only_ai_findings_that_reference_the_supplied_evidence(): void
    {
        config()->set('ai.api_key', 'test-key');
        config()->set('ai.provider', 'gemini');
        $ai = $this->createMock(AIService::class);
        $ai->expects($this->once())->method('ask')->willReturn(json_encode([
            'summary' => 'Müşteriler renk doğruluğu konusunda tekrar eden bir sorun bildiriyor.',
            'findings' => [
                ['type' => 'complaint', 'label' => 'Renk beklentisi', 'reason' => 'Görsel ve ürün rengi ayrışıyor.', 'evidence_ids' => ['Y1']],
                ['type' => 'complaint', 'label' => 'Uydurma bulgu', 'reason' => 'Kanıt yok.', 'evidence_ids' => ['Y99']],
            ],
            'actions' => [
                ['type' => 'ai_studio', 'title' => 'Renk doğruluğu görseli üret', 'reason' => 'Renk farkını azalt.', 'priority' => 'high', 'evidence_ids' => ['Y1']],
            ],
        ], JSON_UNESCAPED_UNICODE));
        $service = new TrendyolBoosterReviewInsightService($ai);
        $reviews = collect(range(1, 5))->map(fn (int $id) => $this->review($id, $id === 1 ? 2 : 4, $id === 1 ? 'Renk farklı geldi.' : 'Ürün güzel ve kullanışlı.'));

        $result = $service->analyzeCollection($reviews);

        $this->assertSame('gemini', $result['provider']);
        $this->assertCount(1, $result['ai_findings']);
        $this->assertSame(['Y1'], $result['ai_findings'][0]['evidence_ids']);
        $this->assertSame('Renk doğruluğu görseli üret', $result['actions'][0]['title']);
    }

    private function review(int $id, int $rating, string $comment, bool $spam = false): TrendyolBoosterReview
    {
        $review = new TrendyolBoosterReview([
            'trendyol_product_id' => '76241080',
            'product_title' => 'Long Line Puf',
            'rating' => $rating,
            'comment' => $comment,
            'is_spam' => $spam,
            'status' => 'approved',
        ]);
        $review->id = $id;
        $review->reviewed_at = now()->subDays($id);

        return $review;
    }
}
