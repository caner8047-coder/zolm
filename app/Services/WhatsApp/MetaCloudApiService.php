<?php

namespace App\Services\WhatsApp;

use App\Models\WaAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaCloudApiService
{
    protected string $baseUrl;
    protected string $graphVersion;

    public function __construct()
    {
        $this->baseUrl = config('whatsapp.meta.graph_base_url', 'https://graph.facebook.com');
        $this->graphVersion = config('whatsapp.meta.graph_version', 'v25.0');
    }

    public function sendTemplateMessage(
        WaAccount $account,
        string $phoneNumber,
        string $templateName,
        string $languageCode = 'tr',
        array $params = [],
    ): array {
        $body = [
            'messaging_product' => 'whatsapp',
            'to' => $phoneNumber,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $languageCode],
            ],
        ];

        if (!empty($params)) {
            $components = [];
            $paramValues = array_map(fn ($p) => ['type' => 'text', 'text' => (string) $p], $params);
            $components[] = [
                'type' => 'body',
                'parameters' => array_values($paramValues),
            ];
            $body['template']['components'] = $components;
        }

        return $this->request($account, 'POST', '/messages', $body);
    }

    public function sendTextMessage(WaAccount $account, string $phoneNumber, string $text): array
    {
        $body = [
            'messaging_product' => 'whatsapp',
            'to' => $phoneNumber,
            'type' => 'text',
            'text' => ['body' => $text],
        ];

        return $this->request($account, 'POST', '/messages', $body);
    }

    public function markAsRead(WaAccount $account, string $messageId): void
    {
        $this->request($account, 'POST', '/messages', [
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id' => $messageId,
        ]);
    }

    public function getBusinessProfile(WaAccount $account): array
    {
        return $this->request($account, 'GET', '/business_profile');
    }

    public function syncTemplates(WaAccount $account): array
    {
        $response = $this->request($account, 'GET', '/message_templates');
        return $response['data'] ?? [];
    }

    public function verifyWebhookSignature(string $rawBody, string $signature): bool
    {
        $appSecret = config('whatsapp.webhook.app_secret', '');

        if ($appSecret === '' || $signature === '') {
            return false;
        }

        $expectedSignature = 'sha256=' . hash_hmac('sha256', $rawBody, $appSecret);

        return hash_equals($expectedSignature, $signature);
    }

    protected function request(WaAccount $account, string $method, string $endpoint, ?array $body = null): array
    {
        $token = $account->access_token;
        $url = rtrim($this->baseUrl, '/') . '/' . $this->graphVersion . '/{phone_number_id}' . $endpoint;
        $url = str_replace('{phone_number_id}', $account->phone_number_id, $url);

        $http = Http::withToken($token)
            ->timeout(30);

        $response = match (strtoupper($method)) {
            'GET' => $http->get($url),
            'POST' => $http->post($url, $body),
            default => throw new \InvalidArgumentException("Desteklenmeyen HTTP metodu: {$method}"),
        };

        if ($response->failed()) {
            Log::warning('Meta Cloud API hatası', [
                'account_id' => $account->id,
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException(
                'Meta API hatası: ' . ($response->json('error.message') ?? $response->body())
            );
        }

        return $response->json();
    }
}
