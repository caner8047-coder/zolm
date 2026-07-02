<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trendyol_bestseller_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 180);
            $table->string('query', 180);
            $table->string('normalized_query', 180);
            $table->string('matched_label', 180)->nullable();
            $table->string('source_url', 1000)->nullable();
            $table->decimal('min_price', 12, 2)->nullable();
            $table->decimal('max_price', 12, 2)->nullable();
            $table->string('fingerprint', 64);
            $table->string('status', 20)->default('active');
            $table->unsignedInteger('run_count')->default(0);
            $table->unsignedSmallInteger('latest_product_count')->default(0);
            $table->timestamp('first_captured_at')->nullable();
            $table->timestamp('last_captured_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'fingerprint'], 'tr_best_report_user_fingerprint_uq');
            $table->index(['user_id', 'last_captured_at'], 'tr_best_report_user_last_idx');
        });

        Schema::create('trendyol_bestseller_report_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trendyol_bestseller_report_id')
                ->constrained('trendyol_bestseller_reports', 'id', 'tr_best_run_report_fk')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source', 40)->default('browser_companion');
            $table->string('source_url', 1000)->nullable();
            $table->unsignedSmallInteger('item_count')->default(0);
            $table->unsignedSmallInteger('priced_item_count')->default(0);
            $table->unsignedSmallInteger('in_stock_item_count')->default(0);
            $table->unsignedSmallInteger('campaign_item_count')->default(0);
            $table->decimal('average_price', 12, 2)->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamp('captured_at')->useCurrent();
            $table->timestamps();

            $table->index(['trendyol_bestseller_report_id', 'captured_at'], 'tr_best_run_report_captured_idx');
            $table->index(['user_id', 'captured_at'], 'tr_best_run_user_captured_idx');
        });

        Schema::create('trendyol_bestseller_report_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trendyol_bestseller_report_run_id')
                ->constrained('trendyol_bestseller_report_runs', 'id', 'tr_best_item_run_fk')
                ->cascadeOnDelete();
            $table->foreignId('trendyol_bestseller_report_id')
                ->constrained('trendyol_bestseller_reports', 'id', 'tr_best_item_report_fk')
                ->cascadeOnDelete();
            $table->foreignId('trendyol_booster_product_id')
                ->nullable()
                ->constrained('trendyol_booster_products', 'id', 'tr_best_item_product_fk')
                ->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('trendyol_product_id', 80);
            $table->string('source_url', 1000)->nullable();
            $table->string('title')->nullable();
            $table->string('brand', 120)->nullable();
            $table->string('image_url', 1000)->nullable();
            $table->unsignedSmallInteger('rank_position');
            $table->unsignedSmallInteger('previous_rank')->nullable();
            $table->smallInteger('rank_delta')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->string('seller_name', 180)->nullable();
            $table->string('seller_id', 80)->nullable();
            $table->decimal('seller_score', 5, 2)->nullable();
            $table->unsignedInteger('stock_quantity')->nullable();
            $table->string('stock_status', 40)->default('unknown');
            $table->unsignedSmallInteger('campaign_count')->default(0);
            $table->json('campaigns_json')->nullable();
            $table->unsignedInteger('estimated_sales_3d')->nullable();
            $table->decimal('estimated_revenue_3d', 14, 2)->nullable();
            $table->decimal('rating', 4, 2)->nullable();
            $table->unsignedInteger('rating_count')->default(0);
            $table->unsignedBigInteger('favorite_count')->nullable();
            $table->unsignedBigInteger('basket_count')->nullable();
            $table->unsignedBigInteger('view_count_24h')->nullable();
            $table->unsignedTinyInteger('data_quality_score')->default(0);
            $table->json('raw_payload')->nullable();
            $table->timestamp('captured_at')->useCurrent();
            $table->timestamps();

            $table->unique(
                ['trendyol_bestseller_report_run_id', 'trendyol_product_id'],
                'tr_best_item_run_product_uq'
            );
            $table->index(['trendyol_bestseller_report_id', 'rank_position'], 'tr_best_item_report_rank_idx');
            $table->index(['trendyol_bestseller_report_id', 'trendyol_product_id', 'captured_at'], 'tr_best_item_product_history_idx');
            $table->index(['user_id', 'captured_at'], 'tr_best_item_user_captured_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trendyol_bestseller_report_items');
        Schema::dropIfExists('trendyol_bestseller_report_runs');
        Schema::dropIfExists('trendyol_bestseller_reports');
    }
};
