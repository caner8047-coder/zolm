<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_integration_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->uuid('event_id')->unique();
            $table->string('event_type', 60);
            $table->json('payload_json');
            $table->string('idempotency_key', 100);
            $table->timestamps();

            $table->unique(['store_id', 'idempotency_key'], 'uniq_store_idemp');
        });

        Schema::create('support_integration_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_integration_event_id')
                ->constrained('support_integration_events', 'id', 'fk_support_integ_event')
                ->cascadeOnDelete();
            $table->string('webhook_url', 255);
            $table->string('status', 30)->default('pending'); // pending, success, failed, dead_letter
            $table->integer('attempts')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_integration_deliveries');
        Schema::dropIfExists('support_integration_events');
    }
};
