<?php

namespace App\Services\Marketplace;

use App\Models\ChannelListing;
use App\Models\ChannelOrder;
use App\Models\ChannelProduct;
use App\Models\MarketplaceQuestion;
use App\Models\MarketplaceStore;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class MarketplaceQuestionSyncService
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $context
     * @return array{created: int, updated: int, skipped: int, impacted_order_ids: array<int, int>}
     */
    public function sync(MarketplaceStore $store, array $items, array $context = []): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($items as $item) {
            try {
                $normalized = $this->normalize($store, $item);

                if (!$normalized) {
                    $skipped++;
                    continue;
                }

                $question = MarketplaceQuestion::query()->firstOrNew([
                    'store_id' => $store->id,
                    'external_question_id' => $normalized['external_question_id'],
                ]);

                $wasRecentlyCreated = !$question->exists;

                $question->fill($normalized);
                $question->last_synced_at = now();
                $question->save();

                $wasRecentlyCreated ? $created++ : $updated++;

                $this->syncMessages($question, $item);

                if (config('customer-care.enabled', false)) {
                    try {
                        app(\App\Services\Support\SupportProjectionService::class)
                            ->projectQuestion($question->fresh(['store']));
                    } catch (Throwable $exception) {
                        Log::warning('Ürün sorusu AI Müşteri Merkezi projeksiyonu tamamlanamadı.', [
                            'store_id' => $store->id,
                            'marketplace_question_id' => $question->id,
                            'error' => $exception->getMessage(),
                        ]);
                    }
                }

                if ($wasRecentlyCreated && $this->shouldNotifyFreshQuestion($question, $context)) {
                    app(\App\Services\NotificationCenterService::class)->notifyQuestionReceived($question);
                }
            } catch (Throwable) {
                $skipped++;
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'impacted_order_ids' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function shouldNotifyFreshQuestion(MarketplaceQuestion $question, array $context): bool
    {
        if (in_array((string) ($context['trigger_type'] ?? ''), ['webhook', 'webhook_replay'], true)) {
            return true;
        }

        if (!$question->asked_at) {
            return true;
        }

        try {
            return CarbonImmutable::parse($question->asked_at)->greaterThanOrEqualTo(now()->subHours(24));
        } catch (Throwable) {
            return true;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function normalize(MarketplaceStore $store, array $item): ?array
    {
        $externalId = $this->firstFilled($item, [
            'external_question_id',
            'question_id',
            'questionId',
            'productQuestionId',
            'issueNumber',
            'issue_number',
            'thread_id',
            'threadId',
            'ticketId',
            'id',
            'message_id',
            'messageId',
            'review_id',
            'reviewId',
        ]);

        $questionText = $this->firstFilled($item, [
            'question_text',
            'questionText',
            'question',
            'text',
            'message',
            'content',
            'body',
            'description',
            'subject',
            'comment',
            'review',
            'lastMessage.body',
            'last_message.body',
        ]);

        if (blank($externalId) || blank($questionText)) {
            return null;
        }

        $sku = $this->firstFilled($item, ['product_sku', 'sku', 'stock_code', 'stockCode', 'merchantSku', 'merchant_sku']);
        $barcode = $this->firstFilled($item, ['product_barcode', 'barcode', 'gtin']);
        $externalProductId = $this->firstFilled($item, ['external_product_id', 'product_id', 'productId', 'product.id', 'productMainId', 'post']);
        $listingId = $this->firstFilled($item, ['listing_id', 'listingId', 'offerId', 'offer_id', 'listing.id']);
        $orderNumber = $this->firstFilled($item, ['order_number', 'orderNumber', 'external_order_id', 'packageNumber', 'orderNo', 'order.id']);

        $channelProduct = $this->matchChannelProduct($store, $sku, $barcode, $externalProductId);
        $channelListing = $this->matchChannelListing($store, $listingId, $sku, $barcode, $channelProduct?->id);
        $channelOrder = $this->matchChannelOrder($store, $orderNumber);

        $normalized = [
            'store_id' => $store->id,
            'channel_product_id' => $channelProduct?->id,
            'channel_listing_id' => $channelListing?->id,
            'channel_order_id' => $channelOrder?->id,
            'external_question_id' => (string) $externalId,
            'question_type' => (string) ($this->firstFilled($item, ['question_type', 'type']) ?: 'product'),
            'status' => $this->normalizeStatus((string) ($this->firstFilled($item, ['status', 'state', 'questionStatus', 'issueStatus']) ?: 'open')),
            'customer_name' => $this->firstFilled($item, ['customer_name', 'customerName', 'customer.fullName', 'customer.name', 'buyerName', 'buyer.name', 'reviewer', 'author_name']),
            'customer_external_id' => $this->firstFilled($item, ['customer_external_id', 'customerId', 'customer.id', 'buyerId', 'buyer.id', 'reviewer_email', 'author_email']),
            'product_name' => $this->firstFilled($item, ['product_name', 'productName', 'product.name', 'product.title', 'productTitle', 'product_title', 'title', 'name']) ?: $channelProduct?->title,
            'product_sku' => $sku ?: $channelProduct?->stock_code,
            'product_barcode' => $barcode ?: $channelProduct?->barcode,
            'product_url' => $this->firstFilled($item, ['product_url', 'productUrl', 'product.url', 'url', 'permalink', 'link']),
            'question_text' => (string) $questionText,
            'asked_at' => $this->parseDate($this->firstFilled($item, ['asked_at', 'created_at', 'createdAt', 'creationDate', 'createdDate', 'questionDate', 'date', 'date_created', 'date_created_gmt', 'lastMessageDate'])),
            'expires_at' => $this->parseDate($this->firstFilled($item, ['expires_at', 'expireDate', 'expirationDate', 'deadline', 'dueDate'])),
            'raw_payload' => $item,
        ];

        $answerText = $this->firstFilled($item, ['answer_text', 'answerText', 'answer', 'answer.text', 'answer.body', 'sellerAnswer']);
        if (filled($answerText)) {
            $normalized['answer_text'] = $answerText;
            $normalized['answered_at'] = $this->parseDate($this->firstFilled($item, ['answered_at', 'answeredDate', 'answerDate', 'answer.createdAt', 'answer.creationDate']));
            if ($normalized['status'] === 'open') {
                $normalized['status'] = 'answered';
            }
        }

        return $normalized;
    }

    protected function syncMessages(MarketplaceQuestion $question, array $item): void
    {
        $messages = Arr::get($item, 'messages');

        if (!is_array($messages) || $messages === []) {
            $messages = [[
                'external_message_id' => $this->firstFilled($item, ['message_id', 'messageId', 'id']),
                'direction' => 'customer',
                'body' => $question->question_text,
                'sent_at' => $question->asked_at,
                'raw_payload' => $item,
            ]];
        }

        foreach ($messages as $message) {
            if (!is_array($message)) {
                continue;
            }

            $body = $this->firstFilled($message, ['body', 'message', 'text', 'content', 'comment', 'answer']);

            if (blank($body)) {
                continue;
            }

            $externalMessageId = $this->firstFilled($message, ['external_message_id', 'message_id', 'messageId', 'id']);

            $question->messages()->updateOrCreate([
                'external_message_id' => $externalMessageId ? (string) $externalMessageId : null,
                'body' => (string) $body,
            ], [
                'direction' => (string) ($this->firstFilled($message, ['direction', 'sender_type', 'senderType']) ?: 'customer'),
                'attachments_json' => Arr::get($message, 'attachments'),
                'sent_at' => $this->parseDate($this->firstFilled($message, ['sent_at', 'created_at', 'createdAt', 'createdDate', 'date'])) ?: $question->asked_at,
                'raw_payload' => $message,
            ]);
        }
    }

    protected function matchChannelProduct(MarketplaceStore $store, mixed $sku, mixed $barcode, mixed $externalProductId): ?ChannelProduct
    {
        return ChannelProduct::query()
            ->where('store_id', $store->id)
            ->when(filled($externalProductId), fn ($query) => $query->where('external_product_id', (string) $externalProductId))
            ->when(blank($externalProductId) && filled($sku), fn ($query) => $query->where('stock_code', (string) $sku))
            ->when(blank($externalProductId) && blank($sku) && filled($barcode), fn ($query) => $query->where('barcode', (string) $barcode))
            ->first();
    }

    protected function matchChannelListing(MarketplaceStore $store, mixed $listingId, mixed $sku, mixed $barcode, ?int $channelProductId): ?ChannelListing
    {
        return ChannelListing::query()
            ->where('store_id', $store->id)
            ->when(filled($listingId), fn ($query) => $query->where('listing_id', (string) $listingId))
            ->when(blank($listingId) && $channelProductId, fn ($query) => $query->where('channel_product_id', $channelProductId))
            ->when(blank($listingId) && !$channelProductId && filled($sku), function ($query) use ($sku) {
                $query->whereHas('channelProduct', fn ($productQuery) => $productQuery->where('stock_code', (string) $sku));
            })
            ->when(blank($listingId) && !$channelProductId && blank($sku) && filled($barcode), function ($query) use ($barcode) {
                $query->whereHas('channelProduct', fn ($productQuery) => $productQuery->where('barcode', (string) $barcode));
            })
            ->first();
    }

    protected function matchChannelOrder(MarketplaceStore $store, mixed $orderNumber): ?ChannelOrder
    {
        if (blank($orderNumber)) {
            return null;
        }

        return ChannelOrder::query()
            ->where('store_id', $store->id)
            ->where(function ($query) use ($orderNumber) {
                $query->where('order_number', (string) $orderNumber)
                    ->orWhere('external_order_id', (string) $orderNumber);
            })
            ->first();
    }

    protected function normalizeStatus(string $status): string
    {
        $normalized = Str::of($status)->lower()->ascii()->replace([' ', '-'], '_')->value();

        return match ($normalized) {
            'answered', 'answered_by_seller', 'closed', 'closed_by_seller', 'resolved', 'cevaplandi', 'cevaplanmis' => 'answered',
            'rejected', 'cancelled', 'canceled', 'closed_without_answer', 'expired', 'spam' => 'closed',
            'draft', 'taslak' => 'draft',
            default => 'open',
        };
    }

    protected function parseDate(mixed $value): ?CarbonImmutable
    {
        if (blank($value)) {
            return null;
        }

        try {
            if (is_numeric($value)) {
                $timestamp = (int) $value;

                if (abs($timestamp) > 9999999999) {
                    return CarbonImmutable::createFromTimestampMs($timestamp);
                }

                return CarbonImmutable::createFromTimestampUTC($timestamp);
            }

            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<int, string>  $keys
     */
    protected function firstFilled(array $item, array $keys): mixed
    {
        foreach ($keys as $key) {
            $value = str_contains($key, '.') ? data_get($item, $key) : ($item[$key] ?? null);

            if (filled($value)) {
                return $value;
            }
        }

        return null;
    }
}
