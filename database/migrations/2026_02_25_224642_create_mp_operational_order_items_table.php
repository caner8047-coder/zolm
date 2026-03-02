<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mp_operational_order_items', function (Blueprint $table) {
            $table->id();
            
            // Master Order İlişkisi
            $table->foreignId('operational_order_id')->constrained('mp_operational_orders')->onDelete('cascade');
            $table->string('order_number'); // Hızlı arama için yedek
            
            // Ürün ve Varyant Bilgileri
            $table->string('barcode')->nullable();
            $table->string('stock_code')->nullable();
            $table->string('product_name')->nullable();
            $table->integer('quantity')->default(1);
            
            // Satır Bazlı Fiyatlar
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('sale_price', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            
            // Senkronizasyon (Faz 2 - Epic 1)
            $table->decimal('synced_cogs_unit', 10, 2)->nullable()->comment('Eşleşen Birim Maliyet');
            $table->decimal('synced_vat_rate', 5, 2)->nullable()->comment('Eşleşen KDV Oranı');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mp_operational_order_items');
    }
};
