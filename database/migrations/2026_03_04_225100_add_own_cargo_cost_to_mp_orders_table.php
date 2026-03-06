<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mp_orders', function (Blueprint $table) {
            $table->decimal('own_cargo_cost_at_time', 12, 2)
                  ->nullable()
                  ->after('packaging_cost_at_time')
                  ->comment('Sipariş anı kendi kargo maliyeti (birim × adet)');
        });
    }

    public function down(): void
    {
        Schema::table('mp_orders', function (Blueprint $table) {
            $table->dropColumn('own_cargo_cost_at_time');
        });
    }
};
