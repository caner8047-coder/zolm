<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trendyol_booster_store_watch_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('trendyol_booster_store_watch_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('scan_number')->default(1);
            $table->string('status', 30)->default('ok');
            $table->string('message', 500)->nullable();
            $table->string('store_id', 80)->nullable();
            $table->string('store_name', 180);
            $table->string('store_url', 1000);
            $table->unsignedInteger('total_products')->default(0);
            $table->unsignedInteger('active_product_count')->default(0);
            $table->unsignedInteger('new_product_count')->default(0);
            $table->unsignedInteger('removed_product_count')->default(0);
            $table->unsignedInteger('price_change_count')->default(0);
            $table->unsignedInteger('campaign_count')->default(0);
            $table->unsignedInteger('top_seller_count')->default(0);
            $table->unsignedInteger('total_reviews')->default(0);
            $table->unsignedInteger('total_favorites')->default(0);
            $table->decimal('avg_price', 10, 2)->nullable();
            $table->decimal('min_price', 10, 2)->nullable();
            $table->decimal('max_price', 10, 2)->nullable();
            $table->decimal('avg_rating', 2, 1)->nullable();
            $table->decimal('store_rating', 2, 1)->nullable();
            $table->json('brand_distribution')->nullable();
            $table->json('category_distribution')->nullable();
            $table->json('price_summary')->nullable();
            $table->json('change_summary')->nullable();
            $table->json('raw_payload')->nullable();
            $table->unsignedInteger('scan_duration_ms')->default(0);
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->foreign('trendyol_booster_store_watch_id', 'tr_booster_store_snapshot_watch_fk')
                ->references('id')
                ->on('trendyol_booster_store_watches')
                ->cascadeOnDelete();
            $table->index(['trendyol_booster_store_watch_id', 'checked_at'], 'tr_booster_store_snapshot_watch_checked_idx');
            $table->index(['user_id', 'checked_at'], 'tr_booster_store_snapshot_user_checked_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trendyol_booster_store_watch_snapshots');
    }
};
