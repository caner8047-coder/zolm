<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_production_readiness_runs', function (Blueprint $table) {
            $table->json('check_results_json')->nullable()->after('status');
            $table->json('failed_checks_json')->nullable()->after('check_results_json');
        });
    }

    public function down(): void
    {
        Schema::table('support_production_readiness_runs', function (Blueprint $table) {
            $table->dropColumn(['check_results_json', 'failed_checks_json']);
        });
    }
};
