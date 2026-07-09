<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PartyLedgerMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_party_ledger_entries_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('party_ledger_entries'));
    }

    public function test_party_ledger_entries_has_expected_columns(): void
    {
        $columns = [
            'id',
            'user_id',
            'party_id',
            'legal_entity_id',
            'crm_contact_id',
            'source_type',
            'source_key',
            'document_type',
            'document_number',
            'document_date',
            'due_date',
            'description',
            'debit_amount',
            'credit_amount',
            'currency_code',
            'exchange_rate',
            'debit_base_amount',
            'credit_base_amount',
            'status',
            'posted_at',
            'voided_at',
            'void_reason',
            'meta_json',
            'created_at',
            'updated_at',
        ];

        foreach ($columns as $column) {
            $this->assertTrue(
                Schema::hasColumn('party_ledger_entries', $column),
                "Column '{$column}' should exist in party_ledger_entries table."
            );
        }
    }

    public function test_accounting_enabled_feature_flag_defaults_to_false(): void
    {
        $config = config('marketplace.features.accounting_enabled');
        $this->assertFalse($config);
    }
}
