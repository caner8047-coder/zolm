<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trendyol_booster_review_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('marketplace_store_id')->nullable()->constrained('marketplace_stores')->nullOnDelete();
            $table->string('store_name', 180);
            $table->string('store_url', 1000);
            $table->string('store_url_hash', 64);
            $table->string('merchant_id', 80);
            $table->boolean('is_active')->default(true);
            $table->datetime('verified_at')->nullable();
            $table->unsignedInteger('verified_product_count')->default(0);
            $table->datetime('last_scanned_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'merchant_id'], 'tb_review_sources_user_merchant_unique');
            $table->unique(['user_id', 'store_url_hash'], 'tb_review_sources_user_url_unique');
            $table->index(['user_id', 'is_active'], 'tb_review_sources_user_active_idx');
        });

        Schema::table('trendyol_booster_review_syncs', function (Blueprint $table) {
            $table->foreignId('review_source_id')->nullable()->after('user_id')
                ->constrained('trendyol_booster_review_sources')->nullOnDelete();
        });

        Schema::table('trendyol_booster_reviews', function (Blueprint $table) {
            $table->foreignId('review_source_id')->nullable()->after('user_id')
                ->constrained('trendyol_booster_review_sources')->nullOnDelete();
            $table->index(['user_id', 'review_source_id', 'status'], 'tb_reviews_source_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('trendyol_booster_reviews', function (Blueprint $table) {
            $table->dropIndex('tb_reviews_source_status_idx');
            $table->dropConstrainedForeignId('review_source_id');
        });

        Schema::table('trendyol_booster_review_syncs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('review_source_id');
        });

        Schema::dropIfExists('trendyol_booster_review_sources');
    }
};
