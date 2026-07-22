<?php

namespace App\Services\Marketplace\Contracts;

use App\Models\MarketplaceStore;

interface PullsBatchStatus
{
    /**
     * Daha önce gönderilmiş bir toplu işlem (fiyat veya stok yükleme) sonucunu sorgular.
     *
     * Bu metod yalnızca salt-okuma işlem yapar; herhangi bir fiyat/stok değişikliği tetiklemez.
     *
     * @param  string  $batchRequestId  Batch işlem kimliği
     * @param  string  $operation       İşlem tipi: 'price-uploads' | 'stock-uploads'
     * @param  array<string, mixed>  $options
     * @return array{
     *     batch_request_id: string,
     *     operation: string,
     *     status: string,
     *     success_count: int|null,
     *     failure_count: int|null,
     *     items: array<int, array<string, mixed>>,
     *     raw_payload: array<string, mixed>
     * }
     */
    public function pullBatchStatus(
        MarketplaceStore $store,
        string $batchRequestId,
        string $operation,
        array $options = []
    ): array;
}
