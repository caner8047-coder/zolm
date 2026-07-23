<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mp_products', function (Blueprint $table) {
            if (! Schema::hasColumn('mp_products', 'return_order_quantity')) {
                $table->unsignedInteger('return_order_quantity')->nullable()->after('return_rate');
            }

            if (! Schema::hasColumn('mp_products', 'return_claim_quantity')) {
                $table->unsignedInteger('return_claim_quantity')->nullable()->after('return_order_quantity');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mp_products', function (Blueprint $table) {
            $columns = array_filter(['return_order_quantity', 'return_claim_quantity'], fn ($column) => Schema::hasColumn('mp_products', $column));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
