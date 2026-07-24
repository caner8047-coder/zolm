<?php

namespace Tests\Feature\WhatsApp;

use App\Jobs\WhatsApp\RetryFailedMessageJob;
use App\Jobs\WhatsApp\SendWaMessageJob;
use App\Models\WaOutbox;
use App\Services\WhatsApp\OutboxService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class RetryFailedMessageJobTest extends WhatsAppTestCase
{
    public function test_it_requeues_retryable_failed_and_stale_processing_messages_only(): void
    {
        Queue::fake();

        $store = $this->createStore();
        $contact = $this->createContact($store);

        $retryableFailed = $this->createOutbox($store->id, $contact->id, 'retryable-failed', [
            'status' => WaOutbox::STATUS_FAILED,
            'retry_count' => 1,
            'max_retries' => 3,
            'next_retry_at' => now()->subMinute(),
        ]);

        $staleProcessing = $this->createOutbox($store->id, $contact->id, 'stale-processing', [
            'status' => WaOutbox::STATUS_PROCESSING,
            'retry_count' => 0,
            'max_retries' => 3,
        ]);
        DB::table('wa_outbox')->where('id', $staleProcessing->id)->update([
            'updated_at' => now()->subMinutes(10),
        ]);

        $exhausted = $this->createOutbox($store->id, $contact->id, 'exhausted', [
            'status' => WaOutbox::STATUS_FAILED,
            'retry_count' => 3,
            'max_retries' => 3,
        ]);

        $freshProcessing = $this->createOutbox($store->id, $contact->id, 'fresh-processing', [
            'status' => WaOutbox::STATUS_PROCESSING,
            'retry_count' => 0,
            'max_retries' => 3,
        ]);

        (new RetryFailedMessageJob())->handle(app(OutboxService::class));

        $this->assertSame(WaOutbox::STATUS_QUEUED, $retryableFailed->fresh()->status);
        $this->assertSame(WaOutbox::STATUS_QUEUED, $staleProcessing->fresh()->status);
        $this->assertSame(WaOutbox::STATUS_FAILED, $exhausted->fresh()->status);
        $this->assertSame(WaOutbox::STATUS_PROCESSING, $freshProcessing->fresh()->status);

        Queue::assertPushed(SendWaMessageJob::class, 2);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createOutbox(int $storeId, int $contactId, string $key, array $overrides): WaOutbox
    {
        return WaOutbox::query()->create(array_merge([
            'contact_id' => $contactId,
            'store_id' => $storeId,
            'idempotency_key' => $key,
            'message_type' => 'text',
            'body_text' => 'Test mesajı',
            'status' => WaOutbox::STATUS_QUEUED,
            'retry_count' => 0,
            'max_retries' => 3,
        ], $overrides));
    }
}
