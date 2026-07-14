<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_agent_actions', function (Blueprint $table) {
            $table->foreignId('conversation_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('support_agent_actions', function (Blueprint $table) {
            $table->foreignId('conversation_id')->nullable(false)->change();
        });
    }
};
