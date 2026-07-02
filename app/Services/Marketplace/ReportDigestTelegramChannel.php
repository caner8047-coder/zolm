<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReportDigestTelegramChannel
{
    public function send(array $payload, string $botToken, string $chatId): void
    {
        if (empty($botToken) || empty($chatId)) {
            return;
        }

        try {
            $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
            
            // Emoji'lerle güzel bir mesaj formatı
            $text = "📊 *" . ($payload['title'] ?? 'Marketplace Report Digest') . "*\n\n";
            $text .= ($payload['summary'] ?? '') . "\n\n";
            
            if (isset($payload['total_profit'])) {
                $text .= "💰 *Toplam Kâr:* ₺" . number_format($payload['total_profit'], 2, ',', '.') . "\n";
            }
            if (isset($payload['total_revenue'])) {
                $text .= "📈 *Toplam Ciro:* ₺" . number_format($payload['total_revenue'], 2, ',', '.') . "\n";
            }
            if (isset($payload['order_count'])) {
                $text .= "📦 *Sipariş Sayısı:* " . $payload['order_count'] . "\n";
            }
            if (isset($payload['margin_percent'])) {
                $text .= "📊 *Ortalama Marj:* %" . number_format($payload['margin_percent'], 1, ',', '.') . "\n";
            }
            
            $text .= "\n⏱ *" . now()->format('d.m.Y H:i') . "*";

            $response = Http::timeout(10)->post($url, [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'Markdown',
            ]);

            if (! $response->successful()) {
                Log::error('Telegram failed to send.', [
                    'chat_id' => $chatId,
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Telegram channel exception: ' . $e->getMessage(), [
                'chat_id' => $chatId,
            ]);
        }
    }
}
