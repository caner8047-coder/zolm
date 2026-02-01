<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tazmin Talepleri Tablosu
     * 
     * Kargo firmasından talep edilecek tazminatları takip eder.
     * Kayıp ürün, hasarlı ürün, fazla faturalama vb. durumlar için.
     */
    public function up(): void
    {
        Schema::create('compensations', function (Blueprint $table) {
            $table->id();
            
            // Tazmin talebini oluşturan kullanıcı
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // İlişkili kargo rapor satırı (opsiyonel)
            $table->foreignId('cargo_report_item_id')->nullable()->constrained()->nullOnDelete();
            
            // Temel bilgiler
            $table->date('tarih');
            $table->string('musteri_adi');
            $table->string('takip_kodu', 30)->nullable();
            $table->string('urun_adi')->nullable();
            $table->string('stok_kodu', 30)->nullable();
            
            // Kargo firması
            $table->string('cargo_company', 50)->nullable();
            
            // Tazmin sebebi
            $table->enum('sebep', [
                'kayip_urun',      // Ürün kayboldu
                'hasarli_urun',    // Ürün hasarlı teslim edildi
                'desi_fazla',      // Fazla desi faturalama
                'tutar_fazla',     // Fazla tutar faturalama
                'gecikme',         // Teslimat gecikmesi
                'yanlis_teslim',   // Yanlış adrese teslim
                'iade_kayip',      // İade sürecinde kayıp
                'diger',           // Diğer sebepler
            ]);
            
            // Detaylı açıklama
            $table->text('aciklama')->nullable();
            
            // Talep edilen tutar
            $table->decimal('talep_tutari', 12, 2)->default(0);
            
            // Onaylanan tutar (kargo firmasının kabul ettiği)
            $table->decimal('onaylanan_tutar', 12, 2)->default(0);
            
            // Durum
            $table->enum('durum', [
                'beklemede',       // Tazmin bekleniyor
                'talep_edildi',    // Kargo firmasına iletildi
                'inceleniyor',     // Kargo firması inceliyor
                'onaylandi',       // Tazmin onaylandı
                'kismi_onay',      // Kısmi onay
                'reddedildi',      // Reddedildi
                'odendi',          // Ödeme alındı
                'kapandi',         // Dosya kapandı
            ])->default('beklemede');
            
            // Kargo firması referans numarası
            $table->string('kargo_referans_no', 50)->nullable();
            
            // Dosya ekleri (JSON array)
            $table->json('attachments')->nullable();
            
            // Tarihler
            $table->date('talep_tarihi')->nullable();
            $table->date('sonuc_tarihi')->nullable();
            
            $table->timestamps();
            
            // İndeksler
            $table->index(['user_id', 'durum']);
            $table->index('tarih');
            $table->index('sebep');
            $table->index('cargo_company');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compensations');
    }
};
