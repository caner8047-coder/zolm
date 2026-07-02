<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trendyol_booster_trend_keywords', function (Blueprint $table) {
            $table->unsignedTinyInteger('signal_score')->default(0)->after('competition_level');
            $table->unsignedTinyInteger('previous_signal_score')->nullable()->after('signal_score');
            $table->unsignedInteger('product_count')->default(0)->after('previous_signal_score');
            $table->unsignedInteger('store_count')->default(0)->after('product_count');
            $table->unsignedBigInteger('total_favorite_count')->default(0)->after('store_count');
            $table->unsignedBigInteger('total_review_count')->default(0)->after('total_favorite_count');
            $table->decimal('average_rating', 3, 2)->nullable()->after('total_review_count');
            $table->unsignedInteger('campaign_product_count')->default(0)->after('average_rating');
            $table->string('trend_direction', 20)->default('new')->after('campaign_product_count');
            $table->json('source_context')->nullable()->after('source');
            $table->timestamp('first_seen_at')->nullable()->after('source_context');
            $table->timestamp('last_seen_at')->nullable()->after('first_seen_at');

            $table->index(['user_id', 'signal_score'], 'tr_booster_trend_user_signal_idx');
        });
    }

    public function down(): void
    {
        Schema::table('trendyol_booster_trend_keywords', function (Blueprint $table) {
            $table->dropIndex('tr_booster_trend_user_signal_idx');
            $table->dropColumn([
                'signal_score',
                'previous_signal_score',
                'product_count',
                'store_count',
                'total_favorite_count',
                'total_review_count',
                'average_rating',
                'campaign_product_count',
                'trend_direction',
                'source_context',
                'first_seen_at',
                'last_seen_at',
            ]);
        });
    }
};
