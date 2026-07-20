<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('legal_entity_id')->nullable()->after('user_id');
            $table->string('module', 50)->nullable()->after('action');
            $table->boolean('contains_sensitive_data')->default(false)->after('user_agent');

            $table->foreign('legal_entity_id')->references('id')->on('legal_entities')->nullOnDelete();
            $table->index('legal_entity_id');
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropForeign(['legal_entity_id']);
            $table->dropColumn(['legal_entity_id', 'module', 'contains_sensitive_data']);
        });
    }
};
