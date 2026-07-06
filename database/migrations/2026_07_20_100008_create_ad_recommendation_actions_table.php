<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_recommendation_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recommendation_id')->constrained('ad_recommendations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('action', 20);
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['recommendation_id', 'created_at'], 'ad_rec_action_rec_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_recommendation_actions');
    }
};
