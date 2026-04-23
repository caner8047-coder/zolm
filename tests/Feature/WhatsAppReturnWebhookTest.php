<?php

namespace Tests\Feature;

use App\Models\ReturnIntakeItem;
use App\Models\ReturnWhatsappMessage;
use App\Models\ReturnWhatsappThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WhatsAppReturnWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_verifies_whatsapp_webhook_subscription(): void
    {
        config()->set('returns.whatsapp_bridge_enabled', true);
        config()->set('returns.whatsapp_verify_token', 'zolm-token');

        $response = $this->get('/api/webhooks/returns/whatsapp?hub.mode=subscribe&hub.verify_token=zolm-token&hub.challenge=abc123');

        $response->assertOk();
        $response->assertSeeText('abc123');
    }

    public function test_it_imports_whatsapp_image_messages_into_return_intake(): void
    {
        Storage::fake('public');
        Bus::fake();
        Http::fake([
            'https://graph.facebook.com/*/MID-1' => Http::response([
                'url' => 'https://lookaside.fbsbx.com/whatsapp_business/attachments/file-1',
                'mime_type' => 'image/jpeg',
            ], 200),
            'https://lookaside.fbsbx.com/*' => Http::response(
                $this->fakeJpegBinary(),
                200,
                ['Content-Type' => 'image/jpeg']
            ),
        ]);

        $user = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        config()->set('returns.whatsapp_bridge_enabled', true);
        config()->set('returns.whatsapp_access_token', 'meta-token');
        config()->set('returns.whatsapp_bridge_system_user_id', $user->id);

        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'metadata' => [
                            'phone_number_id' => 'PHONE-1',
                            'display_phone_number' => '905551112233',
                        ],
                        'contacts' => [[
                            'wa_id' => '905551112233',
                            'profile' => ['name' => 'Ramazan Depocu'],
                        ]],
                        'messages' => [[
                            'id' => 'wamid.HBgM1',
                            'from' => '905551112233',
                            'timestamp' => (string) now()->timestamp,
                            'type' => 'image',
                            'image' => [
                                'id' => 'MID-1',
                                'mime_type' => 'image/jpeg',
                                'caption' => 'hasarsiz etiket barkod',
                            ],
                        ]],
                    ],
                ]],
            ]],
        ];

        $response = $this->postJson('/api/webhooks/returns/whatsapp', $payload);

        $response->assertOk();
        $response->assertJsonPath('summary.messages', 1);

        $thread = ReturnWhatsappThread::query()->firstOrFail();
        $message = ReturnWhatsappMessage::query()->firstOrFail();
        $item = ReturnIntakeItem::query()->with('media')->firstOrFail();

        $this->assertSame('905551112233', $thread->sender_phone);
        $this->assertSame('undamaged', $item->intake_type);
        $this->assertCount(1, $item->media);
        $this->assertSame('label', $item->media->first()->kind);
        $this->assertNotNull($message->return_intake_media_id);
        Storage::disk('public')->assertExists($item->media->first()->path);
    }

    protected function fakeJpegBinary(): string
    {
        $image = imagecreatetruecolor(24, 24);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);

        imagefill($image, 0, 0, $white);
        imageline($image, 2, 2, 22, 22, $black);

        ob_start();
        imagejpeg($image, null, 85);
        $binary = (string) ob_get_clean();
        imagedestroy($image);

        return $binary;
    }
}
