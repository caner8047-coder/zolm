<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Terk edilen sepetler (önce — coupons buna FK ile bağlı)
        Schema::create('wa_abandoned_carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('wa_contacts')->nullOnDelete();
            $table->string('wc_customer_id', 80)->nullable();
            $table->string('cart_key_hash', 64);
            $table->json('cart_snapshot_json');
            $table->decimal('cart_total_snapshot', 12, 2)->default(0);
            $table->string('currency', 3)->default('TRY');
            $table->string('status', 30)->default('active');
            $table->timestamp('last_activity_at');
            $table->timestamp('first_detected_at');
            $table->timestamp('next_action_at')->nullable();
            $table->string('recovery_token_hash', 64)->nullable();
            $table->timestamp('recovery_expires_at')->nullable();
            $table->foreignId('recovered_order_id')->nullable()->constrained('channel_orders')->nullOnDelete();
            $table->timestamp('recovered_at')->nullable();
            $table->timestamps();
            $table->unique(['store_id', 'cart_key_hash']);
            $table->index(['status', 'next_action_at']);
            $table->index(['contact_id', 'status']);
        });

        // 2. Kuponlar
        Schema::create('wa_coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('wa_contacts')->nullOnDelete();
            $table->foreignId('cart_id')->nullable()->constrained('wa_abandoned_carts')->nullOnDelete();
            $table->string('automation_key', 80);
            $table->unsignedBigInteger('wc_coupon_id')->nullable();
            $table->string('code', 60);
            $table->string('discount_type', 20)->default('percent');
            $table->decimal('discount_value', 8, 2)->default(0);
            $table->decimal('minimum_spend', 10, 2)->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->foreignId('related_order_id')->nullable()->constrained('channel_orders')->nullOnDelete();
            $table->string('idempotency_key', 200);
            $table->timestamps();
            $table->unique(['idempotency_key']);
            $table->index(['automation_key', 'contact_id']);
        });

        // 3. Sepet kurtarma çalıştırmaları
        Schema::create('wa_cart_recovery_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained('wa_abandoned_carts')->cascadeOnDelete();
            $table->string('stage', 20);
            $table->string('status', 20)->default('pending');
            $table->timestamp('scheduled_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 60)->nullable();
            $table->foreignId('outbox_id')->nullable()->constrained('wa_outbox')->nullOnDelete();
            $table->foreignId('coupon_id')->nullable()->constrained('wa_coupons')->nullOnDelete();
            $table->timestamps();
            $table->unique(['cart_id', 'stage']);
        });

        // 4. Takip linkleri
        Schema::create('wa_tracking_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outbox_id')->nullable()->constrained('wa_outbox')->nullOnDelete();
            $table->text('destination_url');
            $table->string('token_hash', 64);
            $table->timestamp('expires_at');
            $table->timestamp('clicked_at')->nullable();
            $table->integer('click_count')->default(0);
            $table->timestamps();
            $table->unique(['token_hash']);
        });

        // 5. Stok hatırlatıcı bekleme listesi
        Schema::create('wa_stock_waitlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('wa_contacts')->nullOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variation_id')->nullable();
            $table->unsignedBigInteger('wc_product_id');
            $table->unsignedBigInteger('wc_variation_id')->nullable();
            $table->string('status', 20)->default('waiting');
            $table->timestamp('requested_at');
            $table->timestamp('notified_at')->nullable();
            $table->foreignId('notified_outbox_id')->nullable()->constrained('wa_outbox')->nullOnDelete();
            $table->integer('available_stock_snapshot')->nullable();
            $table->foreignId('related_order_id')->nullable()->constrained('channel_orders')->nullOnDelete();
            $table->timestamps();
            $table->index(['store_id', 'wc_product_id', 'wc_variation_id', 'status'], 'wa_sw_product_idx');
            $table->index(['status', 'requested_at'], 'wa_sw_status_idx');
            $table->index(['contact_id', 'status'], 'wa_sw_contact_idx');
        });

        // 6. Otomasyon ayarları
        Schema::create('wa_automation_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->nullable()->constrained('marketplace_stores')->nullOnDelete();
            $table->string('key', 80);
            $table->json('value')->nullable();
            $table->timestamps();
            $table->unique(['store_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_automation_configs');
        Schema::dropIfExists('wa_stock_waitlists');
        Schema::dropIfExists('wa_tracking_links');
        Schema::dropIfExists('wa_cart_recovery_runs');
        Schema::dropIfExists('wa_coupons');
        Schema::dropIfExists('wa_abandoned_carts');
    }
};
