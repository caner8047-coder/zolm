<?php

namespace App\Services\Marketplace\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class PazaramaOrderStatusResolver
{
    public function resolveOrderStatus(array $payload): string
    {
        return $this->resolveStatus(
            data_get($payload, 'orderStatus'),
            $this->firstStatusName($payload),
        );
    }

    public function resolveLineStatus(array $payload, mixed $fallbackStatusCode = null): string
    {
        return $this->resolveStatus(
            data_get($payload, 'orderItemStatus', data_get($payload, 'status', $fallbackStatusCode)),
            data_get($payload, 'orderItemStatusName') ?: data_get($payload, 'statusName'),
        );
    }

    public function resolveStatus(mixed $statusCode, ?string $statusName = null): string
    {
        $statusFromName = $this->statusFromName($statusName);

        if ($statusFromName !== null) {
            return $statusFromName;
        }

        return $this->statusFromCode($statusCode);
    }

    /**
     * @return array{shipped_at:?string, delivered_at:?string}
     */
    public function resolvePackageTimeline(array $payload, ?string $resolvedStatus = null): array
    {
        $resolvedStatus ??= $this->resolveOrderStatus($payload);

        $shippedAt = $this->normalizeDate(
            data_get($payload, 'shippedDate')
                ?: data_get($payload, 'shipmentDate')
                ?: data_get($payload, 'cargo.shippedDate')
                ?: data_get($payload, 'items.0.shippedDate')
                ?: data_get($payload, 'items.0.cargo.shippedDate')
        );

        $deliveredAt = $this->normalizeDate(
            data_get($payload, 'deliveredDate')
                ?: data_get($payload, 'cargo.deliveredDate')
                ?: data_get($payload, 'items.0.deliveredDate')
                ?: data_get($payload, 'items.0.cargo.deliveredDate')
        );

        if ($resolvedStatus !== 'Delivered') {
            $deliveredAt = null;
        }

        if (!in_array($resolvedStatus, ['Shipped', 'Delivered'], true)) {
            $shippedAt = null;
        }

        return [
            'shipped_at' => $shippedAt,
            'delivered_at' => $deliveredAt,
        ];
    }

    protected function firstStatusName(array $payload): ?string
    {
        return data_get($payload, 'items.0.orderItemStatusName')
            ?: data_get($payload, 'itemList.0.orderItemStatusName')
            ?: data_get($payload, 'orderItemStatusName')
            ?: data_get($payload, 'statusName');
    }

    protected function statusFromName(?string $statusName): ?string
    {
        $normalized = $this->normalizeStatusName($statusName);

        return match (true) {
            $normalized === '' => null,
            Str::contains($normalized, ['iade redd']) => 'Rejected',
            Str::contains($normalized, ['iade']) => 'Returned',
            Str::contains($normalized, ['teslim edilemedi', 'tedarik edilemedi']) => 'Rejected',
            Str::contains($normalized, ['iptal']) => 'Cancelled',
            Str::contains($normalized, ['teslim edildi', 'teslim']) => 'Delivered',
            Str::contains($normalized, ['kargoya verildi', 'kargoda']) => 'Shipped',
            Str::contains($normalized, ['hazirlaniyor', 'hazir']) => 'Processing',
            Str::contains($normalized, ['onaylandi', 'siparis alindi', 'siparisiniz alindi']) => 'Approved',
            Str::contains($normalized, ['created', 'yeni']) => 'Created',
            default => null,
        };
    }

    protected function statusFromCode(mixed $statusCode): string
    {
        return match ((int) $statusCode) {
            1 => 'Created',
            2, 12 => 'Processing',
            3 => 'Approved',
            4 => 'Cancelled',
            5 => 'Shipped',
            7, 8, 10 => 'Returned',
            9, 13, 14 => 'Rejected',
            11 => 'Delivered',
            default => 'Created',
        };
    }

    protected function normalizeStatusName(?string $statusName): string
    {
        return Str::of((string) $statusName)
            ->trim()
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->value();
    }

    protected function normalizeDate(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        if (is_numeric($value)) {
            $timestamp = (int) $value;

            if ($timestamp > 9999999999) {
                return CarbonImmutable::createFromTimestampMs($timestamp)->toIso8601String();
            }

            return CarbonImmutable::createFromTimestamp($timestamp)->toIso8601String();
        }

        try {
            return CarbonImmutable::parse((string) $value)->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }
}
