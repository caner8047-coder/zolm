<?php

namespace App\Services\Support\AI;

/**
 * Sağlayıcının öznel skorunu kanıt kalitesiyle kalibre eder.
 * Native skor vermeyen sağlayıcılarda (0) yalnız doğrulanabilir bağlam kullanılır.
 */
class CustomerCareConfidenceScorer
{
    public function score(CustomerCareAiResponseDto $response, array $context): int
    {
        if (trim($response->suggestedAnswer) === '' || $response->isEscalationRequired) {
            return 0;
        }

        if (($context['has_stale_data'] ?? false) === true) {
            return 30;
        }

        $evidenceScore = 40;
        $hasGrounding = false;

        if (!empty($context['kb'])) {
            $evidenceScore += 35;
            $hasGrounding = true;
        }
        if (!empty($context['orders'])) {
            $evidenceScore += 30;
            $hasGrounding = true;
        }
        if (!empty($context['products'])) {
            $evidenceScore += 25;
            $hasGrounding = true;
        }
        if (!empty($context['customer_summary'])) {
            $evidenceScore += 10;
        }

        $citations = (array) ($context['citations'] ?? []);
        $evidenceScore += min(10, count($citations) * 3);

        $query = mb_strtolower((string) ($context['query'] ?? ''));
        $isLowRisk = preg_match('/^(merhaba|selam|iyi günler|teşekkür|tesekkur|yardım|yardim)[\s\p{P}]*$/u', $query) === 1;
        if (!$hasGrounding && $isLowRisk) {
            $evidenceScore += 35;
        }

        if (!$hasGrounding && !$isLowRisk) {
            $evidenceScore = min($evidenceScore, 65);
        }

        $evidenceScore = min(98, $evidenceScore);
        $providerScore = max(0, min(100, $response->confidence));

        if ($providerScore > 0 && $providerScore < 70) {
            return min($providerScore, $evidenceScore);
        }

        if ($providerScore > 0) {
            return (int) round(($evidenceScore * 0.75) + ($providerScore * 0.25));
        }

        return $evidenceScore;
    }
}
