<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Party;
use App\Models\Receivable;
use App\Models\Payable;
use App\Models\Collection;
use App\Models\Payment;
use App\Models\User;
use App\Models\LegalEntity;
use App\Services\Accounting\OutstandingInvoiceService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutstandingInvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Party $party;
    private OutstandingInvoiceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['is_active' => true]);
        $this->party = Party::factory()->create(['user_id' => $this->user->id]);

        // Seed Minimal Chart of Accounts
        $seeder = new ChartOfAccountsSeeder();
        $seeder->runForUser($this->user->id);

        $this->service = app(OutstandingInvoiceService::class);
    }

    public function test_create_receivable_creates_receivable_and_journal_entry(): void
    {
        $receivable = $this->service->createReceivable([
            'user_id'       => $this->user->id,
            'party_id'      => $this->party->id,
            'amount'        => 1500.00,
            'document_date' => now()->toDateString(),
            'due_date'      => now()->addDays(30)->toDateString(),
            'document_number' => 'INV-001',
            'description'   => 'Test Receivable Invoice',
        ]);

        $this->assertDatabaseHas('receivables', [
            'id' => $receivable->id,
            'amount' => 1500.00,
            'status' => 'open',
        ]);

        // Check general ledger entry
        $this->assertNotNull($receivable->journal_entry_id);
        $this->assertDatabaseHas('journal_entries', [
            'id' => $receivable->journal_entry_id,
            'entry_type' => 'sales_invoice',
        ]);

        // Verify Journal Lines: debit 120 (Alıcılar), credit 600 (Satışlar)
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $receivable->journal_entry_id,
            'debit_amount' => 1500.00,
            'credit_amount' => 0.00,
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $receivable->journal_entry_id,
            'debit_amount' => 0.00,
            'credit_amount' => 1500.00,
        ]);
    }

    public function test_create_payable_creates_payable_and_journal_entry(): void
    {
        $payable = $this->service->createPayable([
            'user_id'       => $this->user->id,
            'party_id'      => $this->party->id,
            'amount'        => 2200.00,
            'document_date' => now()->toDateString(),
            'due_date'      => now()->addDays(15)->toDateString(),
            'document_number' => 'BILL-100',
            'description'   => 'Supplier Bill',
        ]);

        $this->assertDatabaseHas('payables', [
            'id' => $payable->id,
            'amount' => 2200.00,
            'status' => 'open',
        ]);

        $this->assertNotNull($payable->journal_entry_id);
        $this->assertDatabaseHas('journal_entries', [
            'id' => $payable->journal_entry_id,
            'entry_type' => 'purchase_invoice',
        ]);

        // Verify Journal Lines: debit 770 (Giderler), credit 320 (Satıcılar)
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $payable->journal_entry_id,
            'debit_amount' => 2200.00,
            'credit_amount' => 0.00,
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $payable->journal_entry_id,
            'debit_amount' => 0.00,
            'credit_amount' => 2200.00,
        ]);
    }

    public function test_record_collection_registers_collection_and_journal_entry(): void
    {
        $collection = $this->service->recordCollection([
            'user_id'         => $this->user->id,
            'party_id'        => $this->party->id,
            'amount'          => 800.00,
            'collection_date' => now()->toDateString(),
            'payment_method'  => 'bank',
            'description'     => 'Customer Paid Partial',
        ]);

        $this->assertDatabaseHas('collections', [
            'id' => $collection->id,
            'amount' => 800.00,
            'status' => 'posted',
        ]);

        // General Ledger: debit 102 (Bankalar), credit 120 (Alıcılar)
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $collection->journal_entry_id,
            'debit_amount' => 800.00,
            'credit_amount' => 0.00,
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $collection->journal_entry_id,
            'debit_amount' => 0.00,
            'credit_amount' => 800.00,
        ]);
    }

    public function test_record_payment_registers_payment_and_journal_entry(): void
    {
        $payment = $this->service->recordPayment([
            'user_id'      => $this->user->id,
            'party_id'     => $this->party->id,
            'amount'       => 1200.00,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'bank',
            'description'  => 'Paid Supplier partially',
        ]);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'amount' => 1200.00,
            'status' => 'posted',
        ]);

        // General Ledger: debit 320 (Satıcılar), credit 102 (Bankalar)
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $payment->journal_entry_id,
            'debit_amount' => 1200.00,
            'credit_amount' => 0.00,
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $payment->journal_entry_id,
            'debit_amount' => 0.00,
            'credit_amount' => 1200.00,
        ]);
    }

    public function test_collection_allocation_partially_and_fully_pays_receivable(): void
    {
        $receivable = $this->service->createReceivable([
            'user_id'       => $this->user->id,
            'party_id'      => $this->party->id,
            'amount'        => 1000.00,
            'document_date' => now()->toDateString(),
        ]);

        $collection = $this->service->recordCollection([
            'user_id'         => $this->user->id,
            'party_id'        => $this->party->id,
            'amount'          => 1000.00,
            'collection_date' => now()->toDateString(),
        ]);

        // 1. Partial Allocation: 400.00
        $this->service->allocateCollection($collection, [
            ['receivable_id' => $receivable->id, 'amount' => 400.00]
        ]);

        $receivable->refresh();
        $this->assertEquals(400.00, (float) $receivable->paid_amount);
        $this->assertEquals(600.00, $receivable->remainingAmount());
        $this->assertEquals('partially_paid', $receivable->status);

        // 2. Full Allocation: Remaining 600.00
        $this->service->allocateCollection($collection, [
            ['receivable_id' => $receivable->id, 'amount' => 600.00]
        ]);

        $receivable->refresh();
        $this->assertEquals(1000.00, (float) $receivable->paid_amount);
        $this->assertEquals(0.00, $receivable->remainingAmount());
        $this->assertEquals('paid', $receivable->status);
    }

    public function test_allocation_exceeding_outstanding_balance_is_rejected(): void
    {
        $receivable = $this->service->createReceivable([
            'user_id'       => $this->user->id,
            'party_id'      => $this->party->id,
            'amount'        => 500.00,
            'document_date' => now()->toDateString(),
        ]);

        $collection = $this->service->recordCollection([
            'user_id'         => $this->user->id,
            'party_id'        => $this->party->id,
            'amount'          => 600.00,
            'collection_date' => now()->toDateString(),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/kalan bakiyesinden.*büyük olamaz/i');

        $this->service->allocateCollection($collection, [
            ['receivable_id' => $receivable->id, 'amount' => 500.01]
        ]);
    }

    public function test_party_statement_aggregates_correctly(): void
    {
        // 1. Receivable (debit +1000)
        $this->service->createReceivable([
            'user_id'       => $this->user->id,
            'party_id'      => $this->party->id,
            'amount'        => 1000.00,
            'document_date' => now()->subDays(5)->toDateString(),
        ]);

        // 2. Collection (credit -400)
        $this->service->recordCollection([
            'user_id'         => $this->user->id,
            'party_id'        => $this->party->id,
            'amount'          => 400.00,
            'collection_date' => now()->subDays(3)->toDateString(),
        ]);

        // 3. Payable (credit -200)
        $this->service->createPayable([
            'user_id'       => $this->user->id,
            'party_id'      => $this->party->id,
            'amount'        => 200.00,
            'document_date' => now()->subDays(1)->toDateString(),
        ]);

        $statement = $this->service->getPartyStatement($this->user->id, $this->party->id);

        $this->assertCount(3, $statement);

        // 1st entry: Receivable
        $this->assertEquals('receivable', $statement[0]['type']);
        $this->assertEquals(1000.00, $statement[0]['debit']);
        $this->assertEquals(1000.00, $statement[0]['balance']);

        // 2nd entry: Collection
        $this->assertEquals('collection', $statement[1]['type']);
        $this->assertEquals(400.00, $statement[1]['credit']);
        $this->assertEquals(600.00, $statement[1]['balance']);

        // 3rd entry: Payable
        $this->assertEquals('payable', $statement[2]['type']);
        $this->assertEquals(200.00, $statement[2]['credit']);
        $this->assertEquals(400.00, $statement[2]['balance']);
    }

    public function test_invoice_service_rejects_other_user_party(): void
    {
        $otherUser = User::factory()->create();
        $otherParty = Party::factory()->create(['user_id' => $otherUser->id]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/bu kullanıcıya ait değil/i');

        $this->service->createReceivable([
            'user_id'       => $this->user->id,
            'party_id'      => $otherParty->id,
            'amount'        => 500.00,
            'document_date' => now()->toDateString(),
        ]);
    }

    public function test_invoice_service_rejects_other_user_legal_entity(): void
    {
        $otherUser = User::factory()->create();
        $otherEntity = \App\Models\LegalEntity::create([
            'user_id' => $otherUser->id,
            'name' => 'Fake Legal Entity',
            'tax_number' => '999999',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/bu kullanıcıya ait değil/i');

        $this->service->createReceivable([
            'user_id'       => $this->user->id,
            'party_id'      => $this->party->id,
            'legal_entity_id' => $otherEntity->id,
            'amount'        => 500.00,
            'document_date' => now()->toDateString(),
        ]);
    }

    public function test_allocation_rejects_party_mismatch_for_collections(): void
    {
        $otherParty = Party::factory()->create(['user_id' => $this->user->id]);

        $receivable = $this->service->createReceivable([
            'user_id'       => $this->user->id,
            'party_id'      => $this->party->id,
            'amount'        => 500.00,
            'document_date' => now()->toDateString(),
        ]);

        $collection = $this->service->recordCollection([
            'user_id'         => $this->user->id,
            'party_id'        => $otherParty->id,
            'amount'          => 500.00,
            'collection_date' => now()->toDateString(),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/ilişkili olduğu party faturasına dağıtılabilir/i');

        $this->service->allocateCollection($collection, [
            ['receivable_id' => $receivable->id, 'amount' => 500.00]
        ]);
    }

    public function test_allocation_rejects_party_mismatch_for_payments(): void
    {
        $otherParty = Party::factory()->create(['user_id' => $this->user->id]);

        $payable = $this->service->createPayable([
            'user_id'       => $this->user->id,
            'party_id'      => $this->party->id,
            'amount'        => 500.00,
            'document_date' => now()->toDateString(),
        ]);

        $payment = $this->service->recordPayment([
            'user_id'      => $this->user->id,
            'party_id'     => $otherParty->id,
            'amount'       => 500.00,
            'payment_date' => now()->toDateString(),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/ilişkili olduğu party faturasına dağıtılabilir/i');

        $this->service->allocatePayment($payment, [
            ['payable_id' => $payable->id, 'amount' => 500.00]
        ]);
    }

    public function test_allocation_rejects_legal_entity_mismatch_for_collections(): void
    {
        $le1 = LegalEntity::create(['user_id' => $this->user->id, 'name' => 'LE 1', 'tax_number' => '111111']);
        $le2 = LegalEntity::create(['user_id' => $this->user->id, 'name' => 'LE 2', 'tax_number' => '222222']);

        // Scenario A: Invoice has LE, collection does not (null)
        $receivableWithLE = $this->service->createReceivable([
            'user_id'       => $this->user->id,
            'party_id'      => $this->party->id,
            'legal_entity_id' => $le1->id,
            'amount'        => 500.00,
            'document_date' => now()->toDateString(),
        ]);
        $collectionNullLE = $this->service->recordCollection([
            'user_id'         => $this->user->id,
            'party_id'        => $this->party->id,
            'amount'          => 500.00,
            'collection_date' => now()->toDateString(),
        ]);

        try {
            $this->service->allocateCollection($collectionNullLE, [
                ['receivable_id' => $receivableWithLE->id, 'amount' => 500.00]
            ]);
            $this->fail('Allocating global collection to LE-specific receivable should fail');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('legal entity faturasına dağıtılabilir', $e->getMessage());
        }

        // Scenario B: Invoice does not have LE (null), collection has LE
        $receivableNullLE = $this->service->createReceivable([
            'user_id'       => $this->user->id,
            'party_id'      => $this->party->id,
            'amount'        => 500.00,
            'document_date' => now()->toDateString(),
        ]);
        $collectionWithLE = $this->service->recordCollection([
            'user_id'         => $this->user->id,
            'party_id'        => $this->party->id,
            'legal_entity_id' => $le1->id,
            'amount'          => 500.00,
            'collection_date' => now()->toDateString(),
        ]);

        try {
            $this->service->allocateCollection($collectionWithLE, [
                ['receivable_id' => $receivableNullLE->id, 'amount' => 500.00]
            ]);
            $this->fail('Allocating LE-specific collection to global receivable should fail');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('legal entity faturasına dağıtılabilir', $e->getMessage());
        }

        // Scenario C: Both have different LEs
        $collectionWithLE2 = $this->service->recordCollection([
            'user_id'         => $this->user->id,
            'party_id'        => $this->party->id,
            'legal_entity_id' => $le2->id,
            'amount'          => 500.00,
            'collection_date' => now()->toDateString(),
        ]);

        try {
            $this->service->allocateCollection($collectionWithLE2, [
                ['receivable_id' => $receivableWithLE->id, 'amount' => 500.00]
            ]);
            $this->fail('Allocating LE-specific collection to different LE receivable should fail');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('legal entity faturasına dağıtılabilir', $e->getMessage());
        }
    }

    public function test_allocation_rejects_legal_entity_mismatch_for_payments(): void
    {
        $le1 = LegalEntity::create(['user_id' => $this->user->id, 'name' => 'LE 1', 'tax_number' => '111111']);
        $le2 = LegalEntity::create(['user_id' => $this->user->id, 'name' => 'LE 2', 'tax_number' => '222222']);

        // Scenario A: Bill has LE, payment does not (null)
        $payableWithLE = $this->service->createPayable([
            'user_id'       => $this->user->id,
            'party_id'      => $this->party->id,
            'legal_entity_id' => $le1->id,
            'amount'        => 500.00,
            'document_date' => now()->toDateString(),
        ]);
        $paymentNullLE = $this->service->recordPayment([
            'user_id'      => $this->user->id,
            'party_id'     => $this->party->id,
            'amount'       => 500.00,
            'payment_date' => now()->toDateString(),
        ]);

        try {
            $this->service->allocatePayment($paymentNullLE, [
                ['payable_id' => $payableWithLE->id, 'amount' => 500.00]
            ]);
            $this->fail('Allocating global payment to LE-specific payable should fail');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('legal entity faturasına dağıtılabilir', $e->getMessage());
        }

        // Scenario B: Bill does not have LE (null), payment has LE
        $payableNullLE = $this->service->createPayable([
            'user_id'       => $this->user->id,
            'party_id'      => $this->party->id,
            'amount'        => 500.00,
            'document_date' => now()->toDateString(),
        ]);
        $paymentWithLE = $this->service->recordPayment([
            'user_id'      => $this->user->id,
            'party_id'     => $this->party->id,
            'legal_entity_id' => $le1->id,
            'amount'       => 500.00,
            'payment_date' => now()->toDateString(),
        ]);

        try {
            $this->service->allocatePayment($paymentWithLE, [
                ['payable_id' => $payableNullLE->id, 'amount' => 500.00]
            ]);
            $this->fail('Allocating LE-specific payment to global payable should fail');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('legal entity faturasına dağıtılabilir', $e->getMessage());
        }

        // Scenario C: Both have different LEs
        $paymentWithLE2 = $this->service->recordPayment([
            'user_id'      => $this->user->id,
            'party_id'     => $this->party->id,
            'legal_entity_id' => $le2->id,
            'amount'       => 500.00,
            'payment_date' => now()->toDateString(),
        ]);

        try {
            $this->service->allocatePayment($paymentWithLE2, [
                ['payable_id' => $payableWithLE->id, 'amount' => 500.00]
            ]);
            $this->fail('Allocating LE-specific payment to different LE payable should fail');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('legal entity faturasına dağıtılabilir', $e->getMessage());
        }
    }
}
