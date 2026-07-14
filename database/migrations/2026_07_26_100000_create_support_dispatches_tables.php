<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_channel_id')->constrained('support_channels')->restrictOnDelete();
            $table->foreignId('conversation_id')->constrained('support_conversations')->restrictOnDelete();
            $table->foreignId('message_id')->constrained('support_messages')->restrictOnDelete();
            $table->string('idempotency_key', 120)->unique();
            $table->string('status', 20)->default('pending'); // pending, sending, sent, failed
            $table->integer('attempt_count')->default(0);
            $table->timestamp('retry_at')->nullable();
            $table->string('channel_message_id', 120)->nullable();
            $table->text('last_error')->nullable();
            $table->json('payload_json')->nullable();
            $table->timestamps();

            $table->index(['status', 'retry_at'], 'sd_status_retry_idx');
        });

        Schema::create('support_dispatch_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_dispatch_id')->constrained('support_dispatches')->restrictOnDelete();
            $table->timestamp('attempted_at');
            $table->string('status', 20); // success, failed
            $table->text('error_message')->nullable();
            $table->integer('latency_ms')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_dispatch_attempts');
        Schema::dropIfExists('support_dispatches');
    }
};
