<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Gelir Atıf Olayları
        Schema::create('wa_attribution_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->nullable()->constrained('wa_contacts')->nullOnDelete();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->foreignId('message_delivery_id')->nullable()->constrained('wa_message_deliveries')->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained('wa_campaigns')->nullOnDelete();
            $table->foreignId('audience_id')->nullable()->constrained('wa_campaign_audiences')->nullOnDelete();
            $table->string('event_type', 40); // click / coupon_used / order_created
            $table->foreignId('order_id')->nullable()->constrained('channel_orders')->nullOnDelete();
            $table->decimal('revenue', 12, 2)->default(0);
            $table->string('attribution_window', 20); // click / coupon / viewthrough
            $table->timestamp('attributed_at');
            $table->json('metadata_json')->nullable();
            $table->timestamps();
            $table->index(['store_id', 'attributed_at']);
            $table->index(['campaign_id', 'event_type']);
            $table->index(['order_id']);
        });

        // 2. A/B Test Deneyleri
        Schema::create('wa_ab_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->foreignId('segment_id')->nullable()->constrained('wa_segments')->nullOnDelete();
            $table->string('name', 120);
            $table->string('status', 20)->default('draft');
            $table->json('variants_json'); // [{name, template_id, percentage, coupon_config}]
            $table->integer('traffic_split')->default(50);
            $table->string('primary_metric', 30)->default('conversion_rate');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['store_id', 'status']);
        });

        // 3. A/B Test Sonuçları
        Schema::create('wa_ab_test_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ab_test_id')->constrained('wa_ab_tests')->cascadeOnDelete();
            $table->string('variant_name', 60);
            $table->integer('sample_size')->default(0);
            $table->integer('conversions')->default(0);
            $table->decimal('conversion_rate', 8, 4)->default(0);
            $table->decimal('revenue', 12, 2)->default(0);
            $table->integer('clicks')->default(0);
            $table->decimal('confidence', 5, 2)->nullable();
            $table->boolean('is_winner')->default(false);
            $table->timestamps();
            $table->unique(['ab_test_id', 'variant_name']);
        });

        // 4. Kontrol Grubu
        Schema::create('wa_control_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('description')->nullable();
            $table->decimal('sample_percentage', 5, 2)->default(10);
            $table->json('criteria_json')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('current_enrolled')->default(0);
            $table->timestamps();
            $table->index(['store_id', 'is_active']);
        });

        // 5. Kontrol Grubu Üyeleri
        Schema::create('wa_control_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('wa_control_groups')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('wa_contacts')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->string('status', 20)->default('active');
            $table->timestamp('enrolled_at');
            $table->timestamp('excluded_at')->nullable();
            $table->timestamps();
            $table->unique(['group_id', 'contact_id']);
            $table->index(['group_id', 'status']);
        });

        // 6. Otomasyon Tanımları
        Schema::create('wa_automation_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->string('key', 60);
            $table->string('name', 120);
            $table->string('status', 20)->default('draft');
            $table->integer('priority')->default(5);
            $table->json('config_json')->nullable();
            $table->foreignId('template_id')->nullable()->constrained('wa_templates')->nullOnDelete();
            $table->integer('version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['store_id', 'key']);
        });

        // 7. Otomasyon Katılımları
        Schema::create('wa_automation_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_id')->constrained('wa_automation_definitions')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('wa_contacts')->cascadeOnDelete();
            $table->foreignId('related_order_id')->nullable()->constrained('channel_orders')->nullOnDelete();
            $table->foreignId('related_cart_id')->nullable()->constrained('wa_abandoned_carts')->nullOnDelete();
            $table->string('stage', 40);
            $table->string('status', 20)->default('active');
            $table->timestamp('entered_at');
            $table->timestamp('next_run_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('exit_reason', 40)->nullable();
            $table->timestamps();
            $table->index(['automation_id', 'status']);
            $table->index(['contact_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_automation_enrollments');
        Schema::dropIfExists('wa_automation_definitions');
        Schema::dropIfExists('wa_control_group_members');
        Schema::dropIfExists('wa_control_groups');
        Schema::dropIfExists('wa_ab_test_results');
        Schema::dropIfExists('wa_ab_tests');
        Schema::dropIfExists('wa_attribution_events');
    }
};
