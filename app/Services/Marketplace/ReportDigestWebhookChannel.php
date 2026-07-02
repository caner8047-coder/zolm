<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReportDigestWebhookChannel
{
    public function send(array $payload, string $webhookUrl): void
    {
        if (empty($webhookUrl)) {
            return;
        }

        try {
            // Webhook payload'unu basit ve düz bir yapıya çeviriyoruz (Slack, Zapier vb. için)
            $data = [
                'text' => ($payload['title'] ?? 'Marketplace Report Digest') . " Özeti",
                'title' => $payload['title'] ?? 'Marketplace Report Digest',
                'summary' => $payload['summary'] ?? '',
                'total_profit' => $payload['total_profit'] ?? 0,
                'total_revenue' => $payload['total_revenue'] ?? 0,
                'order_count' => $payload['order_count'] ?? 0,
                'margin_percent' => $payload['margin_percent'] ?? 0,
                'report_date' => now()->toIso8601String(),
            ];

            // Slack webhook uyumluluğu için "text" key'ini dışa aldık, geri kalanları attachments/blocks ile zenginleştirebiliriz
            // Ancak şimdilik en temel JSON formunda bırakıyoruz.
            $response = Http::timeout(10)->post($webhookUrl, $data);

            if (! $response->successful()) {
                Log::error('Webhook failed to send.', [
                    'url' => $webhookUrl,
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Webhook channel exception: ' . $e->getMessage(), [
                'url' => $webhookUrl,
            ]);
        }
    }
}
