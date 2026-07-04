<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Karşılama ve İlk Alışveriş Takibi
        Schema::create('wa_onboarding_flows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('wa_contacts')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->string('flow_type', 30); // welcome / first_purchase
            $table->string('status', 20)->default('active');
            $table->integer('current_step')->default(0);
            $table->json('steps_config')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->string('exit_reason', 40)->nullable();
            $table->timestamps();
            $table->unique(['contact_id', 'store_id', 'flow_type']);
            $table->index(['store_id', 'flow_type', 'status']);
        });

        Schema::create('wa_onboarding_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flow_id')->constrained('wa_onboarding_flows')->cascadeOnDelete();
            $table->integer('step_index');
            $table->string('name', 60);
            $table->string('delay_type', 20); // immediate / minutes / days
            $table->integer('delay_value')->default(0);
            $table->string('template_key', 60)->nullable();
            $table->json('template_params')->nullable();
            $table->string('coupon_key', 60)->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('outbox_id')->nullable()->constrained('wa_outbox')->nullOnDelete();
            $table->timestamps();
            $table->index(['flow_id', 'status']);
        });

        // 2. Doğum Günü Takibi
        Schema::create('wa_birthday_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('wa_contacts')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->date('birth_date')->nullable();
            $table->boolean('consent_granted')->default(false);
            $table->timestamp('consent_at')->nullable();
            $table->integer('last_birthday_year')->nullable();
            $table->timestamps();
            $table->unique(['contact_id', 'store_id']);
        });

        // 3. Müşteri Profili Özeti (cache/performance için)
        Schema::create('wa_customer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('wa_contacts')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->integer('total_orders')->default(0);
            $table->decimal('total_revenue', 12, 2)->default(0);
            $table->decimal('avg_order_value', 10, 2)->default(0);
            $table->integer('total_messages_sent')->default(0);
            $table->integer('total_messages_delivered')->default(0);
            $table->integer('total_messages_read')->default(0);
            $table->integer('total_clicks')->default(0);
            $table->integer('total_coupons_used')->default(0);
            $table->timestamp('first_order_at')->nullable();
            $table->timestamp('last_order_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('last_click_at')->nullable();
            $table->string('engagement_score', 10)->default('low'); // low/medium/high
            $table->json('segment_tags')->nullable();
            $table->timestamps();
            $table->unique(['contact_id', 'store_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_customer_profiles');
        Schema::dropIfExists('wa_birthday_profiles');
        Schema::dropIfExists('wa_onboarding_steps');
        Schema::dropIfExists('wa_onboarding_flows');
    }
};
