<?php

namespace Tests\Feature\Hr;

use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Personnel\Actions\CreateEmployeeAction;
use App\Modules\Hr\Personnel\Livewire\EmployeeCreate;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class EmployeeCreateWorkflowTest extends TestCase
{
    use RefreshHrDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'admin']);
        $tenant = LegalEntity::create([
            'user_id' => $this->user->id,
            'name' => 'Çalışan Kayıt Testi',
            'tax_number' => '8181818181',
            'is_active' => true,
        ]);

        app(TenantContext::class)->set($tenant);
        $this->actingAs($this->user);
        Storage::fake('private');
    }

    public function test_form_requires_an_eleven_digit_national_id(): void
    {
        Livewire::test(EmployeeCreate::class)
            ->set('first_name', 'Caner')
            ->set('last_name', 'Ünal')
            ->set('start_date', '2026-07-01')
            ->call('save')
            ->assertHasErrors(['national_id' => 'required']);

        $this->assertDatabaseCount('hr_employees', 0);
    }

    public function test_form_provisions_initial_organization_options(): void
    {
        Livewire::test(EmployeeCreate::class)
            ->assertSet('branch_id', fn ($value) => $value !== null)
            ->assertSet('department_id', fn ($value) => $value !== null)
            ->assertSet('position_id', fn ($value) => $value !== null)
            ->assertSee('Merkez')
            ->assertSee('Genel')
            ->assertSee('Çalışan');

        $this->assertDatabaseCount('hr_branches', 1);
        $this->assertDatabaseCount('hr_departments', 1);
        $this->assertDatabaseCount('hr_positions', 1);
    }

    public function test_valid_form_creates_encrypted_employee_and_employment_record(): void
    {
        Livewire::test(EmployeeCreate::class)
            ->set('first_name', 'Caner')
            ->set('last_name', 'Ünal')
            ->set('national_id', '12345678901')
            ->set('start_date', '2026-07-01')
            ->call('save')
            ->assertHasNoErrors();

        $employee = HrEmployee::withoutGlobalScope('tenant')->sole();

        $this->assertSame('12345678901', $employee->national_id_encrypted);
        $this->assertSame('8901', $employee->national_id_last_four);
        $this->assertNotSame(
            '12345678901',
            DB::table('hr_employees')->where('id', $employee->id)->value('national_id_encrypted')
        );
        $this->assertDatabaseHas('hr_employment_records', ['employee_id' => $employee->id]);
    }

    public function test_action_rejects_duplicate_national_id_with_validation_error(): void
    {
        $action = app(CreateEmployeeAction::class);
        $employeeData = [
            'first_name' => 'İlk',
            'last_name' => 'Çalışan',
            'national_id' => '12345678901',
        ];
        $employmentData = [
            'start_date' => '2026-07-01',
            'employment_type' => 'full_time',
        ];

        $action->execute($employeeData, $employmentData);

        try {
            $action->execute([...$employeeData, 'first_name' => 'İkinci'], $employmentData);
            $this->fail('Mükerrer TC kimlik numarası kabul edilmemeliydi.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('national_id', $exception->errors());
        }

        $this->assertDatabaseCount('hr_employees', 1);
    }

    public function test_retry_completes_existing_employee_with_photo_without_duplicate(): void
    {
        Livewire::test(EmployeeCreate::class)
            ->set('first_name', 'Caner')
            ->set('last_name', 'Ünal')
            ->set('national_id', '12345678901')
            ->set('start_date', '2026-07-01')
            ->call('save')
            ->assertHasNoErrors();

        $employee = HrEmployee::withoutGlobalScope('tenant')->sole();
        $this->assertNull($employee->photo_file_id);

        Livewire::test(EmployeeCreate::class)
            ->set('first_name', 'Caner')
            ->set('last_name', 'Ünal')
            ->set('national_id', '12345678901')
            ->set('start_date', '2026-07-01')
            ->set('photo', UploadedFile::fake()->image('personel.png', 200, 200))
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('hr_employees', 1);
        $this->assertNotNull($employee->fresh()->photo_file_id);
        $this->assertDatabaseCount('hr_files', 1);
    }
}
