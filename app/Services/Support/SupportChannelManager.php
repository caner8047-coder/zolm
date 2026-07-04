<?php

namespace App\Services\Support;

use App\Models\SupportChannel;

class SupportChannelManager
{
    private array $adapters = [];

    public function __construct()
    {
        $this->register(new WhatsAppSupportChannelAdapter());
        $this->register(new TrendyolSupportChannelAdapter());
        $this->register(new HepsiburadaSupportChannelAdapter());
        $this->register(new NullSupportChannelAdapter());
    }

    private function register(SupportChannelAdapterInterface $adapter): void
    {
        $this->adapters[$adapter->key()] = $adapter;
    }

    public function resolve(string $key): SupportChannelAdapterInterface
    {
        return $this->adapters[$key] ?? new NullSupportChannelAdapter();
    }

    public function resolveForChannel(SupportChannel $channel): SupportChannelAdapterInterface
    {
        return $this->resolve($channel->key);
    }

    public function getAllAdapters(): array
    {
        return $this->adapters;
    }
}
