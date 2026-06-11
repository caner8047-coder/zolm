<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('integration_sync_profiles')) {
            return;
        }

        Schema::table('integration_sync_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('integration_sync_profiles', 'claims_poll_minutes')) {
                $table->unsignedInteger('claims_poll_minutes')->default(30)->after('products_poll_minutes');
            }

            if (! Schema::hasColumn('integration_sync_profiles', 'claims_enabled')) {
                $table->boolean('claims_enabled')->default(true)->after('products_enabled');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('integration_sync_profiles')) {
            return;
        }

        Schema::table('integration_sync_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('integration_sync_profiles', 'claims_poll_minutes')) {
                $table->dropColumn('claims_poll_minutes');
            }

            if (Schema::hasColumn('integration_sync_profiles', 'claims_enabled')) {
                $table->dropColumn('claims_enabled');
            }
        });
    }
};
