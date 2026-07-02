<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trendyol_booster_stock_sellers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('trendyol_booster_stock_check_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('seller_name', 180);
            $table->string('seller_id', 80)->nullable();
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('previous_stock')->nullable();
            $table->integer('stock_delta')->default(0);
            $table->unsignedInteger('estimated_sales')->default(0);
            $table->decimal('sale_price', 12, 2)->default(0);
            $table->decimal('seller_score', 5, 2)->nullable();
            $table->string('shipping_note', 180)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'seller_name'], 'tr_booster_stock_seller_user_name_idx');
            $table->foreign('trendyol_booster_stock_check_id', 'tr_booster_stock_seller_check_fk')
                ->references('id')
                ->on('trendyol_booster_stock_checks')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trendyol_booster_stock_sellers');
    }
};
