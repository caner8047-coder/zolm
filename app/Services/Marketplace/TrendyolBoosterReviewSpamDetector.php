<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Str;

class TrendyolBoosterReviewSpamDetector
{
    private const SHORT_REVIEW_THRESHOLD = 10;

    private const REPEATED_PATTERN_THRESHOLD = 3;

    private const REPEATED_PATTERN_DAYS = 30;

    private const SPAM_SCORE_THRESHOLD = 0.7;

    /**
     * Tek bir yorumu spam açısından analiz eder.
     *
     * @param  array{comment: string, rating: int, reviewer_name_hash: string}  $review
     * @param  \Closure|null  $recentReviewsCallback  Aynı kullanıcının son yorumlarını getiren callback
     * @return array{score: float, flags: array<int, string>, is_spam: bool}
     */
    public function detect(array $review, ?\Closure $recentReviewsCallback = null): array
    {
        $flags = [];
        $score = 0.0;
        $comment = trim((string) ($review['comment'] ?? ''));
        $commentLength = mb_strlen($comment);

        // 1. Çok kısa yorum
        if ($commentLength < self::SHORT_REVIEW_THRESHOLD) {
            $flags[] = 'too_short';
            $score += 0.3;
        }

        // Yaygın anlamsız tek kelimelik yorumlar
        $meaninglessWords = ['iyi', 'güzel', 'süper', 'beğendim', 'tamam', 'ok', 'idare', 'fena', 'normal', 'düz'];
        $lowerComment = mb_strtolower($comment, 'UTF-8');
        if (in_array($lowerComment, $meaninglessWords, true)) {
            $flags[] = 'meaningless_single_word';
            $score += 0.2;
        }

        // 2. Sadece noktalama / emoji / boşluk
        $stripped = preg_replace('/[\p{P}\p{S}\s]/u', '', $comment);
        if ($commentLength > 0 && mb_strlen($stripped) === 0) {
            $flags[] = 'no_text_content';
            $score += 0.4;
        }

        // 3. Tekrarlanan karakterler (aaaaa, ....., !!!!!)
        if (preg_match('/(.)\1{4,}/u', $comment)) {
            $flags[] = 'repeated_chars';
            $score += 0.15;
        }

        // 4. Tüm harfleri büyük
        $lettersOnly = preg_replace('/[^\p{L}]/u', '', $comment);
        if (mb_strlen($lettersOnly) > 10 && mb_strtoupper($comment, 'UTF-8') === $comment) {
            $flags[] = 'all_caps';
            $score += 0.1;
        }

        // 5. Yasaklı kelimeler (basit liste)
        $blockedKeywords = ['spam', 'reklam', 'tıkla', 'bedava', 'kazandın', 'hediye'];
        foreach ($blockedKeywords as $keyword) {
            if (Str::contains($lowerComment, $keyword)) {
                $flags[] = 'blocked_keyword';
                $score += 0.5;
                break;
            }
        }

        // 6. Aynı kullanıcıdan tekrarlanan pattern (DB sorgusu gerekli)
        if ($recentReviewsCallback && ! empty($review['reviewer_name_hash'])) {
            $recent = $recentReviewsCallback($review['reviewer_name_hash']);
            if ($recent >= self::REPEATED_PATTERN_THRESHOLD) {
                $flags[] = 'repeated_pattern';
                $score += 0.25;
            }
        }

        $score = min(1.0, $score);

        return [
            'score' => $score,
            'flags' => $flags,
            'is_spam' => $score >= self::SPAM_SCORE_THRESHOLD,
        ];
    }
}
