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
        if (!Schema::hasColumn('mp_periods', 'is_locked')) {
            Schema::table('mp_periods', function (Blueprint $table) {
                $table->boolean('is_locked')->default(false)->after('total_audit_errors')
                      ->comment('Finansal dönemin mutabakatı kapatılıp excel veri girişlerine kilitlendiğini belirtir.');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mp_periods', function (Blueprint $table) {
            $table->dropColumn('is_locked');
        });
    }
};
