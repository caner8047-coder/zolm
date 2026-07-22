<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trendyol UI modülleri için sorgu performansı index'leri.
 * BuyboxAnalysis ve CargoInvoiceReconciliation ekranlarında N+1-free
 * aggregate sorguları için gerekli index'leri ekler.
 */
return new class extends Migration
{
    public function up(): void
    {
        // mp_buybox_listings: store_id + status / retrieved_at / seller_rank
        if (Schema::hasTable('mp_buybox_listings')) {
            Schema::table('mp_buybox_listings', function (Blueprint $table) {
                // store_id + seller_rank: kazanan/kaybeden filtresi
                if (! $this->indexExists('mp_buybox_listings', 'mbl_store_rank_idx')) {
                    $table->index(['store_id', 'seller_rank'], 'mbl_store_rank_idx');
                }

                // store_id + retrieved_at: stale data filtresi
                if (! $this->indexExists('mp_buybox_listings', 'mbl_store_retrieved_idx')) {
                    $table->index(['store_id', 'retrieved_at'], 'mbl_store_retrieved_idx');
                }
            });
        }

        // cargo_invoice_lines: store_id + invoice_date + order_number
        if (Schema::hasTable('cargo_invoice_lines')) {
            Schema::table('cargo_invoice_lines', function (Blueprint $table) {
                // store_id + invoice_date: tarih aralığı filtresi
                if (! $this->indexExists('cargo_invoice_lines', 'cil_store_date_idx')) {
                    $table->index(['store_id', 'invoice_date'], 'cil_store_date_idx');
                }

                // store_id + cargo_type: gidiş/iade filtresi
                if (! $this->indexExists('cargo_invoice_lines', 'cil_store_type_idx')) {
                    $table->index(['store_id', 'cargo_type'], 'cil_store_type_idx');
                }
            });
        }

        // integration_sync_runs: store_id + sync_type + finished_at (health center aggregate)
        if (Schema::hasTable('integration_sync_runs')) {
            Schema::table('integration_sync_runs', function (Blueprint $table) {
                if (! $this->indexExists('integration_sync_runs', 'isr_store_type_finished_idx')) {
                    $table->index(['store_id', 'sync_type', 'finished_at'], 'isr_store_type_finished_idx');
                }
            });
        }

        // integration_push_runs: store_id + status + created_at (batch metrics)
        if (Schema::hasTable('integration_push_runs')) {
            Schema::table('integration_push_runs', function (Blueprint $table) {
                if (! $this->indexExists('integration_push_runs', 'ipr_store_status_created_idx')) {
                    $table->index(['store_id', 'status', 'created_at'], 'ipr_store_status_created_idx');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mp_buybox_listings')) {
            Schema::table('mp_buybox_listings', function (Blueprint $table) {
                if ($this->indexExists('mp_buybox_listings', 'mbl_store_rank_idx')) {
                    $table->dropIndex('mbl_store_rank_idx');
                }
                if ($this->indexExists('mp_buybox_listings', 'mbl_store_retrieved_idx')) {
                    $table->dropIndex('mbl_store_retrieved_idx');
                }
            });
        }

        if (Schema::hasTable('cargo_invoice_lines')) {
            Schema::table('cargo_invoice_lines', function (Blueprint $table) {
                if ($this->indexExists('cargo_invoice_lines', 'cil_store_date_idx')) {
                    $table->dropIndex('cil_store_date_idx');
                }
                if ($this->indexExists('cargo_invoice_lines', 'cil_store_type_idx')) {
                    $table->dropIndex('cil_store_type_idx');
                }
            });
        }

        if (Schema::hasTable('integration_sync_runs')) {
            Schema::table('integration_sync_runs', function (Blueprint $table) {
                if ($this->indexExists('integration_sync_runs', 'isr_store_type_finished_idx')) {
                    $table->dropIndex('isr_store_type_finished_idx');
                }
            });
        }

        if (Schema::hasTable('integration_push_runs')) {
            Schema::table('integration_push_runs', function (Blueprint $table) {
                if ($this->indexExists('integration_push_runs', 'ipr_store_status_created_idx')) {
                    $table->dropIndex('ipr_store_status_created_idx');
                }
            });
        }
    }

    protected function indexExists(string $table, string $indexName): bool
    {
        $conn = \Illuminate\Support\Facades\DB::connection()->getDriverName();
        if ($conn === 'sqlite') {
            return collect(\Illuminate\Support\Facades\DB::select("PRAGMA index_list($table)"))
                ->pluck('name')
                ->contains($indexName);
        }

        if ($conn === 'mysql') {
            $results = \Illuminate\Support\Facades\DB::select("SHOW INDEXES FROM {$table} WHERE Key_name = ?", [$indexName]);
            return count($results) > 0;
        }

        return false;
    }
};
