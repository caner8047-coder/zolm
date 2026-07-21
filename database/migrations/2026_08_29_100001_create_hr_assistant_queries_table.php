<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_assistant_queries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained('legal_entities')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('query_encrypted');
            $table->string('intent', 50);
            $table->string('status', 30);
            $table->text('response_encrypted');
            $table->json('sources');
            $table->timestamp('answered_at');
            $table->timestamps();
            $table->index(['legal_entity_id', 'user_id', 'created_at'], 'hr_assistant_query_history_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_assistant_queries');
    }
};
