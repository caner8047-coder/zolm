<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mp_price_canary_approvals', function (Blueprint $table) {
            $table->string('readiness_version', 40)->nullable()->after('approval_reason');
            $table->string('readiness_hash', 64)->nullable()->after('readiness_version');
            $table->string('policy_version', 40)->nullable()->after('readiness_hash');
            $table->string('rule_version', 40)->nullable()->after('policy_version');
            
            $table->timestamp('shadow_data_cutoff')->nullable()->after('rule_version');
            $table->timestamp('api_metrics_cutoff')->nullable()->after('shadow_data_cutoff');
            $table->timestamp('queue_metrics_cutoff')->nullable()->after('api_metrics_cutoff');
            
            $table->json('approved_product_snapshot')->nullable()->after('queue_metrics_cutoff');
            $table->json('approved_price_policy_snapshot')->nullable()->after('approved_product_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('mp_price_canary_approvals', function (Blueprint $table) {
            $table->dropColumn([
                'readiness_version',
                'readiness_hash',
                'policy_version',
                'rule_version',
                'shadow_data_cutoff',
                'api_metrics_cutoff',
                'queue_metrics_cutoff',
                'approved_product_snapshot',
                'approved_price_policy_snapshot'
            ]);
        });
    }
};
