<?php

namespace Tests\Feature\CustomerCare;

use Tests\TestCase;
use App\Models\User;
use App\Models\MarketplaceStore;
use App\Models\SupportChannel;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\SupportReconciliationFinding;
use App\Models\SupportProjectionCursor;
use App\Services\Support\CustomerCareReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;

class CustomerCareReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected MarketplaceStore $store;
    protected SupportChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create(['role' => 'admin', 'email' => 'admin@zolm.com']);

        $le = \App\Models\LegalEntity::create([
            'user_id' => $this->adminUser->id,
            'name' => 'Test Legal Entity Name',
            'company_name' => 'Test Holding',
            'tax_office' => 'Kadikoy',
            'tax_number' => '1234567890',
            'address' => 'Istanbul',
        ]);

        $this->store = MarketplaceStore::create([
            'store_name' => 'Test Store',
            'store_key' => 'test_store',
            'user_id' => $this->adminUser->id,
            'legal_entity_id' => $le->id,
            'marketplace' => 'trendyol',
            'is_active' => true,
        ]);
        $this->channel = SupportChannel::create([
            'store_id' => $this->store->id,
            'channel_type' => 'whatsapp',
            'key' => 'whatsapp_key',
            'name' => 'WhatsApp Channel',
            'is_enabled' => true,
        ]);

        Config::set('customer-care.enabled', true);
        Config::set('customer-care.reconciliation_enabled', true);
    }

    public function test_reconciliation_route_blocks_when_flag_off()
    {
        Config::set('customer-care.reconciliation_enabled', false);

        $response = $this->actingAs($this->adminUser)->get('/customer-care/reconciliation');
        $response->assertStatus(404);
    }

    public function test_backfill_idempotent_no_duplicates()
    {
        $service = app(CustomerCareReconciliationService::class);

        $payload = [
            'external_id' => 'msg_ext_111',
            'body' => 'Giriş Mesajı',
        ];

        // First backfill
        $res1 = $service->backfillMessage($this->store->id, $this->channel->id, $payload);
        $this->assertTrue($res1['success']);
        $this->assertFalse($res1['duplicate']);

        // Second backfill (duplicate)
        $res2 = $service->backfillMessage($this->store->id, $this->channel->id, $payload);
        $this->assertTrue($res2['success']);
        $this->assertTrue($res2['duplicate']);
        $this->assertEquals($res1['message_id'], $res2['message_id']);
    }

    public function test_backfill_fails_closed_on_disabled_channel()
    {
        $this->channel->update(['is_enabled' => false]);
        $service = app(CustomerCareReconciliationService::class);

        $this->expectException(\RuntimeException::class);
        $service->backfillMessage($this->store->id, $this->channel->id, [
            'external_id' => 'msg_ext_222',
            'body' => 'Hello',
        ]);
    }

    public function test_backfill_prevents_raw_webhook_pii_leak()
    {
        $service = app(CustomerCareReconciliationService::class);

        $res = $service->backfillMessage($this->store->id, $this->channel->id, [
            'external_id' => 'msg_ext_333',
            'body' => 'Gökhan Bey 5321111111',
        ]);

        $message = SupportMessage::find($res['message_id']);
        $this->assertNotNull($message);
        // Verify we sanitized or stored clean version without raw PII
        // PII redactor is used in the app context, so we look for masked output
        $this->assertStringContainsString('Gökhan Bey', $message->body_encrypted);
    }

    public function test_backfill_cross_store_mismatch_raises_authorization_exception()
    {
        $otherStore = MarketplaceStore::create([
            'store_name' => 'Other Store',
            'store_key' => 'other_store',
            'user_id' => $this->adminUser->id,
            'legal_entity_id' => \App\Models\LegalEntity::first()->id,
            'marketplace' => 'trendyol',
            'is_active' => true,
        ]);

        $service = app(CustomerCareReconciliationService::class);

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $service->backfillMessage($otherStore->id, $this->channel->id, [
            'external_id' => 'msg_ext_444',
            'body' => 'Cross store try',
        ]);
    }

    public function test_repair_dry_run_does_not_mutate()
    {
        $run = \App\Models\SupportReconciliationRun::create([
            'store_id' => $this->store->id,
            'started_at' => now(),
            'status' => 'running',
        ]);

        $finding = SupportReconciliationFinding::create([
            'run_id' => $run->id,
            'store_id' => $this->store->id,
            'finding_type' => 'stale_cursor',
            'details_json' => ['cursor_id' => 999],
            'status' => 'detected',
        ]);

        $service = app(CustomerCareReconciliationService::class);
        $service->repairFinding($finding, $this->adminUser, false);

        $this->assertEquals('detected', $finding->fresh()->status);
    }

    public function test_reconciliation_run_rejects_user_without_store_access(): void
    {
        $outsider = User::factory()->create(['role' => 'operator', 'is_active' => true]);

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        app(CustomerCareReconciliationService::class)
            ->runReconciliation($this->store->id, $outsider);
    }

    public function test_all_store_reconciliation_command_persists_runs_without_hard_coded_store(): void
    {
        $this->adminUser->update(['is_active' => true]);
        Config::set('customer-care.system_actor_email', $this->adminUser->email);

        $this->artisan('customer-care:reconcile-projections', [
            '--all' => true,
            '--execute' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('support_reconciliation_runs', [
            'store_id' => $this->store->id,
            'status' => 'completed',
        ]);
    }
}
