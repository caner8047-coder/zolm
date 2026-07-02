<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trendyol_booster_products', function (Blueprint $table) {
            $table->string('image_url', 1000)->nullable()->after('category_name');
            $table->boolean('is_favorite')->default(false)->after('watch_keyword');
        });

        Schema::table('trendyol_booster_snapshots', function (Blueprint $table) {
            $table->unsignedBigInteger('evaluation_count')->nullable()->after('availability');
            $table->unsignedBigInteger('review_count')->nullable()->after('evaluation_count');
            $table->decimal('average_rating', 4, 2)->nullable()->after('review_count');
            $table->unsignedBigInteger('favorite_count')->nullable()->after('average_rating');
            $table->unsignedBigInteger('basket_count')->nullable()->after('favorite_count');
            $table->unsignedBigInteger('view_count_24h')->nullable()->after('basket_count');
            $table->json('recent_reviews')->nullable()->after('view_count_24h');
            $table->string('analysis_source', 40)->nullable()->after('recent_reviews');
        });
    }

    public function down(): void
    {
        Schema::table('trendyol_booster_snapshots', function (Blueprint $table) {
            $table->dropColumn([
                'evaluation_count',
                'review_count',
                'average_rating',
                'favorite_count',
                'basket_count',
                'view_count_24h',
                'recent_reviews',
                'analysis_source',
            ]);
        });

        Schema::table('trendyol_booster_products', function (Blueprint $table) {
            $table->dropColumn(['image_url', 'is_favorite']);
        });
    }
};
