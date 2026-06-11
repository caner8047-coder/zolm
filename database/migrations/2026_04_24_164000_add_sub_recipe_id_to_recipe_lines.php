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
        Schema::table('recipe_lines', function (Blueprint $table) {
            $table->foreignId('sub_recipe_id')->nullable()->after('material_id')->constrained('recipes')->nullOnDelete();
            // material_id'yi nullable yapıyoruz çünkü satır ya bir malzeme ya da bir alt reçete olabilir
            $table->foreignId('material_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recipe_lines', function (Blueprint $table) {
            $table->dropForeign(['sub_recipe_id']);
            $table->dropColumn('sub_recipe_id');
            // Geri alırken material_id'yi tekrar zorunlu yapamayabiliriz eğer içi boş kayıtlar varsa,
            // ama kural gereği bırakabiliriz.
            $table->foreignId('material_id')->nullable(false)->change();
        });
    }
};
