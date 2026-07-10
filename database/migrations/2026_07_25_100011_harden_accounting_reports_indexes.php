<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. receivables tablosu index'leri
        if (Schema::hasTable('receivables')) {
            $indexes = collect(Schema::getIndexes('receivables'))->pluck('name')->all();
            if (!in_array('rec_user_status_due_idx', $indexes, true)) {
                Schema::table('receivables', function (Blueprint $table) {
                    $table->index(['user_id', 'status', 'due_date'], 'rec_user_status_due_idx');
                });
            }
        }

        // 2. payables tablosu index'leri
        if (Schema::hasTable('payables')) {
            $indexes = collect(Schema::getIndexes('payables'))->pluck('name')->all();
            if (!in_array('pay_user_status_due_idx', $indexes, true)) {
                Schema::table('payables', function (Blueprint $table) {
                    $table->index(['user_id', 'status', 'due_date'], 'pay_user_status_due_idx');
                });
            }
        }

        // 3. journal_entries tablosu index'leri
        if (Schema::hasTable('journal_entries')) {
            $indexes = collect(Schema::getIndexes('journal_entries'))->pluck('name')->all();
            if (!in_array('je_user_status_date_idx', $indexes, true)) {
                Schema::table('journal_entries', function (Blueprint $table) {
                    $table->index(['user_id', 'status', 'entry_date'], 'je_user_status_date_idx');
                });
            }
        }

        // 4. journal_lines tablosu index'leri
        if (Schema::hasTable('journal_lines')) {
            $indexes = collect(Schema::getIndexes('journal_lines'))->pluck('name')->all();
            if (!in_array('jl_user_account_idx', $indexes, true)) {
                Schema::table('journal_lines', function (Blueprint $table) {
                    $table->index(['user_id', 'account_id'], 'jl_user_account_idx');
                });
            }
        }

        // 5. stock_balances tablosu index'leri
        if (Schema::hasTable('stock_balances')) {
            $indexes = collect(Schema::getIndexes('stock_balances'))->pluck('name')->all();
            if (!in_array('sb_user_wh_code_idx', $indexes, true)) {
                Schema::table('stock_balances', function (Blueprint $table) {
                    $table->index(['user_id', 'warehouse_id', 'stock_code'], 'sb_user_wh_code_idx');
                });
            }
        }

        // 6. party_ledger_entries tablosu index'leri
        if (Schema::hasTable('party_ledger_entries')) {
            $indexes = collect(Schema::getIndexes('party_ledger_entries'))->pluck('name')->all();
            if (!in_array('ple_user_status_party_idx', $indexes, true)) {
                Schema::table('party_ledger_entries', function (Blueprint $table) {
                    $table->index(['user_id', 'status', 'party_id'], 'ple_user_status_party_idx');
                });
            }
        }
    }

    public function down(): void
    {
        // 1. receivables
        if (Schema::hasTable('receivables')) {
            $indexes = collect(Schema::getIndexes('receivables'))->pluck('name')->all();
            if (in_array('rec_user_status_due_idx', $indexes, true)) {
                Schema::table('receivables', function (Blueprint $table) {
                    $table->dropIndex('rec_user_status_due_idx');
                });
            }
        }

        // 2. payables
        if (Schema::hasTable('payables')) {
            $indexes = collect(Schema::getIndexes('payables'))->pluck('name')->all();
            if (in_array('pay_user_status_due_idx', $indexes, true)) {
                Schema::table('payables', function (Blueprint $table) {
                    $table->dropIndex('pay_user_status_due_idx');
                });
            }
        }

        // 3. journal_entries
        if (Schema::hasTable('journal_entries')) {
            $indexes = collect(Schema::getIndexes('journal_entries'))->pluck('name')->all();
            if (in_array('je_user_status_date_idx', $indexes, true)) {
                Schema::table('journal_entries', function (Blueprint $table) {
                    $table->dropIndex('je_user_status_date_idx');
                });
            }
        }

        // 4. journal_lines
        if (Schema::hasTable('journal_lines')) {
            $indexes = collect(Schema::getIndexes('journal_lines'))->pluck('name')->all();
            if (in_array('jl_user_account_idx', $indexes, true)) {
                Schema::table('journal_lines', function (Blueprint $table) {
                    $table->dropIndex('jl_user_account_idx');
                });
            }
        }

        // 5. stock_balances
        if (Schema::hasTable('stock_balances')) {
            $indexes = collect(Schema::getIndexes('stock_balances'))->pluck('name')->all();
            if (in_array('sb_user_wh_code_idx', $indexes, true)) {
                Schema::table('stock_balances', function (Blueprint $table) {
                    $table->dropIndex('sb_user_wh_code_idx');
                });
            }
        }

        // 6. party_ledger_entries
        if (Schema::hasTable('party_ledger_entries')) {
            $indexes = collect(Schema::getIndexes('party_ledger_entries'))->pluck('name')->all();
            if (in_array('ple_user_status_party_idx', $indexes, true)) {
                Schema::table('party_ledger_entries', function (Blueprint $table) {
                    $table->dropIndex('ple_user_status_party_idx');
                });
            }
        }
    }
};
