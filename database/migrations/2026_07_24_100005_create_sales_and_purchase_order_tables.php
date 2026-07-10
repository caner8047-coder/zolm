<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            $table->unsignedBigInteger('party_id');
            $table->unsignedBigInteger('legal_entity_id')->nullable();

            $table->string('document_number', 120);
            $table->date('order_date');
            $table->string('status', 30)->default('draft'); // draft, approved, cancelled

            $table->decimal('total_amount', 14, 2)->default(0.00);
            $table->string('currency_code', 3)->default('TRY');
            $table->decimal('exchange_rate', 12, 6)->default(1.000000);
            $table->string('description', 500)->nullable();

            // Sonuçlanan ilişkiler (onaylandığında set edilir)
            $table->unsignedBigInteger('receivable_id')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'document_number'], 'sales_orders_doc_unique');
            $table->index(['user_id', 'status']);
        });

        Schema::create('sales_order_items', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('sales_order_id');
            $table->foreign('sales_order_id')->references('id')->on('sales_orders')->cascadeOnDelete();

            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('stock_code', 100);
            $table->integer('quantity');
            $table->decimal('unit_price', 14, 2);
            $table->decimal('vat_rate', 5, 2)->default(20.00); // KDV %20 varsayılan
            $table->decimal('total_amount', 14, 2);

            $table->timestamps();
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            $table->unsignedBigInteger('party_id');
            $table->unsignedBigInteger('legal_entity_id')->nullable();

            $table->string('document_number', 120);
            $table->date('order_date');
            $table->string('status', 30)->default('draft'); // draft, approved, cancelled

            $table->decimal('total_amount', 14, 2)->default(0.00);
            $table->string('currency_code', 3)->default('TRY');
            $table->decimal('exchange_rate', 12, 6)->default(1.000000);
            $table->string('description', 500)->nullable();

            // Sonuçlanan ilişkiler (onaylandığında set edilir)
            $table->unsignedBigInteger('payable_id')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'document_number'], 'purchase_orders_doc_unique');
            $table->index(['user_id', 'status']);
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('purchase_order_id');
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->cascadeOnDelete();

            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('stock_code', 100);
            $table->integer('quantity');
            $table->decimal('unit_price', 14, 2);
            $table->decimal('vat_rate', 5, 2)->default(20.00);
            $table->decimal('total_amount', 14, 2);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('sales_order_items');
        Schema::dropIfExists('sales_orders');
    }
};
