<?php

namespace App\Services\Support;

use App\Models\SupportChannel;
use App\Services\Support\TenantContext;

class BrandVoiceService
{
    /**
     * Kanalın marka sesi ve prompt bağlamını döner.
     */
    public function getBrandVoice(SupportChannel $channel, ?\App\Models\User $user = null): array
    {
        $user = $user ?? auth()->user();
        if (!$user) {
            $user = TenantContext::getSystemActor();
        }

        TenantContext::enforceStoreAccess($channel->store_id, $user);

        $config = $channel->config_json ?? [];

        if (config('customer-care.release_center_enabled', false)) {
            $latestVersion = \App\Models\SupportArtifactVersion::where('store_id', $channel->store_id)
                ->where('artifact_type', 'brand_voice')
                ->where('is_current', true)
                ->first();
            if ($latestVersion && is_array($latestVersion->content_json)) {
                // If it was nested or flat, merge correctly
                $config = array_merge($config, $latestVersion->content_json);
                if (isset($latestVersion->content_json['brand_voice']) && is_array($latestVersion->content_json['brand_voice'])) {
                    $config['brand_voice'] = array_merge($config['brand_voice'] ?? [], $latestVersion->content_json['brand_voice']);
                }
            }
        }

        return [
            'tone' => $config['brand_voice']['tone'] ?? 'kibar ve yardımsever',
            'prompt_context' => $config['brand_voice']['prompt_context'] ?? 'Müşteri hizmetleri asistanısınız.',
            'return_policy' => $config['brand_voice']['return_policy'] ?? 'Genel e-ticaret iade kuralları geçerlidir.',
            'hitap' => $config['brand_voice']['hitap'] ?? 'siz',
            'use_emoji' => filter_var($config['brand_voice']['use_emoji'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'greeting' => $config['brand_voice']['greeting'] ?? 'Merhaba,',
            'signature' => $config['brand_voice']['signature'] ?? 'ZOLM Destek Ekibi',
            'sample_response' => $config['brand_voice']['sample_response'] ?? '',
            'response_length' => $config['brand_voice']['response_length'] ?? 'medium',
            'emoji_level' => $config['brand_voice']['emoji_level'] ?? (($config['brand_voice']['use_emoji'] ?? true) ? 'normal' : 'none'),
            'preferred_expressions' => $config['brand_voice']['preferred_expressions'] ?? [],
            'forbidden_expressions' => $config['brand_voice']['forbidden_expressions'] ?? [],
            'complaint_tone' => $config['brand_voice']['complaint_tone'] ?? 'sakin, empatik ve çözüm odaklı',
            'sales_tone' => $config['brand_voice']['sales_tone'] ?? 'bilgilendirici ve baskısız',
            'crisis_tone' => $config['brand_voice']['crisis_tone'] ?? 'net, sorumluluk sahibi ve insana devir odaklı',
            'language_rules' => $config['brand_voice']['language_rules'] ?? ['tr' => ['forbidden_expressions' => [], 'examples' => []]],
        ];
    }

    /**
     * Kanalın marka sesi ayarlarını günceller.
     */
    public function updateBrandVoice(SupportChannel $channel, array $brandVoiceData, ?\App\Models\User $user = null): void
    {
        $user = $user ?? auth()->user();
        if (!$user) {
            $user = TenantContext::getSystemActor();
        }

        TenantContext::enforceStoreAccess($channel->store_id, $user);

        // PII Masking
        $redactor = app(\App\Services\Support\Security\PiiRedactor::class);

        $tone = $redactor->maskPii(trim(strip_tags($brandVoiceData['tone'] ?? 'kibar ve yardımsever')));
        $promptContext = $redactor->maskPii(trim(strip_tags($brandVoiceData['prompt_context'] ?? 'Müşteri hizmetleri asistanısınız.')));
        $returnPolicy = $redactor->maskPii(trim(strip_tags($brandVoiceData['return_policy'] ?? 'Genel e-ticaret iade kuralları geçerlidir.')));
        $hitap = $redactor->maskPii(trim(strip_tags($brandVoiceData['hitap'] ?? 'siz')));
        $useEmoji = filter_var($brandVoiceData['use_emoji'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $greeting = $redactor->maskPii(trim(strip_tags($brandVoiceData['greeting'] ?? 'Merhaba,')));
        $signature = $redactor->maskPii(trim(strip_tags($brandVoiceData['signature'] ?? 'ZOLM Destek Ekibi')));
        $sampleResponse = $redactor->maskPii(trim(strip_tags($brandVoiceData['sample_response'] ?? '')));
        $requestedResponseLength = $brandVoiceData['response_length'] ?? 'medium';
        $responseLength = in_array($requestedResponseLength, ['short', 'medium', 'long'], true)
            ? $requestedResponseLength : 'medium';
        $emojiLevel = in_array(($brandVoiceData['emoji_level'] ?? ($useEmoji ? 'normal' : 'none')), ['none', 'low', 'normal', 'high'], true)
            ? ($brandVoiceData['emoji_level'] ?? 'normal') : 'normal';
        $preferredExpressions = $this->sanitizeList($brandVoiceData['preferred_expressions'] ?? [], $redactor);
        $forbiddenExpressions = $this->sanitizeList($brandVoiceData['forbidden_expressions'] ?? [], $redactor);
        $complaintTone = $redactor->maskPii(trim(strip_tags($brandVoiceData['complaint_tone'] ?? 'sakin, empatik ve çözüm odaklı')));
        $salesTone = $redactor->maskPii(trim(strip_tags($brandVoiceData['sales_tone'] ?? 'bilgilendirici ve baskısız')));
        $crisisTone = $redactor->maskPii(trim(strip_tags($brandVoiceData['crisis_tone'] ?? 'net, sorumluluk sahibi ve insana devir odaklı')));
        $languageRules = $this->sanitizeLanguageRules($brandVoiceData['language_rules'] ?? [], $redactor);

        if (mb_strlen($tone) > 100 || mb_strlen($hitap) > 100 || mb_strlen($promptContext) > 1000 || mb_strlen($returnPolicy) > 1000 || mb_strlen($greeting) > 1000 || mb_strlen($signature) > 1000 || mb_strlen($sampleResponse) > 1000) {
            throw new \InvalidArgumentException('Marka sesi girdileri uzunluk sınırlarını aşıyor.');
        }

        // Basit prompt injection engelleme kuralları
        $injectionKeywords = [
            'ignore previous', 'system prompt', 'translate to', 'you are now', 'dan mode',
            'talimatları unut', 'ignore all', 'sen artık', 'temsilci rolü', 'sistem ayarı'
        ];
        foreach (array_merge([$tone, $promptContext, $returnPolicy, $hitap, $greeting, $signature, $sampleResponse, $complaintTone, $salesTone, $crisisTone], $preferredExpressions, $forbiddenExpressions) as $field) {
            foreach ($injectionKeywords as $keyword) {
                if (mb_stripos($field, $keyword) !== false) {
                    throw new \InvalidArgumentException('Potansiyel prompt injection tespiti nedeniyle işlem engellendi.');
                }
            }
        }

        $config = $channel->config_json ?? [];
        $config['brand_voice'] = [
            'tone' => $tone,
            'prompt_context' => $promptContext,
            'return_policy' => $returnPolicy,
            'hitap' => $hitap,
            'use_emoji' => $useEmoji,
            'greeting' => $greeting,
            'signature' => $signature,
            'sample_response' => $sampleResponse,
            'response_length' => $responseLength,
            'emoji_level' => $emojiLevel,
            'preferred_expressions' => $preferredExpressions,
            'forbidden_expressions' => $forbiddenExpressions,
            'complaint_tone' => $complaintTone,
            'sales_tone' => $salesTone,
            'crisis_tone' => $crisisTone,
            'language_rules' => $languageRules,
        ];

        $channel->update(['config_json' => $config]);

        // Domain Audit Log (Durable audit log)
        \App\Models\SupportAgentAction::create([
            'conversation_id' => null,
            'message_id' => null,
            'user_id' => $user->id,
            'action' => 'brand_voice_updated',
            'details_json' => [
                'channel_id' => $channel->id,
                'tone' => $tone,
            ],
        ]);

        // Audit Logging (Audit Trail)
        \Illuminate\Support\Facades\Log::info('Brand voice updated.', [
            'channel_id' => $channel->id,
            'actor_user_id' => $user->id,
            'tone' => $tone,
        ]);
    }

    public function validateResponse(string $message, array $voice, string $language = 'tr'): array
    {
        $maxLength = match ($voice['response_length'] ?? 'medium') {
            'short' => 300,
            'long' => 1200,
            default => 700,
        };
        if (mb_strlen($message) > $maxLength) {
            return ['allowed' => false, 'reason' => "Marka cevap uzunluğu sınırı aşıldı ({$maxLength})."];
        }
        $languageRules = $voice['language_rules'][$language] ?? [];
        $forbidden = array_merge($voice['forbidden_expressions'] ?? [], $languageRules['forbidden_expressions'] ?? []);
        foreach ($forbidden as $expression) {
            if ($expression !== '' && mb_stripos($message, $expression) !== false) {
                return ['allowed' => false, 'reason' => "Marka yasaklı ifadesi tespit edildi: {$expression}"];
            }
        }
        $emojiCount = preg_match_all('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $message);
        $emojiLimit = match ($voice['emoji_level'] ?? 'normal') {
            'none' => 0, 'low' => 1, 'high' => 5, default => 2,
        };
        if ($emojiCount > $emojiLimit) {
            return ['allowed' => false, 'reason' => 'Marka emoji seviyesi aşıldı.'];
        }
        return ['allowed' => true, 'reason' => null];
    }

    private function sanitizeList(array|string $value, Security\PiiRedactor $redactor): array
    {
        $items = is_array($value) ? $value : preg_split('/[,\n]+/', $value);
        return collect($items ?: [])->map(fn ($item) => trim($redactor->maskPii(strip_tags((string) $item))))
            ->filter()->take(30)->values()->all();
    }

    private function sanitizeLanguageRules(array|string $value, Security\PiiRedactor $redactor): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }
        $result = [];
        foreach (array_slice($value, 0, 20, true) as $language => $rules) {
            if (!preg_match('/^[a-z]{2}(?:-[A-Z]{2})?$/', (string) $language) || !is_array($rules)) continue;
            $result[$language] = [
                'forbidden_expressions' => $this->sanitizeList($rules['forbidden_expressions'] ?? [], $redactor),
                'examples' => $this->sanitizeList($rules['examples'] ?? [], $redactor),
            ];
        }
        return $result ?: ['tr' => ['forbidden_expressions' => [], 'examples' => []]];
    }
}
