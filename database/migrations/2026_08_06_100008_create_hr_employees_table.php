<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legal_entity_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('employee_number', 50);

            // Hassas veriler: şifreli + hash
            $table->text('national_id_encrypted');
            $table->string('national_id_hash', 64);
            $table->string('national_id_last_four', 4);

            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('gender', 10)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('marital_status', 20)->nullable();
            $table->unsignedBigInteger('photo_file_id')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('personal_email', 255)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('district', 100)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('emergency_contact_name', 200)->nullable();
            $table->string('emergency_contact_phone', 20)->nullable();
            $table->string('emergency_contact_relation', 100)->nullable();
            $table->string('blood_type', 5)->nullable();

            $table->string('status', 20)->default('active');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['legal_entity_id', 'employee_number']);
            $table->unique(['legal_entity_id', 'national_id_hash']);
            $table->foreign('legal_entity_id')->references('id')->on('legal_entities')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');

            $table->index('status');
            $table->index(['last_name', 'first_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_employees');
    }
};
