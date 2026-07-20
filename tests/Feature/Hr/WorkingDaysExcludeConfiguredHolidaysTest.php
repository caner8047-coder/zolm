<?php

namespace Tests\Feature\Hr;

use App\Models\HrHoliday;
use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Actions\CalculateWorkingDaysAction;
use App\Modules\Hr\Core\Services\HrCalendarService;
use App\Modules\Hr\Core\Services\TenantContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkingDaysExcludeConfiguredHolidaysTest extends TestCase
{
    use RefreshDatabase;

    private LegalEntity $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create(['role' => 'admin']);
        $this->tenant = LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'Test',
            'tax_number' => '3000000001',
            'is_active' => true,
        ]);

        app(TenantContext::class)->set($this->tenant);
    }

    public function test_weekends_are_excluded(): void
    {
        $calendar = app(HrCalendarService::class);

        // Cumartesi
        $saturday = Carbon::parse('2026-07-25'); // Cumartesi
        $this->assertFalse($calendar->isWorkingDay($saturday));

        // Pazar
        $sunday = Carbon::parse('2026-07-26'); // Pazar
        $this->assertFalse($calendar->isWorkingDay($sunday));

        // Pazartesi
        $monday = Carbon::parse('2026-07-27'); // Pazartesi
        $this->assertTrue($calendar->isWorkingDay($monday));
    }

    public function test_configured_holidays_are_excluded(): void
    {
        $calendar = app(HrCalendarService::class);

        // DB ile doğrudan tatil ekle (model global scope'u bypass)
        \Illuminate\Support\Facades\DB::table('hr_holidays')->insert([
            'legal_entity_id' => $this->tenant->id,
            'name' => 'Cumhuriyet Bayramı',
            'date' => '2026-10-29',
            'year' => 2026,
            'type' => 'national',
            'is_recurring' => true,
        ]);

        // DB doğrudan kontrol
        $count = \Illuminate\Support\Facades\DB::table('hr_holidays')
            ->where('legal_entity_id', $this->tenant->id)
            ->where('date', '2026-10-29')
            ->count();
        $this->assertGreaterThan(0, $count, 'Holiday should exist in database');

        $holiday = Carbon::parse('2026-10-29'); // Cuma
        $this->assertTrue($calendar->isHoliday($holiday), 'isHoliday should return true');
        $this->assertFalse($calendar->isWorkingDay($holiday), 'Holiday should not be a working day');
    }

    public function test_other_tenant_holiday_does_not_affect(): void
    {
        $userB = User::factory()->create(['role' => 'admin']);
        $tenantB = LegalEntity::create([
            'user_id' => $userB->id,
            'name' => 'Şirket B',
            'tax_number' => '3000000002',
            'is_active' => true,
        ]);

        // Tenant A'ya tatil ekle
        HrHoliday::create([
            'legal_entity_id' => $this->tenant->id,
            'name' => 'Özel Gün A',
            'date' => '2026-11-15',
            'year' => 2026,
            'type' => 'special',
            'is_recurring' => false,
        ]);

        // Tenant B'de aynı tarih tatil değil
        app(TenantContext::class)->set($tenantB);
        $calendar = app(HrCalendarService::class);

        $date = Carbon::parse('2026-11-15'); // Pazar
        // Pazar olduğu için zaten iş günü değil, ama tatil kontrolü de yapılmalı
        $this->assertFalse($calendar->isHoliday($date));
    }

    public function test_working_days_count_excludes_weekends_and_holidays(): void
    {
        $calendar = app(HrCalendarService::class);

        // Tatil ekle: 3 Ağustos 2026 Pazartesi
        \Illuminate\Support\Facades\DB::table('hr_holidays')->insert([
            'legal_entity_id' => $this->tenant->id,
            'name' => 'Özel Tatil',
            'date' => '2026-08-03',
            'year' => 2026,
            'type' => 'special',
            'is_recurring' => false,
        ]);

        // 27 Temmuz (Pazartesi) - 7 Ağustos (Cuma) = 10 iş günü - 1 tatil = 9
        $start = Carbon::parse('2026-07-27');
        $end = Carbon::parse('2026-08-07');

        $workingDays = $calendar->getWorkingDaysBetween($start, $end);
        $this->assertEquals(9, $workingDays);
    }

    public function test_action_uses_calendar_service(): void
    {
        $action = app(CalculateWorkingDaysAction::class);

        // Basit test: cumartesi-pazar arası 0 iş günü
        $start = Carbon::parse('2026-07-25'); // Cumartesi
        $end = Carbon::parse('2026-07-26'); // Pazar

        $result = $action->execute($start, $end);
        $this->assertEquals(0, $result);
    }

    // TODO: Yarım gün tatil desteği mevcut tasarımda desteklenmiyor.
    // hr_holidays tablosunda `half_day` alanı bulunmuyor.
    // Faz 1'de yarım gün tatil desteği eklenmeli.
}
