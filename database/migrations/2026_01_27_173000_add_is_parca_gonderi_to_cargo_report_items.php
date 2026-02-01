<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Parça gönderisi takibi için flag ekle
     * 
     * Fiyatı 0 TL olan siparişler "parça gönderisi" olarak işaretlenir.
     * Bu, eksik vida, kırık parça vb. için yapılan ek gönderimlerdir.
     */
    public function up(): void
    {
        Schema::table('cargo_report_items', function (Blueprint $table) {
            $table->boolean('is_parca_gonderi')->default(false)->after('is_iade');
        });
    }

    public function down(): void
    {
        Schema::table('cargo_report_items', function (Blueprint $table) {
            $table->dropColumn('is_parca_gonderi');
        });
    }
};
