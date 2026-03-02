<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * product_costs tablosuna ambalaj maliyeti ve KDV oranı ekle
     * Pazaryeri Muhasebe modülü ile paylaşılan "Single Source of Truth"
     */
    public function up(): void
    {
        Schema::table('product_costs', function (Blueprint $table) {
            $table->decimal('packaging_cost', 10, 2)->default(0)->after('shipping_cost');
            $table->decimal('vat_rate', 4, 2)->default(20)->after('packaging_cost'); // %1, %10, %20
        });
    }

    public function down(): void
    {
        Schema::table('product_costs', function (Blueprint $table) {
            $table->dropColumn(['packaging_cost', 'vat_rate']);
        });
    }
};
