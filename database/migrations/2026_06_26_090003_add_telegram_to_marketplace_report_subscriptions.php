<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_report_subscriptions', function (Blueprint $table) {
            $table->string('telegram_bot_token')->nullable()->after('webhook_url');
            $table->string('telegram_chat_id')->nullable()->after('telegram_bot_token');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_report_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['telegram_bot_token', 'telegram_chat_id']);
        });
    }
};
