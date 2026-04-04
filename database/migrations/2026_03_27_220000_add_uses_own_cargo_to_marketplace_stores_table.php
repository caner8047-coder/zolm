<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_stores', function (Blueprint $table) {
            $table->boolean('uses_own_cargo')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_stores', function (Blueprint $table) {
            $table->dropColumn('uses_own_cargo');
        });
    }
};
