<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hepsiburada P0 — Pazaryeri kategori özellikleri (attribute) sözlük tabloları.
     *
     * mp_category_attributes: Bir kategoriye bağlı özellik tanımları.
     * mp_category_attribute_values: Bir özelliğin olası değerleri.
     *
     * Her iki tablo da `marketplace` kolonu ile generic tasarlanmıştır;
     * Trendyol ve diğer pazaryerlerinin attribute sözlükleri de aynı tabloya yazılabilir.
     * Tenant'a bağlı değil — referans/sözlük verisi olarak tüm tenant'lar okuyabilir.
     */
    public function up(): void
    {
        Schema::create('mp_category_attributes', function (Blueprint $table) {
            $table->id();

            // Pazaryeri tanımlayıcısı (hepsiburada, trendyol, n11, …)
            $table->string('marketplace', 50);

            // Kategorinin platform kimliği (MpCategory.platform_category_id ile ilişki)
            $table->string('platform_category_id', 120);

            // Özelliğin platform kimliği
            $table->string('platform_attribute_id', 120);

            $table->string('name', 255);

            // Zorunlu mu?
            $table->boolean('is_required')->default(false);

            // Varyant oluşturucu mu?
            $table->boolean('is_variant')->default(false);

            // Çoklu seçim destekliyor mu?
            $table->boolean('is_multi_select')->default(false);

            // Veri tipi: string, integer, decimal, boolean, …
            $table->string('data_type', 50)->nullable();

            // Ham API yanıtı — kayıp alan riski olmadan tam payload
            $table->json('raw_payload')->nullable();

            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['marketplace', 'platform_category_id', 'platform_attribute_id'],
                'mp_cat_attrs_marketplace_cat_attr_unique'
            );
            $table->index(['marketplace', 'platform_category_id'], 'mp_cat_attrs_marketplace_cat_idx');
        });

        Schema::create('mp_category_attribute_values', function (Blueprint $table) {
            $table->id();

            $table->foreignId('mp_category_attribute_id')
                ->constrained('mp_category_attributes')
                ->cascadeOnDelete();

            // Değerin platform kimliği
            $table->string('platform_value_id', 120);

            $table->string('name', 255);

            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(
                ['mp_category_attribute_id', 'platform_value_id'],
                'mp_cat_attr_values_attr_value_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mp_category_attribute_values');
        Schema::dropIfExists('mp_category_attributes');
    }
};
