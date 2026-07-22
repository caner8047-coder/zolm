<?php

namespace App\Modules\Hr\Payroll\Services;

use Illuminate\Validation\ValidationException;

class PayrollItemCatalog
{
    /**
     * Kalem sınıflandırması istemciden gelen vergi bayraklarına güvenilmesini önler.
     * Parasal sınırlar ve dönemsel oranlar sürümlü bordro kuralında yönetilir.
     */
    private const ITEMS = [
        'BONUS' => ['name' => 'Prim / ikramiye', 'type' => 'earning', 'social_security_exempt' => false, 'income_tax_exempt' => false, 'pre_tax_deduction' => false],
        'MEAL_EXEMPT' => ['name' => 'Yemek yardımı istisna kalemi', 'type' => 'earning', 'social_security_exempt' => true, 'income_tax_exempt' => true, 'pre_tax_deduction' => false],
        'TRAVEL_EXEMPT' => ['name' => 'Yol yardımı istisna kalemi', 'type' => 'earning', 'social_security_exempt' => true, 'income_tax_exempt' => true, 'pre_tax_deduction' => false],
        'OTHER_EARNING' => ['name' => 'Diğer vergili kazanç', 'type' => 'earning', 'social_security_exempt' => false, 'income_tax_exempt' => false, 'pre_tax_deduction' => false],
        'ADVANCE_DEDUCTION' => ['name' => 'Avans mahsup kesintisi', 'type' => 'deduction', 'social_security_exempt' => false, 'income_tax_exempt' => false, 'pre_tax_deduction' => false],
        'COURT_DEDUCTION' => ['name' => 'İcra / nafaka kesintisi', 'type' => 'deduction', 'social_security_exempt' => false, 'income_tax_exempt' => false, 'pre_tax_deduction' => false],
        'PRE_TAX_DEDUCTION' => ['name' => 'Vergi öncesi yasal kesinti', 'type' => 'deduction', 'social_security_exempt' => false, 'income_tax_exempt' => false, 'pre_tax_deduction' => true],
        'EMPLOYER_INCENTIVE' => ['name' => 'İşveren SGK teşviki', 'type' => 'employer_incentive', 'social_security_exempt' => false, 'income_tax_exempt' => false, 'pre_tax_deduction' => false],
        'EMPLOYER_BENEFIT' => ['name' => 'İşveren yan hak maliyeti', 'type' => 'employer_benefit', 'social_security_exempt' => false, 'income_tax_exempt' => false, 'pre_tax_deduction' => false],
    ];

    public function all(): array
    {
        return self::ITEMS;
    }

    public function get(string $code): array
    {
        $normalized = strtoupper(trim($code));
        if (! isset(self::ITEMS[$normalized])) {
            throw ValidationException::withMessages([
                'adjustmentCode' => 'Standart bordro kalem kataloğundan geçerli bir kalem seçin.',
            ]);
        }

        return ['code' => $normalized, ...self::ITEMS[$normalized]];
    }
}
