<?php

namespace Tests\Feature\Hr;

use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Payroll\Actions\ManagePayrollEmployeeProfileAction;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PayrollEmployeeProfileWorkflowTest extends TestCase
{
    use RefreshHrDatabase;

    public function test_profile_is_versioned_encrypted_and_requires_second_approver(): void
    {
        (new \Database\Seeders\Hr\HrPermissionSeeder)->run();
        $roleId = DB::table('roles')->where('slug', 'hr_admin')->value('id');
        $maker = User::factory()->create(['role_id' => $roleId]);
        $approver = User::factory()->create(['role_id' => $roleId]);
        $this->actingAs($maker);
        $tenant = LegalEntity::create([
            'user_id' => $maker->id,
            'name' => 'Bordro Profil Testi',
            'tax_number' => '4545454545',
            'is_active' => true,
        ]);
        app(TenantContext::class)->set($tenant);
        $employee = HrEmployee::withoutGlobalScope('tenant')->create([
            'legal_entity_id' => $tenant->id,
            'employee_number' => 'PROFILE01',
            'national_id_encrypted' => 'enc',
            'national_id_hash' => 'payroll-profile-test',
            'national_id_last_four' => '0001',
            'first_name' => 'Bordro',
            'last_name' => 'Profili',
            'status' => 'active',
        ]);
        $data = [
            'effective_from' => '2026-01-01',
            'payment_method' => 'bank',
            'iban' => 'TR33 0006 1005 1978 6457 8413 26',
            'bank_name' => 'Test Bankası',
            'bank_account_holder' => 'Bordro Profili',
            'social_security_status' => 'standard',
            'insurance_branch_code' => '4A',
            'incentive_law_code' => '5510',
            'missing_day_default_code' => '01',
            'change_reason' => 'İlk bordro profili',
        ];

        $profile = app(ManagePayrollEmployeeProfileAction::class)->propose($employee, $data);

        $this->assertSame(1, $profile->version);
        $this->assertSame('pending_approval', $profile->status);
        $this->assertSame('1326', $profile->iban_last_four);
        $raw = DB::table('hr_payroll_employee_profiles')->where('id', $profile->id)->first();
        $this->assertStringNotContainsString('TR330006100519786457841326', $raw->iban_encrypted);

        try {
            app(ManagePayrollEmployeeProfileAction::class)->approve($profile);
            $this->fail('Profili hazırlayan kişi aynı profili onaylayamamalı.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->actingAs($approver);
        $approved = app(ManagePayrollEmployeeProfileAction::class)->approve($profile->fresh());
        $this->assertSame('approved', $approved->status);
        $this->assertSame($approver->id, $approved->approved_by);
    }

    public function test_invalid_iban_is_rejected_before_storage(): void
    {
        (new \Database\Seeders\Hr\HrPermissionSeeder)->run();
        $roleId = DB::table('roles')->where('slug', 'hr_admin')->value('id');
        $user = User::factory()->create(['role_id' => $roleId]);
        $this->actingAs($user);
        $tenant = LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'Geçersiz IBAN Testi',
            'tax_number' => '4646464646',
            'is_active' => true,
        ]);
        app(TenantContext::class)->set($tenant);
        $employee = HrEmployee::withoutGlobalScope('tenant')->create([
            'legal_entity_id' => $tenant->id,
            'employee_number' => 'PROFILE02',
            'national_id_encrypted' => 'enc',
            'national_id_hash' => 'invalid-iban-test',
            'national_id_last_four' => '0002',
            'first_name' => 'Geçersiz',
            'last_name' => 'IBAN',
            'status' => 'active',
        ]);

        $this->expectException(ValidationException::class);
        app(ManagePayrollEmployeeProfileAction::class)->propose($employee, [
            'effective_from' => '2026-01-01',
            'payment_method' => 'bank',
            'iban' => 'TR000000000000000000000000',
            'social_security_status' => 'standard',
            'change_reason' => 'Geçersiz veri testi',
        ]);
    }
}
