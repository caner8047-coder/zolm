<?php

namespace Tests\Feature\Hr;

use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeSensitiveDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_national_id_not_stored_as_plaintext(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $tenant = LegalEntity::create(['user_id' => $user->id, 'name' => 'Test', 'tax_number' => '1111111111', 'is_active' => true]);

        $nationalId = '12345678901';
        $employee = HrEmployee::withoutGlobalScope('tenant')->create([
            'legal_entity_id' => $tenant->id,
            'employee_number' => 'EMP00001',
            'national_id_encrypted' => $nationalId,
            'national_id_hash' => hash('sha256', $nationalId . config('app.key')),
            'national_id_last_four' => substr($nationalId, -4),
            'first_name' => 'Test',
            'last_name' => 'User',
            'status' => 'active',
        ]);

        // Veritabanında açık metin olarak saklanmamalı
        $raw = \Illuminate\Support\Facades\DB::table('hr_employees')
            ->where('id', $employee->id)
            ->value('national_id_encrypted');

        // Laravel encrypted cast ile şifrelenmiş olmalı
        $this->assertNotNull($raw);
        $this->assertNotEquals($nationalId, $raw);
        $this->assertStringNotContainsString($nationalId, $raw);
    }

    public function test_national_id_masked_in_listing(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $tenant = LegalEntity::create(['user_id' => $user->id, 'name' => 'Test', 'tax_number' => '2222222222', 'is_active' => true]);

        $employee = HrEmployee::withoutGlobalScope('tenant')->create([
            'legal_entity_id' => $tenant->id,
            'employee_number' => 'EMP00001',
            'national_id_encrypted' => '12345678901',
            'national_id_hash' => hash('sha256', '12345678901' . config('app.key')),
            'national_id_last_four' => '8901',
            'first_name' => 'Test',
            'last_name' => 'User',
            'status' => 'active',
        ]);

        // Son 4 hane görünmeli
        $this->assertEquals('8901', $employee->national_id_last_four);
        // Encrypted cast okurken çözer - decrypted değer orijinal olmalı
        $this->assertEquals('12345678901', $employee->national_id_encrypted);
        // Ama veritabanında ham değer şifreli olmalı
        $raw = \Illuminate\Support\Facades\DB::table('hr_employees')
            ->where('id', $employee->id)
            ->value('national_id_encrypted');
        $this->assertNotEquals('12345678901', $raw);
    }

    public function test_audit_log_does_not_contain_national_id(): void
    {
        $service = app(\App\Modules\Hr\Core\Services\HrAuditService::class);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('maskSensitive');
        $method->setAccessible(true);

        $data = ['national_id' => '12345678901', 'normal_field' => 'visible'];
        $masked = $method->invoke($service, $data);

        $this->assertStringStartsWith('***', $masked['national_id']);
        $this->assertNotEquals('12345678901', $masked['national_id']);
    }

    public function test_same_tenant_duplicate_hash_prevented(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $tenant = LegalEntity::create(['user_id' => $user->id, 'name' => 'Test', 'tax_number' => '3333333333', 'is_active' => true]);

        $hash = hash('sha256', '12345678901' . config('app.key'));

        HrEmployee::withoutGlobalScope('tenant')->create([
            'legal_entity_id' => $tenant->id, 'employee_number' => 'EMP00001',
            'national_id_encrypted' => '12345678901', 'national_id_hash' => $hash,
            'national_id_last_four' => '8901', 'first_name' => 'A', 'last_name' => 'B', 'status' => 'active',
        ]);

        // Aynı tenant'ta aynı hash ile oluşturulamaz
        $this->expectException(\Illuminate\Database\QueryException::class);
        HrEmployee::withoutGlobalScope('tenant')->create([
            'legal_entity_id' => $tenant->id, 'employee_number' => 'EMP00002',
            'national_id_encrypted' => '12345678901', 'national_id_hash' => $hash,
            'national_id_last_four' => '8901', 'first_name' => 'C', 'last_name' => 'D', 'status' => 'active',
        ]);
    }
}
