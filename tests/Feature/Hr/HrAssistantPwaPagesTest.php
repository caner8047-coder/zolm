<?php

namespace Tests\Feature\Hr;

use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\TenantContext;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HrAssistantPwaPagesTest extends TestCase
{
    use RefreshHrDatabase;

    public function test_assistant_page_and_pwa_manifest_render(): void
    {
        (new \Database\Seeders\Hr\HrPermissionSeeder)->run();
        $roleId = DB::table('roles')->where('slug', 'hr_admin')->value('id');
        $user = User::factory()->create(['role_id' => $roleId]);
        $this->actingAs($user);
        $tenant = LegalEntity::create(['user_id' => $user->id, 'name' => 'PWA', 'tax_number' => '6565656565', 'is_active' => true]);
        app(TenantContext::class)->set($tenant);

        $this->get(route('hr.assistant'))->assertOk()->assertSee('İK Asistanı')->assertSee('/manifest.webmanifest', false);
        $manifest = json_decode((string) file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('ZOLM İş Yönetim Platformu', $manifest['name']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertFileExists(public_path('icons/zolm-pwa-192.png'));
        $this->assertFileExists(public_path('icons/zolm-pwa-512.png'));
        $this->assertStringContainsString("url.pathname.startsWith('/hr')", (string) file_get_contents(public_path('hr-sw.js')));
    }
}
