<?php

namespace Tests\Feature\CustomerCare;

use Tests\TestCase;
use App\Models\User;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\SupportChannel;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\SupportReplyMacro;
use App\Models\SupportInternalNote;
use App\Models\SupportAgentPresence;
use App\Models\SupportSavedView;
use App\Services\Support\CustomerCareWorkspaceService;
use App\Services\Support\CustomerCareOrganizationContext;
use App\Services\Support\TenantContext;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;

class CustomerCareAgentWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private MarketplaceStore $store;
    private MarketplaceStore $otherStore;
    private SupportChannel $channel;
    private SupportConversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $le = LegalEntity::create([
            'user_id'      => $this->adminUser->id,
            'name'         => 'Test Org',
            'company_name' => 'Co',
            'tax_office'   => 'Kadikoy',
            'tax_number'   => '1234567890',
            'address'      => 'Istanbul',
        ]);

        $this->store = MarketplaceStore::create([
            'store_name'      => 'Store A',
            'store_key'       => 'store_a',
            'user_id'         => $this->adminUser->id,
            'legal_entity_id' => $le->id,
            'marketplace'     => 'trendyol',
            'is_active'       => true,
        ]);

        $this->otherStore = MarketplaceStore::create([
            'store_name'      => 'Store B',
            'store_key'       => 'store_b',
            'user_id'         => $this->adminUser->id,
            'legal_entity_id' => $le->id,
            'marketplace'     => 'trendyol',
            'is_active'       => true,
        ]);

        $this->channel = SupportChannel::create([
            'store_id'   => $this->store->id,
            'key'        => 'trendyol',
            'name'       => 'Trendyol Channel',
            'status'     => 'active',
            'is_enabled' => true,
        ]);

        $this->conversation = SupportConversation::create([
            'store_id'                 => $this->store->id,
            'support_channel_id'       => $this->channel->id,
            'external_conversation_id' => 'conv_123',
            'external_customer_id'     => 'cust_123',
            'status'                   => 'open',
            'source_type'              => 'trendyol',
        ]);

        Config::set('customer-care.enabled', true);
    }

    #[Test]
    public function workspace_route_blocks_when_flag_off(): void
    {
        Config::set('customer-care.agent_workspace_enabled', false);

        $response = $this->actingAs($this->adminUser)
            ->get(route('customer-care.agent-workspace'));

        $response->assertStatus(404);
    }

    #[Test]
    public function workspace_route_renders_when_flag_on(): void
    {
        Config::set('customer-care.agent_workspace_enabled', true);

        $response = $this->actingAs($this->adminUser)
            ->get(route('customer-care.agent-workspace'));

        $response->assertStatus(200);
    }

    #[Test]
    public function cross_store_macro_access_is_blocked(): void
    {
        Config::set('customer-care.agent_workspace_enabled', true);

        // Başka mağazaya ait makro oluşturalım
        $macro = SupportReplyMacro::create([
            'store_id'         => $this->otherStore->id,
            'title'            => 'Other Macro',
            'body'             => 'This is a macro for Store B',
            'category'         => 'greeting',
            'channel_scope'    => 'trendyol',
            'is_active'        => true,
        ]);

        // Kendi yetkili olmadığı mağazanın makrosunu render ettirmeyi dene
        $service = app(CustomerCareWorkspaceService::class);
        $unauthorizedUser = User::factory()->create(['role' => 'operator', 'is_active' => true]);

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $service->renderMacro($macro, [], $unauthorizedUser);
    }

    #[Test]
    public function macro_variable_substitution_works(): void
    {
        $macro = SupportReplyMacro::create([
            'store_id'         => $this->store->id,
            'title'            => 'Macro A',
            'body'             => 'Merhaba {customer_name}, {store_name} mağazamıza hoş geldiniz.',
            'category'         => 'greeting',
            'is_active'        => true,
        ]);

        $service = app(CustomerCareWorkspaceService::class);
        $result = $service->renderMacro($macro, [
            'customer_name' => 'Caner',
            'store_name'    => 'ZOLM Shop',
        ], $this->adminUser);

        $this->assertEquals('Merhaba Caner, ZOLM Shop mağazamıza hoş geldiniz.', $result);
    }

    #[Test]
    public function internal_note_is_encrypted_and_does_not_trigger_outbound_dispatch(): void
    {
        $note = SupportInternalNote::create([
            'conversation_id' => $this->conversation->id,
            'user_id'         => $this->adminUser->id,
            'note_encrypted'  => 'Bu hassas bir dahili bilgi notudur.',
        ]);

        $this->assertDatabaseHas('support_internal_notes', [
            'conversation_id' => $this->conversation->id,
        ]);

        // Veritabanında ham olarak kayıtlı olmadığını doğrula (şifrelenmiş)
        $rawNote = \Illuminate\Support\Facades\DB::table('support_internal_notes')->first();
        $this->assertNotNull($rawNote);
        $this->assertStringNotContainsString('Bu hassas bir dahili bilgi notudur.', $rawNote->note_encrypted);

        // Model attribute üzerinden otomatik deşifre edildiğini doğrula
        $loadedNote = SupportInternalNote::first();
        $this->assertEquals('Bu hassas bir dahili bilgi notudur.', $loadedNote->note_encrypted);

        // Outbound dispatch tetiklenmediğini doğrula
        $this->assertDatabaseMissing('support_dispatches', [
            'conversation_id' => $this->conversation->id,
        ]);
    }

    #[Test]
    public function presence_ttl_and_scoping_works(): void
    {
        $service = app(CustomerCareWorkspaceService::class);

        $otherUser = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        // Presence ekle
        $service->registerPresence($this->conversation->id, $otherUser->id);

        $activePresences = $service->getActivePresences($this->conversation->id, $this->adminUser->id);
        $this->assertCount(1, $activePresences);

        // Eski varlıkların TTL ile silinmesini doğrula
        SupportAgentPresence::where('conversation_id', $this->conversation->id)->update([
            'last_active_at' => now()->subSeconds(70),
        ]);

        $activePresencesEmpty = $service->getActivePresences($this->conversation->id, $this->adminUser->id);
        $this->assertCount(0, $activePresencesEmpty);
    }

    #[Test]
    public function saved_views_are_isolated(): void
    {
        $service = app(CustomerCareWorkspaceService::class);

        $service->saveSavedView($this->adminUser->id, $this->store->id, 'Açık Sohbetler', ['status' => 'open']);

        $views = $service->getSavedViews($this->adminUser->id, $this->store->id);
        $this->assertCount(1, $views);
        $this->assertEquals('Açık Sohbetler', $views->first()->name);

        // Başka bir mağaza için filtrenin gelmediğini doğrula
        $otherStoreViews = $service->getSavedViews($this->adminUser->id, $this->otherStore->id);
        $this->assertCount(0, $otherStoreViews);
    }
}
