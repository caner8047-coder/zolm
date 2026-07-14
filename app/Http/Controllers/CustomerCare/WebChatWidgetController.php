<?php

namespace App\Http\Controllers\CustomerCare;

use App\Http\Controllers\Controller;
use App\Models\SupportChannel;
use App\Models\SupportDispatch;
use App\Models\SupportMessage;
use App\Models\SupportWebLead;
use App\Models\SupportWidgetSession;
use App\Models\SupportConsentRecord;
use App\Services\Crm\CrmIdentityResolver;
use App\Services\Support\WebChatSupportChannelAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WebChatWidgetController extends Controller
{
    public function configuration(Request $request, string $publicKey): JsonResponse
    {
        $channel = $this->resolveChannel($publicKey);
        $origin = $this->validatedOrigin($request, $channel);
        $this->enforceRateLimit('configuration', $request, 60, 60);

        return $this->cors(response()->json(['widget' => $this->publicWidgetConfig($channel)]), $origin);
    }

    public function bootstrap(Request $request, string $publicKey): JsonResponse
    {
        $channel = $this->resolveChannel($publicKey);
        $origin = $this->validatedOrigin($request, $channel);
        $this->enforceRateLimit('session', $request, 20, 60);

        $webConfig = $channel->config_json['web_chat'] ?? [];
        $consentRequired = (bool) ($webConfig['consent_required'] ?? true);
        if ($consentRequired && !$request->boolean('consent')) {
            return $this->cors(response()->json([
                'error' => 'Sohbeti başlatmak için aydınlatma metnini kabul etmelisiniz.',
                'privacy_notice_version' => $webConfig['privacy_notice_version'] ?? 'v1',
                'privacy_notice_text' => $webConfig['privacy_notice_text'] ?? 'Mesajlarınız talebinizi yanıtlamak amacıyla işlenir.',
            ], 422), $origin);
        }

        $ttlMinutes = max(15, min(10080, (int) config('customer-care.web_chat_session_ttl_minutes', 1440)));
        $sessionId = Str::uuid()->toString();
        $sessionHash = hash('sha256', $sessionId . ':' . $channel->id . ':' . config('app.key'));
        $expiresAt = now()->addMinutes($ttlMinutes);
        $tokenPayload = [
            'channel_id' => $channel->id,
            'session_id' => $sessionId,
            'session_hash' => $sessionHash,
            'origin' => $origin,
            'expires_at' => $expiresAt->timestamp,
        ];
        $token = Crypt::encryptString(json_encode($tokenPayload, JSON_THROW_ON_ERROR));
        $noticeVersion = (string) ($webConfig['privacy_notice_version'] ?? 'v1');

        $session = SupportWidgetSession::create([
            'support_channel_id' => $channel->id,
            'session_hash' => $sessionHash,
            'token_hash' => hash('sha256', $token),
            'origin' => $origin,
            'consent_granted' => $request->boolean('consent'),
            'marketing_consent_granted' => $request->boolean('marketing_consent'),
            'privacy_notice_version' => $noticeVersion,
            'marketing_notice_version' => (string) ($webConfig['marketing_notice_version'] ?? $noticeVersion),
            'consented_at' => $request->boolean('consent') ? now() : null,
            'marketing_consented_at' => $request->boolean('marketing_consent') ? now() : null,
            'last_seen_at' => now(),
            'expires_at' => $expiresAt,
            'status' => 'active',
            'metadata_json' => [
                'ip_stored' => false,
                'user_agent_stored' => false,
                'context' => $this->validatedContext($request),
                'contact_preference' => in_array($request->input('lead.contact_preference'), ['email', 'phone', 'chat'], true)
                    ? $request->input('lead.contact_preference') : 'chat',
            ],
        ]);

        $lead = $this->createLeadIfRequested($request, $session, $channel);

        return $this->cors(response()->json([
            'token' => $token,
            'expires_at' => $expiresAt->toIso8601String(),
            'widget' => $this->publicWidgetConfig($channel),
            'lead_id' => $lead?->id,
        ]), $origin);
    }

    public function sendMessage(Request $request, string $publicKey, WebChatSupportChannelAdapter $adapter): JsonResponse
    {
        [$channel, $session, $payload, $origin] = $this->resolveAuthorizedSession($request, $publicKey);
        $this->enforceRateLimit('message:' . $session->id, $request, 30, 60);

        if (filled($request->input('website'))) {
            return $this->cors(response()->json(['error' => 'Mesaj doğrulanamadı.'], 422), $origin);
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:2000'],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:100', 'regex:/^[A-Za-z0-9._:-]+$/'],
        ]);

        $result = $adapter->projectTrustedWidgetMessage(
            $channel,
            $payload['session_hash'],
            $validated['idempotency_key'],
            $validated['body'],
            true,
        );

        if (!($result['success'] ?? false)) {
            return $this->cors(response()->json(['error' => $result['message'] ?? 'Mesaj alınamadı.'], 422), $origin);
        }

        $session->update([
            'conversation_id' => $result['conversation_id'],
            'last_seen_at' => now(),
        ]);
        if ($session->conversation_id) {
            $conversation = $session->conversation;
            $ref = $conversation->source_reference_json ?? [];
            $ref['widget_context'] = $session->metadata_json['context'] ?? [];
            $ref['contact_preference'] = $session->metadata_json['contact_preference'] ?? 'chat';
            $conversation->update(['source_reference_json' => $ref]);
        }
        $session->lead?->update(['conversation_id' => $result['conversation_id']]);

        return $this->cors(response()->json([
            'success' => true,
            'message_id' => $result['message_id'] ?? null,
            'projected' => $result['projected'] ?? false,
        ], 202), $origin);
    }

    public function uploadAttachment(Request $request, string $publicKey): JsonResponse
    {
        [$channel, $session, $payload, $origin] = $this->resolveAuthorizedSession($request, $publicKey);
        $this->enforceRateLimit('attachment:' . $session->id, $request, 10, 60);
        abort_unless($channel->hasCapability('attachments'), 422, 'Bu kanalda dosya ekine izin verilmiyor.');
        abort_unless($session->conversation_id, 422, 'Önce bir mesaj göndererek sohbeti başlatın.');
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:2048', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf'],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:100', 'regex:/^[A-Za-z0-9._:-]+$/'],
        ]);
        $externalId = 'web_chat_attachment_' . hash('sha256', $payload['session_hash'] . ':' . $validated['idempotency_key']);
        $existing = SupportMessage::where('conversation_id', $session->conversation_id)->where('external_message_id', $externalId)->first();
        if ($existing) return $this->cors(response()->json(['success' => true, 'message_id' => $existing->id, 'projected' => false], 202), $origin);

        $file = $validated['file'];
        $path = 'customer-care/attachments/' . $channel->store_id . '/' . Str::uuid() . '.enc';
        $encrypted = Crypt::encryptString(base64_encode($file->get()));
        abort_unless(Storage::disk('local')->put($path, $encrypted), 500, 'Dosya güvenli depoya yazılamadı.');
        $message = SupportMessage::create([
            'conversation_id' => $session->conversation_id,
            'external_message_id' => $externalId,
            'direction' => 'inbound',
            'sender_type' => 'customer',
            'message_type' => 'attachment',
            'body_encrypted' => 'Müşteri güvenli bir dosya eki gönderdi.',
            'body_preview' => 'Güvenli dosya eki',
            'payload_json' => ['encrypted_path' => $path, 'mime' => $file->getMimeType(), 'size' => $file->getSize(), 'original_name' => app(\App\Services\Support\Security\PiiRedactor::class)->maskPii($file->getClientOriginalName())],
            'received_at' => now(),
            'delivery_status' => 'received',
        ]);
        $session->conversation->update(['last_message_at' => now(), 'last_inbound_at' => now()]);
        return $this->cors(response()->json(['success' => true, 'message_id' => $message->id, 'projected' => true], 202), $origin);
    }

    public function requestHandoff(Request $request, string $publicKey, WebChatSupportChannelAdapter $adapter): JsonResponse
    {
        [$channel, $session, $payload, $origin] = $this->resolveAuthorizedSession($request, $publicKey);
        $this->enforceRateLimit('handoff:' . $session->id, $request, 5, 60);
        if (!$session->conversation_id) {
            $projected = $adapter->projectTrustedWidgetMessage($channel, $payload['session_hash'], 'handoff-' . Str::uuid(), 'İnsan temsilciyle görüşmek istiyorum.', true);
            abort_unless($projected['success'] ?? false, 422, 'Temsilci talebi oluşturulamadı.');
            $session->update(['conversation_id' => $projected['conversation_id']]);
        }
        app(\App\Services\Support\CustomerCareHandoffService::class)->handoff(
            $session->conversation()->firstOrFail(),
            'Müşteri web widget üzerinden insan temsilci istedi.',
            'medium',
            [],
            'Müşteri sessiz ve kesintisiz temsilci devri talep etti. İletişim tercihi: ' . ($session->metadata_json['contact_preference'] ?? 'chat'),
        );
        return $this->cors(response()->json(['success' => true, 'status' => 'pending', 'message' => 'Talebiniz temsilci kuyruğuna alındı.']), $origin);
    }

    public function poll(Request $request, string $publicKey): JsonResponse
    {
        [, $session, , $origin] = $this->resolveAuthorizedSession($request, $publicKey);
        $this->enforceRateLimit('poll:' . $session->id, $request, 120, 60);
        $afterId = max(0, (int) $request->query('after_id', 0));

        if (!$session->conversation_id) {
            return $this->cors(response()->json(['messages' => [], 'cursor' => $afterId]), $origin);
        }

        $messages = SupportMessage::where('conversation_id', $session->conversation_id)
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit(100)
            ->get()
            ->map(fn (SupportMessage $message) => [
                'id' => $message->id,
                'direction' => $message->direction,
                'sender_type' => $message->sender_type,
                'message_type' => $message->message_type,
                'body' => $message->body_encrypted,
                'status' => $message->delivery_status,
                'created_at' => $message->created_at?->toIso8601String(),
            ]);
        $cursor = (int) ($messages->max('id') ?: $afterId);
        $session->update(['last_seen_at' => now()]);

        return $this->cors(response()->json(['messages' => $messages->values(), 'cursor' => $cursor]), $origin);
    }

    public function acknowledge(Request $request, string $publicKey): JsonResponse
    {
        [, $session, , $origin] = $this->resolveAuthorizedSession($request, $publicKey);
        $validated = $request->validate(['message_ids' => ['required', 'array', 'max:100'], 'message_ids.*' => ['integer']]);

        if ($session->conversation_id) {
            $ids = SupportMessage::where('conversation_id', $session->conversation_id)
                ->where('direction', 'outbound')
                ->whereIn('id', $validated['message_ids'])
                ->pluck('id');
            SupportMessage::whereIn('id', $ids)->update(['delivery_status' => 'delivered', 'sent_at' => now()]);
            SupportDispatch::whereIn('message_id', $ids)->whereIn('status', ['queued', 'accepted'])->update(['status' => 'sent']);
        }

        return $this->cors(response()->json(['success' => true]), $origin);
    }

    public function preflight(Request $request, string $publicKey): JsonResponse
    {
        $channel = $this->resolveChannel($publicKey);
        $origin = $this->validatedOrigin($request, $channel);
        return $this->cors(response()->json(null, 204), $origin);
    }

    private function resolveAuthorizedSession(Request $request, string $publicKey): array
    {
        $channel = $this->resolveChannel($publicKey);
        $origin = $this->validatedOrigin($request, $channel);
        $token = $request->bearerToken() ?: (string) $request->input('token', $request->query('token', ''));
        abort_if($token === '', 401, 'Widget token zorunludur.');

        try {
            $payload = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            abort(401, 'Geçersiz widget token.');
        }

        abort_unless((int) ($payload['channel_id'] ?? 0) === (int) $channel->id, 403, 'Kanal eşleşmedi.');
        abort_unless(hash_equals((string) ($payload['origin'] ?? ''), $origin), 403, 'Origin eşleşmedi.');
        abort_if((int) ($payload['expires_at'] ?? 0) < now()->timestamp, 401, 'Widget oturumu sona erdi.');

        $session = SupportWidgetSession::where('support_channel_id', $channel->id)
            ->where('session_hash', $payload['session_hash'] ?? '')
            ->where('token_hash', hash('sha256', $token))
            ->where('origin', $origin)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->first();
        abort_unless($session, 401, 'Widget oturumu bulunamadı.');

        return [$channel, $session, $payload, $origin];
    }

    private function resolveChannel(string $publicKey): SupportChannel
    {
        abort_unless(
            config('customer-care.enabled', false) && config('customer-care.web_chat_enabled', false),
            404
        );
        return SupportChannel::with('store')
            ->where('public_key', $publicKey)
            ->where('key', 'web_chat')
            ->where('is_enabled', true)
            ->where('status', 'active')
            ->firstOrFail();
    }

    private function validatedOrigin(Request $request, SupportChannel $channel): string
    {
        $origin = strtolower(rtrim((string) $request->headers->get('Origin'), '/'));
        abort_if($origin === '' || filter_var($origin, FILTER_VALIDATE_URL) === false, 403, 'Geçerli Origin zorunludur.');
        $allowed = collect($channel->config_json['web_chat']['allowed_origins'] ?? [])
            ->filter(fn ($value) => is_string($value))
            ->map(fn ($value) => strtolower(rtrim($value, '/')));
        abort_unless($allowed->contains($origin), 403, 'Bu alan adı widget için izinli değil.');
        return $origin;
    }

    private function enforceRateLimit(string $scope, Request $request, int $maxAttempts, int $decaySeconds): void
    {
        $key = 'webchat:' . $scope . ':' . hash('sha256', (string) $request->ip());
        abort_unless(RateLimiter::attempt($key, $maxAttempts, fn () => true, $decaySeconds), 429, 'Çok fazla istek.');
    }

    private function createLeadIfRequested(Request $request, SupportWidgetSession $session, SupportChannel $channel): ?SupportWebLead
    {
        $lead = (array) $request->input('lead', []);
        $purpose = trim(strip_tags((string) ($lead['purpose'] ?? '')));
        if ($purpose === '') {
            return null;
        }

        $name = trim(strip_tags((string) ($lead['name'] ?? '')));
        $email = trim((string) ($lead['email'] ?? ''));
        $phone = trim((string) ($lead['phone'] ?? ''));
        $campaign = mb_substr(trim(strip_tags((string) ($lead['campaign'] ?? ''))), 0, 120);
        $idempotency = (string) ($lead['idempotency_key'] ?? $session->session_hash);
        abort_unless(preg_match('/^[A-Za-z0-9._:-]{8,100}$/', $idempotency) === 1, 422, 'Geçersiz lead idempotency anahtarı.');
        $idempotencyHash = hash('sha256', $idempotency);
        $existing = SupportWebLead::where('store_id', $channel->store_id)->where('idempotency_key_hash', $idempotencyHash)->first();
        if ($existing) {
            $existing->update([
                'support_widget_session_id' => $session->id,
                'marketing_consent_granted' => $session->marketing_consent_granted,
                'marketing_consented_at' => $session->marketing_consented_at,
            ]);
            return $existing;
        }
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            abort(422, 'Geçersiz e-posta adresi.');
        }

        $contact = app(CrmIdentityResolver::class)->resolve([
            'user_id' => $channel->store->user_id,
            'store_id' => $channel->store_id,
            'source_type' => 'web_chat',
            'external_customer_id' => 'web_chat_' . $idempotencyHash,
            'name' => $name ?: null,
            'email' => $email ?: null,
            'phone' => $phone ?: null,
            'confidence' => 100,
            'raw_payload' => null,
        ]);

        $webLead = SupportWebLead::create([
            'store_id' => $channel->store_id,
            'support_widget_session_id' => $session->id,
            'idempotency_key_hash' => $idempotencyHash,
            'crm_contact_id' => $contact->id,
            'name_encrypted' => $name ?: null,
            'email_encrypted' => $email ?: null,
            'phone_encrypted' => $phone ?: null,
            'purpose_encrypted' => mb_substr($purpose, 0, 500),
            'lead_source' => 'web_chat',
            'campaign' => $campaign ?: null,
            'conversation_summary_encrypted' => mb_substr($purpose, 0, 1000),
            'privacy_notice_version' => $session->privacy_notice_version,
            'consented_at' => $session->consented_at,
            'marketing_consent_granted' => $session->marketing_consent_granted,
            'marketing_consented_at' => $session->marketing_consented_at,
            'status' => 'new',
        ]);
        $consentCustomerId = 'web_chat_' . $idempotencyHash;
        SupportConsentRecord::updateOrCreate([
            'store_id' => $channel->store_id,
            'customer_hash' => hash('sha256', $consentCustomerId),
            'channel_key' => 'web_chat',
            'consent_type' => 'marketing',
        ], ['customer_id' => $consentCustomerId, 'status' => $session->marketing_consent_granted ? 'granted' : 'revoked', 'recorded_at' => now()]);
        return $webLead;
    }

    private function validatedContext(Request $request): array
    {
        $context = (array) $request->input('context', []);
        return collect(['product_id', 'sku', 'order_reference'])->mapWithKeys(function (string $key) use ($context) {
            $value = mb_substr(trim(strip_tags((string) ($context[$key] ?? ''))), 0, 120);
            return $value === '' ? [] : [$key => $value];
        })->all();
    }

    private function publicWidgetConfig(SupportChannel $channel): array
    {
        $config = $channel->config_json['web_chat'] ?? [];
        $color = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($config['primary_color'] ?? '')) ? $config['primary_color'] : '#0f172a';
        $logo = filter_var($config['logo_url'] ?? null, FILTER_VALIDATE_URL) && str_starts_with((string) $config['logo_url'], 'https://') ? $config['logo_url'] : null;
        return [
            'name' => mb_substr((string) ($config['assistant_name'] ?? $config['widget_name'] ?? $channel->store?->store_name ?? 'Destek'), 0, 80),
            'greeting' => mb_substr((string) ($config['greeting'] ?? 'Merhaba, nasıl yardımcı olabiliriz?'), 0, 300),
            'primary_color' => $color,
            'logo_url' => $logo,
            'popular_prompts' => collect($config['popular_prompts'] ?? ['Siparişim nerede?', 'Beden seçimi', 'İade koşulları'])->filter(fn ($v) => is_string($v))->take(6)->map(fn ($v) => mb_substr(strip_tags($v), 0, 100))->values(),
            'powered_by_visible' => (bool) ($config['powered_by_visible'] ?? true),
            'attachments_enabled' => $channel->hasCapability('attachments'),
            'privacy_notice_version' => (string) ($config['privacy_notice_version'] ?? 'v1'),
            'privacy_notice_text' => mb_substr((string) ($config['privacy_notice_text'] ?? 'Mesajlarınız talebinizi yanıtlamak amacıyla işlenir.'), 0, 2000),
            'marketing_notice_text' => mb_substr((string) ($config['marketing_notice_text'] ?? 'Kampanya ve duyurular için ayrıca iletişim izni veriyorum.'), 0, 1000),
        ];
    }

    private function cors(JsonResponse $response, string $origin): JsonResponse
    {
        return $response->withHeaders([
            'Access-Control-Allow-Origin' => $origin,
            'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
            'Access-Control-Allow-Headers' => 'Authorization, Content-Type',
            'Vary' => 'Origin',
            'Cache-Control' => 'no-store',
        ]);
    }
}
