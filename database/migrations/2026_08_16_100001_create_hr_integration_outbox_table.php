<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hr_integration_outbox', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained('legal_entities')->cascadeOnDelete();
            $table->string('target', 30);
            $table->string('event_type', 80);
            $table->string('source_type', 160);
            $table->unsignedBigInteger('source_id');
            $table->string('source_key', 190);
            $table->char('payload_hash', 64);
            $table->json('payload');
            $table->string('status', 30)->default('pending');
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['legal_entity_id', 'target', 'source_key'], 'hr_integration_outbox_unique');
            $table->index(['legal_entity_id', 'status', 'target'], 'hr_integration_outbox_queue_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_integration_outbox');
    }
};
