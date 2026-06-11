<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceQuestion;
use App\Models\MarketplaceQuestionAnswerLog;
use App\Models\User;
use App\Services\Marketplace\Contracts\AnswersCustomerQuestions;
use Illuminate\Support\Facades\DB;
use Throwable;

class MarketplaceQuestionAnswerService
{
    public function __construct(
        protected MarketplaceConnectorManager $connectorManager,
    ) {
    }

    public function saveDraft(
        MarketplaceQuestion $question,
        string $answer,
        ?User $user = null,
        ?int $templateId = null,
        ?int $ruleId = null,
        string $source = 'manual',
    ): MarketplaceQuestionAnswerLog {
        return DB::transaction(function () use ($question, $answer, $user, $templateId, $ruleId, $source) {
            $question->forceFill([
                'answer_text' => $answer,
                'status' => 'draft',
            ])->save();

            return MarketplaceQuestionAnswerLog::query()->create([
                'marketplace_question_id' => $question->id,
                'user_id' => $user?->id,
                'template_id' => $templateId,
                'rule_id' => $ruleId,
                'source' => $source,
                'answer_text' => $answer,
                'status' => 'draft',
            ]);
        });
    }

    public function sendAnswer(
        MarketplaceQuestion $question,
        string $answer,
        ?User $user = null,
        ?int $templateId = null,
        ?int $ruleId = null,
        string $source = 'manual',
    ): MarketplaceQuestionAnswerLog {
        $log = MarketplaceQuestionAnswerLog::query()->create([
            'marketplace_question_id' => $question->id,
            'user_id' => $user?->id,
            'template_id' => $templateId,
            'rule_id' => $ruleId,
            'source' => $source,
            'answer_text' => $answer,
            'status' => 'queued',
        ]);

        try {
            $connector = $this->connectorManager->resolve($question->store->marketplace);

            if (!$connector instanceof AnswersCustomerQuestions) {
                throw new \RuntimeException('Bu pazaryeri için canlı soru cevap gönderimi henüz bağlanmadı. Cevap taslak olarak saklandı.');
            }

            $response = $connector->answerCustomerQuestion($question, $answer);

            DB::transaction(function () use ($question, $answer, $user, $log, $response) {
                $question->forceFill([
                    'answer_text' => $answer,
                    'status' => 'answered',
                    'answered_at' => now(),
                    'answered_by_user_id' => $user?->id,
                ])->save();

                $question->messages()->create([
                    'direction' => 'seller',
                    'external_message_id' => $response['external_answer_id'] ?? null,
                    'body' => $answer,
                    'sent_at' => now(),
                    'raw_payload' => $response,
                ]);

                $log->forceFill([
                    'status' => 'sent',
                    'external_answer_id' => $response['external_answer_id'] ?? null,
                    'response_json' => $response,
                    'sent_at' => now(),
                ])->save();
            });
        } catch (Throwable $exception) {
            DB::transaction(function () use ($question, $answer, $log, $exception) {
                $question->forceFill([
                    'answer_text' => $answer,
                    'status' => 'draft',
                ])->save();

                $log->forceFill([
                    'status' => 'failed',
                    'error_message' => $exception->getMessage(),
                ])->save();
            });
        }

        return $log->fresh();
    }
}
