<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        try {
            Schema::table('hr_departments', function (Blueprint $table) {
                $table->foreign('manager_employee_id')->references('id')->on('hr_employees')->onDelete('set null');
            });
        } catch (\Throwable $e) {}

        try {
            Schema::table('hr_units', function (Blueprint $table) {
                $table->foreign('manager_employee_id')->references('id')->on('hr_employees')->onDelete('set null');
            });
        } catch (\Throwable $e) {}

        try {
            Schema::table('hr_teams', function (Blueprint $table) {
                $table->foreign('lead_employee_id')->references('id')->on('hr_employees')->onDelete('set null');
            });
        } catch (\Throwable $e) {}
    }

    public function down(): void
    {
        Schema::table('hr_departments', function (Blueprint $table) {
            $table->dropForeign(['manager_employee_id']);
        });

        Schema::table('hr_units', function (Blueprint $table) {
            $table->dropForeign(['manager_employee_id']);
        });

        Schema::table('hr_teams', function (Blueprint $table) {
            $table->dropForeign(['lead_employee_id']);
        });
    }
};
