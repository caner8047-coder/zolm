<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hr_overtime_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained('legal_entities')->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name', 120);
            $table->decimal('multiplier', 5, 2)->default(1.50);
            $table->unsignedInteger('annual_limit_minutes')->nullable();
            $table->boolean('requires_approval')->default(true);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['legal_entity_id', 'code']);
        });
    }
    public function down(): void { Schema::dropIfExists('hr_overtime_types'); }
};
