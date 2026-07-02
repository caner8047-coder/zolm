<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('channel_orders', 'currency')) {
            Schema::table('channel_orders', function (Blueprint $table) {
                $table->string('currency', 3)->default('TRY')->after('commercial_type');
            });
        }

        if (! Schema::hasColumn('channel_orders', 'exchange_rate')) {
            Schema::table('channel_orders', function (Blueprint $table) {
                $table->decimal('exchange_rate', 10, 4)->default(1.0)->after('currency');
            });
        }

        if (! Schema::hasColumn('order_profit_snapshots', 'currency')) {
            Schema::table('order_profit_snapshots', function (Blueprint $table) {
                $table->string('currency', 3)->default('TRY')->after('profit_state');
            });
        }

        if (! Schema::hasColumn('order_profit_snapshots', 'exchange_rate')) {
            Schema::table('order_profit_snapshots', function (Blueprint $table) {
                $table->decimal('exchange_rate', 10, 4)->default(1.0)->after('currency');
            });
        }
    }

    public function down(): void
    {
        $channelOrderColumns = array_values(array_filter([
            Schema::hasColumn('channel_orders', 'currency') ? 'currency' : null,
            Schema::hasColumn('channel_orders', 'exchange_rate') ? 'exchange_rate' : null,
        ]));

        if ($channelOrderColumns !== []) {
            Schema::table('channel_orders', function (Blueprint $table) use ($channelOrderColumns) {
                $table->dropColumn($channelOrderColumns);
            });
        }

        $snapshotColumns = array_values(array_filter([
            Schema::hasColumn('order_profit_snapshots', 'currency') ? 'currency' : null,
            Schema::hasColumn('order_profit_snapshots', 'exchange_rate') ? 'exchange_rate' : null,
        ]));

        if ($snapshotColumns !== []) {
            Schema::table('order_profit_snapshots', function (Blueprint $table) use ($snapshotColumns) {
                $table->dropColumn($snapshotColumns);
            });
        }
    }
};
