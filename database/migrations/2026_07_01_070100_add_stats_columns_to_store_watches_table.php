<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trendyol_booster_store_watches', function (Blueprint $table) {
            $table->decimal('store_rating', 2, 1)->nullable()->after('brand_distribution');
            $table->unsignedInteger('top_seller_count')->default(0)->after('store_rating');
            $table->unsignedInteger('campaign_count')->default(0)->after('top_seller_count');
            $table->decimal('avg_price', 10, 2)->nullable()->after('campaign_count');
            $table->decimal('avg_rating', 2, 1)->nullable()->after('avg_price');
            $table->unsignedInteger('total_reviews')->default(0)->after('avg_rating');
            $table->json('category_distribution')->nullable()->after('total_reviews');
        });
    }

    public function down(): void
    {
        Schema::table('trendyol_booster_store_watches', function (Blueprint $table) {
            $table->dropColumn([
                'store_rating', 'top_seller_count', 'campaign_count',
                'avg_price', 'avg_rating', 'total_reviews', 'category_distribution',
            ]);
        });
    }
};
