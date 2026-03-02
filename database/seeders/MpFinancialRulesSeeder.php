<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MpFinancialRule;

/**
 * 2026 Yılı Trendyol Finansal Kuralları
 * Barem fiyatları, desi ücretleri, stopaj oranı, ağır kargo cezaları
 */
class MpFinancialRulesSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            // ─── GENEL KURALLAR ─────────────────────────────────
            [
                'rule_key'    => 'stopaj_rate',
                'rule_value'  => '0.01',
                'category'    => null,
                'valid_from'  => '2025-01-01',
                'description' => '%1 E-Ticaret Stopaj Kesintisi',
            ],
            [
                'rule_key'    => 'barem_limit',
                'rule_value'  => '300',
                'category'    => null,
                'valid_from'  => '2025-01-01',
                'description' => 'Barem uygulaması eşik sipariş tutarı (TL)',
            ],

            // ─── BAREM FİYATLARI — 0-150 TL arası ─────────────
            ['rule_key' => 'barem_0_150', 'rule_value' => '27.08', 'category' => 'TEX', 'valid_from' => '2025-01-01', 'description' => '0-150 TL arası barem (TEX)'],
            ['rule_key' => 'barem_0_150', 'rule_value' => '27.08', 'category' => 'PTT', 'valid_from' => '2025-01-01', 'description' => '0-150 TL arası barem (PTT)'],
            ['rule_key' => 'barem_0_150', 'rule_value' => '27.08', 'category' => 'Aras', 'valid_from' => '2025-01-01', 'description' => '0-150 TL arası barem (Aras)'],
            ['rule_key' => 'barem_0_150', 'rule_value' => '27.08', 'category' => 'Sürat', 'valid_from' => '2025-01-01', 'description' => '0-150 TL arası barem (Sürat)'],

            // ─── BAREM FİYATLARI — 150-300 TL arası ───────────
            ['rule_key' => 'barem_150_300', 'rule_value' => '51.66', 'category' => 'TEX', 'valid_from' => '2025-01-01', 'description' => '150-300 TL arası barem (TEX)'],
            ['rule_key' => 'barem_150_300', 'rule_value' => '51.66', 'category' => 'PTT', 'valid_from' => '2025-01-01', 'description' => '150-300 TL arası barem (PTT)'],
            ['rule_key' => 'barem_150_300', 'rule_value' => '51.66', 'category' => 'Aras', 'valid_from' => '2025-01-01', 'description' => '150-300 TL arası barem (Aras)'],
            ['rule_key' => 'barem_150_300', 'rule_value' => '51.66', 'category' => 'Sürat', 'valid_from' => '2025-01-01', 'description' => '150-300 TL arası barem (Sürat)'],

            // ─── DESİ FİYATLARI (KDV Hariç) — 300 TL ÜZERI ───
            // TEX
            ['rule_key' => 'desi_0_2',  'rule_value' => '77.54',  'category' => 'TEX', 'valid_from' => '2026-01-01', 'description' => 'TEX 0-2 Desi'],
            ['rule_key' => 'desi_3',    'rule_value' => '93.63',  'category' => 'TEX', 'valid_from' => '2026-01-01', 'description' => 'TEX 3 Desi'],
            ['rule_key' => 'desi_4',    'rule_value' => '101.46', 'category' => 'TEX', 'valid_from' => '2026-01-01', 'description' => 'TEX 4 Desi'],
            ['rule_key' => 'desi_5',    'rule_value' => '101.46', 'category' => 'TEX', 'valid_from' => '2026-01-01', 'description' => 'TEX 5 Desi'],
            ['rule_key' => 'desi_10',   'rule_value' => '153.47', 'category' => 'TEX', 'valid_from' => '2026-01-01', 'description' => 'TEX 10 Desi'],
            ['rule_key' => 'desi_15',   'rule_value' => '192.81', 'category' => 'TEX', 'valid_from' => '2026-01-01', 'description' => 'TEX 15 Desi'],

            // PTT
            ['rule_key' => 'desi_0_2',  'rule_value' => '77.54',  'category' => 'PTT', 'valid_from' => '2026-01-01', 'description' => 'PTT 0-2 Desi'],
            ['rule_key' => 'desi_3',    'rule_value' => '96.00',  'category' => 'PTT', 'valid_from' => '2026-01-01', 'description' => 'PTT 3 Desi'],

            // Aras
            ['rule_key' => 'desi_0_2',  'rule_value' => '83.93',  'category' => 'Aras', 'valid_from' => '2026-01-01', 'description' => 'Aras 0-2 Desi'],
            ['rule_key' => 'desi_3',    'rule_value' => '95.12',  'category' => 'Aras', 'valid_from' => '2026-01-01', 'description' => 'Aras 3 Desi'],
            ['rule_key' => 'desi_4',    'rule_value' => '103.68', 'category' => 'Aras', 'valid_from' => '2026-01-01', 'description' => 'Aras 4 Desi'],
            ['rule_key' => 'desi_5',    'rule_value' => '111.17', 'category' => 'Aras', 'valid_from' => '2026-01-01', 'description' => 'Aras 5 Desi'],
            ['rule_key' => 'desi_10',   'rule_value' => '153.48', 'category' => 'Aras', 'valid_from' => '2026-01-01', 'description' => 'Aras 10 Desi'],
            ['rule_key' => 'desi_15',   'rule_value' => '188.82', 'category' => 'Aras', 'valid_from' => '2026-01-01', 'description' => 'Aras 15 Desi'],

            // Sürat
            ['rule_key' => 'desi_0_2',  'rule_value' => '89.71',  'category' => 'Sürat', 'valid_from' => '2026-01-01', 'description' => 'Sürat 0-2 Desi'],

            // ─── AĞIR KARGO CEZALARI (100+ DESİ) ──────────────
            ['rule_key' => 'heavy_cargo_fee', 'rule_value' => '4250',  'category' => 'Aras',    'valid_from' => '2025-01-01', 'description' => 'Aras 100+ Desi Ağır Kargo Cezası'],
            ['rule_key' => 'heavy_cargo_fee', 'rule_value' => '4500',  'category' => 'Sürat',   'valid_from' => '2025-01-01', 'description' => 'Sürat 100+ Desi Ağır Kargo Cezası'],
            ['rule_key' => 'heavy_cargo_fee', 'rule_value' => '5350',  'category' => 'Yurtiçi', 'valid_from' => '2025-01-01', 'description' => 'Yurtiçi 100+ Desi Ağır Kargo Cezası'],

            // ─── KDV GİDER SABIT ORANI ────────────────────────
            ['rule_key' => 'expense_vat_rate', 'rule_value' => '0.20', 'category' => null, 'valid_from' => '2025-01-01', 'description' => 'Komisyon ve Kargo gider KDV oranı (sabit %20)'],
        ];

        foreach ($rules as $rule) {
            MpFinancialRule::updateOrCreate(
                [
                    'rule_key'   => $rule['rule_key'],
                    'category'   => $rule['category'],
                    'valid_from' => $rule['valid_from'],
                ],
                array_merge($rule, ['marketplace' => 'trendyol'])
            );
        }
    }
}
