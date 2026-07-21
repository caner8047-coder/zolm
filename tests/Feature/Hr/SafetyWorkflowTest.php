<?php

namespace Tests\Feature\Hr;

use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use App\Modules\Hr\Safety\Actions\ManageSafetyAction;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SafetyWorkflowTest extends TestCase
{
    use RefreshHrDatabase;

    public function test_incident_requires_completed_actions_and_health_data_is_encrypted(): void
    {
        (new \Database\Seeders\Hr\HrPermissionSeeder)->run();
        $adminRoleId = DB::table('roles')->where('slug', 'hr_admin')->value('id');
        $reporter = User::factory()->create(['role_id' => $adminRoleId]);
        $manager = User::factory()->create(['role_id' => $adminRoleId]);
        $tenant = LegalEntity::create(['user_id' => $reporter->id, 'name' => 'İSG', 'tax_number' => '6161616161', 'is_active' => true]);
        app(TenantContext::class)->set($tenant);
        $employee = HrEmployee::withoutGlobalScope('tenant')->create([
            'legal_entity_id' => $tenant->id,
            'user_id' => $reporter->id,
            'employee_number' => 'ISG1',
            'national_id_encrypted' => 'enc',
            'national_id_hash' => 'safety-employee',
            'national_id_last_four' => '0001',
            'first_name' => 'İSG',
            'last_name' => 'Çalışanı',
            'status' => 'active',
        ]);

        $this->actingAs($reporter);
        $service = app(ManageSafetyAction::class);
        $incident = $service->reportIncident([
            'affected_employee_id' => $employee->id,
            'incident_type' => 'near_miss',
            'severity' => 'high',
            'occurred_at' => now()->subMinute()->toDateTimeString(),
            'location' => 'Üretim hattı',
            'description' => 'Koruyucu kapak açık kaldı',
            'immediate_action' => 'Makine durduruldu',
            'lost_time' => false,
        ]);
        $this->assertStringNotContainsString('Koruyucu kapak', DB::table('hr_safety_incidents')->where('id', $incident->id)->value('description_encrypted'));

        $this->actingAs($manager);
        $service->assignToSelf($incident->fresh());
        $correctiveAction = $service->addCorrectiveAction($incident->fresh(), ['title' => 'Kapak sensörünü yenile', 'due_on' => today()->addWeek()->toDateString()]);
        try {
            $service->closeIncident($incident->fresh());
            $this->fail('Açık aksiyon varken olay kapatılamamalı.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
        $service->completeCorrectiveAction($correctiveAction, 'Bakım formu BF-101');
        $closed = $service->closeIncident($incident->fresh());
        $this->assertSame('closed', $closed->status);
        $this->assertNotNull($closed->closed_at);

        $health = $service->createHealthRecord($employee, [
            'record_type' => 'fitness',
            'recorded_on' => today()->toDateString(),
            'expires_on' => today()->addYear()->toDateString(),
            'provider' => 'Özel Sağlık Merkezi',
            'result' => 'İşe uygundur',
            'details' => 'Hassas sağlık detayı',
        ]);
        $raw = DB::table('hr_health_records')->where('id', $health->id)->first();
        $this->assertStringNotContainsString('İşe uygundur', $raw->result_encrypted);
        $this->assertStringNotContainsString('Hassas sağlık detayı', $raw->details_encrypted);

        $restrictedManager = User::factory()->create(['role' => 'operator']);
        $this->grant($restrictedManager, ['hr.isg.manage']);
        $this->actingAs($restrictedManager);
        $this->expectException(HttpException::class);
        $service->createHealthRecord($employee, [
            'record_type' => 'fitness',
            'recorded_on' => today()->toDateString(),
            'result' => 'Görüntülenmemeli',
        ]);
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
