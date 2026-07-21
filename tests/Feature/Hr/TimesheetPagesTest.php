<?php

namespace Tests\Feature\Hr;

use App\Models\HrLicense;
use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use App\Modules\Hr\Timesheet\Actions\CreateTimesheetPeriodAction;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TimesheetPagesTest extends TestCase
{
    use RefreshHrDatabase;

    public function test_timesheet_and_overtime_workspaces_render(): void
    {
        (new \Database\Seeders\Hr\HrPermissionSeeder)->run();
        $roleId=DB::table('roles')->where('slug','hr_admin')->value('id'); $user=User::factory()->create(['role_id'=>$roleId]); $this->actingAs($user);
        $tenant=LegalEntity::create(['user_id'=>$user->id,'name'=>'Test','tax_number'=>'6666666666','is_active'=>true]); app(TenantContext::class)->set($tenant);
        HrLicense::create(['legal_entity_id'=>$tenant->id,'module_key'=>'puantaj','is_active'=>true]);
        HrEmployee::withoutGlobalScope('tenant')->create(['legal_entity_id'=>$tenant->id,'user_id'=>$user->id,'employee_number'=>'UI01','national_id_encrypted'=>'enc','national_id_hash'=>'timesheet-pages-h1','national_id_last_four'=>'0001','first_name'=>'Puantaj','last_name'=>'Test','status'=>'active']);
        $date=now()->subDay()->toDateString(); $period=app(CreateTimesheetPeriodAction::class)->execute('UI Dönemi',$date,$date);
        $this->get(route('hr.timesheets'))->assertOk()->assertSee('Puantaj Dönemleri');
        $this->get(route('hr.timesheets.show',$period->id))->assertOk()->assertSee('UI Dönemi');
        $this->get(route('hr.overtime'))->assertOk()->assertSee('Fazla Mesai Talepleri');
        $this->get(route('hr.my-overtime'))->assertOk()->assertSee('Fazla Mesailerim');
        $this->get(route('hr.settings.overtime-types'))->assertOk()->assertSee('Fazla Mesai Türleri');
    }
}
