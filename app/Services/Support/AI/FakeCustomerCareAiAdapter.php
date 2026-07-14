<?php

namespace App\Services\Support\AI;

use App\Models\SupportConversation;

class FakeCustomerCareAiAdapter implements CustomerCareAiProviderInterface
{
    public function generateAnswer(
        SupportConversation $conversation,
        array $history,
        ?string $promptTemplate = null
    ): CustomerCareAiResponseDto {
        $userMessage = end($history)['text'] ?? '';
        $text = "Fake: Merhaba! Size nasıl yardımcı olabilirim? (Demo)";

        $detected = app(CustomerCareLanguageService::class)->detect($text);
        return new CustomerCareAiResponseDto(
            $text,
            95,
            ['Fake Source #1'],
            false,
            $detected['language'] === 'und' ? 'tr' : $detected['language'],
            (float) $detected['confidence'],
        );
    }
}
