<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optimizasyon Rapor Tabloları (Kurumsal Hafıza)
     * 
     * Her tarife analizi çalıştırıldığında sonuçlar kalıcı olarak
     * saklanır. Geçmişe dönük kârlılık analizleri incelenebilir.
     */
    public function up(): void
    {
        // Rapor Başlığı
        Schema::create('optimization_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');                                           // "11 Şubat 2026 Analizi"
            $table->integer('total_products')->default(0);                    // Toplam analiz edilen
            $table->integer('opportunity_count')->default(0);                 // Fırsat bulunan
            $table->decimal('total_current_profit', 12, 2)->default(0);      // Mevcut toplam kâr
            $table->decimal('total_optimized_profit', 12, 2)->default(0);    // Optimize edilmiş toplam kâr
            $table->decimal('total_extra_profit', 12, 2)->default(0);        // Ekstra kâr potansiyeli
            $table->integer('unmatched_count')->default(0);                  // Maliyeti bulunamayan
            $table->string('original_filename')->nullable();
            $table->enum('status', ['completed', 'exported', 'applied'])->default('completed');
            $table->timestamps();
        });

        // Rapor Detay Satırları
        Schema::create('optimization_report_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('optimization_reports')->cascadeOnDelete();
            
            // Ürün bilgileri
            $table->string('stock_code', 50)->index();
            $table->string('barcode', 50)->nullable();
            $table->string('product_name')->nullable();
            
            // Mevcut durum
            $table->decimal('current_price', 10, 2)->default(0);
            $table->decimal('current_commission', 5, 2)->default(0);
            $table->decimal('current_net_profit', 10, 2)->default(0);
            
            // Önerilen senaryo
            $table->string('suggested_tariff')->nullable();         // "Tarife 2", "Tarife 3" vb.
            $table->decimal('suggested_price', 10, 2)->nullable();
            $table->decimal('suggested_commission', 5, 2)->nullable();
            $table->decimal('suggested_net_profit', 10, 2)->nullable();
            $table->decimal('extra_profit', 10, 2)->default(0);
            
            // Maliyetler (snapshot - o anki değerler)
            $table->decimal('production_cost', 10, 2)->default(0);
            $table->decimal('shipping_cost', 10, 2)->default(0);
            
            // Durum
            $table->enum('action', ['update', 'keep', 'warning'])->default('keep');
            $table->boolean('is_selected')->default(false);
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('optimization_report_items');
        Schema::dropIfExists('optimization_reports');
    }
};
