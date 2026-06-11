<?php

namespace Tests\Feature;

use App\Livewire\CrmWorkspace;
use App\Models\CrmCase;
use App\Models\CrmContact;
use App\Models\CrmNote;
use App\Models\CrmTimelineEvent;
use App\Models\SupplyOrder;
use App\Models\User;
use App\Services\Crm\CrmIdentityResolver;
use App\Services\Crm\CrmProjectionService;
use App\Services\Crm\CrmAlertRuleService;
use App\Services\Crm\CrmCustomerSnapshotService;
use App\Services\Crm\CrmSourceLinkService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class CrmWorkspaceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql.host', 'mysql');
        config()->set('database.connections.mysql.port', '3306');
        config()->set('database.connections.mysql.database', 'zolm');
        config()->set('database.connections.mysql.username', 'sail');
        config()->set('database.connections.mysql.password', 'password');
        DB::purge('mysql');
        DB::reconnect('mysql');
        DB::setDefaultConnection('mysql');
    }

    public function test_crm_workspace_renders_contact_and_accepts_internal_note(): void
    {
        $user = User::factory()->create([
            'role' => 'operator',
            'is_active' => true,
        ]);
        $contact = CrmContact::query()->create([
            'user_id' => $user->id,
            'display_name' => 'Ayşe CRM Test',
            'normalized_name' => 'ayşe crm test',
            'primary_phone' => '5321112233',
            'normalized_phone' => '5321112233',
            'city' => 'İstanbul',
            'last_event_at' => now(),
            'last_event_type' => 'order',
            'last_event_title' => 'Sipariş #CRM-1',
            'order_count' => 1,
            'gross_revenue_total' => 1250,
            'open_case_count' => 1,
            'risk_score' => 42,
            'value_score' => 30,
        ]);
        CrmTimelineEvent::query()->create([
            'user_id' => $user->id,
            'contact_id' => $contact->id,
            'event_key' => 'test-order-crm-1',
            'event_type' => 'order',
            'source_type' => 'marketplace_orders',
            'title' => 'Sipariş #CRM-1',
            'occurred_at' => now(),
        ]);
        CrmCase::query()->create([
            'user_id' => $user->id,
            'contact_id' => $contact->id,
            'source_type' => 'marketplace_questions',
            'category' => 'message',
            'priority' => 'normal',
            'status' => 'open',
            'case_key' => 'test-question-crm-1',
            'title' => 'Yanıt bekleyen müşteri sorusu',
        ]);

        $this->actingAs($user);

        Livewire::test(CrmWorkspace::class)
            ->assertSee('Müşteri 360 Merkezi')
            ->assertSee('Ayşe CRM Test')
            ->call('selectContact', $contact->id)
            ->set('noteBody', 'Müşteri arandı, dönüş bekleniyor.')
            ->call('addNote')
            ->assertSet('workspaceMessageTone', 'success');

        $this->assertDatabaseHas('crm_notes', [
            'user_id' => $user->id,
            'contact_id' => $contact->id,
            'body' => 'Müşteri arandı, dönüş bekleniyor.',
        ]);
        $this->assertSame(1, CrmNote::query()->where('contact_id', $contact->id)->count());
    }

    public function test_identity_resolver_merges_contacts_by_normalized_phone(): void
    {
        $user = User::factory()->create([
            'role' => 'operator',
            'is_active' => true,
        ]);

        $first = app(CrmIdentityResolver::class)->resolve([
            'user_id' => $user->id,
            'source_type' => 'order_customer',
            'name' => 'Telefon Test',
            'phone' => '0532 111 22 33',
            'confidence' => 96,
        ]);
        $second = app(CrmIdentityResolver::class)->resolve([
            'user_id' => $user->id,
            'source_type' => 'question_customer',
            'name' => 'Telefon Test',
            'phone' => '+90 532 111 22 33',
            'confidence' => 92,
        ]);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, CrmContact::query()->where('user_id', $user->id)->count());
    }

    public function test_crm_workspace_can_open_from_source_deep_link(): void
    {
        $user = User::factory()->create([
            'role' => 'operator',
            'is_active' => true,
        ]);
        $contact = CrmContact::query()->create([
            'user_id' => $user->id,
            'display_name' => 'Deep Link Müşteri',
            'normalized_name' => 'deep link müşteri',
            'last_event_at' => now(),
            'last_event_type' => 'supply',
            'last_event_title' => 'Tedarik/üretim siparişi',
        ]);
        $order = SupplyOrder::query()->create([
            'siparis_no' => 'CRM-DEEP-' . random_int(100000, 999999),
            'kayit_tarihi' => now()->toDateString(),
            'musteri_adi' => 'Deep Link Müşteri',
            'urun_adi' => 'Deep ürün',
            'durum' => 'bekliyor',
            'sebebiyet' => 'yok',
        ]);
        CrmTimelineEvent::query()->create([
            'user_id' => $user->id,
            'contact_id' => $contact->id,
            'event_key' => 'supply-order:' . $order->id,
            'event_type' => 'supply',
            'source_type' => 'supply_reports',
            'subject_type' => $order::class,
            'subject_id' => $order->id,
            'title' => 'Tedarik/üretim siparişi',
            'occurred_at' => now(),
        ]);

        $this->actingAs($user);

        Livewire::withQueryParams([
            'source' => 'supply',
            'sourceId' => $order->id,
        ])
            ->test(CrmWorkspace::class)
            ->assertSet('selectedContactId', $contact->id)
            ->assertSee('Deep Link Müşteri')
            ->assertSee('Aksiyon Merkezi')
            ->assertSee('Tedarik raporunda aç');

        $event = CrmTimelineEvent::query()
            ->where('event_key', 'supply-order:' . $order->id)
            ->firstOrFail();
        $action = app(CrmSourceLinkService::class)->actionForTimelineEvent($event);

        $this->assertNotNull($action);
        $this->assertSame('Tedarik raporunda aç', $action['label']);
        $this->assertStringContainsString('/supply-reports', $action['url']);
        $this->assertStringContainsString('search=' . urlencode($order->siparis_no), $action['url']);

        $snapshot = app(CrmCustomerSnapshotService::class)->forSubject($user, 'supply', $order);

        $this->assertNotNull($snapshot);
        $this->assertSame($contact->id, $snapshot['contact_id']);
        $this->assertSame('Deep Link Müşteri', $snapshot['display_name']);
        $this->assertStringContainsString('/crm?contact=' . $contact->id, $snapshot['url']);
    }

    public function test_projection_can_be_limited_by_source_and_since_date(): void
    {
        $user = User::factory()->create([
            'role' => 'operator',
            'is_active' => true,
        ]);
        $suffix = (string) random_int(100000, 999999);
        $oldOrder = SupplyOrder::query()->create([
            'siparis_no' => 'CRM-OLD-' . $suffix,
            'kayit_tarihi' => now()->subDays(10)->toDateString(),
            'musteri_adi' => 'Eski CRM Müşteri',
            'urun_adi' => 'Eski ürün',
            'durum' => 'bekliyor',
            'sebebiyet' => 'yok',
        ]);
        $oldOrder->timestamps = false;
        $oldOrder->forceFill([
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ])->save();

        $recentOrder = SupplyOrder::query()->create([
            'siparis_no' => 'CRM-NEW-' . $suffix,
            'kayit_tarihi' => now()->toDateString(),
            'musteri_adi' => 'Yeni CRM Müşteri',
            'telefon' => '05321112233',
            'urun_adi' => 'Yeni ürün',
            'durum' => 'bekliyor',
            'sebebiyet' => 'yok',
        ]);

        app(CrmProjectionService::class)->projectUser($user, [
            'sources' => ['supply'],
            'since' => now()->subDay(),
        ]);

        $this->assertDatabaseHas('crm_timeline_events', [
            'user_id' => $user->id,
            'event_key' => 'supply-order:' . $recentOrder->id,
            'source_type' => 'supply_reports',
        ]);
        $this->assertDatabaseMissing('crm_timeline_events', [
            'user_id' => $user->id,
            'event_key' => 'supply-order:' . $oldOrder->id,
        ]);
    }

    public function test_crm_alert_rules_create_and_resolve_cross_module_cases(): void
    {
        $user = User::factory()->create([
            'role' => 'operator',
            'is_active' => true,
        ]);
        $contact = CrmContact::query()->create([
            'user_id' => $user->id,
            'display_name' => 'Çapraz Risk Müşteri',
            'normalized_name' => 'çapraz risk müşteri',
            'last_event_at' => now(),
            'risk_score' => 54,
            'value_score' => 20,
        ]);

        foreach ([
            ['source_type' => 'supply_reports', 'category' => 'supply', 'case_key' => 'test-supply-risk'],
            ['source_type' => 'marketplace_questions', 'category' => 'message', 'case_key' => 'test-message-risk'],
            ['source_type' => 'cargo_reports', 'category' => 'cargo', 'case_key' => 'test-cargo-risk'],
        ] as $case) {
            CrmCase::query()->create([
                'user_id' => $user->id,
                'contact_id' => $contact->id,
                'source_type' => $case['source_type'],
                'category' => $case['category'],
                'priority' => 'normal',
                'status' => 'open',
                'case_key' => $case['case_key'] . ':' . $contact->id,
                'title' => 'Operasyon vakası',
            ]);
        }

        $summary = app(CrmAlertRuleService::class)->runForUser($user);

        $this->assertSame(2, $summary['created']);
        $this->assertDatabaseHas('crm_cases', [
            'user_id' => $user->id,
            'contact_id' => $contact->id,
            'source_type' => 'crm',
            'category' => 'crm_alert',
            'case_key' => 'crm-alert:multi-pressure:' . $contact->id,
            'status' => 'open',
            'title' => 'Çoklu operasyon baskısı',
        ]);
        $this->assertDatabaseHas('crm_cases', [
            'user_id' => $user->id,
            'contact_id' => $contact->id,
            'case_key' => 'crm-alert:supply-experience:' . $contact->id,
            'status' => 'open',
        ]);

        $secondRun = app(CrmAlertRuleService::class)->runForUser($user);

        $this->assertSame(0, $secondRun['created']);
        $this->assertSame(2, CrmCase::query()
            ->where('user_id', $user->id)
            ->where('contact_id', $contact->id)
            ->where('source_type', 'crm')
            ->where('category', 'crm_alert')
            ->where('status', 'open')
            ->count());

        CrmCase::query()
            ->where('user_id', $user->id)
            ->where('contact_id', $contact->id)
            ->where('source_type', '!=', 'crm')
            ->update(['status' => 'resolved', 'resolved_at' => now()]);

        $resolvedSummary = app(CrmAlertRuleService::class)->runForUser($user);

        $this->assertSame(2, $resolvedSummary['resolved']);
        $this->assertSame(0, CrmCase::query()
            ->where('user_id', $user->id)
            ->where('contact_id', $contact->id)
            ->where('source_type', 'crm')
            ->where('category', 'crm_alert')
            ->where('status', 'open')
            ->count());
    }
}
