<?php

namespace App\Services\Marketplace\Contracts;

use App\Models\MarketplaceQuestion;

interface AnswersCustomerQuestions
{
    /**
     * @return array<string, mixed>
     */
    public function answerCustomerQuestion(MarketplaceQuestion $question, string $answer): array;
}
