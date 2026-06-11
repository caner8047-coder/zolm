<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            !Schema::hasTable('marketplace_stores')
            || !Schema::hasTable('channel_listings')
            || !Schema::hasColumn('channel_listings', 'commission_rate')
            || !Schema::hasColumn('channel_listings', 'commission_source')
            || !Schema::hasColumn('channel_listings', 'commission_synced_at')
        ) {
            return;
        }

        $storeIds = DB::table('marketplace_stores')
            ->whereRaw('LOWER(marketplace) = ?', ['koctas'])
            ->pluck('id');

        if ($storeIds->isEmpty()) {
            return;
        }

        DB::table('channel_listings')
            ->whereIn('store_id', $storeIds)
            ->update([
                'commission_rate' => null,
                'commission_source' => 'product_fallback',
                'commission_synced_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Eski hatalı Koçtaş oranlarını güvenilir biçimde geri kuracak kaynak yok.
    }
};
