<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mp_products', function (Blueprint $table) {
            if (!Schema::hasColumn('mp_products', 'critical_stock_threshold')) {
                $table->unsignedInteger('critical_stock_threshold')->nullable()->after('stock_quantity');
            }

            if (!Schema::hasColumn('mp_products', 'last_stock_alert_level')) {
                $table->string('last_stock_alert_level', 30)->nullable()->after('critical_stock_threshold');
            }

            if (!Schema::hasColumn('mp_products', 'last_stock_alert_quantity')) {
                $table->integer('last_stock_alert_quantity')->nullable()->after('last_stock_alert_level');
            }

            if (!Schema::hasColumn('mp_products', 'last_stock_alerted_at')) {
                $table->timestamp('last_stock_alerted_at')->nullable()->after('last_stock_alert_quantity');
            }
        });

        Schema::table('channel_listings', function (Blueprint $table) {
            if (!Schema::hasColumn('channel_listings', 'last_stock_alert_level')) {
                $table->string('last_stock_alert_level', 30)->nullable()->after('stock_quantity');
            }

            if (!Schema::hasColumn('channel_listings', 'last_stock_alert_quantity')) {
                $table->integer('last_stock_alert_quantity')->nullable()->after('last_stock_alert_level');
            }

            if (!Schema::hasColumn('channel_listings', 'last_stock_alerted_at')) {
                $table->timestamp('last_stock_alerted_at')->nullable()->after('last_stock_alert_quantity');
            }
        });
    }

    public function down(): void
    {
        Schema::table('channel_listings', function (Blueprint $table) {
            foreach (['last_stock_alerted_at', 'last_stock_alert_quantity', 'last_stock_alert_level'] as $column) {
                if (Schema::hasColumn('channel_listings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('mp_products', function (Blueprint $table) {
            foreach (['last_stock_alerted_at', 'last_stock_alert_quantity', 'last_stock_alert_level', 'critical_stock_threshold'] as $column) {
                if (Schema::hasColumn('mp_products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
