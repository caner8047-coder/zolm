<?php

namespace Tests\Feature\Hr;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HrMigrationsRollbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_migrations_create_expected_tables(): void
    {
        $this->assertTrue(Schema::hasTable('permissions'));
        $this->assertTrue(Schema::hasTable('role_permission'));
        $this->assertTrue(Schema::hasTable('model_has_roles'));
        $this->assertTrue(Schema::hasTable('model_has_permissions'));
        $this->assertTrue(Schema::hasTable('hr_licenses'));
        $this->assertTrue(Schema::hasTable('hr_holidays'));
        $this->assertTrue(Schema::hasTable('hr_files'));
    }

    public function test_activity_logs_has_hr_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('activity_logs', 'legal_entity_id'));
        $this->assertTrue(Schema::hasColumn('activity_logs', 'module'));
        $this->assertTrue(Schema::hasColumn('activity_logs', 'contains_sensitive_data'));
    }

    public function test_hr_tables_have_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('permissions', 'name'));
        $this->assertTrue(Schema::hasColumn('permissions', 'guard_name'));

        $this->assertTrue(Schema::hasColumn('hr_licenses', 'legal_entity_id'));
        $this->assertTrue(Schema::hasColumn('hr_licenses', 'module_key'));
        $this->assertTrue(Schema::hasColumn('hr_licenses', 'is_active'));

        $this->assertTrue(Schema::hasColumn('hr_holidays', 'legal_entity_id'));
        $this->assertTrue(Schema::hasColumn('hr_holidays', 'name'));
        $this->assertTrue(Schema::hasColumn('hr_holidays', 'date'));

        $this->assertTrue(Schema::hasColumn('hr_files', 'legal_entity_id'));
        $this->assertTrue(Schema::hasColumn('hr_files', 'disk_path'));
        $this->assertTrue(Schema::hasColumn('hr_files', 'checksum'));
    }
}
