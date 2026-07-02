<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trendyol_booster_keywords', function (Blueprint $table): void {
            $table->unsignedSmallInteger('checked_result_count')
                ->default(0)
                ->after('result_count');
        });
    }

    public function down(): void
    {
        Schema::table('trendyol_booster_keywords', function (Blueprint $table): void {
            $table->dropColumn('checked_result_count');
        });
    }
};
