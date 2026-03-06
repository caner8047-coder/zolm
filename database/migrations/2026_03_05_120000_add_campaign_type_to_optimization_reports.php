<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kampanya modülleri için optimization_reports tablosuna
     * campaign_type ve campaign_data sütunları ekle.
     */
    public function up(): void
    {
        Schema::table('optimization_reports', function (Blueprint $table) {
            $table->string('campaign_type', 20)->default('tariff')->after('status');
            // tariff = Ürün Komisyon Tarifeleri
            // plus = Plus Komisyon Tarifeleri
            // badge = Avantajlı Ürün Etiketleri
            // flash = Flaş Ürünler
        });

        Schema::table('optimization_report_items', function (Blueprint $table) {
            $table->json('campaign_data')->nullable()->after('scenario_details');
            // Modüle özel veriler: yıldız seviyeleri, flaş tarihleri, plus detayları vb.
        });
    }

    public function down(): void
    {
        Schema::table('optimization_reports', function (Blueprint $table) {
            $table->dropColumn('campaign_type');
        });

        Schema::table('optimization_report_items', function (Blueprint $table) {
            $table->dropColumn('campaign_data');
        });
    }
};
