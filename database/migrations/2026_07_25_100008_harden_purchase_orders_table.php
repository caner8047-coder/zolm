<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_orders', 'warehouse_id')) {
                $table->unsignedBigInteger('warehouse_id')->nullable()->after('legal_entity_id');
            }
            if (!Schema::hasColumn('purchase_orders', 'source_key')) {
                $table->string('source_key', 191)->nullable()->after('document_number');
            }
            if (!Schema::hasColumn('purchase_orders', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('payable_id');
            }
            if (!Schema::hasColumn('purchase_orders', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('approved_at');
            }
            if (!Schema::hasColumn('purchase_orders', 'cancel_reason')) {
                $table->string('cancel_reason')->nullable()->after('cancelled_at');
            }
            if (!Schema::hasColumn('purchase_orders', 'due_date')) {
                $table->date('due_date')->nullable()->after('cancel_reason');
            }
            if (!Schema::hasColumn('purchase_orders', 'meta_json')) {
                $table->json('meta_json')->nullable()->after('due_date');
            }
        });

        // Add foreign key and indexes safely
        $indexes = collect(Schema::getIndexes('purchase_orders'))->pluck('name')->all();

        // warehouse_id FK — restrictOnDelete: depo silinemez, pasifleştirilmeli
        if (!in_array('purchase_orders_warehouse_id_foreign', $indexes)) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->foreign('warehouse_id')->references('id')->on('warehouses')->restrictOnDelete();
            });
        }

        // source_key unique per user
        if (!in_array('purchase_orders_user_source_key_unique', $indexes)) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->unique(['user_id', 'source_key'], 'purchase_orders_user_source_key_unique');
            });
        }

        // Composite index: user_id + status + order_date (liste/filtreleme)
        if (!in_array('purchase_orders_user_status_date_index', $indexes)) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->index(['user_id', 'status', 'order_date'], 'purchase_orders_user_status_date_index');
            });
        }

        // Composite index: user_id + party_id + status (cari bazlı filtreleme)
        if (!in_array('purchase_orders_user_party_status_index', $indexes)) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->index(['user_id', 'party_id', 'status'], 'purchase_orders_user_party_status_index');
            });
        }

        // Composite index: user_id + legal_entity_id + status
        if (!in_array('purchase_orders_user_legal_entity_status_index', $indexes)) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->index(['user_id', 'legal_entity_id', 'status'], 'purchase_orders_user_legal_entity_status_index');
            });
        }

        // Composite index: user_id + warehouse_id + status
        if (!in_array('purchase_orders_user_warehouse_status_index', $indexes)) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->index(['user_id', 'warehouse_id', 'status'], 'purchase_orders_user_warehouse_status_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('purchase_orders')) {
            $indexes = collect(Schema::getIndexes('purchase_orders'))->pluck('name')->all();

            Schema::table('purchase_orders', function (Blueprint $table) use ($indexes) {
                if (in_array('purchase_orders_warehouse_id_foreign', $indexes)) {
                    $table->dropForeign(['warehouse_id']);
                }
                if (in_array('purchase_orders_user_source_key_unique', $indexes)) {
                    $table->dropUnique('purchase_orders_user_source_key_unique');
                }
                if (in_array('purchase_orders_user_status_date_index', $indexes)) {
                    $table->dropIndex('purchase_orders_user_status_date_index');
                }
                if (in_array('purchase_orders_user_party_status_index', $indexes)) {
                    $table->dropIndex('purchase_orders_user_party_status_index');
                }
                if (in_array('purchase_orders_user_legal_entity_status_index', $indexes)) {
                    $table->dropIndex('purchase_orders_user_legal_entity_status_index');
                }
                if (in_array('purchase_orders_user_warehouse_status_index', $indexes)) {
                    $table->dropIndex('purchase_orders_user_warehouse_status_index');
                }

                $columns = ['warehouse_id', 'source_key', 'approved_at', 'cancelled_at', 'cancel_reason', 'due_date', 'meta_json'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('purchase_orders', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
