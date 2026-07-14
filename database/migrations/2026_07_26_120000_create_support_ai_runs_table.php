<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_ai_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->restrictOnDelete();
            $table->foreignId('conversation_id')->constrained('support_conversations')->restrictOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('support_messages')->nullOnDelete();
            $table->string('prompt_template_key', 60)->nullable();
            $table->text('prompt_raw')->nullable();
            $table->text('response_raw')->nullable();
            $table->integer('confidence_score')->default(0);
            $table->json('sources_used_json')->nullable();
            $table->integer('token_in')->default(0);
            $table->integer('token_out')->default(0);
            $table->integer('latency_ms')->default(0);
            $table->string('status', 20)->default('success'); // success, failed, skipped
            $table->timestamps();

            $table->index('store_id', 'sar_store_idx');
            $table->index('conversation_id', 'sar_conv_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ai_runs');
    }
};
