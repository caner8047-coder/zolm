<?php

namespace Tests\Feature\Hr;

use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Assistant\Services\HrAssistantService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HrAssistantWorkflowTest extends TestCase
{
    use RefreshHrDatabase;

    public function test_assistant_returns_sourced_aggregate_and_stores_encrypted_history(): void
    {
        (new \Database\Seeders\Hr\HrPermissionSeeder)->run();
        $roleId = DB::table('roles')->where('slug', 'hr_admin')->value('id');
        $user = User::factory()->create(['role_id' => $roleId]);
        $this->actingAs($user);
        $tenant = LegalEntity::create(['user_id' => $user->id, 'name' => 'Asistan', 'tax_number' => '6363636363', 'is_active' => true]);
        app(TenantContext::class)->set($tenant);
        foreach ([['AST1', 'Bir'], ['AST2', 'İki']] as [$number, $name]) {
            HrEmployee::withoutGlobalScope('tenant')->create([
                'legal_entity_id' => $tenant->id,
                'employee_number' => $number,
                'national_id_encrypted' => 'enc',
                'national_id_hash' => 'assistant-'.$number,
                'national_id_last_four' => '0001',
                'first_name' => $name,
                'last_name' => 'Çalışan',
                'status' => 'active',
            ]);
        }

        $before = HrEmployee::withoutGlobalScope('tenant')->where('legal_entity_id', $tenant->id)->count();
        $query = app(HrAssistantService::class)->ask('Aktif çalışan sayısını özetle');
        $this->assertSame('headcount', $query->intent);
        $this->assertStringContainsString('2', $query->responseText());
        $this->assertContains('hr_employees.status', $query->sources);
        $this->assertSame($before, HrEmployee::withoutGlobalScope('tenant')->where('legal_entity_id', $tenant->id)->count());

        $raw = DB::table('hr_assistant_queries')->where('id', $query->id)->first();
        $this->assertStringNotContainsString('Aktif çalışan', $raw->query_encrypted);
        $this->assertStringNotContainsString('Aktif çalışan sayısı', $raw->response_encrypted);
    }

    public function test_action_requests_are_blocked_and_salary_requires_permission(): void
    {
        (new \Database\Seeders\Hr\HrPermissionSeeder)->run();
        $user = User::factory()->create(['role' => 'operator']);
        $this->grant($user, ['hr.assistant.query']);
        $this->actingAs($user);
        $tenant = LegalEntity::create(['user_id' => $user->id, 'name' => 'Asistan Yetki', 'tax_number' => '6464646464', 'is_active' => true]);
        app(TenantContext::class)->set($tenant);

        $service = app(HrAssistantService::class);
        $blocked = $service->ask('İzin talebini onayla');
        $this->assertSame('blocked', $blocked->status);
        $denied = $service->ask('Aylık ücret maliyeti nedir?');
        $this->assertSame('denied', $denied->status);
        $this->assertStringContainsString('hr.salary.view', $denied->responseText());
    }

    private function grant(User $user, array $permissions): void
    {
        foreach ($permissions as $permission) {
            DB::table('model_has_permissions')->insert([
                'permission_id' => DB::table('permissions')->where('name', $permission)->value('id'),
                'model_id' => $user->id,
                'model_type' => User::class,
            ]);
        }
    }
}
