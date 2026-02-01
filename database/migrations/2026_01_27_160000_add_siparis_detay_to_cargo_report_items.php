<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sipariş detaylarını saklamak için JSON alan ekle
     * 
     * Bu alan, eşleşen sipariş(ler)in detaylarını içerir:
     * - stok_kodu
     * - urun_adi
     * - adet
     * - pazaryeri
     * - magaza
     */
    public function up(): void
    {
        Schema::table('cargo_report_items', function (Blueprint $table) {
            $table->json('siparis_detay')->nullable()->after('siparis_no');
        });
    }

    public function down(): void
    {
        Schema::table('cargo_report_items', function (Blueprint $table) {
            $table->dropColumn('siparis_detay');
        });
    }
};
