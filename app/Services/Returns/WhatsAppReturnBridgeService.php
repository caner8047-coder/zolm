<?php

namespace App\Services\Returns;

use App\Jobs\AnalyzeReturnIntakeItemJob;
use App\Models\ReturnIntakeBatch;
use App\Models\ReturnIntakeItem;
use App\Models\ReturnIntakeMedia;
use App\Models\ReturnWhatsappMessage;
use App\Models\ReturnWhatsappThread;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WhatsAppReturnBridgeService
{
    public function __construct(
        protected WhatsAppCloudApiService $cloudApiService,
        protected ReturnMediaOptimizationService $mediaOptimizationService,
        protected ReturnBridgeSettingsService $settingsService,
        protected string $disk = '',
        protected string $basePath = '',
    ) {
        $this->disk = $this->disk !== '' ? $this->disk : (string) config('returns.storage_disk', 'public');
        $this->basePath = $this->basePath !== '' ? trim($this->basePath, '/') : trim((string) config('returns.storage_path', 'returns'), '/');
    }

    public function verifySignature(Request $request): bool
    {
        $secret = trim((string) $this->settingsService->get('app_secret', ''));

        if ($secret === '') {
            return true;
        }

        $provided = trim((string) $request->header('X-Hub-Signature-256'));

        if ($provided === '') {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $provided);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, int>
     */
    public function handle(array $payload): array
    {
        $summary = [
            'messages' => 0,
            'threads' => 0,
            'media' => 0,
            'dispatched' => 0,
            'duplicates' => 0,
        ];

        foreach (Arr::wrap(data_get($payload, 'entry', [])) as $entry) {
            foreach (Arr::wrap(data_get($entry, 'changes', [])) as $change) {
                if ((string) data_get($change, 'field') !== 'messages') {
                    continue;
                }

                $value = data_get($change, 'value', []);
                $contacts = collect(Arr::wrap(data_get($value, 'contacts', [])))->keyBy(fn ($contact) => (string) data_get($contact, 'wa_id'));
                $metadata = data_get($value, 'metadata', []);

                foreach (Arr::wrap(data_get($value, 'messages', [])) as $message) {
                    $result = $this->ingestMessage($message, $contacts->all(), is_array($metadata) ? $metadata : []);

                    $summary['messages']++;
                    $summary['threads'] += $result['thread_created'] ? 1 : 0;
                    $summary['media'] += $result['media_imported'] ? 1 : 0;
                    $summary['dispatched'] += $result['analysis_dispatched'] ? 1 : 0;
                    $summary['duplicates'] += $result['duplicate'] ? 1 : 0;
                }
            }
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $message
     * @param  array<string, mixed>  $contacts
     * @param  array<string, mixed>  $metadata
     * @return array{thread_created: bool, media_imported: bool, analysis_dispatched: bool, duplicate: bool}
     */
    protected function ingestMessage(array $message, array $contacts, array $metadata): array
    {
        $externalMessageId = trim((string) data_get($message, 'id'));

        if ($externalMessageId === '') {
            return [
                'thread_created' => false,
                'media_imported' => false,
                'analysis_dispatched' => false,
                'duplicate' => false,
            ];
        }

        if (ReturnWhatsappMessage::query()->where('external_message_id', $externalMessageId)->exists()) {
            return [
                'thread_created' => false,
                'media_imported' => false,
                'analysis_dispatched' => false,
                'duplicate' => true,
            ];
        }

        $senderPhone = trim((string) data_get($message, 'from'));
        $senderName = trim((string) data_get($contacts, $senderPhone . '.profile.name', '')) ?: trim((string) data_get($metadata, 'display_phone_number', ''));
        $receivedAt = $this->resolveTimestamp((string) data_get($message, 'timestamp'));
        $messageType = trim((string) data_get($message, 'type', 'text'));
        $textContent = $this->resolveTextContent($message, $messageType);
        $caption = $this->resolveCaption($message, $messageType);
        $commandContext = $this->extractCommandContext($caption . ' ' . $textContent);

        return DB::transaction(function () use (
            $message,
            $metadata,
            $senderPhone,
            $senderName,
            $receivedAt,
            $messageType,
            $textContent,
            $caption,
            $commandContext,
            $externalMessageId
        ) {
            [$thread, $threadCreated] = $this->resolveThread(
                senderPhone: $senderPhone,
                senderName: $senderName,
                receivedAt: $receivedAt,
                metadata: $metadata,
                commandContext: $commandContext,
            );

            $item = $this->ensureIntakeItem($thread, $receivedAt);
            $this->applyThreadContext($thread, $item, $commandContext, $textContent, $caption, $receivedAt);

            $messageRecord = $thread->messages()->create([
                'external_message_id' => $externalMessageId,
                'message_type' => $messageType,
                'text_content' => $textContent ?: null,
                'caption' => $caption ?: null,
                'media_external_id' => $this->resolveMediaId($message, $messageType),
                'media_mime_type' => $this->resolveMediaMimeType($message, $messageType),
                'received_at' => $receivedAt,
                'payload_json' => $message,
            ]);

            $mediaImported = false;
            $analysisDispatched = false;

            if ($this->isMediaMessage($messageType)) {
                $media = $this->importMedia($item, $thread, $message, $messageType, $commandContext, $receivedAt);

                if ($media) {
                    $messageRecord->update([
                        'media_disk' => $media->disk,
                        'media_path' => $media->path,
                        'return_intake_media_id' => $media->id,
                        'processed_at' => now(),
                    ]);

                    $mediaImported = true;
                    $analysisDispatched = $this->queueAnalysisIfReady($thread, $item);
                }
            } else {
                $messageRecord->update([
                    'processed_at' => now(),
                ]);

                $analysisDispatched = $this->queueAnalysisIfReady($thread, $item);
            }

            return [
                'thread_created' => $threadCreated,
                'media_imported' => $mediaImported,
                'analysis_dispatched' => $analysisDispatched,
                'duplicate' => false,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $commandContext
     * @return array{0: ReturnWhatsappThread, 1: bool}
     */
    protected function resolveThread(string $senderPhone, string $senderName, Carbon $receivedAt, array $metadata, array $commandContext): array
    {
        $windowMinutes = max(1, (int) $this->settingsService->get('message_window_minutes', 8));
        $chatId = trim((string) data_get($metadata, 'phone_number_id', '')) ?: $senderPhone;

        $thread = ReturnWhatsappThread::query()
            ->where('sender_phone', $senderPhone)
            ->whereIn('status', ['collecting', 'queued'])
            ->where('last_message_at', '>=', $receivedAt->copy()->subMinutes($windowMinutes))
            ->latest('last_message_at')
            ->first();

        if ($thread) {
            return [$thread, false];
        }

        return [
            ReturnWhatsappThread::create([
                'provider' => 'meta_cloud_api',
                'external_chat_id' => $chatId !== '' ? $chatId : null,
                'sender_phone' => $senderPhone !== '' ? $senderPhone : null,
                'sender_name' => $senderName !== '' ? $senderName : null,
                'intake_type' => $commandContext['intake_type'] ?? 'undamaged',
                'status' => 'collecting',
                'last_message_at' => $receivedAt,
                'raw_context_json' => [
                    'metadata' => $metadata,
                    'source' => 'whatsapp_bridge',
                ],
            ]),
            true,
        ];
    }

    protected function ensureIntakeItem(ReturnWhatsappThread $thread, Carbon $receivedAt): ReturnIntakeItem
    {
        if ($thread->intakeItem) {
            return $thread->intakeItem;
        }

        $systemUser = $this->resolveSystemUser();

        $batch = ReturnIntakeBatch::create([
            'user_id' => $systemUser->id,
            'source' => 'whatsapp_bridge',
            'intake_mode' => $thread->intake_type,
            'status' => 'submitted',
            'captured_at' => $receivedAt,
        ]);

        $item = ReturnIntakeItem::create([
            'batch_id' => $batch->id,
            'submitted_by_user_id' => $systemUser->id,
            'intake_type' => $thread->intake_type,
            'intake_status' => 'queued',
            'condition_status' => $thread->intake_type === 'damaged' ? 'damaged' : 'undamaged',
            'product_verification_status' => 'unverified',
            'decision_status' => 'pending',
            'warehouse_note' => $this->buildInitialWarehouseNote($thread),
            'arrived_at' => $receivedAt,
            'raw_summary_json' => [
                'source' => 'whatsapp_bridge',
                'sender_phone' => $thread->sender_phone,
                'sender_name' => $thread->sender_name,
            ],
        ]);

        $thread->update([
            'return_intake_batch_id' => $batch->id,
            'return_intake_item_id' => $item->id,
        ]);

        return $item;
    }

    /**
     * @param  array<string, mixed>  $commandContext
     */
    protected function applyThreadContext(
        ReturnWhatsappThread $thread,
        ReturnIntakeItem $item,
        array $commandContext,
        string $textContent,
        string $caption,
        Carbon $receivedAt,
    ): void {
        $updates = [
            'last_message_at' => $receivedAt,
        ];

        if (($commandContext['intake_type'] ?? null) && $commandContext['intake_type'] !== $thread->intake_type) {
            $updates['intake_type'] = $commandContext['intake_type'];
            $item->update([
                'intake_type' => $commandContext['intake_type'],
                'condition_status' => $commandContext['intake_type'] === 'damaged' ? 'damaged' : 'undamaged',
            ]);
        }

        $thread->update($updates);

        if ($textContent === '' && $caption === '') {
            return;
        }

        $note = trim($caption !== '' ? $caption : $textContent);

        if ($note === '') {
            return;
        }

        $existing = trim((string) ($item->warehouse_note ?? ''));
        $compiled = $existing !== '' ? $existing . "\n" . $note : $note;

        $item->update([
            'warehouse_note' => mb_substr($compiled, 0, 1000),
        ]);
    }

    /**
     * @param  array<string, mixed>  $message
     * @param  array<string, mixed>  $commandContext
     */
    protected function importMedia(
        ReturnIntakeItem $item,
        ReturnWhatsappThread $thread,
        array $message,
        string $messageType,
        array $commandContext,
        Carbon $receivedAt,
    ): ?ReturnIntakeMedia {
        $mediaId = $this->resolveMediaId($message, $messageType);

        if ($mediaId === '' && !$this->hasDirectMediaUrl($message, $messageType)) {
            return null;
        }

        $download = $mediaId !== ''
            ? $this->cloudApiService->downloadMedia($mediaId)
            : [
                'contents' => (string) file_get_contents((string) data_get($message, "{$messageType}.link")),
                'mime_type' => (string) data_get($message, "{$messageType}.mime_type", 'image/jpeg'),
                'extension' => null,
                'metadata' => [],
            ];

        $kind = $this->resolveMediaKind($item, $thread, $messageType, $commandContext);
        $directory = sprintf('%s/%s/%s/%s', $this->basePath, now()->format('Y'), now()->format('m'), $kind);
        $stored = $this->mediaOptimizationService->storeBinary(
            contents: $download['contents'],
            mimeType: $download['mime_type'] ?? null,
            disk: $this->disk,
            directory: $directory,
            extension: $download['extension'] ?? null,
        );

        return $item->media()->create(array_merge($stored, [
            'kind' => $kind,
            'captured_at' => $receivedAt,
        ]));
    }

    protected function queueAnalysisIfReady(ReturnWhatsappThread $thread, ReturnIntakeItem $item): bool
    {
        $labelCount = $item->media()->where('kind', 'label')->count();

        if ($labelCount === 0) {
            $thread->update(['status' => 'collecting']);
            return false;
        }

        $lastRequestedAt = $thread->analysis_requested_at;

        if ($lastRequestedAt && $lastRequestedAt->gt(now()->subSeconds(15))) {
            return false;
        }

        AnalyzeReturnIntakeItemJob::dispatchAfterResponse($item->id);

        $thread->update([
            'status' => 'queued',
            'analysis_requested_at' => now(),
        ]);

        return true;
    }

    protected function resolveSystemUser(): User
    {
        $configuredUserId = (int) $this->settingsService->get('system_user_id', 0);

        $user = $configuredUserId > 0
            ? User::query()->find($configuredUserId)
            : User::query()->where('role', 'admin')->where('is_active', true)->oldest('id')->first();

        if (!$user) {
            throw new \RuntimeException('WhatsApp koprusu icin aktif bir sistem kullanicisi bulunamadi.');
        }

        return $user;
    }

    protected function buildInitialWarehouseNote(ReturnWhatsappThread $thread): string
    {
        $parts = array_values(array_filter([
            'WhatsApp koprusu ile geldi',
            $thread->sender_name ? 'Gonderen: ' . $thread->sender_name : null,
            $thread->sender_phone ? 'Hat: ' . $thread->sender_phone : null,
        ]));

        return implode(' | ', $parts);
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array{intake_type?: string, media_kind?: string}
     */
    protected function extractCommandContext(string $value): array
    {
        $text = mb_strtolower(trim($value));

        if ($text === '') {
            return [];
        }

        $context = [];

        if ($this->containsAny($text, ['#hasarli', '#hasarlı', ' hasarlı', ' hasarli', 'kırık', 'kirik', 'çizik', 'cizik', 'defolu', 'sorunlu'])) {
            $context['intake_type'] = 'damaged';
        } elseif ($this->containsAny($text, ['#hasarsiz', '#hasarsız', ' hasarsız', ' hasarsiz', 'sağlam', 'saglam'])) {
            $context['intake_type'] = 'undamaged';
        }

        if ($this->containsAny($text, ['#etiket', 'etiket', 'kargo', 'paket', 'poşet', 'poset'])) {
            $context['media_kind'] = 'label';
        } elseif ($this->containsAny($text, ['#hasar', 'hasar', 'kırık', 'kirik', 'çizik', 'cizik', 'ezik', 'yırtık', 'yirtik'])) {
            $context['media_kind'] = 'damage';
        } elseif ($this->containsAny($text, ['#urun', '#ürün', 'ürün', 'urun', 'iç', 'ic'])) {
            $context['media_kind'] = 'product';
        }

        return $context;
    }

    protected function resolveMediaKind(ReturnIntakeItem $item, ReturnWhatsappThread $thread, string $messageType, array $commandContext): string
    {
        if (($commandContext['media_kind'] ?? null) && in_array($commandContext['media_kind'], ['label', 'product', 'damage'], true)) {
            return $commandContext['media_kind'];
        }

        if ($item->media()->where('kind', 'label')->count() === 0) {
            return 'label';
        }

        if ($thread->intake_type === 'damaged' && $item->media()->where('kind', 'damage')->count() === 0) {
            return 'damage';
        }

        return $messageType === 'image' ? 'product' : 'label';
    }

    /**
     * @param  array<string, mixed>  $message
     */
    protected function resolveTextContent(array $message, string $messageType): string
    {
        return $messageType === 'text'
            ? trim((string) data_get($message, 'text.body', ''))
            : '';
    }

    /**
     * @param  array<string, mixed>  $message
     */
    protected function resolveCaption(array $message, string $messageType): string
    {
        return trim((string) data_get($message, "{$messageType}.caption", ''));
    }

    /**
     * @param  array<string, mixed>  $message
     */
    protected function resolveMediaId(array $message, string $messageType): string
    {
        return trim((string) data_get($message, "{$messageType}.id", ''));
    }

    /**
     * @param  array<string, mixed>  $message
     */
    protected function resolveMediaMimeType(array $message, string $messageType): ?string
    {
        $mime = trim((string) data_get($message, "{$messageType}.mime_type", ''));

        return $mime !== '' ? $mime : null;
    }

    protected function resolveTimestamp(string $timestamp): Carbon
    {
        return ctype_digit($timestamp)
            ? Carbon::createFromTimestamp((int) $timestamp)
            : now();
    }

    protected function isMediaMessage(string $messageType): bool
    {
        return in_array($messageType, ['image', 'document'], true);
    }

    /**
     * @param  array<string, mixed>  $message
     */
    protected function hasDirectMediaUrl(array $message, string $messageType): bool
    {
        return trim((string) data_get($message, "{$messageType}.link", '')) !== '';
    }

    /**
     * @param  array<int, string>  $needles
     */
    protected function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
