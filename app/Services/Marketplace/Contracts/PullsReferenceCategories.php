<?php

namespace App\Services\Marketplace\Contracts;

use App\Models\MarketplaceStore;

interface PullsReferenceCategories
{
    /**
     * Pazaryerinden tüm kategori ağacını çeker.
     *
     * Dönen dizi, her kategorinin en azından şu alanları içermelidir:
     *  - id          : platformun kategori kimliği
     *  - name        : kategori adı
     *  - subCategories : (opsiyonel) alt kategorilerin aynı yapıda dizisi
     *
     * @param  array<string, mixed>  $options
     * @return array<int, array<string, mixed>>
     */
    public function getCategories(MarketplaceStore $store, array $options = []): array;
}
