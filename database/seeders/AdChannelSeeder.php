<?php

namespace Database\Seeders;

use App\Models\AdChannel;
use Illuminate\Database\Seeder;

class AdChannelSeeder extends Seeder
{
    public function run(): void
    {
        $channels = [
            ['code' => 'trendyol_product', 'name' => 'Ürün Reklamları'],
            ['code' => 'trendyol_store', 'name' => 'Mağaza Reklamları'],
            ['code' => 'trendyol_influencer', 'name' => 'Influencer Reklamları'],
            ['code' => 'trendyol_meta', 'name' => 'Meta Reklamları'],
        ];

        foreach ($channels as $channel) {
            AdChannel::updateOrCreate(
                ['code' => $channel['code']],
                ['name' => $channel['name']]
            );
        }
    }
}
