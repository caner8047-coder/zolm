<?php

namespace App\Services\Support\AI;

class CustomerCareAiResponseDto
{
    public string $suggestedAnswer;
    public int $confidence; // 0-100
    public array $matchedSources;
    public bool $isEscalationRequired;
    public string $language;
    public float $languageConfidence;

    public function __construct(
        string $suggestedAnswer,
        int $confidence,
        array $matchedSources = [],
        bool $isEscalationRequired = false,
        string $language = 'tr',
        float $languageConfidence = 0.0
    ) {
        $this->suggestedAnswer = $suggestedAnswer;
        $this->confidence = $confidence;
        $this->matchedSources = $matchedSources;
        $this->isEscalationRequired = $isEscalationRequired;
        $this->language = $language;
        $this->languageConfidence = max(0.0, min(1.0, $languageConfidence));
    }

    public function toArray(): array
    {
        return [
            'suggested_answer' => $this->suggestedAnswer,
            'confidence' => $this->confidence,
            'matched_sources' => $this->matchedSources,
            'is_escalation_required' => $this->isEscalationRequired,
            'language' => $this->language,
            'language_confidence' => $this->languageConfidence,
        ];
    }
}
