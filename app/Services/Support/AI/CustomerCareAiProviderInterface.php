<?php

namespace App\Services\Support\AI;

use App\Models\SupportConversation;

interface CustomerCareAiProviderInterface
{
    /**
     * Konuşma bağlamı, geçmişi ve opsiyonel yönergelerle yapay zekadan yanıt üretir.
     */
    public function generateAnswer(
        SupportConversation $conversation,
        array $history,
        ?string $promptTemplate = null
    ): CustomerCareAiResponseDto;
}
