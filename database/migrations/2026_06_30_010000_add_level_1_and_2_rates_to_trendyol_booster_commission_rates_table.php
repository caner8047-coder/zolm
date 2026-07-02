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
        Schema::table('trendyol_booster_commission_rates', function (Blueprint $table) {
            $table->decimal('level_2_rate', 5, 2)->nullable()->after('level_3_rate');
            $table->decimal('level_1_rate', 5, 2)->nullable()->after('level_2_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trendyol_booster_commission_rates', function (Blueprint $table) {
            $table->dropColumn(['level_2_rate', 'level_1_rate']);
        });
    }
};
