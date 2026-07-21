<?php

namespace Tests\Feature\Hr;

use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use App\Modules\Hr\Timesheet\Actions\CalculateTimesheetPeriodAction;
use App\Modules\Hr\Timesheet\Actions\CreateTimesheetPeriodAction;
use App\Modules\Hr\Timesheet\Actions\ExportTimesheetAction;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class TimesheetExportTest extends TestCase
{
    use RefreshHrDatabase;

    public function test_export_creates_safe_xlsx_with_explicit_ledger_values(): void
    {
        (new \Database\Seeders\Hr\HrPermissionSeeder)->run();
        $roleId=DB::table('roles')->where('slug','hr_admin')->value('id'); $user=User::factory()->create(['role_id'=>$roleId]); $this->actingAs($user);
        $tenant=LegalEntity::create(['user_id'=>$user->id,'name'=>'Test','tax_number'=>'7777777777','is_active'=>true]); app(TenantContext::class)->set($tenant);
        HrEmployee::withoutGlobalScope('tenant')->create(['legal_entity_id'=>$tenant->id,'employee_number'=>'=2+2','national_id_encrypted'=>'enc','national_id_hash'=>'export-h1','national_id_last_four'=>'0001','first_name'=>'=FORMULA','last_name'=>'Test','status'=>'active']);
        $date=now()->subDay()->toDateString(); $period=app(CreateTimesheetPeriodAction::class)->execute('Excel',$date,$date); app(CalculateTimesheetPeriodAction::class)->execute($period);
        $relative=app(ExportTimesheetAction::class)->execute($period); $full=storage_path('app/private/'.$relative);
        $this->assertFileExists($full);
        $sheet=IOFactory::load($full)->getActiveSheet();
        $this->assertSame("'=2+2",$sheet->getCell('A2')->getValue());
        $this->assertSame("'=FORMULA Test",$sheet->getCell('B2')->getValue());
        $this->assertSame('Puantaj',$sheet->getTitle());
        @unlink($full);
    }
}
