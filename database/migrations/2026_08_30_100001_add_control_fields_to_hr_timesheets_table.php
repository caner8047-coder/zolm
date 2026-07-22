<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_timesheets', function (Blueprint $table) {
            $table->string('day_type', 30)->default('workday')->after('work_date');
            $table->foreignId('holiday_id')->nullable()->after('day_type')->constrained('hr_holidays')->nullOnDelete();
            $table->unsignedInteger('requested_leave_minutes')->default(0)->after('leave_minutes');
            $table->unsignedInteger('holiday_work_minutes')->default(0)->after('overtime_minutes');
            $table->unsignedInteger('weekly_rest_work_minutes')->default(0)->after('holiday_work_minutes');
            $table->unsignedSmallInteger('anomaly_count')->default(0)->after('missing_minutes');
            $table->json('leave_request_ids')->nullable()->after('last_out_at');
            $table->json('attendance_event_ids')->nullable()->after('leave_request_ids');
            $table->json('calculation_flags')->nullable()->after('attendance_event_ids');

            $table->index(['legal_entity_id', 'timesheet_period_id', 'day_type'], 'hr_timesheet_period_day_type_idx');
            $table->index(['legal_entity_id', 'timesheet_period_id', 'anomaly_count'], 'hr_timesheet_period_anomaly_idx');
        });
    }

    public function down(): void
    {
        Schema::table('hr_timesheets', function (Blueprint $table) {
            $table->dropIndex('hr_timesheet_period_day_type_idx');
            $table->dropIndex('hr_timesheet_period_anomaly_idx');
            $table->dropConstrainedForeignId('holiday_id');
            $table->dropColumn([
                'day_type',
                'requested_leave_minutes',
                'holiday_work_minutes',
                'weekly_rest_work_minutes',
                'anomaly_count',
                'leave_request_ids',
                'attendance_event_ids',
                'calculation_flags',
            ]);
        });
    }
};
