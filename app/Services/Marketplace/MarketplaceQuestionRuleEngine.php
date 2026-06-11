<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceQuestion;
use App\Models\MarketplaceQuestionRule;
use App\Models\User;
use Illuminate\Support\Str;
use Throwable;

class MarketplaceQuestionRuleEngine
{
    public function __construct(
        protected MarketplaceQuestionAnswerService $answerService,
    ) {
    }

    public function apply(MarketplaceQuestion $question, ?User $user = null): ?MarketplaceQuestionRule
    {
        $rule = MarketplaceQuestionRule::query()
            ->active()
            ->where(function ($query) use ($question) {
                $query->whereNull('user_id')
                    ->orWhere('user_id', $question->store->user_id);
            })
            ->where(function ($query) use ($question) {
                $query->whereNull('store_id')
                    ->orWhere('store_id', $question->store_id);
            })
            ->with('template')
            ->orderBy('priority')
            ->get()
            ->first(fn (MarketplaceQuestionRule $candidate) => $this->matches($candidate, $question->question_text));

        if (!$rule) {
            return null;
        }

        $answer = trim((string) ($rule->response_text ?: $rule->template?->body));

        if ($answer === '') {
            return null;
        }

        $rule->forceFill([
            'trigger_count' => (int) $rule->trigger_count + 1,
            'last_triggered_at' => now(),
        ])->save();

        $question->forceFill([
            'matched_rule_id' => $rule->id,
            'ai_suggested_answer' => $answer,
            'ai_status' => 'rule_matched',
        ])->save();

        if ($rule->action_mode === 'auto_send' && !$rule->requires_approval) {
            $this->answerService->sendAnswer(
                $question,
                $answer,
                $user,
                $rule->template_id,
                $rule->id,
                'rule',
            );
        } else {
            $this->answerService->saveDraft(
                $question,
                $answer,
                $user,
                $rule->template_id,
                $rule->id,
                'rule',
            );
        }

        return $rule;
    }

    protected function matches(MarketplaceQuestionRule $rule, string $questionText): bool
    {
        $text = Str::of($questionText)->lower()->ascii()->value();
        $keywords = collect($rule->keywords_json)
            ->filter()
            ->map(fn ($keyword) => trim((string) $keyword))
            ->filter();

        if ($keywords->isEmpty()) {
            return false;
        }

        return match ($rule->match_type) {
            'exact' => $keywords->contains(fn ($keyword) => $text === Str::of($keyword)->lower()->ascii()->value()),
            'regex' => $keywords->contains(function ($keyword) use ($questionText) {
                try {
                    return @preg_match((string) $keyword, $questionText) === 1;
                } catch (Throwable) {
                    return false;
                }
            }),
            default => $keywords->contains(fn ($keyword) => str_contains($text, Str::of($keyword)->lower()->ascii()->value())),
        };
    }
}
