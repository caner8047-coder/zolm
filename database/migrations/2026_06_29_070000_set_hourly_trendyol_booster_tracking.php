<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trendyol_booster_products')
            || ! Schema::hasColumn('trendyol_booster_products', 'tracking_status')
            || ! Schema::hasColumn('trendyol_booster_products', 'analysis_refresh_interval_minutes')) {
            return;
        }

        DB::table('trendyol_booster_products')
            ->where('tracking_status', 'active')
            ->update([
                'watch_stock' => true,
                'analysis_auto_refresh_enabled' => true,
                'analysis_refresh_interval_minutes' => 60,
                'next_analysis_refresh_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Kullanıcının takip tercihlerini geriye doğru değiştirmiyoruz.
    }
};
