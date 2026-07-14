<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_onboarding_states', function (Blueprint $table) {
            $table->timestamp('connection_started_at')->nullable()->after('recommended_mode');
            $table->timestamp('first_verified_draft_at')->nullable()->after('connection_started_at');
            $table->unsignedInteger('verification_duration_seconds')->nullable()->after('first_verified_draft_at');
            $table->timestamp('last_verified_at')->nullable()->after('verification_duration_seconds');
            $table->json('diagnostics_json')->nullable()->after('last_verified_at');
            $table->text('sample_question')->nullable()->after('diagnostics_json');
            $table->json('sample_result_json')->nullable()->after('sample_question');
        });
    }

    public function down(): void
    {
        Schema::table('support_onboarding_states', function (Blueprint $table) {
            $table->dropColumn([
                'connection_started_at',
                'first_verified_draft_at',
                'verification_duration_seconds',
                'last_verified_at',
                'diagnostics_json',
                'sample_question',
                'sample_result_json',
            ]);
        });
    }
};
