<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('support_team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_team_id')->constrained('support_teams')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('support_routing_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->foreignId('support_team_id')->nullable()->constrained('support_teams')->nullOnDelete();
            $table->string('trigger_type', 40); // 'channel', 'rating', 'intent', 'cart_value'
            $table->string('trigger_value', 100)->nullable();
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        if (!Schema::hasColumn('support_conversations', 'support_team_id')) {
            Schema::table('support_conversations', function (Blueprint $table) {
                $table->foreignId('support_team_id')->nullable()->constrained('support_teams')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('support_conversations', 'support_team_id')) {
            Schema::table('support_conversations', function (Blueprint $table) {
                $table->dropForeign(['support_team_id']);
                $table->dropColumn('support_team_id');
            });
        }

        Schema::dropIfExists('support_routing_rules');
        Schema::dropIfExists('support_team_members');
        Schema::dropIfExists('support_teams');
    }
};
