<?php

namespace Tests\Unit;

use App\Jobs\SyncMarketplaceBuyboxJob;
use App\Jobs\TrackMarketplaceBatchRequestsJob;
use App\Jobs\WhatsApp\ProcessCartRecoveryJob;
use App\Jobs\WhatsApp\RetryFailedMessageJob;
use App\Models\MarketplaceStore;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Queue\Events\QueueBusy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class QueueSafetyConfigurationTest extends TestCase
{
    public function test_recurring_jobs_are_unique_and_routed_to_dedicated_queues(): void
    {
        config()->set('marketplace.queues.maintenance', 'marketplace-maintenance');
        config()->set('whatsapp.queue.outbox', 'whatsapp');

        $store = new MarketplaceStore();
        $store->setAttribute('id', 321);

        $buybox = new SyncMarketplaceBuyboxJob($store);
        $batch = new TrackMarketplaceBatchRequestsJob();
        $retry = new RetryFailedMessageJob();
        $cartRecovery = new ProcessCartRecoveryJob();

        foreach ([$buybox, $batch, $retry, $cartRecovery] as $job) {
            $this->assertInstanceOf(ShouldBeUnique::class, $job);
            $this->assertSame(86400, $job->uniqueFor);
        }

        $this->assertSame('marketplace-buybox:321', $buybox->uniqueId());
        $this->assertSame('marketplace-track-batch-requests', $batch->uniqueId());
        $this->assertSame('whatsapp-retry-failed', $retry->uniqueId());
        $this->assertSame('whatsapp-process-cart-recovery', $cartRecovery->uniqueId());

        $this->assertSame('marketplace-maintenance', $buybox->queue);
        $this->assertSame('marketplace-maintenance', $batch->queue);
        $this->assertSame('whatsapp', $retry->queue);
        $this->assertSame('whatsapp', $cartRecovery->queue);
    }

    public function test_database_queue_retry_window_exceeds_longest_job_timeout(): void
    {
        $this->assertGreaterThan(
            1800,
            (int) config('queue.connections.database.retry_after')
        );
    }

    public function test_compose_defines_dedicated_restartable_workers(): void
    {
        $compose = file_get_contents(base_path('compose.yaml'));

        $this->assertIsString($compose);
        $this->assertStringContainsString('queue-marketplace:', $compose);
        $this->assertStringContainsString('queue-default:', $compose);
        $this->assertSame(2, substr_count($compose, 'php artisan queue:work database'));
        $this->assertGreaterThanOrEqual(3, substr_count($compose, 'restart: unless-stopped'));
    }

    public function test_queue_monitor_schedule_and_critical_log_listener_are_registered(): void
    {
        $events = collect(app(Schedule::class)->events())->keyBy('description');

        $this->assertNotNull($events->get('queue-monitor-zolm'));

        Log::spy();
        Event::dispatch(new QueueBusy('database', 'marketplace-sync', 101));

        Log::shouldHaveReceived('critical')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === '[QueueHealth] Kuyruk eşiği aşıldı.'
                    && $context['queue'] === 'marketplace-sync'
                    && $context['size'] === 101;
            });
    }
}
