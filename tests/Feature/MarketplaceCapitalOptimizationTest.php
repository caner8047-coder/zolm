<?php

namespace Tests\Feature;

use App\Models\MpProduct;
use App\Models\User;
use App\Services\Marketplace\MarketplaceCapitalOptimizerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceCapitalOptimizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_optimizer_reads_only_the_selected_users_product_portfolio(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        MpProduct::query()->create($this->productPayload($user->id, 'Kullanıcı Ürünü', 'OWN-1'));
        MpProduct::query()->create($this->productPayload($otherUser->id, 'Başka Kullanıcı Ürünü', 'OTHER-1'));

        $result = app(MarketplaceCapitalOptimizerService::class)->analyze($user->id, [
            'date_from' => now()->subDays(29)->toDateString(),
            'date_to' => now()->toDateString(),
        ]);

        $this->assertSame(1, $result['summary']['product_count']);
        $this->assertSame('Kullanıcı Ürünü', $result['items'][0]['product_name']);
        $this->assertSame('investigate', $result['items'][0]['decision']);
        $this->assertSame(1200.0, $result['summary']['inventory_capital']);
    }

    /** @return array<string, mixed> */
    private function productPayload(int $userId, string $name, string $stockCode): array
    {
        return [
            'user_id' => $userId,
            'barcode' => 'B-'.$stockCode,
            'stock_code' => $stockCode,
            'product_name' => $name,
            'cogs' => 100,
            'packaging_cost' => 10,
            'cargo_cost' => 10,
            'sale_price' => 250,
            'commission_rate' => 15,
            'stock_quantity' => 10,
            'vat_rate' => 20,
            'desi' => 1,
            'pieces' => 1,
            'status' => 'active',
        ];
    }
}
