<?php

namespace App\Services\WhatsApp\Tools;

interface AiTool
{
    public function name(): string;
    public function description(): string;
    public function execute(array $params, int $storeId, ?int $contactId = null): array;
}
