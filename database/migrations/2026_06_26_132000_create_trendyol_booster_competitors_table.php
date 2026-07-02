<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trendyol_booster_competitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trendyol_booster_product_id')->constrained('trendyol_booster_products')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source_url', 1000);
            $table->string('source_url_hash', 64);
            $table->string('trendyol_product_id', 80)->nullable();
            $table->string('title')->nullable();
            $table->string('brand', 120)->nullable();
            $table->decimal('sale_price', 12, 2)->default(0);
            $table->string('currency', 3)->default('TRY');
            $table->string('stock_status', 40)->default('unknown');
            $table->string('availability', 120)->nullable();
            $table->decimal('price_delta_vs_own', 12, 2)->default(0);
            $table->decimal('price_gap_percent', 8, 2)->default(0);
            $table->string('opportunity_type', 40)->default('watch');
            $table->string('opportunity_note', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();

            $table->unique(['trendyol_booster_product_id', 'source_url_hash'], 'tr_booster_comp_product_url_unique');
            $table->index(['user_id', 'opportunity_type'], 'tr_booster_comp_user_opportunity_idx');
            $table->index(['user_id', 'is_active', 'last_checked_at'], 'tr_booster_comp_user_active_checked_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trendyol_booster_competitors');
    }
};
