<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trendyol_booster_reviews', function (Blueprint $table) {
            $table->dropUnique('trendyol_booster_reviews_trendyol_review_id_unique');
            $table->unique(
                ['user_id', 'trendyol_review_id'],
                'tb_reviews_user_review_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('trendyol_booster_reviews', function (Blueprint $table) {
            $table->dropUnique('tb_reviews_user_review_unique');
            $table->unique('trendyol_review_id');
        });
    }
};
