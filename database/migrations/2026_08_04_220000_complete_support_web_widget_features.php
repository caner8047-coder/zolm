<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_widget_sessions', function (Blueprint $table): void {
            $table->boolean('marketing_consent_granted')->default(false)->after('consent_granted');
            $table->string('marketing_notice_version', 40)->nullable()->after('privacy_notice_version');
            $table->timestamp('marketing_consented_at')->nullable()->after('consented_at');
        });
        Schema::table('support_web_leads', function (Blueprint $table): void {
            $table->char('idempotency_key_hash', 64)->nullable()->after('support_widget_session_id');
            $table->string('lead_source', 60)->default('web_chat')->after('purpose_encrypted');
            $table->string('campaign', 120)->nullable()->after('lead_source');
            $table->text('conversation_summary_encrypted')->nullable()->after('campaign');
            $table->boolean('marketing_consent_granted')->default(false)->after('consent_basis');
            $table->timestamp('marketing_consented_at')->nullable()->after('consented_at');
            $table->unique(['store_id', 'idempotency_key_hash'], 'swl_store_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::table('support_web_leads', function (Blueprint $table): void {
            $table->dropUnique('swl_store_idempotency_unique');
            $table->dropColumn(['idempotency_key_hash', 'lead_source', 'campaign', 'conversation_summary_encrypted', 'marketing_consent_granted', 'marketing_consented_at']);
        });
        Schema::table('support_widget_sessions', fn (Blueprint $table) => $table->dropColumn(['marketing_consent_granted', 'marketing_notice_version', 'marketing_consented_at']));
    }
};
