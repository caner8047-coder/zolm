<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pazaryeri Ürünlerim — Tam Ürün Verisi Genişletmesi
     *
     * Mevcut mp_products tablosuna Trendyol export ve manuel Excel
     * verilerini barındıracak 28+ yeni alan eklenir.
     * Mevcut barcode-bazlı unique constraint korunur.
     */
    public function up(): void
    {
        Schema::table('mp_products', function (Blueprint $table) {
            // ─── Tanımlayıcılar ────────────────────────────────────
            $table->string('stock_code')->nullable()->index()->after('barcode');
            $table->string('model_code')->nullable()->after('stock_code');
            $table->string('partner_id')->nullable()->after('model_code');

            // ─── Ürün Özellikleri ──────────────────────────────────
            $table->string('color')->nullable()->after('product_name');
            $table->string('size')->nullable()->after('color');
            $table->string('dimension')->nullable()->after('size');
            $table->string('gender')->nullable()->after('dimension');
            $table->string('brand')->nullable()->index()->after('gender');
            $table->string('category_name')->nullable()->index()->after('brand');
            $table->text('description')->nullable()->after('category_name');

            // ─── Fiyatlandırma ─────────────────────────────────────
            $table->decimal('market_price', 10, 2)->default(0)->after('vat_rate');
            $table->decimal('sale_price', 10, 2)->default(0)->after('market_price');
            $table->decimal('buybox_price', 10, 2)->nullable()->after('sale_price');
            $table->decimal('commission_rate', 5, 2)->default(0)->after('buybox_price');

            // ─── Stok & Lojistik ───────────────────────────────────
            $table->integer('stock_quantity')->default(0)->after('commission_rate');
            $table->decimal('cargo_cost', 10, 2)->default(0)->after('stock_quantity');
            $table->integer('pieces')->default(1)->after('cargo_cost');
            $table->decimal('desi', 8, 2)->default(0)->after('pieces');
            $table->decimal('otv_rate', 5, 2)->default(0)->after('desi');

            // ─── Durum ─────────────────────────────────────────────
            $table->string('status')->default('active')->after('otv_rate');
            $table->string('variant')->nullable()->after('status');
            $table->text('platforms')->nullable()->after('variant');

            // ─── Trendyol Spesifik ─────────────────────────────────
            $table->string('image_url', 512)->nullable()->after('platforms');
            $table->json('image_urls')->nullable()->after('image_url');
            $table->integer('shipping_days')->nullable()->after('image_urls');
            $table->string('shipping_type')->nullable()->after('shipping_days');
            $table->string('trendyol_link', 512)->nullable()->after('shipping_type');
            $table->string('status_description')->nullable()->after('trendyol_link');

            // ─── Import Meta ───────────────────────────────────────
            $table->string('import_source')->nullable()->after('status_description');
            $table->timestamp('last_synced_at')->nullable()->after('import_source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mp_products', function (Blueprint $table) {
            $table->dropColumn([
                'stock_code', 'model_code', 'partner_id',
                'color', 'size', 'dimension', 'gender', 'brand', 'category_name', 'description',
                'market_price', 'sale_price', 'buybox_price', 'commission_rate',
                'stock_quantity', 'cargo_cost', 'pieces', 'desi', 'otv_rate',
                'status', 'variant', 'platforms',
                'image_url', 'image_urls', 'shipping_days', 'shipping_type',
                'trendyol_link', 'status_description',
                'import_source', 'last_synced_at',
            ]);
        });
    }
};
