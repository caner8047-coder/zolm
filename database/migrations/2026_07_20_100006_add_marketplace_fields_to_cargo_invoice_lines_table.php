<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cargo_invoice_lines', function (Blueprint $table) {
            $table->foreignId('store_id')->nullable()->constrained('marketplace_stores')->nullOnDelete();
            $table->string('invoice_serial_number')->nullable()->index();
            $table->string('order_number')->nullable()->index();
            $table->string('parcel_unique_id')->nullable()->index();
            $table->string('cargo_type')->nullable(); // OUTBOUND, RETURN

            $table->unique(['store_id', 'invoice_serial_number', 'parcel_unique_id'], 'cil_store_inv_serial_pkg_unique');
        });
    }

    public function down(): void
    {
        Schema::table('cargo_invoice_lines', function (Blueprint $table) {
            $table->dropForeign(['store_id']);
            $table->dropUnique('cil_store_inv_serial_pkg_unique');
            
            $table->dropColumn(['store_id', 'invoice_serial_number', 'order_number', 'parcel_unique_id', 'cargo_type']);
        });
    }
};
