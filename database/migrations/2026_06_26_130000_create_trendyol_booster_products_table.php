<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trendyol_booster_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mp_product_id')->nullable()->constrained('mp_products')->nullOnDelete();
            $table->foreignId('channel_listing_id')->nullable()->constrained('channel_listings')->nullOnDelete();
            $table->string('source_url', 1000);
            $table->string('source_url_hash', 64);
            $table->string('trendyol_product_id', 80)->nullable();
            $table->string('title')->nullable();
            $table->string('brand', 120)->nullable();
            $table->string('category_name', 180)->nullable();
            $table->decimal('sale_price', 12, 2)->default(0);
            $table->string('currency', 3)->default('TRY');
            $table->decimal('commission_rate', 6, 2)->default(0);
            $table->decimal('cogs', 12, 2)->default(0);
            $table->decimal('packaging_cost', 12, 2)->default(0);
            $table->decimal('cargo_cost', 12, 2)->default(0);
            $table->decimal('return_rate', 6, 2)->default(0);
            $table->decimal('vat_rate', 6, 2)->default(20);
            $table->decimal('cost_vat_rate', 6, 2)->default(20);
            $table->decimal('net_profit', 12, 2)->default(0);
            $table->decimal('profit_margin_percent', 8, 2)->default(0);
            $table->decimal('break_even_price', 12, 2)->nullable();
            $table->decimal('target_price', 12, 2)->nullable();
            $table->unsignedTinyInteger('opportunity_score')->default(0);
            $table->string('decision_status', 30)->default('watch');
            $table->json('decision_reasons')->nullable();
            $table->json('simulation_json')->nullable();
            $table->boolean('watch_price')->default(true);
            $table->boolean('watch_stock')->default(false);
            $table->boolean('watch_keyword')->default(false);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'source_url_hash'], 'tr_booster_user_url_unique');
            $table->index(['user_id', 'decision_status'], 'tr_booster_user_decision_idx');
            $table->index(['user_id', 'opportunity_score'], 'tr_booster_user_score_idx');
            $table->index(['user_id', 'watch_price'], 'tr_booster_user_price_watch_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trendyol_booster_products');
    }
};
