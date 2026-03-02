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
        Schema::table('mp_orders', function (Blueprint $table) {
            $table->boolean('is_reconciled')->default(false)->after('is_flagged')->comment('Mutabık (Invoice/Satır Eşleşmiş) işlemi kilitler');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mp_orders', function (Blueprint $table) {
            $table->dropColumn('is_reconciled');
        });
    }
};
