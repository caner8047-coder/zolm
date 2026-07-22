<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_payroll_records', function (Blueprint $table) {
            $table->unsignedInteger('holiday_work_minutes')->default(0)->after('overtime_minutes');
            $table->unsignedInteger('weekly_rest_work_minutes')->default(0)->after('holiday_work_minutes');
            $table->unsignedInteger('approved_regular_overtime_minutes')->default(0)->after('approved_overtime_minutes');
            $table->unsignedInteger('approved_holiday_work_minutes')->default(0)->after('approved_regular_overtime_minutes');
            $table->unsignedInteger('approved_weekly_rest_work_minutes')->default(0)->after('approved_holiday_work_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('hr_payroll_records', function (Blueprint $table) {
            $table->dropColumn([
                'holiday_work_minutes',
                'weekly_rest_work_minutes',
                'approved_regular_overtime_minutes',
                'approved_holiday_work_minutes',
                'approved_weekly_rest_work_minutes',
            ]);
        });
    }
};
