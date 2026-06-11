<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('app_notifications')) {
            return;
        }

        Schema::table('app_notifications', function (Blueprint $table) {
            $table->index(['read_at', 'created_at'], 'app_notifications_pruning_idx');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('app_notifications')) {
            return;
        }

        Schema::table('app_notifications', function (Blueprint $table) {
            $table->dropIndex('app_notifications_pruning_idx');
        });
    }
};
