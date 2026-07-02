<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_profit_snapshots', function (Blueprint $table) {
            // Kesinti detay kolonları — daha önce hepsi service_fee_total içinde birleşiyordu.
            // Bu kolonlar kategori bazlı kâr analizi derinliği sağlar.
            $table->decimal('advertising_total', 12, 2)->default(0)->after('service_fee_total');
            $table->decimal('penalty_total', 12, 2)->default(0)->after('advertising_total');
            $table->decimal('early_payment_total', 12, 2)->default(0)->after('penalty_total');
            $table->decimal('discount_total', 12, 2)->default(0)->after('early_payment_total');
            $table->decimal('other_cost_total', 12, 2)->default(0)->after('discount_total');

            // Multi-currency desteği (İP-4 ile kullanılacak ama kolon yapısı şimdi eklenir)
            $table->string('currency', 3)->default('TRY')->after('version');
            $table->decimal('exchange_rate', 10, 6)->default(1.000000)->after('currency');
            $table->decimal('profit_try', 12, 2)->nullable()->after('exchange_rate');
        });
    }

    public function down(): void
    {
        Schema::table('order_profit_snapshots', function (Blueprint $table) {
            $table->dropColumn([
                'advertising_total',
                'penalty_total',
                'early_payment_total',
                'discount_total',
                'other_cost_total',
                'currency',
                'exchange_rate',
                'profit_try',
            ]);
        });
    }
};
