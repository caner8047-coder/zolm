<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_channels', function (Blueprint $table): void {
            $table->string('public_key', 64)->nullable()->after('key')->unique();
        });

        DB::table('support_channels')->where('key', 'web_chat')->whereNull('public_key')
            ->orderBy('id')->eachById(function ($channel): void {
                DB::table('support_channels')->where('id', $channel->id)->update(['public_key' => Str::random(48)]);
            });

        Schema::create('support_widget_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('support_channel_id')->constrained('support_channels')->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('support_conversations')->nullOnDelete();
            $table->char('session_hash', 64)->unique();
            $table->char('token_hash', 64)->unique();
            $table->string('origin', 255);
            $table->boolean('consent_granted')->default(false);
            $table->string('privacy_notice_version', 40);
            $table->timestamp('consented_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('expires_at');
            $table->string('status', 20)->default('active');
            $table->json('metadata_json')->nullable();
            $table->timestamps();
            $table->index(['support_channel_id', 'status', 'expires_at'], 'widget_session_channel_status_idx');
        });

        Schema::create('support_web_leads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->foreignId('support_widget_session_id')->constrained('support_widget_sessions')->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('support_conversations')->nullOnDelete();
            $table->foreignId('crm_contact_id')->nullable()->constrained('crm_contacts')->nullOnDelete();
            $table->text('name_encrypted')->nullable();
            $table->text('email_encrypted')->nullable();
            $table->text('phone_encrypted')->nullable();
            $table->text('purpose_encrypted');
            $table->string('consent_basis', 40)->default('explicit_widget');
            $table->string('privacy_notice_version', 40);
            $table->timestamp('consented_at');
            $table->string('status', 30)->default('new');
            $table->timestamps();
            $table->unique('support_widget_session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_web_leads');
        Schema::dropIfExists('support_widget_sessions');
        Schema::table('support_channels', function (Blueprint $table): void {
            $table->dropUnique(['public_key']);
            $table->dropColumn('public_key');
        });
    }
};
