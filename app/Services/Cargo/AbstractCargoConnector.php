<?php

namespace App\Services\Cargo;

use App\Models\CargoCarrierAccount;
use App\Models\Shipment;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

abstract class AbstractCargoConnector
{
    protected function credential(CargoCarrierAccount $account, string $key, mixed $default = null): mixed
    {
        return data_get($account->credentials_encrypted ?? [], $key, $default);
    }

    protected function requireCredentials(CargoCarrierAccount $account, array $keys): void
    {
        $missing = collect($keys)
            ->filter(fn (string $key) => blank($this->credential($account, $key)))
            ->values();

        if ($missing->isNotEmpty()) {
            throw new \RuntimeException('Eksik bağlantı alanları: '.$missing->implode(', '));
        }
    }

    protected function reference(Shipment $shipment): string
    {
        return (string) ($shipment->reference_number
            ?: $shipment->order_number
            ?: $shipment->shipment_no
            ?: $shipment->id);
    }

    protected function toArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return json_decode(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), true) ?: [];
        }

        return [];
    }

    protected function recursiveValue(array $payload, array $keys, mixed $default = null): mixed
    {
        foreach ($payload as $key => $value) {
            if (in_array(Str::lower((string) $key), array_map(fn ($item) => Str::lower((string) $item), $keys), true) && filled($value)) {
                return $value;
            }

            if (is_array($value)) {
                $found = $this->recursiveValue($value, $keys, null);
                if ($found !== null && $found !== '') {
                    return $found;
                }
            }
        }

        return $default;
    }

    protected function normalizeStatus(?string $label): string
    {
        $status = Str::lower(Str::ascii((string) $label));

        return match (true) {
            Str::contains($status, ['teslim edildi', 'delivered']) => 'delivered',
            Str::contains($status, ['dagitimda', 'dagitima', 'out for delivery']) => 'out_for_delivery',
            Str::contains($status, ['iade', 'return']) => 'returned',
            Str::contains($status, ['iptal', 'cancel']) => 'cancelled',
            Str::contains($status, ['hata', 'sorun', 'teslim edilemedi', 'exception', 'failed']) => 'exception',
            Str::contains($status, ['transfer', 'yolda', 'tasima', 'in transit', 'sevk']) => 'in_transit',
            Str::contains($status, ['kabul', 'sube', 'shipment picked', 'picked up']) => 'shipped',
            default => 'ready',
        };
    }

    protected function listFrom(mixed $value): array
    {
        $array = $this->toArray($value);

        if ($array === []) {
            return [];
        }

        return Arr::isList($array) ? $array : [$array];
    }
}
