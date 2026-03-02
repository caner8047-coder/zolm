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
        Schema::create('mp_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            
            $table->string('barcode')->index();
            $table->string('product_name')->nullable();
            $table->decimal('cogs', 10, 2)->default(0)->comment('Üretim/Tedarik Maliyeti');
            $table->decimal('packaging_cost', 10, 2)->default(0)->comment('Ambalaj Maliyeti');
            $table->decimal('vat_rate', 5, 2)->default(10.00)->comment('KDV Oranı (%) 1, 10 veya 20');
            $table->unsignedBigInteger('category_id')->nullable()->comment('Sonraki faz (Komisyon Matrisi) için kategori ID');
            
            $table->timestamps();

            // Aynı kullanıcının aynı barkodu çift girememesi için unique constraint
            $table->unique(['user_id', 'barcode']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mp_products');
    }
};
