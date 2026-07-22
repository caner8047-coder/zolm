<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trendyol_booster_action_states', function (Blueprint $table): void {
            $table->foreignId('assigned_user_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by_user_id')->nullable()->after('assigned_user_id')->constrained('users')->nullOnDelete();
            $table->index(['assigned_user_id', 'status'], 'booster_action_assignee_status_idx');
        });

        Schema::create('trendyol_booster_action_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('fingerprint', 191);
            $table->string('event', 40);
            $table->string('from_value', 191)->nullable();
            $table->string('to_value', 191)->nullable();
            $table->json('context_json')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['owner_user_id', 'occurred_at'], 'booster_action_audit_owner_date_idx');
            $table->index(['fingerprint', 'occurred_at'], 'booster_action_audit_fingerprint_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trendyol_booster_action_audits');
        Schema::table('trendyol_booster_action_states', function (Blueprint $table): void {
            $table->dropIndex('booster_action_assignee_status_idx');
            $table->dropConstrainedForeignId('assigned_by_user_id');
            $table->dropConstrainedForeignId('assigned_user_id');
        });
    }
};
