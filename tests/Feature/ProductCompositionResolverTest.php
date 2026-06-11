<?php

namespace Tests\Feature;

use App\Models\MpProduct;
use App\Models\ProductSet;
use App\Models\ProductSetItem;
use App\Models\User;
use App\Services\ProductCompositionResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductCompositionResolverTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql.host', 'mysql');
        config()->set('database.connections.mysql.port', '3306');
        config()->set('database.connections.mysql.database', 'zolm');
        config()->set('database.connections.mysql.username', 'sail');
        config()->set('database.connections.mysql.password', 'password');
        DB::purge('mysql');
        DB::reconnect('mysql');
        DB::setDefaultConnection('mysql');
    }

    public function test_it_sums_component_cost_logistics_pieces_and_stock_for_set_products(): void
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $setProduct = $this->createProduct($user->id, $suffix.'-SET', [
            'product_name' => 'Alaves Hazeran Cay Seti',
            'cogs' => 999,
            'packaging_cost' => 99,
            'cargo_cost' => 99,
            'desi' => 99,
            'pieces' => 1,
            'stock_quantity' => 0,
        ]);

        $twoSeat = $this->createProduct($user->id, $suffix.'-IKILI', [
            'product_name' => 'Alaves Ikili Berjer',
            'cogs' => 1200,
            'packaging_cost' => 80,
            'cargo_cost' => 100,
            'desi' => 60,
            'pieces' => 4,
            'stock_quantity' => 4,
        ]);

        $singleSeat = $this->createProduct($user->id, $suffix.'-TEKLI', [
            'product_name' => 'Alaves Tekli Berjer',
            'cogs' => 700,
            'packaging_cost' => 40,
            'cargo_cost' => 70,
            'desi' => 35,
            'pieces' => 2,
            'stock_quantity' => 11,
        ]);

        $table = $this->createProduct($user->id, $suffix.'-SEHPA', [
            'product_name' => 'Alaves Sehpa',
            'cogs' => 300,
            'packaging_cost' => 25,
            'cargo_cost' => 45,
            'desi' => 20,
            'pieces' => 1,
            'stock_quantity' => 8,
        ]);

        $set = ProductSet::query()->create([
            'user_id' => $user->id,
            'parent_mp_product_id' => $setProduct->id,
            'name' => 'Alaves Hazeran Cay Seti',
            'status' => ProductSet::STATUS_ACTIVE,
            'cost_mode' => ProductSet::MODE_SUM_COMPONENTS,
            'logistics_mode' => ProductSet::MODE_SUM_COMPONENTS,
        ]);

        $this->addSetItem($set, $twoSeat, 1, 1);
        $this->addSetItem($set, $singleSeat, 2, 2);
        $this->addSetItem($set, $table, 1, 3);

        $resolver = app(ProductCompositionResolver::class);
        $summary = $resolver->resolve($setProduct->fresh());

        $this->assertTrue($summary['is_set']);
        $this->assertSame(3, $summary['component_count']);
        $this->assertEquals(2900.0, $summary['cogs_cost']);
        $this->assertEquals(185.0, $summary['packaging_cost']);
        $this->assertEquals(285.0, $summary['own_cargo_cost']);
        $this->assertEquals(150.0, $summary['desi']);
        $this->assertSame(9, $summary['pieces']);
        $this->assertSame(4, $summary['stock_quantity']);
        $this->assertSame(0, $summary['missing_cost_components']);
        $this->assertSame(0, $summary['missing_logistics_components']);

        $resolver->syncProductTotals($setProduct->fresh());
        $setProduct->refresh();

        $this->assertSame('set', $setProduct->product_type);
        $this->assertSame('set', $setProduct->cost_source);
        $this->assertSame('set', $setProduct->logistics_source);
        $this->assertSame('2900.00', (string) $setProduct->cogs);
        $this->assertSame('185.00', (string) $setProduct->packaging_cost);
        $this->assertSame('285.00', (string) $setProduct->cargo_cost);
        $this->assertSame('150.00', (string) $setProduct->desi);
        $this->assertSame(9, $setProduct->pieces);
        $this->assertSame(4, $setProduct->stock_quantity);
    }

    public function test_component_cost_logistics_and_stock_updates_refresh_parent_set_totals(): void
    {
        $user = User::factory()->create();
        $suffix = (string) random_int(100000, 999999);

        $setProduct = $this->createProduct($user->id, $suffix.'-AUTO-SET', [
            'product_name' => 'Auto Refresh Set',
            'cogs' => 0,
            'packaging_cost' => 0,
            'cargo_cost' => 0,
            'desi' => 0,
            'pieces' => 1,
            'stock_quantity' => 0,
        ]);

        $component = $this->createProduct($user->id, $suffix.'-AUTO-COMP', [
            'product_name' => 'Auto Refresh Component',
            'cogs' => 100,
            'packaging_cost' => 10,
            'cargo_cost' => 20,
            'desi' => 3,
            'pieces' => 1,
            'stock_quantity' => 8,
        ]);

        $set = ProductSet::query()->create([
            'user_id' => $user->id,
            'parent_mp_product_id' => $setProduct->id,
            'name' => 'Auto Refresh Set',
            'status' => ProductSet::STATUS_ACTIVE,
            'cost_mode' => ProductSet::MODE_SUM_COMPONENTS,
            'logistics_mode' => ProductSet::MODE_SUM_COMPONENTS,
        ]);

        $this->addSetItem($set, $component, 2, 1);

        app(ProductCompositionResolver::class)->syncProductTotals($setProduct->fresh());

        $component->update([
            'cogs' => 150,
            'packaging_cost' => 15,
            'cargo_cost' => 25,
            'desi' => 4,
            'pieces' => 2,
            'stock_quantity' => 5,
        ]);

        $setProduct->refresh();

        $this->assertSame('300.00', (string) $setProduct->cogs);
        $this->assertSame('30.00', (string) $setProduct->packaging_cost);
        $this->assertSame('50.00', (string) $setProduct->cargo_cost);
        $this->assertSame('8.00', (string) $setProduct->desi);
        $this->assertSame(4, $setProduct->pieces);
        $this->assertSame(2, $setProduct->stock_quantity);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createProduct(int $userId, string $barcode, array $attributes): MpProduct
    {
        return MpProduct::query()->create(array_merge([
            'user_id' => $userId,
            'barcode' => $barcode,
            'stock_code' => $barcode,
            'product_name' => 'Test Product',
            'status' => 'active',
            'vat_rate' => 10,
            'pieces' => 1,
            'desi' => 0,
        ], $attributes));
    }

    protected function addSetItem(ProductSet $set, MpProduct $component, float $quantity, int $sortOrder): ProductSetItem
    {
        return ProductSetItem::query()->create([
            'product_set_id' => $set->id,
            'component_mp_product_id' => $component->id,
            'quantity' => $quantity,
            'include_cost' => true,
            'include_packaging' => true,
            'include_logistics' => true,
            'sort_order' => $sortOrder,
        ]);
    }
}
