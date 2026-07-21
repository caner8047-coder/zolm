<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_leave_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legal_entity_id');
            $table->string('code', 50);
            $table->string('name');
            $table->string('unit', 20)->default('day');
            $table->boolean('is_paid')->default(true);
            $table->boolean('requires_document')->default(false);
            $table->boolean('allows_negative_balance')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('legal_entity_id')->references('id')->on('legal_entities')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            $table->unique(['legal_entity_id', 'code']);
            $table->index(['legal_entity_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_leave_types');
    }
};
