<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ödeme Detay (Settlement) satırlarının benzersiz Kayıt No'sunu saklamak için
     * document_number sütunu eklenir.
     * 
     * Bu alan olmadan, qty>1 siparişlere ait aynı tutarlı settlement satırları
     * import sırasında birbirini eziyor ve yanlış "Eksik Ödeme" audit alarmlarına
     * neden oluyordu.
     */
    public function up(): void
    {
        Schema::table('mp_settlements', function (Blueprint $table) {
            $table->string('document_number', 100)->nullable()->after('order_number');
            $table->index('document_number');
        });
    }

    public function down(): void
    {
        Schema::table('mp_settlements', function (Blueprint $table) {
            $table->dropIndex(['document_number']);
            $table->dropColumn('document_number');
        });
    }
};
