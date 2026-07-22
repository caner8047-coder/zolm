<?php

namespace Tests\Feature;

use Tests\TestCase;

class TrendyolBoosterPrivacyTest extends TestCase
{
    public function test_companion_privacy_policy_is_public_and_explains_core_data_boundaries(): void
    {
        $this->get(route('legal.trendyol-booster-privacy'))
            ->assertOk()
            ->assertSee('Trendyol Booster Companion Gizlilik Politikası')
            ->assertSee('Genel tarama geçmişinin bir kopyası oluşturulmaz.')
            ->assertSee('Toplu karar kuyruğu liste panelindeki “Kuyruğu temizle” düğmesiyle silinebilir.')
            ->assertSee('İndirilen ürün görselleri ZOLM sunucusuna gönderilmez')
            ->assertSee('Kişisel veriler ve analiz verileri üçüncü taraflara satılmaz.');
    }
}
