<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\LegalEntity;
use App\Models\Warehouse;
use App\Models\Account;
use App\Models\CashAccount;
use App\Models\BankAccount;
use App\Models\Party;
use App\Models\MpProduct;
use App\Models\AccountingPilotFeedback;
use App\Models\AccountingPilotHealthSnapshot;
use App\Services\Accounting\AccountingPilotReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AccountingPilotReadinessServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_check_returns_warning_after_demo_seed(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);
        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        // Seeder calistir
        Artisan::call('accounting:seed-demo', ['--user' => $user->id]);

        $service = app(AccountingPilotReadinessService::class);
        $snapshot = $service->runHealthCheck($user->id);

        $this->assertInstanceOf(AccountingPilotHealthSnapshot::class, $snapshot);
        $this->assertSame('warning', $snapshot->status);
        $this->assertGreaterThan(0, $snapshot->score);
        $this->assertSame(0, $snapshot->failed_count);
        $this->assertGreaterThan(0, $snapshot->warning_count);
    }

    public function test_health_check_fails_when_missing_warehouse_or_legal_entity(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);
        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        // Seeder calistirmadan bos ortamda kontrol et
        $service = app(AccountingPilotReadinessService::class);
        $snapshot = $service->runHealthCheck($user->id);

        $this->assertSame('failed', $snapshot->status);
        $this->assertGreaterThan(0, $snapshot->failed_count);
    }

    public function test_feedback_lifecycle_and_tenant_isolation(): void
    {
        $user1 = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $user2 = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $service = app(AccountingPilotReadinessService::class);

        // Feedback olusturma
        $feedback = $service->createFeedback($user1->id, $user1->id, [
            'module' => 'Cariler',
            'type' => 'bug',
            'severity' => 'high',
            'title' => 'Cari olusturulamiyor',
            'description' => 'Vergi no girince hata veriyor',
        ]);

        $this->assertInstanceOf(AccountingPilotFeedback::class, $feedback);
        $this->assertTrue($feedback->isOpen());
        $this->assertFalse($feedback->isResolved());

        // summary
        $summary1 = $service->feedbackSummary($user1->id);
        $this->assertSame(1, $summary1['open']);
        $this->assertSame(1, $summary1['critical']);

        // user2 summary
        $summary2 = $service->feedbackSummary($user2->id);
        $this->assertSame(0, $summary2['open']);

        // resolve feedback - tenant isolation check
        $this->actingAs($user2);
        
        $this->assertThrows(function () use ($service, $feedback, $user2) {
            $service->resolveFeedback($feedback, $user2->id);
        });

        // resolve feedback with correct user
        $this->actingAs($user1);
        $resolved = $service->resolveFeedback($feedback, $user1->id);
        $this->assertTrue($resolved->isResolved());
        $this->assertNotNull($resolved->resolved_at);

        $summary1New = $service->feedbackSummary($user1->id);
        $this->assertSame(0, $summary1New['open']);
        $this->assertSame(1, $summary1New['resolved']);
    }

    public function test_health_check_tenant_isolation_on_get_latest(): void
    {
        $user1 = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $user2 = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $service = app(AccountingPilotReadinessService::class);

        config()->set('marketplace.features.accounting_enabled', true);

        // User1 icin snapshot olustur
        $service->runHealthCheck($user1->id);

        $this->assertNotNull($service->getLatestSnapshot($user1->id));
        $this->assertNull($service->getLatestSnapshot($user2->id));
    }

    public function test_service_tenant_guard_aborts_on_unauthorized_actions(): void
    {
        $user1 = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $user2 = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $service = app(AccountingPilotReadinessService::class);

        // acting as user2
        $this->actingAs($user2);

        // runHealthCheck on user1 should abort with 403
        $this->assertThrows(function () use ($service, $user1) {
            $service->runHealthCheck($user1->id);
        });

        // getLatestSnapshot on user1 should abort with 403
        $this->assertThrows(function () use ($service, $user1) {
            $service->getLatestSnapshot($user1->id);
        });

        // createFeedback on user1 should abort with 403
        $this->assertThrows(function () use ($service, $user1) {
            $service->createFeedback($user1->id, $user1->id, [
                'module' => 'Cariler',
                'type' => 'bug',
                'severity' => 'high',
                'title' => 'Cari olusturulamiyor',
            ]);
        });

        // feedbackSummary on user1 should abort with 403
        $this->assertThrows(function () use ($service, $user1) {
            $service->feedbackSummary($user1->id);
        });
    }

    public function test_health_check_fails_if_missing_customer_or_supplier(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);
        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        // Seeder calistir
        Artisan::call('accounting:seed-demo', ['--user' => $user->id]);

        $service = app(AccountingPilotReadinessService::class);

        // Müşteri rolünü silip test et
        \App\Models\PartyRole::where('user_id', $user->id)->where('role', 'customer')->delete();
        $snapshot = $service->runHealthCheck($user->id);
        $this->assertSame('failed', $snapshot->status);
        $this->assertSame('failed', $snapshot->checks_json['customer_party']['status']);
    }
}
