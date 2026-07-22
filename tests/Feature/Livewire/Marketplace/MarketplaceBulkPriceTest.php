<?php

namespace Tests\Feature\Livewire\Marketplace;

use App\Jobs\PushMarketplaceBulkPriceActionsJob;
use App\Models\MarketplaceStore;
use App\Models\MpPriceAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MarketplaceBulkPriceTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_price_actions_job_dispatches_individual_jobs(): void
    {
        config(['marketplace.trendyol.bulk_price_actions_enabled' => true]);

        $user = User::factory()->create(['role' => 'operator']);
        $store = MarketplaceStore::factory()->create(['user_id' => $user->id, 'marketplace' => 'trendyol']);

        $action1 = MpPriceAction::create([
            'store_id' => $store->id,
            'barcode' => 'BULK01',
            'old_price' => 100,
            'requested_price' => 90,
            'status' => 'pending',
        ]);

        $action2 = MpPriceAction::create([
            'store_id' => $store->id,
            'barcode' => 'BULK02',
            'old_price' => 200,
            'requested_price' => 180,
            'status' => 'pending',
        ]);

        Queue::fake();

        $bulkJob = new PushMarketplaceBulkPriceActionsJob([$action1->id, $action2->id]);
        $bulkJob->handle();

        Queue::assertPushed(\App\Jobs\PushMarketplacePriceActionJob::class, 2);
    }
}
