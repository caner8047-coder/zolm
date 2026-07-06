<?php

namespace App\Enums;

enum AdChannelCode: string
{
    case ProductAds = 'trendyol_product';
    case StoreAds = 'trendyol_store';
    case InfluencerAds = 'trendyol_influencer';
    case MetaAds = 'trendyol_meta';

    public function label(): string
    {
        return match ($this) {
            self::ProductAds => 'Ürün Reklamları',
            self::StoreAds => 'Mağaza Reklamları',
            self::InfluencerAds => 'Influencer Reklamları',
            self::MetaAds => 'Meta Reklamları',
        };
    }
}
