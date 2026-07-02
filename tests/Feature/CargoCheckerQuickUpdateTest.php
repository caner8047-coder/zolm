<?php

namespace Tests\Feature;

use App\Livewire\Cargo\CargoChecker;
use App\Models\MpProduct;
use App\Models\Product;
use App\Models\ProductReferenceHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CargoCheckerQuickUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_quick_update_creates_missing_marketplace_and_reference_product(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user);

        Livewire::test(CargoChecker::class)
            ->call('openProductEditModal', '1KLTZEM00066', 85.0, 954.3, 2, 'Ramos Bohem Koltuk Takımı')
            ->assertSet('showEditModal', true)
            ->call('updateProductFromModal')
            ->assertSet('showEditModal', false)
            ->assertSet('messageType', 'success');

        $mpProduct = MpProduct::query()
            ->where('user_id', $user->id)
            ->where('stock_code', '1KLTZEM00066')
            ->first();

        $this->assertNotNull($mpProduct);
        $this->assertSame('1KLTZEM00066', $mpProduct->barcode);
        $this->assertSame('Ramos Bohem Koltuk Takımı', $mpProduct->product_name);
        $this->assertEqualsWithDelta(85.0, (float) $mpProduct->desi, 0.001);
        $this->assertSame(2, (int) $mpProduct->pieces);
        $this->assertEqualsWithDelta(954.3, (float) $mpProduct->cargo_cost, 0.001);

        $reference = Product::query()->where('stok_kodu', '1KLTZEM00066')->first();

        $this->assertNotNull($reference);
        $this->assertSame('Ramos Bohem Koltuk Takımı', $reference->urun_adi);
        $this->assertEqualsWithDelta(85.0, (float) $reference->desi, 0.001);
        $this->assertSame(2, (int) $reference->parca);
        $this->assertEqualsWithDelta(954.3, (float) $reference->tutar, 0.001);

        $this->assertDatabaseHas('product_reference_histories', [
            'product_id' => $reference->id,
            'stok_kodu' => '1KLTZEM00066',
            'change_source' => 'cargo_checker_create',
            'changed_by' => $user->id,
        ]);
    }

    public function test_quick_update_updates_existing_marketplace_product_and_reference_product(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $mpProduct = MpProduct::query()->create([
            'user_id' => $user->id,
            'barcode' => 'BAR-001',
            'stock_code' => '1KLTZEM00066',
            'product_name' => 'Eski Ürün',
            'desi' => 10,
            'pieces' => 1,
            'cargo_cost' => 100,
        ]);
        $reference = Product::query()->create([
            'stok_kodu' => '1KLTZEM00066',
            'urun_adi' => 'Eski Referans',
            'parca' => 1,
            'desi' => 10,
            'tutar' => 100,
            'is_active' => true,
            'updated_by' => $user->id,
        ]);

        $this->actingAs($user);

        Livewire::test(CargoChecker::class)
            ->call('openProductEditModal', '1KLTZEM00066', 85.0, 954.3, 2, 'Ramos Bohem Koltuk Takımı')
            ->call('updateProductFromModal')
            ->assertSet('messageType', 'success');

        $this->assertSame(1, MpProduct::query()->where('user_id', $user->id)->where('stock_code', '1KLTZEM00066')->count());
        $this->assertSame(1, Product::query()->where('stok_kodu', '1KLTZEM00066')->count());

        $mpProduct->refresh();
        $reference->refresh();

        $this->assertEqualsWithDelta(85.0, (float) $mpProduct->desi, 0.001);
        $this->assertSame(2, (int) $mpProduct->pieces);
        $this->assertEqualsWithDelta(954.3, (float) $mpProduct->cargo_cost, 0.001);
        $this->assertSame('Eski Ürün', $mpProduct->product_name);

        $this->assertEqualsWithDelta(85.0, (float) $reference->desi, 0.001);
        $this->assertSame(2, (int) $reference->parca);
        $this->assertEqualsWithDelta(954.3, (float) $reference->tutar, 0.001);
        $this->assertSame('Eski Referans', $reference->urun_adi);

        $this->assertTrue(ProductReferenceHistory::query()
            ->where('product_id', $reference->id)
            ->where('change_source', 'cargo_checker_update')
            ->exists());
    }

    public function test_quick_update_reuses_normalized_marketplace_products_instead_of_creating_duplicate_stock_cards(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $firstProduct = MpProduct::query()->create([
            'user_id' => $user->id,
            'barcode' => 'BAR-001',
            'stock_code' => ' 1kltzem00066 ',
            'product_name' => 'Boşluklu Stok',
            'desi' => 10,
            'pieces' => 1,
            'cargo_cost' => 100,
        ]);
        $secondProduct = MpProduct::query()->create([
            'user_id' => $user->id,
            'barcode' => 'BAR-002',
            'stock_code' => '1KLTZEM00066',
            'product_name' => 'Aynı Stok',
            'desi' => 12,
            'pieces' => 1,
            'cargo_cost' => 120,
        ]);

        $this->actingAs($user);

        Livewire::test(CargoChecker::class)
            ->call('openProductEditModal', '1KLTZEM00066', 85.0, 954.3, 2, 'Ramos Bohem Koltuk Takımı')
            ->call('updateProductFromModal')
            ->assertSet('messageType', 'success');

        $this->assertSame(2, MpProduct::query()->where('user_id', $user->id)->count());

        foreach ([$firstProduct->fresh(), $secondProduct->fresh()] as $product) {
            $this->assertEqualsWithDelta(85.0, (float) $product->desi, 0.001);
            $this->assertSame(2, (int) $product->pieces);
            $this->assertEqualsWithDelta(954.3, (float) $product->cargo_cost, 0.001);
        }
    }
}
