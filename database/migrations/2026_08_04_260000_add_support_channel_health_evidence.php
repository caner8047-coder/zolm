<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_channels', function (Blueprint $table): void {
            $table->string('last_health_status', 30)->nullable()->after('last_health_check_at');
            $table->string('last_health_error', 500)->nullable()->after('last_health_status');
        });
    }

    public function down(): void
    {
        Schema::table('support_channels', function (Blueprint $table): void {
            $table->dropColumn(['last_health_status', 'last_health_error']);
        });
    }
};
