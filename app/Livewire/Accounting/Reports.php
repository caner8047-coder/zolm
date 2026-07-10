<?php

namespace App\Livewire\Accounting;

use App\Services\Accounting\ReportService;
use App\Services\ExcelService;
use Livewire\Component;

class Reports extends Component
{
    public string $reportType = 'trial_balance'; // trial_balance, balance_sheet, income_statement
    public string $dateFrom = '';
    public string $dateTo = '';

    public array $reportData = [];

    public string $message = '';
    public string $messageType = 'success';

    public function mount(): void
    {
        $this->dateFrom = now()->startOfYear()->toDateString();
        $this->dateTo = now()->toDateString();
        $this->runReport();
    }

    public function runReport(): void
    {
        $userId = auth()->id();
        $service = app(ReportService::class);

        $this->message = '';

        try {
            if ($this->reportType === 'trial_balance') {
                $this->reportData = $service->getTrialBalance($userId, $this->dateFrom, $this->dateTo);
            } elseif ($this->reportType === 'balance_sheet') {
                $this->reportData = $service->getBalanceSheet($userId, $this->dateTo);
            } elseif ($this->reportType === 'income_statement') {
                $this->reportData = $service->getProfitLossSummary($userId, $this->dateFrom, $this->dateTo);
            }
        } catch (\Exception $e) {
            $this->message = 'Rapor hazırlanırken hata oluştu: ' . $e->getMessage();
            $this->messageType = 'error';
            $this->reportData = [];
        }
    }

    public function exportExcel()
    {
        $userId = auth()->id();
        $excelService = app(ExcelService::class);
        $tempPath = storage_path('app/temp_report_' . time() . '.xlsx');

        try {
            $sheets = [];

            if ($this->reportType === 'trial_balance') {
                $data = [];
                foreach ($this->reportData as $row) {
                    $data[] = [
                        'Hesap Kodu' => $row['code'],
                        'Hesap Adı' => $row['name'],
                        'Borç Toplamı' => $row['debit'],
                        'Alacak Toplamı' => $row['credit'],
                        'Borç Bakiyesi' => $row['debit_balance'],
                        'Alacak Bakiyesi' => $row['credit_balance'],
                    ];
                }
                $sheets[] = [
                    'name' => 'Mizan',
                    'data' => $data,
                ];
            } elseif ($this->reportType === 'balance_sheet') {
                $assets = [];
                foreach ($this->reportData['assets'] ?? [] as $row) {
                    $assets[] = [
                        'Hesap Kodu' => $row['code'],
                        'Hesap Adı' => $row['name'],
                        'Bakiye (TRY)' => $row['balance'],
                    ];
                }
                $assets[] = [
                    'Hesap Kodu' => 'TOPLAM',
                    'Hesap Adı' => 'TOPLAM AKTİFLER',
                    'Bakiye (TRY)' => $this->reportData['total_assets'] ?? 0,
                ];

                $passives = [];
                foreach ($this->reportData['liabilities_and_equity'] ?? [] as $row) {
                    $passives[] = [
                        'Hesap Kodu' => $row['code'],
                        'Hesap Adı' => $row['name'],
                        'Bakiye (TRY)' => $row['balance'],
                    ];
                }
                $passives[] = [
                    'Hesap Kodu' => 'TOPLAM',
                    'Hesap Adı' => 'TOPLAM PASİFLER & ÖZKAYNAK',
                    'Bakiye (TRY)' => $this->reportData['total_liabilities_and_equity'] ?? 0,
                ];

                $sheets[] = [
                    'name' => 'Aktifler',
                    'data' => $assets,
                ];
                $sheets[] = [
                    'name' => 'Pasifler',
                    'data' => $passives,
                ];
            } elseif ($this->reportType === 'income_statement') {
                $data = [
                    [
                        'Açıklama' => 'Brüt Satış Gelirleri (600)',
                        'Tutar (TRY)' => $this->reportData['gross_revenue'] ?? 0,
                    ],
                    [
                        'Açıklama' => 'Faaliyet Giderleri',
                        'Tutar (TRY)' => $this->reportData['total_expense'] ?? 0,
                    ],
                    [
                        'Açıklama' => 'Dönem Net Kâr / Zararı',
                        'Tutar (TRY)' => $this->reportData['net_profit'] ?? 0,
                    ]
                ];
                $sheets[] = [
                    'name' => 'Gelir Tablosu',
                    'data' => $data,
                ];
            }

            if (empty($sheets)) {
                $this->message = 'Dışa aktarılacak veri bulunamadı.';
                $this->messageType = 'error';
                return null;
            }

            $excelService->exportToXlsx($sheets, $tempPath);

            return response()->download($tempPath, $this->reportType . '_raporu.xlsx')->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            $this->message = 'Excel oluşturulurken hata: ' . $e->getMessage();
            $this->messageType = 'error';
            return null;
        }
    }

    public function render()
    {
        return view('livewire.accounting.reports')
            ->layout('layouts.app');
    }
}
