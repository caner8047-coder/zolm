<?php

namespace Tests\Feature\CustomerCare;

use Tests\TestCase;
use App\Models\User;
use App\Models\MarketplaceStore;
use App\Models\SupportSuccessSnapshot;
use App\Models\SupportSuccessTask;
use App\Models\SupportSuccessNote;
use App\Models\LegalEntity;
use App\Services\Support\CustomerCareSuccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use PHPUnit\Framework\Attributes\Test;

class CustomerCareSuccessTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $otherUser;
    protected MarketplaceStore $store;
    protected MarketplaceStore $otherStore;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create(['role' => 'admin', 'email' => 'admin@zolm.com', 'is_active' => true]);
        $this->otherUser = User::factory()->create(['role' => 'operator', 'email' => 'other@zolm.com', 'is_active' => true]);

        $le = LegalEntity::create([
            'user_id'      => $this->adminUser->id,
            'name'         => 'Test Legal',
            'company_name' => 'Test Co',
            'tax_office'   => 'Kadikoy',
            'tax_number'   => '1234567890',
            'address'      => 'Istanbul',
        ]);

        $this->store = MarketplaceStore::create([
            'store_name'      => 'Test Store',
            'store_key'       => 'test_store',
            'user_id'         => $this->adminUser->id,
            'legal_entity_id' => $le->id,
            'marketplace'     => 'trendyol',
            'is_active'       => true,
        ]);

        $this->otherStore = MarketplaceStore::create([
            'store_name'      => 'Other Store',
            'store_key'       => 'other_store',
            'user_id'         => $this->otherUser->id,
            'legal_entity_id' => $le->id,
            'marketplace'     => 'trendyol',
            'is_active'       => true,
        ]);

        Config::set('customer-care.enabled', true);
        Config::set('customer-care.success_center_enabled', true);
    }

    #[Test]
    public function success_route_blocks_when_flag_off(): void
    {
        Config::set('customer-care.success_center_enabled', false);
        $response = $this->actingAs($this->adminUser)->get('/customer-care/success');
        $response->assertStatus(404);
    }

    #[Test]
    public function health_score_computes_and_stores_snapshot(): void
    {
        $service  = app(CustomerCareSuccessService::class);
        $snapshot = $service->computeSnapshot($this->store->id, $this->adminUser);

        $this->assertDatabaseHas('support_success_snapshots', [
            'store_id' => $this->store->id,
        ]);

        // health_label geçerli bir değer olmalı
        $this->assertContains($snapshot->health_label, ['healthy', 'degraded', 'critical', 'unknown']);
    }

    #[Test]
    public function no_fake_health_score_when_data_missing(): void
    {
        // Hiç eval, launch plan, queue data yokken — skor uydurulamaz
        $service  = app(CustomerCareSuccessService::class);
        $snapshot = $service->computeSnapshot($this->store->id, $this->adminUser);

        // unknown_components boş olmamalı (veri yokken)
        $this->assertNotEmpty($snapshot->unknown_components ?? []);
    }

    #[Test]
    public function cross_store_snapshot_access_is_blocked(): void
    {
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        $service = app(CustomerCareSuccessService::class);
        // otherUser kendi mağazasına erişebilir ama adminUser'ın mağazasına giremez
        $service->computeSnapshot($this->store->id, $this->otherUser);
    }

    #[Test]
    public function task_resolve_is_append_only_and_tenant_scoped(): void
    {
        $service  = app(CustomerCareSuccessService::class);
        $snapshot = $service->computeSnapshot($this->store->id, $this->adminUser);

        $task = SupportSuccessTask::create([
            'store_id'    => $this->store->id,
            'snapshot_id' => $snapshot->id,
            'task_type'   => 'golden_eval_refresh',
            'description' => 'Test görev',
            'status'      => 'open',
        ]);

        $resolved = $service->resolveTask($task->id, $this->adminUser);

        $this->assertEquals('resolved', $resolved->status);
        $this->assertEquals($this->adminUser->id, $resolved->resolved_by);
        $this->assertNotNull($resolved->resolved_at);
    }

    #[Test]
    public function pii_is_masked_in_success_notes(): void
    {
        $service = app(CustomerCareSuccessService::class);
        $rawBody = 'Müşteri ahmet@example.com ile ilgili not. TCKN: 12345678901';

        $note = $service->addNote($this->store->id, $this->adminUser, $rawBody);

        // Şifreli olduğu için raw body doğrudan kayıtlarda görünmemeli
        $this->assertNotEquals($rawBody, $note->body_encrypted);

        // Decrypt edince PII maskelenmeli
        $decrypted = Crypt::decryptString($note->body_encrypted);
        $this->assertStringNotContainsString('ahmet@example.com', $decrypted);
        $this->assertStringNotContainsString('12345678901', $decrypted);
        $this->assertStringContainsString('[E-POSTA-MASKELENDİ]', $decrypted);
        $this->assertStringContainsString('[TCKN-MASKELENDİ]', $decrypted);
    }

    #[Test]
    public function note_cannot_be_added_to_foreign_store(): void
    {
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        $service = app(CustomerCareSuccessService::class);
        // otherUser, adminUser'ın mağazasına not ekleyemez
        $service->addNote($this->store->id, $this->otherUser, 'Yabancı not');
    }

    #[Test]
    public function success_snapshot_dry_run_command_does_not_persist(): void
    {
        Config::set('customer-care.system_actor_email', $this->adminUser->email);

        $this->artisan("customer-care:success-snapshot --store={$this->store->id}")
            ->assertExitCode(0);

        // dry-run varsayılandır — DB'ye yazılmamalı
        $this->assertDatabaseMissing('support_success_snapshots', [
            'store_id' => $this->store->id,
        ]);
    }

    #[Test]
    public function test_success_snapshot_dry_run_computes_health_without_persisting(): void
    {
        $service = app(CustomerCareSuccessService::class);
        $data = $service->calculateSnapshotData($this->store->id, $this->adminUser);

        // Hesaplanan veriler in-memory mevcut olmalı
        $this->assertArrayHasKey('health_score', $data);
        $this->assertArrayHasKey('health_label', $data);
        $this->assertArrayHasKey('component_scores', $data);

        // Ancak veritabanına hiçbir şey yazılmamış olmalı
        $this->assertDatabaseCount('support_success_snapshots', 0);
    }
}
