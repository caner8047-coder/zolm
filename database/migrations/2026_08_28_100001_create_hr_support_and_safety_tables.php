<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_support_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained('legal_entities')->cascadeOnDelete();
            $table->string('ticket_number', 40);
            $table->foreignId('requester_employee_id')->nullable()->constrained('hr_employees')->nullOnDelete();
            $table->foreignId('requester_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category', 40);
            $table->string('subject', 180);
            $table->text('description_encrypted');
            $table->string('priority', 20)->default('normal');
            $table->string('status', 30)->default('open');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->unique(['legal_entity_id', 'ticket_number']);
            $table->index(['legal_entity_id', 'status', 'priority']);
        });

        Schema::create('hr_support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained('legal_entities')->cascadeOnDelete();
            $table->foreignId('support_ticket_id')->constrained('hr_support_tickets')->cascadeOnDelete();
            $table->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body_encrypted');
            $table->boolean('is_internal')->default(false);
            $table->timestamps();
            $table->index(['support_ticket_id', 'created_at']);
        });

        Schema::create('hr_health_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained('legal_entities')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr_employees')->restrictOnDelete();
            $table->string('record_type', 40);
            $table->date('recorded_on');
            $table->date('expires_on')->nullable();
            $table->text('provider_encrypted')->nullable();
            $table->text('result_encrypted');
            $table->text('details_encrypted')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['legal_entity_id', 'recorded_on']);
            $table->index(['employee_id', 'expires_on']);
        });

        Schema::create('hr_safety_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained('legal_entities')->cascadeOnDelete();
            $table->string('incident_number', 40);
            $table->foreignId('reporter_employee_id')->nullable()->constrained('hr_employees')->nullOnDelete();
            $table->foreignId('affected_employee_id')->nullable()->constrained('hr_employees')->nullOnDelete();
            $table->string('incident_type', 40);
            $table->string('severity', 20);
            $table->timestamp('occurred_at');
            $table->string('location', 180);
            $table->text('description_encrypted');
            $table->text('immediate_action_encrypted')->nullable();
            $table->boolean('lost_time')->default(false);
            $table->string('status', 30)->default('reported');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_hash', 64);
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->unique(['legal_entity_id', 'incident_number']);
            $table->index(['legal_entity_id', 'status', 'severity']);
        });

        Schema::create('hr_safety_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained('legal_entities')->cascadeOnDelete();
            $table->foreignId('safety_incident_id')->constrained('hr_safety_incidents')->cascadeOnDelete();
            $table->string('title', 220);
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_on')->nullable();
            $table->string('status', 30)->default('pending');
            $table->text('completion_evidence_encrypted')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['legal_entity_id', 'status', 'due_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_safety_actions');
        Schema::dropIfExists('hr_safety_incidents');
        Schema::dropIfExists('hr_health_records');
        Schema::dropIfExists('hr_support_messages');
        Schema::dropIfExists('hr_support_tickets');
    }
};
