<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Webhook Altyapısı
        Schema::create('wa_webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('provider', 40);
            $table->string('url', 500);
            $table->string('secret_encrypted')->nullable();
            $table->string('status', 20)->default('active');
            $table->json('config_json')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_received_at')->nullable();
            $table->timestamp('last_health_check_at')->nullable();
            $table->timestamps();
            $table->index(['store_id', 'provider', 'is_active']);
        });

        // 2. Webhook Olay Kayıtları
        Schema::create('wa_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('endpoint_id')->nullable()->constrained('wa_webhook_endpoints')->nullOnDelete();
            $table->string('provider', 40);
            $table->string('event_type', 60);
            $table->string('direction', 10); // inbound / outbound
            $table->string('status', 20)->default('received');
            $table->json('payload_hash')->nullable();
            $table->text('error_message')->nullable();
            $table->decimal('processing_time_ms', 10, 2)->nullable();
            $table->timestamps();
            $table->index(['provider', 'event_type', 'created_at']);
            $table->index(['endpoint_id', 'status']);
        });

        // 3. Bildirim Köprüsü
        Schema::create('wa_notification_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->string('key', 40);
            $table->string('name', 80);
            $table->string('type', 30); // email / sms / push / webhook
            $table->string('status', 20)->default('configured');
            $table->json('config_json')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
            $table->unique(['store_id', 'key']);
        });

        // 4. Bildirim Şablonları
        Schema::create('wa_notification_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained('wa_notification_channels')->cascadeOnDelete();
            $table->string('key', 60);
            $table->string('name', 120);
            $table->string('subject', 200)->nullable();
            $table->text('body_template');
            $table->json('variables_schema')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['channel_id', 'key']);
        });

        // 5. Bildirim Gönderimleri
        Schema::create('wa_notification_sends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained('wa_notification_channels')->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('wa_notification_templates')->nullOnDelete();
            $table->string('recipient', 200);
            $table->string('status', 20)->default('queued');
            $table->json('variables_used')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['channel_id', 'status']);
            $table->index(['template_id', 'created_at']);
        });

        // 6. Dış Servis Entegrasyonları
        Schema::create('wa_external_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->string('provider', 40);
            $table->string('name', 80);
            $table->string('status', 20)->default('configured');
            $table->json('config_json')->nullable();
            $table->string('credentials_encrypted')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamp('last_health_check_at')->nullable();
            $table->timestamps();
            $table->unique(['store_id', 'provider']);
        });

        // 7. Entegrasyon Senkronizasyon Kuyrukları
        Schema::create('wa_integration_sync_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_id')->constrained('wa_external_integrations')->cascadeOnDelete();
            $table->string('job_type', 40);
            $table->string('status', 20)->default('queued');
            $table->json('payload_json')->nullable();
            $table->text('result_json')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['integration_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_integration_sync_jobs');
        Schema::dropIfExists('wa_external_integrations');
        Schema::dropIfExists('wa_notification_sends');
        Schema::dropIfExists('wa_notification_templates');
        Schema::dropIfExists('wa_notification_channels');
        Schema::dropIfExists('wa_webhook_logs');
        Schema::dropIfExists('wa_webhook_endpoints');
    }
};
