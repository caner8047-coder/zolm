<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trendyol_booster_keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trendyol_booster_product_id')->constrained('trendyol_booster_products')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('keyword', 180);
            $table->string('keyword_hash', 64);
            $table->unsignedSmallInteger('target_rank')->default(20);
            $table->unsignedSmallInteger('observed_rank')->nullable();
            $table->unsignedInteger('result_count')->default(0);
            $table->string('visibility_status', 40)->default('tracking');
            $table->string('visibility_note', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();

            $table->unique(['trendyol_booster_product_id', 'keyword_hash'], 'tr_booster_keyword_product_unique');
            $table->index(['user_id', 'visibility_status'], 'tr_booster_keyword_user_status_idx');
            $table->index(['user_id', 'is_active', 'last_checked_at'], 'tr_booster_keyword_user_active_checked_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trendyol_booster_keywords');
    }
};
