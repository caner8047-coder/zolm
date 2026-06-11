<?php

namespace App\Services\Crm;

use App\Models\ChannelOrder;
use App\Models\ChannelOrderItem;
use App\Models\CrmContact;
use App\Models\CrmCustomerLedgerEntry;
use App\Models\CrmTimelineEvent;
use App\Models\MarketplaceStore;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CrmCustomerLedgerProjectionService
{
    public function __construct(
        protected CrmIdentityResolver $identityResolver,
    ) {
    }

    public function tablesReady(): bool
    {
        return Schema::hasTable('crm_contacts')
            && Schema::hasTable('crm_customer_ledger_entries');
    }

    /**
     * @return array{entries:int, created:int, updated:int, skipped:int}
     */
    public function syncUser(User $user, array $options = []): array
    {
        if (!$this->tablesReady() || !Schema::hasTable('channel_orders') || !Schema::hasTable('channel_order_items')) {
            return ['entries' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 1];
        }

        $since = $this->resolveSince($options['since'] ?? null, $options['recent_days'] ?? null);
        $summary = ['entries' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0];

        ChannelOrder::query()
            ->with([
                'store',
                'items.financialEvents',
                'items.listing',
                'items.product.activeRecipe',
            ])
            ->whereHas('store', fn (Builder $query) => $query->where('user_id', $user->id))
            ->when($since, fn (Builder $query) => $this->applySince($query, $since, ['updated_at', 'created_at', 'ordered_at']))
            ->orderBy('id')
            ->chunkById(150, function ($orders) use ($user, &$summary) {
                foreach ($orders as $order) {
                    $contact = $this->resolveContactForOrder($user, $order);
                    $orderSummary = $this->syncOrder($contact, $order);

                    $summary['entries'] += $orderSummary['entries'];
                    $summary['created'] += $orderSummary['created'];
                    $summary['updated'] += $orderSummary['updated'];
                }
            });

        return $summary;
    }

    /**
     * @return array{entries:int, created:int, updated:int}
     */
    public function syncOrder(CrmContact $contact, ChannelOrder $order): array
    {
        $order->loadMissing([
            'store',
            'items.financialEvents',
            'items.listing',
            'items.product.activeRecipe',
        ]);

        $summary = ['entries' => 0, 'created' => 0, 'updated' => 0];

        foreach ($order->items as $item) {
            $entry = $this->upsertOrderItemEntry($contact, $order, $item);
            $summary['entries']++;

            if ($entry->wasRecentlyCreated) {
                $summary['created']++;
            } else {
                $summary['updated']++;
            }
        }

        return $summary;
    }

    public function createManualEntry(User $user, array $data): CrmCustomerLedgerEntry
    {
        $entry = CrmCustomerLedgerEntry::create($this->manualEntryPayload($user, $data));

        $this->recordManualTimelineEvent($entry->contact, $entry);
        $this->recalculateContactFromOrderEvents($entry->contact);

        return $entry->load(['contact', 'store', 'recipe']);
    }

    public function updateManualEntry(User $user, int $entryId, array $data): CrmCustomerLedgerEntry
    {
        $entry = CrmCustomerLedgerEntry::query()
            ->where('user_id', $user->id)
            ->where('source_type', 'manual')
            ->whereKey($entryId)
            ->firstOrFail();
        $oldContact = $entry->contact;
        $payload = $this->manualEntryPayload($user, $data);
        $payload['payload_json'] = array_merge($payload['payload_json'], [
            'updated_from' => 'crm_customer_ledger',
            'updated_at' => now()->toIso8601String(),
        ]);

        $entry->fill($payload)->save();
        $entry->refresh()->load(['contact', 'store', 'recipe']);

        $this->recordManualTimelineEvent($entry->contact, $entry);
        $this->recalculateContactFromOrderEvents($entry->contact);

        if ($oldContact && (int) $oldContact->id !== (int) $entry->contact_id) {
            $freshOldContact = $oldContact->fresh();

            if ($freshOldContact) {
                $this->recalculateContactFromOrderEvents($freshOldContact);
            }
        }

        return $entry;
    }

    public function voidManualEntry(User $user, int $entryId): CrmCustomerLedgerEntry
    {
        $entry = CrmCustomerLedgerEntry::query()
            ->where('user_id', $user->id)
            ->where('source_type', 'manual')
            ->whereKey($entryId)
            ->firstOrFail();
        $payload = $entry->payload_json ?: [];
        $payload['voided_from'] = 'crm_customer_ledger';
        $payload['voided_at'] = now()->toIso8601String();

        $entry->forceFill([
            'status' => 'cancelled',
            'notes' => $this->appendSystemNote($entry->notes, 'Müşteri cari ekranından iptal edildi.'),
            'payload_json' => $payload,
        ])->save();
        $entry->refresh()->load(['contact', 'store', 'recipe']);

        $this->recordManualTimelineEvent($entry->contact, $entry);
        $this->recalculateContactFromOrderEvents($entry->contact);

        return $entry;
    }

    protected function manualEntryPayload(User $user, array $data): array
    {
        $contact = $this->resolveManualContact($user, $data);
        $store = $this->resolveStore($user, $data['store_id'] ?? null);
        $recipe = $this->resolveManualRecipe($user, $data['recipe_id'] ?? null);
        $quantity = max(0.01, $this->decimal($data['quantity'] ?? 1));
        $unitPrice = max(0, $this->decimal($data['unit_price'] ?? 0));
        $grossAmount = $this->decimal($data['gross_amount'] ?? 0);
        $grossAmount = $grossAmount > 0 ? $grossAmount : round($quantity * $unitPrice, 2);
        $discountAmount = max(0, $this->decimal($data['discount_amount'] ?? 0));
        $commissionRate = max(0, min(100, $this->decimal($data['commission_rate'] ?? 0)));
        $commissionAmount = $this->decimal($data['commission_amount'] ?? 0);
        $commissionAmount = $commissionAmount > 0 ? $commissionAmount : round(max(0, $grossAmount - $discountAmount) * $commissionRate / 100, 2);
        $cargoAmount = max(0, $this->decimal($data['cargo_amount'] ?? 0));
        $costAmount = max(0, $this->decimal($data['cost_amount'] ?? 0));
        $costAmount = $costAmount > 0 ? $costAmount : round((float) ($recipe?->total_cost ?? 0) * $quantity, 2);
        $netAmount = round(max(0, $grossAmount - $discountAmount) - $commissionAmount - $cargoAmount, 2);
        $profitAmount = round($netAmount - $costAmount, 2);
        $productName = trim((string) ($data['product_name'] ?? ''));

        if ($productName === '' && $recipe) {
            $productName = $recipe->name;
        }

        return [
            'user_id' => $user->id,
            'contact_id' => $contact->id,
            'store_id' => $store?->id,
            'recipe_id' => $recipe?->id,
            'source_type' => 'manual',
            'platform' => $this->cleanNullable($data['platform'] ?? null) ?: $store?->marketplace ?: 'Manuel',
            'marketplace_order_number' => $this->cleanNullable($data['marketplace_order_number'] ?? null),
            'product_name' => $productName !== '' ? $productName : 'Manuel ürün',
            'stock_code' => $this->cleanNullable($data['stock_code'] ?? null) ?: $recipe?->stock_code,
            'barcode' => $this->cleanNullable($data['barcode'] ?? null),
            'recipe_name' => $recipe?->name,
            'recipe_version' => $recipe?->version,
            'tariff_name' => $this->cleanNullable($data['tariff_name'] ?? null) ?: ($commissionRate > 0 ? $this->commissionTariffLabel($commissionRate) : null),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'gross_amount' => $grossAmount,
            'discount_amount' => $discountAmount,
            'commission_rate' => $commissionRate,
            'commission_amount' => $commissionAmount,
            'cargo_amount' => $cargoAmount,
            'cost_amount' => $costAmount,
            'net_amount' => $netAmount,
            'profit_amount' => $profitAmount,
            'currency' => $store?->currency ?: 'TRY',
            'status' => $this->normalizeStatus($data['status'] ?? 'completed'),
            'purchased_at' => $this->asCarbon($data['purchased_at'] ?? null) ?: now(),
            'notes' => $this->cleanNullable($data['notes'] ?? null),
            'payload_json' => [
                'entry_source' => 'manual',
                'created_from' => 'crm_customer_ledger',
            ],
        ];
    }

    protected function upsertOrderItemEntry(CrmContact $contact, ChannelOrder $order, ChannelOrderItem $item): CrmCustomerLedgerEntry
    {
        $recipe = $this->resolveRecipeForItem((int) $contact->user_id, $item);
        $quantity = max(1, (float) ($item->quantity ?: 1));
        $unitPrice = (float) ($item->unit_price ?: 0);
        $grossAmount = $this->itemGrossAmount($item, $quantity, $unitPrice);
        $discountAmount = round((float) ($item->discount_amount ?? 0) + (float) ($item->marketplace_discount_amount ?? 0), 2);
        $saleBase = (float) ($item->billable_amount ?: max(0, $grossAmount - $discountAmount));
        $commissionRate = $this->resolveCommissionRate($item);
        $commissionAmount = $this->financialEventTotal($item, ['commission']);
        $commissionAmount = $commissionAmount > 0 ? $commissionAmount : round($saleBase * $commissionRate / 100, 2);
        $cargoAmount = $this->financialEventTotal($item, ['cargo']);
        if ($cargoAmount <= 0 && $item->product) {
            $composition = app(\App\Services\ProductCompositionResolver::class)->resolve($item->product, $quantity);
            $cargoAmount = round((float) ($composition['own_cargo_cost'] ?? 0), 2);
        }
        $costAmount = $this->resolveCostAmount($recipe, $item, $quantity);
        $netAmount = round($saleBase - $commissionAmount - $cargoAmount, 2);
        $profitAmount = round($netAmount - $costAmount, 2);
        $sourceKey = 'channel-order-item:' . $item->id;

        return CrmCustomerLedgerEntry::updateOrCreate(
            [
                'user_id' => $contact->user_id,
                'source_key' => $sourceKey,
            ],
            [
                'contact_id' => $contact->id,
                'store_id' => $order->store_id,
                'channel_order_id' => $order->id,
                'channel_order_item_id' => $item->id,
                'mp_product_id' => $item->mp_product_id,
                'recipe_id' => $recipe?->id,
                'source_type' => 'marketplace_order_item',
                'subject_type' => $item::class,
                'subject_id' => $item->id,
                'platform' => $order->store?->marketplace ?: $order->store?->store_name,
                'marketplace_order_number' => $order->order_number ?: $order->external_order_id,
                'product_name' => $item->product_name ?: $item->product?->product_name ?: 'Ürün adı yok',
                'stock_code' => $item->stock_code ?: $item->product?->stock_code,
                'barcode' => $item->barcode ?: $item->product?->barcode,
                'recipe_name' => $recipe?->name,
                'recipe_version' => $recipe?->version,
                'tariff_name' => $this->resolveTariffName($item, $commissionRate),
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'gross_amount' => $grossAmount,
                'discount_amount' => $discountAmount,
                'commission_rate' => $commissionRate,
                'commission_amount' => $commissionAmount,
                'cargo_amount' => $cargoAmount,
                'cost_amount' => $costAmount,
                'net_amount' => $netAmount,
                'profit_amount' => $profitAmount,
                'currency' => $order->store?->currency ?: 'TRY',
                'status' => $this->normalizeStatus($item->line_status ?: $order->order_status),
                'purchased_at' => $order->ordered_at ?: $order->created_at,
                'payload_json' => [
                    'order_status' => $order->order_status,
                    'line_status' => $item->line_status,
                    'external_line_id' => $item->external_line_id,
                    'commission_source' => $item->listing?->commission_source,
                ],
            ],
        );
    }

    protected function resolveContactForOrder(User $user, ChannelOrder $order): CrmContact
    {
        return $this->identityResolver->resolve([
            'user_id' => $user->id,
            'store_id' => $order->store_id,
            'marketplace' => $order->store?->marketplace,
            'source_type' => 'order_customer',
            'external_customer_id' => $this->firstFilled([
                data_get($order->raw_payload, 'customerId'),
                data_get($order->raw_payload, 'customer.id'),
                data_get($order->raw_payload, 'customer.customerId'),
            ]),
            'name' => $order->customer_name ?: $order->billing_name,
            'email' => $order->customer_email,
            'phone' => $order->customer_phone,
            'tax_number' => $order->billing_tax_number,
            'city' => $order->shipment_city,
            'district' => $order->shipment_district,
            'confidence' => $order->customer_phone || $order->customer_email || $order->billing_tax_number ? 96 : 68,
            'raw_payload' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ],
        ]);
    }

    protected function resolveManualContact(User $user, array $data): CrmContact
    {
        $contactId = (int) ($data['contact_id'] ?? 0);
        $storeId = (int) ($data['store_id'] ?? 0);
        $storeId = $storeId > 0 ? $storeId : null;

        if ($contactId > 0) {
            $contact = CrmContact::query()
                ->where('user_id', $user->id)
                ->whereKey($contactId)
                ->first();

            if ($contact) {
                return $contact;
            }
        }

        return $this->identityResolver->resolve([
            'user_id' => $user->id,
            'store_id' => $storeId,
            'marketplace' => $data['platform'] ?? null,
            'source_type' => 'manual_customer',
            'name' => $data['customer_name'] ?? null,
            'email' => $data['customer_email'] ?? null,
            'phone' => $data['customer_phone'] ?? null,
            'confidence' => 70,
            'raw_payload' => [
                'created_from' => 'crm_customer_ledger',
            ],
        ]);
    }

    protected function recordManualTimelineEvent(CrmContact $contact, CrmCustomerLedgerEntry $entry): void
    {
        $isReversal = in_array($entry->status, ['cancelled', 'returned'], true);

        CrmTimelineEvent::updateOrCreate(
            [
                'user_id' => $contact->user_id,
                'event_key' => 'manual-ledger:' . $entry->id,
            ],
            [
                'contact_id' => $contact->id,
                'store_id' => $entry->store_id,
                'event_type' => 'order',
                'source_type' => 'crm_customer_ledger',
                'subject_type' => $entry::class,
                'subject_id' => $entry->id,
                'title' => trim(($entry->marketplace_order_number ? '#' . $entry->marketplace_order_number . ' · ' : '') . $entry->product_name),
                'body' => trim(($entry->platform ?: 'Manuel') . ' · ' . ($entry->tariff_name ?: $entry->sourceLabel())),
                'occurred_at' => $entry->purchased_at ?: now(),
                'payload_json' => [
                    'gross_amount' => $isReversal ? 0 : (float) $entry->gross_amount,
                    'profit' => $isReversal ? 0 : (float) $entry->profit_amount,
                    'commission_amount' => $isReversal ? 0 : (float) $entry->commission_amount,
                    'ledger_gross_amount' => (float) $entry->gross_amount,
                    'ledger_profit' => (float) $entry->profit_amount,
                    'ledger_commission_amount' => (float) $entry->commission_amount,
                    'commission_rate' => (float) $entry->commission_rate,
                    'platform' => $entry->platform,
                    'product_name' => $entry->product_name,
                    'status' => $entry->status,
                ],
            ],
        );
    }

    protected function recalculateContactFromOrderEvents(CrmContact $contact): void
    {
        $events = $contact->timelineEvents()->get();
        $orderEvents = $events->where('event_type', 'order');
        $lastEvent = $events->sortByDesc('occurred_at')->first();
        $grossRevenue = $orderEvents->sum(fn ($event) => (float) data_get($event->payload_json, 'gross_amount', 0));

        $contact->forceFill([
            'first_order_at' => optional($orderEvents->sortBy('occurred_at')->first())->occurred_at,
            'last_order_at' => optional($orderEvents->sortByDesc('occurred_at')->first())->occurred_at,
            'last_event_at' => $lastEvent?->occurred_at,
            'last_event_type' => $lastEvent?->event_type,
            'last_event_title' => $lastEvent?->title,
            'order_count' => $orderEvents->count(),
            'gross_revenue_total' => $grossRevenue,
            'open_case_count' => $contact->openCases()->count(),
            'value_score' => min(100, (int) round(($orderEvents->count() * 4) + min(70, $grossRevenue / 1000))),
        ])->save();
    }

    protected function resolveRecipeForItem(int $userId, ChannelOrderItem $item): ?Recipe
    {
        if ($item->product?->activeRecipe) {
            return $item->product->activeRecipe;
        }

        if (!Schema::hasTable('recipes')) {
            return null;
        }

        if (!$item->mp_product_id && (!Schema::hasColumn('recipes', 'stock_code') || blank($item->stock_code))) {
            return null;
        }

        return Recipe::query()
            ->where('user_id', $userId)
            ->active()
            ->where(function (Builder $query) use ($item) {
                if ($item->mp_product_id) {
                    $query->where('mp_product_id', $item->mp_product_id);
                }

                if (Schema::hasColumn('recipes', 'stock_code') && filled($item->stock_code)) {
                    $query->orWhere('stock_code', $item->stock_code);
                }
            })
            ->latest('updated_at')
            ->first();
    }

    protected function resolveManualRecipe(User $user, mixed $recipeId): ?Recipe
    {
        $recipeId = (int) $recipeId;

        if ($recipeId <= 0 || !Schema::hasTable('recipes')) {
            return null;
        }

        return Recipe::query()
            ->where('user_id', $user->id)
            ->whereKey($recipeId)
            ->first();
    }

    protected function resolveStore(User $user, mixed $storeId): ?MarketplaceStore
    {
        $storeId = (int) $storeId;

        if ($storeId <= 0 || !Schema::hasTable('marketplace_stores')) {
            return null;
        }

        return MarketplaceStore::query()
            ->where('user_id', $user->id)
            ->whereKey($storeId)
            ->first();
    }

    protected function resolveCommissionRate(ChannelOrderItem $item): float
    {
        $rate = $item->commission_rate !== null
            ? (float) $item->commission_rate
            : (float) ($item->listing?->commission_rate ?? $item->product?->commission_rate ?? 0);

        return round(max(0, min(100, $rate)), 2);
    }

    protected function resolveTariffName(ChannelOrderItem $item, float $commissionRate): ?string
    {
        $payload = $item->raw_payload ?: [];
        $candidate = $this->firstFilled([
            data_get($payload, 'tariffName'),
            data_get($payload, 'tariff.name'),
            data_get($payload, 'commissionTariff'),
            data_get($payload, 'commission.tariff'),
            data_get($payload, 'commissionRateName'),
            data_get($payload, 'campaignName'),
            data_get($payload, 'categoryName'),
            $item->listing?->commission_source,
        ]);

        if ($candidate) {
            return (string) Str::of($candidate)->squish()->limit(120, '');
        }

        return $commissionRate > 0 ? $this->commissionTariffLabel($commissionRate) : null;
    }

    protected function resolveCostAmount(?Recipe $recipe, ChannelOrderItem $item, float $quantity): float
    {
        if ($recipe) {
            return round((float) $recipe->total_cost * $quantity, 2);
        }

        $product = $item->product;
        if ($product) {
            $composition = app(\App\Services\ProductCompositionResolver::class)->resolve($product, $quantity);

            return round((float) $composition['cogs_cost'] + (float) $composition['packaging_cost'], 2);
        }

        return 0.0;
    }

    protected function itemGrossAmount(ChannelOrderItem $item, float $quantity, float $unitPrice): float
    {
        $grossAmount = (float) ($item->gross_amount ?: 0);

        if ($grossAmount > 0) {
            return round($grossAmount, 2);
        }

        if ($item->billable_amount !== null) {
            return round((float) $item->billable_amount, 2);
        }

        return round($unitPrice * $quantity, 2);
    }

    /**
     * @param array<int, string> $types
     */
    protected function financialEventTotal(ChannelOrderItem $item, array $types): float
    {
        if (!$item->relationLoaded('financialEvents')) {
            $item->load('financialEvents');
        }

        return round((float) $item->financialEvents
            ->whereIn('event_type', $types)
            ->sum(fn ($event) => abs((float) $event->amount)), 2);
    }

    protected function normalizeStatus(?string $status): string
    {
        $normalized = (string) Str::of((string) $status)->lower()->trim();

        return match (true) {
            Str::contains($normalized, ['return', 'iade', 'returned']) => 'returned',
            Str::contains($normalized, ['cancel', 'iptal', 'void']) => 'cancelled',
            Str::contains($normalized, ['new', 'created', 'pending', 'bekliyor', 'draft']) => 'pending',
            default => 'completed',
        };
    }

    protected function commissionTariffLabel(float $commissionRate): string
    {
        return 'Komisyon %' . number_format($commissionRate, 2, ',', '.');
    }

    protected function applySince(Builder $query, Carbon $since, array $columns): Builder
    {
        return $query->where(function (Builder $sinceQuery) use ($since, $columns) {
            foreach ($columns as $column) {
                $sinceQuery->orWhere($column, '>=', $since);
            }
        });
    }

    protected function resolveSince(mixed $since, mixed $recentDays): ?Carbon
    {
        if ($since) {
            return Carbon::parse($since)->startOfDay();
        }

        if ($recentDays) {
            return now()->subDays(max(1, (int) $recentDays));
        }

        return null;
    }

    protected function asCarbon(mixed $value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        return $value instanceof Carbon ? $value : Carbon::parse($value);
    }

    protected function firstFilled(array $values): ?string
    {
        foreach ($values as $value) {
            $clean = $this->cleanNullable($value);

            if ($clean !== null) {
                return $clean;
            }
        }

        return null;
    }

    protected function cleanNullable(mixed $value): ?string
    {
        $clean = trim((string) $value);

        return $clean === '' ? null : $clean;
    }

    protected function appendSystemNote(?string $notes, string $note): string
    {
        $line = '[' . now()->format('d.m.Y H:i') . '] ' . $note;
        $notes = trim((string) $notes);

        return $notes === '' ? $line : $notes . "\n" . $line;
    }

    protected function decimal(mixed $value): float
    {
        if (is_string($value)) {
            $value = trim($value);

            if (str_contains($value, ',')) {
                $value = str_replace(['.', ','], ['', '.'], $value);
            }
        }

        return round((float) $value, 2);
    }
}
