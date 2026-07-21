<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hr_attendance_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained('legal_entities')->cascadeOnDelete();
            $table->string('code', 60);
            $table->string('name', 120);
            $table->string('type', 30);
            $table->string('location')->nullable();
            $table->string('secret_hash')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['legal_entity_id', 'code']);
            $table->index(['legal_entity_id', 'type', 'is_active']);
        });
    }
    public function down(): void { Schema::dropIfExists('hr_attendance_devices'); }
};
