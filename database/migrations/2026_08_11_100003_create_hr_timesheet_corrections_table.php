<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hr_timesheet_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained('legal_entities')->cascadeOnDelete();
            $table->foreignId('timesheet_id')->constrained('hr_timesheets')->cascadeOnDelete();
            $table->unsignedInteger('revision_number');
            $table->json('old_values');
            $table->json('new_values');
            $table->text('reason');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['timesheet_id', 'revision_number']);
            $table->index(['legal_entity_id', 'created_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('hr_timesheet_corrections'); }
};
