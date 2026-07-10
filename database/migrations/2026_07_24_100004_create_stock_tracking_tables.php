<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            $table->string('name', 120);
            $table->string('code', 50);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('address', 255)->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'code'], 'warehouses_user_code_unique');
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            $table->unsignedBigInteger('warehouse_id');
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->cascadeOnDelete();

            // Ürün referansı
            $table->unsignedBigInteger('product_id')->nullable();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();

            $table->string('stock_code', 100);
            $table->string('movement_type', 40); // in_purchase, in_return, in_adjustment, out_sale, out_loss, out_adjustment
            $table->string('direction', 10); // in, out
            $table->integer('quantity'); // pozitif tamsayı

            $table->decimal('unit_cost', 14, 2)->nullable();
            $table->string('source_type', 60)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('description', 500)->nullable();
            $table->date('movement_date');

            $table->timestamps();

            $table->index(['user_id', 'stock_code']);
            $table->index(['user_id', 'warehouse_id', 'stock_code']);
            $table->index(['user_id', 'movement_date']);
        });

        Schema::create('stock_balances', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            $table->unsignedBigInteger('warehouse_id');
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->cascadeOnDelete();

            $table->unsignedBigInteger('product_id')->nullable();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();

            $table->string('stock_code', 100);
            $table->integer('quantity')->default(0);

            $table->timestamps();

            $table->unique(['user_id', 'warehouse_id', 'stock_code'], 'stock_balances_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_balances');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('warehouses');
    }
};
