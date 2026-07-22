<?php

namespace App\Services\Cargo;

use Illuminate\Support\Str;

class CargoCarrierRegistry
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return collect(config('cargo.companies', []))
            ->mapWithKeys(function (array $carrier, string $code) {
                $normalizedCode = $this->normalizeCode($code);

                return [$normalizedCode => array_merge([
                    'code' => Str::upper($normalizedCode),
                    'name' => Str::headline($normalizedCode),
                    'aliases' => [],
                    'connector' => null,
                    'capabilities' => [],
                    'integration_status' => 'tracking_only',
                ], $carrier, [
                    'key' => $normalizedCode,
                ])];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $code): ?array
    {
        $normalized = $this->normalizeCode($code);
        $carriers = $this->all();

        if (isset($carriers[$normalized])) {
            return $carriers[$normalized];
        }

        foreach ($carriers as $carrier) {
            $aliases = array_merge(
                [$carrier['key'], $carrier['code'] ?? '', $carrier['name'] ?? ''],
                $carrier['aliases'] ?? [],
            );

            if (collect($aliases)->contains(fn (mixed $alias) => $this->normalizeCode((string) $alias) === $normalized)) {
                return $carrier;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $code): array
    {
        return $this->find($code)
            ?? throw new \InvalidArgumentException("Desteklenmeyen kargo firması: {$code}");
    }

    public function canonicalCode(string $code): string
    {
        return (string) ($this->get($code)['key'] ?? $this->normalizeCode($code));
    }

    public function name(string $code): string
    {
        return (string) ($this->get($code)['name'] ?? $code);
    }

    public function hasConnector(string $code): bool
    {
        return filled($this->find($code)['connector'] ?? null);
    }

    public function supports(string $code, string $capability): bool
    {
        return in_array($capability, $this->find($code)['capabilities'] ?? [], true);
    }

    /**
     * @return list<string>
     */
    public function connectorCodes(): array
    {
        return collect($this->all())
            ->filter(fn (array $carrier) => filled($carrier['connector'] ?? null))
            ->keys()
            ->values()
            ->all();
    }

    public function unavailableMessage(string $code): string
    {
        $carrier = $this->get($code);
        $name = (string) $carrier['name'];

        return match ($carrier['integration_status'] ?? null) {
            'contract_required' => "{$name} canlı entegrasyonu için firmadan kurumsal API sözleşmesi ve teknik doküman alınmalı.",
            'developer_access_required' => "{$name} canlı entegrasyonu için geliştirici hesabı, test ortamı ve API kimlik bilgileri gerekli.",
            'marketplace_managed' => "{$name} ayrı bir kargo hesabı olarak değil, Trendyol mağaza entegrasyonu üzerinden yönetilir.",
            default => "{$name} için canlı gönderi sürücüsü henüz etkin değil.",
        };
    }

    protected function normalizeCode(string $value): string
    {
        return (string) Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_');
    }
}
