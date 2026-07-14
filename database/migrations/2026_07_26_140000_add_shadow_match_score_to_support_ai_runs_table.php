<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_ai_runs', function (Blueprint $table) {
            $table->integer('shadow_match_score')->nullable(); // 0-100 benzerlik skoru
        });
    }

    public function down(): void
    {
        Schema::table('support_ai_runs', function (Blueprint $table) {
            $table->dropColumn('shadow_match_score');
        });
    }
};
