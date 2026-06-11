<?php

namespace App\Services\Cargo;

use App\Models\CargoCarrierAccount;
use App\Models\CargoInvoiceLine;
use App\Models\ChannelClaim;
use App\Models\ChannelOrder;
use App\Models\ChannelOrderPackage;
use App\Models\Shipment;
use App\Models\SupplyOrder;
use App\Services\Marketplace\MarketplaceProfitSnapshotService;
use App\Services\ProductCompositionResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CargoShipmentService
{
    public function __construct(
        protected SuratCargoConnector $suratConnector,
        protected ProductCompositionResolver $compositionResolver,
    ) {
    }

    public function defaultAccount(?int $userId = null, ?int $legalEntityId = null, string $carrierCode = 'surat'): ?CargoCarrierAccount
    {
        $userId = $userId ?: auth()->id();

        if (!$userId) {
            return null;
        }

        return CargoCarrierAccount::query()
            ->where('user_id', $userId)
            ->where('carrier_code', $carrierCode)
            ->where('is_active', true)
            ->when($legalEntityId, fn ($query) => $query->where(function ($subQuery) use ($legalEntityId) {
                $subQuery->where('legal_entity_id', $legalEntityId)
                    ->orWhereNull('legal_entity_id');
            }))
            ->orderByDesc('is_default')
            ->orderByDesc('legal_entity_id')
            ->latest('id')
            ->first();
    }

    public function createOrUpdateFromPackage(ChannelOrderPackage $package, ?CargoCarrierAccount $account = null): Shipment
    {
        $package->loadMissing(['order.items.product.productSet.items.componentProduct', 'order.store', 'items.product.productSet.items.componentProduct']);
        $order = $package->order;
        $store = $package->store ?: $order?->store;
        $account = $account ?: $this->defaultAccount($store?->user_id, $store?->legal_entity_id);

        if (!$order || !$store) {
            throw new \RuntimeException('Gönderi oluşturmak için pazaryeri sipariş ve mağaza bilgisi gerekli.');
        }

        return DB::transaction(function () use ($package, $order, $store, $account) {
            $shipment = Shipment::query()->firstOrNew([
                'channel_order_package_id' => $package->id,
                'flow_type' => 'order',
                'carrier_code' => 'surat',
            ]);

            if (!$shipment->exists) {
                $shipment->shipment_no = $this->nextShipmentNo();
            }

            $items = $package->items->isNotEmpty()
                ? $package->items
                : $order->items->where('channel_order_package_id', $package->id);

            if ($items->isEmpty()) {
                $items = $order->items;
            }

            $totals = $this->calculateItemTotals($items);
            $invoiceCost = (float) ($shipment->invoice_cost ?? 0);

            $shipment->fill([
                'user_id' => $store->user_id,
                'legal_entity_id' => $store->legal_entity_id,
                'store_id' => $store->id,
                'channel_order_id' => $order->id,
                'cargo_carrier_account_id' => $account?->id,
                'source_type' => 'marketplace_order',
                'direction' => 'outgoing',
                'carrier_name' => 'Sürat Kargo',
                'reference_number' => $package->package_number ?: $order->order_number,
                'order_number' => $order->order_number,
                'package_number' => $package->package_number,
                'tracking_number' => $shipment->tracking_number ?: $package->cargo_tracking_number,
                'barcode' => $shipment->barcode ?: $package->cargo_barcode,
                'status' => $shipment->exists ? $shipment->status : 'draft',
                'customer_name' => $order->customer_name ?: $order->billing_name,
                'customer_phone' => $order->customer_phone,
                'destination_city' => $order->shipment_city,
                'destination_district' => $order->shipment_district,
                'destination_address' => $this->orderAddress($order->raw_payload),
                'sender_name' => $account?->account_name,
                'sender_phone' => $account?->contact_phone,
                'origin_city' => $account?->origin_city,
                'origin_district' => $account?->origin_district,
                'origin_address' => $account?->origin_address,
                'parcel_count' => max(1, (int) $totals['pieces']),
                'total_desi' => $totals['desi'],
                'total_weight' => $totals['weight'],
                'expected_cost' => $totals['cost'],
                'cost_delta' => $invoiceCost > 0 ? round($invoiceCost - (float) $totals['cost'], 2) : (float) $shipment->cost_delta,
                'currency' => $store->currency ?: 'TRY',
                'raw_payload' => [
                    'order' => $order->raw_payload,
                    'package' => $package->raw_payload,
                ],
                'meta_json' => [
                    'marketplace' => $store->marketplace,
                    'store_name' => $store->store_name,
                    'uses_own_cargo' => (bool) $store->uses_own_cargo,
                ],
            ]);
            $shipment->save();

            $this->syncItems($shipment, $items);
            $this->syncParcel($shipment);
            $this->upsertExpectedCost($shipment);

            return $shipment->fresh(['items', 'parcels', 'costs']);
        });
    }

    public function createOrUpdateFromClaim(ChannelClaim $claim, ?CargoCarrierAccount $account = null): Shipment
    {
        $claim->loadMissing(['store', 'items']);
        $store = $claim->store;
        $account = $account ?: $this->defaultAccount($store?->user_id, $store?->legal_entity_id);

        if (!$store) {
            throw new \RuntimeException('İade/değişim gönderisi için mağaza bilgisi gerekli.');
        }

        return DB::transaction(function () use ($claim, $store, $account) {
            $flowType = $claim->type === 'exchange' ? 'exchange' : 'return';
            $order = filled($claim->order_number)
                ? ChannelOrder::query()
                    ->where('store_id', $store->id)
                    ->where('order_number', $claim->order_number)
                    ->first()
                : null;

            $shipment = Shipment::query()->firstOrNew([
                'channel_claim_id' => $claim->id,
                'flow_type' => $flowType,
                'carrier_code' => 'surat',
            ]);

            if (!$shipment->exists) {
                $shipment->shipment_no = $this->nextShipmentNo();
            }

            $shipment->fill([
                'user_id' => $store->user_id,
                'legal_entity_id' => $store->legal_entity_id,
                'store_id' => $store->id,
                'channel_order_id' => $order?->id,
                'cargo_carrier_account_id' => $account?->id,
                'source_type' => 'marketplace_claim',
                'direction' => 'incoming',
                'carrier_name' => 'Sürat Kargo',
                'reference_number' => $claim->external_claim_id,
                'order_number' => $claim->order_number,
                'tracking_number' => $shipment->tracking_number ?: $claim->cargo_tracking_number,
                'status' => $shipment->exists ? $shipment->status : 'draft',
                'customer_name' => $claim->customer_name,
                'sender_name' => $claim->customer_name,
                'origin_city' => null,
                'destination_city' => $account?->origin_city,
                'destination_district' => $account?->origin_district,
                'destination_address' => $account?->origin_address,
                'parcel_count' => max(1, (int) $claim->items->sum('quantity')),
                'total_desi' => 0,
                'expected_cost' => 0,
                'currency' => $store->currency ?: 'TRY',
                'raw_payload' => $claim->raw_payload,
                'meta_json' => [
                    'marketplace' => $store->marketplace,
                    'claim_status' => $claim->status,
                    'claim_reason' => $claim->reason,
                ],
            ]);
            $shipment->save();

            $shipment->items()->delete();

            foreach ($claim->items as $item) {
                $shipment->items()->create([
                    'channel_claim_item_id' => $item->id,
                    'stock_code' => $item->stock_code,
                    'barcode' => $item->barcode,
                    'product_name' => $item->product_name,
                    'quantity' => max(1, (int) $item->quantity),
                    'unit_price' => (float) ($item->price ?? 0),
                    'expected_pieces' => max(1, (int) $item->quantity),
                    'meta_json' => ['claim_item_status' => $item->status],
                ]);
            }

            $this->syncParcel($shipment);
            $this->upsertExpectedCost($shipment);

            return $shipment->fresh(['items', 'parcels', 'costs']);
        });
    }

    public function createOrUpdateFromSupplyOrder(SupplyOrder $supplyOrder, ?CargoCarrierAccount $account = null): Shipment
    {
        $account = $account ?: $this->defaultAccount(auth()->id());

        return DB::transaction(function () use ($supplyOrder, $account) {
            $shipment = Shipment::query()->firstOrNew([
                'supply_order_id' => $supplyOrder->id,
                'flow_type' => 'supply',
                'carrier_code' => 'surat',
            ]);

            if (!$shipment->exists) {
                $shipment->shipment_no = $this->nextShipmentNo();
            }

            $shipment->fill([
                'user_id' => auth()->id() ?: $account?->user_id ?: 1,
                'legal_entity_id' => $account?->legal_entity_id,
                'cargo_carrier_account_id' => $account?->id,
                'source_type' => 'supply_order',
                'direction' => 'outgoing',
                'carrier_name' => 'Sürat Kargo',
                'reference_number' => $supplyOrder->siparis_no,
                'order_number' => $supplyOrder->siparis_no,
                'status' => $shipment->exists ? $shipment->status : 'draft',
                'customer_name' => $supplyOrder->musteri_adi,
                'customer_phone' => $supplyOrder->telefon,
                'destination_city' => $supplyOrder->il,
                'destination_district' => $supplyOrder->ilce,
                'destination_address' => $supplyOrder->adres,
                'sender_name' => $account?->account_name,
                'sender_phone' => $account?->contact_phone,
                'origin_city' => $account?->origin_city,
                'origin_district' => $account?->origin_district,
                'origin_address' => $account?->origin_address,
                'parcel_count' => max(1, (int) $supplyOrder->adet),
                'raw_payload' => ['supply_order_id' => $supplyOrder->id],
                'meta_json' => ['category' => $supplyOrder->kategori],
            ]);
            $shipment->save();

            $shipment->items()->delete();
            $shipment->items()->create([
                'product_name' => $supplyOrder->urun_adi,
                'quantity' => max(1, (int) $supplyOrder->adet),
                'expected_pieces' => max(1, (int) $supplyOrder->adet),
            ]);

            $this->syncParcel($shipment);
            $this->upsertExpectedCost($shipment);

            return $shipment->fresh(['items', 'parcels', 'costs']);
        });
    }

    public function pushToCarrier(Shipment $shipment, ?CargoCarrierAccount $account = null): Shipment
    {
        $account = $account ?: $shipment->carrierAccount ?: $this->defaultAccount($shipment->user_id, $shipment->legal_entity_id);

        if (!$account) {
            throw new \RuntimeException('Sürat hesap bilgisi bulunamadı. Önce Sürat Entegrasyon sekmesinde hesap tanımlayın.');
        }

        $result = $this->suratConnector->createShipment($account, $shipment);

        $shipment->forceFill([
            'cargo_carrier_account_id' => $account->id,
            'external_shipment_id' => $result['external_shipment_id'] ?? $shipment->external_shipment_id,
            'tracking_number' => $result['tracking_number'] ?? $shipment->tracking_number,
            'barcode' => $result['barcode'] ?? $shipment->barcode,
            'status' => $result['status'] ?? 'label_created',
            'status_label' => $result['status_label'] ?? 'Barkod oluşturuldu',
            'raw_payload' => $result['raw_payload'] ?? $shipment->raw_payload,
            'last_error' => null,
        ])->save();

        $this->syncParcel($shipment);

        if ($shipment->package) {
            $shipment->package->update([
                'cargo_company' => 'Sürat Kargo',
                'cargo_tracking_number' => $shipment->tracking_number ?: $shipment->package->cargo_tracking_number,
                'cargo_barcode' => $shipment->barcode ?: $shipment->package->cargo_barcode,
                'package_status' => in_array($shipment->status, ['ready', 'label_created'], true) ? 'LabelCreated' : $shipment->package->package_status,
                'last_synced_at' => now(),
            ]);
        }

        $this->recordEvent($shipment, [
            'event_code' => 'label_created',
            'event_status' => $shipment->status,
            'event_description' => $shipment->status_label ?: 'Barkod oluşturuldu',
            'event_at' => now(),
            'raw_payload' => $result,
        ]);

        return $shipment->fresh(['events', 'parcels', 'items']);
    }

    public function refreshTracking(Shipment $shipment, ?CargoCarrierAccount $account = null): Shipment
    {
        $account = $account ?: $shipment->carrierAccount ?: $this->defaultAccount($shipment->user_id, $shipment->legal_entity_id);

        if (!$account) {
            throw new \RuntimeException('Sürat sorgulama hesabı bulunamadı.');
        }

        $result = $this->suratConnector->trackShipment($account, $shipment);

        return $this->applyTrackingResult($shipment, $result);
    }

    public function cancelShipment(Shipment $shipment, ?CargoCarrierAccount $account = null, array $context = []): Shipment
    {
        $account = $account ?: $shipment->carrierAccount ?: $this->defaultAccount($shipment->user_id, $shipment->legal_entity_id);

        if (!$account) {
            throw new \RuntimeException('Sürat iptal hesabı bulunamadı.');
        }

        $result = $this->suratConnector->cancelShipment($account, $shipment, $context);

        $shipment->forceFill([
            'cargo_carrier_account_id' => $account->id,
            'status' => $result['status'] ?? 'cancelled',
            'status_label' => $result['status_label'] ?? 'İptal edildi',
            'cancelled_at' => now(),
            'last_error' => null,
            'raw_payload' => $result['raw_payload'] ?? $shipment->raw_payload,
        ])->save();

        $this->syncParcel($shipment);

        if ($shipment->package) {
            $shipment->package->update([
                'package_status' => 'Cancelled',
                'last_synced_at' => now(),
            ]);
        }

        $this->recordEvent($shipment, [
            'event_code' => 'cancelled',
            'event_status' => 'cancelled',
            'event_description' => $shipment->status_label ?: 'İptal edildi',
            'event_at' => now(),
            'raw_payload' => $result,
        ]);

        return $shipment->fresh(['events', 'parcels', 'items']);
    }

    public function applyTrackingResult(Shipment $shipment, array $result): Shipment
    {
        $actualCost = (float) ($result['actual_cost'] ?? 0);
        $actualDesi = (float) ($result['actual_desi'] ?? 0);
        $financialUpdates = [];
        $status = $result['status'] ?? $shipment->status;
        $shippedAt = $shipment->shipped_at;
        $deliveredAt = $this->parseDate($result['delivered_at'] ?? null)
            ?: ($status === 'delivered' ? ($shipment->delivered_at ?: now()) : $shipment->delivered_at);

        if ($actualCost > 0) {
            $financialUpdates['actual_cost'] = $actualCost;
            $financialUpdates['cost_delta'] = round($actualCost - (float) $shipment->expected_cost, 2);
        }

        if ($actualDesi > 0) {
            $financialUpdates['total_desi'] = max((float) $shipment->total_desi, $actualDesi);
        }

        if (!$shippedAt && $this->isCarrierShippedStatus((string) $status)) {
            $shippedAt = $this->parseDate($result['shipped_at'] ?? null)
                ?: $this->parseDate($result['last_event_at'] ?? null)
                ?: now();
        }

        $shipment->forceFill(array_merge([
            'tracking_number' => $result['tracking_number'] ?? $shipment->tracking_number,
            'barcode' => $result['barcode'] ?? $shipment->barcode,
            'status' => $status,
            'status_label' => $result['status_label'] ?? $shipment->status_label,
            'shipped_at' => $shippedAt,
            'delivered_at' => $deliveredAt,
            'last_tracked_at' => now(),
            'last_error' => null,
            'raw_payload' => $result['raw_payload'] ?? $shipment->raw_payload,
        ], $financialUpdates))->save();

        if ($actualCost > 0) {
            $shipment->costs()->updateOrCreate([
                'cost_source' => 'carrier_tracking',
                'cost_type' => 'shipping',
            ], [
                'amount' => $actualCost,
                'direction' => 'debit',
                'currency' => $shipment->currency ?: 'TRY',
                'cost_date' => now(),
                'raw_payload' => $result['raw_payload'] ?? $result,
            ]);
        }

        foreach (($result['events'] ?? []) as $event) {
            $this->recordEvent($shipment, $event);
        }

        $this->syncParcel($shipment);

        if ($shipment->package) {
            $packageStatus = $this->packageStatusForCarrierStatus((string) $shipment->status, $shipment->package->package_status);

            $shipment->package->update([
                'cargo_company' => 'Sürat Kargo',
                'cargo_tracking_number' => $shipment->tracking_number ?: $shipment->package->cargo_tracking_number,
                'cargo_barcode' => $shipment->barcode ?: $shipment->package->cargo_barcode,
                'package_status' => $packageStatus,
                'shipped_at' => $this->isCarrierShippedStatus((string) $shipment->status)
                    ? ($shipment->package->shipped_at ?: $shipment->shipped_at ?: now())
                    : $shipment->package->shipped_at,
                'delivered_at' => ($result['status'] ?? null) === 'delivered' ? ($shipment->delivered_at ?: now()) : $shipment->package->delivered_at,
                'last_synced_at' => now(),
            ]);
        }

        $this->syncOrderStatusFromShipment($shipment);

        if ($actualCost > 0) {
            $this->recalculateProfit($shipment);
        }

        return $shipment->fresh(['events', 'parcels', 'items']);
    }

    protected function isCarrierShippedStatus(string $status): bool
    {
        return in_array($status, ['shipped', 'in_transit', 'out_for_delivery', 'delivered'], true);
    }

    protected function packageStatusForCarrierStatus(string $status, ?string $currentStatus = null): string
    {
        return match ($status) {
            'delivered' => 'Delivered',
            'returned' => 'Returned',
            'cancelled' => 'Cancelled',
            'failed' => 'Delivery failed',
            'exception' => 'Exception',
            'out_for_delivery' => 'Out for delivery',
            'in_transit' => 'In transit',
            'shipped' => 'Shipped',
            default => $currentStatus ?: 'Processing',
        };
    }

    protected function syncOrderStatusFromShipment(Shipment $shipment): void
    {
        if (!$shipment->order) {
            return;
        }

        $current = Str::lower(Str::ascii((string) $shipment->order->order_status));
        $currentIsFinal = Str::contains($current, ['cancel', 'iptal', 'return', 'iade', 'refund', 'reject', 'redd']);
        $status = (string) $shipment->status;

        if ($currentIsFinal) {
            return;
        }

        if ($status === 'delivered') {
            $shipment->order->update([
                'order_status' => Str::contains($current, ['completed', 'tamam']) ? $shipment->order->order_status : 'Delivered',
                'delivered_at' => $shipment->order->delivered_at ?: $shipment->delivered_at ?: now(),
                'last_synced_at' => now(),
            ]);

            return;
        }

        if (!$this->isCarrierShippedStatus($status) || Str::contains($current, ['delivered', 'completed', 'teslim', 'tamam'])) {
            return;
        }

        $shipment->order->update([
            'order_status' => match ($status) {
                'out_for_delivery' => 'Out for delivery',
                'in_transit' => 'In transit',
                default => 'Shipped',
            },
            'last_synced_at' => now(),
        ]);
    }

    public function reconcileInvoiceLine(CargoInvoiceLine $line): ?Shipment
    {
        $shipment = Shipment::query()
            ->where('user_id', $line->user_id)
            ->where(function ($query) use ($line) {
                if ($line->tracking_number) {
                    $query->orWhere('tracking_number', $line->tracking_number)
                        ->orWhere('barcode', $line->tracking_number);
                }

                if ($line->barcode) {
                    $query->orWhere('barcode', $line->barcode)
                        ->orWhere('tracking_number', $line->barcode);
                }

                if ($line->order_reference) {
                    $query->orWhere('order_number', $line->order_reference)
                        ->orWhere('reference_number', $line->order_reference)
                        ->orWhere('package_number', $line->order_reference);
                }
            })
            ->latest('id')
            ->first();

        if (!$shipment) {
            $line->forceFill([
                'is_reconciled' => false,
                'discrepancy_type' => 'shipment_missing',
            ])->save();

            return null;
        }

        $actualAmount = (float) ($line->total_amount ?: $line->amount);
        $delta = round($actualAmount - (float) $shipment->expected_cost, 2);

        $shipment->forceFill([
            'invoice_cost' => $actualAmount,
            'actual_cost' => $actualAmount,
            'cost_delta' => $delta,
            'total_desi' => max((float) $shipment->total_desi, (float) $line->desi),
        ])->save();

        $line->forceFill([
            'shipment_id' => $shipment->id,
            'is_reconciled' => true,
            'discrepancy_type' => abs($delta) > (float) config('cargo.tolerances.tutar', 5) ? 'amount_mismatch' : null,
        ])->save();

        $shipment->costs()->updateOrCreate([
            'cargo_invoice_line_id' => $line->id,
            'cost_source' => 'carrier_invoice',
            'cost_type' => 'shipping',
        ], [
            'amount' => $actualAmount,
            'direction' => 'debit',
            'currency' => $line->currency,
            'external_reference' => $line->invoice_number,
            'cost_date' => $line->invoice_date,
            'raw_payload' => $line->raw_payload,
        ]);

        $this->syncParcel($shipment);
        $this->recalculateProfit($shipment);

        return $shipment->fresh(['costs', 'invoiceLines']);
    }

    protected function syncItems(Shipment $shipment, iterable $items): void
    {
        $shipment->items()->delete();

        foreach ($items as $item) {
            $product = $item->product;
            $quantity = max(1, (int) $item->quantity);
            $composition = $product
                ? $this->compositionResolver->resolve($product, $quantity)
                : ['pieces' => $quantity, 'desi' => (float) ($item->cargo_desi ?? 0) * $quantity, 'own_cargo_cost' => 0];

            $shipment->items()->create([
                'channel_order_item_id' => $item->id,
                'mp_product_id' => $item->mp_product_id,
                'stock_code' => $item->stock_code,
                'barcode' => $item->barcode,
                'product_name' => $item->product_name,
                'quantity' => $quantity,
                'unit_price' => (float) ($item->unit_price ?: $item->gross_amount ?: 0),
                'expected_pieces' => max(1, (int) ($composition['pieces'] ?? $quantity)),
                'expected_desi' => round((float) ($composition['desi'] ?? 0), 2),
                'expected_cost' => round((float) ($composition['own_cargo_cost'] ?? 0), 2),
                'meta_json' => [
                    'line_status' => $item->line_status,
                    'is_matched' => (bool) $item->is_matched,
                ],
            ]);
        }
    }

    protected function syncParcel(Shipment $shipment): void
    {
        $shipment->parcels()->updateOrCreate([
            'parcel_index' => 1,
        ], [
            'tracking_number' => $shipment->tracking_number,
            'barcode' => $shipment->barcode,
            'desi' => $shipment->total_desi,
            'weight' => $shipment->total_weight,
            'piece_count' => max(1, (int) $shipment->parcel_count),
            'status' => $shipment->status,
        ]);
    }

    protected function upsertExpectedCost(Shipment $shipment): void
    {
        $shipment->costs()->updateOrCreate([
            'cost_source' => 'expected',
            'cost_type' => 'shipping',
        ], [
            'amount' => (float) $shipment->expected_cost,
            'direction' => 'debit',
            'currency' => $shipment->currency ?: 'TRY',
            'cost_date' => now(),
            'raw_payload' => [
                'total_desi' => (float) $shipment->total_desi,
                'parcel_count' => (int) $shipment->parcel_count,
            ],
        ]);
    }

    protected function recordEvent(Shipment $shipment, array $event): void
    {
        $eventAt = $this->parseDate($event['event_at'] ?? null) ?: now();
        $description = trim((string) ($event['event_description'] ?? ''));

        $exists = $shipment->events()
            ->where('event_status', $event['event_status'] ?? null)
            ->where('event_description', $description)
            ->where('event_at', $eventAt)
            ->exists();

        if (!$exists) {
            $shipment->events()->create([
                'carrier_code' => 'surat',
                'event_code' => $event['event_code'] ?? null,
                'event_status' => $event['event_status'] ?? null,
                'event_description' => $description,
                'location_city' => $event['location_city'] ?? null,
                'location_district' => $event['location_district'] ?? null,
                'branch_name' => $event['branch_name'] ?? null,
                'event_at' => $eventAt,
                'received_at' => now(),
                'is_terminal' => in_array($event['event_status'] ?? null, Shipment::TERMINAL_STATUSES, true),
                'raw_payload' => $event['raw_payload'] ?? $event,
            ]);
        }

        $shipment->forceFill(['last_event_at' => $eventAt])->save();
    }

    protected function calculateItemTotals(iterable $items): array
    {
        $pieces = 0;
        $desi = 0.0;
        $weight = 0.0;
        $cost = 0.0;

        foreach ($items as $item) {
            $quantity = max(1, (int) $item->quantity);
            $product = $item->product;
            $composition = $product
                ? $this->compositionResolver->resolve($product, $quantity)
                : ['pieces' => $quantity, 'desi' => (float) ($item->cargo_desi ?? 0) * $quantity, 'own_cargo_cost' => 0];

            $pieces += max(1, (int) ($composition['pieces'] ?? $quantity));
            $desi += (float) ($composition['desi'] ?? 0);
            $cost += (float) ($composition['own_cargo_cost'] ?? 0);
        }

        return [
            'pieces' => max(1, $pieces),
            'desi' => round($desi, 2),
            'weight' => round($weight, 2),
            'cost' => round($cost, 2),
        ];
    }

    protected function recalculateProfit(Shipment $shipment): void
    {
        if (!$shipment->store || !$shipment->channel_order_id || !Schema::hasTable('order_profit_snapshots')) {
            return;
        }

        app(MarketplaceProfitSnapshotService::class)->recalculateForOrders($shipment->store, [$shipment->channel_order_id]);
    }

    protected function orderAddress(?array $payload): ?string
    {
        return data_get($payload, 'shipmentAddress.fullAddress')
            ?: data_get($payload, 'shipmentAddress.address')
            ?: data_get($payload, 'shippingAddress.fullAddress')
            ?: data_get($payload, 'shippingAddress.address')
            ?: data_get($payload, 'address.fullAddress');
    }

    protected function parseDate(mixed $value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function nextShipmentNo(): string
    {
        do {
            $number = 'SHP-' . now()->format('Ymd-His') . '-' . Str::upper(Str::random(5));
        } while (Shipment::query()->where('shipment_no', $number)->exists());

        return $number;
    }
}
