<?php

namespace Tests\Feature\Hr;

use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Document\Actions\ExportDocumentsAction;
use App\Modules\Hr\Document\Models\HrDocumentType;
use App\Modules\Hr\Document\Models\HrEmployeeDocument;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Feature\Hr\RefreshHrDatabase;
use Tests\TestCase;

class DocumentExportSecurityTest extends TestCase
{
    use RefreshHrDatabase;

    private function hrAdminWithTenant(): array
    {
        (new \Database\Seeders\Hr\HrPermissionSeeder)->run();
        $user = User::factory()->create(['role' => 'admin']);
        $adminRole = DB::table('roles')->where('slug', 'hr_admin')->first();
        DB::table('model_has_roles')->insert(['role_id' => $adminRole->id, 'model_id' => $user->id, 'model_type' => User::class]);
        $tenant = LegalEntity::create(['user_id' => $user->id, 'name' => 'Test', 'tax_number' => '1111111111', 'is_active' => true]);
        app(TenantContext::class)->set($tenant);
        $this->actingAs($user);
        return [$user, $tenant];
    }

    private function makeEmployee(LegalEntity $t, string $name): HrEmployee
    {
        return HrEmployee::withoutGlobalScope('tenant')->create([
            'legal_entity_id' => $t->id, 'employee_number' => 'EMP' . random_int(10000, 99999),
            'national_id_encrypted' => 'enc', 'national_id_hash' => hash('sha256', $name), 'national_id_last_four' => '0001',
            'first_name' => $name, 'last_name' => 'User', 'status' => 'active',
        ]);
    }

    public function test_xlsx_injection_is_neutralized(): void
    {
        Storage::fake('private');
        [$user, $tenant] = $this->hrAdminWithTenant();
        $emp = $this->makeEmployee($tenant, '=HYPERLINK("http://evil")');
        $type = HrDocumentType::create(['legal_entity_id' => $tenant->id, 'code' => 'ID', 'name' => '=1+1', 'category' => 'identity', 'sensitivity' => 'standard', 'is_active' => true]);
        HrEmployeeDocument::withoutGlobalScope('tenant')->create(['legal_entity_id' => $tenant->id, 'employee_id' => $emp->id, 'document_type_id' => $type->id, 'status' => 'active', 'version_number' => 1]);

        $path = app(ExportDocumentsAction::class)->execute();
        $sheet = IOFactory::load(storage_path("app/private/{$path}"))->getActiveSheet();

        $this->assertStringStartsNotWith('=', (string) $sheet->getCell('C2')->getValue());
        $this->assertStringStartsNotWith('=', (string) $sheet->getCell('D2')->getValue());
        $this->assertStringContainsString('HYPERLINK', (string) $sheet->getCell('C2')->getValue());
    }

    public function test_sensitive_documents_excluded_without_permission(): void
    {
        Storage::fake('private');
        (new \Database\Seeders\Hr\HrPermissionSeeder)->run();
        $user = User::factory()->create(['role' => 'admin']);
        $viewPerm = DB::table('permissions')->where('name', 'hr.documents.view')->first();
        DB::table('model_has_permissions')->insert(['permission_id' => $viewPerm->id, 'model_id' => $user->id, 'model_type' => User::class]);
        $tenant = LegalEntity::create(['user_id' => $user->id, 'name' => 'Test', 'tax_number' => '2222222222', 'is_active' => true]);
        app(TenantContext::class)->set($tenant);
        $this->actingAs($user);

        $emp = $this->makeEmployee($tenant, 'Ahmet');
        $sensitive = HrDocumentType::create(['legal_entity_id' => $tenant->id, 'code' => 'SEC', 'name' => 'Gizli', 'category' => 'other', 'sensitivity' => 'highly_sensitive', 'is_active' => true]);
        $standard = HrDocumentType::create(['legal_entity_id' => $tenant->id, 'code' => 'STD', 'name' => 'Standart', 'category' => 'other', 'sensitivity' => 'standard', 'is_active' => true]);
        HrEmployeeDocument::withoutGlobalScope('tenant')->create(['legal_entity_id' => $tenant->id, 'employee_id' => $emp->id, 'document_type_id' => $sensitive->id, 'status' => 'active', 'version_number' => 1]);
        HrEmployeeDocument::withoutGlobalScope('tenant')->create(['legal_entity_id' => $tenant->id, 'employee_id' => $emp->id, 'document_type_id' => $standard->id, 'status' => 'active', 'version_number' => 1]);

        $sheet = IOFactory::load(storage_path("app/private/" . app(ExportDocumentsAction::class)->execute()))->getActiveSheet();
        $names = [];
        for ($r = 2; $r <= 3; $r++) {
            $v = $sheet->getCell('D' . $r)->getValue();
            if ($v !== null && $v !== '') {
                $names[] = $v;
            }
        }
        $this->assertContains('Standart', $names);
        $this->assertNotContains('Gizli', $names);
    }
}
