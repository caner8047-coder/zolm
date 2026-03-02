<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Senaryo detaylarını JSON olarak sakla
     * Her ürün için 4 tarife senaryosunun P&L analizi
     */
    public function up(): void
    {
        Schema::table('optimization_report_items', function (Blueprint $table) {
            $table->json('scenario_details')->nullable()->after('is_selected');
        });
    }

    public function down(): void
    {
        Schema::table('optimization_report_items', function (Blueprint $table) {
            $table->dropColumn('scenario_details');
        });
    }
};
