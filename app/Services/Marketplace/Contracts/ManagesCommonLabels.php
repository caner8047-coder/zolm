<?php

namespace App\Services\Marketplace\Contracts;

use App\Models\ChannelOrderPackage;

interface ManagesCommonLabels
{
    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function createCommonLabel(ChannelOrderPackage $package, array $context = []): array;

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function getCommonLabel(ChannelOrderPackage $package, array $context = []): array;
}
