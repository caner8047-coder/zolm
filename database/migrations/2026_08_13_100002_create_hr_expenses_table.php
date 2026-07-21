<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hr_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained('legal_entities')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignId('expense_category_id')->constrained('hr_expense_categories')->restrictOnDelete();
            $table->foreignId('receipt_file_id')->nullable()->constrained('hr_files')->nullOnDelete();
            $table->date('expense_date');
            $table->char('currency', 3)->default('TRY');
            $table->decimal('net_amount', 14, 2);
            $table->decimal('vat_rate', 5, 2)->default(0);
            $table->decimal('vat_amount', 14, 2)->default(0);
            $table->decimal('gross_amount', 14, 2);
            $table->string('status', 30)->default('pending_manager');
            $table->string('merchant_name', 160)->nullable();
            $table->string('document_number', 120)->nullable();
            $table->text('description');
            $table->string('project_reference', 120)->nullable();
            $table->string('order_reference', 120)->nullable();
            $table->string('customer_reference', 120)->nullable();
            $table->uuid('source_key');
            $table->char('payload_hash', 64);
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_reference', 160)->nullable();
            $table->timestamps();
            $table->unique(['legal_entity_id', 'source_key']);
            $table->index(['legal_entity_id', 'status', 'expense_date'], 'hr_expenses_status_date_idx');
            $table->index(['legal_entity_id', 'employee_id', 'expense_date'], 'hr_expenses_employee_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_expenses');
    }
};
