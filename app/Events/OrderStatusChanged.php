<?php

namespace App\Events;

use App\Models\ChannelOrder;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ChannelOrder $order,
        public readonly string $oldStatus,
        public readonly string $newStatus,
        public readonly string $source = 'sync',
    ) {}
}
