<?php

namespace Tests\Feature;

use App\Models\ReturnWhatsappThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReturnWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_unified_returns_workspace_for_authorized_user(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        ReturnWhatsappThread::create([
            'provider' => 'meta_cloud_api',
            'sender_phone' => '905551112233',
            'sender_name' => 'Ramazan Depocu',
            'status' => 'collecting',
            'last_message_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('returns.workspace'))
            ->assertOk()
            ->assertSee('İade Merkezi')
            ->assertSee('İade Kabul Formu')
            ->assertSee('Akıllı İade Merkezi')
            ->assertSee('WhatsApp Köprüsü');
    }

    public function test_it_renders_whatsapp_panel_when_whatsapp_tab_is_requested(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        ReturnWhatsappThread::create([
            'provider' => 'meta_cloud_api',
            'sender_phone' => '905551112233',
            'sender_name' => 'Ramazan Depocu',
            'status' => 'collecting',
            'last_message_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('returns.workspace', ['tab' => 'whatsapp']))
            ->assertOk()
            ->assertSee('WhatsApp İade Köprüsü')
            ->assertSee('1 dakikada bağla')
            ->assertSee('Gelen oturumlar')
            ->assertSee('Ramazan Depocu');
    }

    public function test_legacy_return_routes_redirect_to_workspace_sections(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('returns.intake'))
            ->assertRedirect(route('returns.workspace', ['tab' => 'kabul']));

        $this->actingAs($user)
            ->get(route('returns.center', ['item' => 12]))
            ->assertRedirect(route('returns.workspace', ['item' => 12, 'tab' => 'havuz']));

        $this->actingAs($user)
            ->get(route('returns.whatsapp-bridge', ['thread' => 7]))
            ->assertRedirect(route('returns.workspace', ['thread' => 7, 'tab' => 'whatsapp']));
    }
}
