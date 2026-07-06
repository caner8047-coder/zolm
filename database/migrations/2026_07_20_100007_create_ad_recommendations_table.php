<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('channel_code', 50);
            $table->string('entity_type', 50);
            $table->unsignedBigInteger('entity_id');
            $table->string('priority', 20);
            $table->string('category', 50);
            $table->string('title', 500);
            $table->text('description');
            $table->text('recommended_action');
            $table->json('evidence')->nullable();
            $table->json('expected_impact')->nullable();
            $table->decimal('confidence_score', 5, 4)->default(0);
            $table->string('status', 20)->default('new');
            $table->string('generated_by', 20)->default('rule');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status', 'priority'], 'ad_rec_user_status_priority_idx');
            $table->index(['user_id', 'channel_code', 'entity_type'], 'ad_rec_user_channel_entity_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_recommendations');
    }
};
