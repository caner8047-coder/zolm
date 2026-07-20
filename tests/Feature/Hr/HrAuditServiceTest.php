<?php

namespace Tests\Feature\Hr;

use App\Models\ActivityLog;
use App\Models\HrHoliday;
use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use Tests\Feature\Hr\RefreshHrDatabase;
use Tests\TestCase;

class HrAuditServiceTest extends TestCase
{
    use RefreshHrDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\Hr\HrPermissionSeeder::class);
    }

    public function test_audit_log_has_hr_columns(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $tenant = LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'Test',
            'tax_number' => '5555555555',
            'is_active' => true,
        ]);

        $this->actingAs($user);
        app(TenantContext::class)->set($tenant);

        app(HrAuditService::class)->logEvent('test_action', 'Test açıklaması');

        $log = ActivityLog::latest()->first();
        $this->assertNotNull($log);
        $this->assertEquals('test_action', $log->action);
        $this->assertEquals('hr', $log->metadata['module'] ?? null);
    }

    public function test_audit_log_masks_national_id(): void
    {
        $service = app(HrAuditService::class);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('maskValue');
        $method->setAccessible(true);

        $result = $method->invoke($service, 'national_id', '12345678901');
        $this->assertStringStartsWith('***', $result);
        $this->assertEquals('***8901', $result);
    }

    public function test_audit_log_masks_iban(): void
    {
        $service = app(HrAuditService::class);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('maskValue');
        $method->setAccessible(true);

        $result = $method->invoke($service, 'iban', 'TR330006100519786457841326');
        $this->assertStringStartsWith('TR33', $result);
        $this->assertStringEndsWith('26', $result);
        $this->assertStringContainsString('*', $result);
    }

    public function test_audit_log_masks_salary(): void
    {
        $service = app(HrAuditService::class);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('maskValue');
        $method->setAccessible(true);

        $result = $method->invoke($service, 'gross_salary', 15000);
        $this->assertEquals('[MASKED]', $result);
    }

    public function test_audit_log_excludes_sensitive_data(): void
    {
        $service = app(HrAuditService::class);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('maskSensitive');
        $method->setAccessible(true);

        $data = [
            'national_id' => '12345678901',
            'iban' => 'TR330006100519786457841326',
            'gross_salary' => 15000,
            'blood_type' => 'A+',
            'password' => 'secret',
            'normal_field' => 'visible',
        ];

        $result = $method->invoke($service, $data);

        $this->assertStringStartsWith('***', $result['national_id']);
        $this->assertStringContainsString('*', $result['iban']);
        $this->assertEquals('[MASKED]', $result['gross_salary']);
        $this->assertEquals('[HARIÇ TUTULDU]', $result['blood_type']);
        $this->assertEquals('[ASLA LOGLANMAZ]', $result['password']);
        $this->assertEquals('visible', $result['normal_field']);
    }
}
