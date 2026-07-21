<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hepsiburada P0 — ChannelProduct tablosuna katalog ürün alanları ekleniyor.
     *
     * Bu alanlar listing verisinin ötesinde gerçek katalog içeriğini (açıklama, görseller,
     * özellikler, onay durumu) saklamak için eklenmiştir. Tüm alanlar nullable olduğundan
     * mevcut Trendyol ve diğer connector kayıtları etkilenmez.
     */
    public function up(): void
    {
        Schema::table('channel_products', function (Blueprint $table) {
            $table->text('description')->nullable()->after('title');
            $table->json('images')->nullable()->after('description');
            $table->json('attributes')->nullable()->after('images');
            $table->string('approval_status', 50)->nullable()->after('attributes');
            $table->json('rejection_reasons')->nullable()->after('approval_status');
            $table->string('import_tracking_id', 120)->nullable()->after('rejection_reasons');
            $table->boolean('is_catalog_product')->default(false)->after('import_tracking_id');

            $table->index(['store_id', 'approval_status'], 'channel_products_store_approval_idx');
            $table->index(['store_id', 'is_catalog_product'], 'channel_products_store_catalog_idx');
        });
    }

    public function down(): void
    {
        Schema::table('channel_products', function (Blueprint $table) {
            $table->dropIndex('channel_products_store_approval_idx');
            $table->dropIndex('channel_products_store_catalog_idx');
            $table->dropColumn([
                'description',
                'images',
                'attributes',
                'approval_status',
                'rejection_reasons',
                'import_tracking_id',
                'is_catalog_product',
            ]);
        });
    }
};
