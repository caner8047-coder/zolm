<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. AI Çalışma Kayıtları
        Schema::create('wa_ai_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('wa_conversations')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('wa_contacts')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->foreignId('inbound_message_id')->nullable()->constrained('wa_inbound_messages')->nullOnDelete();
            $table->foreignId('outbox_id')->nullable()->constrained('wa_outbox')->nullOnDelete();
            $table->string('intent', 60);
            $table->text('user_message');
            $table->text('ai_response')->nullable();
            $table->json('tools_called')->nullable();
            $table->json('context_snapshot')->nullable();
            $table->string('status', 30)->default('processing');
            $table->string('handoff_reason', 60)->nullable();
            $table->text('handoff_summary')->nullable();
            $table->decimal('response_time_ms', 10, 2)->nullable();
            $table->timestamps();
            $table->index(['conversation_id', 'created_at']);
            $table->index(['store_id', 'intent']);
        });

        // 2. AI Tool Çağrıları
        Schema::create('wa_ai_tool_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_run_id')->constrained('wa_ai_runs')->cascadeOnDelete();
            $table->string('tool_name', 60);
            $table->json('input_params')->nullable();
            $table->json('output_data')->nullable();
            $table->string('status', 20)->default('success');
            $table->text('error_message')->nullable();
            $table->decimal('execution_time_ms', 10, 2)->nullable();
            $table->timestamps();
            $table->index(['ai_run_id']);
        });

        // 3. Temsilciye Devir
        Schema::create('wa_handoffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('wa_conversations')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('wa_contacts')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->foreignId('triggered_by_ai_run_id')->nullable()->constrained('wa_ai_runs')->nullOnDelete();
            $table->string('reason', 60);
            $table->text('summary')->nullable();
            $table->string('status', 30)->default('pending');
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolution', 60)->nullable();
            $table->timestamps();
            $table->index(['conversation_id', 'status']);
            $table->index(['store_id', 'status']);
        });

        // 4. Bilgi Bankası Makaleleri
        Schema::create('wa_knowledge_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->nullable()->constrained('marketplace_stores')->nullOnDelete();
            $table->string('title', 200);
            $table->string('slug', 200);
            $table->string('category', 60);
            $table->text('content');
            $table->string('status', 20)->default('draft');
            $table->integer('version')->default(1);
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_until')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['store_id', 'slug']);
            $table->index(['store_id', 'status', 'category']);
        });

        // 5. Bilgi Bankası Parçacıkları
        Schema::create('wa_knowledge_article_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('wa_knowledge_articles')->cascadeOnDelete();
            $table->integer('chunk_index');
            $table->text('content');
            $table->string('content_hash', 64);
            $table->timestamps();
            $table->index(['article_id', 'chunk_index']);
        });

        // 6. Conversation alanlarını genişlet
        Schema::table('wa_conversations', function (Blueprint $table) {
            $table->string('ai_status', 20)->default('active')->after('status');
            $table->string('handoff_status', 20)->nullable()->after('ai_status');
            $table->string('priority', 20)->default('normal')->after('handoff_status');
            $table->text('last_ai_summary')->nullable()->after('priority');
            $table->string('last_intent', 60)->nullable()->after('last_ai_summary');
        });
    }

    public function down(): void
    {
        Schema::table('wa_conversations', function (Blueprint $table) {
            $table->dropColumn(['ai_status', 'handoff_status', 'priority', 'last_ai_summary', 'last_intent']);
        });
        Schema::dropIfExists('wa_knowledge_article_chunks');
        Schema::dropIfExists('wa_knowledge_articles');
        Schema::dropIfExists('wa_handoffs');
        Schema::dropIfExists('wa_ai_tool_calls');
        Schema::dropIfExists('wa_ai_runs');
    }
};
