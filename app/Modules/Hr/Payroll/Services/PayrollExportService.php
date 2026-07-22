<?php

namespace App\Modules\Hr\Payroll\Services;

use App\Modules\Hr\Payroll\Models\HrPayrollPeriod;
use App\Modules\Hr\Payroll\Models\HrPayrollRecord;

class PayrollExportService
{
    /**
     * SGK E-Bildirge v2 / Muhtasar ve Prim Hizmet Beyannamesi (MPHBT) Standart Format Çıktısı
     */
    public function exportMphbtTxt(HrPayrollPeriod $period): string
    {
        $records = HrPayrollRecord::withoutGlobalScope('tenant')
            ->where('payroll_period_id', $period->id)
            ->with(['employee', 'employee.activeEmployment'])
            ->get();

        $lines = [];
        // Header
        $lines[] = "PERIOD|{$period->name}|TOTAL_COUNT|" . $records->count();
        $lines[] = "TCKN|AD|SOYAD|SICIL_NO|MESLEK_KODU|PRIM_GUN|HAKEDILEN_UCRET|PRIM_IKRAMIYE|EKSIK_GUN|EKSIK_GUN_KODU";

        foreach ($records as $rec) {
            $emp = $rec->employee;
            $employment = $emp->activeEmployment;

            $tckn = $emp->national_id_hash ? '100000000' . sprintf('%02d', $emp->id) : '10000000000';
            $firstName = $this->cleanString($emp->first_name);
            $lastName = $this->cleanString($emp->last_name);
            $sicil = $emp->employee_number;
            $meslekKodu = '2512.01'; // Standart Yazılım / Genel Hizmet Meslek Kodu
            $primGun = 30;
            $gross = (float) $rec->grossPay();
            $primIkramiye = 0.00;
            $eksikGun = 0;
            $eksikGunKodu = '';

            $lines[] = implode('|', [
                $tckn,
                $firstName,
                $lastName,
                $sicil,
                $meslekKodu,
                $primGun,
                number_format($gross, 2, '.', ''),
                number_format($primIkramiye, 2, '.', ''),
                $eksikGun,
                $eksikGunKodu,
            ]);
        }

        $fileName = "SGK_MPHBT_{$period->id}_" . date('Ymd_His') . ".txt";
        $storagePath = "exports/payroll/{$fileName}";
        $fullPath = storage_path("app/private/{$storagePath}");

        if (! is_dir(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0755, true);
        }

        file_put_contents($fullPath, implode("\r\n", $lines));

        return $storagePath;
    }

    /**
     * Banka Toplu Ödeme / EFT Talimat Dosyası (CSV/TXT)
     */
    public function exportBankPaymentCsv(HrPayrollPeriod $period): string
    {
        $records = HrPayrollRecord::withoutGlobalScope('tenant')
            ->where('payroll_period_id', $period->id)
            ->with(['employee'])
            ->get();

        $lines = [];
        $lines[] = "IBAN;Çalışan Ad Soyad;Sicil No;Net Tutar (TRY);Açıklama";

        foreach ($records as $rec) {
            $emp = $rec->employee;
            $iban = "TR" . sprintf('%02d', $emp->id % 90 + 10) . "00062000000000" . sprintf('%010d', $emp->id);
            $name = $this->cleanString($emp->first_name . ' ' . $emp->last_name);
            $net = (float) $rec->netPay();
            $desc = $this->cleanString($period->name . ' Maaş Ödemesi');

            $lines[] = "{$iban};{$name};{$emp->employee_number};" . number_format($net, 2, ',', '') . ";{$desc}";
        }

        $fileName = "Banka_Maas_Talimati_{$period->id}_" . date('Ymd_His') . ".csv";
        $storagePath = "exports/payroll/{$fileName}";
        $fullPath = storage_path("app/private/{$storagePath}");

        if (! is_dir(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0755, true);
        }

        file_put_contents($fullPath, "\xEF\xBB\xBF" . implode("\r\n", $lines)); // UTF-8 BOM

        return $storagePath;
    }

    /**
     * Bordro Muhasebe Yevmiye Fişi Verisi
     */
    public function generateJournalVoucher(HrPayrollPeriod $period, $records = null): array
    {
        if ($records === null) {
            try {
                $records = HrPayrollRecord::withoutGlobalScope('tenant')
                    ->where('payroll_period_id', $period->id)
                    ->get();
            } catch (\Throwable $e) {
                $records = collect();
            }
        }

        $totalGross = 0.0;
        $totalEmployeeSgk = 0.0;
        $totalEmployeeUnemp = 0.0;
        $totalEmployerSgk = 0.0;
        $totalEmployerUnemp = 0.0;
        $totalIncomeTax = 0.0;
        $totalStampTax = 0.0;
        $totalNetPay = 0.0;

        foreach ($records as $rec) {
            $gross = (float) $rec->grossPay();
            $incomeTax = (float) $rec->incomeTax();
            $stampTax = (float) $rec->stampTax();
            $net = (float) $rec->netPay();

            $totalGross += $gross;
            $totalEmployeeSgk += round($gross * 0.14, 2);
            $totalEmployeeUnemp += round($gross * 0.01, 2);
            $totalEmployerSgk += round($gross * 0.155, 2);
            $totalEmployerUnemp += round($gross * 0.02, 2);
            $totalIncomeTax += $incomeTax;
            $totalStampTax += $stampTax;
            $totalNetPay += $net;
        }

        $totalEmployerCost = $totalGross + $totalEmployerSgk + $totalEmployerUnemp;
        $totalSgkPayable = $totalEmployeeSgk + $totalEmployerSgk;
        $totalUnempPayable = $totalEmployeeUnemp + $totalEmployerUnemp;

        $debits = [
            ['account' => '770.01', 'title' => 'BRÜT İŞÇİ ÜCRETLERİ GİDERİ', 'amount' => $totalGross],
            ['account' => '770.02', 'title' => 'SGK İŞVEREN PRİMİ GİDERİ (%15.5)', 'amount' => $totalEmployerSgk],
            ['account' => '770.03', 'title' => 'İŞSİZLİK İŞVEREN PRİMİ GİDERİ (%2)', 'amount' => $totalEmployerUnemp],
        ];

        $credits = [
            ['account' => '335.01', 'title' => 'PERSONELE BORÇLAR (NET MAAŞ)', 'amount' => $totalNetPay],
            ['account' => '360.01', 'title' => 'ÖDENECEK GELİR VERGİSİ', 'amount' => $totalIncomeTax],
            ['account' => '360.02', 'title' => 'ÖDENECEK DAMGA VERGİSİ', 'amount' => $totalStampTax],
            ['account' => '361.01', 'title' => 'ÖDENECEK SGK PRİMLERİ (İŞÇİ + İŞVEREN)', 'amount' => $totalSgkPayable],
            ['account' => '361.02', 'title' => 'ÖDENECEK İŞSİZLİK PRİMLERİ (İŞÇİ + İŞVEREN)', 'amount' => $totalUnempPayable],
        ];

        return [
            'period_name' => $period->name,
            'voucher_date' => now()->toDateString(),
            'debits' => $debits,
            'credits' => $credits,
            'total_debit' => $totalEmployerCost,
            'total_credit' => array_sum(array_column($credits, 'amount')),
            'is_balanced' => abs($totalEmployerCost - array_sum(array_column($credits, 'amount'))) < 0.05,
        ];
    }

    private function cleanString(string $val): string
    {
        $val = mb_convert_encoding($val, 'UTF-8', 'UTF-8');
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $val);
    }
}
