<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hr_expense_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained('legal_entities')->cascadeOnDelete();
            $table->string('code', 60);
            $table->string('name', 160);
            $table->boolean('requires_receipt')->default(true);
            $table->decimal('default_vat_rate', 5, 2)->default(20);
            $table->decimal('approval_limit', 14, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['legal_entity_id', 'code']);
            $table->index(['legal_entity_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_expense_categories');
    }
};
