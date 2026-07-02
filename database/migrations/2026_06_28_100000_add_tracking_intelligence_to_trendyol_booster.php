<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trendyol_booster_products', function (Blueprint $table) {
            $table->string('tracking_status', 20)->default('active')->after('is_favorite');
            $table->json('tracking_sources')->nullable()->after('tracking_status');
            $table->string('tracking_group_key', 80)->nullable()->after('tracking_sources');
            $table->timestamp('tracking_started_at')->nullable()->after('tracking_group_key');
            $table->timestamp('tracking_paused_at')->nullable()->after('tracking_started_at');
            $table->unsignedTinyInteger('data_quality_score')->default(0)->after('opportunity_score');
            $table->unsignedTinyInteger('interest_score')->default(0)->after('data_quality_score');
            $table->unsignedTinyInteger('competition_score')->default(0)->after('interest_score');
            $table->unsignedTinyInteger('risk_score')->default(0)->after('competition_score');
            $table->decimal('estimated_daily_sales', 12, 2)->nullable()->after('risk_score');
            $table->decimal('estimated_daily_revenue', 14, 2)->nullable()->after('estimated_daily_sales');
            $table->timestamp('metrics_calculated_at')->nullable()->after('estimated_daily_revenue');

            $table->index(['user_id', 'tracking_status'], 'tr_booster_user_tracking_idx');
            $table->index(['tracking_status', 'next_analysis_refresh_at'], 'tr_booster_tracking_due_idx');
        });

        Schema::table('trendyol_booster_snapshots', function (Blueprint $table) {
            $table->unsignedInteger('stock_quantity')->nullable()->after('availability');
            $table->unsignedInteger('question_count')->nullable()->after('view_count_24h');
            $table->unsignedSmallInteger('category_rank')->nullable()->after('question_count');
            $table->decimal('seller_score', 5, 2)->nullable()->after('category_rank');
            $table->unsignedBigInteger('seller_follower_count')->nullable()->after('seller_score');
            $table->unsignedSmallInteger('campaign_count')->nullable()->after('seller_follower_count');
            $table->string('favorite_precision', 20)->nullable()->after('favorite_count');
            $table->json('data_sources')->nullable()->after('analysis_source');
            $table->unsignedTinyInteger('data_quality_score')->default(0)->after('data_sources');
            $table->unsignedTinyInteger('confidence_score')->default(0)->after('data_quality_score');
            $table->decimal('estimated_hourly_sales', 12, 3)->nullable()->after('confidence_score');
            $table->decimal('estimated_daily_sales', 12, 2)->nullable()->after('estimated_hourly_sales');
            $table->decimal('estimated_days_of_stock', 12, 2)->nullable()->after('estimated_daily_sales');
            $table->decimal('estimated_daily_revenue', 14, 2)->nullable()->after('estimated_days_of_stock');
            $table->decimal('estimated_conversion_rate', 8, 2)->nullable()->after('estimated_daily_revenue');
            $table->decimal('sentiment_score', 6, 2)->nullable()->after('estimated_conversion_rate');
            $table->json('positive_topics')->nullable()->after('sentiment_score');
            $table->json('negative_topics')->nullable()->after('positive_topics');
            $table->unsignedTinyInteger('interest_score')->default(0)->after('negative_topics');
            $table->unsignedTinyInteger('competition_score')->default(0)->after('interest_score');
            $table->unsignedTinyInteger('risk_score')->default(0)->after('competition_score');
            $table->json('metrics_json')->nullable()->after('risk_score');
        });
    }

    public function down(): void
    {
        Schema::table('trendyol_booster_snapshots', function (Blueprint $table) {
            $table->dropColumn([
                'stock_quantity',
                'question_count',
                'category_rank',
                'seller_score',
                'seller_follower_count',
                'campaign_count',
                'favorite_precision',
                'data_sources',
                'data_quality_score',
                'confidence_score',
                'estimated_hourly_sales',
                'estimated_daily_sales',
                'estimated_days_of_stock',
                'estimated_daily_revenue',
                'estimated_conversion_rate',
                'sentiment_score',
                'positive_topics',
                'negative_topics',
                'interest_score',
                'competition_score',
                'risk_score',
                'metrics_json',
            ]);
        });

        Schema::table('trendyol_booster_products', function (Blueprint $table) {
            $table->dropIndex('tr_booster_user_tracking_idx');
            $table->dropIndex('tr_booster_tracking_due_idx');
            $table->dropColumn([
                'tracking_status',
                'tracking_sources',
                'tracking_group_key',
                'tracking_started_at',
                'tracking_paused_at',
                'data_quality_score',
                'interest_score',
                'competition_score',
                'risk_score',
                'estimated_daily_sales',
                'estimated_daily_revenue',
                'metrics_calculated_at',
            ]);
        });
    }
};
