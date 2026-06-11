<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('integration_sync_profiles')) {
            return;
        }

        $afterPollColumn = Schema::hasColumn('integration_sync_profiles', 'claims_poll_minutes')
            ? 'claims_poll_minutes'
            : 'products_poll_minutes';
        $afterEnabledColumn = Schema::hasColumn('integration_sync_profiles', 'claims_enabled')
            ? 'claims_enabled'
            : 'products_enabled';

        Schema::table('integration_sync_profiles', function (Blueprint $table) use ($afterPollColumn, $afterEnabledColumn) {
            if (! Schema::hasColumn('integration_sync_profiles', 'questions_poll_minutes')) {
                $table->unsignedInteger('questions_poll_minutes')->default(15)->after($afterPollColumn);
            }

            if (! Schema::hasColumn('integration_sync_profiles', 'questions_enabled')) {
                $table->boolean('questions_enabled')->default(true)->after($afterEnabledColumn);
            }
        });

        $questionStoreIds = DB::table('marketplace_stores')
            ->whereIn('marketplace', ['trendyol', 'hepsiburada', 'n11', 'koctas', 'pazarama', 'ciceksepeti', 'woocommerce'])
            ->pluck('id');

        if ($questionStoreIds->isNotEmpty()) {
            DB::table('integration_sync_profiles')
                ->whereIn('store_id', $questionStoreIds)
                ->where('questions_enabled', false)
                ->update(['questions_enabled' => true]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('integration_sync_profiles')) {
            return;
        }

        Schema::table('integration_sync_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('integration_sync_profiles', 'questions_poll_minutes')) {
                $table->dropColumn('questions_poll_minutes');
            }

            if (Schema::hasColumn('integration_sync_profiles', 'questions_enabled')) {
                $table->dropColumn('questions_enabled');
            }
        });
    }
};
