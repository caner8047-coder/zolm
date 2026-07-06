<?php

namespace App\Services\Ads;

use App\Models\AdCampaign;
use Illuminate\Support\Facades\DB;

class AdCampaignMatcher
{
    /**
     * Kampanya bul veya oluştur
     */
    public function findOrCreate(
        int $userId,
        int $adAccountId,
        string $campaignName,
        string $channelCode,
        ?string $startAt = null,
        ?string $externalCampaignId = null
    ): AdCampaign {
        // 1. external_campaign_id varsa kesin eşleşme ara
        if ($externalCampaignId) {
            $campaign = AdCampaign::where('ad_account_id', $adAccountId)
                ->where('external_campaign_id', $externalCampaignId)
                ->first();

            if ($campaign) {
                return $campaign;
            }
        }

        // 2. start_at doluysa aday eşleştirme yap
        if ($startAt) {
            $identityHash = $this->generateIdentityHash($adAccountId, $channelCode, $campaignName, $startAt);

            $campaign = AdCampaign::where('ad_account_id', $adAccountId)
                ->where('campaign_identity_hash', $identityHash)
                ->first();

            if ($campaign) {
                // external_campaign_id geldiyse güncelle
                if ($externalCampaignId && !$campaign->external_campaign_id) {
                    $campaign->update(['external_campaign_id' => $externalCampaignId]);
                }
                return $campaign;
            }

            // Aday sayısı kontrolü
            $candidates = AdCampaign::where('ad_account_id', $adAccountId)
                ->where('channel_code', $channelCode)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($campaignName)])
                ->where('start_at', $startAt)
                ->get();

            if ($candidates->count() === 1) {
                $campaign = $candidates->first();
                if ($externalCampaignId && !$campaign->external_campaign_id) {
                    $campaign->update(['external_campaign_id' => $externalCampaignId]);
                }
                return $campaign;
            }

            if ($candidates->count() > 1) {
                // Birden fazla aday varsa ilkini kullan (gerçek kullanımda kullanıcıya sorulur)
                return $candidates->first();
            }
        }

        // 3. Yeni kampanya oluştur
        $campaignKey = $this->generateCampaignKey($adAccountId, $channelCode, $campaignName, $startAt);
        $identityHash = $startAt
            ? $this->generateIdentityHash($adAccountId, $channelCode, $campaignName, $startAt)
            : hash('sha256', "{$adAccountId}|{$channelCode}|" . mb_strtolower($campaignName) . "|pending");

        return AdCampaign::create([
            'user_id' => $userId,
            'ad_account_id' => $adAccountId,
            'channel_code' => $channelCode,
            'external_campaign_id' => $externalCampaignId,
            'campaign_identity_hash' => $identityHash,
            'campaign_key' => $campaignKey,
            'name' => $campaignName,
            'status' => 'active',
            'start_at' => $startAt,
        ]);
    }

    /**
     * ID ile kampanya bul
     */
    public function findById(int $campaignId, int $userId): AdCampaign
    {
        return AdCampaign::where('id', $campaignId)
            ->where('user_id', $userId)
            ->firstOrFail();
    }

    /**
     * Campaign identity hash üretimi
     */
    protected function generateIdentityHash(
        int $adAccountId,
        string $channelCode,
        string $campaignName,
        ?string $startAt
    ): string {
        $data = implode('|', [
            $adAccountId,
            $channelCode,
            mb_strtolower(trim($campaignName)),
            $startAt ?? '',
        ]);

        return hash('sha256', $data);
    }

    /**
     * Campaign key üretimi
     */
    protected function generateCampaignKey(
        int $adAccountId,
        string $channelCode,
        string $campaignName,
        ?string $startAt
    ): string {
        return implode('|', [
            $channelCode,
            mb_strtolower(trim($campaignName)),
            $startAt ?? 'unknown',
            $adAccountId,
        ]);
    }
}
