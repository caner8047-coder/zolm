<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mp_periods', function (Blueprint $table) {
            $table->dropUnique('mp_periods_unique');
            $table->unique(
                ['user_id', 'year', 'month', 'marketplace', 'seller_id'],
                'mp_periods_user_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('mp_periods', function (Blueprint $table) {
            $table->dropUnique('mp_periods_user_unique');
            $table->unique(['year', 'month', 'marketplace', 'seller_id'], 'mp_periods_unique');
        });
    }
};
