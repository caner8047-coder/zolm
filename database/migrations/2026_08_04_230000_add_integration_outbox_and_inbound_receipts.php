<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('support_integration_deliveries', 'integration_connection_id')) {
            Schema::table('support_integration_deliveries', function (Blueprint $table): void {
                $table->foreignId('integration_connection_id')->nullable()->after('support_integration_event_id')
                    ->constrained('integration_connections')->nullOnDelete();
            });
        }
        if (!Schema::hasColumn('support_integration_deliveries', 'operation_path')) {
            Schema::table('support_integration_deliveries', function (Blueprint $table): void {
                $table->string('operation_path', 255)->nullable()->after('webhook_url');
            });
        }

        if (!Schema::hasTable('support_inbound_webhook_receipts')) {
            Schema::create('support_inbound_webhook_receipts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('store_id');
                $table->foreignId('integration_connection_id')->nullable();
                $table->string('provider', 40);
                $table->string('event_id', 190);
                $table->char('payload_hash', 64);
                $table->string('status', 30)->default('received');
                $table->text('last_error')->nullable();
                $table->timestamp('received_at');
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();
                // Laravel'in varsayılan integration_connection FK adı MySQL'in
                // 64 karakter sınırını aşıyor; kısa ve kararlı adlar kullan.
                $table->foreign('store_id', 'siwr_store_fk')
                    ->references('id')->on('marketplace_stores')->cascadeOnDelete();
                $table->foreign('integration_connection_id', 'siwr_connection_fk')
                    ->references('id')->on('integration_connections')->nullOnDelete();
                $table->unique(['store_id', 'provider', 'event_id'], 'siwr_store_provider_event_unique');
            });
        } else {
            // MySQL DDL transaction dışıdır. Önceki sürüm uzun FK adında
            // yarıda kaldıysa mevcut tabloyu eksik constraint/indexlerle tamamla.
            if (!Schema::hasIndex('support_inbound_webhook_receipts', 'siwr_connection_fk')
                && !Schema::hasIndex('support_inbound_webhook_receipts', 'support_inbound_webhook_receipts_integration_connection_id_foreign')) {
                Schema::table('support_inbound_webhook_receipts', function (Blueprint $table): void {
                    $table->foreign('integration_connection_id', 'siwr_connection_fk')
                        ->references('id')->on('integration_connections')->nullOnDelete();
                });
            }
            if (!Schema::hasIndex('support_inbound_webhook_receipts', 'siwr_store_provider_event_unique')) {
                Schema::table('support_inbound_webhook_receipts', function (Blueprint $table): void {
                    $table->unique(['store_id', 'provider', 'event_id'], 'siwr_store_provider_event_unique');
                });
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('support_inbound_webhook_receipts');
        if (Schema::hasColumn('support_integration_deliveries', 'integration_connection_id')) {
            Schema::table('support_integration_deliveries', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('integration_connection_id');
            });
        }
        if (Schema::hasColumn('support_integration_deliveries', 'operation_path')) {
            Schema::table('support_integration_deliveries', function (Blueprint $table): void {
                $table->dropColumn('operation_path');
            });
        }
    }
};
