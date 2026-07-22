<?php

namespace Tests\Feature\Hr;

use App\Models\HrLicense;
use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class HrServicePagesSmokeTest extends TestCase
{
    use RefreshHrDatabase;

    public function test_all_static_hr_service_pages_render_without_server_error(): void
    {
        $this->seed(\Database\Seeders\Hr\HrPermissionSeeder::class);

        $roleId = DB::table('roles')->where('slug', 'hr_admin')->value('id');
        $user = User::factory()->create(['role_id' => $roleId]);
        $tenant = LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'İK Servis Kontrolü',
            'tax_number' => '9090909090',
            'is_active' => true,
        ]);

        foreach (array_keys(config('hr.modules', [])) as $moduleKey) {
            HrLicense::create([
                'legal_entity_id' => $tenant->id,
                'module_key' => $moduleKey,
                'is_active' => true,
            ]);
        }

        HrEmployee::withoutGlobalScope('tenant')->create([
            'legal_entity_id' => $tenant->id,
            'user_id' => $user->id,
            'employee_number' => 'SMOKE-001',
            'national_id_encrypted' => 'encrypted',
            'national_id_hash' => 'hr-service-pages-smoke',
            'national_id_last_four' => '0001',
            'first_name' => 'Servis',
            'last_name' => 'Kontrol',
            'status' => 'active',
        ]);

        app(TenantContext::class)->set($tenant);
        $this->actingAs($user);

        $failures = [];
        $checked = 0;

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            $action = $route->getActionName();

            if (!str_starts_with($uri, 'hr')
                || !in_array('GET', $route->methods(), true)
                || str_contains($uri, '{')
                || str_contains($action, '@')
                || $action === 'Closure') {
                continue;
            }

            $checked++;
            $response = $this->get('/'.$uri);

            if ($response->getStatusCode() !== 200) {
                $failures[] = sprintf('%s [%s] => HTTP %d', $route->getName(), $uri, $response->getStatusCode());
            }
        }

        $this->assertGreaterThanOrEqual(50, $checked, 'Beklenen İK servis sayısı taranmadı.');
        $this->assertSame([], $failures, "Hata veren İK servisleri:\n".implode("\n", $failures));
    }
}
