<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_onboarding_states', function (Blueprint $table): void {
            $table->timestamp('catalog_verified_at')->nullable()->after('last_verified_at');
            $table->json('catalog_dry_run_json')->nullable()->after('diagnostics_json');
            $table->json('support_bundle_json')->nullable()->after('catalog_dry_run_json');
            $table->timestamp('support_requested_at')->nullable()->after('support_bundle_json');
        });
    }

    public function down(): void
    {
        Schema::table('support_onboarding_states', fn (Blueprint $table) => $table->dropColumn([
            'catalog_verified_at', 'catalog_dry_run_json', 'support_bundle_json', 'support_requested_at',
        ]));
    }
};
