<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MpFinancialRuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rules = [
            ['rule_key' => 'barem_limit', 'rule_value' => '300.00', 'category' => null, 'description' => 'Sipariş İçi Kargo Barem Sınırı (Seperatör)'],
            ['rule_key' => 'stopaj_rate', 'rule_value' => '0.01', 'category' => null, 'description' => '2025 e-Ticaret Stopaj Oranı (%1)'],
            
            // 0-150 TL Barem Fiyatları
            ['rule_key' => 'barem_0_150', 'rule_value' => '27.08', 'category' => 'Trendyol Express', 'description' => '0-150 TL Arası TEX Taban Fiyatı'],
            ['rule_key' => 'barem_0_150', 'rule_value' => '27.08', 'category' => 'PTT Kargo', 'description' => '0-150 TL Arası PTT Taban Fiyatı'],
            ['rule_key' => 'barem_0_150', 'rule_value' => '35.83', 'category' => 'Aras Kargo', 'description' => '0-150 TL Arası Aras Taban Fiyatı'],
            
            // 150-300 TL Barem Fiyatları
            ['rule_key' => 'barem_150_300', 'rule_value' => '81.66', 'category' => 'Yurtiçi Kargo', 'description' => '150-300 TL Arası Yurtiçi Kargo Fiyatı'],
            ['rule_key' => 'barem_150_300', 'rule_value' => '62.49', 'category' => 'Sürat Kargo', 'description' => '150-300 TL Arası Sürat Kargo Fiyatı'],

            // Ağır Kargo Cezaları (Desi/Hacim Aşımı)
            ['rule_key' => 'heavy_cargo_fee', 'rule_value' => '4250.00', 'category' => 'Aras Kargo', 'description' => 'Aras Ağır Kargo Ceza Faturası'],
            ['rule_key' => 'heavy_cargo_fee', 'rule_value' => '4500.00', 'category' => 'Sürat Kargo', 'description' => 'Sürat Ağır Kargo Ceza Faturası'],
            ['rule_key' => 'heavy_cargo_fee', 'rule_value' => '5350.00', 'category' => 'Yurtiçi Kargo', 'description' => 'Yurtiçi Kargo Hacim Fazlası Ceza Faturası'],
        ];

        foreach ($rules as $rule) {
            \App\Models\MpFinancialRule::updateOrCreate(
                [
                    'rule_key' => $rule['rule_key'],
                    'category' => $rule['category']
                ],
                [
                    'rule_value' => $rule['rule_value'],
                    'description' => $rule['description'],
                    'valid_from' => '2025-01-01',
                ]
            );
        }
    }
}
