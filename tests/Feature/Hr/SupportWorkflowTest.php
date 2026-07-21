<?php

namespace Tests\Feature\Hr;

use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use App\Modules\Hr\Support\Actions\ManageSupportTicketAction;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SupportWorkflowTest extends TestCase
{
    use RefreshHrDatabase;

    public function test_employee_ticket_is_encrypted_and_internal_notes_are_hidden(): void
    {
        (new \Database\Seeders\Hr\HrPermissionSeeder)->run();
        $adminRoleId = DB::table('roles')->where('slug', 'hr_admin')->value('id');
        $manager = User::factory()->create(['role_id' => $adminRoleId]);
        $employeeUser = User::factory()->create(['role' => 'operator']);
        $this->grant($employeeUser, ['hr.support.view', 'hr.support.create']);
        $tenant = LegalEntity::create(['user_id' => $manager->id, 'name' => 'Destek', 'tax_number' => '6060606060', 'is_active' => true]);
        app(TenantContext::class)->set($tenant);
        HrEmployee::withoutGlobalScope('tenant')->create([
            'legal_entity_id' => $tenant->id,
            'user_id' => $employeeUser->id,
            'employee_number' => 'SUP1',
            'national_id_encrypted' => 'enc',
            'national_id_hash' => 'support-employee',
            'national_id_last_four' => '0001',
            'first_name' => 'Destek',
            'last_name' => 'Çalışanı',
            'status' => 'active',
        ]);

        $this->actingAs($employeeUser);
        $action = app(ManageSupportTicketAction::class);
        $ticket = $action->create(['category' => 'payroll', 'subject' => 'Bordro sorusu', 'description' => 'Hassas destek açıklaması', 'priority' => 'high']);
        $action->addMessage($ticket, 'Çalışan mesajı');
        $this->assertStringNotContainsString('Hassas destek açıklaması', DB::table('hr_support_tickets')->where('id', $ticket->id)->value('description_encrypted'));

        $this->actingAs($manager);
        $action->assignToSelf($ticket->fresh());
        $action->addMessage($ticket->fresh(), 'Yalnız ekip notu', true);
        $action->addMessage($ticket->fresh(), 'Çalışana açık yanıt');

        $this->actingAs($employeeUser);
        $visible = $action->visibleMessages($ticket->fresh());
        $this->assertCount(2, $visible);
        $this->assertFalse($visible->contains(fn ($message) => $message->body() === 'Yalnız ekip notu'));

        $this->actingAs($manager);
        $action->changeStatus($ticket->fresh(), 'closed');
        $this->actingAs($employeeUser);
        $this->expectException(HttpException::class);
        $action->addMessage($ticket->fresh(), 'Kapalı talebe yanıt');
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
