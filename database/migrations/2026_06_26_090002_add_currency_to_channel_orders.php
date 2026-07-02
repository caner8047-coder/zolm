<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channel_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('channel_orders', 'currency')) {
                $table->string('currency', 3)->default('TRY')->after('commercial_type');
            }
            if (!Schema::hasColumn('channel_orders', 'exchange_rate')) {
                $table->decimal('exchange_rate', 10, 6)->default(1.0)->after('currency');
            }
        });
    }

    public function down(): void
    {
        Schema::table('channel_orders', function (Blueprint $table) {
            $table->dropColumn(['currency', 'exchange_rate']);
        });
    }
};
