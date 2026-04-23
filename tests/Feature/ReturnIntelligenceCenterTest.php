<?php

namespace Tests\Feature;

use App\Livewire\Returns\ReturnIntelligenceCenter;
use App\Models\ReturnIntakeBatch;
use App\Models\ReturnIntakeItem;
use App\Models\ReturnIntakeMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReturnIntelligenceCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_records_internal_decisions_from_review_screen(): void
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
            'decision_status' => 'pending',
            'suggested_decision' => 'restock',
            'suggested_confidence' => 86,
            'suggestion_summary' => 'Ürün sağlam ve stok için uygun.',
            'arrived_at' => now(),
        ]);

        $this->actingAs($user);

        Livewire::test(ReturnIntelligenceCenter::class)
            ->call('selectItem', $item->id)
            ->set('decisionNote', 'Ürün sağlam şekilde stoklandı.')
            ->call('markRestocked')
            ->assertSet('messageType', 'success');

        $item->refresh();

        $this->assertSame('restocked', $item->decision_status);
        $this->assertSame('decisioned', $item->intake_status);
        $this->assertDatabaseHas('return_intake_decisions', [
            'return_intake_item_id' => $item->id,
            'decision' => 'restocked',
            'reason_code' => 'restock',
            'note' => 'Ürün sağlam şekilde stoklandı.',
        ]);
    }

    public function test_it_can_run_auto_policies_from_review_screen(): void
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
            'suggested_confidence' => 95,
            'suggestion_summary' => 'Urun saglam ve stoga uygun.',
            'arrived_at' => now(),
        ]);

        $this->actingAs($user);

        Livewire::test(ReturnIntelligenceCenter::class)
            ->call('runAutoPolicies')
            ->assertSet('messageType', 'success');

        $item->refresh();

        $this->assertSame('restocked', $item->decision_status);
    }

    public function test_it_dispatches_selection_event_when_reviewing_an_item(): void
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
            'intake_status' => 'needs_review',
            'condition_status' => 'undamaged',
            'decision_status' => 'pending',
            'arrived_at' => now(),
        ]);

        $this->actingAs($user);

        Livewire::test(ReturnIntelligenceCenter::class)
            ->call('selectItem', $item->id)
            ->assertSet('selectedItemId', $item->id)
            ->assertDispatched('return-item-selected');
    }

    public function test_it_shows_multi_label_uploads_as_a_list_in_detail_panel(): void
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
            'intake_status' => 'needs_review',
            'condition_status' => 'undamaged',
            'decision_status' => 'pending',
            'detected_tracking_number' => '2331491831317',
            'arrived_at' => now(),
        ]);

        foreach (range(1, 3) as $index) {
            ReturnIntakeMedia::create([
                'return_intake_item_id' => $item->id,
                'kind' => 'label',
                'disk' => 'public',
                'path' => "returns/test/label-{$index}.jpg",
                'thumbnail_path' => "returns/test/thumb-label-{$index}.jpg",
                'mime_type' => 'image/jpeg',
                'extension' => 'jpg',
                'size_bytes' => 1024,
                'original_size_bytes' => 2048,
            ]);
        }

        $this->actingAs($user);

        Livewire::test(ReturnIntelligenceCenter::class)
            ->assertSee('3 etiket yüklendi')
            ->assertSee('Toplu yükleme listesi')
            ->assertSee('Etiket 1')
            ->assertSee('Etiket 2')
            ->assertSee('Etiket 3');
    }
}
