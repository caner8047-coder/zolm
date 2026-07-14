<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->string('metric', 50); // ai_drafts, auto_replies, agent_replies, knowledge_suggestions, connected_channels
            $table->string('month', 10); // YYYY-MM or YYYY-MM-DD format
            $table->integer('count')->default(0);
            $table->timestamps();

            $table->unique(['store_id', 'metric', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_usages');
    }
};
