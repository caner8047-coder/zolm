<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->string('external_product_id', 120);
            $table->string('external_parent_id', 120)->nullable();
            $table->string('stock_code', 100)->nullable();
            $table->string('barcode', 100)->nullable();
            $table->string('title')->nullable();
            $table->string('brand', 120)->nullable();
            $table->string('category_name', 180)->nullable();
            $table->decimal('vat_rate', 5, 2)->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'external_product_id'], 'channel_products_store_external_unique');
            $table->index(['store_id', 'stock_code'], 'channel_products_store_stock_idx');
            $table->index(['store_id', 'barcode'], 'channel_products_store_barcode_idx');
        });

        Schema::create('channel_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->foreignId('channel_product_id')->nullable()->constrained('channel_products')->nullOnDelete();
            $table->foreignId('mp_product_id')->nullable()->constrained('mp_products')->nullOnDelete();
            $table->string('listing_id', 120);
            $table->string('listing_status', 50)->default('draft');
            $table->decimal('sale_price', 12, 2)->nullable();
            $table->decimal('list_price', 12, 2)->nullable();
            $table->string('currency', 3)->default('TRY');
            $table->integer('stock_quantity')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('last_price_sync_at')->nullable();
            $table->timestamp('last_stock_sync_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'listing_id'], 'channel_listings_store_listing_unique');
            $table->index(['store_id', 'mp_product_id'], 'channel_listings_store_mp_product_idx');
            $table->index(['store_id', 'listing_status'], 'channel_listings_store_status_idx');
        });

        Schema::create('product_match_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->foreignId('channel_listing_id')->nullable()->constrained('channel_listings')->nullOnDelete();
            $table->string('match_status', 30)->default('pending');
            $table->string('match_reason', 150)->nullable();
            $table->json('candidate_ids_json')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'match_status'], 'product_match_issues_store_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_match_issues');
        Schema::dropIfExists('channel_listings');
        Schema::dropIfExists('channel_products');
    }
};
