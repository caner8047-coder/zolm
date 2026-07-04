<?php

namespace App\Events;

use App\Models\MpProduct;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductStockChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly MpProduct $product,
        public readonly int $oldQuantity,
        public readonly int $newQuantity,
    ) {}
}
