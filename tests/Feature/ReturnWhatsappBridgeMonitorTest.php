<?php

namespace Tests\Feature;

use App\Jobs\AnalyzeReturnIntakeItemJob;
use App\Livewire\Returns\ReturnWhatsappBridge;
use App\Models\ReturnBridgeSetting;
use App\Models\ReturnIntakeBatch;
use App\Models\ReturnIntakeItem;
use App\Models\ReturnWhatsappThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Tests\TestCase;

class ReturnWhatsappBridgeMonitorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_whatsapp_bridge_screen_for_authorized_user(): void
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
            ->followingRedirects()
            ->get(route('returns.whatsapp-bridge'))
            ->assertOk()
            ->assertSee('İade Merkezi')
            ->assertSee('Ramazan Depocu');
    }

    public function test_it_can_requeue_analysis_from_monitor_screen(): void
    {
        Bus::fake();

        $user = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $batch = ReturnIntakeBatch::create([
            'user_id' => $user->id,
            'source' => 'whatsapp_bridge',
            'intake_mode' => 'undamaged',
            'status' => 'submitted',
            'captured_at' => now(),
        ]);

        $item = ReturnIntakeItem::create([
            'batch_id' => $batch->id,
            'submitted_by_user_id' => $user->id,
            'intake_type' => 'undamaged',
            'intake_status' => 'ready_for_decision',
            'condition_status' => 'undamaged',
            'decision_status' => 'pending',
            'arrived_at' => now(),
        ]);

        $thread = ReturnWhatsappThread::create([
            'provider' => 'meta_cloud_api',
            'sender_phone' => '905551112233',
            'sender_name' => 'Ramazan Depocu',
            'status' => 'collecting',
            'last_message_at' => now(),
            'return_intake_batch_id' => $batch->id,
            'return_intake_item_id' => $item->id,
        ]);

        $this->actingAs($user);

        Livewire::test(ReturnWhatsappBridge::class)
            ->call('selectThread', $thread->id)
            ->call('dispatchAnalysisNow')
            ->assertSet('messageType', 'success');

        $thread->refresh();
        $item->refresh();

        $this->assertSame('queued', $thread->status);
        $this->assertSame('queued', $item->intake_status);

        Bus::assertDispatched(AnalyzeReturnIntakeItemJob::class, function (AnalyzeReturnIntakeItemJob $job) use ($item) {
            return $job->returnIntakeItemId === $item->id;
        });
    }

    public function test_manager_can_save_whatsapp_bridge_settings_from_screen(): void
    {
        $manager = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $systemUser = User::factory()->create([
            'role' => 'operator',
            'is_active' => true,
        ]);

        $this->actingAs($manager);

        Livewire::test(ReturnWhatsappBridge::class)
            ->set('settingsForm.enabled', true)
            ->set('settingsForm.system_user_id', (string) $systemUser->id)
            ->set('settingsForm.verify_token', 'verify-token-123')
            ->set('settingsForm.access_token', 'access-token-xyz')
            ->set('settingsForm.app_secret', 'secret-456')
            ->set('settingsForm.graph_version', 'v23.0')
            ->set('settingsForm.message_window_minutes', 12)
            ->call('saveBridgeSettings')
            ->assertSet('messageType', 'success');

        $setting = ReturnBridgeSetting::query()->first();

        $this->assertNotNull($setting);
        $this->assertTrue((bool) $setting->whatsapp_bridge_enabled);
        $this->assertSame($systemUser->id, $setting->system_user_id);
        $this->assertSame('verify-token-123', $setting->verify_token);
        $this->assertSame('access-token-xyz', $setting->access_token);
        $this->assertSame('secret-456', $setting->app_secret);
        $this->assertSame('v23.0', $setting->graph_version);
        $this->assertSame(12, $setting->message_window_minutes);
    }
}
