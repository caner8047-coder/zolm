<?php

namespace App\Services\Cargo;

use App\Models\CargoCarrierAccount;
use App\Models\ChannelOrderPackage;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class WooCommerceSuratTrackingSyncService
{
    public function __construct(
        protected CargoShipmentService $shipmentService,
        protected SuratCargoConnector $suratConnector,
    ) {
    }

    /**
     * @param  array{limit?: int, lookback_days?: int, stale_minutes?: int, archive_report?: bool}  $options
     * @return array<string, mixed>
     */
    public function sync(array $options = []): array
    {
        if (!$this->requiredTablesReady()) {
            return $this->emptySummary(['skipped_reason' => 'required_tables_missing']);
        }

        $limit = max(1, (int) ($options['limit'] ?? 100));
        $lookbackDays = min(31, max(1, (int) ($options['lookback_days'] ?? 14)));
        $staleMinutes = max(5, (int) ($options['stale_minutes'] ?? 60));
        $archiveReport = (bool) ($options['archive_report'] ?? false);

        $summary = $this->emptySummary([
            'limit' => $limit,
            'lookback_days' => $lookbackDays,
            'stale_minutes' => $staleMinutes,
        ]);

        $accounts = CargoCarrierAccount::query()
            ->surat()
            ->active()
            ->whereNotNull('customer_code')
            ->where('customer_code', '!=', '')
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();

        $summary['accounts'] = $accounts->count();

        foreach ($accounts as $account) {
            if ($summary['scanned'] >= $limit) {
                break;
            }

            $remainingLimit = $limit - (int) $summary['scanned'];
            $packages = $this->candidatePackages($account, $remainingLimit, $lookbackDays, $staleMinutes);

            if ($packages->isEmpty()) {
                continue;
            }

            $summary['scanned'] += $packages->count();

            try {
                $startDate = $this->reportStartDate($packages, $lookbackDays);
                $endDate = now()->toDateString();
                $report = $this->suratConnector->sentShipmentReport($account, $startDate, $endDate);
                $rows = collect($report['rows'] ?? [])
                    ->filter(fn ($row) => is_array($row))
                    ->values();

                $summary['report_rows'] += $rows->count();

                if ($archiveReport) {
                    app(SuratReportArchiveService::class)->archive($account, $report, $startDate, $endDate);
                }

                $usedRows = [];

                foreach ($packages as $package) {
                    $match = $this->matchPackage($package, $rows, $usedRows);

                    if (($match['ambiguous'] ?? false) === true) {
                        $summary['ambiguous']++;
                        continue;
                    }

                    $row = $match['row'] ?? null;

                    if (!is_array($row)) {
                        $summary['unmatched']++;
                        continue;
                    }

                    try {
                        $shipment = $this->shipmentService->createOrUpdateFromPackage($package, $account);
                        $trackingResult = $this->trackingResultFromReportRow($row, $shipment, (string) ($match['strategy'] ?? 'unknown'));
                        $shipment = $this->shipmentService->applyTrackingResult($shipment, $trackingResult);

                        $this->markRowUsed($usedRows, $row);
                        $summary['matched']++;
                        $summary['updated']++;

                        Log::info('WooCommerce Sürat otomatik takip eşleşti', [
                            'package_id' => $package->id,
                            'order_id' => $package->channel_order_id,
                            'shipment_id' => $shipment->id,
                            'tracking_number' => $shipment->tracking_number,
                            'match_strategy' => $match['strategy'] ?? null,
                        ]);
                    } catch (\Throwable $exception) {
                        $summary['failed']++;
                        $summary['errors'][] = [
                            'package_id' => $package->id,
                            'message' => $exception->getMessage(),
                        ];
                    }
                }
            } catch (\Throwable $exception) {
                $summary['failed']++;
                $summary['errors'][] = [
                    'account_id' => $account->id,
                    'message' => $exception->getMessage(),
                ];
            }
        }

        return $summary;
    }

    /**
     * @return Collection<int, ChannelOrderPackage>
     */
    protected function candidatePackages(
        CargoCarrierAccount $account,
        int $limit,
        int $lookbackDays,
        int $staleMinutes,
    ): Collection {
        $lookbackStart = now()->subDays($lookbackDays);
        $staleBefore = now()->subMinutes($staleMinutes);

        return ChannelOrderPackage::query()
            ->with([
                'store:id,user_id,legal_entity_id,marketplace,store_name,currency,uses_own_cargo',
                'order:id,store_id,legal_entity_id,external_order_id,order_number,order_status,customer_name,customer_phone,billing_name,shipment_city,shipment_district,ordered_at,delivered_at,cancelled_at,returned_at,raw_payload',
                'order.items.product',
                'items.product',
            ])
            ->whereHas('store', function (Builder $query) use ($account) {
                $query->where('marketplace', 'woocommerce')
                    ->where('user_id', $account->user_id);

                if ($account->legal_entity_id) {
                    $query->where('legal_entity_id', $account->legal_entity_id);
                }
            })
            ->whereHas('order', function (Builder $query) use ($lookbackStart) {
                $query->whereNull('cancelled_at')
                    ->whereNull('returned_at')
                    ->where(function (Builder $dateQuery) use ($lookbackStart) {
                        $dateQuery->whereNull('ordered_at')
                            ->orWhere('ordered_at', '>=', $lookbackStart);
                    });

                foreach (['cancel', 'iptal', 'refund', 'return', 'iade'] as $needle) {
                    $query->whereRaw('LOWER(COALESCE(order_status, \'\')) NOT LIKE ?', ['%' . $needle . '%']);
                }
            })
            ->where(function (Builder $query) use ($staleBefore) {
                $query->whereNull('cargo_tracking_number')
                    ->orWhere('cargo_tracking_number', '')
                    ->orWhereNull('last_synced_at')
                    ->orWhere('last_synced_at', '<=', $staleBefore);
            })
            ->where(function (Builder $query) {
                foreach (['delivered', 'completed', 'teslim', 'cancel', 'iptal', 'return', 'iade', 'refunded'] as $needle) {
                    $query->whereRaw('LOWER(COALESCE(package_status, \'\')) NOT LIKE ?', ['%' . $needle . '%']);
                }
            })
            ->orderByRaw("CASE WHEN cargo_tracking_number IS NULL OR cargo_tracking_number = '' THEN 0 ELSE 1 END")
            ->oldest('last_synced_at')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  Collection<int, ChannelOrderPackage>  $packages
     */
    protected function reportStartDate(Collection $packages, int $lookbackDays): string
    {
        $lookbackStart = now()->subDays($lookbackDays)->startOfDay();
        $oldestOrder = $packages
            ->map(fn (ChannelOrderPackage $package) => $package->order?->ordered_at)
            ->filter()
            ->min();

        if ($oldestOrder instanceof Carbon) {
            return $oldestOrder->copy()->subDay()->max($lookbackStart)->toDateString();
        }

        return $lookbackStart->toDateString();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<string, bool>  $usedRows
     * @return array{row?: array<string, mixed>, strategy?: string, ambiguous?: bool}
     */
    protected function matchPackage(ChannelOrderPackage $package, Collection $rows, array $usedRows): array
    {
        $availableRows = $rows
            ->filter(fn (array $row) => !$this->rowIsUsed($usedRows, $row))
            ->values();

        if ($availableRows->isEmpty()) {
            return [];
        }

        $trackingNumber = $this->normalizeReference($package->cargo_tracking_number);

        if ($trackingNumber !== '') {
            $trackingMatches = $availableRows
                ->filter(fn (array $row) => $this->normalizeReference($row['tracking_number'] ?? null) === $trackingNumber)
                ->values();

            if ($trackingMatches->count() === 1) {
                return ['row' => $trackingMatches->first(), 'strategy' => 'tracking_number'];
            }
        }

        $packageReferences = $this->packageReferenceKeys($package);
        $referenceMatches = $availableRows
            ->filter(fn (array $row) => $packageReferences->intersect($this->rowReferenceKeys($row))->isNotEmpty())
            ->values();

        if ($referenceMatches->count() === 1) {
            return ['row' => $referenceMatches->first(), 'strategy' => 'order_reference'];
        }

        if ($referenceMatches->count() > 1) {
            $best = $this->bestDatedRow($package, $referenceMatches);

            return $best
                ? ['row' => $best, 'strategy' => 'order_reference_best_date']
                : ['ambiguous' => true];
        }

        $customerName = $this->normalizePersonName($package->order?->customer_name ?: $package->order?->billing_name);

        if ($customerName === '') {
            return [];
        }

        $nameMatches = $availableRows
            ->filter(fn (array $row) => $this->normalizePersonName($row['customer_name'] ?? null) === $customerName)
            ->values();

        if ($nameMatches->isEmpty()) {
            return [];
        }

        $locationMatches = $nameMatches
            ->filter(fn (array $row) => $this->rowMatchesLocation($package, $row))
            ->values();

        $candidates = $locationMatches->isNotEmpty() ? $locationMatches : $nameMatches;

        if ($candidates->count() === 1) {
            return [
                'row' => $candidates->first(),
                'strategy' => $locationMatches->isNotEmpty() ? 'customer_name_location' : 'customer_name',
            ];
        }

        $best = $this->bestDatedRow($package, $candidates);

        return $best
            ? ['row' => $best, 'strategy' => $locationMatches->isNotEmpty() ? 'customer_name_location_best_date' : 'customer_name_best_date']
            : ['ambiguous' => true];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    protected function bestDatedRow(ChannelOrderPackage $package, Collection $rows): ?array
    {
        $orderedAt = $package->order?->ordered_at instanceof Carbon
            ? $package->order->ordered_at
            : null;

        if (!$orderedAt) {
            return null;
        }

        $scored = $rows
            ->map(function (array $row) use ($orderedAt) {
                $rowDate = $this->rowDate($row);

                if (!$rowDate || $rowDate->lt($orderedAt->copy()->subDay())) {
                    return null;
                }

                return [
                    'row' => $row,
                    'distance' => abs($rowDate->diffInHours($orderedAt)),
                    'has_tracking' => filled($row['tracking_number'] ?? null),
                ];
            })
            ->filter()
            ->sortBy([
                ['has_tracking', 'desc'],
                ['distance', 'asc'],
            ])
            ->values();

        if ($scored->isEmpty()) {
            return null;
        }

        if ($scored->count() === 1) {
            return $scored->first()['row'];
        }

        $first = $scored->first();
        $second = $scored->get(1);

        return ((int) $first['distance'] + 24) < (int) $second['distance']
            ? $first['row']
            : null;
    }

    /**
     * @return Collection<int, string>
     */
    protected function packageReferenceKeys(ChannelOrderPackage $package): Collection
    {
        $order = $package->order;
        $references = collect([
            $package->external_package_id,
            $package->package_number,
            $order?->external_order_id,
            $order?->order_number,
        ]);

        return $references
            ->flatMap(fn ($value) => $this->referenceVariants($value))
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return Collection<int, string>
     */
    protected function rowReferenceKeys(array $row): Collection
    {
        return collect([
            $row['web_order_code'] ?? null,
            $row['sales_code'] ?? null,
        ])
            ->flatMap(fn ($value) => $this->referenceVariants($value))
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * @return array<int, string>
     */
    protected function referenceVariants(mixed $value): array
    {
        $normalized = $this->normalizeReference($value);

        if ($normalized === '') {
            return [];
        }

        $variants = [$normalized];
        $digits = preg_replace('/\D+/', '', $normalized) ?: '';

        if (strlen($digits) >= 4) {
            $variants[] = $digits;
        }

        return $variants;
    }

    protected function rowMatchesLocation(ChannelOrderPackage $package, array $row): bool
    {
        $city = $this->normalizePlace($package->order?->shipment_city);
        $district = $this->normalizePlace($package->order?->shipment_district);
        $rowCity = $this->normalizePlace($row['destination_city'] ?? null);
        $rowDistrict = $this->normalizePlace($row['destination_district'] ?? null);

        return ($city !== '' && $rowCity !== '' && $city === $rowCity)
            || ($district !== '' && $rowDistrict !== '' && $district === $rowDistrict);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function trackingResultFromReportRow(array $row, Shipment $shipment, string $strategy): array
    {
        $status = $this->shipmentStatusFromReportRow($row);
        $lastEventAt = $this->parseCarrierDate($row['last_event_at'] ?? null)
            ?: $this->parseCarrierDate($row['document_date'] ?? null)
            ?: $this->parseCarrierDate($row['created_at'] ?? null);
        $statusLabel = trim((string) ($row['status'] ?? ''));
        $actualCost = (float) ($row['total_amount'] ?? 0);

        return array_filter([
            'success' => true,
            'tracking_number' => filled($row['tracking_number'] ?? null)
                ? (string) $row['tracking_number']
                : $shipment->tracking_number,
            'barcode' => $shipment->barcode,
            'status' => $status,
            'status_label' => $statusLabel !== '' ? $statusLabel : $this->shipmentStatusLabel($status),
            'delivered_at' => $row['delivered_at'] ?? null,
            'shipped_at' => $lastEventAt?->toDateTimeString(),
            'last_event_at' => $lastEventAt?->toDateTimeString(),
            'actual_cost' => $actualCost > 0 ? $actualCost : null,
            'actual_desi' => (float) ($row['measurement_desi'] ?: $row['desi'] ?: 0) ?: null,
            'events' => [[
                'event_code' => 'surat_report_sync',
                'event_status' => $status,
                'event_description' => $statusLabel !== '' ? $statusLabel : $this->shipmentStatusLabel($status),
                'event_at' => $lastEventAt?->toDateTimeString() ?: now()->toDateTimeString(),
                'raw_payload' => $row,
            ]],
            'raw_payload' => [
                'source' => 'woocommerce_surat_auto_match',
                'match_strategy' => $strategy,
                'report_row' => $row,
            ],
        ], fn ($value) => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function shipmentStatusFromReportRow(array $row): string
    {
        $number = filled($row['status_code'] ?? null) && is_numeric($row['status_code'])
            ? (int) $row['status_code']
            : null;

        if ($number !== null) {
            return match (true) {
                $number === 1 => 'ready',
                in_array($number, [2, 3, 4, 7, 8, 9, 10, 11], true) => 'in_transit',
                in_array($number, [5, 15], true) => 'out_for_delivery',
                $number === 6 => 'delivered',
                in_array($number, [12, 13, 14, 16], true) => 'returned',
                default => $this->shipmentStatusFromLabel((string) ($row['status'] ?? '')),
            };
        }

        return $this->shipmentStatusFromLabel((string) ($row['status'] ?? ''));
    }

    protected function shipmentStatusFromLabel(string $label): string
    {
        $normalized = Str::lower(Str::ascii(trim($label)));

        return match (true) {
            $normalized === '' => 'ready',
            Str::contains($normalized, ['teslim edildi', 'delivered', 'completed']) => 'delivered',
            Str::contains($normalized, ['dagitim', 'delivery']) => 'out_for_delivery',
            Str::contains($normalized, ['evrak', 'hazir', 'tamam', 'olusturuldu']) => 'ready',
            Str::contains($normalized, ['yolda', 'transfer', 'tasima', 'transit', 'shipped', 'aktarma']) => 'in_transit',
            Str::contains($normalized, ['iptal', 'cancel']) => 'cancelled',
            Str::contains($normalized, ['iade', 'return']) => 'returned',
            Str::contains($normalized, ['hata', 'sorun', 'exception', 'failed']) => 'exception',
            default => 'shipped',
        };
    }

    protected function shipmentStatusLabel(string $status): string
    {
        return match ($status) {
            'delivered' => 'Teslim edildi',
            'out_for_delivery' => 'Dağıtımda',
            'in_transit' => 'Yolda',
            'returned' => 'İade edildi',
            'cancelled' => 'İptal edildi',
            'exception', 'failed' => 'Sorunlu',
            'ready', 'label_created' => 'Hazır',
            default => 'Kargoya verildi',
        };
    }

    protected function markRowUsed(array &$usedRows, array $row): void
    {
        $identity = $this->rowIdentity($row);

        if ($identity !== '') {
            $usedRows[$identity] = true;
        }
    }

    protected function rowIsUsed(array $usedRows, array $row): bool
    {
        $identity = $this->rowIdentity($row);

        return $identity !== '' && isset($usedRows[$identity]);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function rowIdentity(array $row): string
    {
        $identity = (string) (
            $row['tracking_number']
            ?? $row['web_order_code']
            ?? $row['sales_code']
            ?? ''
        );

        if ($identity !== '') {
            return $identity;
        }

        return hash('sha1', json_encode([
            $row['customer_name'] ?? null,
            $row['status'] ?? null,
            $row['document_date'] ?? null,
            $row['created_at'] ?? null,
            $row['last_event_at'] ?? null,
        ]));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function rowDate(array $row): ?Carbon
    {
        return $this->parseCarrierDate($row['document_date'] ?? null)
            ?: $this->parseCarrierDate($row['created_at'] ?? null)
            ?: $this->parseCarrierDate($row['last_event_at'] ?? null);
    }

    protected function parseCarrierDate(mixed $value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function normalizeReference(mixed $value): string
    {
        return Str::of((string) $value)
            ->trim()
            ->ascii()
            ->upper()
            ->replaceMatches('/[^A-Z0-9]+/', '')
            ->value();
    }

    protected function normalizePersonName(mixed $value): string
    {
        return Str::of((string) $value)
            ->trim()
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->value();
    }

    protected function normalizePlace(mixed $value): string
    {
        return Str::of((string) $value)
            ->trim()
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->value();
    }

    protected function requiredTablesReady(): bool
    {
        return Schema::hasTable('cargo_carrier_accounts')
            && Schema::hasTable('channel_orders')
            && Schema::hasTable('channel_order_packages')
            && Schema::hasTable('shipments');
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function emptySummary(array $extra = []): array
    {
        return array_merge([
            'accounts' => 0,
            'scanned' => 0,
            'report_rows' => 0,
            'matched' => 0,
            'updated' => 0,
            'unmatched' => 0,
            'ambiguous' => 0,
            'failed' => 0,
            'errors' => [],
        ], $extra);
    }
}
