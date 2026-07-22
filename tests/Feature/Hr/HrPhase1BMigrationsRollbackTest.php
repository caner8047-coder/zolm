<?php

namespace Tests\Feature\Hr;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HrPhase1BMigrationsRollbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_faz1b_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('hr_document_types'));
        $this->assertTrue(Schema::hasTable('hr_document_requirements'));
        $this->assertTrue(Schema::hasTable('hr_employee_documents'));
        $this->assertTrue(Schema::hasTable('hr_document_requests'));
        $this->assertTrue(Schema::hasTable('hr_employee_document_versions'));
    }

    public function test_faz0_and_1a_tables_preserved(): void
    {
        // Faz 0 tabloları
        $this->assertTrue(Schema::hasTable('permissions'));
        $this->assertTrue(Schema::hasTable('hr_licenses'));
        $this->assertTrue(Schema::hasTable('hr_holidays'));
        $this->assertTrue(Schema::hasTable('hr_files'));

        // Faz 1A tabloları
        $this->assertTrue(Schema::hasTable('hr_employees'));
        $this->assertTrue(Schema::hasTable('hr_employment_records'));
        $this->assertTrue(Schema::hasTable('hr_departments'));
        $this->assertTrue(Schema::hasTable('hr_units'));
        $this->assertTrue(Schema::hasTable('hr_teams'));
    }

    public function test_document_tables_have_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('hr_document_types', 'legal_entity_id'));
        $this->assertTrue(Schema::hasColumn('hr_document_types', 'code'));
        $this->assertTrue(Schema::hasColumn('hr_document_types', 'category'));
        $this->assertTrue(Schema::hasColumn('hr_document_types', 'sensitivity'));

        $this->assertTrue(Schema::hasColumn('hr_employee_documents', 'document_number_encrypted'));
        $this->assertTrue(Schema::hasColumn('hr_employee_documents', 'document_number_hash'));
        $this->assertTrue(Schema::hasColumn('hr_employee_documents', 'status'));
        $this->assertTrue(Schema::hasColumn('hr_employee_documents', 'verification_status'));
    }

    public function test_document_tables_have_foreign_keys_and_indexes(): void
    {
        $this->assertNotEmpty(Schema::getForeignKeys('hr_employee_documents'));
        $this->assertNotEmpty(Schema::getForeignKeys('hr_document_types'));
        $this->assertNotEmpty(Schema::getIndexes('hr_employee_documents'));
    }

    public function test_faz1b_rollback_drops_document_tables_and_preserves_faz0_1a(): void
    {
        $phase1BLastMigration = '2026_08_07_100005_create_hr_employee_document_versions_table.php';
        $laterMigrationCount = collect(glob(database_path('migrations/*.php')))
            ->map(fn (string $path): string => basename($path))
            ->filter(fn (string $migration): bool => $migration > $phase1BLastMigration)
            ->count();

        // Yeni fazlar eklense bile checkpoint testi sabit adım sayılarına bağlı kalmasın.
        if ($laterMigrationCount > 0) {
            Artisan::call('migrate:rollback', ['--step' => $laterMigrationCount]);
        }

        $this->assertTrue(Schema::hasTable('hr_employee_document_versions'));

        Artisan::call('migrate:rollback', ['--step' => 5]);

        $this->assertFalse(Schema::hasTable('hr_employee_document_versions'));
        $this->assertFalse(Schema::hasTable('hr_document_requests'));
        $this->assertFalse(Schema::hasTable('hr_employee_documents'));
        $this->assertFalse(Schema::hasTable('hr_document_requirements'));
        $this->assertFalse(Schema::hasTable('hr_document_types'));

        // Faz 0 ve Faz 1A tablaları korunmuş olmalı
        $this->assertTrue(Schema::hasTable('permissions'));
        $this->assertTrue(Schema::hasTable('hr_employees'));
        $this->assertTrue(Schema::hasTable('hr_departments'));
    }
}
