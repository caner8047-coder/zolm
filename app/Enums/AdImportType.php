<?php

namespace App\Enums;

enum AdImportType: string
{
    case ProductGeneralReport = 'product_general';
    case ProductCampaignReport = 'product_campaign';
    case StoreKeywordReport = 'store_keyword';
    case InfluencerReport = 'influencer';

    public function label(): string
    {
        return match ($this) {
            self::ProductGeneralReport => 'Ürün Reklamları Genel Rapor',
            self::ProductCampaignReport => 'Ürün Reklamları Kampanya-Ürün Rapor',
            self::StoreKeywordReport => 'Mağaza Reklamları Kelime Raporu',
            self::InfluencerReport => 'Influencer Raporu',
        };
    }

    public function channelCode(): AdChannelCode
    {
        return match ($this) {
            self::ProductGeneralReport, self::ProductCampaignReport => AdChannelCode::ProductAds,
            self::StoreKeywordReport => AdChannelCode::StoreAds,
            self::InfluencerReport => AdChannelCode::InfluencerAds,
        };
    }

    public function requiresCampaignContext(): bool
    {
        return match ($this) {
            self::ProductGeneralReport => false,
            self::ProductCampaignReport, self::StoreKeywordReport, self::InfluencerReport => true,
        };
    }
}
