<?php

namespace App\Services\Ads;

use App\Models\AdCampaign;
use App\Models\AdCampaignSnapshot;
use App\Models\AdKeywordSnapshot;
use App\Models\InfluencerCreatorSnapshot;
use App\Models\AdRecommendation;
use App\Enums\AdChannelCode;
use App\Enums\AdRecommendationPriority;
use App\Enums\AdRecommendationCategory;

class RuleEngine
{
    /**
     * Tüm kuralları çalıştır ve öneriler üret
     */
    public function runAllRules(int $userId): array
    {
        $recommendations = [];

        $recommendations = array_merge($recommendations, $this->runProductAdRules($userId));
        $recommendations = array_merge($recommendations, $this->runStoreAdRules($userId));
        $recommendations = array_merge($recommendations, $this->runInfluencerRules($userId));

        return $recommendations;
    }

    /**
     * Ürün Reklamı Kuralları
     */
    protected function runProductAdRules(int $userId): array
    {
        $recommendations = [];

        $campaigns = AdCampaign::where('user_id', $userId)
            ->where('channel_code', AdChannelCode::ProductAds->value)
            ->with('latestSnapshot')
            ->get();

        foreach ($campaigns as $campaign) {
            $snapshot = $campaign->latestSnapshot;
            if (!$snapshot) continue;

            // Kural 1: Yeterli harcama + tıklama, satış 0 → inceleme
            if ($snapshot->spend > 100 && $snapshot->clicks > 50 && $snapshot->sales_total == 0) {
                $recommendations[] = $this->createRecommendation(
                    $userId,
                    AdChannelCode::ProductAds->value,
                    'campaign',
                    $campaign->id,
                    AdRecommendationPriority::High,
                    AdRecommendationCategory::Profitability,
                    'Sıfır satışlı kampanya',
                    "Kampanya \"{$campaign->name}\" için {$snapshot->spend} ₺ harcama ve {$snapshot->clicks} tıklama var ancak satış bulunmuyor.",
                    'Kampanyayı inceleyin veya durdurmayı değerlendirin.',
                    ['spend' => $snapshot->spend, 'clicks' => $snapshot->clicks, 'sales' => $snapshot->sales_total],
                    0.85
                );
            }

            // Kural 2: ROAS hedef üstü + yeterli satış + stok güçlü → bütçe artır
            if ($snapshot->roas >= 5 && $snapshot->sales_total >= 5) {
                $recommendations[] = $this->createRecommendation(
                    $userId,
                    AdChannelCode::ProductAds->value,
                    'campaign',
                    $campaign->id,
                    AdRecommendationPriority::High,
                    AdRecommendationCategory::Budget,
                    'Bütçeyi kontrollü artır',
                    "Kampanya \"{$campaign->name}\" yüksek ROAS ({$snapshot->roas}) ve yeterli satış ({$snapshot->sales_total}) üretiyor.",
                    'Günlük bütçeyi %10-15 artırın.',
                    ['roas' => $snapshot->roas, 'sales' => $snapshot->sales_total],
                    0.90
                );
            }

            // Kural 3: ROAS yüksek ama 1 satış → veri hacmi düşük
            if ($snapshot->roas >= 5 && $snapshot->sales_total == 1) {
                $recommendations[] = $this->createRecommendation(
                    $userId,
                    AdChannelCode::ProductAds->value,
                    'campaign',
                    $campaign->id,
                    AdRecommendationPriority::Medium,
                    AdRecommendationCategory::DataQuality,
                    'Düşük veri hacmi',
                    "Kampanya \"{$campaign->name}\" yüksek ROAS ({$snapshot->roas}) gösteriyor ancak sadece 1 satış var.",
                    'Veri hacmi yeterli değil, hemen bütçe artırımı yapmayın.',
                    ['roas' => $snapshot->roas, 'sales' => $snapshot->sales_total],
                    0.70
                );
            }

            // Kural 4: Net katkı negatif → bütçe azalt/durdur
            if ($snapshot->revenue_total > 0 && $snapshot->spend > 0) {
                $netContribution = $snapshot->revenue_total - $snapshot->spend;
                if ($netContribution < 0 && abs($netContribution) > 100) {
                    $recommendations[] = $this->createRecommendation(
                        $userId,
                        AdChannelCode::ProductAds->value,
                        'campaign',
                        $campaign->id,
                        AdRecommendationPriority::Critical,
                        AdRecommendationCategory::Profitability,
                        'Net katkı negatif',
                        "Kampanya \"{$campaign->name}\" negatif net katkı üretiyor: {$netContribution} ₺.",
                        'Bütçeyi azaltın veya kampanyayı durdurun.',
                        ['net_contribution' => $netContribution, 'spend' => $snapshot->spend, 'revenue' => $snapshot->revenue_total],
                        0.95
                    );
                }
            }
        }

        return $recommendations;
    }

    /**
     * Mağaza Reklamı Kuralları
     */
    protected function runStoreAdRules(int $userId): array
    {
        $recommendations = [];

        $keywords = AdKeywordSnapshot::whereHas('campaign', function ($q) use ($userId) {
            $q->where('user_id', $userId)
              ->where('channel_code', AdChannelCode::StoreAds->value);
        })->get();

        foreach ($keywords as $keyword) {
            // Kural: Kelime yüksek harcama, satış 0 → israf uyarısı
            if ($keyword->spend > 50 && $keyword->sales_total == 0) {
                $recommendations[] = $this->createRecommendation(
                    $userId,
                    AdChannelCode::StoreAds->value,
                    'keyword',
                    $keyword->id,
                    AdRecommendationPriority::High,
                    AdRecommendationCategory::Keyword,
                    'Kelime israfı uyarısı',
                    "\"{$keyword->keyword}\" kelimesi için {$keyword->spend} ₺ harcama yapılmış ancak satış yok.",
                    'Bu kelimeyi kampanyadan çıkarın veya teklifinizi düşürün.',
                    ['keyword' => $keyword->keyword, 'spend' => $keyword->spend],
                    0.80
                );
            }
        }

        return $recommendations;
    }

    /**
     * Influencer Kuralları
     */
    protected function runInfluencerRules(int $userId): array
    {
        $recommendations = [];

        $creators = InfluencerCreatorSnapshot::whereHas('campaign', function ($q) use ($userId) {
            $q->where('user_id', $userId)
              ->where('channel_code', AdChannelCode::InfluencerAds->value);
        })->with('influencerProfile')
          ->get()
          ->groupBy('influencer_profile_id');

        foreach ($creators as $profileId => $snapshots) {
            $totalVisits = $snapshots->sum('link_visits');
            $totalSales = $snapshots->sum('sales_total');
            $totalRevenue = $snapshots->sum('revenue_total');
            $creatorName = $snapshots->first()->influencerProfile?->handle ?? 'Bilinmeyen';

            // Kural: 500+ ziyaret, 0 satış → uyum inceleme
            if ($totalVisits > 500 && $totalSales == 0) {
                $recommendations[] = $this->createRecommendation(
                    $userId,
                    AdChannelCode::InfluencerAds->value,
                    'creator',
                    $profileId,
                    AdRecommendationPriority::High,
                    AdRecommendationCategory::Creator,
                    'Creator uyumsuzluğu',
                    "\"{$creatorName}\" {$totalVisits} ziyaret getirmiş ancak satış yapmamış.",
                    'Creator, teklif ve ürün uyumunu inceleyin.',
                    ['link_visits' => $totalVisits, 'sales' => $totalSales],
                    0.85
                );
            }

            // Kural: Düşük ziyaretle satış var → kontrollü tekrar test
            if ($totalVisits < 50 && $totalSales > 0) {
                $recommendations[] = $this->createRecommendation(
                    $userId,
                    AdChannelCode::InfluencerAds->value,
                    'creator',
                    $profileId,
                    AdRecommendationPriority::Medium,
                    AdRecommendationCategory::Creator,
                    'Düşük ziyaret, yüksek dönüşüm',
                    "\"{$creatorName}\" az ziyaret ({$totalVisits}) ile {$totalSales} satış yapmış.",
                    'Kontrollü bir tekrar testi düşünün.',
                    ['link_visits' => $totalVisits, 'sales' => $totalSales],
                    0.75
                );
            }
        }

        return $recommendations;
    }

    /**
     * Öneri oluştur
     */
    protected function createRecommendation(
        int $userId,
        string $channelCode,
        string $entityType,
        int $entityId,
        AdRecommendationPriority $priority,
        AdRecommendationCategory $category,
        string $title,
        string $description,
        string $recommendedAction,
        array $evidence,
        float $confidenceScore
    ): AdRecommendation {
        ksort($evidence);
        $evidenceHash = hash('sha256', json_encode($evidence, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $existing = AdRecommendation::where('user_id', $userId)
            ->where('channel_code', $channelCode)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('title', $title)
            ->latest()
            ->get()
            ->first(function (AdRecommendation $recommendation) use ($evidenceHash): bool {
                $metadataHash = $recommendation->metadata['evidence_hash'] ?? null;

                if ($metadataHash) {
                    return hash_equals($metadataHash, $evidenceHash);
                }

                $storedEvidence = $recommendation->evidence ?? [];
                ksort($storedEvidence);

                return hash_equals(
                    hash('sha256', json_encode($storedEvidence, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
                    $evidenceHash
                );
            });

        if ($existing) {
            if (in_array($existing->status, ['new', 'viewed', 'snoozed'], true)) {
                $existing->update([
                    'priority' => $priority->value,
                    'category' => $category->value,
                    'description' => $description,
                    'recommended_action' => $recommendedAction,
                    'confidence_score' => $confidenceScore,
                    'metadata' => array_merge($existing->metadata ?? [], [
                        'rule_version' => '1.1.0',
                        'last_evaluated_at' => now()->toISOString(),
                        'evidence_hash' => $evidenceHash,
                    ]),
                ]);
            }

            return $existing;
        }

        return AdRecommendation::create([
            'user_id' => $userId,
            'channel_code' => $channelCode,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'priority' => $priority->value,
            'category' => $category->value,
            'title' => $title,
            'description' => $description,
            'recommended_action' => $recommendedAction,
            'evidence' => $evidence,
            'confidence_score' => $confidenceScore,
            'status' => 'new',
            'generated_by' => 'rule',
            'metadata' => [
                'rule_version' => '1.1.0',
                'generated_at' => now()->toISOString(),
                'evidence_hash' => $evidenceHash,
            ],
        ]);
    }
}
