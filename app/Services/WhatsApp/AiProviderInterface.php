<?php

namespace App\Services\WhatsApp;

interface AiProviderInterface
{
    public function chat(string $systemPrompt, string $userMessage, array $tools = []): array;
}
