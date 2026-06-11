<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channel_listings', function (Blueprint $table) {
            if (!Schema::hasColumn('channel_listings', 'shipping_days')) {
                $table->unsignedSmallInteger('shipping_days')->nullable()->after('last_stock_alerted_at');
            }

            if (!Schema::hasColumn('channel_listings', 'shipping_type')) {
                $table->string('shipping_type', 80)->nullable()->after('shipping_days');
            }

            if (!Schema::hasColumn('channel_listings', 'fast_delivery_type')) {
                $table->string('fast_delivery_type', 80)->nullable()->after('shipping_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('channel_listings', function (Blueprint $table) {
            foreach (['fast_delivery_type', 'shipping_type', 'shipping_days'] as $column) {
                if (Schema::hasColumn('channel_listings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
