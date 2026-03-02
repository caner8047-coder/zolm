<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ürün Maliyet Tablosu
     * 
     * Üretim ve kargo maliyetlerini ürün bazlı saklar.
     * Tarife optimizasyonunda "sabit maliyet" olarak kullanılır.
     */
    public function up(): void
    {
        Schema::create('product_costs', function (Blueprint $table) {
            $table->id();
            
            // Eşleşme anahtarları
            $table->string('stock_code', 50)->index();
            $table->string('barcode', 50)->nullable()->index();
            
            // Ürün bilgisi
            $table->string('product_name')->nullable();
            
            // Maliyetler
            $table->decimal('production_cost', 10, 2)->default(0); // Üretim Maliyeti
            $table->decimal('shipping_cost', 10, 2)->default(0);   // Kargo Maliyeti
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_costs');
    }
};
