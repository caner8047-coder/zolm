<?php

namespace Tests\Feature\CustomerCare;

use App\Livewire\CustomerCare\KnowledgeSuggestions;
use App\Livewire\CustomerCare\ProductQuestions;
use App\Models\LegalEntity;
use App\Models\MarketplaceQuestion;
use App\Models\MarketplaceStore;
use App\Models\SupportConversation;
use App\Models\SupportKnowledgeSuggestion;
use App\Models\SupportMessage;
use App\Models\User;
use App\Models\WaKnowledgeArticle;
use App\Services\Marketplace\MarketplaceQuestionSyncService;
use App\Services\Support\CustomerCareProductQuestionLearningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerCareProductQuestionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('customer-care.enabled', true);
        Config::set('customer-care.knowledge_enabled', true);
        Config::set('customer-care.governance_enabled', false);
    }

    public function test_product_questions_page_lists_only_accessible_store_records(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $otherUser = User::factory()->create(['role' => 'admin']);
        $store = $this->createStore($user, 'ZOLM Store', 'trendyol');
        $otherStore = $this->createStore($otherUser, 'Başka Store', 'trendyol');
        $question = $this->createAnsweredQuestion($store, 'Kumaşı nedir?', 'Pamuklu kumaştır.');
        $this->createAnsweredQuestion($otherStore, 'Gizli soru', 'Gizli cevap');

        Livewire::actingAs($user)
            ->test(ProductQuestions::class)
            ->assertSee('Ürün Soruları ve AI Eğitim Havuzu')
            ->assertSee($question->question_text)
            ->assertDontSee('Gizli soru');
    }

    public function test_answered_question_becomes_pii_safe_knowledge_candidate_idempotently(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $store = $this->createStore($user, 'ZOLM Store', 'trendyol');
        $question = $this->createAnsweredQuestion(
            $store,
            '05321112233 numaram için ölçüsü nedir?',
            'Caner Bey, ürün 120 x 60 cm ölçüsündedir.'
        );

        $component = Livewire::actingAs($user)->test(ProductQuestions::class)
            ->call('createKnowledgeCandidate', $question->id)
            ->assertSet('errorMessage', '');

        $question->refresh();
        $suggestion = SupportKnowledgeSuggestion::findOrFail($question->learning_suggestion_id);

        $this->assertSame('candidate', $question->learning_status);
        $this->assertStringNotContainsString('05321112233', $suggestion->title);
        $this->assertStringNotContainsString('Caner Bey', $suggestion->proposed_answer);
        $this->assertSame('product', $suggestion->scope);
        $this->assertSame(1, SupportConversation::count());
        $this->assertSame(2, SupportMessage::count());

        $component->call('createKnowledgeCandidate', $question->id);
        $this->assertSame(1, SupportKnowledgeSuggestion::count());

        $service = app(CustomerCareProductQuestionLearningService::class);
        $service->exclude($question->fresh(), $user, 'İnsan kararıyla hariç tutuldu.');
        $this->assertSame('excluded', $question->fresh()->learning_status);
        $this->assertSame('rejected', $suggestion->fresh()->status);

        $service->restore($question->fresh(), $user);
        $this->assertSame('candidate', $question->fresh()->learning_status);
        $this->assertSame('pending', $suggestion->fresh()->status);
    }

    public function test_order_specific_and_high_risk_answers_cannot_enter_learning_queue(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $store = $this->createStore($user, 'ZOLM Store', 'trendyol');
        $orderQuestion = $this->createAnsweredQuestion($store, 'Kargom nerede kaldı?', 'Takip numaranızdan kontrol edebilirsiniz.');
        $healthQuestion = $this->createAnsweredQuestion($store, 'Egzamayı geçirir mi?', 'Bu krem kesin geçirir.');
        $service = app(CustomerCareProductQuestionLearningService::class);

        foreach ([$orderQuestion, $healthQuestion] as $question) {
            try {
                $service->createKnowledgeCandidate($question, $user);
                $this->fail('Riskli soru bilgi adayı olmamalıydı.');
            } catch (\DomainException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }

        $this->assertSame(0, SupportKnowledgeSuggestion::count());
    }

    public function test_knowledge_approval_publishes_article_and_updates_question_status(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $store = $this->createStore($user, 'ZOLM Store', 'trendyol');
        $question = $this->createAnsweredQuestion($store, 'Masa ölçüsü nedir?', '120 x 60 cm ölçüsündedir.');
        $suggestion = app(CustomerCareProductQuestionLearningService::class)
            ->createKnowledgeCandidate($question, $user);

        Livewire::actingAs($user)
            ->test(KnowledgeSuggestions::class)
            ->call('approve', $suggestion->id)
            ->assertSet('errorMessage', '');

        $question->refresh();
        $article = WaKnowledgeArticle::firstOrFail();

        $this->assertSame('applied', $question->learning_status);
        $this->assertSame('Ürün Bilgisi', $article->category);
        $this->assertStringContainsString('Masa ölçüsü nedir?', $article->content);

        $grounded = app(\App\Services\Support\CustomerCareKnowledgeGroundingService::class)
            ->ground($store->id, 'Mimo Yan Sehpa masa ölçüsü');
        $this->assertStringContainsString('120 x 60 cm', $grounded['kb']);
        $this->assertContains('knowledge_article', array_column($grounded['citations'], 'type'));
    }

    public function test_only_applied_question_can_become_golden_candidate(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $store = $this->createStore($user, 'ZOLM Store', 'trendyol');
        $question = $this->createAnsweredQuestion($store, 'Rengi nasıldır?', 'Mat siyah renktedir.');
        $service = app(CustomerCareProductQuestionLearningService::class);

        $this->expectException(\DomainException::class);
        $service->toggleGoldenCandidate($question, $user);
    }

    public function test_applied_question_can_be_added_to_golden_candidate_pool(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $store = $this->createStore($user, 'ZOLM Store', 'trendyol');
        $question = $this->createAnsweredQuestion($store, 'Rengi nasıldır?', 'Mat siyah renktedir.');
        $service = app(CustomerCareProductQuestionLearningService::class);
        $suggestion = $service->createKnowledgeCandidate($question, $user);

        Livewire::actingAs($user)->test(KnowledgeSuggestions::class)->call('approve', $suggestion->id);

        $this->assertTrue($service->toggleGoldenCandidate($question->fresh(), $user));
        $this->assertTrue($question->fresh()->is_golden_candidate);
    }

    public function test_question_sync_projects_answered_hepsiburada_question_to_customer_care(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $store = $this->createStore($user, 'HB Store', 'hepsiburada');

        $result = app(MarketplaceQuestionSyncService::class)->sync($store, [[
            'external_question_id' => 'HB-Q-901',
            'question_text' => 'Kurulum gerekiyor mu?',
            'answer_text' => 'Hayır, ürün kurulu gönderilir.',
            'product_name' => 'Yan Sehpa',
            'product_sku' => 'YS-01',
            'status' => 'open',
            'asked_at' => now()->subDay()->toIso8601String(),
        ]]);

        $this->assertSame(1, $result['created']);
        $this->assertDatabaseHas('support_channels', [
            'store_id' => $store->id,
            'key' => 'hepsiburada',
        ]);
        $this->assertDatabaseHas('support_conversations', [
            'store_id' => $store->id,
            'source_type' => 'hepsiburada',
            'status' => 'resolved',
        ]);
        $this->assertSame(2, SupportMessage::count());
    }

    public function test_product_questions_route_is_fail_closed_with_knowledge_flag(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->createStore($user, 'ZOLM Store', 'trendyol');

        Config::set('customer-care.knowledge_enabled', false);
        $this->actingAs($user)->get(route('customer-care.product-questions'))->assertNotFound();

        Config::set('customer-care.knowledge_enabled', true);
        $this->actingAs($user)->get(route('customer-care.product-questions'))->assertOk();
    }

    private function createStore(User $user, string $name, string $marketplace): MarketplaceStore
    {
        $legalEntity = LegalEntity::create([
            'user_id' => $user->id,
            'name' => $name . ' Legal',
            'tax_number' => (string) random_int(1000000000, 9999999999),
            'is_active' => true,
        ]);

        return MarketplaceStore::create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'store_name' => $name,
            'marketplace' => $marketplace,
            'is_active' => true,
        ]);
    }

    private function createAnsweredQuestion(MarketplaceStore $store, string $question, string $answer): MarketplaceQuestion
    {
        return MarketplaceQuestion::create([
            'store_id' => $store->id,
            'external_question_id' => 'Q-' . uniqid(),
            'question_type' => 'product',
            'status' => 'answered',
            'product_name' => 'Mimo Yan Sehpa',
            'product_sku' => 'MIMO-01',
            'question_text' => $question,
            'answer_text' => $answer,
            'asked_at' => now()->subDay(),
            'answered_at' => now(),
        ]);
    }
}
