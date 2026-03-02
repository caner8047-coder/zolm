<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->onDelete('cascade');
            $table->foreignId('material_id')->constrained()->onDelete('cascade');

            // Operasyon & kullanım yeri
            $table->string('operation', 20)->default('diger');
            // terzihane, doseme, marangoz, sunger, paketleme, demirhane, diger
            $table->string('usage_area', 100)->nullable();
            // oturum, sırt, kol, kol klapa, iskelet, gövde, vb.

            // Hesap tipi
            $table->string('calc_type', 20)->default('fixed_qty');
            // fabric_meter, area_m2, volume_m3, piece, fixed_qty

            // Ölçüler (cm cinsinden giriş)
            $table->decimal('width_cm', 10, 2)->nullable();
            $table->decimal('length_cm', 10, 2)->nullable();
            $table->decimal('height_cm', 10, 2)->nullable();
            $table->decimal('pieces', 10, 4)->default(1);

            // Override'lar (null = malzemeden al)
            $table->decimal('waste_rate_override', 5, 4)->nullable();
            $table->decimal('fabric_width_override', 8, 2)->nullable();

            // Sabit miktar (fixed_qty için)
            $table->decimal('constant_qty', 12, 6)->nullable();

            // Hesaplanan sonuç (cache)
            $table->decimal('calculated_qty', 12, 6)->default(0);
            $table->string('calculated_unit', 10)->default('pcs');

            // Meta
            $table->text('notes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            // Indexes
            $table->index(['recipe_id', 'operation']);
            $table->index(['recipe_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_lines');
    }
};
