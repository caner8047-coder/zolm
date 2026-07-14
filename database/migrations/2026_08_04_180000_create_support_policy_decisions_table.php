<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_policy_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->foreignId('support_channel_id')->constrained('support_channels')->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('support_conversations')->nullOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('support_messages')->nullOnDelete();
            $table->string('policy_version', 30);
            $table->string('channel_key', 50);
            $table->boolean('allowed');
            $table->string('decision_code', 80);
            $table->text('reason')->nullable();
            $table->json('validator_set_json')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['store_id', 'allowed', 'created_at'], 'spd_store_allowed_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_policy_decisions');
    }
};
