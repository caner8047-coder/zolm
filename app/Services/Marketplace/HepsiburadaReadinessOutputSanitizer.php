<?php

namespace App\Services\Marketplace;

class HepsiburadaReadinessOutputSanitizer
{
    /**
     * Sanitize catalog product items for readiness output.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    public function sanitizeCatalogItems(array $items): array
    {
        return array_map(function (array $item) {
            $productId = (string) ($item['product_id'] ?? $item['external_product_id'] ?? $item['id'] ?? '');
            $sku = (string) ($item['merchant_sku'] ?? $item['sku'] ?? $item['stock_code'] ?? '');
            $barcode = (string) ($item['barcode'] ?? '');
            $title = (string) ($item['product_name'] ?? $item['title'] ?? $item['name'] ?? '');
            $status = (string) ($item['status'] ?? $item['approval_status'] ?? $item['listing_status'] ?? 'unknown');

            return [
                'product_id_masked'   => self::maskString($productId),
                'merchant_sku_masked' => self::maskString($sku),
                'barcode_masked'      => self::maskString($barcode),
                'product_name_short'  => self::shortenString($title, 30),
                'approval_status'     => $status !== '' ? $status : 'unknown',
            ];
        }, $items);
    }

    /**
     * Sanitize category items for readiness output.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    public function sanitizeCategoryItems(array $items): array
    {
        return array_map(function (array $item) {
            return [
                'category_id'   => $item['category_id'] ?? $item['id'] ?? null,
                'category_name' => $item['category_name'] ?? $item['name'] ?? null,
                'parent_id'     => $item['parent_id'] ?? null,
                'leaf'          => (bool) ($item['leaf'] ?? $item['is_leaf'] ?? true),
            ];
        }, $items);
    }

    /**
     * Sanitize category attribute items for readiness output.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    public function sanitizeAttributeItems(array $items): array
    {
        return array_map(function (array $item) {
            $values = $item['values'] ?? $item['attribute_values'] ?? [];
            return [
                'attribute_id'   => $item['attribute_id'] ?? $item['id'] ?? null,
                'attribute_name' => $item['attribute_name'] ?? $item['name'] ?? null,
                'required'       => (bool) ($item['required'] ?? $item['is_required'] ?? false),
                'variant'        => (bool) ($item['variant'] ?? $item['is_variant'] ?? false),
                'value_count'    => is_array($values) ? count($values) : 0,
            ];
        }, $items);
    }

    /**
     * Sanitize batch status response for readiness output.
     *
     * @param  array<string, mixed>  $batch
     * @param  string|null  $batchId
     * @return array<string, mixed>
     */
    public function sanitizeBatchResult(array $batch, ?string $batchId = null): array
    {
        $id = $batchId ?: (string) ($batch['batch_id'] ?? $batch['id'] ?? '');
        $errorCodes = $batch['error_codes'] ?? $batch['errors'] ?? [];

        $sanitizedCodes = [];
        if (is_array($errorCodes)) {
            foreach ($errorCodes as $code) {
                if (is_string($code)) {
                    $sanitizedCodes[] = self::sanitizeErrorCode($code);
                } elseif (is_array($code) && isset($code['code'])) {
                    $sanitizedCodes[] = self::sanitizeErrorCode((string) $code['code']);
                }
            }
        }

        return [
            'batch_id_masked'       => self::maskString($id),
            'operation'             => (string) ($batch['operation'] ?? 'unknown'),
            'status'                => (string) ($batch['status'] ?? 'unknown'),
            'success_count'         => (int) ($batch['success_count'] ?? $batch['successful_item_count'] ?? 0),
            'failure_count'         => (int) ($batch['failure_count'] ?? $batch['failed_item_count'] ?? 0),
            'sanitized_error_codes' => array_values(array_unique($sanitizedCodes)),
        ];
    }

    /**
     * Mask sensitive strings (skus, barcodes, IDs, tokens).
     */
    public static function maskString(string $val): string
    {
        $val = trim($val);
        if ($val === '') {
            return '***';
        }

        $len = mb_strlen($val);
        if ($len <= 4) {
            return '***';
        }
        if ($len <= 8) {
            return mb_substr($val, 0, 1) . '***' . mb_substr($val, -1);
        }

        return mb_substr($val, 0, 2) . '***' . mb_substr($val, -2);
    }

    /**
     * Shorten strings safely.
     */
    public static function shortenString(string $val, int $maxLength = 30): string
    {
        $val = trim($val);
        if (mb_strlen($val) > $maxLength) {
            return mb_substr($val, 0, $maxLength) . '...';
        }

        return $val;
    }

    /**
     * Sanitize error code to short identifier without raw message details.
     */
    public static function sanitizeErrorCode(string $code): string
    {
        $code = trim(preg_replace('/[^a-zA-Z0-9_\-]/', '_', $code));
        return mb_strtoupper(mb_substr($code, 0, 40)) ?: 'UNKNOWN_ERROR';
    }
}
