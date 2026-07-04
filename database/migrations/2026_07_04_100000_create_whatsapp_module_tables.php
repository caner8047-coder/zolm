<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Hesaplar ve Ayarlar ──────────────────────────────────────
        Schema::create('wa_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->nullable()->constrained('marketplace_stores')->nullOnDelete()->unique();
            $table->string('brand_id', 80)->nullable();
            $table->string('waba_id', 80);
            $table->string('phone_number_id', 80);
            $table->string('display_phone_number', 40);
            $table->text('access_token_encrypted');
            $table->string('status', 20)->default('active');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('wa_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->nullable()->constrained('marketplace_stores')->nullOnDelete();
            $table->string('key', 120);
            $table->json('value')->nullable();
            $table->timestamps();
            $table->unique(['store_id', 'key']);
        });

        // ── Müşteriler ve İzinler ────────────────────────────────────
        Schema::create('wa_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->string('wc_customer_id', 80)->nullable();
            $table->foreignId('zolm_customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('phone_e164_encrypted');
            $table->string('phone_hash', 64);
            $table->string('first_name', 120)->nullable();
            $table->string('last_name', 120)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->unique(['store_id', 'phone_hash']);
            $table->index('phone_hash');
            $table->index('wc_customer_id');
        });

        Schema::create('wa_contact_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('wa_contacts')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->string('purpose', 40);
            $table->string('status', 20);
            $table->timestamps();
            $table->unique(['contact_id', 'purpose', 'store_id']);
        });

        Schema::create('wa_consent_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('wa_contacts')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->string('purpose', 40);
            $table->string('action', 20);
            $table->string('consent_text_version', 40)->nullable();
            $table->string('source', 60);
            $table->timestamp('consent_timestamp');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            $table->index(['contact_id', 'purpose', 'consent_timestamp']);
        });

        Schema::create('wa_suppressions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('wa_contacts')->cascadeOnDelete();
            $table->string('reason', 40);
            $table->text('details')->nullable();
            $table->timestamp('suppressed_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index('contact_id');
        });

        // ── Mesaj Kuyruğu ve Teslimat ────────────────────────────────
        Schema::create('wa_outbox', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('wa_contacts')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->string('idempotency_key', 200);
            $table->string('message_type', 30);
            $table->string('template_name', 120)->nullable();
            $table->string('template_language', 10)->nullable();
            $table->json('template_params_json')->nullable();
            $table->text('body_text')->nullable();
            $table->string('priority', 10)->default('normal');
            $table->string('status', 20)->default('queued');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->smallInteger('retry_count')->default(0);
            $table->smallInteger('max_retries')->default(3);
            $table->text('error_message')->nullable();
            $table->string('error_code', 40)->nullable();
            $table->string('meta_message_id', 120)->nullable();
            $table->string('automation_key', 80)->nullable();
            $table->foreignId('related_order_id')->nullable()->constrained('channel_orders')->nullOnDelete();
            $table->foreignId('related_cart_id')->nullable()->nullOnDelete();
            $table->timestamps();
            $table->unique(['idempotency_key']);
            $table->index(['status', 'scheduled_at']);
            $table->index(['contact_id', 'status']);
            $table->index(['store_id', 'status']);
        });

        Schema::create('wa_message_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outbox_id')->constrained('wa_outbox')->cascadeOnDelete();
            $table->string('meta_message_id', 120);
            $table->string('provider_event_key', 200);
            $table->string('status', 20);
            $table->string('error_code', 40)->nullable();
            $table->string('error_classification', 40)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
            $table->unique(['provider_event_key']);
            $table->index('outbox_id');
            $table->index('meta_message_id');
        });

        // ── Webhook Olayları ──────────────────────────────────────────
        Schema::create('wa_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 80);
            $table->string('request_id', 200)->nullable();
            $table->string('request_hash', 64)->nullable();
            $table->string('provider_event_key', 200);
            $table->string('source', 20);
            $table->json('payload');
            $table->string('signature', 200)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->string('status', 20)->default('pending');
            $table->integer('duplicate_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->unique(['provider_event_key']);
            $table->index('request_id');
            $table->index('status');
        });

        // ── Şablonlar ────────────────────────────────────────────────
        Schema::create('wa_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wa_account_id')->constrained('wa_accounts')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('language', 10);
            $table->string('category', 40);
            $table->string('status', 30);
            $table->json('components_json')->nullable();
            $table->json('variable_schema_json')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->unique(['wa_account_id', 'name', 'language']);
        });

        // ── Konuşmalar ve Gelen Mesajlar ─────────────────────────────
        Schema::create('wa_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('wa_contacts')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->string('status', 20)->default('open');
            $table->timestamp('last_message_at')->nullable();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['contact_id', 'status']);
            $table->index(['store_id', 'status']);
        });

        Schema::create('wa_inbound_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('wa_conversations')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('wa_contacts')->cascadeOnDelete();
            $table->string('meta_message_id', 120);
            $table->string('message_type', 30);
            $table->text('body')->nullable();
            $table->json('payload_json');
            $table->timestamp('received_at');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['meta_message_id']);
            $table->index(['conversation_id', 'received_at']);
            $table->index('contact_id');
        });

        // ── Denetim Kayıtları ────────────────────────────────────────
        Schema::create('wa_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80);
            $table->string('entity_type', 60)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('details')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            $table->index(['action', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_audit_logs');
        Schema::dropIfExists('wa_inbound_messages');
        Schema::dropIfExists('wa_conversations');
        Schema::dropIfExists('wa_templates');
        Schema::dropIfExists('wa_webhook_events');
        Schema::dropIfExists('wa_message_deliveries');
        Schema::dropIfExists('wa_outbox');
        Schema::dropIfExists('wa_suppressions');
        Schema::dropIfExists('wa_consent_events');
        Schema::dropIfExists('wa_contact_preferences');
        Schema::dropIfExists('wa_contacts');
        Schema::dropIfExists('wa_settings');
        Schema::dropIfExists('wa_accounts');
    }
};
