<?php

namespace Tests\Feature\Hr;

use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Asset\Actions\AcceptAssetAssignmentAction;
use App\Modules\Hr\Asset\Actions\AssignAssetAction;
use App\Modules\Hr\Asset\Actions\CreateAssetAction;
use App\Modules\Hr\Asset\Actions\ReturnAssetAction;
use App\Modules\Hr\Asset\Enums\AssetAssignmentStatus;
use App\Modules\Hr\Asset\Enums\AssetStatus;
use App\Modules\Hr\Asset\Models\HrAssetCategory;
use App\Modules\Hr\Core\Models\HrIntegrationOutbox;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AssetWorkflowTest extends TestCase
{
    use RefreshHrDatabase;
    private User $user; private LegalEntity $tenant; private HrEmployee $employee; private HrAssetCategory $category;

    protected function setUp(): void
    {
        parent::setUp(); (new \Database\Seeders\Hr\HrPermissionSeeder)->run();
        $role=DB::table('roles')->where('slug','hr_admin')->value('id'); $this->user=User::factory()->create(['role_id'=>$role]); $this->actingAs($this->user);
        $this->tenant=LegalEntity::create(['user_id'=>$this->user->id,'name'=>'Zimmet','tax_number'=>'2323232323','is_active'=>true]); app(TenantContext::class)->set($this->tenant);
        $this->employee=HrEmployee::withoutGlobalScope('tenant')->create(['legal_entity_id'=>$this->tenant->id,'user_id'=>$this->user->id,'employee_number'=>'AST01','national_id_encrypted'=>'enc','national_id_hash'=>'asset-h','national_id_last_four'=>'0001','first_name'=>'Zimmet','last_name'=>'Test','status'=>'active']);
        $this->category=HrAssetCategory::create(['legal_entity_id'=>$this->tenant->id,'code'=>'LAPTOP','name'=>'Bilgisayar']);
    }

    public function test_asset_assignment_acceptance_and_return_are_recorded_in_ledgers(): void
    {
        $asset=app(CreateAssetAction::class)->execute($this->category,['asset_code'=>'DEM-001','name'=>'MacBook','serial_number'=>'SER-1','barcode'=>'BAR-1','stock_item_reference'=>'STK-42']);
        $this->assertSame(AssetStatus::Available,$asset->status);
        $assignment=app(AssignAssetAction::class)->execute($asset,$this->employee,['note'=>'Adaptör ile teslim']);
        $this->assertSame(AssetAssignmentStatus::Assigned,$assignment->status);
        $accepted=app(AcceptAssetAssignmentAction::class)->execute($assignment,true);
        $this->assertNotNull($accepted->accepted_at); $this->assertSame('v1',$accepted->acceptance_statement_version);
        $returned=app(ReturnAssetAction::class)->execute($assignment,'good','Eksiksiz iade');
        $this->assertSame(AssetAssignmentStatus::Returned,$returned->status); $this->assertSame(AssetStatus::Available,$asset->fresh()->status);
        $this->assertCount(4,$asset->events()->get()); $this->assertSame(3,HrIntegrationOutbox::where('target','stock')->count());
    }

    public function test_same_asset_cannot_be_assigned_twice(): void
    {
        $asset=app(CreateAssetAction::class)->execute($this->category,['asset_code'=>'DEM-002','name'=>'Telefon']); app(AssignAssetAction::class)->execute($asset,$this->employee);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class); app(AssignAssetAction::class)->execute($asset->fresh(),$this->employee);
    }

    public function test_lost_return_closes_assignment_and_asset(): void
    {
        $asset=app(CreateAssetAction::class)->execute($this->category,['asset_code'=>'DEM-003','name'=>'Anahtar']); $assignment=app(AssignAssetAction::class)->execute($asset,$this->employee); $result=app(ReturnAssetAction::class)->execute($assignment,'lost','Kayıp bildirimi',true);
        $this->assertSame(AssetAssignmentStatus::Lost,$result->status); $this->assertSame(AssetStatus::Lost,$asset->fresh()->status);
    }
}
