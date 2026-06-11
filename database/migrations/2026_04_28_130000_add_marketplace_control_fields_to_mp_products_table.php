<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mp_products', function (Blueprint $table) {
            if (!Schema::hasColumn('mp_products', 'cost_vat_rate')) {
                $table->decimal('cost_vat_rate', 5, 2)->nullable()->after('vat_rate');
            }

            if (!Schema::hasColumn('mp_products', 'extra_cost_fixed')) {
                $table->decimal('extra_cost_fixed', 10, 2)->default(0)->after('cargo_cost');
            }

            if (!Schema::hasColumn('mp_products', 'extra_cost_percentage')) {
                $table->decimal('extra_cost_percentage', 5, 2)->default(0)->after('extra_cost_fixed');
            }

            if (!Schema::hasColumn('mp_products', 'return_rate')) {
                $table->decimal('return_rate', 5, 2)->nullable()->after('stock_quantity');
            }

            if (!Schema::hasColumn('mp_products', 'return_rate_source')) {
                $table->string('return_rate_source', 40)->nullable()->after('return_rate');
            }

            if (!Schema::hasColumn('mp_products', 'return_rate_calculated_at')) {
                $table->timestamp('return_rate_calculated_at')->nullable()->after('return_rate_source');
            }

            if (!Schema::hasColumn('mp_products', 'fast_delivery_type')) {
                $table->string('fast_delivery_type', 80)->nullable()->after('shipping_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mp_products', function (Blueprint $table) {
            foreach ([
                'cost_vat_rate',
                'extra_cost_fixed',
                'extra_cost_percentage',
                'return_rate',
                'return_rate_source',
                'return_rate_calculated_at',
                'fast_delivery_type',
            ] as $column) {
                if (Schema::hasColumn('mp_products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
