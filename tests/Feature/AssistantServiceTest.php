<?php

namespace Tests\Feature;

use App\Models\Party;
use App\Models\User;
use App\Services\Accounting\AssistantService;
use App\Services\Accounting\OutstandingInvoiceService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssistantServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Party $party;
    private AssistantService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['is_active' => true]);
        $this->party = Party::factory()->create(['user_id' => $this->user->id]);

        $seeder = new ChartOfAccountsSeeder();
        $seeder->runForUser($this->user->id);

        $this->service = app(AssistantService::class);
    }

    public function test_ask_assistant_returns_cash_flow_details(): void
    {
        // 1. Seed collection + receivable to populate forecast data
        $invoice = app(OutstandingInvoiceService::class);
        $invoice->recordCollection([
            'user_id'         => $this->user->id,
            'party_id'        => $this->party->id,
            'amount'          => 5000.00,
            'collection_date' => now()->toDateString(),
        ]);

        // 2. Ask assistant
        $query = $this->service->askAssistant($this->user->id, 'nakit akışım ve kasa durumum nedir?');

        $this->assertDatabaseHas('assistant_queries', [
            'id' => $query->id,
            'status' => 'completed',
        ]);

        $this->assertStringContainsString('5.000,00', $query->response_text);
        $this->assertEquals('cash_flow', $query->meta_json['report']);
    }

    public function test_ask_assistant_returns_receivables_aging_details(): void
    {
        $invoice = app(OutstandingInvoiceService::class);
        $invoice->createReceivable([
            'user_id'       => $this->user->id,
            'party_id'      => $this->party->id,
            'amount'        => 750.00,
            'document_date' => now()->subDays(10)->toDateString(),
            'due_date'      => now()->subDays(5)->toDateString(), // 5 days overdue (bucket 0-30)
        ]);

        $query = $this->service->askAssistant($this->user->id, 'vadesi geçmiş alacak yaşlandırmasını getir');

        $this->assertStringContainsString('750,00', $query->response_text);
        $this->assertEquals('aged_receivables', $query->meta_json['report']);
    }

    public function test_fallback_message_on_unknown_query(): void
    {
        $query = $this->service->askAssistant($this->user->id, 'havayı nasıl görüyorsun?');

        $this->assertStringContainsString('sorunuzu tam olarak anlayamadım', $query->response_text);
        $this->assertTrue($query->meta_json['fallback']);
    }
}
