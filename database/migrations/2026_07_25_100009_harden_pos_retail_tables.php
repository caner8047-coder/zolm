<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. pos_terminals tablosu güncellemeleri
        Schema::table('pos_terminals', function (Blueprint $table) {
            if (!Schema::hasColumn('pos_terminals', 'warehouse_id')) {
                $table->unsignedBigInteger('warehouse_id')->nullable();
            }
            if (!Schema::hasColumn('pos_terminals', 'account_id')) {
                $table->unsignedBigInteger('account_id')->nullable();
            }
            if (!Schema::hasColumn('pos_terminals', 'legal_entity_id')) {
                $table->unsignedBigInteger('legal_entity_id')->nullable();
            }
            if (!Schema::hasColumn('pos_terminals', 'meta_json')) {
                $table->json('meta_json')->nullable();
            }
        });

        $terminalIndexes = collect(Schema::getIndexes('pos_terminals'))->pluck('name')->all();
        Schema::table('pos_terminals', function (Blueprint $table) use ($terminalIndexes) {
            if (!in_array('pos_terminals_warehouse_id_foreign', $terminalIndexes, true)) {
                $table->foreign('warehouse_id')->references('id')->on('warehouses')->nullOnDelete();
            }
            if (!in_array('pos_terminals_account_id_foreign', $terminalIndexes, true)) {
                $table->foreign('account_id')->references('id')->on('accounts')->nullOnDelete();
            }
            if (!in_array('pos_terminals_legal_entity_id_foreign', $terminalIndexes, true)) {
                $table->foreign('legal_entity_id')->references('id')->on('legal_entities')->nullOnDelete();
            }
        });

        // 2. pos_shifts tablosu güncellemeleri
        Schema::table('pos_shifts', function (Blueprint $table) {
            if (!Schema::hasColumn('pos_shifts', 'account_id')) {
                $table->unsignedBigInteger('account_id')->nullable();
            }
            if (!Schema::hasColumn('pos_shifts', 'warehouse_id')) {
                $table->unsignedBigInteger('warehouse_id')->nullable();
            }
            if (!Schema::hasColumn('pos_shifts', 'legal_entity_id')) {
                $table->unsignedBigInteger('legal_entity_id')->nullable();
            }
            if (!Schema::hasColumn('pos_shifts', 'expected_closing_balance')) {
                $table->decimal('expected_closing_balance', 15, 2)->default(0.00);
            }
            if (!Schema::hasColumn('pos_shifts', 'difference_amount')) {
                $table->decimal('difference_amount', 15, 2)->default(0.00);
            }
            if (!Schema::hasColumn('pos_shifts', 'meta_json')) {
                $table->json('meta_json')->nullable();
            }
        });

        $shiftIndexes = collect(Schema::getIndexes('pos_shifts'))->pluck('name')->all();
        Schema::table('pos_shifts', function (Blueprint $table) use ($shiftIndexes) {
            if (!in_array('pos_shifts_account_id_foreign', $shiftIndexes, true)) {
                $table->foreign('account_id')->references('id')->on('accounts')->nullOnDelete();
            }
            if (!in_array('pos_shifts_warehouse_id_foreign', $shiftIndexes, true)) {
                $table->foreign('warehouse_id')->references('id')->on('warehouses')->restrictOnDelete();
            }
            if (!in_array('pos_shifts_legal_entity_id_foreign', $shiftIndexes, true)) {
                $table->foreign('legal_entity_id')->references('id')->on('legal_entities')->nullOnDelete();
            }
        });

        // 3. pos_sales tablosu güncellemeleri
        Schema::table('pos_sales', function (Blueprint $table) {
            if (!Schema::hasColumn('pos_sales', 'legal_entity_id')) {
                $table->unsignedBigInteger('legal_entity_id')->nullable();
            }
            if (!Schema::hasColumn('pos_sales', 'warehouse_id')) {
                $table->unsignedBigInteger('warehouse_id')->nullable();
            }
            if (!Schema::hasColumn('pos_sales', 'party_id')) {
                $table->unsignedBigInteger('party_id')->nullable();
            }
            if (!Schema::hasColumn('pos_sales', 'account_id')) {
                $table->unsignedBigInteger('account_id')->nullable();
            }
            if (!Schema::hasColumn('pos_sales', 'collection_id')) {
                $table->unsignedBigInteger('collection_id')->nullable();
            }
            if (!Schema::hasColumn('pos_sales', 'source_key')) {
                $table->string('source_key', 191)->nullable();
            }
            if (!Schema::hasColumn('pos_sales', 'reference_number')) {
                $table->string('reference_number')->nullable();
            }
            if (!Schema::hasColumn('pos_sales', 'status')) {
                $table->string('status')->default('posted');
            }
            if (!Schema::hasColumn('pos_sales', 'posted_at')) {
                $table->timestamp('posted_at')->nullable();
            }
            if (!Schema::hasColumn('pos_sales', 'voided_at')) {
                $table->timestamp('voided_at')->nullable();
            }
            if (!Schema::hasColumn('pos_sales', 'void_reason')) {
                $table->string('void_reason')->nullable();
            }
            if (!Schema::hasColumn('pos_sales', 'meta_json')) {
                $table->json('meta_json')->nullable();
            }
        });

        $saleIndexes = collect(Schema::getIndexes('pos_sales'))->pluck('name')->all();
        Schema::table('pos_sales', function (Blueprint $table) use ($saleIndexes) {
            if (!in_array('pos_sales_legal_entity_id_foreign', $saleIndexes, true)) {
                $table->foreign('legal_entity_id')->references('id')->on('legal_entities')->nullOnDelete();
            }
            if (!in_array('pos_sales_warehouse_id_foreign', $saleIndexes, true)) {
                $table->foreign('warehouse_id')->references('id')->on('warehouses')->restrictOnDelete();
            }
            if (!in_array('pos_sales_party_id_foreign', $saleIndexes, true)) {
                $table->foreign('party_id')->references('id')->on('parties')->nullOnDelete();
            }
            if (!in_array('pos_sales_account_id_foreign', $saleIndexes, true)) {
                $table->foreign('account_id')->references('id')->on('accounts')->nullOnDelete();
            }
            if (!in_array('pos_sales_collection_id_foreign', $saleIndexes, true)) {
                $table->foreign('collection_id')->references('id')->on('collections')->nullOnDelete();
            }

            // Indexes
            if (!in_array('pos_sales_user_source_key_unique', $saleIndexes, true)) {
                $table->unique(['user_id', 'source_key'], 'pos_sales_user_source_key_unique');
            }
            if (!in_array('pos_sales_user_status_idx', $saleIndexes, true)) {
                $table->index(['user_id', 'status'], 'pos_sales_user_status_idx');
            }
            if (!in_array('pos_sales_user_shift_status_idx', $saleIndexes, true)) {
                $table->index(['user_id', 'pos_shift_id', 'status'], 'pos_sales_user_shift_status_idx');
            }
            if (!in_array('pos_sales_user_warehouse_status_idx', $saleIndexes, true)) {
                $table->index(['user_id', 'warehouse_id', 'status'], 'pos_sales_user_warehouse_status_idx');
            }
            if (!in_array('pos_sales_user_party_status_idx', $saleIndexes, true)) {
                $table->index(['user_id', 'party_id', 'status'], 'pos_sales_user_party_status_idx');
            }
            if (!in_array('pos_sales_user_so_idx', $saleIndexes, true)) {
                $table->index(['user_id', 'sales_order_id'], 'pos_sales_user_so_idx');
            }
            if (!in_array('pos_sales_user_coll_idx', $saleIndexes, true)) {
                $table->index(['user_id', 'collection_id'], 'pos_sales_user_coll_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        // 1. pos_sales tablosu foreign key & column drops
        if (Schema::hasTable('pos_sales')) {
            $fks = [
                'pos_sales_legal_entity_id_foreign',
                'pos_sales_warehouse_id_foreign',
                'pos_sales_party_id_foreign',
                'pos_sales_account_id_foreign',
                'pos_sales_collection_id_foreign'
            ];
            foreach ($fks as $fk) {
                try {
                    DB::statement("ALTER TABLE pos_sales DROP FOREIGN KEY {$fk}");
                } catch (\Exception $e) {}
            }

            Schema::table('pos_sales', function (Blueprint $table) {
                $cols = [
                    'legal_entity_id', 'warehouse_id', 'party_id', 'account_id', 'collection_id',
                    'source_key', 'reference_number', 'status', 'posted_at', 'voided_at', 'void_reason', 'meta_json'
                ];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('pos_sales', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        // 2. pos_shifts tablosu foreign key & column drops
        if (Schema::hasTable('pos_shifts')) {
            $fks = [
                'pos_shifts_account_id_foreign',
                'pos_shifts_warehouse_id_foreign',
                'pos_shifts_legal_entity_id_foreign'
            ];
            foreach ($fks as $fk) {
                try {
                    DB::statement("ALTER TABLE pos_shifts DROP FOREIGN KEY {$fk}");
                } catch (\Exception $e) {}
            }

            Schema::table('pos_shifts', function (Blueprint $table) {
                $cols = ['account_id', 'warehouse_id', 'legal_entity_id', 'expected_closing_balance', 'difference_amount', 'meta_json'];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('pos_shifts', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        // 3. pos_terminals tablosu foreign key & column drops
        if (Schema::hasTable('pos_terminals')) {
            $fks = [
                'pos_terminals_warehouse_id_foreign',
                'pos_terminals_account_id_foreign',
                'pos_terminals_legal_entity_id_foreign'
            ];
            foreach ($fks as $fk) {
                try {
                    DB::statement("ALTER TABLE pos_terminals DROP FOREIGN KEY {$fk}");
                } catch (\Exception $e) {}
            }

            Schema::table('pos_terminals', function (Blueprint $table) {
                $cols = ['warehouse_id', 'account_id', 'legal_entity_id', 'meta_json'];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('pos_terminals', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        Schema::enableForeignKeyConstraints();
    }
};
