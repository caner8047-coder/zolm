<?php

namespace App\Services\Support;

use App\Models\SupportChannel;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\SupportOnboardingState;
use App\Services\Support\AI\CustomerCareAiOrchestrator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\ChannelListing;
use App\Models\MpOrder;
use App\Models\MarketplaceQuestion;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Kurulum başarısını gerçek bağlantı sağlığı ile doğrulanmış ilk AI taslağı
 * arasındaki ölçülebilir kanıt zinciri olarak kaydeder.
 */
class CustomerCareOnboardingVerificationService
{
    public function __construct(
        private SupportCapabilityService $capabilities,
        private CustomerCareAiOrchestrator $orchestrator,
    ) {
    }

    public function verify(int $storeId, string $sampleQuestion = 'Merhaba', ?User $actor = null): array
    {
        $actor = $actor ?? Auth::user() ?? TenantContext::getSystemActor();
        TenantContext::enforceStoreAccess($storeId, $actor);

        $sampleQuestion = trim(strip_tags($sampleQuestion));
        if ($sampleQuestion === '' || mb_strlen($sampleQuestion) > 500) {
            return $this->failure($storeId, 'Örnek soru 1-500 karakter arasında olmalıdır.');
        }

        $state = SupportOnboardingState::firstOrCreate(
            ['store_id' => $storeId],
            ['current_step' => 1, 'steps_completed' => [], 'status' => 'in_progress', 'recommended_mode' => 'manual']
        );

        if (!$state->connection_started_at) {
            $state->connection_started_at = now();
            $state->save();
        }

        $channels = SupportChannel::where('store_id', $storeId)
            ->where('is_enabled', true)
            ->where('status', 'active')
            ->orderBy('id')
            ->get();

        if ($channels->isEmpty()) {
            return $this->failure($storeId, 'Doğrulanabilecek aktif bir müşteri iletişim kanalı bulunamadı.');
        }

        $diagnostics = [];
        $verifiedChannel = null;
        foreach ($channels as $channel) {
            try {
                $health = $this->capabilities->healthCheck($channel);
                $this->capabilities->refreshCapabilities($channel);
            } catch (\Throwable $e) {
                $health = ['status' => 'error', 'message' => $e->getMessage()];
            }

            $aiCapability = $channel->capabilities()
                ->where('capability', 'ai_suggestions')
                ->value('status');
            $isHealthy = ($health['status'] ?? null) === 'ok' && $aiCapability === 'available';
            $diagnostics[] = [
                'channel_id' => $channel->id,
                'channel_key' => $channel->key,
                'health_status' => $health['status'] ?? 'unknown',
                'health_message' => mb_substr((string) ($health['message'] ?? ''), 0, 250),
                'ai_suggestions' => $aiCapability ?? 'unavailable',
                'verified' => $isHealthy,
            ];

            if ($isHealthy && !$verifiedChannel) {
                $verifiedChannel = $channel;
            }
        }

        if (!$verifiedChannel) {
            return $this->failure($storeId, 'Kanal sağlık kontrolü ve AI taslak yeteneği birlikte doğrulanamadı.', $diagnostics);
        }

        $catalogDryRun = $this->catalogDryRun($storeId);
        $diagnostics[] = [
            'type' => 'catalog_dry_run',
            'health_status' => $catalogDryRun['verified'] ? 'ok' : 'warning',
            'health_message' => $catalogDryRun['message'],
            'verified' => $catalogDryRun['verified'],
            'counts' => $catalogDryRun['counts'],
        ];

        $conversation = DB::transaction(function () use ($storeId, $verifiedChannel, $sampleQuestion) {
            $externalId = 'onboarding_verification_' . Str::uuid();
            $conversation = SupportConversation::create([
                'support_channel_id' => $verifiedChannel->id,
                'external_conversation_id' => $externalId,
                'store_id' => $storeId,
                'source_type' => 'onboarding_verification',
                'status' => 'resolved',
                'priority' => 'normal',
                'ai_mode' => 'manual',
                'ownership_status' => 'human',
                'last_message_at' => now(),
                'last_inbound_at' => now(),
                'source_reference_json' => ['synthetic' => true, 'purpose' => 'onboarding_verification'],
            ]);
            SupportMessage::create([
                'conversation_id' => $conversation->id,
                'external_message_id' => 'onboarding_sample_' . Str::uuid(),
                'direction' => 'inbound',
                'sender_type' => 'customer',
                'message_type' => 'text',
                'body_encrypted' => $sampleQuestion,
                'body_preview' => mb_substr($sampleQuestion, 0, 100),
                'delivery_status' => 'received',
                'received_at' => now(),
            ]);

            return $conversation;
        });

        $result = $this->orchestrator->generateDraft($conversation);
        $verified = ($result['success'] ?? false) === true
            && ($result['status'] ?? null) === 'draft'
            && !empty($result['message_id'])
            && (int) ($result['confidence'] ?? 0) >= 75;

        $now = now();
        $duration = max(0, $state->connection_started_at->diffInSeconds($now));
        $safeResult = [
            'success' => $verified,
            'status' => $result['status'] ?? 'failed',
            'confidence' => (int) ($result['confidence'] ?? 0),
            'message_id' => $result['message_id'] ?? null,
            'channel_id' => $verifiedChannel->id,
            'conversation_id' => $conversation->id,
        ];

        $state->update([
            'first_verified_draft_at' => $verified ? ($state->first_verified_draft_at ?? $now) : $state->first_verified_draft_at,
            'verification_duration_seconds' => $verified ? ($state->verification_duration_seconds ?? $duration) : $state->verification_duration_seconds,
            'last_verified_at' => $now,
            'diagnostics_json' => $diagnostics,
            'catalog_verified_at' => $catalogDryRun['verified'] ? $now : null,
            'catalog_dry_run_json' => $catalogDryRun,
            'support_bundle_json' => $this->supportBundle($storeId, $diagnostics, $catalogDryRun),
            'sample_question' => $sampleQuestion,
            'sample_result_json' => $safeResult,
        ]);

        return $verified
            ? ['success' => true, 'message' => 'Bağlantı ve ilk AI taslağı doğrulandı.', 'duration_seconds' => $duration, 'diagnostics' => $diagnostics, 'result' => $safeResult]
            : ['success' => false, 'message' => 'AI, güvenli ve gönderilebilir bir taslak üretemedi.', 'diagnostics' => $diagnostics, 'result' => $safeResult];
    }

    private function catalogDryRun(int $storeId): array
    {
        $base = ChannelListing::where('store_id', $storeId);
        $totalListings = (clone $base)->count();
        $freshListings = (clone $base)->where('stock_quantity', '>=', 0)
            ->whereNotNull('last_stock_sync_at')->where('last_stock_sync_at', '>=', now()->subHours(24))
            ->whereNotNull('last_price_sync_at')->where('last_price_sync_at', '>=', now()->subHours(24))
            ->where('sale_price', '>', 0)->count();
        $counts = [
            'catalog_listings' => $totalListings,
            'fresh_sellable_listings' => $freshListings,
            'orders_scanned' => MpOrder::where('store_id', $storeId)->limit(100)->count(),
            'historical_questions_scanned' => MarketplaceQuestion::where('store_id', $storeId)->limit(100)->count(),
        ];
        return [
            'verified' => $freshListings > 0,
            'counts' => $counts,
            'checked_at' => now()->toIso8601String(),
            'message' => $freshListings > 0
                ? "{$freshListings} güncel ve satışa açık katalog kaydı dry-run ile doğrulandı."
                : 'Güncel stok ve fiyatı birlikte doğrulanmış katalog kaydı yok; ürün otomasyonu kapalı kalacak.',
        ];
    }

    private function supportBundle(int $storeId, array $diagnostics, array $catalog): array
    {
        return [
            'schema_version' => '1.0',
            'store_id' => $storeId,
            'generated_at' => now()->toIso8601String(),
            'app_environment' => app()->environment(),
            'php_version' => PHP_VERSION,
            'channels' => $diagnostics,
            'catalog' => $catalog,
            'secrets_included' => false,
            'pii_included' => false,
        ];
    }

    private function failure(int $storeId, string $message, array $diagnostics = []): array
    {
        SupportOnboardingState::where('store_id', $storeId)->update([
            'last_verified_at' => now(),
            'diagnostics_json' => $diagnostics,
            'sample_result_json' => ['success' => false, 'status' => 'failed', 'message' => $message],
        ]);

        return ['success' => false, 'message' => $message, 'diagnostics' => $diagnostics];
    }
}
