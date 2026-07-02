<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_risk_signal_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('fingerprint', 64);
            $table->string('signal_key', 100);
            $table->string('category', 60);
            $table->string('severity', 20);
            $table->string('status', 20)->default('open');
            $table->string('title', 180);
            $table->json('signal_json')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('snoozed_until')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'fingerprint'], 'marketplace_risk_user_fingerprint_unique');
            $table->index(['user_id', 'status', 'severity'], 'marketplace_risk_user_status_severity_idx');
            $table->index(['user_id', 'category', 'status'], 'marketplace_risk_user_category_status_idx');
            $table->index(['user_id', 'last_seen_at'], 'marketplace_risk_user_last_seen_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_risk_signal_states');
    }
};
