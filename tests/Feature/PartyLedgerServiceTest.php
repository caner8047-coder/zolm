<?php

namespace Tests\Feature;

use App\Models\CrmContact;
use App\Models\LegalEntity;
use App\Models\Party;
use App\Models\PartyLedgerEntry;
use App\Models\User;
use App\Services\Accounting\PartyLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class PartyLedgerServiceTest extends TestCase
{
    use RefreshDatabase;

    private PartyLedgerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PartyLedgerService();
    }

    public function test_post_receivable_writes_debit(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);

        $entry = $this->service->postReceivable($party, 1000.50);

        $this->assertInstanceOf(PartyLedgerEntry::class, $entry);
        $this->assertEquals('receivable', $entry->document_type);
        $this->assertEquals(1000.50, (float) $entry->debit_amount);
        $this->assertEquals(0, (float) $entry->credit_amount);
        $this->assertEquals($user->id, $entry->user_id);
        $this->assertEquals($party->id, $entry->party_id);
    }

    public function test_post_collection_writes_credit(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);

        $entry = $this->service->postCollection($party, 500.25);

        $this->assertEquals('collection', $entry->document_type);
        $this->assertEquals(0, (float) $entry->debit_amount);
        $this->assertEquals(500.25, (float) $entry->credit_amount);
    }

    public function test_post_payable_writes_credit(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);

        $entry = $this->service->postPayable($party, 2000);

        $this->assertEquals('payable', $entry->document_type);
        $this->assertEquals(0, (float) $entry->debit_amount);
        $this->assertEquals(2000, (float) $entry->credit_amount);
    }

    public function test_post_payment_writes_debit(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);

        $entry = $this->service->postPayment($party, 750);

        $this->assertEquals('payment', $entry->document_type);
        $this->assertEquals(750, (float) $entry->debit_amount);
        $this->assertEquals(0, (float) $entry->credit_amount);
    }

    public function test_balance_for_party_calculates_debit_minus_credit(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);

        $this->service->postReceivable($party, 1000);
        $this->service->postCollection($party, 400);

        $balance = $this->service->balanceForParty($party);

        $this->assertEquals(1000, $balance['debit']);
        $this->assertEquals(400, $balance['credit']);
        $this->assertEquals(600, $balance['balance']);
    }

    public function test_balance_positive_means_we_are_creditor(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);

        $this->service->postReceivable($party, 2000);
        $this->service->postCollection($party, 500);

        $balance = $this->service->balanceForParty($party);

        $this->assertGreaterThan(0, $balance['balance']);
    }

    public function test_balance_negative_means_we_are_debtor(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);

        $this->service->postPayable($party, 2000);
        $this->service->postPayment($party, 500);

        $balance = $this->service->balanceForParty($party);

        $this->assertLessThan(0, $balance['balance']);
    }

    public function test_void_entry_removes_from_balance(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);

        $entry = $this->service->postReceivable($party, 1000);
        $balance1 = $this->service->balanceForParty($party);
        $this->assertEquals(1000, $balance1['balance']);

        $this->service->voidEntry($entry, 'Mistake');

        $balance2 = $this->service->balanceForParty($party);
        $this->assertEquals(0, $balance2['balance']);
    }

    public function test_source_key_idempotency(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);

        $entry1 = $this->service->postReceivable($party, 1000, [
            'source_key' => 'order-123',
        ]);

        $entry2 = $this->service->postReceivable($party, 1000, [
            'source_key' => 'order-123',
        ]);

        $this->assertEquals($entry1->id, $entry2->id);
        $this->assertEquals(1, PartyLedgerEntry::where('source_key', 'order-123')->count());
    }

    public function test_user_isolation_is_enforced(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $party1 = Party::factory()->create(['user_id' => $user1->id]);
        $party2 = Party::factory()->create(['user_id' => $user2->id]);

        $this->service->postReceivable($party1, 1000);
        $this->service->postReceivable($party2, 2000);

        $balance1 = $this->service->balanceForParty($party1);
        $balance2 = $this->service->balanceForParty($party2);

        $this->assertEquals(1000, $balance1['balance']);
        $this->assertEquals(2000, $balance2['balance']);
    }

    public function test_zero_amount_is_rejected(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);

        $this->expectException(InvalidArgumentException::class);
        $this->service->postReceivable($party, 0);
    }

    public function test_negative_amount_is_rejected(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);

        $this->expectException(InvalidArgumentException::class);
        $this->service->postReceivable($party, -100);
    }

    public function test_exchange_rate_zero_is_rejected(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->postEntry([
            'user_id' => $user->id,
            'party_id' => $party->id,
            'document_type' => 'receivable',
            'document_date' => now()->toDateString(),
            'debit_amount' => 100,
            'credit_amount' => 0,
            'exchange_rate' => 0,
        ]);
    }

    public function test_exchange_rate_negative_is_rejected(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->postEntry([
            'user_id' => $user->id,
            'party_id' => $party->id,
            'document_type' => 'receivable',
            'document_date' => now()->toDateString(),
            'debit_amount' => 100,
            'credit_amount' => 0,
            'exchange_rate' => -1,
        ]);
    }

    public function test_legal_entity_id_nullable_works(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);

        $entry = $this->service->postReceivable($party, 1000, [
            'legal_entity_id' => null,
        ]);

        $this->assertNull($entry->legal_entity_id);
    }

    public function test_crm_contact_id_nullable_works(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);

        $entry = $this->service->postReceivable($party, 1000, [
            'crm_contact_id' => null,
        ]);

        $this->assertNull($entry->crm_contact_id);
    }

    public function test_scope_posted_excludes_voided_entries(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);

        $entry1 = $this->service->postReceivable($party, 1000);
        $this->service->postReceivable($party, 2000);

        $this->service->voidEntry($entry1);

        $postedEntries = PartyLedgerEntry::posted()->where('party_id', $party->id)->get();

        $this->assertCount(1, $postedEntries);
        $this->assertEquals(2000, (float) $postedEntries->first()->debit_amount);
    }

    public function test_scope_for_party_filters_correctly(): void
    {
        $user = User::factory()->create();
        $party1 = Party::factory()->create(['user_id' => $user->id]);
        $party2 = Party::factory()->create(['user_id' => $user->id]);

        $this->service->postReceivable($party1, 1000);
        $this->service->postReceivable($party2, 2000);

        $entries = PartyLedgerEntry::forParty($party1->id)->get();

        $this->assertCount(1, $entries);
        $this->assertEquals($party1->id, $entries->first()->party_id);
    }

    public function test_signed_amount_calculates_correctly(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);

        $entry = $this->service->postReceivable($party, 1000);

        $this->assertEquals(1000, $entry->signedAmount());

        $collection = $this->service->postCollection($party, 400);

        $this->assertEquals(-400, $collection->signedAmount());
    }

    public function test_is_void_returns_true_for_voided_entries(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);

        $entry = $this->service->postReceivable($party, 1000);

        $this->assertFalse($entry->isVoid());

        $this->service->voidEntry($entry);

        $this->assertTrue($entry->fresh()->isVoid());
    }

    public function test_legal_entity_id_from_another_user_is_rejected(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user1->id]);
        $legalEntity = LegalEntity::create(['user_id' => $user2->id, 'name' => 'Other LE', 'tax_number' => '9999999999']);

        $this->expectException(InvalidArgumentException::class);
        $this->service->postReceivable($party, 1000, [
            'legal_entity_id' => $legalEntity->id,
        ]);
    }

    public function test_crm_contact_id_from_another_user_is_rejected(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user1->id]);
        $crmContact = CrmContact::create(['user_id' => $user2->id, 'display_name' => 'Other Contact', 'status' => 'active']);

        $this->expectException(InvalidArgumentException::class);
        $this->service->postReceivable($party, 1000, [
            'crm_contact_id' => $crmContact->id,
        ]);
    }

    public function test_legal_entity_id_from_same_user_is_written(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);
        $legalEntity = LegalEntity::create(['user_id' => $user->id, 'name' => 'My LE', 'tax_number' => '1234567890']);

        $entry = $this->service->postReceivable($party, 1000, [
            'legal_entity_id' => $legalEntity->id,
        ]);

        $this->assertEquals($legalEntity->id, $entry->legal_entity_id);
    }

    public function test_crm_contact_id_from_same_user_is_written(): void
    {
        $user = User::factory()->create();
        $party = Party::factory()->create(['user_id' => $user->id]);
        $crmContact = CrmContact::create(['user_id' => $user->id, 'display_name' => 'My Contact', 'status' => 'active']);

        $entry = $this->service->postReceivable($party, 1000, [
            'crm_contact_id' => $crmContact->id,
        ]);

        $this->assertEquals($crmContact->id, $entry->crm_contact_id);
    }

    public function test_party_factory_only_produces_valid_party_types(): void
    {
        $validTypes = ['person', 'organization', 'unknown'];

        for ($i = 0; $i < 50; $i++) {
            $party = Party::factory()->make();
            $this->assertContains($party->party_type, $validTypes, "Invalid party_type: {$party->party_type}");
        }
    }
}
