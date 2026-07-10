<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('sales_orders', 'discount_amount')) {
            Schema::table('sales_orders', function (Blueprint $table) {
                $table->decimal('discount_amount', 14, 2)->default(0.00)->after('total_amount');
            });
        }

        Schema::table('sales_order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('sales_order_items', 'discount_rate')) {
                $table->decimal('discount_rate', 5, 2)->default(0.00)->after('vat_rate');
            }
            if (!Schema::hasColumn('sales_order_items', 'discount_amount')) {
                $table->decimal('discount_amount', 14, 2)->default(0.00)->after('discount_rate');
            }
        });

        if (!Schema::hasColumn('purchase_orders', 'discount_amount')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->decimal('discount_amount', 14, 2)->default(0.00)->after('total_amount');
            });
        }

        Schema::table('purchase_order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_order_items', 'discount_rate')) {
                $table->decimal('discount_rate', 5, 2)->default(0.00)->after('vat_rate');
            }
            if (!Schema::hasColumn('purchase_order_items', 'discount_amount')) {
                $table->decimal('discount_amount', 14, 2)->default(0.00)->after('discount_rate');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_order_items', 'discount_rate')) {
                $table->dropColumn('discount_rate');
            }
            if (Schema::hasColumn('purchase_order_items', 'discount_amount')) {
                $table->dropColumn('discount_amount');
            }
        });

        if (Schema::hasColumn('purchase_orders', 'discount_amount')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->dropColumn('discount_amount');
            });
        }

        Schema::table('sales_order_items', function (Blueprint $table) {
            if (Schema::hasColumn('sales_order_items', 'discount_rate')) {
                $table->dropColumn('discount_rate');
            }
            if (Schema::hasColumn('sales_order_items', 'discount_amount')) {
                $table->dropColumn('discount_amount');
            }
        });

        if (Schema::hasColumn('sales_orders', 'discount_amount')) {
            Schema::table('sales_orders', function (Blueprint $table) {
                $table->dropColumn('discount_amount');
            });
        }
    }
};
