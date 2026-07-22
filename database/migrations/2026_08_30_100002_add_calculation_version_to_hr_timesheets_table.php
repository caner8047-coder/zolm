<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_timesheets', function (Blueprint $table) {
            $table->unsignedSmallInteger('calculation_version')->default(1)->after('calculation_flags');
        });
    }

    public function down(): void
    {
        Schema::table('hr_timesheets', fn (Blueprint $table) => $table->dropColumn('calculation_version'));
    }
};
