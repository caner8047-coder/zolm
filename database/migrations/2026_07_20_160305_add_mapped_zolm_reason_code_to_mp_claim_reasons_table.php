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
        Schema::table('mp_claim_reasons', function (Blueprint $table) {
            $table->string('mapped_zolm_reason_code')->nullable()->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mp_claim_reasons', function (Blueprint $table) {
            $table->dropColumn('mapped_zolm_reason_code');
        });
    }
};
