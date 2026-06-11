<?php

namespace Tests\Feature;

use App\Livewire\MarketplaceOrders;
use App\Models\ChannelOrder;
use App\Models\ChannelOrderPackage;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MarketplaceOrdersSortingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_by_source_order_date_instead_of_cargo_due_date_by_default(): void
    {
        [$user, $store] = $this->createStoreGraph();

        $oldOrder = $this->createOrderWithPackage(
            $store,
            'ORD-SOURCE-OLD',
            orderedAt: '2026-04-27 09:07:00',
            sourceOrderDate: '2026-04-20 01:48:00',
            cargoDueDate: '2026-04-27 09:07:00',
        );
        $newOrder = $this->createOrderWithPackage(
            $store,
            'ORD-SOURCE-NEW',
            orderedAt: '2026-04-21 10:00:00',
            sourceOrderDate: '2026-04-21 10:00:00',
            cargoDueDate: '2026-04-30 10:00:00',
        );

        $this->actingAs($user);

        Livewire::test(MarketplaceOrders::class)
            ->assertSet('sortField', 'source_ordered_at_metric')
            ->assertSeeInOrder([$newOrder->order_number, $oldOrder->order_number])
            ->assertSee('20.04.2026 01:48');
    }

    public function test_it_can_list_by_cargo_due_date(): void
    {
        [$user, $store] = $this->createStoreGraph('2');

        $earlierDueOrder = $this->createOrderWithPackage(
            $store,
            'ORD-DUE-EARLY',
            orderedAt: '2026-04-27 09:07:00',
            sourceOrderDate: '2026-04-20 01:48:00',
            cargoDueDate: '2026-04-27 09:07:00',
        );
        $laterDueOrder = $this->createOrderWithPackage(
            $store,
            'ORD-DUE-LATE',
            orderedAt: '2026-04-21 10:00:00',
            sourceOrderDate: '2026-04-21 10:00:00',
            cargoDueDate: '2026-04-30 10:00:00',
        );

        $this->actingAs($user);

        Livewire::test(MarketplaceOrders::class)
            ->call('applySortPreset', 'cargo_due_asc')
            ->assertSet('sortField', 'cargo_due_at_metric')
            ->assertSet('sortDirection', 'asc')
            ->assertSeeInOrder([$earlierDueOrder->order_number, $laterDueOrder->order_number])
            ->assertSee('Son teslim: 27/04 09:07');
    }

    public function test_primary_search_matches_customer_name(): void
    {
        [$user, $store] = $this->createStoreGraph('3');

        $matchingOrder = $this->createOrderWithPackage(
            $store,
            'ORD-CUSTOMER-MATCH',
            orderedAt: '2026-04-27 09:07:00',
            sourceOrderDate: '2026-04-27 09:07:00',
            cargoDueDate: '2026-04-28 09:07:00',
            customerName: 'Arif Karakurt',
        );

        $otherOrder = $this->createOrderWithPackage(
            $store,
            'ORD-CUSTOMER-OTHER',
            orderedAt: '2026-04-27 10:07:00',
            sourceOrderDate: '2026-04-27 10:07:00',
            cargoDueDate: '2026-04-28 10:07:00',
            customerName: 'Başka Müşteri',
        );

        $this->actingAs($user);

        Livewire::test(MarketplaceOrders::class)
            ->set('search', 'Arif Karakurt')
            ->assertSee($matchingOrder->order_number)
            ->assertDontSee($otherOrder->order_number);
    }

    /**
     * @return array{0: User, 1: MarketplaceStore}
     */
    protected function createStoreGraph(string $prefix = '1'): array
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $entity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Sorting Ltd.',
            'tax_number' => $prefix . $suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $entity->id,
            'marketplace' => 'pazarama',
            'store_name' => 'ZEM SORTING',
            'store_code' => 'SORT-' . $suffix,
            'seller_id' => 'SORT-' . $suffix,
            'status' => 'configured',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        return [$user, $store];
    }

    protected function createOrderWithPackage(
        MarketplaceStore $store,
        string $orderNumber,
        string $orderedAt,
        string $sourceOrderDate,
        string $cargoDueDate,
        string $customerName = 'Sıralama Test',
    ): ChannelOrder {
        $order = ChannelOrder::query()->create([
            'store_id' => $store->id,
            'legal_entity_id' => $store->legal_entity_id,
            'external_order_id' => $orderNumber,
            'order_number' => $orderNumber,
            'order_status' => 'approved',
            'customer_name' => $customerName,
            'ordered_at' => $orderedAt,
            'raw_payload' => [
                'orderDate' => $sourceOrderDate,
            ],
        ]);

        ChannelOrderPackage::query()->create([
            'store_id' => $store->id,
            'channel_order_id' => $order->id,
            'external_package_id' => 'PKG-' . $orderNumber,
            'package_number' => 'PKG-' . $orderNumber,
            'package_status' => 'approved',
            'raw_payload' => [
                'estimatedShippingDate' => $cargoDueDate,
            ],
        ]);

        return $order;
    }
}
