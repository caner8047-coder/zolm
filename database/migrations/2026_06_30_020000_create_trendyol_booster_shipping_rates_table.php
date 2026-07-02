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
        Schema::create('trendyol_booster_shipping_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            
            $table->string('cargo_company', 50)->index(); // TEX, PTT, Aras, Sürat vb.
            $table->integer('desi')->index();
            $table->decimal('price', 8, 2); // KDV Hariç fiyat
            
            $table->string('marketplace', 50)->default('trendyol');
            $table->string('source', 100)->nullable(); // PDF Import vb.
            $table->timestamp('imported_at')->nullable();
            
            $table->timestamps();
            
            // Aynı satıcı (user_id), aynı kargo firması ve desi için sadece bir fiyat kaydı tutulmalı.
            // user_id nullable olduğundan default/system kayıtları user_id = null olur.
            $table->unique(['user_id', 'cargo_company', 'desi'], 'shipping_rates_user_company_desi_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trendyol_booster_shipping_rates');
    }
};
