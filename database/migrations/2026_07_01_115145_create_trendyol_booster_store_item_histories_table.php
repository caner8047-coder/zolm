<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('trendyol_booster_store_item_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('trendyol_booster_store_watch_item_id');
            $table->foreign('trendyol_booster_store_watch_item_id', 'tr_booster_st_item_hist_fk')
                ->references('id')->on('trendyol_booster_store_watch_items')
                ->cascadeOnDelete();
            $table->decimal('sale_price', 12, 2)->nullable();
            $table->unsignedSmallInteger('rank')->nullable();
            $table->unsignedInteger('review_count')->nullable();
            $table->decimal('rating', 3, 2)->nullable();
            $table->boolean('is_campaign')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['trendyol_booster_store_watch_item_id', 'created_at'], 'tr_booster_store_item_hist_item_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trendyol_booster_store_item_histories');
    }
};
