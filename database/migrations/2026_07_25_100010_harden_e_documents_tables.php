<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. e_documents tablosu güncellemeleri
        Schema::table('e_documents', function (Blueprint $table) {
            if (!Schema::hasColumn('e_documents', 'legal_entity_id')) {
                $table->unsignedBigInteger('legal_entity_id')->nullable();
            }
            if (!Schema::hasColumn('e_documents', 'party_id')) {
                $table->unsignedBigInteger('party_id')->nullable();
            }
            if (!Schema::hasColumn('e_documents', 'warehouse_id')) {
                $table->unsignedBigInteger('warehouse_id')->nullable();
            }
            if (!Schema::hasColumn('e_documents', 'source_key')) {
                $table->string('source_key', 191)->nullable();
            }
            if (!Schema::hasColumn('e_documents', 'provider')) {
                $table->string('provider')->default('simulator');
            }
            if (!Schema::hasColumn('e_documents', 'provider_document_id')) {
                $table->string('provider_document_id')->nullable();
            }
            if (!Schema::hasColumn('e_documents', 'profile_type')) {
                $table->string('profile_type')->default('basic');
            }
            if (!Schema::hasColumn('e_documents', 'scenario_type')) {
                $table->string('scenario_type')->nullable();
            }
            if (!Schema::hasColumn('e_documents', 'issue_date')) {
                $table->date('issue_date')->nullable();
            }
            if (!Schema::hasColumn('e_documents', 'due_date')) {
                $table->date('due_date')->nullable();
            }
            if (!Schema::hasColumn('e_documents', 'currency_code')) {
                $table->string('currency_code', 3)->default('TRY');
            }
            if (!Schema::hasColumn('e_documents', 'exchange_rate')) {
                $table->decimal('exchange_rate', 15, 6)->default(1.000000);
            }
            if (!Schema::hasColumn('e_documents', 'subtotal_amount')) {
                $table->decimal('subtotal_amount', 15, 2)->default(0.00);
            }
            if (!Schema::hasColumn('e_documents', 'discount_amount')) {
                $table->decimal('discount_amount', 15, 2)->default(0.00);
            }
            if (!Schema::hasColumn('e_documents', 'vat_amount')) {
                $table->decimal('vat_amount', 15, 2)->default(0.00);
            }
            if (!Schema::hasColumn('e_documents', 'total_amount')) {
                $table->decimal('total_amount', 15, 2)->default(0.00);
            }
            if (!Schema::hasColumn('e_documents', 'buyer_name')) {
                $table->string('buyer_name')->nullable();
            }
            if (!Schema::hasColumn('e_documents', 'buyer_tax_number')) {
                $table->string('buyer_tax_number')->nullable();
            }
            if (!Schema::hasColumn('e_documents', 'buyer_tax_office')) {
                $table->string('buyer_tax_office')->nullable();
            }
            if (!Schema::hasColumn('e_documents', 'buyer_email')) {
                $table->string('buyer_email')->nullable();
            }
            if (!Schema::hasColumn('e_documents', 'buyer_phone')) {
                $table->string('buyer_phone')->nullable();
            }
            if (!Schema::hasColumn('e_documents', 'buyer_address')) {
                $table->text('buyer_address')->nullable();
            }
            if (!Schema::hasColumn('e_documents', 'sent_at')) {
                $table->timestamp('sent_at')->nullable();
            }
            if (!Schema::hasColumn('e_documents', 'accepted_at')) {
                $table->timestamp('accepted_at')->nullable();
            }
            if (!Schema::hasColumn('e_documents', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable();
            }
            if (!Schema::hasColumn('e_documents', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable();
            }
            if (!Schema::hasColumn('e_documents', 'cancel_reason')) {
                $table->string('cancel_reason')->nullable();
            }
            if (!Schema::hasColumn('e_documents', 'provider_request_json')) {
                $table->json('provider_request_json')->nullable();
            }
            if (!Schema::hasColumn('e_documents', 'provider_response_json')) {
                $table->json('provider_response_json')->nullable();
            }
            if (!Schema::hasColumn('e_documents', 'meta_json')) {
                $table->json('meta_json')->nullable();
            }
        });

        // 1.1 Foreign keys and indexes for e_documents
        $docIndexes = collect(Schema::getIndexes('e_documents'))->pluck('name')->all();
        Schema::table('e_documents', function (Blueprint $table) use ($docIndexes) {
            if (!in_array('e_documents_legal_entity_id_foreign', $docIndexes, true)) {
                $table->foreign('legal_entity_id')->references('id')->on('legal_entities')->nullOnDelete();
            }
            if (!in_array('e_documents_party_id_foreign', $docIndexes, true)) {
                $table->foreign('party_id')->references('id')->on('parties')->nullOnDelete();
            }
            if (!in_array('e_documents_warehouse_id_foreign', $docIndexes, true)) {
                $table->foreign('warehouse_id')->references('id')->on('warehouses')->nullOnDelete();
            }

            // Indexes
            if (!in_array('e_documents_user_source_key_unique', $docIndexes, true)) {
                $table->unique(['user_id', 'source_key'], 'e_documents_user_source_key_unique');
            }
            if (!in_array('e_documents_user_invoice_number_unique', $docIndexes, true)) {
                $table->unique(['user_id', 'invoice_number'], 'e_documents_user_invoice_number_unique');
            }
            if (!in_array('e_documents_user_status_issue_date_idx', $docIndexes, true)) {
                $table->index(['user_id', 'status', 'issue_date'], 'e_documents_user_status_issue_date_idx');
            }
            if (!in_array('e_documents_user_type_status_idx', $docIndexes, true)) {
                $table->index(['user_id', 'document_type', 'status'], 'e_documents_user_type_status_idx');
            }
            if (!in_array('e_documents_user_so_idx', $docIndexes, true)) {
                $table->index(['user_id', 'sales_order_id'], 'e_documents_user_so_idx');
            }
            if (!in_array('e_documents_user_party_status_idx', $docIndexes, true)) {
                $table->index(['user_id', 'party_id', 'status'], 'e_documents_user_party_status_idx');
            }
            if (!in_array('e_documents_user_le_status_idx', $docIndexes, true)) {
                $table->index(['user_id', 'legal_entity_id', 'status'], 'e_documents_user_le_status_idx');
            }
        });

        // 2. e_document_lines tablosu
        if (!Schema::hasTable('e_document_lines')) {
            Schema::create('e_document_lines', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

                $table->unsignedBigInteger('e_document_id');
                $table->foreign('e_document_id')->references('id')->on('e_documents')->cascadeOnDelete();

                $table->unsignedBigInteger('sales_order_item_id')->nullable();
                $table->foreign('sales_order_item_id')->references('id')->on('sales_order_items')->nullOnDelete();

                $table->string('stock_code')->nullable();
                $table->string('description');
                $table->decimal('quantity', 15, 4);
                $table->string('unit_name')->default('Adet');
                $table->decimal('unit_price', 15, 2);
                $table->decimal('discount_rate', 8, 4)->default(0.0000);
                $table->decimal('discount_amount', 15, 2)->default(0.00);
                $table->decimal('vat_rate', 8, 4)->default(0.0000);
                $table->decimal('vat_amount', 15, 2)->default(0.00);
                $table->decimal('line_subtotal', 15, 2)->default(0.00);
                $table->decimal('line_total', 15, 2)->default(0.00);
                $table->json('meta_json')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'e_document_id'], 'e_doc_lines_user_doc_idx');
                $table->index(['user_id', 'stock_code'], 'e_doc_lines_user_stock_idx');
            });
        }

        // 3. e_document_events tablosu
        Schema::table('e_document_events', function (Blueprint $table) {
            if (!Schema::hasColumn('e_document_events', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable();
            }
            if (!Schema::hasColumn('e_document_events', 'actor_user_id')) {
                $table->unsignedBigInteger('actor_user_id')->nullable();
            }
            if (!Schema::hasColumn('e_document_events', 'event_type')) {
                $table->string('event_type')->default('status_changed');
            }
            if (!Schema::hasColumn('e_document_events', 'payload_json')) {
                $table->json('payload_json')->nullable();
            }
            if (!Schema::hasColumn('e_document_events', 'occurred_at')) {
                $table->timestamp('occurred_at')->nullable();
            }
        });

        $eventIndexes = collect(Schema::getIndexes('e_document_events'))->pluck('name')->all();
        Schema::table('e_document_events', function (Blueprint $table) use ($eventIndexes) {
            if (!in_array('e_document_events_user_id_foreign', $eventIndexes, true)) {
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            }
            if (!in_array('e_document_events_actor_user_id_foreign', $eventIndexes, true)) {
                $table->foreign('actor_user_id')->references('id')->on('users')->nullOnDelete();
            }
        });

        // 4. e_document_sequences tablosu
        if (!Schema::hasTable('e_document_sequences')) {
            Schema::create('e_document_sequences', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->integer('year');
                $table->string('document_type', 30);
                $table->string('prefix', 10);
                $table->integer('last_number')->default(0);
                $table->timestamps();

                $table->unique(['user_id', 'year', 'document_type'], 'e_doc_seq_user_year_type_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        // 1. Drop sequence and lines
        Schema::dropIfExists('e_document_sequences');
        Schema::dropIfExists('e_document_lines');

        // 2. e_document_events tablosu rollback
        if (Schema::hasTable('e_document_events')) {
            try { DB::statement("ALTER TABLE e_document_events DROP FOREIGN KEY e_document_events_user_id_foreign"); } catch (\Exception $e) {}
            try { DB::statement("ALTER TABLE e_document_events DROP FOREIGN KEY e_document_events_actor_user_id_foreign"); } catch (\Exception $e) {}

            Schema::table('e_document_events', function (Blueprint $table) {
                $cols = ['user_id', 'actor_user_id', 'event_type', 'payload_json', 'occurred_at'];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('e_document_events', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        // 3. e_documents tablosu rollback
        if (Schema::hasTable('e_documents')) {
            $docIndexes = collect(Schema::getIndexes('e_documents'))->pluck('name')->all();

            $fks = [
                'e_documents_legal_entity_id_foreign',
                'e_documents_party_id_foreign',
                'e_documents_warehouse_id_foreign'
            ];
            foreach ($fks as $fk) {
                try { DB::statement("ALTER TABLE e_documents DROP FOREIGN KEY {$fk}"); } catch (\Exception $e) {}
            }

            Schema::table('e_documents', function (Blueprint $table) use ($docIndexes) {
                if (in_array('e_documents_user_source_key_unique', $docIndexes, true)) {
                    $table->dropUnique('e_documents_user_source_key_unique');
                }
                if (in_array('e_documents_user_invoice_number_unique', $docIndexes, true)) {
                    $table->dropUnique('e_documents_user_invoice_number_unique');
                }
                if (in_array('e_documents_user_status_issue_date_idx', $docIndexes, true)) {
                    $table->dropIndex('e_documents_user_status_issue_date_idx');
                }
                if (in_array('e_documents_user_type_status_idx', $docIndexes, true)) {
                    $table->dropIndex('e_documents_user_type_status_idx');
                }
                if (in_array('e_documents_user_so_idx', $docIndexes, true)) {
                    $table->dropIndex('e_documents_user_so_idx');
                }
                if (in_array('e_documents_user_party_status_idx', $docIndexes, true)) {
                    $table->dropIndex('e_documents_user_party_status_idx');
                }
                if (in_array('e_documents_user_le_status_idx', $docIndexes, true)) {
                    $table->dropIndex('e_documents_user_le_status_idx');
                }

                $cols = [
                    'legal_entity_id', 'party_id', 'warehouse_id', 'source_key', 'provider',
                    'provider_document_id', 'profile_type', 'scenario_type', 'issue_date', 'due_date',
                    'currency_code', 'exchange_rate', 'subtotal_amount', 'discount_amount', 'vat_amount',
                    'total_amount', 'buyer_name', 'buyer_tax_number', 'buyer_tax_office', 'buyer_email',
                    'buyer_phone', 'buyer_address', 'sent_at', 'accepted_at', 'rejected_at', 'cancelled_at',
                    'cancel_reason', 'provider_request_json', 'provider_response_json', 'meta_json'
                ];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('e_documents', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        Schema::enableForeignKeyConstraints();
    }
};
