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
        // Faz 2A vardiya migration'larını önce geri alırız.
        Artisan::call('migrate:rollback', ['--step' => 5]);

        // Faz 1C migration'ları eklendiğinde önce onları geri alırız. Böylece
        // Faz 1B checkpoint testi, sonraki fazların migration sırasına bağlı kalmaz.
        Artisan::call('migrate:rollback', ['--step' => 6]);

        $this->assertTrue(Schema::hasTable('hr_employee_document_versions'));

        // Faz 1B belge migration'larının tamamı 5 migration'dır.
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
