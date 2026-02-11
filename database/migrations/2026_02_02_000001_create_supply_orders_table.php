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
        Schema::create('supply_orders', function (Blueprint $table) {
            $table->id();
            $table->string('siparis_no', 50)->unique(); // D kolonu - benzersiz anahtar
            $table->date('kayit_tarihi')->nullable(); // C kolonu
            $table->string('musteri_adi', 255); // U kolonu
            $table->string('telefon', 30)->nullable(); // T kolonu
            $table->text('adres')->nullable(); // Y kolonu
            $table->string('ilce', 100)->nullable(); // AA kolonu
            $table->string('il', 100)->nullable(); // AB kolonu
            $table->string('urun_adi', 500); // AN kolonu
            $table->string('kategori', 100)->nullable(); // AP kolonu
            $table->unsignedSmallInteger('adet')->default(1); // AQ kolonu
            $table->date('soz_tarihi')->nullable(); // BP kolonu
            $table->string('renk_etiketi', 50)->nullable(); // BW kolonu - filtre için
            
            // Durum yönetimi (manuel güncelleme)
            $table->enum('durum', ['bekliyor', 'uretim', 'paketleme', 'kargo', 'gonderildi'])
                  ->default('bekliyor');
            
            // Gecikme sebebiyeti
            $table->enum('sebebiyet', ['uretim', 'paketleme', 'kargo', 'yok'])
                  ->default('yok');
            
            // Meta bilgiler
            $table->date('gonderim_tarihi')->nullable(); // Gönderildiğinde set edilir
            $table->text('notlar')->nullable();
            
            $table->timestamps();
            
            // İndeksler
            $table->index('durum');
            $table->index('sebebiyet');
            $table->index('kayit_tarihi');
            $table->index('soz_tarihi');
            $table->index('renk_etiketi');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supply_orders');
    }
};
