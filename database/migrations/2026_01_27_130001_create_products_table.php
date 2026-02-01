<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ürün Master Tablosu
     * 
     * Her ürün için standart desi, parça ve tutar değerlerini saklar.
     * Kargo karşılaştırmasında "beklenen" değerleri hesaplamak için kullanılır.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            
            // Benzersiz ürün kodu: 1BRJZEM00048, 1PUFZEM00390 gibi
            $table->string('stok_kodu', 30)->unique();
            
            // Ürün adı
            $table->string('urun_adi');
            
            // Kaç parça/kolide gönderilecek (1-9 arası)
            $table->unsignedTinyInteger('parca')->default(1);
            
            // Toplam hacim (desi) - 100 desi üzeri paketler parçalanır
            $table->decimal('desi', 10, 2)->default(0);
            
            // Standart kargo ücreti (TL)
            $table->decimal('tutar', 10, 2)->default(0);
            
            // Ürün kategorisi (stok kodundan parse edilir: BRJ=Berjer, PUF=Puf, KNP=Kanepe)
            $table->string('kategori', 20)->nullable();
            
            // Aktif/Pasif durumu
            $table->boolean('is_active')->default(true);
            
            // Son güncelleyen kullanıcı
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            
            $table->timestamps();
            
            // İndeksler
            $table->index('stok_kodu');
            $table->index('kategori');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
