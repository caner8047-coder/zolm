<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mp_products', function (Blueprint $table) {
            if (!Schema::hasColumn('mp_products', 'profit_commission_override_enabled')) {
                $table->boolean('profit_commission_override_enabled')
                    ->default(false)
                    ->after('commission_rate');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mp_products', function (Blueprint $table) {
            if (Schema::hasColumn('mp_products', 'profit_commission_override_enabled')) {
                $table->dropColumn('profit_commission_override_enabled');
            }
        });
    }
};
