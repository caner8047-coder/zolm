<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Günlük Özet Metrikleri
        Schema::create('wa_daily_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->date('metric_date');
            $table->string('channel', 30)->default('all');
            // Gönderim metrikleri
            $table->integer('messages_queued')->default(0);
            $table->integer('messages_sent')->default(0);
            $table->integer('messages_delivered')->default(0);
            $table->integer('messages_read')->default(0);
            $table->integer('messages_failed')->default(0);
            $table->integer('messages_opted_out')->default(0);
            // Dönüşüm metrikleri
            $table->integer('clicks')->default(0);
            $table->integer('coupon_created')->default(0);
            $table->integer('coupon_used')->default(0);
            $table->integer('orders_attributed')->default(0);
            $table->decimal('revenue_attributed', 12, 2)->default(0);
            // Kargo/bildirim
            $table->integer('shipping_notifications')->default(0);
            $table->integer('order_confirmations')->default(0);
            $table->integer('return_notifications')->default(0);
            // Sepet kurtarma
            $table->integer('cart_recovery_sent')->default(0);
            $table->integer('cart_recovery_recovered')->default(0);
            // Stok hatırlatıcı
            $table->integer('stock_alerts_sent')->default(0);
            $table->integer('stock_alerts_converted')->default(0);
            // AI
            $table->integer('ai_runs')->default(0);
            $table->integer('ai_handoffs')->default(0);
            $table->decimal('avg_response_time_ms', 10, 2)->nullable();
            // Destek
            $table->integer('support_conversations_opened')->default(0);
            $table->integer('support_conversations_resolved')->default(0);
            $table->decimal('avg_first_response_minutes', 10, 2)->nullable();
            $table->timestamps();
            $table->unique(['store_id', 'metric_date', 'channel']);
        });

        // 2. Kampanya Metrikleri (detaylı)
        Schema::create('wa_campaign_daily_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('wa_campaigns')->cascadeOnDelete();
            $table->date('metric_date');
            $table->integer('recipients_queued')->default(0);
            $table->integer('recipients_sent')->default(0);
            $table->integer('recipients_delivered')->default(0);
            $table->integer('recipients_read')->default(0);
            $table->integer('recipients_clicked')->default(0);
            $table->integer('recipients_converted')->default(0);
            $table->integer('recipients_skipped')->default(0);
            $table->integer('recipients_failed')->default(0);
            $table->decimal('revenue_attributed', 12, 2)->default(0);
            $table->integer('coupons_created')->default(0);
            $table->integer('coupons_used')->default(0);
            $table->timestamps();
            $table->unique(['campaign_id', 'metric_date']);
        });

        // 3. SLA Tanımları
        Schema::create('sla_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('channel', 30)->default('all');
            $table->string('priority', 20)->default('normal');
            $table->integer('first_response_minutes');
            $table->integer('resolution_minutes');
            $table->boolean('business_hours_only')->default(true);
            $table->json('business_hours_config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['store_id', 'channel', 'is_active']);
        });

        // 4. SLA Durum Takibi
        Schema::create('sla_tracks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sla_definition_id')->constrained('sla_definitions')->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('support_conversations')->nullOnDelete();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->string('status', 20)->default('active');
            $table->timestamp('started_at');
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('resolution_deadline');
            $table->timestamp('first_response_deadline');
            $table->boolean('first_response_breached')->default(false);
            $table->boolean('resolution_breached')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'resolution_deadline']);
            $table->index(['store_id', 'status']);
        });

        // 5. SLA Olayları
        Schema::create('sla_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sla_track_id')->constrained('sla_tracks')->cascadeOnDelete();
            $table->string('event_type', 40);
            $table->json('details_json')->nullable();
            $table->timestamps();
            $table->index(['sla_track_id', 'event_type']);
        });

        // 6. Retention Cleanup Takibi
        Schema::create('wa_retention_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->nullable()->constrained('marketplace_stores')->nullOnDelete();
            $table->string('target_table', 60);
            $table->integer('records_deleted')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_retention_runs');
        Schema::dropIfExists('sla_events');
        Schema::dropIfExists('sla_tracks');
        Schema::dropIfExists('sla_definitions');
        Schema::dropIfExists('wa_campaign_daily_metrics');
        Schema::dropIfExists('wa_daily_metrics');
    }
};
