<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. warehouses tablosu güncellemesi
        Schema::table('warehouses', function (Blueprint $table) {
            if (!Schema::hasColumn('warehouses', 'legal_entity_id')) {
                $table->unsignedBigInteger('legal_entity_id')->nullable()->after('user_id');
            }
        });

        // Foreign key ekleme (eğer index listesinde warehouses_legal_entity_id_foreign yoksa)
        $warehouseIndexes = collect(Schema::getIndexes('warehouses'))->pluck('name')->all();
        if (!in_array('warehouses_legal_entity_id_foreign', $warehouseIndexes)) {
            Schema::table('warehouses', function (Blueprint $table) {
                $table->foreign('legal_entity_id')->references('id')->on('legal_entities')->nullOnDelete();
            });
        }

        Schema::table('warehouses', function (Blueprint $table) {
            if (!Schema::hasColumn('warehouses', 'meta_json')) {
                $table->json('meta_json')->nullable()->after('is_active');
            }
        });

        // 2. stock_movements tablosu güncellemesi
        Schema::table('stock_movements', function (Blueprint $table) {
            if (!Schema::hasColumn('stock_movements', 'legal_entity_id')) {
                $table->unsignedBigInteger('legal_entity_id')->nullable()->after('product_id');
            }

            if (!Schema::hasColumn('stock_movements', 'source_key')) {
                $table->string('source_key', 191)->nullable()->after('source_id');
            }

            if (!Schema::hasColumn('stock_movements', 'reference_number')) {
                $table->string('reference_number')->nullable()->after('source_key');
            }

            if (!Schema::hasColumn('stock_movements', 'status')) {
                $table->string('status')->default('posted')->after('reference_number');
            }

            if (!Schema::hasColumn('stock_movements', 'posted_at')) {
                $table->timestamp('posted_at')->nullable()->after('status');
            }

            if (!Schema::hasColumn('stock_movements', 'voided_at')) {
                $table->timestamp('voided_at')->nullable()->after('posted_at');
            }

            if (!Schema::hasColumn('stock_movements', 'void_reason')) {
                $table->string('void_reason')->nullable()->after('voided_at');
            }

            if (!Schema::hasColumn('stock_movements', 'meta_json')) {
                $table->json('meta_json')->nullable()->after('void_reason');
            }
        });

        // Foreign key and indexes on stock_movements
        $movementIndexes = collect(Schema::getIndexes('stock_movements'))->pluck('name')->all();

        if (!in_array('stock_movements_legal_entity_id_foreign', $movementIndexes)) {
            Schema::table('stock_movements', function (Blueprint $table) {
                $table->foreign('legal_entity_id')->references('id')->on('legal_entities')->nullOnDelete();
            });
        }

        Schema::table('stock_movements', function (Blueprint $table) use ($movementIndexes) {
            if (!in_array('stock_movements_user_source_key_unique', $movementIndexes)) {
                $table->unique(['user_id', 'source_key'], 'stock_movements_user_source_key_unique');
            }

            if (!in_array('stock_movements_user_status_date_idx', $movementIndexes)) {
                $table->index(['user_id', 'status', 'movement_date'], 'stock_movements_user_status_date_idx');
            }

            if (!in_array('stock_movements_user_wh_status_idx', $movementIndexes)) {
                $table->index(['user_id', 'warehouse_id', 'status'], 'stock_movements_user_wh_status_idx');
            }

            if (!in_array('stock_movements_user_code_status_idx', $movementIndexes)) {
                $table->index(['user_id', 'stock_code', 'status'], 'stock_movements_user_code_status_idx');
            }

            if (!in_array('stock_movements_user_le_status_idx', $movementIndexes)) {
                $table->index(['user_id', 'legal_entity_id', 'status'], 'stock_movements_user_le_status_idx');
            }
        });
    }

    public function down(): void
    {
        // Drop foreign keys and indexes on stock_movements
        if (Schema::hasTable('stock_movements')) {
            $movementIndexes = collect(Schema::getIndexes('stock_movements'))->pluck('name')->all();

            Schema::table('stock_movements', function (Blueprint $table) use ($movementIndexes) {
                if (in_array('stock_movements_legal_entity_id_foreign', $movementIndexes)) {
                    $table->dropForeign(['legal_entity_id']);
                }

                if (in_array('stock_movements_user_source_key_unique', $movementIndexes)) {
                    $table->dropUnique('stock_movements_user_source_key_unique');
                }

                if (in_array('stock_movements_user_status_date_idx', $movementIndexes)) {
                    $table->dropIndex('stock_movements_user_status_date_idx');
                }

                if (in_array('stock_movements_user_wh_status_idx', $movementIndexes)) {
                    $table->dropIndex('stock_movements_user_wh_status_idx');
                }

                if (in_array('stock_movements_user_code_status_idx', $movementIndexes)) {
                    $table->dropIndex('stock_movements_user_code_status_idx');
                }

                if (in_array('stock_movements_user_le_status_idx', $movementIndexes)) {
                    $table->dropIndex('stock_movements_user_le_status_idx');
                }

                $columnsToDrop = [
                    'legal_entity_id', 'source_key', 'reference_number', 'status',
                    'posted_at', 'voided_at', 'void_reason', 'meta_json'
                ];

                foreach ($columnsToDrop as $column) {
                    if (Schema::hasColumn('stock_movements', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        // Drop foreign keys and columns on warehouses
        if (Schema::hasTable('warehouses')) {
            $warehouseIndexes = collect(Schema::getIndexes('warehouses'))->pluck('name')->all();

            Schema::table('warehouses', function (Blueprint $table) use ($warehouseIndexes) {
                if (in_array('warehouses_legal_entity_id_foreign', $warehouseIndexes)) {
                    $table->dropForeign(['legal_entity_id']);
                }

                if (Schema::hasColumn('warehouses', 'legal_entity_id')) {
                    $table->dropColumn('legal_entity_id');
                }

                if (Schema::hasColumn('warehouses', 'meta_json')) {
                    $table->dropColumn('meta_json');
                }
            });
        }
    }
};
