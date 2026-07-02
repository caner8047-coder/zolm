<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mp_profit_action_items')) {
            return;
        }

        Schema::table('mp_profit_action_items', function (Blueprint $table) {
            if (! Schema::hasColumn('mp_profit_action_items', 'priority')) {
                $table->string('priority', 20)->default('medium')->after('status');
            }

            if (! Schema::hasColumn('mp_profit_action_items', 'due_date')) {
                $table->date('due_date')->nullable()->after('priority');
            }

            if (! Schema::hasColumn('mp_profit_action_items', 'owner_label')) {
                $table->string('owner_label', 120)->nullable()->after('due_date');
            }
        });

        Schema::table('mp_profit_action_items', function (Blueprint $table) {
            $table->index(['user_id', 'scope_hash', 'priority', 'due_date'], 'mp_profit_actions_workflow_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('mp_profit_action_items')) {
            return;
        }

        Schema::table('mp_profit_action_items', function (Blueprint $table) {
            $table->dropIndex('mp_profit_actions_workflow_idx');
        });

        Schema::table('mp_profit_action_items', function (Blueprint $table) {
            if (Schema::hasColumn('mp_profit_action_items', 'owner_label')) {
                $table->dropColumn('owner_label');
            }

            if (Schema::hasColumn('mp_profit_action_items', 'due_date')) {
                $table->dropColumn('due_date');
            }

            if (Schema::hasColumn('mp_profit_action_items', 'priority')) {
                $table->dropColumn('priority');
            }
        });
    }
};
