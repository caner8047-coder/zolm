<?php

namespace App\Livewire\CustomerCare;

use Livewire\Component;
use App\Models\MarketplaceStore;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\SupportAiRun;
use App\Models\SupportQualityReview;
use App\Models\SupportQualityReviewItem;
use App\Models\SupportKnowledgeSuggestion;
use App\Services\Support\Security\PiiRedactor;

class QualityCenter extends Component
{
    private const SCORE_CATEGORIES = [
        'accuracy',
        'brand_voice',
        'channel_policy',
        'pii_safety',
        'clarity',
        'sales_alignment',
        'promise_risk',
    ];

    public int $selectedStoreId;
    public string $filterType = 'ai_run'; // ai_run, agent_reply
    public ?int $selectedItemId = null;

    // Selection Details
    public ?int $selectedConversationId = null;
    public ?int $selectedMessageId = null;
    public string $itemPreviewText = '';

    // Scorecard Form Fields
    public array $scores = [
        'accuracy' => null,
        'brand_voice' => null,
        'channel_policy' => null,
        'pii_safety' => null,
        'clarity' => null,
        'sales_alignment' => null,
        'promise_risk' => null,
    ];
    public array $comments = [
        'accuracy' => '',
        'brand_voice' => '',
        'channel_policy' => '',
        'pii_safety' => '',
        'clarity' => '',
        'sales_alignment' => '',
        'promise_risk' => '',
    ];
    public string $feedback = '';
    public string $decision = 'approved'; // approved, correction_required, golden_candidate, kb_candidate

    // Messages
    public string $successMessage = '';
    public string $errorMessage = '';

    public function mount()
    {
        $user = auth()->user();
        if (!$user || $user->role !== 'admin') {
            abort(403, 'Bu sayfaya erişim yetkiniz bulunmamaktadır.');
        }

        $store = MarketplaceStore::first();
        $this->selectedStoreId = $store ? $store->id : 0;
    }

    public function selectItem(int $id, string $type)
    {
        $this->selectedItemId = $id;
        $this->successMessage = '';
        $this->errorMessage = '';
        $redactor = app(PiiRedactor::class);

        // P0-4: Store-scoped resolver for selecting items
        if ($type === 'ai_run') {
            $run = SupportAiRun::where('store_id', $this->selectedStoreId)->find($id);
            if ($run) {
                $this->selectedConversationId = $run->conversation_id;
                $this->selectedMessageId = $run->message_id;
                // P0-4: PII masking for prompt/response in preview
                $rawPreview = "Sorgu: " . $run->prompt_raw . "\n\nAI Cevabı: " . ($run->response_raw ?? '[Cevap üretilemedi]');
                $this->itemPreviewText = $redactor->maskPii($rawPreview);
            } else {
                $this->resetForm();
                $this->errorMessage = 'Seçilen yapay zeka çalıştırması bu mağazaya ait değil veya bulunamadı.';
            }
        } else {
            $msg = SupportMessage::whereHas('conversation', function ($q) {
                $q->where('store_id', $this->selectedStoreId);
            })->find($id);
            if ($msg) {
                $this->selectedConversationId = $msg->conversation_id;
                $this->selectedMessageId = $msg->id;
                // P0-4: PII masking in preview
                $this->itemPreviewText = $redactor->maskPii("Temsilci Mesajı: " . $msg->body_encrypted);
            } else {
                $this->resetForm();
                $this->errorMessage = 'Seçilen temsilci mesajı bu mağazaya ait değil veya bulunamadı.';
            }
        }
    }

    public function submitReview()
    {
        $user = auth()->user();
        if (!$user || $user->role !== 'admin') {
            abort(403);
        }

        if (!$this->selectedItemId) {
            $this->errorMessage = 'Lütfen incelemek için listeden bir kayıt seçin.';
            return;
        }

        // Canonical server-side resolution to prevent property manipulation (P0-2)
        $canonicalConversationId = null;
        $canonicalMessageId = null;

        if ($this->filterType === 'ai_run') {
            $run = SupportAiRun::where('store_id', $this->selectedStoreId)->find($this->selectedItemId);
            if (!$run) {
                $this->errorMessage = 'Seçilen kayıt bu mağazaya ait değil. İşlem engellendi.';
                return;
            }
            $canonicalConversationId = $run->conversation_id;
            $canonicalMessageId = $run->message_id;
        } else {
            $msg = SupportMessage::whereHas('conversation', function ($q) {
                $q->where('store_id', $this->selectedStoreId);
            })->find($this->selectedItemId);
            if (!$msg) {
                $this->errorMessage = 'Seçilen kayıt bu mağazaya ait değil. İşlem engellendi.';
                return;
            }
            $canonicalConversationId = $msg->conversation_id;
            $canonicalMessageId = $msg->id;
        }

        $normalizedScores = [];
        if (array_diff(array_keys($this->scores), self::SCORE_CATEGORIES)) {
            $this->errorMessage = 'Geçersiz kalite kriteri gönderildi. İnceleme kaydedilmedi.';
            return;
        }
        foreach (self::SCORE_CATEGORIES as $category) {
            $score = filter_var($this->scores[$category] ?? null, FILTER_VALIDATE_INT);
            if ($score === false || $score < 0 || $score > 100 || $score % 5 !== 0) {
                $this->errorMessage = 'Tüm kalite kriterleri insan incelemesiyle 0-100 arasında puanlanmalıdır.';
                return;
            }
            $normalizedScores[$category] = $score;
        }
        $this->scores = $normalizedScores;
        $overallScore = (int) (array_sum($normalizedScores) / count($normalizedScores));

        // PII Masking feedback
        $redactor = app(PiiRedactor::class);
        $cleanFeedback = $redactor->maskPii($this->feedback);

        $review = SupportQualityReview::create([
            'store_id' => $this->selectedStoreId,
            'conversation_id' => $canonicalConversationId,
            'message_id' => $canonicalMessageId,
            'reviewer_id' => $user->id,
            'overall_score' => $overallScore,
            'feedback' => $cleanFeedback,
            'decision' => $this->decision,
        ]);

        foreach ($this->scores as $category => $score) {
            $review->items()->create([
                'category' => $category,
                'score' => $score,
                'comment' => $redactor->maskPii($this->comments[$category] ?? ''),
            ]);
        }

        if ($this->decision === 'correction_required' && $canonicalConversationId) {
            $conversation = SupportConversation::where('store_id', $this->selectedStoreId)
                ->find($canonicalConversationId);
            $message = $canonicalMessageId
                ? SupportMessage::where('conversation_id', $canonicalConversationId)->find($canonicalMessageId)
                : null;

            if ($conversation) {
                $criticalScore = min((int) ($this->scores['accuracy'] ?? 100), (int) ($this->scores['pii_safety'] ?? 100));
                app(\App\Services\Support\CustomerCareCorrectionService::class)->report(
                    $conversation,
                    $message,
                    $user,
                    $message?->body_encrypted ?: 'AI yanıtında doğruluk sorunu',
                    $cleanFeedback ?: 'Kalite incelemesinde düzeltme gerekli kararı verildi.',
                    $criticalScore <= 40 ? 'critical' : 'warning',
                );
            }
        }

        // Action candidate workflow
        if ($this->decision === 'kb_candidate') {
            $proposedAnswer = '';
            if ($canonicalMessageId) {
                $msg = SupportMessage::whereHas('conversation', function ($q) {
                    $q->where('store_id', $this->selectedStoreId);
                })->find($canonicalMessageId);
                $proposedAnswer = $msg ? $msg->body_encrypted : '';
            }

            // P0-4: KB candidate proposed answer must be redacted before database entry
            $redactedProposedAnswer = $redactor->maskPii($proposedAnswer);
            $redactedTitle = $redactor->maskPii('Kalite İncelemesi Önerisi - ' . now()->format('Y-m-d'));
            $hashKey = md5($this->selectedStoreId . '_' . $canonicalConversationId . '_' . $canonicalMessageId . '_' . $redactedTitle);

            SupportKnowledgeSuggestion::create([
                'store_id' => $this->selectedStoreId,
                'source_conversation_id' => $canonicalConversationId,
                'source_message_id' => $canonicalMessageId,
                'category' => 'general',
                'title' => $redactedTitle,
                'proposed_answer' => $redactedProposedAnswer,
                'confidence' => $overallScore,
                'status' => 'pending',
                'hash_key' => $hashKey,
            ]);
        }

        $this->successMessage = 'Kalite denetim incelemesi başarıyla kaydedildi.';
        $this->resetForm();
    }

    protected function resetForm()
    {
        $this->selectedItemId = null;
        $this->selectedConversationId = null;
        $this->selectedMessageId = null;
        $this->itemPreviewText = '';
        $this->feedback = '';
        $this->scores = array_fill_keys(self::SCORE_CATEGORIES, null);
        $this->comments = array_fill_keys(array_keys($this->comments), '');
        $this->decision = 'approved';
    }

    public function render()
    {
        $stores = MarketplaceStore::all();

        // Query reviews queue based on filters
        $reviewQueue = [];
        if ($this->filterType === 'ai_run') {
            $reviewQueue = SupportAiRun::where('store_id', $this->selectedStoreId)
                ->latest()
                ->limit(15)
                ->get();
        } else {
            $reviewQueue = SupportMessage::whereHas('conversation', function ($q) {
                    $q->where('store_id', $this->selectedStoreId);
                })
                ->where('sender_type', 'agent')
                ->latest()
                ->limit(15)
                ->get();
        }

        // Fetch Coaching Metrics (Only real items, PII-masked)
        $pastReviews = SupportQualityReview::where('store_id', $this->selectedStoreId)
            ->where('decision', '!=', 'pending_review')
            ->latest()
            ->get();

        $redactor = app(PiiRedactor::class);
        $coachingData = [
            'strong_points' => [],
            'attention_points' => [],
            'best_replies' => [],
        ];

        foreach ($pastReviews as $rev) {
            $maskedFeedback = $redactor->maskPii($rev->feedback);
            if ($rev->overall_score >= 85) {
                if (!empty($maskedFeedback)) {
                    $coachingData['strong_points'][] = $maskedFeedback;
                }
                if ($rev->message) {
                    $coachingData['best_replies'][] = [
                        'score' => $rev->overall_score,
                        'body' => $redactor->maskPii($rev->message->body_encrypted),
                    ];
                }
            } else {
                if (!empty($maskedFeedback)) {
                    $coachingData['attention_points'][] = $maskedFeedback;
                }
            }
        }

        return view('livewire.customer-care.quality-center', [
            'stores' => $stores,
            'reviewQueue' => $reviewQueue,
            'coachingData' => $coachingData,
        ])->layout('layouts.app');
    }
}
