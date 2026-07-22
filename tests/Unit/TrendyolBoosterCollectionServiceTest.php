<?php

namespace Tests\Unit;

use App\Services\Marketplace\TrendyolBoosterCollectionService;
use PHPUnit\Framework\TestCase;

class TrendyolBoosterCollectionServiceTest extends TestCase
{
    public function test_it_normalizes_collection_names_for_stable_user_scoped_identity(): void
    {
        $service = new TrendyolBoosterCollectionService();

        $this->assertSame('Numune Adayları', $service->normalizeName('  <b>Numune</b>   Adayları  '));
        $this->assertLessThanOrEqual(80, mb_strlen($service->normalizeName(str_repeat('Ürün ', 30))));
    }
}
