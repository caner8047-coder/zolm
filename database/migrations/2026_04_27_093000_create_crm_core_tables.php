<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('display_name', 180);
            $table->string('normalized_name', 180)->nullable();
            $table->string('primary_email', 180)->nullable();
            $table->string('primary_phone', 40)->nullable();
            $table->string('normalized_phone', 40)->nullable();
            $table->string('billing_tax_number', 40)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('district', 120)->nullable();
            $table->timestamp('first_order_at')->nullable();
            $table->timestamp('last_order_at')->nullable();
            $table->timestamp('last_event_at')->nullable();
            $table->string('last_event_type', 60)->nullable();
            $table->string('last_event_title', 220)->nullable();
            $table->unsignedInteger('order_count')->default(0);
            $table->decimal('gross_revenue_total', 14, 2)->default(0);
            $table->unsignedInteger('return_count')->default(0);
            $table->unsignedInteger('question_count')->default(0);
            $table->unsignedInteger('open_case_count')->default(0);
            $table->unsignedTinyInteger('risk_score')->default(0);
            $table->unsignedTinyInteger('value_score')->default(0);
            $table->string('status', 30)->default('active');
            $table->json('tags_json')->nullable();
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'last_event_at'], 'crm_contacts_user_last_event_idx');
            $table->index(['user_id', 'normalized_name'], 'crm_contacts_user_name_idx');
            $table->index(['user_id', 'normalized_phone'], 'crm_contacts_user_phone_idx');
            $table->index(['user_id', 'billing_tax_number'], 'crm_contacts_user_tax_idx');
            $table->index(['user_id', 'risk_score'], 'crm_contacts_user_risk_idx');
        });

        Schema::create('crm_contact_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('crm_contacts')->cascadeOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('marketplace_stores')->nullOnDelete();
            $table->string('marketplace', 50)->nullable();
            $table->string('source_type', 50);
            $table->string('external_customer_id', 140)->nullable();
            $table->string('email', 180)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('normalized_phone', 40)->nullable();
            $table->string('name', 180)->nullable();
            $table->string('normalized_name', 180)->nullable();
            $table->string('tax_number', 40)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('district', 120)->nullable();
            $table->decimal('confidence', 5, 2)->default(0);
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'source_type', 'store_id', 'external_customer_id'], 'crm_identities_user_source_store_external_unique');
            $table->index(['user_id', 'email'], 'crm_identities_user_email_idx');
            $table->index(['user_id', 'normalized_phone'], 'crm_identities_user_phone_idx');
            $table->index(['user_id', 'tax_number'], 'crm_identities_user_tax_idx');
            $table->index(['contact_id', 'source_type'], 'crm_identities_contact_source_idx');
        });

        Schema::create('crm_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('crm_contacts')->nullOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('marketplace_stores')->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_type', 50);
            $table->string('category', 50)->default('general');
            $table->string('priority', 20)->default('normal');
            $table->string('status', 30)->default('open');
            $table->nullableMorphs('subject');
            $table->string('case_key', 191)->nullable();
            $table->string('title', 220);
            $table->text('summary')->nullable();
            $table->timestamp('sla_due_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'case_key'], 'crm_cases_user_case_key_unique');
            $table->index(['user_id', 'status', 'priority'], 'crm_cases_user_status_priority_idx');
            $table->index(['contact_id', 'status'], 'crm_cases_contact_status_idx');
            $table->index(['store_id', 'source_type'], 'crm_cases_store_source_idx');
        });

        Schema::create('crm_timeline_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('crm_contacts')->nullOnDelete();
            $table->foreignId('case_id')->nullable()->constrained('crm_cases')->nullOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('marketplace_stores')->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_key', 191)->nullable();
            $table->string('event_type', 60);
            $table->string('source_type', 50);
            $table->nullableMorphs('subject');
            $table->string('title', 220);
            $table->text('body')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->json('payload_json')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'event_key'], 'crm_timeline_user_event_unique');
            $table->index(['contact_id', 'occurred_at'], 'crm_timeline_contact_occurred_idx');
            $table->index(['user_id', 'source_type', 'occurred_at'], 'crm_timeline_user_source_occurred_idx');
        });

        Schema::create('crm_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('crm_contacts')->nullOnDelete();
            $table->foreignId('case_id')->nullable()->constrained('crm_cases')->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('task_type', 40)->default('follow_up');
            $table->string('priority', 20)->default('normal');
            $table->string('status', 30)->default('open');
            $table->string('title', 220);
            $table->text('description')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'due_at'], 'crm_tasks_user_status_due_idx');
            $table->index(['contact_id', 'status'], 'crm_tasks_contact_status_idx');
        });

        Schema::create('crm_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('crm_contacts')->nullOnDelete();
            $table->foreignId('case_id')->nullable()->constrained('crm_cases')->nullOnDelete();
            $table->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->string('visibility', 30)->default('internal');
            $table->boolean('is_pinned')->default(false);
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->index(['contact_id', 'created_at'], 'crm_notes_contact_created_idx');
            $table->index(['user_id', 'is_pinned'], 'crm_notes_user_pinned_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_notes');
        Schema::dropIfExists('crm_tasks');
        Schema::dropIfExists('crm_timeline_events');
        Schema::dropIfExists('crm_cases');
        Schema::dropIfExists('crm_contact_identities');
        Schema::dropIfExists('crm_contacts');
    }
};
