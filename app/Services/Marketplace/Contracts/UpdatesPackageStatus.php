<?php

namespace App\Services\Marketplace\Contracts;

use App\Models\ChannelOrderPackage;

interface UpdatesPackageStatus
{
    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function notifyPackagePicking(ChannelOrderPackage $package, array $context = []): array;

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function notifyPackageInvoiced(ChannelOrderPackage $package, array $context = []): array;
}
