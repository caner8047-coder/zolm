<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trendyol_booster_store_watches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('store_url', 1000);
            $table->string('store_url_hash', 64);
            $table->string('store_id', 80)->nullable();
            $table->string('store_name', 180);
            $table->unsignedInteger('total_products')->default(0);
            $table->unsignedInteger('best_seller_count')->default(0);
            $table->unsignedInteger('new_product_count')->default(0);
            $table->unsignedInteger('price_change_count')->default(0);
            $table->unsignedInteger('scan_count')->default(0);
            $table->unsignedInteger('last_scan_duration_ms')->default(0);
            $table->json('brand_distribution')->nullable();
            $table->json('raw_payload')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'store_url_hash'], 'tr_booster_store_user_url_unique');
            $table->index(['user_id', 'is_active', 'last_checked_at'], 'tr_booster_store_user_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trendyol_booster_store_watches');
    }
};
