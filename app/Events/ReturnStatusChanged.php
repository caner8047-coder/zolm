<?php

namespace App\Events;

use App\Models\ChannelClaim;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReturnStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ChannelClaim $claim,
        public readonly string $oldStatus,
        public readonly string $newStatus,
    ) {}
}
