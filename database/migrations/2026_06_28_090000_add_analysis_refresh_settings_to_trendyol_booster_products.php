<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trendyol_booster_products', function (Blueprint $table) {
            $table->boolean('analysis_auto_refresh_enabled')->default(false)->after('is_favorite');
            $table->unsignedSmallInteger('analysis_refresh_interval_minutes')->default(1440)->after('analysis_auto_refresh_enabled');
            $table->timestamp('next_analysis_refresh_at')->nullable()->after('analysis_refresh_interval_minutes');
            $table->timestamp('last_analysis_refresh_at')->nullable()->after('next_analysis_refresh_at');
            $table->string('last_analysis_refresh_status', 20)->nullable()->after('last_analysis_refresh_at');
            $table->text('last_analysis_refresh_error')->nullable()->after('last_analysis_refresh_status');
            $table->index(
                ['analysis_auto_refresh_enabled', 'next_analysis_refresh_at'],
                'tr_booster_analysis_refresh_due_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('trendyol_booster_products', function (Blueprint $table) {
            $table->dropIndex('tr_booster_analysis_refresh_due_idx');
            $table->dropColumn([
                'analysis_auto_refresh_enabled',
                'analysis_refresh_interval_minutes',
                'next_analysis_refresh_at',
                'last_analysis_refresh_at',
                'last_analysis_refresh_status',
                'last_analysis_refresh_error',
            ]);
        });
    }
};
