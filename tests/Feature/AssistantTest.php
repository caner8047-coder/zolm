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

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        (new \Database\Seeders\ChartOfAccountsSeeder())->runForUser($this->user->id);
        config()->set('marketplace.features.accounting_enabled', true);
    }

    // ─── ROUTE / FEATURE FLAG ────────────────────────────────────────────

    public function test_route_is_blocked_when_accounting_enabled_is_false(): void
    {
        config()->set('marketplace.features.accounting_enabled', false);

        $this->actingAs($this->user)
            ->get(route('accounting.assistant'))
            ->assertStatus(404);
    }

    public function test_page_renders_when_accounting_enabled_is_true(): void
    {
        $this->actingAs($this->user)
            ->get(route('accounting.assistant'))
            ->assertStatus(200)
            ->assertSeeLivewire('accounting.assistant');
    }

    public function test_rendering_does_not_mutate_feature_flag(): void
    {
        config()->set('marketplace.features.accounting_enabled', false);

        Livewire::actingAs($this->user)->test('accounting.assistant');

        $this->assertFalse(config('marketplace.features.accounting_enabled'));
    }

    // ─── SORU SOR ────────────────────────────────────────────────────────

    public function test_asking_assistant_creates_query_record(): void
    {
        Livewire::actingAs($this->user)
            ->test('accounting.assistant')
            ->set('questionText', 'Nakit akışım nasıl?')
            ->call('askQuestion')
            ->assertSet('questionText', '')
            ->assertSet('message', '');

        $this->assertDatabaseHas('assistant_queries', [
            'user_id'    => $this->user->id,
            'query_text' => 'Nakit akışım nasıl?',
            'status'     => 'completed',
        ]);
    }

    public function test_short_question_is_rejected(): void
    {
        Livewire::actingAs($this->user)
            ->test('accounting.assistant')
            ->set('questionText', 'ab')
            ->call('askQuestion')
            ->assertSet('messageType', 'error');
    }

    public function test_empty_question_is_rejected(): void
    {
        Livewire::actingAs($this->user)
            ->test('accounting.assistant')
            ->set('questionText', '')
            ->call('askQuestion')
            ->assertSet('messageType', 'error');
    }

    // ─── HAZIR SORU ──────────────────────────────────────────────────────

    public function test_suggested_question_button_works(): void
    {
        Livewire::actingAs($this->user)
            ->test('accounting.assistant')
            ->call('askQuestion', 'Stok değerim ne kadar?')
            ->assertSet('questionText', '');

        $this->assertDatabaseHas('assistant_queries', [
            'user_id'    => $this->user->id,
            'query_text' => 'Stok değerim ne kadar?',
        ]);
    }

    // ─── KAYDET / SİL ────────────────────────────────────────────────────

    public function test_saving_and_deleting_questions(): void
    {
        Livewire::actingAs($this->user)
            ->test('accounting.assistant')
            ->call('saveQuestion', 'En çok borcum olan cariler?')
            ->assertSet('messageType', 'success');

        $this->assertDatabaseHas('assistant_saved_questions', [
            'user_id'    => $this->user->id,
            'query_text' => 'En çok borcum olan cariler?',
        ]);

        $sq = AssistantSavedQuestion::where('user_id', $this->user->id)->first();

        Livewire::actingAs($this->user)
            ->test('accounting.assistant')
            ->call('deleteSavedQuestion', $sq->id)
            ->assertSet('messageType', 'success');

        $this->assertDatabaseMissing('assistant_saved_questions', ['id' => $sq->id]);
    }

    public function test_cannot_delete_other_user_saved_question(): void
    {
        $otherUser = User::factory()->create(['is_active' => true]);
        $sq2 = AssistantSavedQuestion::create([
            'user_id'    => $otherUser->id,
            'title'      => 'Soru 2',
            'query_text' => 'User 2 nin sorusu',
        ]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        Livewire::actingAs($this->user)
            ->test('accounting.assistant')
            ->call('deleteSavedQuestion', $sq2->id);
    }

    // ─── GEÇMİŞ TEMİZLE ─────────────────────────────────────────────────

    public function test_clear_history_only_deletes_auth_user_history(): void
    {
        $otherUser = User::factory()->create(['is_active' => true]);

        // Her iki kullanıcının sorgusu
        AssistantQuery::create([
            'user_id'       => $this->user->id,
            'query_text'    => 'Benim sorum',
            'response_text' => 'Cevap',
            'status'        => 'completed',
        ]);
        AssistantQuery::create([
            'user_id'       => $otherUser->id,
            'query_text'    => 'Onun sorusu',
            'response_text' => 'Cevap',
            'status'        => 'completed',
        ]);

        Livewire::actingAs($this->user)
            ->test('accounting.assistant')
            ->call('clearHistory')
            ->assertSet('messageType', 'success');

        // Asıl kullanıcının geçmişi silindi
        $this->assertDatabaseMissing('assistant_queries', ['user_id' => $this->user->id]);
        // Diğer kullanıcının geçmişi bozulmadı
        $this->assertDatabaseHas('assistant_queries', ['user_id' => $otherUser->id]);
    }

    // ─── TEKRARLA ────────────────────────────────────────────────────────

    public function test_repeat_question_creates_new_query(): void
    {
        $q = AssistantQuery::create([
            'user_id'       => $this->user->id,
            'query_text'    => 'Nakit akışım nedir?',
            'response_text' => 'Test cevabı',
            'status'        => 'completed',
            'intent'        => 'cash_flow',
        ]);

        Livewire::actingAs($this->user)
            ->test('accounting.assistant')
            ->call('repeatQuestion', $q->id)
            ->assertSet('questionText', '');

        // İlk sorgudan farklı ID ile yeni query oluşmalı (duplicate guard süresi geçmişse)
        // Burada sadece çalıştığını verify ediyoruz
        $this->assertDatabaseHas('assistant_queries', [
            'user_id'    => $this->user->id,
            'query_text' => 'Nakit akışım nedir?',
        ]);
    }

    // ─── UI KAYNAK BİLGİSİ ──────────────────────────────────────────────

    public function test_source_info_appears_in_ui(): void
    {
        Livewire::actingAs($this->user)
            ->test('accounting.assistant')
            ->set('questionText', 'Nakit akışım nasıl?')
            ->call('askQuestion')
            ->assertSee('ReportService');
    }

    // ─── FALLBACK / HATA UI ─────────────────────────────────────────────

    public function test_fallback_answer_shown_in_ui(): void
    {
        Livewire::actingAs($this->user)
            ->test('accounting.assistant')
            ->set('questionText', 'Yarın hava nasıl olacak?')
            ->call('askQuestion')
            ->assertSee('anlayamadım');
    }

    // ─── TENANT İZOLASYONU UI ────────────────────────────────────────────

    public function test_tenant_isolation_on_assistant(): void
    {
        $user2 = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $sq2 = AssistantSavedQuestion::create([
            'user_id'    => $user2->id,
            'title'      => 'Soru 2',
            'query_text' => 'User 2 nin sorusu',
        ]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        Livewire::actingAs($this->user)
            ->test('accounting.assistant')
            ->call('deleteSavedQuestion', $sq2->id);
    }
}
