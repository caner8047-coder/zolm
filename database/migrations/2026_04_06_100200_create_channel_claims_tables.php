<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->string('external_claim_id', 120);
            $table->string('order_number', 100)->nullable();
            $table->string('cargo_tracking_number', 100)->nullable();
            $table->string('cargo_provider', 100)->nullable();
            $table->string('status', 50)->default('pending'); // pending, approved, rejected, cancelled, received, etc
            $table->string('type', 30)->default('return'); // return, cancel, exchange
            $table->string('reason', 255)->nullable();
            $table->text('reason_detail')->nullable();
            $table->text('customer_note')->nullable();
            $table->string('customer_name', 200)->nullable();
            
            $table->timestamp('created_date')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'external_claim_id'], 'channel_claims_store_external_unique');
            $table->index(['store_id', 'status'], 'channel_claims_store_status_idx');
            $table->index(['order_number'], 'channel_claims_order_number_idx');
        });

        Schema::create('channel_claim_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_id')->constrained('channel_claims')->cascadeOnDelete();
            $table->string('external_item_id', 120);
            $table->string('external_order_line_id', 120)->nullable();
            $table->string('product_name', 500)->nullable();
            $table->string('barcode', 100)->nullable();
            $table->string('stock_code', 100)->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('price', 10, 2)->nullable();
            $table->string('status', 50)->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(['claim_id', 'external_item_id'], 'channel_claim_items_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_claim_items');
        Schema::dropIfExists('channel_claims');
    }
};
