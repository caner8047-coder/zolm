<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_customer_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('crm_contacts')->cascadeOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('marketplace_stores')->nullOnDelete();
            $table->foreignId('channel_order_id')->nullable()->constrained('channel_orders')->nullOnDelete();
            $table->foreignId('channel_order_item_id')->nullable()->constrained('channel_order_items')->nullOnDelete();
            $table->foreignId('mp_product_id')->nullable()->constrained('mp_products')->nullOnDelete();
            $table->foreignId('recipe_id')->nullable()->constrained('recipes')->nullOnDelete();
            $table->string('source_type', 50)->default('manual');
            $table->string('source_key', 191)->nullable();
            $table->nullableMorphs('subject');
            $table->string('platform', 80)->nullable();
            $table->string('marketplace_order_number', 120)->nullable();
            $table->string('product_name', 255);
            $table->string('stock_code', 120)->nullable();
            $table->string('barcode', 120)->nullable();
            $table->string('recipe_name', 255)->nullable();
            $table->string('recipe_version', 40)->nullable();
            $table->string('tariff_name', 120)->nullable();
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('gross_amount', 14, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('commission_rate', 5, 2)->default(0);
            $table->decimal('commission_amount', 14, 2)->default(0);
            $table->decimal('cargo_amount', 14, 2)->default(0);
            $table->decimal('cost_amount', 14, 2)->default(0);
            $table->decimal('net_amount', 14, 2)->default(0);
            $table->decimal('profit_amount', 14, 2)->default(0);
            $table->string('currency', 3)->default('TRY');
            $table->string('status', 40)->default('completed');
            $table->timestamp('purchased_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('payload_json')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'source_key'], 'crm_customer_ledger_user_source_unique');
            $table->index(['user_id', 'purchased_at'], 'crm_customer_ledger_user_purchased_idx');
            $table->index(['contact_id', 'purchased_at'], 'crm_customer_ledger_contact_purchased_idx');
            $table->index(['store_id', 'purchased_at'], 'crm_customer_ledger_store_purchased_idx');
            $table->index(['user_id', 'platform'], 'crm_customer_ledger_user_platform_idx');
            $table->index(['user_id', 'status'], 'crm_customer_ledger_user_status_idx');
            $table->index(['user_id', 'stock_code'], 'crm_customer_ledger_user_stock_idx');
            $table->index(['recipe_id', 'purchased_at'], 'crm_customer_ledger_recipe_purchased_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_customer_ledger_entries');
    }
};
