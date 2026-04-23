<?php

namespace App\Services\Returns;

use App\Models\ReturnIntakeItem;

class ReturnDecisionSuggestionService
{
    /**
     * @param  array<string, mixed>  $classification
     * @return array{decision: string, confidence: float, summary: string, reasons: array<int, string>}
     */
    public function suggest(ReturnIntakeItem $item, array $classification = []): array
    {
        $condition = (string) ($classification['condition_status'] ?? $item->condition_status ?? 'unknown');
        $confidence = (float) ($item->matching_confidence ?? 0);
        $verification = (string) ($item->product_verification_status ?? 'unverified');
        $issueTags = array_values(array_filter(array_map('strval', $classification['issue_tags'] ?? ($item->raw_summary_json['issue_tags'] ?? []))));
        $hasClaim = $item->channel_claim_id !== null;
        $hasDamageEvidence = $item->media->where('kind', 'damage')->isNotEmpty();
        $hasProductEvidence = $item->media->where('kind', 'product')->isNotEmpty();
        $hasOperatorBarcode = filled($item->operator_barcode);
        $hasProductSignal = $hasProductEvidence || $hasOperatorBarcode;
        $hasReliableMatch = $item->channel_order_id !== null || $item->channel_claim_id !== null;
        $normalizedTags = array_map(static fn (string $tag) => mb_strtolower($tag), $issueTags);

        if (!$hasReliableMatch || $confidence < 55) {
            return $this->makeSuggestion(
                'manual_review',
                96,
                'Sipariş eşleşmesi zayıf olduğu için kayıt manuel kontrolde tutulmalı.',
                ['weak_match']
            );
        }

        if ($verification === 'mismatch') {
            return $this->makeSuggestion(
                $hasClaim ? 'reject_marketplace' : 'manual_review',
                93,
                $hasClaim
                    ? 'Gelen ürün barkodu siparişle uyuşmuyor. Pazaryeri reddi için güçlü aday.'
                    : 'Gelen ürün barkodu siparişle uyuşmuyor. Önce manuel teyit gerekli.',
                ['barcode_mismatch']
            );
        }

        if ($condition === 'damaged') {
            $severeDamage = collect($normalizedTags)->contains(fn (string $tag) => in_array($tag, [
                'broken_part',
                'scratched_surface',
                'torn_package',
                'damaged_box',
                'missing_piece',
            ], true));

            if ($severeDamage && $hasDamageEvidence) {
                return $this->makeSuggestion(
                    $hasClaim ? 'reject_marketplace' : 'scrap',
                    88,
                    $hasClaim
                        ? 'Hasar kanıtı mevcut. Pazaryeri reddi veya itiraz akışı için uygun görünüyor.'
                        : 'Ürün hasarlı görünüyor ve kanıt fotoğrafları mevcut. Hurda kararı güçlü aday.',
                    ['damage_evidence']
                );
            }

            return $this->makeSuggestion(
                'manual_review',
                82,
                'Hasar bildirimi var ancak karar için operasyondan son kontrol alınmalı.',
                ['damage_review']
            );
        }

        if ($condition === 'undamaged' && $verification === 'matched' && $confidence >= 78 && $hasProductSignal) {
            return $this->makeSuggestion(
                $hasClaim ? 'approve_marketplace' : 'restock',
                91,
                $hasClaim
                    ? 'Ürün sağlam ve barkod eşleşiyor. Pazaryeri onayı için uygun.'
                    : 'Ürün sağlam ve barkod eşleşiyor. Doğrudan stoka alma adayı.',
                ['strong_match']
            );
        }

        if ($condition === 'undamaged' && $confidence >= 70) {
            return $this->makeSuggestion(
                'restock',
                76,
                'Hasar görünmüyor ve sipariş eşleşmesi yeterli. İç stok kararı için uygun.',
                ['restock_candidate']
            );
        }

        return $this->makeSuggestion(
            'manual_review',
            74,
            'Kayıt çözülebilir görünüyor ancak otomatik karar için veri henüz yeterince güçlü değil.',
            ['insufficient_evidence']
        );
    }

    /**
     * @param  array<int, string>  $reasons
     * @return array{decision: string, confidence: float, summary: string, reasons: array<int, string>}
     */
    protected function makeSuggestion(string $decision, float $confidence, string $summary, array $reasons): array
    {
        return [
            'decision' => $decision,
            'confidence' => min(99.99, max(0, $confidence)),
            'summary' => $summary,
            'reasons' => $reasons,
        ];
    }
}
