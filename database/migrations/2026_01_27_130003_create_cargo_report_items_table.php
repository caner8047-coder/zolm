<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kargo Rapor Satırları
     * 
     * Her karşılaştırma satırı için detaylı bilgi saklar.
     * Beklenen vs gerçek değerler, farklar ve hata tipleri.
     */
    public function up(): void
    {
        Schema::create('cargo_report_items', function (Blueprint $table) {
            $table->id();
            
            // Ana rapor ilişkisi
            $table->foreignId('cargo_report_id')->constrained()->onDelete('cascade');
            
            // Temel bilgiler
            $table->date('tarih')->nullable();
            $table->string('musteri_adi');
            $table->string('takip_kodu', 30)->nullable();
            $table->string('stok_kodu', 30)->nullable();
            $table->string('urun_adi')->nullable();
            
            // Sipariş bilgileri
            $table->unsignedSmallInteger('adet')->default(1);
            
            // Beklenen (Doğru) değerler - Ürün listesinden hesaplanan
            $table->unsignedSmallInteger('beklenen_parca')->default(1);
            $table->decimal('beklenen_desi', 10, 2)->default(0);
            $table->decimal('beklenen_tutar', 10, 2)->default(0);
            
            // Gerçek (Kargo) değerler - Kargo raporundan gelen
            $table->unsignedSmallInteger('gercek_parca')->default(1);
            $table->decimal('gercek_desi', 10, 2)->default(0);
            $table->decimal('gercek_tutar', 10, 2)->default(0);
            
            // Fark hesaplamaları (gercek - beklenen)
            $table->smallInteger('parca_fark')->default(0);
            $table->decimal('desi_fark', 10, 2)->default(0);
            $table->decimal('tutar_fark', 10, 2)->default(0);
            
            // Hata tipi
            $table->enum('error_type', [
                'none',           // Hata yok
                'desi_eksik',     // Kargo az desi faturalıyor (bizim lehimize)
                'desi_fazla',     // Kargo fazla desi faturalıyor (bizim aleyhimize)
                'tutar_eksik',    // Kargo az tutar faturalıyor
                'tutar_fazla',    // Kargo fazla tutar faturalıyor
                'parca_eksik',    // Parça eksik teslim
                'parca_fazla',    // Parça fazla (beklenenden çok koli)
                'eslesmedi',      // Müşteri eşleşmedi
            ])->default('none');
            
            // Hata var mı? (hızlı filtreleme için)
            $table->boolean('has_error')->default(false);
            
            // Eşleşme durumu
            $table->boolean('is_matched')->default(true);
            
            // Sipariş kaynağı
            $table->string('pazaryeri', 30)->nullable();
            $table->string('magaza', 50)->nullable();
            $table->string('siparis_no', 50)->nullable();
            
            // Ek bilgiler
            $table->string('cikis_il', 50)->nullable();
            $table->boolean('is_iade')->default(false);
            
            $table->timestamps();
            
            // İndeksler
            $table->index(['cargo_report_id', 'has_error']);
            $table->index('takip_kodu');
            $table->index('musteri_adi');
            $table->index('stok_kodu');
            $table->index('error_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cargo_report_items');
    }
};
