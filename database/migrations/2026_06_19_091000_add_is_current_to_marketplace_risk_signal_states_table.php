<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('marketplace_risk_signal_states')
            || Schema::hasColumn('marketplace_risk_signal_states', 'is_current')) {
            return;
        }

        Schema::table('marketplace_risk_signal_states', function (Blueprint $table) {
            $table->boolean('is_current')->default(true)->after('status');
            $table->index(
                ['user_id', 'is_current', 'last_seen_at'],
                'marketplace_risk_user_current_seen_idx'
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('marketplace_risk_signal_states')
            || ! Schema::hasColumn('marketplace_risk_signal_states', 'is_current')) {
            return;
        }

        Schema::table('marketplace_risk_signal_states', function (Blueprint $table) {
            $table->dropIndex('marketplace_risk_user_current_seen_idx');
            $table->dropColumn('is_current');
        });
    }
};
