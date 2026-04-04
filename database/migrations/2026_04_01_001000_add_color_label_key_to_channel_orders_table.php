<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channel_orders', function (Blueprint $table) {
            $table->string('color_label_key', 40)
                ->nullable()
                ->after('order_status')
                ->index('channel_orders_color_label_idx');
        });
    }

    public function down(): void
    {
        Schema::table('channel_orders', function (Blueprint $table) {
            $table->dropIndex('channel_orders_color_label_idx');
            $table->dropColumn('color_label_key');
        });
    }
};
