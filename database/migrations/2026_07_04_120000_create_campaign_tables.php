<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Segmentler
        Schema::create('wa_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->json('rules_json');
            $table->integer('estimated_count')->nullable();
            $table->timestamp('last_calculated_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['store_id', 'status']);
        });

        // 2. Kampanyalar
        Schema::create('wa_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->foreignId('wa_account_id')->constrained('wa_accounts')->cascadeOnDelete();
            $table->foreignId('segment_id')->nullable()->constrained('wa_segments')->nullOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('status', 30)->default('draft');
            $table->foreignId('template_id')->nullable()->constrained('wa_templates')->nullOnDelete();
            $table->json('template_params_json')->nullable();
            $table->timestamp('schedule_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->integer('attribution_window_days')->default(7);
            $table->boolean('quiet_hours_enabled')->default(true);
            $table->integer('batch_size')->default(50);
            $table->integer('batch_delay_seconds')->default(5);
            $table->integer('frequency_cap_override')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            // Kupon
            $table->boolean('coupon_enabled')->default(false);
            $table->string('coupon_type', 20)->default('percent');
            $table->decimal('coupon_value', 8, 2)->default(0);
            $table->decimal('coupon_minimum_spend', 10, 2)->default(0);
            $table->integer('coupon_expiry_hours')->default(48);
            $table->integer('coupon_usage_limit')->default(1);
            $table->integer('total_recipients')->default(0);
            $table->integer('total_sent')->default(0);
            $table->integer('total_delivered')->default(0);
            $table->integer('total_read')->default(0);
            $table->integer('total_clicked')->default(0);
            $table->integer('total_converted')->default(0);
            $table->decimal('total_revenue', 12, 2)->default(0);
            $table->timestamps();
            $table->index(['store_id', 'status']);
            $table->index(['status', 'schedule_at']);
        });

        // 3. Kampanya alıcıları
        Schema::create('wa_campaign_audiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('wa_campaigns')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('wa_contacts')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->json('snapshot_data_json')->nullable();
            $table->string('eligibility_status', 30)->default('eligible');
            $table->text('exclusion_reason')->nullable();
            $table->foreignId('outbox_id')->nullable()->constrained('wa_outbox')->nullOnDelete();
            $table->foreignId('coupon_id')->nullable()->constrained('wa_coupons')->nullOnDelete();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();
            $table->unique(['campaign_id', 'contact_id']);
            $table->index(['campaign_id', 'eligibility_status']);
            $table->index(['contact_id', 'campaign_id']);
        });

        // 4. Kampanya olayları
        Schema::create('wa_campaign_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('wa_campaigns')->cascadeOnDelete();
            $table->foreignId('audience_id')->nullable()->constrained('wa_campaign_audiences')->nullOnDelete();
            $table->string('event_type', 40);
            $table->json('payload_json')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['campaign_id', 'event_type']);
        });

        // 5. Frekans limitleri
        Schema::create('wa_frequency_caps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('wa_contacts')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->string('message_class', 30);
            $table->string('rolling_window_key', 40);
            $table->integer('sent_count')->default(0);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
            $table->unique(['contact_id', 'store_id', 'message_class', 'rolling_window_key'], 'wa_freq_cap_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_frequency_caps');
        Schema::dropIfExists('wa_campaign_events');
        Schema::dropIfExists('wa_campaign_audiences');
        Schema::dropIfExists('wa_campaigns');
        Schema::dropIfExists('wa_segments');
    }
};
