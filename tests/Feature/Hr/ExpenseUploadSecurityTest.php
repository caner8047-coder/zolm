<?php

namespace Tests\Feature\Hr;

use App\Models\HrFile;
use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\MalwareScanner;
use App\Modules\Hr\Core\Services\ScanResult;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Expense\Actions\CreateExpenseAction;
use App\Modules\Hr\Expense\Models\HrExpense;
use App\Modules\Hr\Expense\Models\HrExpenseCategory;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExpenseUploadSecurityTest extends TestCase
{
    use RefreshHrDatabase;

    private HrEmployee $employee;
    private HrExpenseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        (new \Database\Seeders\Hr\HrPermissionSeeder)->run();
        $roleId = DB::table('roles')->where('slug', 'hr_admin')->value('id');
        $user = User::factory()->create(['role_id' => $roleId]); $this->actingAs($user);
        $tenant = LegalEntity::create(['user_id' => $user->id, 'name' => 'Dosya Test', 'tax_number' => '5656565656', 'is_active' => true]); app(TenantContext::class)->set($tenant);
        $this->employee = HrEmployee::withoutGlobalScope('tenant')->create(['legal_entity_id' => $tenant->id, 'user_id' => $user->id, 'employee_number' => 'EXPF01', 'national_id_encrypted' => 'enc', 'national_id_hash' => 'expense-file', 'national_id_last_four' => '0001', 'first_name' => 'Dosya', 'last_name' => 'Test', 'status' => 'active']);
        $this->category = HrExpenseCategory::create(['legal_entity_id' => $tenant->id, 'code' => 'FIS', 'name' => 'Fişli', 'requires_receipt' => true, 'default_vat_rate' => 20]);
    }

    public function test_infected_receipt_is_rejected_without_orphan_file(): void
    {
        $this->app->bind(MalwareScanner::class, fn () => new class implements MalwareScanner { public function scan(string $filePath): ScanResult { return ScanResult::Infected; } });
        try {
            app(CreateExpenseAction::class)->execute($this->employee, $this->category, ['expense_date' => now()->toDateString(), 'net_amount' => 100, 'description' => 'Şüpheli fiş'], UploadedFile::fake()->create('fis.pdf', 100, 'application/pdf'));
            $this->fail('Şüpheli fiş kabul edilmemeliydi.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
        $this->assertSame(0, HrFile::count());
        $this->assertSame(0, HrExpense::count());
    }

    public function test_unavailable_scanner_fails_closed(): void
    {
        config(['hr.malware_scanner.fail_closed' => true]);
        $this->app->bind(MalwareScanner::class, fn () => new class implements MalwareScanner { public function scan(string $filePath): ScanResult { return ScanResult::Unavailable; } });
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(CreateExpenseAction::class)->execute($this->employee, $this->category, ['expense_date' => now()->toDateString(), 'net_amount' => 100, 'description' => 'Tarayıcı yok'], UploadedFile::fake()->create('fis.pdf', 100, 'application/pdf'));
    }
}
