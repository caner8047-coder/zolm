<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mp_products', function (Blueprint $table) {
            if (! Schema::hasColumn('mp_products', 'unit_name')) {
                $table->string('unit_name', 30)->default('adet')->after('category_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mp_products', function (Blueprint $table) {
            if (Schema::hasColumn('mp_products', 'unit_name')) {
                $table->dropColumn('unit_name');
            }
        });
    }
};
