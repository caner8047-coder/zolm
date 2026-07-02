<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('channel_order_packages')) {
            return;
        }

        Schema::table('channel_order_packages', function (Blueprint $table): void {
            if (! Schema::hasColumn('channel_order_packages', 'label_printed_at')) {
                $table->timestamp('label_printed_at')->nullable()->after('delivered_at');
            }

            if (! Schema::hasColumn('channel_order_packages', 'label_print_count')) {
                $table->unsignedInteger('label_print_count')->default(0)->after('label_printed_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('channel_order_packages')) {
            return;
        }

        Schema::table('channel_order_packages', function (Blueprint $table): void {
            if (Schema::hasColumn('channel_order_packages', 'label_print_count')) {
                $table->dropColumn('label_print_count');
            }

            if (Schema::hasColumn('channel_order_packages', 'label_printed_at')) {
                $table->dropColumn('label_printed_at');
            }
        });
    }
};
