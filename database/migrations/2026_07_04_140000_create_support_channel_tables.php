<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Destek Kanalları
        Schema::create('support_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->nullable()->constrained('marketplace_stores')->nullOnDelete();
            $table->string('key', 40);
            $table->string('name', 80);
            $table->string('status', 30)->default('not_configured');
            $table->boolean('is_enabled')->default(false);
            $table->json('config_json')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamp('last_health_check_at')->nullable();
            $table->timestamps();
            $table->unique(['store_id', 'key']);
        });

        // 2. Kanal Yetenekleri
        Schema::create('support_channel_capabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_channel_id')->constrained('support_channels')->cascadeOnDelete();
            $table->string('capability', 40);
            $table->string('status', 20)->default('unknown');
            $table->string('source', 40);
            $table->timestamp('checked_at')->nullable();
            $table->json('details_json')->nullable();
            $table->timestamps();
            $table->unique(['support_channel_id', 'capability'], 'sc_cap_unique');
        });

        // 3. Birleşik Konuşmalar
        Schema::create('support_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_channel_id')->constrained('support_channels')->cascadeOnDelete();
            $table->string('external_conversation_id', 120);
            $table->string('external_customer_id', 120)->nullable();
            $table->foreignId('store_id')->nullable()->constrained('marketplace_stores')->nullOnDelete();
            $table->string('source_type', 20);
            $table->string('status', 20)->default('open');
            $table->string('priority', 20)->default('normal');
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('last_inbound_at')->nullable();
            $table->timestamp('last_outbound_at')->nullable();
            $table->string('ai_mode', 20)->default('suggestion_only');
            $table->json('source_reference_json')->nullable();
            $table->timestamps();
            $table->unique(['support_channel_id', 'external_conversation_id'], 'sc_conv_unique');
            $table->index(['status', 'priority'], 'sc_conv_status_idx');
            $table->index(['assigned_user_id', 'status'], 'sc_conv_assign_idx');
        });

        // 4. Birleşik Mesajlar
        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('support_conversations')->cascadeOnDelete();
            $table->string('external_message_id', 120)->nullable();
            $table->string('direction', 10);
            $table->string('sender_type', 20);
            $table->string('message_type', 30)->default('text');
            $table->text('body_encrypted')->nullable();
            $table->text('body_preview')->nullable();
            $table->json('payload_json')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->string('delivery_status', 20)->nullable();
            $table->string('source_reference_type', 40)->nullable();
            $table->string('source_reference_id', 80)->nullable();
            $table->timestamps();
            $table->unique(['conversation_id', 'external_message_id'], 'sc_msg_unique');
            $table->index(['conversation_id', 'received_at'], 'sc_msg_recv_idx');
        });

        // 5. Sync_CURSOR'lar
        Schema::create('support_sync_cursors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_channel_id')->constrained('support_channels')->cascadeOnDelete();
            $table->string('sync_type', 40);
            $table->text('cursor_value')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->string('last_error_code', 40)->nullable();
            $table->timestamps();
            $table->unique(['support_channel_id', 'sync_type'], 'sc_sync_unique');
        });

        // 6. Temsilci İşlemleri
        Schema::create('support_agent_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('support_conversations')->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('support_messages')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40);
            $table->json('details_json')->nullable();
            $table->timestamps();
            $table->index(['conversation_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_agent_actions');
        Schema::dropIfExists('support_sync_cursors');
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_conversations');
        Schema::dropIfExists('support_channel_capabilities');
        Schema::dropIfExists('support_channels');
    }
};
