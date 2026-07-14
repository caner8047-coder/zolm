<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_knowledge_suggestions', function (Blueprint $table) {
            $table->string('cluster_key', 64)->nullable()->after('hash_key');
            $table->json('source_conversation_ids')->nullable()->after('cluster_key');
            $table->json('source_message_ids')->nullable()->after('source_conversation_ids');
            $table->string('scope', 30)->default('store')->after('source_message_ids');
            $table->unsignedInteger('version')->default(1)->after('scope');
            $table->timestamp('effective_until')->nullable()->after('version');
            $table->foreignId('owner_user_id')->nullable()->after('effective_until')->constrained('users')->nullOnDelete();
            $table->index(['store_id', 'cluster_key', 'status'], 'sks_store_cluster_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('support_knowledge_suggestions', function (Blueprint $table) {
            $table->dropIndex('sks_store_cluster_status_idx');
            $table->dropConstrainedForeignId('owner_user_id');
            $table->dropColumn(['cluster_key', 'source_conversation_ids', 'source_message_ids', 'scope', 'version', 'effective_until']);
        });
    }
};
