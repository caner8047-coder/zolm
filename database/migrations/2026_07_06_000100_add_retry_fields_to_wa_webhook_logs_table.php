<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wa_webhook_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('wa_webhook_logs', 'request_id')) {
                $table->string('request_id', 120)->nullable()->after('status');
            }

            if (! Schema::hasColumn('wa_webhook_logs', 'retry_count')) {
                $table->unsignedInteger('retry_count')->default(0)->after('processing_time_ms');
            }

            if (! Schema::hasColumn('wa_webhook_logs', 'next_retry_at')) {
                $table->timestamp('next_retry_at')->nullable()->after('retry_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('wa_webhook_logs', function (Blueprint $table) {
            if (Schema::hasColumn('wa_webhook_logs', 'next_retry_at')) {
                $table->dropColumn('next_retry_at');
            }

            if (Schema::hasColumn('wa_webhook_logs', 'retry_count')) {
                $table->dropColumn('retry_count');
            }

            if (Schema::hasColumn('wa_webhook_logs', 'request_id')) {
                $table->dropColumn('request_id');
            }
        });
    }
};
