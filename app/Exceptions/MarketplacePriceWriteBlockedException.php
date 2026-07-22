<?php

namespace App\Exceptions;

use Exception;

class MarketplacePriceWriteBlockedException extends Exception
{
    public function __construct(string $message = "Fiyat push işlemi güvenlik veya dry-run kısıtlaması nedeniyle engellendi.", int $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
