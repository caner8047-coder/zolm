<?php

namespace App\Services\Marketplace\Contracts;

use App\Models\ChannelOrderPackage;

interface SendsInvoiceLinks
{
    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function sendInvoiceLink(ChannelOrderPackage $package, string $invoiceLink, array $context = []): array;
}
