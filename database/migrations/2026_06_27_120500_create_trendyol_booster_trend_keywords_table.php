<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trendyol_booster_trend_keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('category_name', 180)->nullable();
            $table->string('keyword', 180);
            $table->string('keyword_hash', 64);
            $table->unsignedInteger('search_volume_min')->default(0);
            $table->unsignedInteger('search_volume_max')->default(0);
            $table->string('search_volume_label', 80)->nullable();
            $table->string('competition_level', 20)->default('unknown');
            $table->decimal('recommended_bid', 12, 2)->default(0);
            $table->decimal('best_bid', 12, 2)->default(0);
            $table->string('source', 120)->default('manual');
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'keyword_hash', 'category_name'], 'tr_booster_trend_user_keyword_unique');
            $table->index(['user_id', 'competition_level'], 'tr_booster_trend_user_comp_idx');
            $table->index(['user_id', 'search_volume_max'], 'tr_booster_trend_user_volume_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trendyol_booster_trend_keywords');
    }
};
