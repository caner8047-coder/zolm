<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cargo_report_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_entity_id')->nullable()->constrained('legal_entities')->nullOnDelete();
            $table->foreignId('cargo_carrier_account_id')->nullable()->constrained('cargo_carrier_accounts')->nullOnDelete();
            $table->string('carrier_code', 30)->default('surat');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('source_endpoint', 120)->default('KargoTakipHareketCoklu');
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('total_pieces')->default(0);
            $table->decimal('total_desi', 12, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->decimal('measurement_amount', 14, 2)->default(0);
            $table->decimal('grand_total_amount', 14, 2)->default(0);
            $table->string('currency', 3)->default('TRY');
            $table->string('status', 30)->default('completed');
            $table->text('last_error')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'carrier_code', 'start_date', 'end_date'], 'cargo_report_runs_user_carrier_dates_idx');
            $table->index(['cargo_carrier_account_id', 'start_date'], 'cargo_report_runs_account_start_idx');
        });

        Schema::create('cargo_report_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cargo_report_run_id')->nullable()->constrained('cargo_report_runs')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_entity_id')->nullable()->constrained('legal_entities')->nullOnDelete();
            $table->foreignId('cargo_carrier_account_id')->nullable()->constrained('cargo_carrier_accounts')->nullOnDelete();
            $table->string('carrier_code', 30)->default('surat');
            $table->date('report_date');
            $table->string('line_hash', 80);
            $table->string('tracking_number', 120)->nullable();
            $table->string('web_order_code', 120)->nullable();
            $table->string('sales_code', 120)->nullable();
            $table->string('customer_name', 200)->nullable();
            $table->string('sender_name', 200)->nullable();
            $table->string('destination_city', 120)->nullable();
            $table->string('destination_district', 120)->nullable();
            $table->string('status', 120)->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->unsignedInteger('pieces')->default(0);
            $table->decimal('desi', 12, 2)->default(0);
            $table->decimal('measurement_desi', 12, 2)->default(0);
            $table->decimal('measurement_kg', 12, 2)->default(0);
            $table->decimal('amount', 14, 2)->default(0);
            $table->decimal('measurement_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->decimal('vat_amount', 14, 2)->default(0);
            $table->decimal('amount_without_vat', 14, 2)->default(0);
            $table->string('currency', 3)->default('TRY');
            $table->timestamp('document_date')->nullable();
            $table->timestamp('carrier_created_at')->nullable();
            $table->timestamp('last_event_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('delivered_to', 200)->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(['cargo_carrier_account_id', 'report_date', 'line_hash'], 'cargo_report_lines_account_date_hash_unique');
            $table->index(['user_id', 'carrier_code', 'report_date'], 'cargo_report_lines_user_carrier_date_idx');
            $table->index(['tracking_number'], 'cargo_report_lines_tracking_idx');
            $table->index(['web_order_code'], 'cargo_report_lines_web_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cargo_report_lines');
        Schema::dropIfExists('cargo_report_runs');
    }
};
