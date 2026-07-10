<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_terminals', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            $table->string('name', 100);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });

        Schema::create('pos_shifts', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            $table->unsignedBigInteger('pos_terminal_id');
            $table->foreign('pos_terminal_id')->references('id')->on('pos_terminals')->cascadeOnDelete();

            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();

            $table->decimal('opening_balance', 14, 2)->default(0.00);
            $table->decimal('closing_balance', 14, 2)->nullable();
            $table->string('status', 30)->default('open'); // open, closed

            $table->timestamps();
        });

        Schema::create('pos_sales', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            $table->unsignedBigInteger('pos_shift_id');
            $table->foreign('pos_shift_id')->references('id')->on('pos_shifts')->cascadeOnDelete();

            $table->unsignedBigInteger('sales_order_id');
            $table->foreign('sales_order_id')->references('id')->on('sales_orders')->cascadeOnDelete();

            $table->string('payment_method', 50)->default('cash'); // cash, credit_card
            $table->decimal('amount', 14, 2);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_sales');
        Schema::dropIfExists('pos_shifts');
        Schema::dropIfExists('pos_terminals');
    }
};
