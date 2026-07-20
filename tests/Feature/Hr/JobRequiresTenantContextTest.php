<?php

namespace Tests\Feature\Hr;

use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Jobs\HrJob;
use App\Modules\Hr\Core\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TestHrJob extends HrJob
{
    public static bool $executed = false;
    public static ?int $executedTenantId = null;

    public function execute(): void
    {
        self::$executed = true;
        self::$executedTenantId = app(TenantContext::class)->getId();
    }

    public static function reset(): void
    {
        self::$executed = false;
        self::$executedTenantId = null;
    }
}

class JobRequiresTenantContextTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TestHrJob::reset();
    }

    public function test_job_carries_tenant_id(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $tenant = LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'Test',
            'tax_number' => '1000000001',
            'is_active' => true,
        ]);

        app(TenantContext::class)->set($tenant);

        $job = new TestHrJob();
        $this->assertEquals($tenant->id, $job->tenantId);
    }

    public function test_job_sets_context_on_handle(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $tenant = LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'Test',
            'tax_number' => '1000000002',
            'is_active' => true,
        ]);

        app(TenantContext::class)->set($tenant);
        $job = new TestHrJob();
        app(TenantContext::class)->clear();

        $job->handle();

        $this->assertTrue(TestHrJob::$executed);
        $this->assertEquals($tenant->id, TestHrJob::$executedTenantId);
    }

    public function test_job_does_not_access_other_tenant_data(): void
    {
        $userA = User::factory()->create(['role' => 'admin']);
        $userB = User::factory()->create(['role' => 'admin']);

        $tenantA = LegalEntity::create([
            'user_id' => $userA->id,
            'name' => 'Şirket A',
            'tax_number' => '1000000003',
            'is_active' => true,
        ]);

        $tenantB = LegalEntity::create([
            'user_id' => $userB->id,
            'name' => 'Şirket B',
            'tax_number' => '1000000004',
            'is_active' => true,
        ]);

        // Tenant A context'inde job oluştur
        app(TenantContext::class)->set($tenantA);
        $job = new TestHrJob();

        // Job'un tenantId'si A olmalı
        $this->assertEquals($tenantA->id, $job->tenantId);
        $this->assertNotEquals($tenantB->id, $job->tenantId);

        // Job'u çalıştır
        $job->handle();

        // Job Tenant A context'inde çalışmış olmalı
        $this->assertEquals($tenantA->id, TestHrJob::$executedTenantId);
    }
}
