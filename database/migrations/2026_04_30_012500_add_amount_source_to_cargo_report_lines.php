<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cargo_report_lines', function (Blueprint $table) {
            $table->string('amount_source', 40)->default('empty')->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('cargo_report_lines', function (Blueprint $table) {
            $table->dropColumn('amount_source');
        });
    }
};
