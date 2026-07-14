<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_quality_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('support_conversations')->nullOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('support_messages')->nullOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->integer('overall_score');
            $table->text('feedback')->nullable();
            $table->string('decision', 40); // approved, correction_required, golden_candidate, kb_candidate
            $table->timestamps();
        });

        Schema::create('support_quality_review_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_quality_review_id')->constrained('support_quality_reviews')->cascadeOnDelete();
            $table->string('category', 60); // accuracy, brand_voice, channel_policy, pii_safety, clarity, sales_alignment, promise_risk
            $table->integer('score');
            $table->text('comment')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_quality_review_items');
        Schema::dropIfExists('support_quality_reviews');
    }
};
