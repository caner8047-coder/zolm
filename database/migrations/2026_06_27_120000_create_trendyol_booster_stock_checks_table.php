<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trendyol_booster_stock_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('trendyol_booster_product_id')->nullable();
            $table->string('source_url', 1000);
            $table->string('source_url_hash', 64);
            $table->string('trendyol_product_id', 80)->nullable();
            $table->string('barcode', 120)->nullable();
            $table->string('title')->nullable();
            $table->string('brand', 120)->nullable();
            $table->string('image_url', 1000)->nullable();
            $table->unsignedInteger('total_stock')->default(0);
            $table->unsignedInteger('previous_total_stock')->nullable();
            $table->integer('stock_delta')->default(0);
            $table->unsignedInteger('estimated_sales')->default(0);
            $table->unsignedSmallInteger('seller_count')->default(0);
            $table->string('stock_status', 40)->default('unknown');
            $table->json('raw_payload')->nullable();
            $table->timestamp('checked_at')->useCurrent();
            $table->timestamps();

            $table->index(['user_id', 'checked_at'], 'tr_booster_stock_user_checked_idx');
            $table->index(['user_id', 'source_url_hash', 'checked_at'], 'tr_booster_stock_user_url_checked_idx');
            $table->index(['user_id', 'trendyol_product_id'], 'tr_booster_stock_user_product_idx');
            $table->foreign('trendyol_booster_product_id', 'tr_booster_stock_product_fk')
                ->references('id')
                ->on('trendyol_booster_products')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trendyol_booster_stock_checks');
    }
};
