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
        Schema::create('mp_operational_orders', function (Blueprint $table) {
            $table->id();
            // Benzersiz Anahtar (Master)
            $table->string('order_number')->unique();
            $table->string('package_number')->nullable();
            
            // Tarihler
            $table->dateTime('order_date')->nullable();
            $table->dateTime('delivery_date')->nullable();
            
            // Müşteri & Firma Bilgileri
            $table->string('customer_name')->nullable();
            $table->string('customer_city')->nullable();
            $table->string('customer_district')->nullable();
            $table->text('customer_address')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('company_name')->nullable();
            $table->string('tax_office')->nullable();
            $table->string('tax_number')->nullable();
            
            // Lojistik ve Statü
            $table->string('cargo_company')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('status')->nullable();
            $table->string('invoice_number')->nullable();
            
            // Toplam Finansal (Sepet Özeti)
            $table->decimal('total_gross_amount', 10, 2)->default(0);
            $table->decimal('total_discount', 10, 2)->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mp_operational_orders');
    }
};
