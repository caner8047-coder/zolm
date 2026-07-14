<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('support_knowledge_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->restrictOnDelete();
            $table->foreignId('source_conversation_id')->constrained('support_conversations')->cascadeOnDelete();
            $table->foreignId('source_message_id')->nullable()->constrained('support_messages')->nullOnDelete();
            $table->string('category', 50);
            $table->string('title', 255);
            $table->text('proposed_answer');
            $table->integer('confidence');
            $table->string('status', 20)->default('pending'); // pending, approved, rejected, applied
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('hash_key', 64)->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_knowledge_suggestions');
    }
};
