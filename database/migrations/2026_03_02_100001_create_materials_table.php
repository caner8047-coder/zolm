<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Tanımlayıcılar
            $table->string('code', 50);
            $table->string('name', 255);

            // Kategori & Birim
            $table->string('category', 30)->default('other');
            // fabric, foam, wood, hardware, packaging, textile, lining, other
            $table->string('base_unit', 10)->default('pcs');
            // m, m2, m3, pcs, kg, set

            // Fire
            $table->decimal('default_waste_rate', 5, 4)->default(0.10);

            // Kumaş-spesifik
            $table->decimal('fabric_width_cm', 8, 2)->nullable();
            $table->string('fabric_calc_method', 30)->default('area_div_width');
            // area_div_width, fixed_meter_per_piece

            // Sünger/Panel-spesifik
            $table->decimal('density_kg_m3', 8, 2)->nullable();
            $table->decimal('thickness_cm', 8, 2)->nullable();

            // Yuvarlama
            $table->string('rounding_mode', 15)->default('none');
            // none, ceil_step, round, floor
            $table->decimal('rounding_step', 5, 3)->nullable();

            // Fiyat & Tedarikçi
            $table->decimal('unit_price', 12, 4)->nullable();
            $table->string('currency', 3)->default('TRY');
            $table->string('supplier', 100)->nullable();

            // Meta
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes
            $table->unique(['user_id', 'code']);
            $table->index(['user_id', 'category']);
            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materials');
    }
};
