<?php

namespace Tests\Feature;

use App\Models\ReturnIntakeBatch;
use App\Models\ReturnIntakeItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReturnAutoDecisionPolicyCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_applies_internal_restock_decision_for_eligible_item(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $batch = ReturnIntakeBatch::create([
            'user_id' => $user->id,
            'source' => 'zolm_mobile',
            'intake_mode' => 'undamaged',
            'status' => 'submitted',
            'captured_at' => now(),
        ]);

        $item = ReturnIntakeItem::create([
            'batch_id' => $batch->id,
            'submitted_by_user_id' => $user->id,
            'intake_type' => 'undamaged',
            'intake_status' => 'ready_for_decision',
            'condition_status' => 'undamaged',
            'product_verification_status' => 'matched',
            'decision_status' => 'pending',
            'suggested_decision' => 'restock',
            'suggested_confidence' => 94,
            'suggestion_summary' => 'Urun saglam ve stoga uygun.',
            'arrived_at' => now(),
        ]);

        $this->artisan('returns:run-auto-policies', ['--item' => $item->id])
            ->expectsOutputToContain('Iade auto policy sonucu')
            ->assertSuccessful();

        $item->refresh();

        $this->assertSame('restocked', $item->decision_status);
        $this->assertSame('decisioned', $item->intake_status);
        $this->assertDatabaseHas('return_intake_decisions', [
            'return_intake_item_id' => $item->id,
            'decision' => 'restocked',
            'decision_mode' => 'automatic',
            'reason_code' => 'auto_policy_restock',
        ]);
    }
}
