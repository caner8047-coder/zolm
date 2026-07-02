<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trendyol_booster_store_watch_items', function (Blueprint $table) {
            $table->unsignedInteger('stock_quantity')->nullable()->after('stock_status');
        });

        Schema::table('trendyol_booster_store_item_histories', function (Blueprint $table) {
            $table->unsignedBigInteger('trendyol_booster_store_watch_snapshot_id')->nullable()->after('trendyol_booster_store_watch_item_id');
            $table->unsignedInteger('favorite_count')->nullable()->after('review_count');
            $table->unsignedInteger('stock_quantity')->nullable()->after('favorite_count');
            $table->string('stock_status', 50)->nullable()->after('stock_quantity');

            $table->foreign('trendyol_booster_store_watch_snapshot_id', 'tr_booster_st_item_hist_snapshot_fk')
                ->references('id')
                ->on('trendyol_booster_store_watch_snapshots')
                ->nullOnDelete();
            $table->index(['trendyol_booster_store_watch_snapshot_id'], 'tr_booster_st_item_hist_snapshot_idx');
        });
    }

    public function down(): void
    {
        Schema::table('trendyol_booster_store_item_histories', function (Blueprint $table) {
            $table->dropForeign('tr_booster_st_item_hist_snapshot_fk');
            $table->dropIndex('tr_booster_st_item_hist_snapshot_idx');
            $table->dropColumn([
                'trendyol_booster_store_watch_snapshot_id',
                'favorite_count',
                'stock_quantity',
                'stock_status',
            ]);
        });

        Schema::table('trendyol_booster_store_watch_items', function (Blueprint $table) {
            $table->dropColumn('stock_quantity');
        });
    }
};
