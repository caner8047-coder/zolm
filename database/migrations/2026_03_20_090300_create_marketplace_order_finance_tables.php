<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->foreignId('legal_entity_id')->constrained('legal_entities')->cascadeOnDelete();
            $table->string('external_order_id', 120);
            $table->string('order_number', 100);
            $table->string('order_status', 50)->default('new');
            $table->string('commercial_type', 50)->nullable();
            $table->string('customer_name', 150)->nullable();
            $table->string('customer_email', 150)->nullable();
            $table->string('customer_phone', 32)->nullable();
            $table->string('billing_name', 150)->nullable();
            $table->string('billing_tax_number', 32)->nullable();
            $table->string('shipment_country', 120)->nullable();
            $table->string('shipment_city', 120)->nullable();
            $table->string('shipment_district', 120)->nullable();
            $table->timestamp('ordered_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'external_order_id'], 'channel_orders_store_external_unique');
            $table->index(['store_id', 'order_number'], 'channel_orders_store_order_number_idx');
            $table->index(['store_id', 'order_status'], 'channel_orders_store_status_idx');
            $table->index(['legal_entity_id', 'ordered_at'], 'channel_orders_entity_ordered_idx');
        });

        Schema::create('channel_order_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->foreignId('channel_order_id')->constrained('channel_orders')->cascadeOnDelete();
            $table->string('external_package_id', 120);
            $table->string('package_number', 120)->nullable();
            $table->string('package_status', 50)->default('new');
            $table->string('cargo_company', 120)->nullable();
            $table->string('cargo_tracking_number', 120)->nullable();
            $table->string('cargo_barcode', 120)->nullable();
            $table->decimal('cargo_desi', 8, 2)->nullable();
            $table->string('shipment_provider', 120)->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(['channel_order_id', 'external_package_id'], 'channel_order_packages_order_external_unique');
            $table->index(['store_id', 'cargo_tracking_number'], 'channel_order_packages_store_tracking_idx');
        });

        Schema::create('channel_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->foreignId('channel_order_id')->constrained('channel_orders')->cascadeOnDelete();
            $table->foreignId('channel_order_package_id')->nullable()->constrained('channel_order_packages')->nullOnDelete();
            $table->foreignId('channel_listing_id')->nullable()->constrained('channel_listings')->nullOnDelete();
            $table->foreignId('mp_product_id')->nullable()->constrained('mp_products')->nullOnDelete();
            $table->string('external_line_id', 120);
            $table->string('stock_code', 100)->nullable();
            $table->string('barcode', 100)->nullable();
            $table->string('product_name')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)->nullable();
            $table->decimal('gross_amount', 12, 2)->nullable();
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('marketplace_discount_amount', 12, 2)->default(0);
            $table->decimal('billable_amount', 12, 2)->nullable();
            $table->decimal('commission_rate', 5, 2)->nullable();
            $table->decimal('vat_rate', 5, 2)->nullable();
            $table->string('line_status', 50)->default('new');
            $table->boolean('is_matched')->default(false);
            $table->string('match_source', 30)->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'external_line_id'], 'channel_order_items_store_external_unique');
            $table->index(['store_id', 'stock_code'], 'channel_order_items_store_stock_idx');
            $table->index(['store_id', 'barcode'], 'channel_order_items_store_barcode_idx');
            $table->index(['store_id', 'line_status'], 'channel_order_items_store_status_idx');
        });

        Schema::create('order_financial_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->foreignId('legal_entity_id')->constrained('legal_entities')->cascadeOnDelete();
            $table->foreignId('channel_order_id')->nullable()->constrained('channel_orders')->nullOnDelete();
            $table->foreignId('channel_order_package_id')->nullable()->constrained('channel_order_packages')->nullOnDelete();
            $table->foreignId('channel_order_item_id')->nullable()->constrained('channel_order_items')->nullOnDelete();
            $table->string('event_source', 50);
            $table->string('event_type', 50);
            $table->string('external_event_id', 120)->nullable();
            $table->string('reference_number', 120)->nullable();
            $table->timestamp('event_date')->nullable();
            $table->timestamp('due_date')->nullable();
            $table->timestamp('settlement_date')->nullable();
            $table->decimal('amount', 14, 2)->default(0);
            $table->string('currency', 3)->default('TRY');
            $table->string('direction', 10)->default('debit');
            $table->string('status', 30)->default('pending');
            $table->text('notes')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'event_source', 'external_event_id'], 'order_financial_events_store_source_external_unique');
            $table->index(['store_id', 'event_type', 'event_date'], 'order_financial_events_store_type_date_idx');
            $table->index(['channel_order_id', 'status'], 'order_financial_events_order_status_idx');
        });

        Schema::create('order_profit_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->foreignId('channel_order_id')->constrained('channel_orders')->cascadeOnDelete();
            $table->foreignId('channel_order_item_id')->nullable()->constrained('channel_order_items')->nullOnDelete();
            $table->string('profit_state', 50)->default('estimated');
            $table->decimal('gross_revenue', 14, 2)->default(0);
            $table->decimal('net_receivable', 14, 2)->default(0);
            $table->decimal('commission_total', 14, 2)->default(0);
            $table->decimal('cargo_total', 14, 2)->default(0);
            $table->decimal('service_fee_total', 14, 2)->default(0);
            $table->decimal('withholding_total', 14, 2)->default(0);
            $table->decimal('packaging_cost', 14, 2)->default(0);
            $table->decimal('own_cargo_cost', 14, 2)->default(0);
            $table->decimal('cogs_cost', 14, 2)->default(0);
            $table->decimal('return_effect', 14, 2)->default(0);
            $table->decimal('vat_effect', 14, 2)->default(0);
            $table->decimal('estimated_profit', 14, 2)->default(0);
            $table->decimal('confirmed_profit', 14, 2)->default(0);
            $table->decimal('margin_percent', 8, 2)->default(0);
            $table->timestamp('calculated_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['store_id', 'profit_state'], 'order_profit_snapshots_store_state_idx');
            $table->index(['channel_order_id', 'calculated_at'], 'order_profit_snapshots_order_calculated_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_profit_snapshots');
        Schema::dropIfExists('order_financial_events');
        Schema::dropIfExists('channel_order_items');
        Schema::dropIfExists('channel_order_packages');
        Schema::dropIfExists('channel_orders');
    }
};
