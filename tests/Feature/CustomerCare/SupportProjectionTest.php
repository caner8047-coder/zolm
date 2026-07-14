<?php

namespace Tests\Feature\CustomerCare;

use App\Models\User;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\MarketplaceQuestion;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Services\Support\SupportProjectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportProjectionTest extends TestCase
{
    use RefreshDatabase;
    use CustomerCareTestHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupSystemActor();
    }

    private function createStore(User $user): MarketplaceStore
    {
        $legalEntity = LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'Test Company',
            'tax_number' => '1234567890',
            'is_active' => true,
        ]);

        return MarketplaceStore::create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'Test Store',
            'store_code' => 'TST',
            'is_active' => true,
        ]);
    }

    /**
     * MarketplaceQuestion kaydının idempotent şekilde projekte edildiğini doğrula.
     */
    public function test_marketplace_question_is_projected_idempotently(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $store = $this->createStore($user);

        $question = MarketplaceQuestion::create([
            'store_id' => $store->id,
            'external_question_id' => 'q-12345',
            'question_type' => 'PRODUCT_QUESTION',
            'status' => 'open',
            'customer_name' => 'Müşteri A',
            'customer_external_id' => 'cust-99',
            'product_name' => 'Test Product',
            'product_sku' => 'SKU-001',
            'question_text' => 'Bu ürünün kumaşı nedir?',
            'asked_at' => now()->subMinutes(10),
            'last_synced_at' => now(),
        ]);

        $service = new SupportProjectionService();

        // İlk Projeksiyon
        $conversation1 = $service->projectQuestion($question);

        $this->assertNotNull($conversation1);
        $this->assertEquals($store->id, $conversation1->store_id);
        $this->assertEquals('trendyol_questions_' . $question->id, $conversation1->external_conversation_id);

        // Gelen mesaj eklenmiş mi?
        $inboundMessages = SupportMessage::where('conversation_id', $conversation1->id)
            ->where('direction', 'inbound')
            ->get();
        $this->assertCount(1, $inboundMessages);
        $this->assertEquals('Bu ürünün kumaşı nedir?', $inboundMessages->first()->body_encrypted);

        // İkinci Projeksiyon (İdempolik kontrolü: aynı kaydı tekrar oluşturmamalı)
        $conversation2 = $service->projectQuestion($question);

        $this->assertEquals($conversation1->id, $conversation2->id);
        $this->assertEquals(1, SupportConversation::count());
        $this->assertEquals(1, SupportMessage::count());

        // Soru cevaplandığında projeksiyonu güncelleme testi
        $question->update([
            'status' => 'answered',
            'answer_text' => 'Pamuklu kumaştır.',
            'answered_at' => now(),
            'answered_by_user_id' => $user->id,
        ]);

        $conversation3 = $service->projectQuestion($question);

        $this->assertEquals($conversation1->id, $conversation3->id);
        $this->assertEquals('resolved', $conversation3->status);
        $this->assertEquals($user->id, $conversation3->assigned_user_id);

        // Cevap mesajı (outbound) eklenmiş mi?
        $outboundMessages = SupportMessage::where('conversation_id', $conversation3->id)
            ->where('direction', 'outbound')
            ->get();
        $this->assertCount(1, $outboundMessages);
        $this->assertEquals('Pamuklu kumaştır.', $outboundMessages->first()->body_encrypted);
        $this->assertEquals(2, SupportMessage::count()); // 1 inbound + 1 outbound
    }
}
