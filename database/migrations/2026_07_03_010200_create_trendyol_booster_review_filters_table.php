<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trendyol_booster_review_filters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->tinyInteger('min_rating')->unsigned()->default(1);
            $table->tinyInteger('max_rating')->unsigned()->default(5);
            $table->unsignedInteger('min_comment_length')->default(0);
            $table->boolean('require_photo')->default(false);
            $table->json('exclude_keywords')->nullable();
            $table->json('include_keywords')->nullable();
            $table->boolean('auto_exclude_spam')->default(true);
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_apply_on_push')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trendyol_booster_review_filters');
    }
};
