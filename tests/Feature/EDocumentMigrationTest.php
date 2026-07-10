<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\EDocument;
use Tests\TestCase;

class EDocumentMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_e_documents_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('e_documents'));

        $expectedColumns = [
            'id', 'user_id', 'sales_order_id', 'legal_entity_id', 'party_id', 'warehouse_id',
            'document_type', 'uuid', 'invoice_number', 'status', 'source_key', 'provider',
            'provider_document_id', 'profile_type', 'scenario_type', 'issue_date', 'due_date',
            'currency_code', 'exchange_rate', 'subtotal_amount', 'discount_amount', 'vat_amount',
            'total_amount', 'buyer_name', 'buyer_tax_number', 'buyer_tax_office', 'buyer_email',
            'buyer_phone', 'buyer_address', 'pdf_path', 'xml_path', 'sent_at', 'accepted_at',
            'rejected_at', 'cancelled_at', 'cancel_reason', 'provider_request_json',
            'provider_response_json', 'meta_json', 'created_at', 'updated_at'
        ];

        foreach ($expectedColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn('e_documents', $column),
                "e_documents tablosunda '{$column}' kolonu eksik!"
            );
        }
    }

    public function test_e_document_lines_table_exists_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('e_document_lines'));

        $expectedColumns = [
            'id', 'user_id', 'e_document_id', 'sales_order_item_id', 'stock_code', 'description',
            'quantity', 'unit_name', 'unit_price', 'discount_rate', 'discount_amount', 'vat_rate',
            'vat_amount', 'line_subtotal', 'line_total', 'meta_json', 'created_at', 'updated_at'
        ];

        foreach ($expectedColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn('e_document_lines', $column),
                "e_document_lines tablosunda '{$column}' kolonu eksik!"
            );
        }
    }

    public function test_user_id_and_source_key_is_unique(): void
    {
        $user = User::factory()->create();

        EDocument::create([
            'user_id'       => $user->id,
            'uuid'          => (string) \Illuminate\Support\Str::uuid(),
            'document_type' => 'e_archive',
            'source_key'    => 'unique_source_key_123',
            'status'        => 'draft'
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        EDocument::create([
            'user_id'       => $user->id,
            'uuid'          => (string) \Illuminate\Support\Str::uuid(),
            'document_type' => 'e_archive',
            'source_key'    => 'unique_source_key_123',
            'status'        => 'draft'
        ]);
    }

    public function test_user_id_and_invoice_number_is_unique(): void
    {
        $user = User::factory()->create();

        EDocument::create([
            'user_id'        => $user->id,
            'uuid'           => (string) \Illuminate\Support\Str::uuid(),
            'document_type'  => 'e_archive',
            'invoice_number' => 'INV-2026-001',
            'status'         => 'draft'
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        EDocument::create([
            'user_id'        => $user->id,
            'uuid'           => (string) \Illuminate\Support\Str::uuid(),
            'document_type'  => 'e_archive',
            'invoice_number' => 'INV-2026-001',
            'status'         => 'draft'
        ]);
    }
}
