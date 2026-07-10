<?php

namespace Tests\Feature;

use App\Models\AssistantQuery;
use App\Models\AssistantSavedQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_is_blocked_when_accounting_enabled_is_false(): void
    {
        config()->set('marketplace.features.accounting_enabled', false);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $this->actingAs($user)
            ->get(route('accounting.assistant'))
            ->assertStatus(404);
    }

    public function test_page_renders_when_accounting_enabled_is_true(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $this->actingAs($user)
            ->get(route('accounting.assistant'))
            ->assertStatus(200)
            ->assertSeeLivewire('accounting.assistant');
    }

    public function test_asking_assistant_queries_cash_flow(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        (new \Database\Seeders\ChartOfAccountsSeeder())->runForUser($user->id);

        Livewire::actingAs($user)
            ->test('accounting.assistant')
            ->set('questionText', 'Nakit akışım nasıl?')
            ->call('askQuestion')
            ->assertSet('questionText', '')
            ->assertSet('message', '');

        $this->assertDatabaseHas('assistant_queries', [
            'user_id' => $user->id,
            'query_text' => 'Nakit akışım nasıl?',
            'status' => 'completed',
        ]);
    }

    public function test_saving_and_deleting_questions(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        Livewire::actingAs($user)
            ->test('accounting.assistant')
            ->call('saveQuestion', 'En çok borcum olan cariler?')
            ->assertSet('messageType', 'success');

        $this->assertDatabaseHas('assistant_saved_questions', [
            'user_id' => $user->id,
            'query_text' => 'En çok borcum olan cariler?',
        ]);

        $sq = AssistantSavedQuestion::first();

        Livewire::actingAs($user)
            ->test('accounting.assistant')
            ->call('deleteSavedQuestion', $sq->id)
            ->assertSet('messageType', 'success');

        $this->assertDatabaseMissing('assistant_saved_questions', [
            'id' => $sq->id,
        ]);
    }

    public function test_tenant_isolation_on_assistant(): void
    {
        config()->set('marketplace.features.accounting_enabled', true);

        $user1 = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $user2 = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $sq2 = AssistantSavedQuestion::create([
            'user_id' => $user2->id,
            'title' => 'Soru 2',
            'query_text' => 'User 2 nin sorusu',
        ]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        // User 1 trying to delete User 2's saved question should fail
        Livewire::actingAs($user1)
            ->test('accounting.assistant')
            ->call('deleteSavedQuestion', $sq2->id);
    }
}
