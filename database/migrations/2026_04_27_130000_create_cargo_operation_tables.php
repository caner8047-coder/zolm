<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cargo_carrier_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_entity_id')->nullable()->constrained('legal_entities')->nullOnDelete();
            $table->string('carrier_code', 30)->default('surat');
            $table->string('carrier_name', 120)->default('Sürat Kargo');
            $table->string('account_name', 150)->nullable();
            $table->string('customer_code', 80)->nullable();
            $table->string('sender_username', 120)->nullable();
            $table->text('sender_password_encrypted')->nullable();
            $table->text('query_password_encrypted')->nullable();
            $table->string('cod_username', 120)->nullable();
            $table->text('cod_password_encrypted')->nullable();
            $table->string('api_base_url')->nullable();
            $table->string('query_base_url')->nullable();
            $table->string('branch_code', 80)->nullable();
            $table->string('origin_city', 120)->nullable();
            $table->string('origin_district', 120)->nullable();
            $table->text('origin_address')->nullable();
            $table->string('contact_name', 150)->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('status', 30)->default('draft');
            $table->timestamp('last_verified_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('settings_json')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'carrier_code', 'customer_code'], 'cargo_accounts_user_carrier_customer_unique');
            $table->index(['user_id', 'carrier_code', 'is_active'], 'cargo_accounts_user_carrier_active_idx');
            $table->index(['legal_entity_id', 'is_default'], 'cargo_accounts_entity_default_idx');
        });

        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_entity_id')->nullable()->constrained('legal_entities')->nullOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('marketplace_stores')->nullOnDelete();
            $table->foreignId('channel_order_id')->nullable()->constrained('channel_orders')->nullOnDelete();
            $table->foreignId('channel_order_package_id')->nullable()->constrained('channel_order_packages')->nullOnDelete();
            $table->foreignId('channel_claim_id')->nullable()->constrained('channel_claims')->nullOnDelete();
            $table->foreignId('supply_order_id')->nullable()->constrained('supply_orders')->nullOnDelete();
            $table->foreignId('cargo_carrier_account_id')->nullable()->constrained('cargo_carrier_accounts')->nullOnDelete();
            $table->string('shipment_no', 80)->unique();
            $table->string('source_type', 50)->default('manual');
            $table->string('direction', 20)->default('outgoing');
            $table->string('flow_type', 40)->default('order');
            $table->string('carrier_code', 30)->default('surat');
            $table->string('carrier_name', 120)->default('Sürat Kargo');
            $table->string('external_shipment_id', 120)->nullable();
            $table->string('reference_number', 120)->nullable();
            $table->string('order_number', 120)->nullable();
            $table->string('package_number', 120)->nullable();
            $table->string('tracking_number', 120)->nullable();
            $table->string('barcode', 160)->nullable();
            $table->string('status', 40)->default('draft');
            $table->string('status_label', 160)->nullable();
            $table->string('customer_name', 200)->nullable();
            $table->string('customer_phone', 40)->nullable();
            $table->string('destination_city', 120)->nullable();
            $table->string('destination_district', 120)->nullable();
            $table->text('destination_address')->nullable();
            $table->string('sender_name', 200)->nullable();
            $table->string('sender_phone', 40)->nullable();
            $table->string('origin_city', 120)->nullable();
            $table->string('origin_district', 120)->nullable();
            $table->text('origin_address')->nullable();
            $table->unsignedSmallInteger('parcel_count')->default(1);
            $table->decimal('total_desi', 10, 2)->default(0);
            $table->decimal('total_weight', 10, 2)->default(0);
            $table->decimal('expected_cost', 12, 2)->default(0);
            $table->decimal('actual_cost', 12, 2)->default(0);
            $table->decimal('invoice_cost', 12, 2)->default(0);
            $table->decimal('cost_delta', 12, 2)->default(0);
            $table->string('currency', 3)->default('TRY');
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('last_tracked_at')->nullable();
            $table->timestamp('last_event_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('raw_payload')->nullable();
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'carrier_code', 'status'], 'shipments_user_carrier_status_idx');
            $table->index(['store_id', 'channel_order_id'], 'shipments_store_order_idx');
            $table->index(['channel_order_package_id', 'carrier_code'], 'shipments_package_carrier_idx');
            $table->index(['channel_claim_id', 'flow_type'], 'shipments_claim_flow_idx');
            $table->index(['tracking_number'], 'shipments_tracking_idx');
            $table->index(['order_number'], 'shipments_order_number_idx');
            $table->index(['source_type', 'direction', 'flow_type'], 'shipments_source_direction_flow_idx');
        });

        Schema::create('shipment_parcels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->unsignedSmallInteger('parcel_index')->default(1);
            $table->string('external_parcel_id', 120)->nullable();
            $table->string('tracking_number', 120)->nullable();
            $table->string('barcode', 160)->nullable();
            $table->decimal('desi', 10, 2)->default(0);
            $table->decimal('weight', 10, 2)->default(0);
            $table->decimal('width', 10, 2)->default(0);
            $table->decimal('height', 10, 2)->default(0);
            $table->decimal('length', 10, 2)->default(0);
            $table->unsignedSmallInteger('piece_count')->default(1);
            $table->string('status', 40)->default('draft');
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['shipment_id', 'parcel_index'], 'shipment_parcels_shipment_index_idx');
            $table->index(['tracking_number'], 'shipment_parcels_tracking_idx');
            $table->index(['barcode'], 'shipment_parcels_barcode_idx');
        });

        Schema::create('shipment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->foreignId('channel_order_item_id')->nullable()->constrained('channel_order_items')->nullOnDelete();
            $table->foreignId('channel_claim_item_id')->nullable()->constrained('channel_claim_items')->nullOnDelete();
            $table->foreignId('mp_product_id')->nullable()->constrained('mp_products')->nullOnDelete();
            $table->string('stock_code', 120)->nullable();
            $table->string('barcode', 120)->nullable();
            $table->string('product_name')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->unsignedSmallInteger('expected_pieces')->default(1);
            $table->decimal('expected_desi', 10, 2)->default(0);
            $table->decimal('expected_cost', 12, 2)->default(0);
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->index(['shipment_id', 'stock_code'], 'shipment_items_shipment_stock_idx');
            $table->index(['channel_order_item_id'], 'shipment_items_order_item_idx');
        });

        Schema::create('shipment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->string('carrier_code', 30)->default('surat');
            $table->string('event_code', 80)->nullable();
            $table->string('event_status', 80)->nullable();
            $table->text('event_description')->nullable();
            $table->string('location_city', 120)->nullable();
            $table->string('location_district', 120)->nullable();
            $table->string('branch_name', 160)->nullable();
            $table->timestamp('event_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->boolean('is_terminal')->default(false);
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['shipment_id', 'event_at'], 'shipment_events_shipment_event_at_idx');
            $table->index(['carrier_code', 'event_status'], 'shipment_events_carrier_status_idx');
        });

        Schema::create('cargo_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_entity_id')->nullable()->constrained('legal_entities')->nullOnDelete();
            $table->foreignId('cargo_carrier_account_id')->nullable()->constrained('cargo_carrier_accounts')->nullOnDelete();
            $table->foreignId('shipment_id')->nullable()->constrained('shipments')->nullOnDelete();
            $table->string('carrier_code', 30)->default('surat');
            $table->string('invoice_number', 120)->nullable();
            $table->date('invoice_date')->nullable();
            $table->string('waybill_number', 120)->nullable();
            $table->string('tracking_number', 120)->nullable();
            $table->string('barcode', 160)->nullable();
            $table->string('order_reference', 120)->nullable();
            $table->string('sender_name', 200)->nullable();
            $table->string('recipient_name', 200)->nullable();
            $table->string('origin_city', 120)->nullable();
            $table->string('destination_city', 120)->nullable();
            $table->string('destination_district', 120)->nullable();
            $table->unsignedSmallInteger('parcel_count')->default(1);
            $table->decimal('desi', 10, 2)->default(0);
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('vat_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('TRY');
            $table->string('status', 40)->default('pending');
            $table->boolean('is_reconciled')->default(false);
            $table->string('discrepancy_type', 60)->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'carrier_code', 'invoice_date'], 'cargo_invoice_lines_user_carrier_date_idx');
            $table->index(['tracking_number'], 'cargo_invoice_lines_tracking_idx');
            $table->index(['order_reference'], 'cargo_invoice_lines_order_ref_idx');
            $table->index(['shipment_id', 'is_reconciled'], 'cargo_invoice_lines_shipment_reconciled_idx');
        });

        Schema::create('shipment_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->foreignId('cargo_invoice_line_id')->nullable()->constrained('cargo_invoice_lines')->nullOnDelete();
            $table->string('cost_source', 40)->default('expected');
            $table->string('cost_type', 60)->default('shipping');
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('direction', 10)->default('debit');
            $table->string('currency', 3)->default('TRY');
            $table->string('external_reference', 160)->nullable();
            $table->timestamp('cost_date')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['shipment_id', 'cost_source', 'cost_type'], 'shipment_costs_shipment_source_type_idx');
            $table->index(['cargo_invoice_line_id'], 'shipment_costs_invoice_line_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_costs');
        Schema::dropIfExists('cargo_invoice_lines');
        Schema::dropIfExists('shipment_events');
        Schema::dropIfExists('shipment_items');
        Schema::dropIfExists('shipment_parcels');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('cargo_carrier_accounts');
    }
};
