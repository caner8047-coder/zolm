<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('money_transfers', function (Blueprint $table) {
            if (!Schema::hasColumn('money_transfers', 'legal_entity_id')) {
                $table->unsignedBigInteger('legal_entity_id')->nullable()->after('journal_entry_id');
                $table->foreign('legal_entity_id')->references('id')->on('legal_entities')->nullOnDelete();
            }

            if (!Schema::hasColumn('money_transfers', 'source_key')) {
                $table->string('source_key', 191)->nullable()->after('legal_entity_id');
            }

            if (!Schema::hasColumn('money_transfers', 'reference_number')) {
                $table->string('reference_number')->nullable()->after('source_key');
            }

            if (!Schema::hasColumn('money_transfers', 'status')) {
                $table->string('status')->default('posted')->after('reference_number');
            }

            if (!Schema::hasColumn('money_transfers', 'posted_at')) {
                $table->timestamp('posted_at')->nullable()->after('status');
            }

            if (!Schema::hasColumn('money_transfers', 'voided_at')) {
                $table->timestamp('voided_at')->nullable()->after('posted_at');
            }

            if (!Schema::hasColumn('money_transfers', 'void_reason')) {
                $table->string('void_reason')->nullable()->after('voided_at');
            }

            if (!Schema::hasColumn('money_transfers', 'meta_json')) {
                $table->json('meta_json')->nullable()->after('void_reason');
            }
        });

        // Unique user_id + source_key and other indexes
        Schema::table('money_transfers', function (Blueprint $table) {
            $table->unique(['user_id', 'source_key'], 'money_transfers_user_source_key_unique');
            $table->index(['user_id', 'status', 'transfer_date'], 'money_transfers_user_status_date_idx');
            $table->index(['user_id', 'from_account_id'], 'money_transfers_user_from_idx');
            $table->index(['user_id', 'to_account_id'], 'money_transfers_user_to_idx');
            $table->index(['user_id', 'legal_entity_id', 'status'], 'money_transfers_user_le_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('money_transfers', function (Blueprint $table) {
            $table->dropUnique('money_transfers_user_source_key_unique');
            $table->dropIndex('money_transfers_user_status_date_idx');
            $table->dropIndex('money_transfers_user_from_idx');
            $table->dropIndex('money_transfers_user_to_idx');
            $table->dropIndex('money_transfers_user_le_status_idx');

            if (Schema::hasColumn('money_transfers', 'legal_entity_id')) {
                $table->dropForeign(['legal_entity_id']);
                $table->dropColumn('legal_entity_id');
            }
            $table->dropColumn([
                'source_key',
                'reference_number',
                'status',
                'posted_at',
                'voided_at',
                'void_reason',
                'meta_json'
            ]);
        });
    }
};
