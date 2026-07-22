<?php

namespace Tests\Feature;

use App\Jobs\SyncMarketplaceBuyboxJob;
use App\Jobs\SyncMarketplaceCargoInvoiceJob;
use App\Jobs\SyncMarketplaceReferenceJob;
use App\Models\IntegrationConnection;
use App\Models\MarketplaceStore;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MarketplaceOperationalSchedulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_trendyol_schedules_dispatch_configured_store_and_skip_inactive_store(): void
    {
        Queue::fake();

        $configuredStore = MarketplaceStore::factory()->create(['seller_id' => 'schedule-configured']);
        IntegrationConnection::factory()->create([
            'store_id' => $configuredStore->id,
            'status' => 'configured',
        ]);

        $inactiveStore = MarketplaceStore::factory()->create(['seller_id' => 'schedule-inactive']);
        IntegrationConnection::factory()->create([
            'store_id' => $inactiveStore->id,
            'status' => 'inactive',
        ]);

        $scheduledJobs = [
            'marketplace-sync-buybox' => SyncMarketplaceBuyboxJob::class,
            'marketplace-sync-references' => SyncMarketplaceReferenceJob::class,
            'marketplace-sync-cargo-invoices' => SyncMarketplaceCargoInvoiceJob::class,
        ];
        $events = collect(app(Schedule::class)->events())->keyBy('description');

        foreach ($scheduledJobs as $eventName => $jobClass) {
            $event = $events->get($eventName);
            $this->assertNotNull($event, $eventName.' zamanlayıcısı kayıtlı değil.');

            $event->run(app());

            Queue::assertPushed(
                $jobClass,
                fn ($job): bool => $job->store->is($configuredStore)
            );
            Queue::assertNotPushed(
                $jobClass,
                fn ($job): bool => $job->store->is($inactiveStore)
            );
        }
    }
}
