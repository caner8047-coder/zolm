<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('app_notifications') || Schema::hasColumn('app_notifications', 'email_digest_sent_at')) {
            return;
        }

        Schema::table('app_notifications', function (Blueprint $table): void {
            $table->timestamp('email_digest_sent_at')->nullable()->after('triggered_at');
            $table->index(['type', 'email_digest_sent_at', 'created_at'], 'app_notifications_digest_pending_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('app_notifications') || ! Schema::hasColumn('app_notifications', 'email_digest_sent_at')) {
            return;
        }

        Schema::table('app_notifications', function (Blueprint $table): void {
            $table->dropIndex('app_notifications_digest_pending_idx');
            $table->dropColumn('email_digest_sent_at');
        });
    }
};
