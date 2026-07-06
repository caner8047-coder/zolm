<?php

namespace App\Services\WhatsApp;

use App\Models\WaContact;
use App\Models\WaOutbox;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OutboxService
{
    public function enqueue(
        WaContact $contact,
        string $messageType,
        ?string $templateName = null,
        ?string $templateLanguage = null,
        ?array $templateParams = null,
        ?string $bodyText = null,
        string $priority = 'normal',
        ?string $automationKey = null,
        ?int $relatedOrderId = null,
        ?int $relatedCartId = null,
        ?string $scheduledAt = null,
        ?string $idempotencyKey = null,
    ): WaOutbox {
        if (! $contact->store()->where('marketplace', 'woocommerce')->where('is_active', true)->exists()) {
            throw new RuntimeException('WhatsApp mesajları yalnızca aktif WooCommerce mağazaları için kuyruğa alınabilir.');
        }

        $idempotencyKey ??= $this->buildIdempotencyKey($automationKey, $contact->store_id, $contact->id, $templateName);

        // Unique constraint sayesinde duplicate insert hata verir
        return WaOutbox::create([
            'contact_id' => $contact->id,
            'store_id' => $contact->store_id,
            'idempotency_key' => $idempotencyKey,
            'message_type' => $messageType,
            'template_name' => $templateName,
            'template_language' => $templateLanguage,
            'template_params_json' => $templateParams,
            'body_text' => $bodyText,
            'priority' => $priority,
            'status' => WaOutbox::STATUS_QUEUED,
            'scheduled_at' => $scheduledAt,
            'automation_key' => $automationKey,
            'related_order_id' => $relatedOrderId,
            'related_cart_id' => $relatedCartId,
        ]);
    }

    public function buildIdempotencyKey(
        ?string $automationKey,
        int|string $storeId,
        int|string $contactId,
        ?string $suffix = null,
    ): string {
        $parts = array_filter([
            $automationKey,
            $storeId,
            $contactId,
            $suffix,
        ]);

        return implode(':', $parts);
    }

    public function claimForProcessing(WaOutbox $outbox): bool
    {
        $updated = DB::table('wa_outbox')
            ->where('id', $outbox->id)
            ->where('status', WaOutbox::STATUS_QUEUED)
            ->update([
                'status' => WaOutbox::STATUS_PROCESSING,
                'updated_at' => now(),
            ]);

        return $updated > 0;
    }

    public function markSent(WaOutbox $outbox, string $metaMessageId): void
    {
        $outbox->update([
            'status' => WaOutbox::STATUS_SENT,
            'meta_message_id' => $metaMessageId,
            'error_message' => null,
            'error_code' => null,
        ]);
    }

    public function markFailed(WaOutbox $outbox, string $errorMessage, ?string $errorCode = null, bool $shouldRetry = true): void
    {
        $retryCount = $outbox->retry_count + 1;
        $maxRetries = $outbox->max_retries;

        if ($shouldRetry && $retryCount < $maxRetries) {
            $backoffSeconds = match ($retryCount) {
                1 => 60,
                2 => 300,
                default => 900,
            };

            $outbox->update([
                'status' => WaOutbox::STATUS_QUEUED,
                'retry_count' => $retryCount,
                'next_retry_at' => now()->addSeconds($backoffSeconds),
                'error_message' => $errorMessage,
                'error_code' => $errorCode,
            ]);
        } else {
            $outbox->update([
                'status' => WaOutbox::STATUS_FAILED,
                'retry_count' => $retryCount,
                'error_message' => $errorMessage,
                'error_code' => $errorCode,
            ]);
        }
    }

    public function updateDeliveryStatus(WaOutbox $outbox, string $newStatus): void
    {
        if (!$outbox->canProgressTo($newStatus)) {
            return;
        }

        $outbox->update(['status' => $newStatus]);
    }
}
