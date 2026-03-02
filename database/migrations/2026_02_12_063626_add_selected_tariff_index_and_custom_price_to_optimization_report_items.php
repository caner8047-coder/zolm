<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('optimization_report_items', function (Blueprint $table) {
            // Kullanıcının seçtiği tarife indeksi (0=Mevcut, 1=Tarife 2, 2=Tarife 3, 3=Tarife 4)
            $table->tinyInteger('selected_tariff_index')->nullable()->after('is_selected');
            // Kullanıcının girdiği özel fiyat (tarife fiyatını override eder)
            $table->decimal('custom_price', 10, 2)->nullable()->after('selected_tariff_index');
        });
    }

    public function down(): void
    {
        Schema::table('optimization_report_items', function (Blueprint $table) {
            $table->dropColumn(['selected_tariff_index', 'custom_price']);
        });
    }
};
