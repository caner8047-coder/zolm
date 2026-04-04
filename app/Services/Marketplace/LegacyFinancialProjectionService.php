<?php

namespace App\Services\Marketplace;

use App\Models\ChannelOrder;
use App\Models\MarketplaceStore;
use App\Models\MpOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class LegacyFinancialProjectionService
{
    public function __construct(
        protected MarketplaceFinancialSyncService $financialSyncService,
        protected MarketplaceProfitSnapshotService $profitSnapshotService,
    ) {
    }

    /**
     * @return array{projected_rows:int,created:int,updated:int,skipped:int,impacted_order_ids:array<int>}
     */
    public function projectStore(MarketplaceStore $store, bool $onlyUnprojected = true, int $limit = 0): array
    {
        return $this->projectRows($store, $this->candidateRows($store, $onlyUnprojected, $limit));
    }

    /**
     * @return array{projected_rows:int,created:int,updated:int,skipped:int,impacted_order_ids:array<int>}
     */
    public function previewStore(MarketplaceStore $store, bool $onlyUnprojected = true, int $limit = 0): array
    {
        return [
            'projected_rows' => $this->candidateRows($store, $onlyUnprojected, $limit)->count(),
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'impacted_order_ids' => [],
        ];
    }

    /**
     * @param  iterable<int, MpOrder>  $rows
     * @return array{projected_rows:int,created:int,updated:int,skipped:int,impacted_order_ids:array<int>}
     */
    public function projectRows(MarketplaceStore $store, iterable $rows): array
    {
        $rows = $rows instanceof Collection ? $rows : collect($rows);
        $events = [];
        $rowIds = [];

        foreach ($rows as $row) {
            if (!$row instanceof MpOrder) {
                continue;
            }

            $rowEvents = $this->eventsForRow($store, $row);

            if ($rowEvents === []) {
                continue;
            }

            $rowIds[] = $row->id;
            $events = array_merge($events, $rowEvents);
        }

        if ($events === []) {
            return [
                'projected_rows' => 0,
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'impacted_order_ids' => [],
            ];
        }

        $sync = $this->financialSyncService->sync($store, $events);
        $this->profitSnapshotService->recalculateForOrders($store, $sync['impacted_order_ids']);

        MpOrder::query()
            ->whereIn('id', $rowIds)
            ->update([
                'store_id' => $store->id,
                'legal_entity_id' => $store->legal_entity_id,
                'source_marketplace' => $store->marketplace,
                'projected_at' => now(),
            ]);

        return array_merge($sync, [
            'projected_rows' => count($rowIds),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function eventsForRow(MarketplaceStore $store, MpOrder $row): array
    {
        $orderNumber = trim((string) $row->order_number);

        if ($orderNumber === '') {
            return [];
        }

        $settlement = $row->settlement;
        $dueDate = optional($settlement?->due_date)->toDateTimeString();
        $settlementDate = optional($settlement?->settlement_date)->toDateTimeString()
            ?: optional($row->payment_date)->toDateTimeString();
        $eventDate = optional($row->payment_date)->toDateTimeString()
            ?: optional($row->delivery_date)->toDateTimeString()
            ?: optional($row->order_date)->toDateTimeString();
        $reference = (string) ($settlement?->document_number ?: $row->order_number ?: $row->id);

        $cargo = abs((float) $row->cargo_amount);
        $serviceFee = abs((float) $row->service_fee);
        $withholding = abs((float) $row->withholding_tax);
        $commission = abs((float) $row->commission_amount);
        $netHakedis = round((float) $row->net_hakedis, 2);
        $sellerRevenueBasis = round($netHakedis + $cargo + $serviceFee + $withholding, 2);

        $baseMeta = [
            'order_number' => $orderNumber,
            'external_package_id' => null,
            'external_line_id' => null,
            'stock_code' => $row->stock_code,
            'barcode' => $row->barcode,
            'reference_number' => $reference,
            'event_date' => $eventDate,
            'due_date' => $dueDate,
            'settlement_date' => $settlementDate,
            'currency' => 'TRY',
            'status' => 'posted',
            'raw_payload' => [
                'source' => 'legacy_mp_order',
                'mp_order_id' => $row->id,
                'mp_settlement_id' => $settlement?->id,
                'net_hakedis' => $netHakedis,
            ],
        ];

        $events = [];

        if (round($sellerRevenueBasis, 2) !== 0.0) {
            $events[] = $this->buildEvent(
                rowId: $row->id,
                type: 'seller_revenue',
                amount: $sellerRevenueBasis,
                notes: 'Legacy net hakediş bazlı gelir',
                baseMeta: $baseMeta,
            );
        }

        if ($commission > 0) {
            $events[] = $this->buildEvent(
                rowId: $row->id,
                type: 'commission',
                amount: $commission,
                notes: 'Legacy komisyon',
                baseMeta: $baseMeta,
            );
        }

        if ($cargo > 0) {
            $events[] = $this->buildEvent(
                rowId: $row->id,
                type: 'cargo',
                amount: $cargo,
                notes: 'Legacy kargo kesintisi',
                baseMeta: $baseMeta,
            );
        }

        if ($serviceFee > 0) {
            $events[] = $this->buildEvent(
                rowId: $row->id,
                type: 'service_fee',
                amount: $serviceFee,
                notes: 'Legacy hizmet bedeli',
                baseMeta: $baseMeta,
            );
        }

        if ($withholding > 0) {
            $events[] = $this->buildEvent(
                rowId: $row->id,
                type: 'withholding',
                amount: $withholding,
                notes: 'Legacy stopaj',
                baseMeta: $baseMeta,
            );
        }

        return $events;
    }

    /**
     * @param  array<string, mixed>  $baseMeta
     * @return array<string, mixed>
     */
    protected function buildEvent(int $rowId, string $type, float $amount, string $notes, array $baseMeta): array
    {
        $direction = in_array($type, ['commission', 'cargo', 'service_fee', 'withholding'], true)
            ? 'debit'
            : ($amount >= 0 ? 'credit' : 'debit');

        return [
            'event_source' => 'legacy_mp_order',
            'event_type' => $type,
            'external_event_id' => sha1('legacy_mp_order|'.$rowId.'|'.$type),
            'amount' => abs($amount),
            'direction' => $direction,
            'notes' => $notes,
        ] + $baseMeta;
    }

    /**
     * @return Collection<int, MpOrder>
     */
    protected function candidateRows(MarketplaceStore $store, bool $onlyUnprojected = true, int $limit = 0): Collection
    {
        $channelOrderNumbers = ChannelOrder::query()
            ->where('store_id', $store->id)
            ->pluck('order_number')
            ->filter()
            ->unique()
            ->values();

        if ($channelOrderNumbers->isEmpty()) {
            return collect();
        }

        $query = MpOrder::query()
            ->with(['settlement', 'period'])
            ->whereHas('period', fn (Builder $builder) => $builder->where('user_id', $store->user_id))
            ->whereIn('order_number', $channelOrderNumbers)
            ->where(function (Builder $builder) use ($store) {
                $builder->where('store_id', $store->id)
                    ->orWhereNull('store_id');
            })
            ->orderByDesc('order_date')
            ->orderByDesc('id');

        if ($onlyUnprojected) {
            $query->whereNull('projected_at');
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        return $query->get();
    }
}
