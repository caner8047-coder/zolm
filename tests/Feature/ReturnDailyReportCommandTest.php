<?php

namespace Tests\Feature;

use App\Models\ReturnIntakeBatch;
use App\Models\ReturnIntakeItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReturnDailyReportCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_and_persists_daily_return_report(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'name' => 'Ramazan Depocu',
        ]);

        $batch = ReturnIntakeBatch::create([
            'user_id' => $user->id,
            'source' => 'zolm_mobile',
            'intake_mode' => 'damaged',
            'status' => 'submitted',
            'captured_at' => now(),
        ]);

        ReturnIntakeItem::create([
            'batch_id' => $batch->id,
            'submitted_by_user_id' => $user->id,
            'intake_type' => 'damaged',
            'intake_status' => 'decisioned',
            'condition_status' => 'damaged',
            'decision_status' => 'scrapped',
            'arrived_at' => now(),
        ]);

        $this->artisan('returns:daily-report', ['--persist' => true])
            ->expectsOutputToContain('Gunluk iade raporu')
            ->expectsOutputToContain('Hasar orani')
            ->assertSuccessful();

        $this->assertDatabaseHas('return_daily_reports', [
            'report_date' => today()->startOfDay()->format('Y-m-d H:i:s'),
        ]);
    }
}
