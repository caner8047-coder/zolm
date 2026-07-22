<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integration_sync_profiles', function (Blueprint $table) {
            $table->unsignedBigInteger('catalog_source_user_id')->nullable()->after('store_id');
        });
    }

    public function down(): void
    {
        Schema::table('integration_sync_profiles', function (Blueprint $table) {
            $table->dropColumn('catalog_source_user_id');
        });
    }
};
