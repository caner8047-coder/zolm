<?php

namespace App\Services\Support\Integration;

use App\Models\SupportConversation;

interface GenericCrmConnectorInterface
{
    public function syncContact(array $contactData): array;

    public function syncConversation(SupportConversation $conversation): array;
}
