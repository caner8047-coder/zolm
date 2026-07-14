<?php

namespace App\Services\Support;

use App\Models\MarketplaceQuestion;
use App\Models\SupportKnowledgeSuggestion;
use App\Models\SupportMessage;
use App\Models\User;
use App\Services\Support\Security\PiiRedactor;
use App\Services\Support\Security\SupportRbacService;
use Illuminate\Support\Facades\DB;

class CustomerCareProductQuestionLearningService
{
    private const ORDER_SPECIFIC_TERMS = [
        'siparişim', 'siparisim', 'kargom', 'takip numaram', 'takip no',
        'nerede kaldı', 'nerde kaldı', 'iade kodu', 'adresimi', 'sipariş no', 'siparis no',
    ];

    private const DYNAMIC_TERMS = [
        'fiyat', 'stok', 'kampanya', 'indirim', 'kupon', 'teslim', 'ne zaman gelir',
    ];

    private const HIGH_RISK_TERMS = [
        'kesin geçirir', 'kesin gecirir', 'tedavi eder', 'iyileştirir', 'iyilestirir',
        'garanti eder', 'yasal hakk', 'doktor', 'reçete', 'recete',
    ];

    public function __construct(
        private readonly SupportProjectionService $projectionService,
        private readonly PiiRedactor $piiRedactor,
        private readonly SupportRbacService $rbacService,
        private readonly CustomerCareKnowledgeGroundingService $groundingService,
    ) {
    }

    /**
     * @return array{eligible: bool, risk: string, reason: string, effective_days: int}
     */
    public function eligibility(MarketplaceQuestion $question): array
    {
        if (blank($question->answer_text) || !in_array($question->status, ['answered', 'closed'], true)) {
            return $this->eligibilityResult(false, 'unanswered', 'Yalnız pazaryerinde yayınlanmış insan cevapları kullanılabilir.', 0);
        }

        $combined = mb_strtolower(trim($question->question_text . ' ' . $question->answer_text));

        if ($this->groundingService->containsPromptInjection($combined)) {
            return $this->eligibilityResult(false, 'unsafe', 'Prompt injection şüphesi nedeniyle eğitim akışı engellendi.', 0);
        }

        if ($this->containsAny($combined, self::HIGH_RISK_TERMS)) {
            return $this->eligibilityResult(false, 'high_risk', 'Sağlık, hukuk veya kesin vaat içeren cevaplar otomatik bilgi adayı olamaz.', 0);
        }

        if ($this->containsAny($combined, self::ORDER_SPECIFIC_TERMS)) {
            return $this->eligibilityResult(false, 'order_specific', 'Siparişe veya müşteriye özel cevaplar yeniden kullanılamaz.', 0);
        }

        if ($this->containsAny($combined, self::DYNAMIC_TERMS)) {
            return $this->eligibilityResult(true, 'dynamic', 'Fiyat, stok veya teslimat bilgisi içerdiği için 7 gün sonra yeniden doğrulanır.', 7);
        }

        return $this->eligibilityResult(true, 'product_knowledge', 'Ürün kapsamlı, insan onaylı bilgi adayı olmaya uygun.', 180);
    }

    public function createKnowledgeCandidate(MarketplaceQuestion $question, User $actor): SupportKnowledgeSuggestion
    {
        TenantContext::enforceStoreAccess((int) $question->store_id, $actor);
        $this->rbacService->enforcePermission($actor, (int) $question->store_id, 'ai_draft_generate');

        $eligibility = $this->eligibility($question);
        if (!$eligibility['eligible']) {
            throw new \DomainException($eligibility['reason']);
        }

        if ($question->learningSuggestion) {
            if ($question->learningSuggestion->status === 'rejected') {
                throw new \DomainException('Reddedilmiş bilgi önerisini yeniden açmak için “Yeniden İncele” işlemini kullanın.');
            }

            $question->update([
                'learning_status' => $question->learningSuggestion->status === 'applied' ? 'applied' : 'candidate',
                'learning_excluded_reason' => null,
                'learning_reviewed_by_user_id' => $actor->id,
                'learning_reviewed_at' => now(),
            ]);

            return $question->learningSuggestion;
        }

        $hashKey = hash('sha256', "product-question:{$question->store_id}:{$question->id}");
        $existing = SupportKnowledgeSuggestion::where('hash_key', $hashKey)->first();
        if ($existing) {
            if ($existing->status === 'rejected') {
                throw new \DomainException('Reddedilmiş bilgi önerisini yeniden açmak için “Yeniden İncele” işlemini kullanın.');
            }

            $question->update([
                'learning_status' => $existing->status === 'applied' ? 'applied' : 'candidate',
                'learning_suggestion_id' => $existing->id,
                'learning_reviewed_by_user_id' => $actor->id,
                'learning_reviewed_at' => now(),
            ]);

            return $existing;
        }

        $limitCheck = app(CustomerCareUsageService::class)
            ->checkLimit((int) $question->store_id, 'knowledge_suggestions');
        if (!$limitCheck['allowed']) {
            throw new \RuntimeException($limitCheck['reason']);
        }

        $conversation = $this->projectionService->projectQuestion($question->fresh('store'));
        $answerMessage = SupportMessage::where('conversation_id', $conversation->id)
            ->where('direction', 'outbound')
            ->where('source_reference_type', 'MarketplaceQuestion')
            ->where('source_reference_id', (string) $question->id)
            ->latest('id')
            ->first();

        if (!$answerMessage) {
            throw new \RuntimeException('Yayınlanmış cevap AI Müşteri Merkezi konuşmasına aktarılamadı.');
        }

        $cleanProduct = $this->cleanText($question->product_name ?: $question->product_sku ?: 'Ürün');
        $cleanSku = $this->cleanText($question->product_sku ?: $question->product_barcode ?: 'Tanımsız');
        $cleanQuestion = $this->cleanText($question->question_text);
        $cleanAnswer = $this->cleanText($question->answer_text);
        $title = mb_substr("{$cleanProduct} — {$cleanQuestion}", 0, 190);
        $proposedAnswer = "Ürün: {$cleanProduct}\nStok Kodu: {$cleanSku}\nSoru: {$cleanQuestion}\nOnaylı Yanıt: {$cleanAnswer}";
        $clusterKey = hash('sha256', mb_strtolower("{$question->store_id}|{$cleanSku}|{$cleanQuestion}"));

        $suggestion = DB::transaction(function () use (
            $question,
            $actor,
            $conversation,
            $answerMessage,
            $eligibility,
            $hashKey,
            $clusterKey,
            $title,
            $proposedAnswer
        ): SupportKnowledgeSuggestion {
            $suggestion = SupportKnowledgeSuggestion::create([
                'store_id' => $question->store_id,
                'source_conversation_id' => $conversation->id,
                'source_message_id' => $answerMessage->id,
                'category' => 'Ürün Bilgisi',
                'title' => $title,
                'proposed_answer' => $proposedAnswer,
                'confidence' => 90,
                'status' => 'pending',
                'hash_key' => $hashKey,
                'cluster_key' => $clusterKey,
                'source_conversation_ids' => [$conversation->id],
                'source_message_ids' => [$answerMessage->id],
                'scope' => 'product',
                'version' => 1,
                'effective_until' => now()->addDays($eligibility['effective_days']),
            ]);

            $question->update([
                'learning_status' => 'candidate',
                'learning_suggestion_id' => $suggestion->id,
                'learning_excluded_reason' => null,
                'learning_reviewed_by_user_id' => $actor->id,
                'learning_reviewed_at' => now(),
            ]);

            return $suggestion;
        });

        app(CustomerCareUsageService::class)
            ->incrementUsage((int) $question->store_id, 'knowledge_suggestions');

        return $suggestion;
    }

    public function exclude(MarketplaceQuestion $question, User $actor, string $reason): void
    {
        TenantContext::enforceStoreAccess((int) $question->store_id, $actor);
        $this->rbacService->enforcePermission($actor, (int) $question->store_id, 'knowledge_publish');

        if ($question->learningSuggestion?->status === 'applied') {
            throw new \DomainException('Yayınlanmış bilgi makalesi önce Bilgi Bankası üzerinden yönetilmelidir.');
        }

        DB::transaction(function () use ($question, $actor, $reason): void {
            if ($question->learningSuggestion?->status === 'pending') {
                $question->learningSuggestion->update([
                    'status' => 'rejected',
                    'reviewed_by_user_id' => $actor->id,
                    'reviewed_at' => now(),
                ]);
            }

            $question->update([
                'learning_status' => 'excluded',
                'learning_excluded_reason' => mb_substr($this->cleanText($reason), 0, 255),
                'is_golden_candidate' => false,
                'learning_reviewed_by_user_id' => $actor->id,
                'learning_reviewed_at' => now(),
            ]);
        });
    }

    public function restore(MarketplaceQuestion $question, User $actor): void
    {
        TenantContext::enforceStoreAccess((int) $question->store_id, $actor);
        $this->rbacService->enforcePermission($actor, (int) $question->store_id, 'knowledge_publish');

        if ($question->learningSuggestion?->status === 'applied') {
            throw new \DomainException('Yayınlanmış bilgi makalesi önce Bilgi Bankası üzerinden yönetilmelidir.');
        }

        DB::transaction(function () use ($question, $actor): void {
            if ($question->learningSuggestion?->status === 'rejected') {
                $question->learningSuggestion->update([
                    'status' => 'pending',
                    'reviewed_by_user_id' => null,
                    'reviewed_at' => null,
                ]);
            }

            $question->update([
                'learning_status' => $question->learningSuggestion ? 'candidate' : 'new',
                'learning_excluded_reason' => null,
                'learning_reviewed_by_user_id' => $actor->id,
                'learning_reviewed_at' => now(),
            ]);
        });
    }

    public function toggleGoldenCandidate(MarketplaceQuestion $question, User $actor): bool
    {
        TenantContext::enforceStoreAccess((int) $question->store_id, $actor);
        $this->rbacService->enforcePermission($actor, (int) $question->store_id, 'approve_quality_review');

        if ($question->learning_status !== 'applied') {
            throw new \DomainException('Golden adaylığı için soru-cevap önce bilgi bankasında insan onayıyla yayınlanmalıdır.');
        }

        $nextState = !$question->is_golden_candidate;
        $question->update([
            'is_golden_candidate' => $nextState,
            'learning_reviewed_by_user_id' => $actor->id,
            'learning_reviewed_at' => now(),
        ]);

        return $nextState;
    }

    private function cleanText(?string $value): string
    {
        $value = strip_tags((string) $value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';

        return trim($this->piiRedactor->maskPii($value));
    }

    private function containsAny(string $text, array $terms): bool
    {
        foreach ($terms as $term) {
            if (str_contains($text, $term)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{eligible: bool, risk: string, reason: string, effective_days: int}
     */
    private function eligibilityResult(bool $eligible, string $risk, string $reason, int $effectiveDays): array
    {
        return [
            'eligible' => $eligible,
            'risk' => $risk,
            'reason' => $reason,
            'effective_days' => $effectiveDays,
        ];
    }
}
