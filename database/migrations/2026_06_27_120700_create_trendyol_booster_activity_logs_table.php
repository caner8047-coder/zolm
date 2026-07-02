<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trendyol_booster_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('trendyol_booster_product_id')->nullable();
            $table->string('activity_type', 60);
            $table->string('title')->nullable();
            $table->string('subject', 240)->nullable();
            $table->string('summary', 600)->nullable();
            $table->string('result_label', 120)->nullable();
            $table->decimal('result_value', 14, 2)->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('recorded_at')->useCurrent();
            $table->timestamps();

            $table->index(['user_id', 'activity_type', 'recorded_at'], 'tr_booster_activity_user_type_idx');
            $table->index(['user_id', 'recorded_at'], 'tr_booster_activity_user_recorded_idx');
            $table->foreign('trendyol_booster_product_id', 'tr_booster_activity_product_fk')
                ->references('id')
                ->on('trendyol_booster_products')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trendyol_booster_activity_logs');
    }
};
