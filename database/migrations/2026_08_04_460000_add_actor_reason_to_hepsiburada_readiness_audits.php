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
        Schema::table('hepsiburada_readiness_audits', function (Blueprint $table) {
            $table->string('reason', 255)->nullable()->after('acting_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hepsiburada_readiness_audits', function (Blueprint $table) {
            if (Schema::hasColumn('hepsiburada_readiness_audits', 'reason')) {
                $table->dropColumn('reason');
            }
        });
    }
};
