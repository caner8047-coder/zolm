<?php

namespace App\Services\Support;

use App\Models\SupportChannel;

interface SupportMessageCorrectionAdapterInterface
{
    /** @return array<int, string> edit, retract, delete */
    public function correctionCapabilities(SupportChannel $channel): array;

    public function correctMessage(
        SupportChannel $channel,
        string $externalConversationId,
        string $channelMessageId,
        string $correctionText
    ): array;
}
