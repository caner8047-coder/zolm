<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trendyol_booster_supplier_researches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source_url', 1000);
            $table->string('source_url_hash', 64);
            $table->string('trendyol_product_id', 80)->nullable();
            $table->string('title', 500);
            $table->string('brand', 120)->nullable();
            $table->string('category_name', 180)->nullable();
            $table->string('image_url', 1000)->nullable();
            $table->decimal('source_price', 12, 2)->default(0);
            $table->string('currency', 8)->default('TRY');
            $table->unsignedInteger('scan_count')->default(0);
            $table->unsignedSmallInteger('platform_count')->default(0);
            $table->unsignedSmallInteger('seller_count')->default(0);
            $table->unsignedSmallInteger('offer_count')->default(0);
            $table->unsignedSmallInteger('verified_offer_count')->default(0);
            $table->decimal('min_price', 12, 2)->nullable();
            $table->decimal('median_price', 12, 2)->nullable();
            $table->decimal('max_price', 12, 2)->nullable();
            $table->decimal('price_spread_percent', 8, 2)->nullable();
            $table->unsignedTinyInteger('market_fit_score')->nullable();
            $table->unsignedTinyInteger('confidence_score')->default(0);
            $table->string('risk_level', 20)->default('unknown');
            $table->string('verdict', 500)->nullable();
            $table->string('search_query', 1000)->nullable();
            $table->string('search_url', 1000)->nullable();
            $table->uuid('last_scan_uuid')->nullable();
            $table->json('raw_payload')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'source_url_hash'], 'tr_booster_supplier_user_url_unique');
            $table->index(['user_id', 'is_active', 'last_checked_at'], 'tr_booster_supplier_user_active_idx');
        });

        Schema::create('trendyol_booster_supplier_offers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('trendyol_booster_supplier_research_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('scan_uuid');
            $table->string('offer_key', 64);
            $table->string('platform', 50);
            $table->string('platform_label', 100);
            $table->string('seller_name', 180)->nullable();
            $table->string('seller_id', 80)->nullable();
            $table->string('external_product_id', 120)->nullable();
            $table->string('title', 500);
            $table->string('source_url', 1000);
            $table->string('source_url_hash', 64);
            $table->decimal('sale_price', 12, 2)->default(0);
            $table->decimal('previous_sale_price', 12, 2)->nullable();
            $table->decimal('price_delta', 12, 2)->default(0);
            $table->unsignedInteger('stock')->nullable();
            $table->unsignedInteger('previous_stock')->nullable();
            $table->unsignedInteger('estimated_sales')->default(0);
            $table->string('availability', 40)->default('unknown');
            $table->unsignedTinyInteger('match_score')->default(0);
            $table->string('match_status', 20)->default('review');
            $table->string('source_type', 40)->default('google');
            $table->unsignedSmallInteger('rank')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('observed_at')->useCurrent();
            $table->timestamps();

            $table->unique(['trendyol_booster_supplier_research_id', 'scan_uuid', 'offer_key'], 'tr_booster_supplier_offer_scan_unique');
            $table->index(['user_id', 'observed_at'], 'tr_booster_supplier_offer_user_seen_idx');
            $table->index(['trendyol_booster_supplier_research_id', 'scan_uuid', 'rank'], 'tr_booster_supplier_offer_scan_rank_idx');
            $table->foreign('trendyol_booster_supplier_research_id', 'tr_booster_supplier_offer_research_fk')
                ->references('id')
                ->on('trendyol_booster_supplier_researches')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trendyol_booster_supplier_offers');
        Schema::dropIfExists('trendyol_booster_supplier_researches');
    }
};
