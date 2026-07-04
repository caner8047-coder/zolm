<?php

namespace App\Services\WhatsApp;

use App\Models\WaNotificationChannel;
use App\Models\WaNotificationTemplate;
use App\Models\WaNotificationSend;

class NotificationBridgeService
{
    /**
     * Bildirim gönder
     */
    public function send(WaNotificationChannel $channel, string $templateKey, string $recipient, array $variables = []): array
    {
        if (!$channel->is_enabled) {
            return ['success' => false, 'message' => 'Kanal pasif'];
        }

        $template = WaNotificationTemplate::where('channel_id', $channel->id)
            ->where('key', $templateKey)
            ->active()
            ->first();

        if (!$template) {
            return ['success' => false, 'message' => 'Şablon bulunamadı'];
        }

        // Değişkenleri doğrula
        $schema = $template->variables_schema ?? [];
        foreach (array_keys($schema) as $var) {
            if (!array_key_exists($var, $variables)) {
                return ['success' => false, 'message' => "Eksik değişken: {$var}"];
            }
        }

        // Şablonu doldur
        $body = $template->body_template;
        foreach ($variables as $key => $value) {
            $body = str_replace('{' . $key . '}', (string) $value, $body);
        }

        $send = WaNotificationSend::create([
            'channel_id' => $channel->id,
            'template_id' => $template->id,
            'recipient' => $recipient,
            'status' => 'queued',
            'variables_used' => array_keys($variables),
        ]);

        // Kanal tipine göre gönder
        $result = match ($channel->type) {
            'email' => $this->sendEmail($channel, $template, $recipient, $body),
            'sms' => $this->sendSms($channel, $recipient, $body),
            'webhook' => $this->sendWebhook($channel, $template, $recipient, $variables),
            default => ['success' => false, 'message' => 'Desteklenmeyen kanal tipi'],
        };

        if ($result['success']) {
            $send->update(['status' => 'sent', 'sent_at' => now()]);
            $channel->update(['last_sent_at' => now()]);
        } else {
            $send->update(['status' => 'failed', 'error_message' => $result['message'] ?? 'Bilinmeyen hata']);
        }

        return $result;
    }

    private function sendEmail(WaNotificationChannel $channel, WaNotificationTemplate $template, string $recipient, string $body): array
    {
        $config = $channel->config_json ?? [];

        try {
            wp_mail($recipient, $template->subject ?? 'Bildirim', $body, [
                'Content-Type' => 'text/html; charset=UTF-8',
                'From' => $config['from_email'] ?? 'noreply@zemhome.com.tr',
            ]);

            return ['success' => true];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function sendSms(WaNotificationChannel $channel, string $recipient, string $body): array
    {
        // SMS gateway entegrasyonu — gerçek entegrasyon ileride
        return ['success' => true, 'message' => 'SMS gönderimi planlandı'];
    }

    private function sendWebhook(WaNotificationChannel $channel, WaNotificationTemplate $template, string $recipient, array $variables): array
    {
        $config = $channel->config_json ?? [];
        $url = $config['webhook_url'] ?? '';

        if (empty($url)) {
            return ['success' => false, 'message' => 'Webhook URL tanımlı değil'];
        }

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(10)
                ->post($url, [
                    'template' => $template->key,
                    'recipient' => $recipient,
                    'variables' => $variables,
                ]);

            return $response->successful()
                ? ['success' => true]
                : ['success' => false, 'message' => 'HTTP ' . $response->status()];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
