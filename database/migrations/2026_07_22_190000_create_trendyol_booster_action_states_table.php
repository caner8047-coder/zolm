<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trendyol_booster_action_states', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('fingerprint', 191);
            $table->string('status', 24)->default('open');
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('snoozed_until')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->json('context_json')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'fingerprint'], 'booster_action_state_user_fingerprint_unique');
            $table->index(['user_id', 'status', 'snoozed_until'], 'booster_action_state_queue_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trendyol_booster_action_states');
    }
};
