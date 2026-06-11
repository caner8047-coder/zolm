<?php

namespace App\Services\Marketplace\Connectors\Concerns;

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

trait NormalizesCustomerQuestions
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function normalizeQuestionPayload(array $payload, array $overrides = []): array
    {
        $questionText = $this->questionTextValue($this->firstQuestionValue($payload, [
            'question_text',
            'questionText',
            'question',
            'text',
            'message',
            'content',
            'body',
            'description',
            'subject',
            'title',
            'comment',
            'review',
            'lastMessage.body',
            'last_message.body',
            'messages.0.body',
            'messages.0.message',
            'messages.0.text',
        ]));

        $answerText = $this->questionTextValue($this->firstQuestionValue($payload, [
            'answer_text',
            'answerText',
            'answer',
            'answer.text',
            'answer.body',
            'sellerAnswer',
            'seller_answer',
            'response',
        ]));

        $question = array_filter([
            'external_question_id' => (string) $this->firstQuestionValue($payload, [
                'external_question_id',
                'question_id',
                'questionId',
                'productQuestionId',
                'issueNumber',
                'issue_number',
                'thread_id',
                'threadId',
                'ticketId',
                'message_id',
                'messageId',
                'review_id',
                'reviewId',
                'id',
            ]),
            'question_type' => (string) ($this->firstQuestionValue($payload, [
                'question_type',
                'questionType',
                'issueType',
                'type',
                'kind',
            ]) ?: 'product'),
            'status' => $this->normalizeQuestionStatus((string) ($this->firstQuestionValue($payload, [
                'status',
                'state',
                'questionStatus',
                'issueStatus',
                'statusText',
                'status.text',
            ]) ?: 'open')),
            'customer_name' => $this->firstQuestionValue($payload, [
                'customer_name',
                'customerName',
                'customer.fullName',
                'customer.name',
                'buyerName',
                'buyer.name',
                'userName',
                'reviewer',
                'author_name',
            ]),
            'customer_external_id' => $this->firstQuestionValue($payload, [
                'customer_external_id',
                'customerId',
                'customer.id',
                'buyerId',
                'buyer.id',
                'reviewer_email',
                'author_email',
            ]),
            'product_name' => $this->firstQuestionValue($payload, [
                'product_name',
                'productName',
                'product.name',
                'product.title',
                'productTitle',
                'product_title',
                'productDescription',
                'product.description',
                'name',
            ]),
            'product_sku' => $this->firstQuestionValue($payload, [
                'product_sku',
                'sku',
                'stock_code',
                'stockCode',
                'merchantSku',
                'merchant_sku',
                'merchantSKU',
                'shop_sku',
                'offer_sku',
                'product.sku',
                'product.stockCode',
                'product.merchantSku',
            ]),
            'product_barcode' => $this->firstQuestionValue($payload, [
                'product_barcode',
                'barcode',
                'gtin',
                'product.barcode',
                'product.gtin',
            ]),
            'external_product_id' => $this->firstQuestionValue($payload, [
                'external_product_id',
                'product_id',
                'productId',
                'product.id',
                'productSku',
                'productMainId',
                'post',
            ]),
            'listing_id' => $this->firstQuestionValue($payload, [
                'listing_id',
                'listingId',
                'offerId',
                'offer_id',
                'listing.id',
                'product.offerId',
                'product.listingId',
            ]),
            'product_url' => $this->firstQuestionValue($payload, [
                'product_url',
                'productUrl',
                'product.url',
                'url',
                'permalink',
                'link',
            ]),
            'order_number' => $this->firstQuestionValue($payload, [
                'order_number',
                'orderNumber',
                'external_order_id',
                'packageNumber',
                'order.id',
                'orderNo',
            ]),
            'question_text' => $questionText,
            'answer_text' => $answerText,
            'asked_at' => $this->questionDate($this->firstQuestionValue($payload, [
                'asked_at',
                'created_at',
                'createdAt',
                'creationDate',
                'createdDate',
                'questionDate',
                'date',
                'date_created',
                'date_created_gmt',
                'lastMessageDate',
                'last_message_date',
            ])),
            'answered_at' => $this->questionDate($this->firstQuestionValue($payload, [
                'answered_at',
                'answeredDate',
                'answerDate',
                'answer.creationDate',
                'answer.createdAt',
                'updated_at',
                'updatedAt',
            ])),
            'expires_at' => $this->questionDate($this->firstQuestionValue($payload, [
                'expires_at',
                'expireDate',
                'expirationDate',
                'deadline',
                'dueDate',
            ])),
        ], fn ($value) => $value !== null && $value !== '');

        $cleanOverrides = array_filter($overrides, fn ($value) => $value !== null && $value !== '');

        return array_replace($question, $cleanOverrides, [
            'raw_payload' => $payload,
            'messages' => $this->normalizeQuestionMessages($payload, $questionText, $answerText),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $paths
     * @return array<int, array<string, mixed>>
     */
    protected function questionRowsFromPayload(array $payload, array $paths = []): array
    {
        foreach (array_merge($paths, [
            'items',
            'content',
            'data',
            'questions',
            'issues',
            'threads',
            'reviews',
            'comments',
            'productQuestions.productQuestion',
            'productQuestionList.productQuestion',
            'result.items',
            'result.data',
        ]) as $path) {
            $candidate = data_get($payload, $path);
            $rows = $this->coerceQuestionRows($candidate);

            if ($rows !== []) {
                return $rows;
            }
        }

        return $this->coerceQuestionRows($payload);
    }

    /**
     * @param  array<int|string, mixed>|mixed  $candidate
     * @return array<int, array<string, mixed>>
     */
    protected function coerceQuestionRows(mixed $candidate): array
    {
        if (!is_array($candidate) || $candidate === []) {
            return [];
        }

        if (!array_is_list($candidate)) {
            if ($this->looksLikeQuestionRow($candidate)) {
                return [$candidate];
            }

            return [];
        }

        return collect($candidate)
            ->filter(fn ($row) => is_array($row))
            ->map(fn (array $row) => $row)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function looksLikeQuestionRow(array $payload): bool
    {
        foreach ([
            'external_question_id',
            'question_id',
            'questionId',
            'productQuestionId',
            'issueNumber',
            'thread_id',
            'threadId',
            'review_id',
            'id',
            'question',
            'questionText',
            'message',
            'comment',
            'review',
        ] as $key) {
            if (filled(data_get($payload, $key))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $keys
     */
    protected function firstQuestionValue(array $payload, array $keys): mixed
    {
        foreach ($keys as $key) {
            $value = data_get($payload, $key);

            if (filled($value)) {
                return $value;
            }
        }

        return null;
    }

    protected function questionTextValue(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        if (is_array($value)) {
            $value = data_get($value, 'text')
                ?: data_get($value, 'body')
                ?: data_get($value, 'message')
                ?: data_get($value, 'content');
        }

        if (blank($value)) {
            return null;
        }

        return trim(strip_tags(html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    protected function normalizeQuestionStatus(string $status): string
    {
        $normalized = Str::of($status)->lower()->ascii()->replace([' ', '-'], '_')->value();

        return match ($normalized) {
            'answered', 'answered_by_seller', 'closed', 'closed_by_seller', 'resolved', 'cevaplandi', 'cevaplanmis' => 'answered',
            'rejected', 'cancelled', 'canceled', 'closed_without_answer', 'expired', 'spam' => 'closed',
            'draft', 'taslak' => 'draft',
            default => 'open',
        };
    }

    protected function questionDate(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            if (is_numeric($value)) {
                $timestamp = (int) $value;

                if (abs($timestamp) > 9999999999) {
                    return CarbonImmutable::createFromTimestampMs($timestamp)->toIso8601String();
                }

                return CarbonImmutable::createFromTimestampUTC($timestamp)->toIso8601String();
            }

            return CarbonImmutable::parse((string) $value)->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeQuestionMessages(array $payload, ?string $questionText = null, ?string $answerText = null): array
    {
        $messages = $this->coerceQuestionRows(Arr::wrap(
            data_get($payload, 'messages')
            ?: data_get($payload, 'thread.messages')
            ?: data_get($payload, 'conversation')
            ?: []
        ));

        $normalized = collect($messages)
            ->map(function (array $message) {
                $body = $this->questionTextValue($this->firstQuestionValue($message, [
                    'body',
                    'message',
                    'text',
                    'content',
                    'comment',
                    'answer',
                ]));

                if (blank($body)) {
                    return null;
                }

                $sender = Str::of((string) ($this->firstQuestionValue($message, [
                    'direction',
                    'sender_type',
                    'senderType',
                    'author.type',
                    'from.type',
                    'from',
                    'sender',
                    'userType',
                ]) ?: 'customer'))->lower()->ascii()->value();

                $direction = Str::contains($sender, ['seller', 'merchant', 'satici', 'store', 'agent', 'admin', 'operator'])
                    ? 'seller'
                    : 'customer';

                return [
                    'external_message_id' => $this->firstQuestionValue($message, [
                        'external_message_id',
                        'message_id',
                        'messageId',
                        'id',
                    ]),
                    'direction' => $direction,
                    'body' => $body,
                    'sent_at' => $this->questionDate($this->firstQuestionValue($message, [
                        'sent_at',
                        'created_at',
                        'createdAt',
                        'createdDate',
                        'date',
                    ])),
                    'attachments' => data_get($message, 'attachments') ?: data_get($message, 'files'),
                    'raw_payload' => $message,
                ];
            })
            ->filter()
            ->values()
            ->all();

        if ($normalized === [] && filled($questionText)) {
            $normalized[] = [
                'external_message_id' => $this->firstQuestionValue($payload, [
                    'message_id',
                    'messageId',
                    'question_id',
                    'questionId',
                    'issueNumber',
                    'thread_id',
                    'id',
                ]),
                'direction' => 'customer',
                'body' => $questionText,
                'sent_at' => $this->questionDate($this->firstQuestionValue($payload, [
                    'asked_at',
                    'created_at',
                    'createdAt',
                    'creationDate',
                    'createdDate',
                    'questionDate',
                    'date',
                ])),
                'raw_payload' => $payload,
            ];
        }

        if (filled($answerText)) {
            $normalized[] = [
                'external_message_id' => $this->firstQuestionValue($payload, [
                    'answer_id',
                    'answerId',
                    'answer.id',
                ]),
                'direction' => 'seller',
                'body' => $answerText,
                'sent_at' => $this->questionDate($this->firstQuestionValue($payload, [
                    'answered_at',
                    'answeredDate',
                    'answerDate',
                    'answer.createdAt',
                    'answer.creationDate',
                ])),
                'raw_payload' => data_get($payload, 'answer') ?: $payload,
            ];
        }

        return $normalized;
    }
}
