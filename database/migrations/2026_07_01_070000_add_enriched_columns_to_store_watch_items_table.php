<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trendyol_booster_store_watch_items', function (Blueprint $table) {
            $table->string('image_url', 500)->nullable()->after('source_url');
            $table->decimal('rating', 2, 1)->nullable()->after('brand');
            $table->unsignedInteger('review_count')->nullable()->after('rating');
            $table->unsignedInteger('favorite_count')->nullable()->after('review_count');
            $table->json('campaign_badges')->nullable()->after('favorite_count');
            $table->boolean('is_first_seller')->default(false)->after('campaign_badges');
            $table->decimal('original_price', 10, 2)->nullable()->after('sale_price');
            $table->decimal('discount_rate', 5, 2)->nullable()->after('original_price');
            $table->string('category_name', 255)->nullable()->after('discount_rate');
            $table->string('seller_name', 255)->nullable()->after('category_name');
            $table->string('stock_status', 50)->nullable()->after('seller_name');
            $table->decimal('previous_rating', 2, 1)->nullable()->after('previous_sale_price');
            $table->unsignedInteger('previous_review_count')->nullable()->after('previous_rating');
            $table->integer('review_delta')->default(0)->after('previous_review_count');
        });
    }

    public function down(): void
    {
        Schema::table('trendyol_booster_store_watch_items', function (Blueprint $table) {
            $table->dropColumn([
                'image_url', 'rating', 'review_count', 'favorite_count',
                'campaign_badges', 'is_first_seller', 'original_price',
                'discount_rate', 'category_name', 'seller_name', 'stock_status',
                'previous_rating', 'previous_review_count', 'review_delta',
            ]);
        });
    }
};
