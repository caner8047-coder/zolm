<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trendyol_booster_review_syncs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('queued');
            $table->string('sync_type', 20)->default('full');
            $table->unsignedInteger('total_products')->default(0);
            $table->unsignedInteger('processed_products')->default(0);
            $table->unsignedInteger('total_reviews')->default(0);
            $table->unsignedInteger('new_reviews')->default(0);
            $table->unsignedInteger('updated_reviews')->default(0);
            $table->unsignedInteger('deleted_reviews')->default(0);
            $table->unsignedInteger('spam_detected')->default(0);
            $table->datetime('started_at')->nullable();
            $table->datetime('completed_at')->nullable();
            $table->datetime('last_synced_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('meta')->nullable();
            $table->unsignedInteger('progress_percent')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trendyol_booster_review_syncs');
    }
};
