<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mp_profit_action_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('scope_hash', 64);
            $table->string('fingerprint', 64);
            $table->string('action_key', 80);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('action_label')->nullable();
            $table->string('route_name', 80)->default('mp.finance');
            $table->json('query_json')->nullable();
            $table->json('filters_json')->nullable();
            $table->json('recommendation_json')->nullable();
            $table->unsignedInteger('value')->default(0);
            $table->decimal('impact', 14, 2)->default(0);
            $table->decimal('score', 14, 2)->default(0);
            $table->string('status', 30)->default('open');
            $table->text('note')->nullable();
            $table->timestamp('snoozed_until')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'fingerprint'], 'mp_profit_actions_user_fingerprint_unique');
            $table->index(['user_id', 'scope_hash', 'status'], 'mp_profit_actions_scope_status_idx');
            $table->index(['user_id', 'status', 'score'], 'mp_profit_actions_status_score_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mp_profit_action_items');
    }
};
