<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trendyol_booster_store_watch_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('trendyol_booster_store_watch_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('trendyol_product_id', 80)->nullable();
            $table->string('source_url', 1000)->nullable();
            $table->string('title')->nullable();
            $table->string('brand', 120)->nullable();
            $table->decimal('sale_price', 12, 2)->default(0);
            $table->decimal('previous_sale_price', 12, 2)->nullable();
            $table->decimal('price_delta', 12, 2)->default(0);
            $table->unsignedSmallInteger('rank')->nullable();
            $table->boolean('is_new')->default(false);
            $table->json('raw_payload')->nullable();
            $table->timestamp('checked_at')->useCurrent();
            $table->timestamps();

            $table->index(['user_id', 'checked_at'], 'tr_booster_store_item_user_checked_idx');
            $table->index(['trendyol_booster_store_watch_id', 'rank'], 'tr_booster_store_item_watch_rank_idx');
            $table->foreign('trendyol_booster_store_watch_id', 'tr_booster_store_item_watch_fk')
                ->references('id')
                ->on('trendyol_booster_store_watches')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trendyol_booster_store_watch_items');
    }
};
